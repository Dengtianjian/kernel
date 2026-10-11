<?php

namespace kernel\Modules\Wechat\OfficialAccount\Apis;

use kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 网页授权与网页应用
 *
 * 微信公众号网页授权相关接口。
 *
 * 注意：本类中的 access_token 指「网页授权 access_token」（sns/oauth2），
 * 与账号级的接口调用凭证 access_token 不同，因此方法名加了 WebPage 前缀以示区分。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html
 */
class WebApp extends WechatOfficialAccountApi
{
  /**
   * 生成获取code的URL地址
   * @link https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#0
   *
   * @param string $redirectURL 授权后重定向的回调链接地址， 请使用 urlEncode 对链接进行处理
   * @param string $responseType 返回类型，请填写code
   * @param string $scope 应用授权作用域，snsapi_base （不弹出授权页面，直接跳转，只能获取用户openid），snsapi_userinfo （弹出授权页面，可通过openid拿到昵称、性别、所在地。并且， 即使在未关注的情况下，只要用户授权，也能获取其信息 ）
   * @param string $state 重定向后会带上state参数，开发者可以填写a-zA-Z0-9的参数值，最多128字节
   * @param boolean $forcePopup 强制此次授权需要用户弹窗确认；默认为false；需要注意的是，若用户命中了特殊场景下的静默授权逻辑，则此参数不生效
   * @param boolean $forceSnapShot 强制此次授权进行是否进入快照页逻辑判定(在快照页功能灰度期间，部分网页即使命中了进入快照页模式逻辑未灰度用户也不会进入快照页模式，开发者可以通过此参数设置进入快照页模式判断逻辑)，默认为fals需要注意的是，若本次登录命中了近期登录过免授权逻辑逻辑或特殊场景下的静默授权逻辑，则此参数不生效
   * @return string URL地址
   */
  public function generateGetCodeURL($redirectURL, $responseType = "code", $scope = "snsapi_base", $state = "", $forcePopup = false, $forceSnapShot = false)
  {
    $redirectURL = urlencode($redirectURL);
    $querys = [
      "appid" => $this->account->appId(),
      "redirect_uri" => $redirectURL,
      "response_type" => $responseType,
      "scope" => $scope,
      "state" => $state
    ];
    if ($forcePopup) {
      $querys["foucePopup"] = true;
    }
    if ($forceSnapShot) {
      $querys["forceSnapShot"] = true;
    }
    $queryString = http_build_query($querys);

    return "https://open.weixin.qq.com/connect/oauth2/authorize?$queryString#wechat_redirect";
  }
  /**
   * 获取网页授权 access_token
   * @link https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#1
   *
   * @param string $code code参数
   * @return array 微信响应数组，成功时含 access_token、expires_in、refresh_token、openid、scope
   */
  public function getWebPageAccessToken($code)
  {
    return $this->get("sns/oauth2/access_token", [
      "appid" => $this->account->appId(),
      "secret" => $this->account->appSecret(),
      "code" => $code,
      "grant_type" => "authorization_code",
    ], false)->getData();
  }
  /**
   * 刷新网页授权 access_token
   * @link https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#2
   *
   * @param string $refreshToken 填写通过access_token获取到的refresh_token参数
   * @return array 微信响应数组，成功时含 access_token、expires_in、refresh_token、openid、scope
   */
  public function refreshWebPageAccessToken($refreshToken)
  {
    return $this->get("sns/oauth2/refresh_token", [
      "appid" => $this->account->appId(),
      "grant_type" => "refresh_token",
      "refresh_token" => $refreshToken
    ], false)->getData();
  }
  /**
   * 拉取用户信息（需 scope 为 snsapi_userinfo）
   * @link https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#3
   *
   * @param string $openId 用户的唯一标识
   * @param string $lang 返回国家地区语言版本，zh_CN 简体，zh_TW 繁体，en 英语
   * @return array 微信响应数组，成功时含用户昵称、头像等信息
   */
  public function getUserInfo($openId, $lang = "zh_CN")
  {
    return $this->get("sns/userinfo", [
      "openid" => $openId,
      "lang" => $lang,
    ])->getData();
  }
  /**
   * 检验网页授权凭证（access_token）是否有效
   * @link https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/Wechat_webpage_authorization.html#4
   *
   * @param string $openId 用户的唯一标识
   * @return bool true=有效，false=无效
   */
  public function checkAccessToken($openId)
  {
    $getResult = $this->get("sns/auth", [
      "openid" => $openId,
    ])->getData();
    if (!is_array($getResult) || ($getResult['errcode'] ?? -1) != 0) return false;

    return true;
  }
}
