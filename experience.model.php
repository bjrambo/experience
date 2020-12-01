<?php

/**
 * @class  experienceModel
 * @author CONORY (http://www.conory.com)
 * @brief The model class fo the experience module
 **/
class experienceModel extends experience
{
	protected $experienceList = array();

	/**
	 * @brief Initialization
	 */
	function init()
	{
	}

	/**
	 * @brief 모듈설정
	 **/
	function getModuleConfig()
	{
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('experience');

		if (!$config->max_level)
		{
			$config->max_level = 30;
		}
		if ($config->max_level > 1000)
		{
			$config->max_level = 1000;
		}
		if ($config->max_level < 1)
		{
			$config->max_level = 1;
		}

		if (!$config->level_icon)
		{
			$config->level_icon = 'default';
		}
		if (!$config->sync_point)
		{
			$config->sync_point = FALSE;
		}

		return $config;
	}

	/**
	 * @brief 경험치 정보 존재확인
	 */
	function isExistsExperience($member_srl)
	{
		$member_srl = abs($member_srl);
		if ($this->experienceList[$member_srl])
		{
			return true;
		}


		$args = new stdClass;
		$args->member_srl = $member_srl;
		$output = executeQuery('experience.getExperience', $args);
		if ($output->data->member_srl == $member_srl)
		{
			if (!$this->experienceList[$member_srl])
			{
				$this->experienceList[$member_srl] = (int)$output->data->experience;
			}
			return true;
		}

		return false;
	}

	/**
	 * @brief 경험치 가져오기
	 */
	function getExperience($member_srl, $from_db = false)
	{
		$member_srl = abs($member_srl);
		if (!$from_db && $this->experienceList[$member_srl])
		{
			return $this->experienceList[$member_srl];
		}

		//캐시 뒤짐.
		$path = sprintf(_XE_PATH_ . 'files/member_extra_info/experience/%s', getNumberingPath($member_srl));
		$cache_filename = sprintf('%s%d.cache.txt', $path, $member_srl);

		if (!$from_db && file_exists($cache_filename))
		{
			return $this->experienceList[$member_srl] = trim(FileHandler::readFile($cache_filename));
		}

		//DB에서...
		$args = new stdClass;
		$args->member_srl = $member_srl;
		$output = executeQuery('experience.getExperience', $args);

		if (isset($output->data->member_srl))
		{
			$experience = (int)$output->data->experience;
			$this->experienceList[$member_srl] = $experience;

			if (!is_dir($path))
			{
				FileHandler::makeDir($path);
			}
			FileHandler::writeFile($cache_filename, $experience);

			return $experience;
		}

		return 0;
	}

	/**
	 * @brief 레벨 가져오기
	 */
	function getLevel($experience, $level_step)
	{
		$level_count = count($level_step);
		for ($level = 0; $level <= $level_count; $level++)
		{
			if ($experience < $level_step[$level])
			{
				break;
			}
		}

		$level--;

		return $level;
	}

	/**
	 * @brief 레벨 도달 퍼센트 계산
	 */
	function getLevelPer($experience, $config)
	{
		$per = '';

		$level = $this->getLevel($experience, $config->level_step);
		if ($level < $config->max_level)
		{
			$next_experience = $config->level_step[$level + 1];
			$present_experience = $config->level_step[$level];
			if ($next_experience > 0)
			{
				$per = (int)(($experience - $present_experience) / ($next_experience - $present_experience) * 100);
				$per = $per . '%';
			}
		}

		if (!$per)
		{
			$per = '100%';
		}

		return $per;
	}

	/**
	 * @brief 경험치 회원 목록
	 */
	function getMemberList($args = null, $columnList = array())
	{
		if ($args == null)
		{
			$args = new stdClass();
		}
		$args->is_admin = Context::get('is_admin') == 'Y' ? 'Y' : '';
		$args->is_denied = Context::get('is_denied') == 'Y' ? 'Y' : '';
		$args->selected_group_srl = Context::get('selected_group_srl');

		$search_target = trim(Context::get('search_target'));
		$search_keyword = trim(Context::get('search_keyword'));

		if (!$search_keyword)
		{
			unset($args->is_admin, $args->is_denied, $args->selected_group_srl, $search_target);
		}

		if ($search_target && $search_keyword)
		{
			switch ($search_target)
			{
				case 'user_id' :
					if ($search_keyword)
					{
						$search_keyword = str_replace(' ', '%', $search_keyword);
					}
					$args->s_user_id = $search_keyword;
					break;
				case 'user_name' :
					if ($search_keyword)
					{
						$search_keyword = str_replace(' ', '%', $search_keyword);
					}
					$args->s_user_name = $search_keyword;
					break;
				case 'nick_name' :
					if ($search_keyword)
					{
						$search_keyword = str_replace(' ', '%', $search_keyword);
					}
					$args->s_nick_name = $search_keyword;
					break;
				case 'email_address' :
					if ($search_keyword)
					{
						$search_keyword = str_replace(' ', '%', $search_keyword);
					}
					$args->s_email_address = $search_keyword;
					break;
				case 'regdate' :
					$args->s_regdate = $search_keyword;
					break;
				case 'last_login' :
					$args->s_last_login = $search_keyword;
					break;
				case 'extra_vars' :
					$args->s_extra_vars = $search_keyword;
					break;
			}
		}


		if ($args->selected_group_srl)
		{
			$query_id = 'experience.getMemberListWithinGroup';
		}
		else
		{
			$query_id = 'experience.getMemberList';
		}
		debugPrint($query_id);
		$output = executeQuery($query_id, $args, $columnList);
		debugPRint($output);
		if ($output->total_count)
		{
			$oModuleModel = getModel('module');
			$config = $oModuleModel->getModuleConfig('experience');

			foreach ($output->data as $key => $val)
			{
				$output->data[$key]->level = $this->getLevel($val->experience, $config->level_step);
			}
		}

		return $output;
	}

	function getMonthExperience($member_srl, $date)
	{
		if (!$member_srl)
		{
			return false;
		}

		$args = new stdClass();
		$args->regdate = $date;
		$args->member_srl = $member_srl;
		$output = executeQuery('experience.getMonthExperienceByMemberSrl', $args);

		return $output->data;
	}
	
	function getMedalByMemberSrl($member_srl)
	{
		if(!$member_srl)
		{
			return false;
		}
		
		$args = new stdClass();
		$args->member_srl = $member_srl;
		
		return executeQuery('experience.getMedalByMemberSrl', $args)->data;
	}
}
