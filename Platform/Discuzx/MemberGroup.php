<?php

namespace gstudio_kernel\Platform\Discuzx;

use gstudio_kernel\Foundation\Data\Arr;
use gstudio_kernel\Foundation\Response;
use gstudio_kernel\Model\DiscuzX\CommonUserGroupModel;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class MemberGroup
{
  public static function all()
  {
    return CommonUserGroupModel::ins()->getAll();
  }
}
