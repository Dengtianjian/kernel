<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis\ImageAndText;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 图像处理
 *
 * 对应微信「图像处理与文字识别 → 图像处理」分类（接口前缀 /cv/img）。
 *
 * 说明：接口支持传图片 url（img_url）；若需直接上传图片二进制，可在 $extra 中传 "img" => 文件内容。
 */
class Image extends WechatMiniProgramApi
{
  /**
   * 图片智能裁剪（POST /cv/img/aicrop）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/img-ocr/img/api_imgaicrop.html
   *
   * @param string $imgUrl 图片 url
   * @param array $extra 附加参数，如 ["ratios" => ["1:1", "16:9"]]，具体字段以官方文档为准
   * @return array 微信响应数组，成功时含 results（裁剪结果列表）
   */
  function aiCrop($imgUrl, $extra = [])
  {
    return $this->post("cv/img/aicrop", array_merge([
      "img_url" => $imgUrl
    ], $extra))->getData();
  }
  /**
   * 二维码/条码识别（POST /cv/img/qrcode）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/img-ocr/img/api_imgqrcode.html
   *
   * @param string $imgUrl 图片 url
   * @param array $extra 附加参数，具体字段以官方文档为准
   * @return array 微信响应数组，成功时含 code_results（识别结果列表）
   */
  function qrCode($imgUrl, $extra = [])
  {
    return $this->post("cv/img/qrcode", array_merge([
      "img_url" => $imgUrl
    ], $extra))->getData();
  }
  /**
   * 图片高清化（POST /cv/img/superresolution）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/img-ocr/img/api_imgsuperresolution.html
   *
   * 注意：该接口已下架，保留方法仅为兼容历史调用。
   *
   * @param string $imgUrl 图片 url
   * @param array $extra 附加参数，具体字段以官方文档为准
   * @return array 微信响应数组
   */
  function superResolution($imgUrl, $extra = [])
  {
    return $this->post("cv/img/superresolution", array_merge([
      "img_url" => $imgUrl
    ], $extra))->getData();
  }
}
