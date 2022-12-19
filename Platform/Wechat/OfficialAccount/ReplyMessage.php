<?php

namespace gstudio_kernel\Platform\Wechat\OfficialAccount;

use gstudio_kernel\Foundation\Data\Arr;

class ReplyMessage
{
  /**
   * 回复文本消息
   *
   * @param string $toUserName 接收方帐号（收到的OpenID）
   * @param string $fromUserName 开发者微信号
   * @param string $Content 回复的消息内容（换行：在 content 中能够换行，微信客户端就支持换行显示）
   * @return string XML
   */
  static function text($toUserName, $fromUserName, $Content)
  {
    return Arr::toXML([
      "ToUserName" => $toUserName,
      "FromUserName" => $fromUserName,
      "CreateTime" => time(),
      "MsgType" => "text",
      "Content" => $Content
    ]);
  }
  /**
   * 回复图片消息
   *
   * @param string $toUserName 接收方帐号（收到的OpenID）
   * @param string $fromUserName 开发者微信号
   * @param string $mediaId 通过素材管理中的接口上传多媒体文件，得到的id。
   * @return string XML
   */
  static function image($toUserName, $fromUserName, $mediaId)
  {
    return Arr::toXML([
      "ToUserName" => $toUserName,
      "FromUserName" => $fromUserName,
      "CreateTime" => time(),
      "MsgType" => "image",
      "Image" => [
        "MediaId" => $mediaId
      ]
    ]);
  }
  /**
   * 回复语音消息
   *
   * @param string $toUserName 接收方帐号（收到的OpenID）
   * @param string $fromUserName 开发者微信号
   * @param string $mediaId 通过素材管理中的接口上传多媒体文件，得到的id
   * @return string XML
   */
  static function voice($toUserName, $fromUserName, $mediaId)
  {
    return Arr::toXML([
      "ToUserName" => $toUserName,
      "FromUserName" => $fromUserName,
      "CreateTime" => time(),
      "MsgType" => "voice",
      "Voice" => [
        "MediaId" => $mediaId
      ]
    ]);
  }
  /**
   * 回复视频消息
   *
   * @param string $toUserName 接收方帐号（收到的OpenID）
   * @param string $fromUserName 	开发者微信号
   * @param string $mediaId 通过素材管理中的接口上传多媒体文件，得到的id
   * @param string? $title 视频消息的标题
   * @param string? $description 视频消息的描述
   * @return string XML
   */
  static function video($toUserName, $fromUserName, $mediaId, $title = null, $description = null)
  {
    $video = [
      "MediaId" => $mediaId
    ];
    if ($title) {
      $video['Title'] = $title;
    }
    if ($description) {
      $video['Description'] = $description;
    }
    return Arr::toXML([
      "ToUserName" => $toUserName,
      "FromUserName" => $fromUserName,
      "CreateTime" => time(),
      "MsgType" => "video",
      "Video" => $video
    ]);
  }
  /**
   * 回复音乐消息
   *
   * @param string $toUserName 接收方帐号（收到的OpenID）
   * @param string $fromUserName 开发者微信号
   * @param string $thumbMediaId 缩略图的媒体id，通过素材管理中的接口上传多媒体文件，得到的id
   * @param string $title 音乐标题
   * @param string $description 音乐描述
   * @param string $musicURL 	音乐链接
   * @param string $HQMusicUrl 高质量音乐链接，WIFI环境优先使用该链接播放音乐
   * @return string XML
   */
  static function music($toUserName, $fromUserName, $thumbMediaId, $title = null, $description = null, $musicURL = null, $HQMusicUrl = null)
  {
    $Music = [
      "ThumbMediaId" => $thumbMediaId
    ];
    if ($title) {
      $video['Title'] = $title;
    }
    if ($description) {
      $video['Description'] = $description;
    }
    if ($musicURL) {
      $video['MusicURL'] = $musicURL;
    }
    if ($HQMusicUrl) {
      $video['HQMusicUrl'] = $HQMusicUrl;
    }
    return Arr::toXML([
      "ToUserName" => $toUserName,
      "FromUserName" => $fromUserName,
      "CreateTime" => time(),
      "MsgType" => "music",
      "Music" => $Music
    ]);
  }
  /**
   * 回复图文消息
   *
   * @param string $toUserName 接收方帐号（收到的OpenID）
   * @param string  $fromUserName 开发者微信号
   * @param array $articles 二位数组。 图文消息信息，注意，如果图文数超过限制，则将只发限制内的条数。当用户发送文本、图片、语音、视频、图文、地理位置这六种消息时，开发者只能回复1条图文消息；其余场景最多可回复8条图文消息。
   * @param - Title 图文消息标题
   * @param - Description 图文消息描述
   * @param - PicUrl 图片链接，支持JPG、PNG格式，较好的效果为大图360*200，小图200*200
   * @param - Url 点击图文消息跳转链接
   * @return string XML
   */
  static function news($toUserName, $fromUserName, $articles)
  {
    return Arr::toXML([
      "ToUserName" => $toUserName,
      "FromUserName" => $fromUserName,
      "CreateTime" => time(),
      "MsgType" => "news",
      "ArticleCount" => count($articles),
      "Articles" => [
        "item" => $articles
      ]
    ]);
  }
}
