<?php

/**
 * @class  experienceController
 * @author CONORY (http://www.conory.com)
 * @brief Controller class of experience modules
 **/
class experienceController extends experience
{
	/**
	 * @brief Initialization
	 */
	function init()
	{
	}

	/**
	 * @brief 포인트 증감 트리거
	 **/
	function triggerSetPoint(&$obj)
	{
		$act = Context::get('act');

		//관리자의 포인트 수동조작은 무조건 경험치에서 제외
		if ($act == 'procPointAdminUpdatePoint')
		{
			return new BaseObject();
		}

		$_point_act = array(
			'procMemberLogin',
			'procMemberInsert',
			'procBoardInsertDocument',
			'procBoardDeleteDocument',
			'procBoardInsertComment',
			'procBoardDeleteComment',
			'procDocumentVoteUp',
			'procDocumentVoteUpCancel',
			'procDocumentVoteDown',
			'procDocumentVoteDownCancel',
			'procCommentVoteUp',
			'procCommentVoteUpCancel',
			'procCommentVoteDown',
			'procCommentVoteDownCancel',
			'procDocumentManageCheckedDocument',
			'procSocialxeConfirmMail',
			'procSocialxeInputAddInfo',
			'procSocialxeCallback',
		);

		$config = $this->getConfig();
		$_experience_act = str_replace("\r", "", $config->experience_act);
		$_experience_act = explode("\n", $_experience_act);

		//지정한 포인트 적립 말고는 모두 경험치에서 제외
		if (!in_array($act, $_point_act) && !in_array($act, $_experience_act))
		{
			return new BaseObject();
		}

		$_minus_point_act = array(
			'procBoardDeleteDocument',
			'procBoardDeleteComment',
			'procDocumentManageCheckedDocument',
			'procDocumentVoteUpCancel',
			'procDocumentVoteDownCancel',
			'procCommentVoteUpCancel',
			'procCommentVoteDownCancel',
		);

		//지정한 act 빼고, 오로지 포인트 적립만 경험치 지급
		if ($obj->current_point >= $obj->set_point && !in_array($act, $_minus_point_act))
		{
			return new BaseObject();
		}
		$point = abs($obj->set_point - $obj->current_point);

		if (in_array($act, $_minus_point_act) && $obj->current_point > $obj->set_point)
		{
			$output = $this->setExperience($obj->member_srl, $point, 'minus');
		}
		else
		{
			$output = $this->setExperience($obj->member_srl, $point, 'add');
		}

		return new BaseObject();
	}

	/**
	 * @brief 경험치 지급
	 */
	function setExperience($member_srl, $experience, $mode = null, $updateMonth = true)
	{
		$member_srl = abs($member_srl);
		$mode_arr = array('add', 'minus', 'update');
		if (!$mode || !in_array($mode, $mode_arr))
		{
			$mode = 'update';
		}

		/** @var experienceModel $oExperienceModel */
		$oExperienceModel = experienceModel::getInstance();
		$config = $this->getConfig();

		$current_experience = $oExperienceModel->getExperience($member_srl, true);
		$current_level = $oExperienceModel->getLevel($current_experience, $config->level_step);

		$args = new stdClass;
		$args->member_srl = $member_srl;
		$args->experience = $current_experience;

		switch ($mode)
		{
			case 'add' :
				$args->experience += $experience;
				break;
			case 'minus' :
				$args->experience -= $experience;
				break;
			case 'update' :
				$args->experience = $experience;
				break;
		}

		if ($args->experience < 0)
		{
			$args->experience = 0;
		}
		$experience = $args->experience;

		// before 트리거 호출
		$trigger_obj = new stdClass;
		$trigger_obj->member_srl = $args->member_srl;
		$trigger_obj->mode = $mode;
		$trigger_obj->current_experience = $current_experience;
		$trigger_obj->current_level = $current_level;
		$trigger_obj->set_experience = $experience;
		$trigger_output = ModuleHandler::triggerCall('experience.setExperience', 'before', $trigger_obj);
		if (!$trigger_output->toBool())
		{
			return $trigger_output;
		}

		$oDB = DB::getInstance();
		$oDB->begin();
		if ($oExperienceModel->isExistsExperience($member_srl))
		{
			$output = executeQuery("experience.updateExperience", $args);
		}
		else
		{
			$output = executeQuery("experience.insertExperience", $args);
		}
		
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}
		else
		{
			if($updateMonth)
			{
				$todayMon = date('Ym');
				$monThExperienceData = $oExperienceModel->getMonthExperience($member_srl, $todayMon);

				$args = new stdClass();
				$args->member_srl = $member_srl;
				$args->regdate = $todayMon;

				$point = abs($experience - $current_experience);

				if ($mode == 'minus')
				{
					$point = $point * -1;
				}
				else if ($mode == 'update')
				{
					$point = $experience - $current_experience;
				}

				if ($monThExperienceData)
				{
					if(is_array($monThExperienceData))
					{
						foreach ($monThExperienceData as $monThExperienceDatum)
						{
							$expriencePoint = $monThExperienceDatum->experience;
							break;
						}
					}
					else
					{
						$expriencePoint = $monThExperienceData->experience;
					}
					$args->experience = $expriencePoint + $point;
					$output = executeQuery('experience.updateMonthExperience', $args);
				}
				else
				{
					$args->experience = $point;
					$output = executeQuery('experience.insertMonthExperience', $args);
				}
			}
		}
		
		//레벨변화 적용
		$level = $oExperienceModel->getLevel($experience, $config->level_step);
		if ($level != $current_level)
		{
			$this->applyChangeLevel($member_srl, $experience, $config);

			//레벨업 알림(알림센터)
			if (is_dir('./modules/ncenterlite') && $level > $current_level && $config->ncenter_levelup == 'Y')
			{
				$oNcenterliteController = ncenterliteController::getInstance();

				$body = new stdClass;
				$body->level = $level;

				$args = new stdClass;
				$args->member_srl = $member_srl;
				$args->srl = 1;
				$args->target_srl = 1;
				$args->target_p_srl = 1;
				$args->type = 'U';
				$args->target_type = 'U';
				$args->notify_type = $config->levelup_ntype;
				$args->target_body = serialize($body);
				$args->target_url = getUrl('');
				$args->regdate = date('YmdHis');
				$args->notify = $oNcenterliteController->_getNotifyId($args);
				$notifyOutput = $oNcenterliteController->_insertNotify($args);
			}
		}

		// after 트리거 호출
		$trigger_obj->new_group_list = $GLOBALS['__new_group_list__'];
		$trigger_obj->del_group_list = $GLOBALS['__del_group_list__'];
		$trigger_obj->new_level = $level;
		$trigger_output = ModuleHandler::triggerCall('experience.setExperience', 'after', $trigger_obj);
		if (!$trigger_output->toBool())
		{
			$oDB->rollback();
			return $trigger_output;
		}

		$oDB->commit();

		$cache_path = sprintf('./files/member_extra_info/experience/%s/', getNumberingPath($member_srl));
		FileHandler::makedir($cache_path);

		$cache_filename = sprintf('%s%d.cache.txt', $cache_path, $member_srl);
		FileHandler::writeFile($cache_filename, $experience);

		return $output;
	}

	/**
	 * @brief 레벨변화 적용
	 */
	function applyChangeLevel($member_srl, $experience, $config)
	{
		$experience_group = $config->experience_group;
		if (!$experience_group || !is_array($experience_group))
		{
			return;
		}

		$oMemberModel = memberModel::getInstance();
		$group_list = $oMemberModel->getMemberGroups($member_srl);

		$level = experienceModel::getInstance()->getLevel($experience, $config->level_step);

		$del_group_list = array();
		$new_group_list = array();

		asort($experience_group);
		$default_group = $oMemberModel->getDefaultGroup();

		//설정된 그룹 초기화 후 새 그룹 부여
		if ($config->group_reset != 'N')
		{
			if (in_array($level, $experience_group))
			{
				foreach ($experience_group as $group_srl => $target_level)
				{
					if ($target_level == $level)
					{
						$new_group_list[] = $group_srl;
						$group_level = $target_level;

					}
					else
					{
						$del_group_list[] = $group_srl;
					}
				}

			}
			else
			{
				$i = $level;
				while ($i > 0)
				{
					if (in_array($i, $experience_group))
					{
						foreach ($experience_group as $group_srl => $target_level)
						{
							if ($target_level == $i)
							{
								$new_group_list[] = $group_srl;
								$group_level = $target_level;

							}
							else
							{
								$del_group_list[] = $group_srl;
							}
						}
						break;
					}
					$i--;
				}
			}

			//기본그룹도 제거
			if ($new_group_list[0])
			{
				$del_group_list[] = $default_group->group_srl;
			}

			//해당 레벨의 그룹이 없다면 기본그룹만 추가
			if ($config->link_group_mode == 'Y')
			{
				if (!$new_group_list[0])
				{
					foreach ($experience_group as $group_srl => $target_level)
					{
						$del_group_list[] = $group_srl;
					}
					$new_group_list[] = $default_group->group_srl;
				}
			}
			else if ($new_group_list[0] && $group_level)
			{
				$group_high_level = 0;

				//기존 그룹에서 제일 높은 레벨 구함
				foreach ($experience_group as $group_srl => $target_level)
				{
					if (array_key_exists($group_srl, $group_list) && $group_high_level < $target_level)
					{
						$group_high_level = $target_level;
					}
				}

				//기존그룹의 레벨이 높다면 그대로 유지
				if ($group_level < $group_high_level)
				{
					$del_group_list = array();
					$new_group_list = array();

					foreach ($experience_group as $group_srl => $target_level)
					{
						if ($target_level == $group_high_level)
						{
							$new_group_list[] = $group_srl;
						}
						else
						{
							$del_group_list[] = $group_srl;
						}
					}
					$del_group_list[] = $default_group->group_srl;
				}
			}


			//새 그룹만 부여
		}
		else
		{
			foreach ($experience_group as $group_srl => $target_level)
			{
				if ($target_level <= $level)
				{
					$new_group_list[] = $group_srl;
				}
				else if ($config->link_group_mode == 'Y')
				{
					$del_group_list[] = $group_srl;
				}
			}

			$new_group_list[] = $default_group->group_srl;
		}

		$args = new stdClass;
		$args->member_srl = $member_srl;

		$oMemberController = memberController::getInstance();

		// 그룹제거
		if ($del_group_list[0])
		{
			$_gdel_list = array();

			foreach ($del_group_list as $group_srl)
			{
				if (array_key_exists($group_srl, $group_list))
				{
					$args->group_srl = $group_srl;
					executeQuery('member.deleteMemberGroupMember', $args);

					$_gdel_list[] = $group_srl;
				}
			}
			$oMemberController->clearMemberCache($member_srl);
		}

		// 그룹추가
		if ($new_group_list[0])
		{
			$_gnew_list = array();
			$group_list = $oMemberModel->getMemberGroups($member_srl, 0, true);

			foreach ($new_group_list as $group_srl)
			{
				if (!array_key_exists($group_srl, $group_list))
				{
					$args->group_srl = $group_srl;
					executeQuery('member.addMemberToGroup', $args);

					$_gnew_list[] = $group_srl;
				}
			}
		}

		//변경된 그룹반영을 위해 회원캐시삭제
		if ($_gnew_list[0] || $_gdel_list[0])
		{
			$oMemberController->clearMemberCache($member_srl);
		}

		$GLOBALS['__new_group_list__'] = $_gnew_list;
		$GLOBALS['__del_group_list__'] = $_gdel_list;
	}

	/**
	 * Gift to medal for user.
	 * @return bool
	 */
	public function giftAllMemberMedal()
	{
		$config = $this->getConfig();
		
		// 무조건 지난달.
		$toMonthFirstDay = mktime(0, 0, 0, date("m"), 1, date("Y"));
		$prev_month = strtotime("-1 month", $toMonthFirstDay);
		$prevMonth = date('Ym', $prev_month);

		// 이번달에 메달을 지급한지 채크 이미 지급하였다면 패스~
		$args = new stdClass();
		$args->update_regdate = $prevMonth;
		$getMedalList = executeQueryArray('experience.getMedalList', $args);
		if(count($getMedalList->data) > 0)
		{
			return false;
		}

		// 메달 정보 전부 삭제.
		$deleteOutput = executeQuery('experience.deleteAllMedal');
		if(!$deleteOutput->toBool())
		{
			return false;
		}
		
		/** @var experienceModel $oExperienceModel */
		$oExperienceModel = experienceModel::getInstance();

		$args = new stdClass();
		$args->regdate = $prevMonth;
		$args->exception_member = $config->exception_member;
		$args->list_count = $config->medal_bronze;
		$MonthOutput = executeQuery('experience.getMonthRank', $args);
		$rankCount = 1;

		foreach ($MonthOutput->data as $monthDatum)
		{
			$medalString = "없음";
			if ($rankCount == intval($config->medal_diamond))
			{
				$medal = 'diamond';
				$medalString = '다이아몬드';
			}
			elseif ($rankCount > intval($config->medal_diamond) && $rankCount <= intval($config->medal_platinum))
			{
				$medal = 'platinum';
				$medalString = '플레티넘';
			}
			elseif ($rankCount > intval($config->medal_platinum) && $rankCount <= intval($config->medal_gold))
			{
				$medal = 'gold';
				$medalString = '골드';
			}
			elseif ($rankCount > intval($config->medal_gold) && $rankCount <= intval($config->medal_silver))
			{
				$medal = 'silver';
				$medalString = '실버';
			}
			elseif ($rankCount > intval($config->medal_silver) && $rankCount <= intval($config->medal_bronze))
			{
				$medal = 'bronze';
				$medalString = '브론즈';
			}

			$args = new stdClass();
			$args->member_srl = $monthDatum->member_srl;
			$args->medal = $medal;
			$args->update_regdate = $prevMonth;
			$medas = $oExperienceModel->getMedalByMemberSrl($monthDatum->member_srl);
			if ($medas)
			{
				$output = executeQuery('experience.updateMedal', $args);
			}
			else
			{
				$output = executeQuery('experience.insertMedal', $args);
			}
			$rankCount++;
			//메달 흭득 알림 (알림센터)
			if (is_dir('./modules/ncenterlite'))
			{
				$oNcenterliteController = ncenterliteController::getInstance();

				$body = new stdClass;
				$body->medal = $medalString;

				$args = new stdClass;
				$args->member_srl = $monthDatum->member_srl;
				$args->srl = 1;
				$args->target_srl = 1;
				$args->target_p_srl = 1;
				$args->type = 'U';
				$args->target_type = 'U';
				$args->notify_type = $config->medal_update_ntype;
				$args->target_body = serialize($body);
				$args->target_url = getUrl('');
				$args->regdate = date('YmdHis');
				$args->notify = $oNcenterliteController->_getNotifyId($args);
				$output = $oNcenterliteController->_insertNotify($args);
			}
		}
		return true;
	}
	
	function triggerModuleHandlerInitAfter()
	{
	}
}
