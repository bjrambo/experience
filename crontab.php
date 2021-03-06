<?php
// Abort if not CLI.
if (PHP_SAPI !== 'cli')
{
	echo "This script must be executed on the command line interface.\n";
	exit(1);
}

define('__XE__', true);
require_once('../../config/config.inc.php'); //XE config.inc.php 주소
$oContext = Context::getInstance();
$oContext->init();

$display = new DisplayHandler();
$oExperienceController = experienceController::getInstance();
$oExperienceController->giftAllMemberMedal();
$display->getDebugInfo();
