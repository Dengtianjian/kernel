<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload;

use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * Upload Part - Copy（通过复制已有对象或其一部分来生成分块）的输入容器
 *
 * 请求：`PUT /<ObjectKey>?partNumber=N&uploadId=X`。
 * `partNumber` / `uploadId` 是 **URL 参数（参与签名）**。
 *
 * 头（与 PUT Object - Copy 同源对象参数）：
 * - `x-cos-copy-source`：源对象，格式 `{bucket}.cos.{region}.myqcloud.com/{已编码源键}`
 * - `x-cos-copy-source-Range`：源字节范围，如 `bytes=0-1023`
 * - `x-cos-copy-source-SSE-Customer-*`：**源对象**用 SSE-C 加密时需要（注意是带 `copy-source-`
 *   前缀的版本，与「目标对象 SSE-C」的 `x-cos-server-side-encryption-customer-*` 不同）
 *
 * 文档核对状态：**待核对**（按官方 Upload Part - Copy 通用写法整理；首次接入对真实桶复核）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload
 */
class QCloudCosUploadPartCopy extends AbstractQCloudCosObject
{
  /**
   * partNumber（分块编号，URL 参数）
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
   * x-cos-copy-source（源对象路径）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function copySource($value = null)
  {
    return $this->item("x-cos-copy-source", func_get_args());
  }

  /**
   * x-cos-copy-source-Range（源字节范围）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function copySourceRange($value = null)
  {
    return $this->item("x-cos-copy-source-Range", func_get_args());
  }

  /**
   * x-cos-copy-source-SSE-Customer-Algorithm（源对象 SSE-C 算法，固定 AES256）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function copySourceCustomerAlgorithm($value = null)
  {
    return $this->item("x-cos-copy-source-SSE-Customer-Algorithm", func_get_args());
  }

  /**
   * x-cos-copy-source-SSE-Customer-Key（源对象 SSE-C 密钥，Base64）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function copySourceCustomerKey($value = null)
  {
    return $this->item("x-cos-copy-source-SSE-Customer-Key", func_get_args());
  }

  /**
   * x-cos-copy-source-SSE-Customer-Key-MD5（源对象 SSE-C 密钥 MD5，Base64）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function copySourceCustomerKeyMD5($value = null)
  {
    return $this->item("x-cos-copy-source-SSE-Customer-Key-MD5", func_get_args());
  }
}
