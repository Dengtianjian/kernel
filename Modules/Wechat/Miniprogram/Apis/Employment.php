<?php

namespace kernel\Modules\Wechat\Miniprogram\Apis;

use kernel\Modules\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 用工关系
 *
 * 对应微信「用工关系」分类，用于向灵活用工场景下的用户推送消息、解绑用工关系。
 */
class Employment extends WechatMiniProgramApi
{
  /**
   * 推送用工消息（POST /cgi-bin/message/wxopen/employeerelationmsg/send）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/laboruse/api_sendemployeerelationmsg.html
   *
   * @param array $data 请求体，字段（如 touser、page、data 等）以官方文档为准
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function sendMessage($data = [])
  {
    return $this->post("cgi-bin/message/wxopen/employeerelationmsg/send", $data)->getData();
  }
  /**
   * 解绑用工关系（POST /wxa/business/unbinduserb2cauthinfo）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/laboruse/api_unbinduserb2cauthinfo.html
   *
   * @param array $data 请求体，字段（如 openid 等）以官方文档为准
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function unbindUserB2cAuthInfo($data = [])
  {
    return $this->post("wxa/business/unbinduserb2cauthinfo", $data)->getData();
  }
}
