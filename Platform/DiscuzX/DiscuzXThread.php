<?php

namespace kernel\Platform\DiscuzX;

use kernel\Foundation\BaseObject;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXModel;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class DiscuzXThread extends BaseObject
{
  function changeThreadViews($threadId, $newViews)
  {
    return \C::t('forum_thread')->increase($threadId, array('views' => $newViews), true);
  }
  /**
   * 获取主题附件
   *
   * @param int|array $ThreadId 主题ID或者主题ID数组
   * @param boolean $onlyImage 只获取图片附件
   * @return array
   */
  function getThreadAttachments($ThreadId, $onlyImage = false)
  {
    $FPM = new DiscuzXModel("forum_post");
    $posts = $FPM->field("pid", "tid")->where([
      "tid" => $ThreadId,
      "first" => 1
    ])->getAll();
    $postAttachs = DiscuzXPost::singleton()->getThreadPostAttachments(array_column($posts, "pid"), $onlyImage);
    $posts = Arr::indexToAssoc($posts, "pid");
    $threadAttachs = [];
    foreach ($postAttachs as $postId => $attachs) {
      $threadId = $posts[$postId]['tid'];
      $threadAttachs[$threadId] = array_values($attachs);
    }

    return $threadAttachs;
  }
}
