<?php

namespace kernel\Platform\DiscuzX;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class DiscuzXThread
{
  static function changeThreadViews($threadId, $newViews)
  {
    return \C::t('forum_thread')->increase($threadId, array('views' => $newViews), true);
  }
}