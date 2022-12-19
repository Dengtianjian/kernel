<?php

namespace gstudio_kernel\Platform\Discuzx;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class Thread
{
  static function changeThreadViews($threadId, $newViews)
  {
    return \C::t('forum_thread')->increase($threadId, array('views' => $newViews), true);
  }
}
