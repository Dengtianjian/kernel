<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis\Material;

use kernel\Foundation\HTTP\Curl;
use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 素材管理 - 临时素材
 *
 * 临时素材仅在微信服务器保存 3 天，到期自动删除；
 * 上传后返回的 media_id 可用于客服消息、群发消息等场景。
 *
 * 说明：上传为 multipart/form-data，不走父类 post() 封装，因此单独新建 Curl 实例
 * 手动附加 access_token 与文件；下载接口返回二进制内容，直接返回原始响应体。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/New_temporary_materials.html
 */
class TemporaryMaterial extends WechatOfficialAccountApi
{
  /**
   * 上传临时素材
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/New_temporary_materials.html
   *
   * @param string $type 媒体文件类型：image/voice/video/thumb
   * @param string $filePath 本地文件绝对路径
   * @param string|null $fileName 上传文件名，默认取路径 basename
   * @param string $mimeType 文件 MIME 类型，默认由 curl 推断
   * @return array 微信响应数组，成功时含 media_id、type、created_at
   */
  public function upload($type, $filePath, $fileName = null, $mimeType = "")
  {
    return (new Curl())
      ->url($this->apiUrl . "/cgi-bin/media/upload", [
        "access_token" => $this->accessToken(),
        "type" => $type
      ])
      ->file($filePath, "media", $fileName, $mimeType)
      ->post()
      ->getData();
  }
  /**
   * 获取临时素材
   *
   * 成功时返回文件二进制内容（图片/语音/视频等）；出错时返回 JSON 数组（含 errcode/errmsg）。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Get_temporary_materials.html
   *
   * @param string $mediaId 素材 media_id
   * @return string|array 文件二进制内容或错误 JSON
   */
  public function download($mediaId)
  {
    return $this->get("cgi-bin/media/get", [
      "media_id" => $mediaId
    ])->getData();
  }
  /**
   * 获取高清语音素材（js-sdk 专用）
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Get_temporary_materials.html
   *
   * @param string $mediaId 语音素材 media_id
   * @return string|array 文件二进制内容或错误 JSON
   */
  public function downloadHdVoice($mediaId)
  {
    return $this->get("cgi-bin/media/get/jssdk", [
      "media_id" => $mediaId
    ])->getData();
  }
}
