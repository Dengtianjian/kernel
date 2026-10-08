<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload;

use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * Abort Multipart Upload（终止分块上传并释放已上传的分块）的输入容器
 *
 * 请求：`DELETE /<ObjectKey>?uploadId=X`。`uploadId` 是 **URL 参数（参与签名）**。
 * 成功返回 **204**（无内容）；若 UploadId 不存在或已结束，COS 返回 404。
 *
 * ⚠️ **本接口没有专用请求头/请求体** —— 仅保留 `uploadId()` 一个 URL 参数方法（及基类容器能力）；
 * 保留它是为了让分块上传这一组的接口与 `Object/MultipartUpload` 目录一一对应。
 *
 * 文档核对状态：**待核对**（按官方 Abort Multipart Upload 通用写法整理；首次接入对真实桶复核）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload
 */
class QCloudCosAbortMultipartUpload extends AbstractQCloudCosObject
{
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
}
