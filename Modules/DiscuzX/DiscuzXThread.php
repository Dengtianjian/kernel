<?php

namespace kernel\Modules\DiscuzX;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Object\BaseObject;
use kernel\Foundation\Database\RDB\Model;


class DiscuzXThread extends BaseObject
{
  function changeThreadViews($threadId,  $newViews)
  {
    return \C::t('forum_thread')->increase($threadId, array('views' => $newViews), true);
  }
  /**
   * 获取主题附件
   *
   * @param int|array $threadId 主题ID或者主题ID数组
   * @param boolean $onlyImage 只获取图片附件
   * @return array
   */
  function getThreadAttachments($threadId, $onlyImage = false)
  {
    $FPM = new Model("forum_post");
    $posts = $FPM->field("pid", "tid")->where([
      "tid" => $threadId,
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
