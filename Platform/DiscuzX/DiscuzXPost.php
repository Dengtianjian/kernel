<?php

namespace kernel\Platform\DiscuzX;

use kernel\Foundation\BaseObject;
use kernel\Foundation\Data\Arr;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXModel;

class DiscuzXPost extends BaseObject
{
  /**
   * 获取主题首个帖子的附件
   *
   * @param int|array $postId 帖子ID或者帖子ID数组
   * @param boolean $onlyImage 只获取图片附件
   * @return array
   */
  function getThreadPostAttachments($postId, $onlyImage = false)
  {
    global $_G;
    $FAM = new DiscuzXModel("forum_attachment");
    $indexTablePostAttachs = $FAM->where("pid", $postId)->getAll();
    $attachGroups = Arr::group($indexTablePostAttachs, "tableid");
    $indexTablePostAttachs = Arr::indexToAssoc($indexTablePostAttachs, "aid");
    $attachs = [];
    $payAttachIds = [];
    foreach ($attachGroups as $tableId => $item) {
      $FANM = new DiscuzXModel("forum_attachment_" . $tableId);
      if ($onlyImage) {
        $FANM->where("isimage", [1, -1]);
      }
      $tableAttachs = $FANM->where("aid", array_column($item, "aid"))->getAll();
      $tableAttachs = Arr::group($tableAttachs, "pid");

      foreach ($tableAttachs as $postId => $postAttachs) {
        if (!isset($attachs[$postId])) {
          $attachs[$postId] = [];
        }

        foreach ($postAttachs as $attachItem) {
          $attachItem['downloads'] = $indexTablePostAttachs[$attachItem['aid']]['downloads'];

          if ($attachItem['price']) {
            if ($_G['setting']['maxchargespan'] && TIMESTAMP - $attachItem['dateline'] >= $_G['setting']['maxchargespan'] * 3600) {
              \C::t('forum_attachment_n')->update_attachment('tid:' . $_G['tid'], $attachItem['aid'], array('price' => 0));
              $attachItem['price'] = 0;
            } elseif (!$_G['forum_attachmentdown'] && $_G['uid'] != $attachItem['uid']) {
              $payAttachIds[$attachItem['aid']] = $attachItem['pid'];
            }
          }
          $payAttachIds[$attachItem['aid']] = $attachItem['pid'];
          $attachItem['payed'] = $_G['forum_attachmentdown'] || $_G['uid'] == $attachItem['uid'] ? 1 : 0;

          $attachItem['url'] = (($attachItem['remote'] ? $_G['setting']['ftp']['attachurl'] : $_G['setting']['attachurl']) . 'forum/') . $attachItem['attachment'];

          $attachs[$postId][$attachItem['aid']] = $attachItem;
        }
      }
    }
    if ($payAttachIds) {
      foreach (\C::t('common_credit_log')->fetch_all_by_uid_operation_relatedid($_G['uid'], 'BAC', array_keys($payAttachIds)) as $creditlog) {
        $attachId = $creditlog['relatedid'];
        $postId = $payAttachIds[$attachId];
        $attachs[$postId][$attachId]['payed'] = true;
      }
    }

    return $attachs;
  }
}
