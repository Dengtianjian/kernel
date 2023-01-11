<?php

namespace gstudio_kernel\Platform\Discuzx;

use gstudio_kernel\Foundation\Data\Arr;
use gstudio_kernel\Foundation\Response;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class Member
{
  static function getUser()
  {
    return \getglobal('member');
  }
  static function group()
  {
  }
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
  /**
   * 获取会员数据
   *
   * @param int $userId 会员ID
   * @return array 会员数据
   */
  static function get($userId)
  {
    $userInfo = getuserbyuid($userId);

    $userFieldData = \C::t("common_member_profile")->fetch($userId);
    $userFieldsSetting = \C::t("common_member_profile_setting")->fetch_all_by_available(1);
    $userFields = [];
    foreach ($userFieldsSetting as $fieldKey => $field) {
      $field['content'] = $userFieldData[$fieldKey];
      $userFields[$fieldKey] = $field;
    }

    $userForumFields = \C::t("common_member_field_forum")->fetch($userId);
    $userForumFields['sightml'] = preg_replace("/<img|img>/", "<span", $userForumFields['sightml']);
    $userGroup = \C::t("common_usergroup")->fetch($userInfo['groupid']);
    $userCount = \C::t("common_member_count")->fetch($userId);

    $userInfo = array_merge([
      "profile" => $userFields
    ], $userInfo, $userForumFields, $userGroup, $userCount);

    $userInfo['regdate'] = dgmdate($userInfo['regdate']);
    $userInfo['avatar'] = avatar($userId, "middle", 1);

    return $userInfo;
  }
}
