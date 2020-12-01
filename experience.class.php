<?php

/**
 * @class  experience
 * @author CONORY (http://www.conory.com)
 * @brief The parent class of the experience module
 **/
class experience extends ModuleObject
{
	protected $triggers = array(
		array('point.setPoint', 'experience', 'controller', 'triggerSetPoint', 'after'),
	);

	private $oCacheHandler = NULL;

	/**
	 * @brief 모듈 설치
	 */
	function moduleInstall()
	{
		FileHandler::makeDir('./files/member_extra_info/experience');

		$config = new stdClass;
		$config->max_level = 30;
		$config->level_icon = 'default';

		for ($i = 1; $i <= 30; $i++)
		{
			$config->level_step[$i] = pow($i, 2) * 90;
		}

		$oModuleController = getController('module');
		$oModuleController->insertModuleConfig('experience', $config);

		return new BaseObject();
	}

	/**
	 * @brief 업데이트 체크
	 */
	function checkUpdate()
	{
		$oDB = DB::getInstance();
		$oModuleModel = getModel('module');

		//트리커 설치
		foreach ($this->triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				return true;
			}
		}

		//레벨업 알림을 위한 알림타입 설치 (알림센터)
		$config = $oModuleModel->getModuleConfig('experience');
		if (is_dir('./modules/ncenterlite'))
		{
			$oNcenterliteModel = getModel('ncenterlite');
			if (!$config->levelup_ntype || !$oNcenterliteModel->isNotifyTypeExistsbySrl($config->levelup_ntype))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @brief 업데이트
	 */
	function moduleUpdate()
	{
		$oDB = DB::getInstance();
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');

		//트리커 설치
		foreach ($this->triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
			}
		}

		//레벨업 알림을 위한 알림타입 설치 (알림센터)
		$config = $oModuleModel->getModuleConfig('experience');
		if (is_dir('./modules/ncenterlite'))
		{
			$oNcenterliteModel = getModel('ncenterlite');
			if (!$config->levelup_ntype || !$oNcenterliteModel->isNotifyTypeExistsbySrl($config->levelup_ntype))
			{
				$args = new stdClass;
				$args->notify_type_srl = getNextSequence();
				$args->notify_type_id = 'levelup';
				$args->notify_type_args = 'level';
				$args->notify_string = '<strong>레벨 Up!</strong> Lv.%level%이 되셨습니다.';

				$oNcenterliteModel = getModel('ncenterlite');
				$oNcenterliteModel->insertNotifyType($args);

				$config->levelup_ntype = $args->notify_type_srl;
				$oModuleController->insertModuleConfig('experience', $config);
			}
		}

		return new BaseObject(0, 'success_updated');
	}

	/**
	 * @brief 모듈제거
	 */
	function recompileCache()
	{
	}

	function getCacheHandler()
	{
		if ($this->oCacheHandler === NULL)
		{
			$this->oCacheHandler = CacheHandler::getInstance('object');
			if (!$this->oCacheHandler->isSupport())
			{
				$this->oCacheHandler = false;
			}
		}
		return $this->oCacheHandler;
	}
}
