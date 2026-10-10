<?php

namespace kernel\Platform\Wechat\Miniprogram\XPay;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 虚拟支付 - 文件上传
 *
 * 对应微信「虚拟支付」分类下媒体文件上传相关接口（接口前缀 /xpay）。
 */
class File extends WechatMiniProgramApi
{
  /**
   * 上传媒体文件（POST /xpay/upload_vp_file，multipart/form-data）
   *
   * 该接口为 multipart 上传，不走父类的 post() 封装，因此需手动附加 access_token。
   *
   * @param string $filePath 本地文件绝对路径
   * @param string $fieldName 表单字段名（默认 "media"）
   * @param array $extra 附加 query 参数，字段以官方文档为准
   * @return array 微信响应数组，成功时含 file_id / media_id 等
   */
  function uploadFile($filePath, $fieldName = "media", $extra = [])
  {
    $query = array_merge($extra, [
      "access_token" => $this->accessToken()
    ]);

    return $this->CURL->url($this->apiUrl . "/xpay/upload_vp_file", $query)
      ->file($filePath, $fieldName)
      ->post()
      ->getData();
  }
  /**
   * 获取微信支付反馈投诉图片的签名头部（POST /xpay/get_upload_file_sign）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/VirtualPayment/api_get_upload_file_sign.html
   *
   * @param array $data 请求体字段以官方文档为准
   * @return array 微信响应数组，成功时含签名头部信息
   */
  function getUploadFileSign($data = [])
  {
    return $this->post("xpay/get_upload_file_sign", $data)->getData();
  }
}
