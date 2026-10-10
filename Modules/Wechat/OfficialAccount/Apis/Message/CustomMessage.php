<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis\Message;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 客服消息
 *
 * 用户与公众号产生交互（关注、发消息、点击菜单等）后 48 小时内，
 * 可通过客服消息接口向用户下发消息。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Service_Center_messages.html
 */
class CustomMessage extends WechatOfficialAccountApi
{
  /**
   * 发送客服消息
   *
   * $msgType 与 $content 需对应官方约定：
   * - text：["content" => "文本内容"]
   * - image / voice：["media_id" => "素材ID"]
   * - video：["media_id" => "素材ID", "thumb_media_id" => "封面素材ID", "title" => "", "description" => ""]
   * - music：["title" => "", "description" => "", "musicurl" => "", "hqmusicurl" => "", "thumb_media_id" => ""]
   * - news：["articles" => [["title" => "", "description" => "", "url" => "", "picurl" => ""], ...]]
   * - mpnews：["media_id" => "图文消息素材ID"]
   * - menu：["head_content" => "", "list" => [...], "tail_content" => ""]
   * - miniprogrampage：["title" => "", "appid" => "", "pagepath" => "", "thumb_media_id" => ""]
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Service_Center_messages.html
   *
   * @param string $touser 用户 OpenID
   * @param string $msgType 消息类型：text/image/voice/video/music/news/mpnews/menu/miniprogrampage
   * @param array $content 对应消息类型的消息体
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function send($touser, $msgType, $content = [])
  {
    return $this->post("cgi-bin/message/custom/send", [
      "touser" => $touser,
      "msgtype" => $msgType,
      $msgType => $content
    ])->getData();
  }
  /**
   * 客服输入状态
   *
   * 下发前需先调用本接口向用户发送"正在输入"，用户收到后可提升交互体验。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Service_Center_messages.html
   *
   * @param string $touser 用户 OpenID
   * @param string $command 输入状态：Typing（正在输入）/ CancelTyping（取消正在输入）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function typing($touser, $command = "Typing")
  {
    return $this->post("cgi-bin/message/custom/typing", [
      "touser" => $touser,
      "command" => $command
    ])->getData();
  }
}
