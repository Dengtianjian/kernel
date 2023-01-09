<?php

use kernel\Foundation\Config;
use kernel\Foundation\File;
use kernel\Foundation\Iuu;

if (!defined('F_KERNEL') || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

include_once(DISCUZ_ROOT . "source/plugin/kernel/Autoload.php");

$sql = <<<SQL
DROP TABLE IF EXISTS `pre_kernel_extensions`;
DROP TABLE IF EXISTS `pre_kernel_logins`;
SQL;

runquery($sql);

$Iuu = new Iuu("kernel", null);
$Iuu->uninstall();

$finish = TRUE;
