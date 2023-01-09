<?php

use kernel\Foundation\Iuu;

if (!defined("F_KERNEL") || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

include_once(DISCUZ_ROOT . "source/plugin/kernel/Autoload.php");

$Iuu = new Iuu("kernel", $_GET['fromversion']);
$Iuu->upgrade();

$finish = TRUE;