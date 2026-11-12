<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload;

use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * Initiate Multipart Upload（初始化分块上传）的输入容器
 *
 * 请求：`POST /<ObjectKey>?uploads`。
 * 响应体含 `<InitiateMultipartUploadResult><UploadId>` —— **后续每个 UploadPart / CompleteMultipartUpload
 * 都要带上这个 UploadId**（它是 URL 参数，参与签名）。
 *
 * 本接口支持的对象级头与「上传对象」一致（基类已提供，直接可用）：`acl()` / 四个 `grant*()` /
 * `storageClass()` / `meta()` / `tagging()` / 目标对象 SSE 六个头（`serverSideEncryption()`…`sse*()`）。
 * 仅 `Content-Type`（对象的 MIME）是分块场景的必填项、基类没有，这里补上。
 *
 * 文档核对状态：**待核对**（按官方 Initiate Multipart Upload 通用写法整理；首次接入对真实桶复核）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload
 */
class QCloudCosInitiateMultipartUpload extends AbstractQCloudCosObject
{
  /**
   * Content-Type（对象的 MIME，分块上传时通常在初始化阶段指定）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function contentType($value = null)
  {
    return $this->item("Content-Type", func_get_args());
  }
}
