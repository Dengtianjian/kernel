<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload;

use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * Upload Part（上传分块）的输入容器
 *
 * 请求：`PUT /<ObjectKey>?partNumber=N&uploadId=X`。
 *
 * `partNumber` / `uploadId` 是 **URL 参数（参与签名）** —— 必须**同时**出现在签名的 `q-url-param-list`
 * 与真实请求 URL 上（接线时归入 `$options['params']`）。
 *
 * 头：`Content-MD5`（可选，校验块完整性）、`Content-Length`（块字节数）、
 * `x-cos-server-side-encryption-customer-*`（SSE-C，逐块覆盖密钥 —— 基类已提供
 * `customerAlgorithm()` / `customerKey()` / `customerKeyMD5()`）。
 *
 * 请求体（块的二进制数据）由**发送层单独传入**，本容器只持有头与 URL 参数。
 *
 * 文档核对状态：**待核对**（按官方 Upload Part 通用写法整理；首次接入对真实桶复核）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload
 */
class QCloudCosUploadPart extends AbstractQCloudCosObject
{
  /**
   * partNumber（分块编号，从 1 开始，URL 参数）
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function partNumber($value = null)
  {
    return $this->item("partNumber", func_get_args());
  }

  /**
   * uploadId（本次分块上传的 ID，URL 参数）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function uploadId($value = null)
  {
    return $this->item("uploadId", func_get_args());
  }

  /**
   * Content-MD5（块的 MD5 的 Base64 值，可选校验）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function contentMD5($value = null)
  {
    return $this->item("Content-MD5", func_get_args());
  }

  /**
   * Content-Length（块的字节数）
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function contentLength($value = null)
  {
    return $this->item("Content-Length", func_get_args());
  }
}
