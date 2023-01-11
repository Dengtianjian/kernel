<?php

namespace gstudio_kernel\Platform\Wechat\Miniprogram;

use gstudio_kernel\Foundation\Network\Curl;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class SecretCheck extends WechatMiniProgram
{
  /**
   * 文本内容安全识别
   *
   * @param string $content 需检测的文本内容，文本字数的上限为2500字，需使用UTF-8编码
   * @param string $openId 用户的openid（用户需在近两小时访问过小程序）
   * @param integer $scene 场景枚举值（1 资料；2 评论；3 论坛；4 社交日志）
   * @return array 检测结果
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
   * 音视频内容安全识别
   *
   * @param string $mediaUrl 要检测的图片或音频的url，支持图片格式包括 jpg , jepg, png, bmp, gif（取首帧），支持的音频格式包括mp3, aac, ac3, wma, flac, vorbis, opus, wav
   * @param string $openId 用户的openid（用户需在近两小时访问过小程序）
   * @param integer $scene 场景枚举值（1 资料；2 评论；3 论坛；4 社交日志）
   * @param integer $mediaType 1:音频;2:图片
   * @return array 检测结果
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
  /**
   * 获取用户安全等级
   *
   * @param string $openid 用户的openid
   * @param integer $scene 场景值，0:注册，1:营销作弊
   * @return array
   */
  function getUserRiskRank($openid, $scene = 1)
  {
    global $_G;
    return $this->post("wxa/getuserriskrank", [
      "appid" => $this->AppId,
      "openid" => $openid,
      "scene" => $scene,
      "client_ip" =>  $_G['client_ip'],
      // "is_test" => true
    ])->getData();
  }
}
