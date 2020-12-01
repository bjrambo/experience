<?php
    /**
     * @class  experienceAdminController
     * @author CONORY (http://www.conory.com)
	 * @brief The admin controller class of the experience module
     **/
	 
	class experienceAdminController extends experience
	{
		/**
		 * @brief Initialization
		 */
		function init()
		{
		}

		/**
		 * @brief 기본 설정
		 */
		function procExperienceAdminInsertConfig()
		{
			$args = Context::getRequestVars();
			
			$oExperienceModel = getModel('experience');
			$config = $oExperienceModel->getModuleConfig();
			
			$config->max_level = $args->max_level;
			if($config->max_level>1000) $config->max_level = 1000;
			if($config->max_level<1) $config->max_level = 1;
			$config->level_icon = $args->level_icon;
			$config->medal_icon = $args->medal_icon;

			$config->medal_diamond = $args->medal_diamond;
			$config->medal_platinum = $args->medal_icon;
			$config->medal_gold = $args->medal_gold;
			$config->medal_silver = $args->medal_silver;
			$config->medal_bronze = $args->medal_bronze;
			
			if($args->ncenter_levelup == 'Y')
			{
				$config->ncenter_levelup = 'Y';
			}
			else
			{
				$config->ncenter_levelup = 'N';
			}
			$config->link_group_mode = $args->link_group_mode;
			$config->experience_act = $args->experience_act;
			
			$oMemberModel = getModel('member');
			$group_list = $oMemberModel->getGroups();
			
			//그룹 연동
			foreach($group_list as $group)
			{
				if($group->is_admin == 'Y' || $group->is_default == 'Y') continue;
				
				$group_srl = $group->group_srl;
				if(isset($args->{'experience_group_'.$group_srl}))
				{
					if($args->{'experience_group_'.$group_srl} > $args->max_level)
					{
						$args->{'experience_group_'.$group_srl} = $args->max_level;
					}
					
					if($args->{'experience_group_'.$group_srl} < 1)
					{
						$args->{'experience_group_'.$group_srl} = 1;
					}
					
					$config->experience_group[$group_srl] = $args->{'experience_group_'.$group_srl};
				}
				else
				{
					unset($config->experience_group[$group_srl]);
				}
			}
			
			$config->group_reset = $args->group_reset;
			
			unset($config->level_step);
			for($i=1;$i <= $config->max_level;$i++)
			{
				$key = "level_step_".$i;
				$config->level_step[$i] = (int)$args->{$key};
			}
			$config->expression = $args->expression;
			
			$oModuleController = getController('module');
			$oModuleController->insertModuleConfig('experience', $config);
			
			$this->setMessage('success_updated');

			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispExperienceAdminConfig');
			$this->setRedirectUrl($returnUrl);
		}
		
		/**
		 * @brief 회원 경험치 변경
		 */
		function procExperienceAdminUpdateExperience()
		{
			$member_srl = Context::get('member_srl');
			$experience = Context::get('experience');

			preg_match('/^(\+|-)?([1-9][0-9]*)$/', $experience, $m);

			$action = '';
			switch($m[1])
			{
				case '+':
					$action = 'add';
					break;
				case '-':
					$action = 'minus';
					break;
				default:
					$action = 'update';
					break;
			}
			$experience = $m[2];

			$oExperienceController = getController('experience');
			$output = $oExperienceController->setExperience($member_srl, (int)$experience, $action);

			$this->setError(-1);
			$this->setMessage('success_updated', 'info');
			
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispExperienceAdminMemberList');
			return $this->setRedirectUrl($returnUrl, $output);
		}
		
		/**
		 * @brief 경험치 포인트 동기화
		 */
		function procExperienceAdminSyncPoint()
		{
			@set_time_limit(0);
			
			$oExperienceModel = getModel('experience');
			$config = $oExperienceModel->getModuleConfig();
			if($config->sync_point) return new BaseObject(-1,'msg_invalid_request');
			
			$output = executeQueryArray('experience.getPoint');
			if(!$output->toBool()) return $output;

			if($output->data)
			{
				$oExperienceController = getController('experience');
				foreach($output->data as $key => $val)
				{
					$oExperienceController->setExperience($val->member_srl, $val->point);
				}
			}
			
			$config->sync_point = true;
			
			$oModuleController = getController('module');
			$oModuleController->insertModuleConfig('experience', $config);
			
			$this->setMessage('success_updated');
		}
	}
