<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis\Message;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 群发消息
 *
 * 公众号可通过本类接口按标签或 OpenID 列表群发消息，群发有次数与速率限制，
 * 且会占用公众号的群发次数，详见官方文档。
 *
 * 注意：本类方法名刻意避开 get()/delete() 等父类 HTTP 方法名，
 * 以免覆盖 {@see WechatOfficialAccountApi::get()} 等请求封装。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Batch_Sends_and_Originality_Checks.html
 */
class MassMessage extends WechatOfficialAccountApi
{
  /**
   * 按标签群发
   *
   * $msgType 与 $content 需对应官方约定（同客服消息），例如：
   * - text：["content" => "文本内容"]
   * - mpnews：["media_id" => "图文素材ID"]
   * - image / voice：["media_id" => "素材ID"]
   * - wxcard：["card_id" => "卡券ID"]
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Batch_Sends_and_Originality_Checks.html
   *
   * @param string $msgType 消息类型
   * @param array $content 消息体
   * @param array $filter 发送对象：["is_to_all" => true] 或 ["is_to_all" => false, "tag_id" => 标签ID]
   * @param array $options 其余可选字段，如 send_ignore_reprint、clientmsgid
   * @return array 微信响应数组，成功时含 msg_id、msg_data_id
   */
  public function sendAll($msgType, $content, array $filter = ["is_to_all" => true], array $options = [])
  {
    $body = [
      "filter" => $filter,
      "msgtype" => $msgType,
      $msgType => $content
    ];

    return $this->post("cgi-bin/message/mass/sendall", array_merge($body, $options))->getData();
  }
  /**
   * 按 OpenID 列表群发
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Batch_Sends_and_Originality_Checks.html
   *
   * @param array $openIds 接收者 OpenID 列表（最多 10000 个）
   * @param string $msgType 消息类型
   * @param array $content 消息体
   * @param array $options 其余可选字段，如 send_ignore_reprint、clientmsgid
   * @return array 微信响应数组，成功时含 msg_id、msg_data_id
   */
  public function sendToUsers(array $openIds, $msgType, $content, array $options = [])
  {
    $body = [
      "touser" => array_values($openIds),
      "msgtype" => $msgType,
      $msgType => $content
    ];

    return $this->post("cgi-bin/message/mass/send", array_merge($body, $options))->getData();
  }
  /**
   * 群发消息预览
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Batch_Sends_and_Originality_Checks.html
   *
   * @param string $msgType 消息类型
   * @param array $content 消息体
   * @param string $toUser 接收预览的 OpenID（与 $toWxName 二选一）
   * @param string|null $toWxName 接收预览的微信号（与 $toUser 二选一）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function preview($msgType, $content, $toUser = null, $toWxName = null)
  {
    $body = [
      "msgtype" => $msgType,
      $msgType => $content
    ];
    if ($toUser) {
      $body['touser'] = $toUser;
    }
    if ($toWxName) {
      $body['towxname'] = $toWxName;
    }

    return $this->post("cgi-bin/message/mass/preview", $body)->getData();
  }
  /**
   * 查询群发消息发送状态
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Batch_Sends_and_Originality_Checks.html
   *
   * @param int|string $msgId 群发消息的 msg_id
   * @return array 微信响应数组，成功时含 msg_id、msg_status
   */
  public function getStatus($msgId)
  {
    return $this->post("cgi-bin/message/mass/get", [
      "msg_id" => $msgId
    ])->getData();
  }
  /**
   * 删除群发
   *
   * 群发接口派生的 msg_id 对应的消息可在此删除；已发送成功的消息仅可删除图文消息。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Batch_Sends_and_Originality_Checks.html
   *
   * @param int|string $msgId 群发消息的 msg_id
   * @param int|null $articleIdx 图文消息中要删除的文章序号（从 1 开始）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function deleteMass($msgId, $articleIdx = null)
  {
    $body = [
      "msg_id" => $msgId
    ];
    if ($articleIdx !== null) {
      $body['article_idx'] = $articleIdx;
    }

    return $this->post("cgi-bin/message/mass/delete", $body)->getData();
  }
  /**
   * 获取群发速度
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Batch_Sends_and_Originality_Checks.html
   *
   * @return array 微信响应数组，成功时含 speed
   */
  public function getSpeed()
  {
    return $this->post("cgi-bin/message/mass/speed/get")->getData();
  }
  /**
   * 设置群发速度
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Batch_Sends_and_Originality_Checks.html
   *
   * @param int $speed 群发速度级别（0-4，具体含义以官方文档为准）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function setSpeed($speed)
  {
    return $this->post("cgi-bin/message/mass/speed/set", [
      "speed" => $speed
    ])->getData();
  }
}
