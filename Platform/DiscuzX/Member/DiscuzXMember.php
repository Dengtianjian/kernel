<?php

namespace kernel\Platform\DiscuzX\Member;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Response;
use kernel\Platform\DiscuzX\Foundation\DiscuzXModel;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class DiscuzXMember
{
  /**
   * 账号密码登录
   *
   * @param string $username 会员账号
   * @param string $password 会员密码
   * @return array 会员信息
   */
  static function login($username, $password)
  {
    global $_G;
    include_once libfile("function/member");

    $login = \C::t('common_failedlogin')->fetch_ip($_G['clientip']);
    $loginPerm = (!$login || (TIMESTAMP - $login['lastupdate'] > 900)) ? 5 : max(0, 5 - $login['count']);
    if (!$login) {
      \C::t('common_failedlogin')->insert(array(
        'ip' => $_G['clientip'],
        'count' => 0,
        'lastupdate' => TIMESTAMP
      ), false, true);
    } elseif (TIMESTAMP - $login['lastupdate'] > 900) {
      \C::t('common_failedlogin')->insert(array(
        'ip' => $_G['clientip'],
        'count' => 0,
        'lastupdate' => TIMESTAMP
      ), false, true);
      \C::t('common_failedlogin')->delete_old(901);
    }

    if ($loginPerm == 0) {
      Response::error(403, 403001, "密码错误次数过多，请 15 分钟后重新登录", [
        "loginCount" => 0
      ]);
    }

    $userLoginResult = userlogin($username, $password, null, null);
    if ($userLoginResult['status'] <= 0) {
      \C::t('common_failedlogin')->update_failed($_G['clientip']);
      failedip();
      $loginCount = $loginperm - 1;
      Response::error(400, 400001, "登录失败，您还可以尝试 {$loginCount} 次", [
        "loginCount" => $loginCount
      ]);
    }
    setloginstatus($userLoginResult['member'], 0);
    \C::t('common_member_status')->update($_G['uid'], array('lastip' => $_G['clientip'], 'port' => $_G['remoteport'], 'lastvisit' => TIMESTAMP, 'lastactivity' => TIMESTAMP));

    uc_user_synlogin($_G['uid']); //* UC同步登录

    return $userLoginResult['member'];
  }
  public static function credit($userId = null)
  {
    if ($userId === null) {
      $userId = \getglobal("uid");
    }
    $CMCM = new DiscuzXModel("common_member_count");
    $memberCredit = $CMCM->where([
      "uid" => $userId
    ])->getOne();
    unset($CMCM);
    return $memberCredit;
  }
  public static function group($groupId = null)
  {
    if ($groupId === null) {
      $groupId = \getglobal("member")['groupid'];
    }
    $CUGM = new DiscuzXModel("common_usergroup");
    $memberGroup = $CUGM->where([
      "groupid" => $groupId
    ])->getOne();
    return $memberGroup;
  }
  public static function newPrompt($userId = null)
  {
    if ($userId === null) {
      $userId = \getglobal("uid");
    }
    $CMNP = new DiscuzXModel("common_member_newprompt");
    $prompts = $CMNP->where([
      "uid" => $userId
    ])->getOne();
    foreach ($prompts as &$promptItem) {
      $promptItem = \array_merge($promptItem, \unserialize($promptItem['data']));
      unset($promptItem['data']);
    }
    return $prompts;
  }
  public static function get($userId = null, $detailed = true)
  {
    if ($userId === null) {
      $userId = \getglobal("uid");
    }
    $MM = new DiscuzXModel("common_member");
    $member = $MM->where([
      "uid" => $userId
    ])->getOne();
    if ($detailed) {
      $memberCredit = self::credit($userId);
      $memberCredit = Arr::indexToAssoc($memberCredit, 'uid');
      $memberGroupId = $member['groupid'];
      $memberGroup = self::group($memberGroupId);
      $memberGroup = Arr::indexToAssoc($memberGroup, "groupid");
      $memberPrompt = self::newPrompt($userId);
      $memberPrompt = Arr::indexToAssoc($memberPrompt, "uid");
      $member['group'] = $memberGroup[$member['groupid']];
      $member['credit'] = $memberCredit[$member['uid']];
      $member['prompts'] = $memberPrompt[$member['uid']];

      $userForumFields = \C::t("common_member_field_forum")->fetch($userId);
      $member['sightml'] = preg_replace("/<img|img>/", "<span", $userForumFields['sightml']);

      \ksort($member);
    }
    $member['regdate'] = dgmdate($member['regdate']);
    $member['avatar'] = avatar($userId, "middle", true);

    return $member;
  }
}