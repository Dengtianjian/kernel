<?php

namespace kernel\Platform\DiscuzX\Member;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\DataConversion;
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
  public static function credit($memberId = null)
  {
    if ($memberId === null) {
      $memberId = \getglobal("uid");
    }
    $CMCM = new DiscuzXModel("common_member_count");
    $memberCredits = $CMCM->where([
      "uid" => $memberId
    ])->getAll();
    if (!count($memberCredits) && !is_array($memberId)) return null;
    if (is_array($memberId)) return Arr::indexToAssoc($memberCredits, "uid");
    return $memberCredits[0];
  }
  public static function group($groupId = null)
  {
    if ($groupId === null) {
      $groupId = \getglobal("member")['groupid'];
    }
    $CUGM = new DiscuzXModel("common_usergroup");
    $memberGroups = $CUGM->where([
      "groupid" => $groupId
    ])->getAll();
    if (!count($memberGroups) && !is_array($groupId)) return null;
    if (is_array($groupId)) return Arr::indexToAssoc($memberGroups, "groupid");
    return $memberGroups[0];
  }
  public static function newPrompt($memberId = null)
  {
    if ($memberId === null) {
      $memberId = \getglobal("uid");
    }
    $CMNP = new DiscuzXModel("common_member_newprompt");
    $prompts = $CMNP->where([
      "uid" => $memberId
    ])->getAll();
    $prompts = Arr::group($prompts, "uid");
    foreach ($prompts as &$MemberPrompts) {
      foreach ($MemberPrompts as &$promptItem) {
        $promptItem = \array_merge($promptItem, \unserialize($promptItem['data']));
        unset($promptItem['data']);
      }
    }
    if (!count($prompts) && !is_array($memberId)) return null;
    if (is_array($memberId)) return $prompts;
    return $prompts[$memberId];
  }
  public static function get($memberId = null, $detailed = true, $dataConversionRules = null)
  {
    if ($memberId === null) {
      $memberId = \getglobal("uid");
    }
    $MM = new DiscuzXModel("common_member");
    $members = $MM->where([
      "uid" => $memberId
    ])->getAll();
    if (empty($members)) return is_array($memberId) ? [] : null;

    $Groups = [];
    $Credits = [];
    $Prompts = [];
    $userForumFields = [];
    if ($detailed) {
      $Groups = self::group(array_column($members, "groupid"));

      $Credits = self::credit(is_array($memberId) ? $memberId : [$memberId]);
      $Prompts = self::newPrompt(is_array($memberId) ? $memberId : [$memberId]);

      $userForumFields = \C::t("common_member_field_forum")->fetch_all($memberId);
      $userForumFields = Arr::indexToAssoc($userForumFields, "uid");
      foreach ($userForumFields as &$item) {
        $item['sightml'] = preg_replace("/<img|img>/", "<span", $item['sightml']);
      }
    }

    global $_G;
    $_G['setting']['dynavt'] = 1;
    foreach ($members as &$MemberItem) {
      $MemberItem['avatar'] = \avatar($MemberItem, "middle", true);

      if (isset($Groups[$MemberItem['groupid']])) {
        $MemberItem['group'] = $Groups[$MemberItem['groupid']];
      }
      if (isset($Credits[$MemberItem['uid']])) {
        $MemberItem['count'] = $Credits[$MemberItem['groupid']];
      }
      if (isset($Prompts[$MemberItem['uid']])) {
        $MemberItem['prompts'] = $Prompts[$MemberItem['uid']];
      }
      if (isset($userForumFields[$MemberItem['uid']])) {
        $MemberItem['forum_field'] = $userForumFields[$MemberItem['uid']];
      }
    }

    if ($dataConversionRules) {
      $members = DataConversion::quick($members, $dataConversionRules, true, true);
    }

    return is_array($memberId) ? $members : $members[0];
  }
  public static function getAll($page = 1, $limit = 15, $query = null, $accurateQuery = false)
  {
    $CM = new DiscuzXModel("common_member");
    if ($query) {
      $userQueryValue = "%" . $query . "%";
      if ($accurateQuery) {
        $userQueryValue = $query;
      }
      $CM->where("username", $userQueryValue, $accurateQuery ? '=' : "LIKE");
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
