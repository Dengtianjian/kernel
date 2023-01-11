<?php

namespace gstudio_kernel\Platform\Wechat\Miniprogram;

use gstudio_kernel\Foundation\Exception\ThrowError;
use gstudio_kernel\Foundation\Output;
use gstudio_kernel\Foundation\Store;
use gstudio_kernel\Model\WechatUsersModel;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class User extends WechatMiniProgram
{
  public function JSCode2Session($code)
  {
    $request = $this->get("sns/jscode2session", [
      "appid" => $this->AppId,
      "secret" => $this->AppSecret,
      "grant_type" => "authorization_code",
      "js_code" => $code
    ], false)->getData();

    if ($request['errcode']) {
      switch ($request['errcode']) {
        case "40029":
          return new ThrowError(400, "400:InvalidCode", "登录失败，请稍后重试", [], "无效的Code");
          break;
        case "45011":
          return new ThrowError(400, "400:RequestLimited", "登录失败，尝试次数过多，请稍后重试", [], "频率限制，每个用户每分钟100次");
          break;
        case "40226":
          return new ThrowError(400, "400:BadUser", "登录失败，您是高风险用户，请联系管理员", [], "高风险等级用户，小程序登录拦截");
          break;
        default:
          return new ThrowError(400, "400:" . $request['errcode'], "登录失败，请稍后重试", [], $request['errmsg']);
          break;
      }
    }
    return $request;
  }
  public function bind($code)
  {
    $res = $this->JSCode2Session($code);
    if (ThrowError::is($res)) {
      $res->response();
    }
    $WUM = new WechatUsersModel();
    $member = Store::getApp("member");
    return $WUM->bind($member['uid'], $res['openid'], $res['unionid']);
  }
  public function register($code)
  {
    $res = $this->JSCode2Session($code);
    if (ThrowError::is($res)) {
      $res->response();
    }
    $WUM = new WechatUsersModel();
    return $WUM->register($res['openid'], $res['unionid']);
  }
}
