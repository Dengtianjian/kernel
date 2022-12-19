<?php

use gstudio_kernel\Foundation\Config;
use gstudio_kernel\Foundation\File;
use gstudio_kernel\Foundation\Iuu;

if (!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

include_once(DISCUZ_ROOT . "source/plugin/gstudio_kernel/Autoload.php");

$sql = <<<SQL
DROP TABLE IF EXISTS `pre_gstudio_kernel_extensions`;
DROP TABLE IF EXISTS `pre_gstudio_kernel_logins`;
SQL;

runquery($sql);

$Iuu = new Iuu("gstudio_kernel", null);
$Iuu->uninstall();

$finish = TRUE;
