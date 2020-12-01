<?php
    /**
     * @class  experienceAdminView
     * @author CONORY (http://www.conory.com)
	 * @brief The admin view class of the experience module
     **/
	class experienceAdminView extends experience
	{
		/**
		 * @brief Initialization
		 */
		function init()
		{
			$config = $this->getConfig();
			Context::set('config', $config);
			
			$this->setTemplatePath($this->module_path.'tpl');
		}

		/**
		 * @brief 기본 설정
		 */
		function dispExperienceAdminConfig()
		{
			$level_icon_list = FileHandler::readDir("./modules/experience/icons");
			Context::set('level_icon_list', $level_icon_list);

			$medal_icon_list = FileHandler::readDir("./modules/experience/medal");
			Context::set('medal_icon_list', $medal_icon_list);

			//그룹 목록
			$oMemberModel = getModel('member');
			$group_list = $oMemberModel->getGroups();
			Context::set('group_list', $group_list);
			
			//포인트 기능 활성화여부
			$oModuleModel = getModel('module');
			$config = $oModuleModel->getModuleConfig('point');
			if($config->able_module == 'N')
			{
				Context::set('no_point_module', true);
			}
			
			$this->setTemplateFile('config');
		}
		
		/**
		 * @brief 경험치 회원 목록
		 */
		function dispExperienceAdminMemberList()
		{
			$oMemberModel = getModel('member');
			$memberConfig = $oMemberModel->getMemberConfig();
			Context::set('identifier', $memberConfig->identifier);
			
			$this->group_list = $oMemberModel->getGroups();
			Context::set('group_list', $this->group_list);
			
			$oExperienceModel = getModel('experience');
			$columnList = array('member.member_srl', 'member.user_id', 'member.email_address', 'member.nick_name', 'experience.experience');
			
			$args = new stdClass;
			$args->list_count = 20;
			$args->page = Context::get('page');
			$output = $oExperienceModel->getMemberList($args, $columnList);
			
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('member_list', $output->data);
			Context::set('page_navigation', $output->page_navigation);
			
			$this->setTemplateFile('member_list');
		}
	}
