<?php

namespace gstudio_kernel\Service;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Data\Arr;
use gstudio_kernel\Foundation\Database\Model;
use gstudio_kernel\Foundation\Service;

class UserService extends Service
{
  protected static $tableName = "common_member";
  public static function getUserCredit($userId = null)
  {
    if ($userId === null) {
      $userId = \getglobal("uid");
    }
    $CMCM = new Model("common_member_count");
    $memberCredit = $CMCM->where([
      "uid" => $userId
    ])->getOne();
    unset($CMCM);
    return $memberCredit;
  }
  public static function getUserGroup($groupId = null)
  {
    if ($groupId === null) {
      $groupId = \getglobal("member")['groupid'];
    }
    $CUGM = new Model("common_usergroup");
    $memberGroup = $CUGM->where([
      "groupid" => $groupId
    ])->getOne();
    return $memberGroup;
  }
  public static function getUserPrompt($userId = null)
  {
    if ($userId === null) {
      $userId = \getglobal("uid");
    }
    $CMNP = new Model("common_member_newprompt");
    $prompts = $CMNP->where([
      "uid" => $userId
    ])->getOne();
    foreach ($prompts as &$promptItem) {
      $promptItem = \array_merge($promptItem, \unserialize($promptItem['data']));
      unset($promptItem['data']);
    }
    return $prompts;
  }
  public static function getUser($userId = null, $detailed = true)
  {
    if ($userId === null) {
      $userId = \getglobal("uid");
    }
    $MM = new Model("common_member");
    $member = $MM->where([
      "uid" => $userId
    ])->getOne();
    if ($detailed) {
      $memberCredit = self::getUserCredit($userId);
      $memberCredit = Arr::indexToAssoc($memberCredit, 'uid');
      $memberGroupId = $member['groupid'];
      $memberGroup = self::getUserGroup($memberGroupId);
      $memberGroup = Arr::indexToAssoc($memberGroup, "groupid");
      $memberPrompt = self::getUserPrompt($userId);
      $memberPrompt = Arr::indexToAssoc($memberPrompt, "uid");
      $member['group'] = $memberGroup[$member['groupid']];
      $member['credit'] = $memberCredit[$member['uid']];
      $member['prompts'] = $memberPrompt[$member['uid']];
      \ksort($member);
    }
    // if (!\is_array($userId)) {
    //   $member = $member[0];
    // }

    return $member;
  }
}
