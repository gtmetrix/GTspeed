<?php

require_once(dirname(dirname(__FILE__)).'/app/Mage.php');

$min_errorLogger = Mage::getStoreConfigFlag('gtspeed/cssdebug/errorlogger') ? true : false;
$min_allowDebugFlag = Mage::getStoreConfigFlag('gtspeed/cssdebug/debugflag') ? true : false;
