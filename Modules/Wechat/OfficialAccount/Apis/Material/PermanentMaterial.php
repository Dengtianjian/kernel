<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis\Material;

use kernel\Foundation\HTTP\Curl;
use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 素材管理 - 永久素材
 *
 * 永久素材长期保存在微信服务器，新增后有数量上限（图文、图片、语音、视频分别计数）；
 * 其中图文素材即草稿发布前的内容载体，也可直接用于群发。
 *
 * 说明：上传为 multipart/form-data，不走父类 post() 封装，因此单独新建 Curl 实例
 * 手动附加 access_token 与文件；下载图文/视频素材时返回 JSON，其他类型返回二进制内容。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Adding_Permanent_Assets.html
 */
class PermanentMaterial extends WechatOfficialAccountApi
{
  /**
   * 新增永久素材（图片/语音/视频/缩略图）
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Adding_Permanent_Assets.html
   *
   * @param string $type 媒体文件类型：image/voice/video/thumb
   * @param string $filePath 本地文件绝对路径
   * @param array $description 视频素材的描述信息（title、introduction），仅 video 需要
   * @param string|null $fileName 上传文件名，默认取路径 basename
   * @param string $mimeType 文件 MIME 类型，默认由 curl 推断
   * @return array 微信响应数组，成功时含 media_id、url（图片素材为地址）
   */
  public function upload($type, $filePath, array $description = [], $fileName = null, $mimeType = "")
  {
    $curl = (new Curl())
      ->url($this->apiUrl . "/cgi-bin/material/add_material", [
        "access_token" => $this->accessToken(),
        "type" => $type
      ]);
    if ($description) {
      $curl->data([
        "description" => json_encode($description, JSON_UNESCAPED_UNICODE)
      ]);
    }

    return $curl
      ->file($filePath, "media", $fileName, $mimeType)
      ->post()
      ->getData();
  }
  /**
   * 上传图文消息内的图片（不占用永久素材数量）
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Adding_Permanent_Assets.html
   *
   * @param string $filePath 本地图片绝对路径
   * @param string|null $fileName 上传文件名，默认取路径 basename
   * @param string $mimeType 文件 MIME 类型，默认由 curl 推断
   * @return array 微信响应数组，成功时含 url（图片地址）
   */
  public function uploadImage($filePath, $fileName = null, $mimeType = "")
  {
    return (new Curl())
      ->url($this->apiUrl . "/cgi-bin/media/uploadimg", [
        "access_token" => $this->accessToken()
      ])
      ->file($filePath, "media", $fileName, $mimeType)
      ->post()
      ->getData();
  }
  /**
   * 新增永久图文素材
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Adding_Permanent_Assets.html
   *
   * @param array $articles 图文素材数组，每项含 title、thumb_media_id、author、digest、
   *                        show_cover_pic、content、content_source_url、need_open_comment、only_fans_can_comment
   * @return array 微信响应数组，成功时含 media_id
   */
  public function addNews(array $articles)
  {
    return $this->post("cgi-bin/material/add_news", [
      "articles" => array_values($articles)
    ])->getData();
  }
  /**
   * 修改永久图文素材
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Editing_Permanent_Assets.html
   *
   * @param string $mediaId 图文素材 media_id
   * @param int $index 要更新的文章在图文素材中的位置（从 0 开始）
   * @param array $articles 更新后的图文数组（仅替换指定 index 的文章）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function updateNews($mediaId, $index, array $articles)
  {
    return $this->post("cgi-bin/material/update_news", [
      "media_id" => $mediaId,
      "index" => $index,
      "articles" => array_values($articles)
    ])->getData();
  }
  /**
   * 获取永久素材
   *
   * 图文素材返回 JSON（news_item）；视频素材返回 JSON（title、description、down_url）；
   * 图片、语音等返回文件二进制内容。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Getting_Permanent_Assets.html
   *
   * @param string $mediaId 素材 media_id
   * @return string|array 文件二进制内容或 JSON 数组
   */
  public function download($mediaId)
  {
    return $this->post("cgi-bin/material/get_material", [
      "media_id" => $mediaId
    ])->getData();
  }
  /**
   * 删除永久素材
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Deleting_Permanent_Assets.html
   *
   * @param string $mediaId 素材 media_id
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function deleteMaterial($mediaId)
  {
    return $this->post("cgi-bin/material/del_material", [
      "media_id" => $mediaId
    ])->getData();
  }
  /**
   * 获取素材列表
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Get_the_total_of_all_materials.html
   *
   * @param string $type 素材类型：image/video/voice/news
   * @param int $offset 从全部素材的该偏移位置开始返回（0 表示从第一个素材开始）
   * @param int $count 返回素材的数量，取值 1-20
   * @return array 微信响应数组，成功时含 total_count、item_count、item
   */
  public function batchGet($type, $offset = 0, $count = 20)
  {
    return $this->post("cgi-bin/material/batchget_material", [
      "type" => $type,
      "offset" => $offset,
      "count" => $count
    ])->getData();
  }
  /**
   * 获取素材总数
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Asset_Management/Get_the_total_of_all_materials.html
   *
   * @return array 微信响应数组，成功时含 voice_count、video_count、image_count、news_count
   */
  public function getCount()
  {
    return $this->get("cgi-bin/material/get_materialcount")->getData();
  }
}
