<?php

/*
 * 클론탭 전용 페이지입니다.
 * 클론탭을 위해서는 다음아래에 규칙을 다음과 같이 이용해야합니다.
 * DB에서 gginstagram_user_id 항목에서 module_srl 과 insta_id를 받아 아래와 같이 입력해줘야합니다.
 */
// it is get comment
// do not update file.
define('__XE__', true);
require_once('../../config/config.inc.php'); //XE config.inc.php 주소
$oContext = Context::getInstance();
$oContext->init();


$display = new DisplayHandler();
$oExperienceController = experienceController::getInstance();
$oExperienceController->giftAllMemberMedal();
$display->getDebugInfo();
