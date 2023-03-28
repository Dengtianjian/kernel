<?php

namespace kernel\Platform\Wechat\Miniprogram;

use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Store;
use kernel\Model\WechatUsersModel;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class User extends WechatMiniProgram
{
  /**
   * JSCode换取AccessToken
   *
   * @param string $code 前端获取到的JSCode
   */
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
          return new ReturnResult(false, 400, "400:InvalidCode", "登录失败，请稍后重试", "无效的Code");
          break;
        case "45011":
          return new ReturnResult(false, 400, "400:RequestLimited", "登录失败，尝试次数过多，请稍后重试", "频率限制，每个用户每分钟100次");
          break;
        case "40226":
          return new ReturnResult(false, 400, "400:BadUser", "登录失败，您是高风险用户，请联系管理员", "高风险等级用户，小程序登录拦截");
          break;
        case "40163":
          return new ReturnResult(false, 400, "400:CodeBeenUsed", "登录失败，请重新操作", "jscode已经使用过了");
          break;
        default:
          return new ReturnResult(false, 400, "400:" . $request['errcode'], "登录失败，请稍后重试", $request['errmsg']);
          break;
      }
    }
    return $request;
  }
  /**
   * 绑定
   *
   * @param string $code JSCode
   * @return ReturnResult|bool
   */
  public function bind($code)
  {
    $res = $this->JSCode2Session($code);
    if ($res->error) {
      return $res;
    }
    $res = $res->result();
    $WUM = new WechatUsersModel();
    $member = Store::getApp("member");
    return $WUM->bind($member['uid'], $res['openid'], $res['unionid']);
  }
  /**
   * 注册
   *
   * @param string $code JSCode
   * @return ReturnResult|bool
   */
  public function register($code)
  {
    $res = $this->JSCode2Session($code);
    if ($res->error) {
      return $res;
    }
    $res = $res->result();
    $WUM = new WechatUsersModel();
    return $WUM->register($res['openid'], $res['unionid']);
  }
}
