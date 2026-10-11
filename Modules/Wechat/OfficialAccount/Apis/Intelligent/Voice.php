<?php

namespace kernel\Modules\Wechat\OfficialAccount\Apis\Intelligent;

use kernel\Foundation\HTTP\Curl;
use kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 智能接口 - 智能语音
 *
 * 语音转文字为异步流程：先 addVoiceToRecoForText() 提交语音（$voiceId 由调用方生成，
 * 用于后续查询），再轮询 queryRecoResultForText() 获取识别结果；另提供文本翻译接口。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Intelligent_Interface/AI_Open_API.html
 */
class Voice extends WechatOfficialAccountApi
{
  /**
   * 提交语音识别任务（异步）
   *
   * 语音文件需为官方要求的格式（默认 amr，也可传 speex 等，以官方文档为准），
   * 提交成功仅表示任务已受理，需再调用 queryRecoResultForText() 查询结果。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Intelligent_Interface/AI_Open_API.html
   *
   * @param string $voiceId 语音唯一标识（调用方自行生成，用于查询结果）
   * @param string $filePath 本地语音文件绝对路径
   * @param string $lang 语言类型（zh_CN 等）
   * @param string $format 语音格式，如 amr
   * @param string|null $fileName 上传文件名，默认取路径 basename
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function addVoiceToRecoForText($voiceId, $filePath, $lang = "zh_CN", $format = "amr", $fileName = null)
  {
    return (new Curl())
      ->url($this->apiUrl . "/cgi-bin/media/voice/addvoicetorecofortext", [
        "access_token" => $this->accessToken(),
        "voice_id" => $voiceId,
        "format" => $format,
        "lang" => $lang
      ])
      ->file($filePath, "voice", $fileName)
      ->post()
      ->getData();
  }
  /**
   * 查询语音识别结果
   *
   * 需在提交语音识别任务后调用；结果未就绪时微信返回对应状态，可稍后重试。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Intelligent_Interface/AI_Open_API.html
   *
   * @param string $voiceId 提交任务时的语音唯一标识
   * @param string $lang 语言类型（zh_CN 等）
   * @return array 微信响应数组，成功时含 result（识别文本）
   */
  public function queryRecoResultForText($voiceId, $lang = "zh_CN")
  {
    return $this->post("cgi-bin/media/voice/queryrecoresultfortext", [], [
      "voice_id" => $voiceId,
      "lang" => $lang
    ])->getData();
  }
  /**
   * 文本翻译
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Intelligent_Interface/AI_Open_API.html
   *
   * @param string $content 待翻译的文本内容
   * @param string $lfrom 源语言，如 zh_CN
   * @param string $lto 目标语言，如 en_US
   * @return array 微信响应数组，成功时含 result（翻译结果）
   */
  public function translateContent($content, $lfrom, $lto)
  {
    return $this->CURL
      ->url($this->apiUrl . "/cgi-bin/media/voice/translatecontent", [
        "access_token" => $this->accessToken(),
        "lfrom" => $lfrom,
        "lto" => $lto
      ])
      ->https(false)
      ->post($content)
      ->getData();
  }
}
