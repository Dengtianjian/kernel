<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 基础接口
 *
 * 微信公众号基础接口：获取微信服务器 IP、jsapi_ticket，以及网络检测、
 * 接口调用频次（quota）与调用详情（rid）查询等。
 *
 * 说明：账号级接口调用凭证 access_token 由 {@see \kernel\Platform\Wechat\WechatAccessToken}
 * 统一获取与缓存，各请求经 {@see WechatOfficialAccountApi::accessToken()} 自动注入，
 * 因此本类不再单独暴露 access_token 相关方法。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Basic_Information/Get_the_WeChat_server_IP_address.html
 */
class Basic extends WechatOfficialAccountApi
{
  /**
   * 获取微信服务器 IP 地址（旧版接口）
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Basic_Information/Get_the_WeChat_server_IP_address.html
   *
   * @return array 微信响应数组，成功时含 ip_list
   */
  public function getCallbackIP()
  {
    return $this->get("cgi-bin/getcallbackip")->getData();
  }
  /**
   * 获取微信服务器 IP 地址（新版接口，已取代 getcallbackip）
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Basic_Information/Get_the_WeChat_server_IP_address.html
   *
   * @return array 微信响应数组，成功时含 ip_list
   */
  public function getApiDomainIp()
  {
    return $this->get("cgi-bin/get_api_domain_ip")->getData();
  }
  /**
   * 获取 jsapi_ticket
   *
   * jsapi_ticket 用于 JS-SDK 权限签名，官方缓存有效期为 7200 秒，
   * 签名算法见 {@link https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/JS-SDK.html}
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/JS-SDK.html
   *
   * @param string $type 票据类型，固定为 jsapi
   * @return array 微信响应数组，成功时含 ticket、expires_in
   */
  public function getJsApiTicket($type = "jsapi")
  {
    return $this->get("cgi-bin/ticket/getticket", [
      "type" => $type
    ])->getData();
  }
  /**
   * 网络检测
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Basic_Information/Network_Detection.html
   *
   * @param string $action 检测动作：dns（域名解析）、ping（ping 检测）、all（全部）
   * @param string $checkOperator 检测运营商，以官方文档为准（如 CHINANET、UNICOM、CAP 等）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function checkCallback($action = "all", $checkOperator = "CHINANET")
  {
    return $this->post("cgi-bin/callback/check", [
      "action" => $action,
      "check_operator" => $checkOperator
    ])->getData();
  }
  /**
   * 清除接口调用频率限制（quota）
   *
   * 公众号调用接口频率超限被拦截后，可调用本接口将当日调用次数清零。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Basic_Information/Clear_quota.html
   *
   * @param string|null $appId 公众号 AppId，不传时取当前账号的 AppId
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function clearQuota($appId = null)
  {
    return $this->post("cgi-bin/clear_quota", [
      "appid" => $appId ?: $this->account->appId()
    ])->getData();
  }
  /**
   * 查询接口调用频次（quota）
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/openApi/Quota.html
   *
   * @param string $cgiPath 接口路径，如 "cgi-bin/message/custom/send"
   * @return array 微信响应数组，成功时含 quota、used、remain 等字段
   */
  public function getOpenApiQuota($cgiPath)
  {
    return $this->post("cgi-bin/openapi/quota/get", [
      "cgi_path" => $cgiPath
    ])->getData();
  }
  /**
   * 查询 rid 对应的接口调用详情
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/openApi/Rid.html
   *
   * @param string $rid 接口调用返回中的 rid
   * @return array 微信响应数组，成功时含该次调用的请求与响应详情
   */
  public function getRidInfo($rid)
  {
    return $this->post("cgi-bin/openapi/rid/get", [
      "rid" => $rid
    ])->getData();
  }
}
