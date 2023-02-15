<?php

namespace kernel\Platform\DiscuzX\Member;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\HTTP\Response\ResponseError;
use kernel\Foundation\Response;
use kernel\Foundation\ReturnResult;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXModel;

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
   * @return ReturnResult 会员信息
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
      return new ResponseError(403, 403001, "密码错误次数过多，请 15 分钟后重新登录", [
        "loginCount" => 0
      ]);
    }

    $userLoginResult = userlogin($username, $password, null, null);
    if ($userLoginResult['status'] <= 0) {
      \C::t('common_failedlogin')->update_failed($_G['clientip']);
      failedip();
      $loginCount = $loginperm - 1;
      return new ResponseError(400, 400001, "登录失败，您还可以尝试 {$loginCount} 次", [
        "loginCount" => $loginCount
      ]);
    }
    setloginstatus($userLoginResult['member'], 0);
    \C::t('common_member_status')->update($_G['uid'], array('lastip' => $_G['clientip'], 'port' => $_G['remoteport'], 'lastvisit' => TIMESTAMP, 'lastactivity' => TIMESTAMP));

    uc_user_synlogin($_G['uid']); //* UC同步登录

    return new ReturnResult($userLoginResult['member']);
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
    ])->getAll();
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
    if (!$member) return null;
    if ($detailed) {
      $member['group'] = self::group($member['groupid']);
      $member['credit'] = self::credit($userId);
      $member['prompts'] = self::newPrompt($userId);

      $userForumFields = \C::t("common_member_field_forum")->fetch($userId);
      $member['sightml'] = preg_replace("/<img|img>/", "<span", $userForumFields['sightml']);

      \ksort($member);
    }

    $member['regdate'] = dgmdate($member['regdate']);
    global $_G;
    $_G['setting']['dynavt'] = 1;
    $member['avatar'] = \avatar($userId, "middle", true);

    return $member;
  }
  public static function getAll($page = 1, $limit = 15, $query = null, $accurateQuery = false)
  {
    $CM = new DiscuzXModel("common_member");
    if ($query) {
      $userQueryValue = "%" . $query . "%";
      if ($accurateQuery) {
        $userQueryValue = $query;
      }
      $CM->where("username", $userQueryValue, $accurateQuery ? '=' : "LIKE", "OR");
      $CM->where("uid", $query, "=");
    }
    $CMT = clone $CM;
    $CM->page($page, $limit);
    $Members = $CM->getAll();
    foreach ($Members as &$MemberItem) {
      $MemberItem['avatar'] = avatar($MemberItem['uid'], "middle", 1);
    }
    return [
      "list" => $Members,
      "total" => $CMT->count()
    ];
  }
}
