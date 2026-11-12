<?php

namespace kernel\Modules\Wechat\OfficialAccount\Apis\Message;

use kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 一次性订阅消息
 *
 * 用户通过网页授权/支付等场景授权一次后，公众号可下发一条对应的模板消息，
 * 授权次数可累积（每授权一次可下发一条）。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/One-off_subscription_message.html
 */
class SubscribeMessage extends WechatOfficialAccountApi
{
  /**
   * 发送一次性订阅消息
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/One-off_subscription_message.html
   *
   * @param string $touser 接收者 OpenID
   * @param string $templateId 订阅消息模板 ID
   * @param string $scene 场景值（订阅消息的 scene，用于统计，如 "1000"）
   * @param string $title 消息标题
   * @param array $data 模板数据，如 ["content" => ["value" => "内容"]]
   * @param string|null $url 点击消息后的跳转链接
   * @param array|null $miniprogram 跳转小程序所需数据（含 appid、pagepath）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function send($touser, $templateId, $scene, $title, array $data = [], $url = null, $miniprogram = null)
  {
    $body = [
      "touser" => $touser,
      "template_id" => $templateId,
      "scene" => $scene,
      "title" => $title,
      "data" => $data
    ];
    if ($url !== null) {
      $body['url'] = $url;
    }
    if ($miniprogram !== null) {
      $body['miniprogram'] = $miniprogram;
    }

    return $this->post("cgi-bin/message/template/subscribe", $body)->getData();
  }
}
