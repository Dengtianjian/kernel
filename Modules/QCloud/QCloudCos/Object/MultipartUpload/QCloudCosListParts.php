<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload;

use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * List Parts（列举已上传的分块）的输入容器
 *
 * 请求：`GET /<ObjectKey>?uploadId=X[&max-parts=N][&part-number-marker=M]`。
 * `uploadId` / `max-parts` / `part-number-marker` 都是 **URL 参数（参与签名）**。
 * 成功返回 200，响应体为 `<ListPartsResult>`。
 *
 * 文档核对状态：**待核对**（按官方 List Parts 通用写法整理；首次接入对真实桶复核）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload
 */
class QCloudCosListParts extends AbstractQCloudCosObject
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

  /**
   * max-parts（单次列举的最大分块数，默认 1000）
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function maxParts($value = null)
  {
    return $this->item("max-parts", func_get_args());
  }

  /**
   * part-number-marker（分页起点：从上一次返回的 `NextPartNumberMarker` 取）
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function partNumberMarker($value = null)
  {
    return $this->item("part-number-marker", func_get_args());
  }
}
