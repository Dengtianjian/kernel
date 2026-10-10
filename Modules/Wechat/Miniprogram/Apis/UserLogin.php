<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis;

use kernel\Foundation\Result;
use kernel\Foundation\Data\Arr;
use kernel\Modules\Auth\Auth;
use kernel\Platform\Wechat\Models\WechatUsersModel;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

class UserLogin extends WechatMiniProgramApi
{
  /**
   * 小程序登录凭证校验（JSCode 换取登录态）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/user-login/api_code2session.html
   *
   * 使用小程序端 wx.login 获取的临时登录凭证 code 换取用户的 openid、session_key
   * （满足 UnionID 获取条件时含 unionid）。code 只能使用一次。
   *
   * 微信错误码已在方法内转换为带提示信息的错误态 Result：
   * - 40029：无效的 code
   * - 45011：调用频率限制（每个用户每分钟 100 次）
   * - 40226：高风险等级用户，小程序登录拦截
   * - 40163：code 已被使用过
   * - 其他：按 "400:{errcode}" 返回，提示信息取 errmsg
   *
   * @param string $code 小程序端 wx.login 获取的临时登录凭证 JSCode
   * @return array|false 成功时 Result 数据含 openid、session_key（可能含 unionid）；失败时为错误态 Result
   */
  public function JSCode2Session($code)
  {
    $this->get("sns/jscode2session", [
      "appid" => $this->AppId,
      "secret" => $this->AppSecret,
      "grant_type" => "authorization_code",
      "js_code" => $code
    ], false);
    $responseData = $this->getJSONData();

    if ($responseData['errcode']) {
      switch ($responseData['errcode']) {
        case "40029":
          return $this->break(400, "400:InvalidCode", "登录失败，请稍后重试", "无效的Code");
        case "45011":
          return $this->break(400, "400:RequestLimited", "登录失败，尝试次数过多，请稍后重试", "频率限制，每个用户每分钟100次");
          break;
        case "40226":
          return $this->break(400, "400:BadUser", "登录失败，您是高风险用户，请联系管理员", "高风险等级用户，小程序登录拦截");
          break;
        case "40163":
          return $this->break(400, "400:CodeBeenUsed", "登录失败，请重新操作", "jscode已经使用过了");
          break;
        default:
          return $this->break(400, "400:" . $responseData['errcode'], "登录失败，请稍后重试", $responseData['errmsg']);
          break;
      }
    }
    return $responseData;
  }
  /**
   * 检查登录态
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/user-login/api_checksessionkey.html
   *
   * 校验用户当前的 session_key 是否有效（即登录态是否过期）。
   * 为保护 session_key，不直接传明文，而是传它的登录态签名。
   *
   * @param string $openid 用户唯一标识符
   * @param string $signature 用户登录态签名，即 hmac_sha256("", session_key)（session_key 对空字符串签名）
   * @param string $sigMethod 登录态签名的哈希方法，目前仅支持 hmac_sha256
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function checkSession($openid, $signature, $sigMethod = "hmac_sha256")
  {
    return $this->get("wxa/checksession", [
      "openid" => $openid,
      "signature" => $signature,
      "sig_method" => $sigMethod
    ])->getData();
  }
  /**
   * 重置登录态
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/user-login/api_resetusersessionkey.html
   *
   * 重置用户的 session_key，重置后原 session_key 立即失效；新的 session_key 继承原
   * session_key 的过期时间（重置不能续期），且不允许频繁重置同一个用户的登录态。
   *
   * @param string $openid 用户唯一标识符
   * @param string $signature 用户登录态签名，即 hmac_sha256("", session_key)（session_key 对空字符串签名）
   * @param string $sigMethod 登录态签名的哈希方法，目前仅支持 hmac_sha256
   * @return array 微信响应数组，成功时含 openid、session_key（重置后的登录态）
   */
  public function resetUserSessionKey($openid, $signature, $sigMethod = "hmac_sha256")
  {
    return $this->get("wxa/resetusersessionkey", [
      "openid" => $openid,
      "signature" => $signature,
      "sig_method" => $sigMethod
    ])->getData();
  }
  /**
   * 绑定
   *
   * @param string $code JSCode
   * @return Result|bool
   */
  public function bind($code)
  {
    $res = $this->JSCode2Session($code);
    if (!$res) {
      return $this->forwardBreak();
    }
    $user = Auth::user();
    return WechatUsersModel::bind($user['uid'], $res['openid'], $res['unionid']);
  }
  /**
   * 注册
   *
   * @param string $code JSCode
   * @return Result|bool
   */
  public function register($code)
  {
    $res = $this->JSCode2Session($code);
    if (!$res) {
      return $this->forwardBreak();
    }
    return WechatUsersModel::register($res['openid'], $res['unionid']);
  }
}
