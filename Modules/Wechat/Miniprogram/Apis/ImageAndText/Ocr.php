<?php

namespace kernel\Modules\Wechat\Miniprogram\Apis\ImageAndText;

use kernel\Modules\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 文字识别（OCR）
 *
 * 对应微信「图像处理与文字识别 → OCR」分类（接口前缀 /cv/ocr）。
 *
 * 说明：接口支持传图片 url（img_url）；若需直接上传图片二进制，可在 $extra 中传 "img" => 文件内容。
 */
class Ocr extends WechatMiniProgramApi
{
  /**
   * 通用印刷体识别（POST /cv/ocr/comm）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/img-ocr/ocr/api_commocr.html
   *
   * @param string $imgUrl 图片 url
   * @param array $extra 附加参数，具体字段以官方文档为准
   * @return array 微信响应数组，成功时含 items（识别结果）
   */
  function comm($imgUrl, $extra = [])
  {
    return $this->post("cv/ocr/comm", array_merge([
      "img_url" => $imgUrl
    ], $extra))->getData();
  }
  /**
   * 身份证识别（POST /cv/ocr/idcard）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/img-ocr/ocr/api_idcardocr.html
   *
   * @param string $imgUrl 图片 url
   * @param array $extra 附加参数，具体字段以官方文档为准
   * @return array 微信响应数组，成功时含 idcard（身份证信息）
   */
  function idCard($imgUrl, $extra = [])
  {
    return $this->post("cv/ocr/idcard", array_merge([
      "img_url" => $imgUrl
    ], $extra))->getData();
  }
  /**
   * 银行卡识别（POST /cv/ocr/bankcard）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/img-ocr/ocr/api_bankcardocr.html
   *
   * @param string $imgUrl 图片 url
   * @param array $extra 附加参数，具体字段以官方文档为准
   * @return array 微信响应数组，成功时含 items（银行卡信息）
   */
  function bankCard($imgUrl, $extra = [])
  {
    return $this->post("cv/ocr/bankcard", array_merge([
      "img_url" => $imgUrl
    ], $extra))->getData();
  }
  /**
   * 营业执照识别（POST /cv/ocr/bizlicense）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/img-ocr/ocr/api_bizlicenseocr.html
   *
   * @param string $imgUrl 图片 url
   * @param array $extra 附加参数，具体字段以官方文档为准
   * @return array 微信响应数组，成功时含营业执照信息
   */
  function bizLicense($imgUrl, $extra = [])
  {
    return $this->post("cv/ocr/bizlicense", array_merge([
      "img_url" => $imgUrl
    ], $extra))->getData();
  }
  /**
   * 行驶证识别（POST /cv/ocr/driving）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/img-ocr/ocr/api_drivingocr.html
   *
   * @param string $imgUrl 图片 url
   * @param array $extra 附加参数，具体字段以官方文档为准
   * @return array 微信响应数组，成功时含行驶证信息
   */
  function driving($imgUrl, $extra = [])
  {
    return $this->post("cv/ocr/driving", array_merge([
      "img_url" => $imgUrl
    ], $extra))->getData();
  }
  /**
   * 驾驶证识别（POST /cv/ocr/drivinglicense）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/img-ocr/ocr/api_drivinglicenseocr.html
   *
   * @param string $imgUrl 图片 url
   * @param array $extra 附加参数，具体字段以官方文档为准
   * @return array 微信响应数组，成功时含驾驶证信息
   */
  function drivingLicense($imgUrl, $extra = [])
  {
    return $this->post("cv/ocr/drivinglicense", array_merge([
      "img_url" => $imgUrl
    ], $extra))->getData();
  }
}
