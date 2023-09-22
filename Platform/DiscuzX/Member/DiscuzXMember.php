<?php

namespace kernel\Platform\DiscuzX\Member;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\DataConversion;
use kernel\Foundation\Date;
use kernel\Foundation\HTTP\Response\ResponseError;
use kernel\Foundation\Response;
use kernel\Foundation\ReturnResult\ReturnResult;
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
   * @param int $cookieTime cookie有效期
   * @return ReturnResult 会员信息
   */
  static function login($username, $password, $cookieTime = 1296000)
  {
    global $_G;
    include_once libfile("function/member");
    $R = new ReturnResult(true);

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
      return $R->error(403, 403001, "密码错误次数过多，请 15 分钟后重新登录", [
        "loginCount" => 0
      ]);
    }

    $userLoginResult = userlogin($username, $password, null, null);
    if ($userLoginResult['status'] <= 0) {
      \C::t('common_failedlogin')->update_failed($_G['clientip']);
      failedip();
      $loginCount = $loginperm - 1;
      return $R->error(400, 400001, "登录失败，您还可以尝试 {$loginCount} 次", [
        "loginCount" => $loginCount
      ]);
    }
    setloginstatus($userLoginResult['member'], $cookieTime);
    \C::t('common_member_status')->update($_G['uid'], array('lastip' => $_G['clientip'], 'port' => $_G['remoteport'], 'lastvisit' => TIMESTAMP, 'lastactivity' => TIMESTAMP));

    uc_user_synlogin($_G['uid']); //* UC同步登录

    return $R->success($userLoginResult['member']);
  }
  /**
   * 注册用户
   *
   * @param string $username 用户名
   * @param string $password 账号密码
   * @param string $email 注册邮箱地址
   * @param string $invationCode 邀请码
   * @return ReturnResult
   */
  public static function register($username, $password, $email = null, $invationCode = null)
  {
    global $_G;
    $R = new ReturnResult(true);

    //* 是否关闭注册
    if ($_G['setting']['regstatus'] == 0 || $_G['setting']['regstatus'] == 2) {
      return $R->error(403, "403:RegisterClosed", $_G['setting']['regclosemessage']);
    }
    include_once libfile("function/member");

    //* 限时注册IP注册间隔限制(小时)
    loadcache(['ipctrl']);
    if ($_G['cache']['ipctrl']['ipregctrl']) {
      foreach (explode("\n", $_G['cache']['ipctrl']['ipregctrl']) as $ctrlip) {
        if (preg_match("/^(" . preg_quote(($ctrlip = trim($ctrlip)), '/') . ")/", $_G['clientip'])) {
          $ctrlip = $ctrlip . '%';
          $_G['setting']['regctrl'] = $_G['setting']['ipregctrltime'];
          break;
        } else {
          $ctrlip = $_G['clientip'];
        }
      }
    } else {
      $ctrlip = $_G['clientip'];
    }

    //* 同一 IP 注册间隔限制(小时)
    if ($_G['setting']['regctrl']) {
      $result = \C::t('common_regip')->count_by_ip_dateline($ctrlip, $_G['timestamp'] - $_G['setting']['regctrl'] * 3600);
      if ($result) {
        return $R->error(403, "403:registrationRestricted", "抱歉，您的 IP 地址在 " . $_G['setting']['regctrl'] . " 小时内无法注册", [
          "regctrl" => $_G['setting']['regctrl']
        ]);
      }
    }

    //* 同一 IP 24小时内允许注册的最大次数
    $setregip = null;
    if ($_G['setting']['regfloodctrl']) {
      $regip = \C::t('common_regip')->fetch_by_ip_dateline($_G['clientip'], $_G['timestamp'] - 86400);
      if ($regip) {
        if ($regip['count'] >= $_G['setting']['regfloodctrl']) {
          return $R->error(403, "403:RegistraLimited24Hours", "抱歉，IP 地址在 24 小时内只能注册 " . $_G['setting']['regfloodctrl'] . " 次", [
            "regfloodctrl" => $_G['setting']['regfloodctrl']
          ]);
        } else {
          $setregip = 1;
        }
      } else {
        $setregip = 2;
      }
    }

    //* 邀请码
    if ($invationCode) {
      $inviteStatus = false;
      if ($_G['setting']['regstatus'] == 2) {
        if ($_G['setting']['inviteconfig']['inviteareawhite']) {
          $location = $whitearea = '';
          $location = trim(convertip($_G['clientip']));
          if ($location) {
            $whitearea = preg_quote(trim($_G['setting']['inviteconfig']['inviteareawhite']), '/');
            $whitearea = str_replace(array("\\*"), array('.*'), $whitearea);
            $whitearea = '.*' . $whitearea . '.*';
            $whitearea = '/^(' . str_replace(array("\r\n", ' '), array('.*|.*', ''), $whitearea) . ')$/i';
            if (@preg_match($whitearea, $location)) {
              $inviteStatus = true;
            }
          }
        }

        if ($_G['setting']['inviteconfig']['inviteipwhite']) {
          foreach (explode("\n", $_G['setting']['inviteconfig']['inviteipwhite']) as $ctrlip) {
            if (preg_match("/^(" . preg_quote(($ctrlip = trim($ctrlip)), '/') . ")/", $_G['clientip'])) {
              $inviteStatus = true;
              break;
            }
          }
        }
      }

      if (!$inviteStatus) {
        $invite = \C::t('common_invite')->fetch_by_code($invationCode);
        if (!$invite) {
          return $R->error(400, "400:BadIntivationCode", "无效的邀请码");
        }
        if ($invite['code'] == $invationCode && empty($invite['fuid']) && (empty($invite['endtime']) || $_G['timestamp'] < $invite['endtime'])) {
          $result['uid'] = $invite['uid'];
          $result['id'] = $invite['id'];
          $member = getuserbyuid($result['uid']);
          $invite['username'] = $member['username'];
        }
      }
    }
    if ($_G['setting']['regstatus'] == 2 && empty($invite) && !$inviteStatus) {
      return $R->error(400, "400:NoOpenRegistrationInvite", "抱歉，本站目前暂时不允许用户直接注册，需要有效的邀请码才能注册");
    }

    loaducenter();

    //* 邮箱验证
    if ($email) {
      $email = strtolower(trim($email));
      if (strlen($email) > 255) {
        return $R->error(400, "400:ProfileEmailIllegal", "Email 地址无效");
      }
      if ($_G['setting']['regmaildomain']) {
        $maildomainexp = '/(' . str_replace("\r\n", '|', preg_quote(trim($_G['setting']['maildomainlist']), '/')) . ')$/i';
        if ($_G['setting']['regmaildomain'] == 1 && !preg_match($maildomainexp, $email)) {
          return $R->error(400, "400:ProfileEmailDomainIllegal", "抱歉，Email 包含不可使用的邮箱域名");
        } elseif ($_G['setting']['regmaildomain'] == 2 && preg_match($maildomainexp, $email)) {
          return $R->error(400, "400:ProfileEmailDomainIllegal", "抱歉，Email 包含不可使用的邮箱域名");
        }
      }

      $ucresult = uc_user_checkemail($email);

      if ($ucresult == -4) {
        return $R->error(400, "400:ProfileEmailIllegal", "Email 地址无效");
      } elseif ($ucresult == -5) {
        return $R->error(400, "400:ProfileEmailDomainIllegal", "抱歉，Email 包含不可使用的邮箱域名");
      } elseif ($ucresult == -6) {
        return $R->error(400, "400:ProfileEmailDuplicate", "该 Email 地址已被注册");
      }
    } else {
      $email = uniqid("email") . "@email.com"; //* 随机邮箱地址
    }

    //* 检测用户名长度
    if (dstrlen($username) < 3) {
      return $R->error(400, "400:UsernameTooShort", "用户名不得少于 3 个字符");
    }
    if (dstrlen($username) > 15) {
      return $R->error(400, "400:UsernameTooLong", "用户名长度不得超过 15 个字符");
    }

    if (uc_get_user(addslashes($username)) && !\C::t('common_member')->fetch_uid_by_username($username) && !\C::t('common_member_archive')->fetch_uid_by_username($username)) {
      return $R->error(400, "400:ProfileUsernameDuplicate", "该用户名已被注册");
    }

    //* 检测是否是 用户信息保留关键字
    $censorUser = $_G['setting']['censoruser'];
    $censorUser = explode("\r\n", $censorUser);
    if (in_array($username, $censorUser)) {
      return $R->error(400, "400:BadUsername", "用户名包含被系统屏蔽的字符");
    }

    //* 检测密码长度
    if (dstrlen($password) < $_G['setting']['pwlength']) {
      return $R->error(400, "400:BadPassword:001", "密码不得少于 " . $_G['setting']['pwlength'] . " 个字符", [
        "passwordMinLength" => $_G['setting']['pwlength']
      ]);
    }

    //* 密码强度检测
    $passwordStorageSetting = $_G['setting']['strongpw'];
    $passwordStorageList = [
      1 => "\d+",
      2 => "[a-z]+",
      3 => "[A-Z]+",
      4 => "[^a-zA-z0-9]+"
    ];
    $tips = [
      1 => "密码太弱，密码中必须包含数字",
      2 => "密码太弱，密码中必须包含小写字母",
      3 => "密码太弱，密码中必须包含大写字母",
      4 => "密码太弱，密码中必须包含特殊字符"
    ];
    $badPasswordMessage = null;
    foreach ($passwordStorageSetting as $item) {
      if (!preg_match("/$passwordStorageList[$item]/", $password)) {
        $badPasswordMessage = $tips[$item];
      }
    }
    if ($badPasswordMessage) {
      return $R->error(400, "400:BadPassword:002", $badPasswordMessage);
    }
    if (empty($_G['setting']['ignorepassword'])) {
      if ($password != addslashes($password)) {
        return $R->error(400, "400:ProfilePasswordIllegal", "抱歉，密码空或包含非法字符");
      }
    }

    //* 新用户注册验证 查询是否在 注册验证限制的地区列表 和 注册验证限制的 IP 列表
    $registerVerify = $_G['setting']['regverify'];
    $areaVerifyWhite = null;
    if ($registerVerify) {
      if ($areaVerifyWhite) {
        $location = $whitearea = '';
        $location = trim(convertip($_G['clientip'], "./"));
        if ($location) {
          $whitearea = preg_quote(trim($areaVerifyWhite), '/');
          $whitearea = str_replace(array("\\*"), array('.*'), $whitearea);
          $whitearea = '.*' . $whitearea . '.*';
          $whitearea = '/^(' . str_replace(array("\r\n", ' '), array('.*|.*', ''), $whitearea) . ')$/i';
          if (@preg_match($whitearea, $location)) {
            $registerVerify = 0;
          }
        }
      }

      if ($_G['cache']['ipctrl']['ipverifywhite']) {
        foreach (explode("\n", $_G['cache']['ipctrl']['ipverifywhite']) as $ctrlip) {
          if (preg_match("/^(" . preg_quote(($ctrlip = trim($ctrlip)), '/') . ")/", $_G['clientip'])) {
            $registerVerify = 0;
            break;
          }
        }
      }
    }

    //* UC 查询用户名是否已经存在
    if (\uc_get_user($username) && !\C::t('common_member')->fetch_uid_by_username($username) && !\C::t('common_member_archive')->fetch_uid_by_username($username)) {
      return $R->error(400, 400014, "该用户名已被注册");
    }

    //* 在 UC 注册
    $uid = uc_user_register($username, $password, $email, "", "", $_G['clientip']);
    $registerFailedMessage = null;
    if ($uid <= 0) {
      if ($uid == -1) {
        $registerFailedMessage = "用户名包含敏感字符";
      } elseif ($uid == -2) {
        $registerFailedMessage = "用户名包含被系统屏蔽的字符";
      } elseif ($uid == -3) {
        $registerFailedMessage = "该用户名已被注册";
      } elseif ($uid == -4) {
        $registerFailedMessage = "Email 地址无效";
      } elseif ($uid == -5) {
        $registerFailedMessage = "抱歉，Email 包含不可使用的邮箱域名";
      } elseif ($uid == -6) {
        $registerFailedMessage = "该 Email 地址已被注册";
      }
    }
    if ($registerFailedMessage) {
      return $R->error(400, "400_" . $uid . ":UCRegisterFailed", $registerFailedMessage);
    }

    //* 设置 初始用户组
    $groupId = 8;
    if (!$registerVerify) {
      $groupId = $_G['setting']['newusergroupid'];
    }


    //* 检测 UID 是否被占用了
    if (getuserbyuid($uid, 1)) {
      return $R->error(409, "409:UIDUesd", "抱歉，用户 $uid 已被占用", [
        "uid" => $uid
      ]);
    }

    //* 记录注册时使用的IP地址
    if ($setregip !== null) {
      if ($setregip == 1) {
        \C::t('common_regip')->update_count_by_ip($_G['clientip']);
      } else {
        \C::t('common_regip')->insert(array('ip' => $_G['clientip'], 'count' => 1, 'dateline' => $_G['timestamp']));
      }
    }

    if ($invite && $_G['setting']['inviteconfig']['invitegroupid']) {
      $groupId = $_G['setting']['inviteconfig']['invitegroupid'];
    }

    //* 插入用户表
    //* 存储在common_member表使用 随机密码
    $password = md5(random(10));
    $init = [
      'credits' => explode(',', $_G['setting']['initcredits'])
    ];
    \C::t('common_member')->insert($uid, $username, $password, $email, $_G['clientip'], $groupId, $init, 0, $_G['remoteport']);

    //* 更新用户统计缓存
    include_once libfile('cache/userstats', 'function');
    build_cache_userstats();

    //* 记录 24 小时允许注册的最大次数
    if ($_G['setting']['regctrl'] || $_G['setting']['regfloodctrl']) {
      \C::t('common_regip')->delete_by_dateline($_G['timestamp'] - ($_G['setting']['regctrl'] > 72 ? $_G['setting']['regctrl'] : 72) * 3600);
      if ($_G['setting']['regctrl']) {
        \C::t('common_regip')->insert(array('ip' => $_G['clientip'], 'count' => -1, 'dateline' => $_G['timestamp']));
      }
    }

    //* 新用户注册验证
    if ($registerVerify == 1) {
      //* 邮箱验证
      $idstring = random(6);
      $authstr = $_G['setting']['regverify'] == 1 ? "$_G[timestamp]\t2\t$idstring" : '';
      \C::t('common_member_field_forum')->update($uid, array('authstr' => $authstr));
      $verifyurl = "{$_G['siteurl']}member.php?mod=activate&amp;uid={$uid}&amp;id=$idstring";
      $email_verify_message = lang('email', 'email_verify_message', array(
        'username' => $username,
        'bbname' => $_G['setting']['bbname'],
        'siteurl' => $_G['siteurl'],
        'url' => $verifyurl
      ));
      include_once libfile("function/mail");
      if (!sendmail("$username <$email>", lang('email', 'email_verify_subject'), $email_verify_message)) {
        runlog('sendmail', "$email sendmail failed.");
      }
    } else if ($registerVerify == 2) {
      //* 人工审核
      $registerReason = dhtmlspecialchars($_GET['register_rason']);
      \C::t('common_member_validate')->insert(array(
        'uid' => $uid,
        'submitdate' => $_G['timestamp'],
        'moddate' => 0,
        'admin' => '',
        'submittimes' => 1,
        'status' => 0,
        'message' => $registerReason,
        'remark' => '',
      ), false, true);
      manage_addnotify('verifyuser');
    }

    include_once libfile('function/stat');
    \setloginstatus([
      'uid' => $uid,
      'username' => $username,
      'password' => $password,
      'groupid' => $groupId
    ], 0);
    \updatestat('register');

    if ($invite['id']) {
      $result = \C::t('common_invite')->count_by_uid_fuid($invite['uid'], $uid);
      if (!$result) {
        \C::t('common_invite')->update($invite['id'], array('fuid' => $uid, 'fusername' => $_G['username'], 'regdateline' => $_G['timestamp'], 'status' => 2));
        updatestat('invite');
      } else {
        $invite = array();
      }
    }
    if ($invite['uid']) {
      if ($_G['setting']['inviteconfig']['inviteaddcredit']) {
        updatemembercount($uid, array($_G['setting']['inviteconfig']['inviterewardcredit'] => $_G['setting']['inviteconfig']['inviteaddcredit']));
      }
      if ($_G['setting']['inviteconfig']['invitedaddcredit']) {
        updatemembercount($invite['uid'], array($_G['setting']['inviteconfig']['inviterewardcredit'] => $_G['setting']['inviteconfig']['invitedaddcredit']));
      }
      include_once libfile('function/friend');
      friend_make($invite['uid'], $invite['username'], false);
      notification_add($invite['uid'], 'friend', 'invite_friend', array('actor' => '<a href="home.php?mod=space&uid=' . $invite['uid'] . '" target="_blank">' . $invite['username'] . '</a>'), 1);

      space_merge($invite, 'field_home');
      if (!empty($invite['privacy']['feed']['invite'])) {
        include_once libfile('function/feed');
        $tite_data = array('username' => '<a href="home.php?mod=space&uid=' . $_G['uid'] . '">' . $_G['username'] . '</a>');
        feed_add('friend', 'feed_invite', $tite_data, '', array(), '', array(), array(), '', '', '', 0, 0, '', $invite['uid'], $invite['username']);
      }
    }

    //* 发送欢迎信息
    $welcomemsg = &$_G['setting']['welcomemsg'];
    $welcomemsgtitle = &$_G['setting']['welcomemsgtitle'];
    $welcomemsgtxt = &$_G['setting']['welcomemsgtxt'];
    if ($welcomemsg && !empty($welcomemsgtxt)) {
      $welcomemsgtitle = replacesitevar($welcomemsgtitle);
      $welcomemsgtxt = replacesitevar($welcomemsgtxt);
      if ($welcomemsg == 1) {
        $welcomemsgtxt = nl2br(str_replace(':', '&#58;', $welcomemsgtxt));
        notification_add($uid, 'system', $welcomemsgtxt, array('from_id' => 0, 'from_idtype' => 'welcomemsg'), 1);
      } elseif ($welcomemsg == 2) {
        sendmail_cron($email, $welcomemsgtitle, $welcomemsgtxt);
      } elseif ($welcomemsg == 3) {
        sendmail_cron($email, $welcomemsgtitle, $welcomemsgtxt);
        $welcomemsgtxt = nl2br(str_replace(':', '&#58;', $welcomemsgtxt));
        notification_add($uid, 'system', $welcomemsgtxt, array('from_id' => 0, 'from_idtype' => 'welcomemsg'), 1);
      }
    }

    dsetcookie('loginuser', '');
    dsetcookie('activationauth', '');
    dsetcookie('invite_auth', '');

    return $R->success(getuserbyuid($uid));
  }
  public static function avatar($memberId, $size = 'middle', $returnsrc = 1, $real = FALSE, $static = FALSE, $ucenterurl = '', $class = '', $extra = '', $random = 0)
  {
    $avatarURL = avatar($memberId, $size, $returnsrc, $real, $static, $ucenterurl, $class, $extra, $random);
    if (strpos($avatarURL, F_BASE_URL) === false) {
      $avatarURL = preg_replace("/(https?:\/\/)?[0-9a-zA-Z\._-]+/", F_BASE_URL, $avatarURL, 1);
    }

    return $avatarURL;
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
    global $_G;
    if (
      isset($_G['setting']['membersplit'])
    ) {
      $CMCAM = new DiscuzXModel("common_member_count_archive");
      $archiveMemberArchive = $CMCAM->where([
        "uid" => $memberId
      ])->getAll();
      $memberCredits = array_merge($memberCredits, $archiveMemberArchive);
    }
    if (!count($memberCredits) && !is_array($memberId)) return null;
    if (is_array($memberId)) return Arr::indexToAssoc($memberCredits, "uid");
    return $memberCredits[0];
  }
  public static function group($groupId = null, $simple = false)
  {
    if ($groupId === null) {
      $groupId = \getglobal("member")['groupid'];
    }
    $CUGM = new DiscuzXModel("common_usergroup");
    if ($simple) {
      $CUGM->field("groupid", "grouptitle", "icon", "color");
    }
    include_once libfile("function/group");
    $memberGroups = $CUGM->where([
      "groupid" => $groupId
    ])->getAll();
    foreach ($memberGroups as &$item) {
      $item['icon'] = get_groupimg($item['icon']);
    }
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
    global $_G;
    if ($memberId === null) {
      $memberId = \getglobal("uid");
    }
    $MM = new DiscuzXModel("common_member");
    $members = $MM->where([
      "uid" => $memberId
    ])->getAll();
    if (
      isset($_G['setting']['membersplit'])
    ) {
      $MMR = new DiscuzXModel("common_member_archive");
      $archiveMembers = $MMR->where([
        "uid" => $memberId
      ])->getAll();
      $members = array_merge($members, $archiveMembers);
    }

    if (empty($members)) return is_array($memberId) ? [] : null;

    $Groups =  self::group(array_column($members, "groupid"), !$detailed);
    $Credits = [];
    $Prompts = [];
    $userForumFields = [];
    if ($detailed) {
      $Credits = self::credit(is_array($memberId) ? $memberId : [$memberId]);
      $Prompts = self::newPrompt(is_array($memberId) ? $memberId : [$memberId]);

      $userForumFields = \C::t("common_member_field_forum")->fetch_all($memberId);
      if (
        isset($_G['setting']['membersplit'])
      ) {
        $archiveUserForumFields = \C::t("common_member_field_forum_archive")->fetch_all($memberId);
        $userForumFields = array_merge($userForumFields, $archiveUserForumFields);
      }
      $userForumFields = Arr::indexToAssoc($userForumFields, "uid");
      foreach ($userForumFields as &$item) {
        $item['sightml'] = preg_replace("/<img|img>/", "<span", $item['sightml']);
      }
    }

    global $_G;
    $_G['setting']['dynavt'] = 1;
    foreach ($members as &$MemberItem) {
      $MemberItem['avatar'] = self::avatar($MemberItem['uid']);

      if (isset($Groups[$MemberItem['groupid']])) {
        $MemberItem['group'] = $Groups[$MemberItem['groupid']];
      }
      if (isset($Credits[$MemberItem['uid']])) {
        $MemberItem['count'] = $Credits[$MemberItem['uid']];
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
      $MemberItem['avatar'] = self::avatar($MemberItem['uid'], "middle", 1);
    }
    return [
      "list" => $Members,
      "total" => $CMT->count()
    ];
  }
}
