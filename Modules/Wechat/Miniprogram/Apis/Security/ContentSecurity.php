<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis\Security;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 内容安全
 *
 * 对应微信「小程序安全 → 内容安全」分类，用于检测文本、图片、音频内容是否违规。
 */
class ContentSecurity extends WechatMiniProgramApi
{
  /**
   * 内容安全结果标签对应文案
   *
   * @param int $label 检测结果中的 label 值
   * @return string
   */
  function getLabelText($label)
  {
    $LabelTexts = [
      100 => "",
      10001 => "广告",
      20001 => "时政",
      20002 => "色情",
      20003 => "辱骂",
      20006 => "违法犯罪",
      20008 => "欺诈",
      20012 => "低俗",
      20013 => "涉及版权",
      21000 => "其它违规",
    ];

    return $LabelTexts[$label] ?: "违规";
  }
  /**
   * 文本内容安全识别（POST /wxa/msg_sec_check）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/sec-center/sec-check/api_msgseccheck.html
   *
   * @param string $content 需检测的文本内容，上限 2500 字，需 UTF-8 编码
   * @param string $openId 用户的 openid（用户需在近两小时访问过小程序）
   * @param int $scene 场景枚举值（1 资料；2 评论；3 论坛；4 社交日志）
   * @return array 微信响应数组，成功时含 result（suggest、label 等）
   */
  function msgSecCheck($content, $openId, $scene = 2)
  {
    return $this->post("wxa/msg_sec_check", [
      "content" => $content,
      "version" => 2,
      "scene" => $scene,
      "openid" => $openId
    ])->getData();
  }
  /**
   * 多媒体内容安全识别（POST /wxa/media_check_async）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/sec-center/sec-check/api_mediacheckasync.html
   *
   * 异步检测图片或音频，检测结果通过消息推送通知。
   *
   * @param string $mediaUrl 要检测的图片或音频的 url，支持 jpg/jpeg/png/bmp/gif（取首帧）与 mp3/aac/ac3/wma/flac/vorbis/opus/wav
   * @param string $openId 用户的 openid（用户需在近两小时访问过小程序）
   * @param int $scene 场景枚举值（1 资料；2 评论；3 论坛；4 社交日志）
   * @param int $mediaType 1 音频；2 图片
   * @return array 微信响应数组，成功时含 trace_id（可用于关联异步检测结果）
   */
  function mediaCheckAsync($mediaUrl, $openId, $scene = 2, $mediaType = 2)
  {
    return $this->post("wxa/media_check_async", [
      "media_url" => $mediaUrl,
      "media_type" => $mediaType,
      "version" => 2,
      "scene" => $scene,
      "openid" => $openId
    ])->getData();
  }
}
