<?php

use gstudio_kernel\Foundation\Iuu;

if (!defined("IN_DISCUZ") || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

include_once(DISCUZ_ROOT . "source/plugin/gstudio_kernel/Autoload.php");

$Iuu = new Iuu("gstudio_kernel", $_GET['fromversion']);
$Iuu->upgrade();

$finish = TRUE;