<?php

namespace kernel\Modules\DiscuzX\Member;

use kernel\Modules\DiscuzX\Model\System\CommonUserGroupModel;

class DiscuzXMemberGroup
{
  public static function all()
  {
    return CommonUserGroupModel::get();
  }
}
