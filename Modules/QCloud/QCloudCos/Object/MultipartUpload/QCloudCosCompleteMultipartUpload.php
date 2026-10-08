<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload;

use kernel\Foundation\Data\Arr;
use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * Complete Multipart Upload（完成分块上传）的输入容器
 *
 * 请求：`POST /<ObjectKey>?uploadId=X`。
 * 请求体 XML：
 * ```xml
 * <CompleteMultipartUpload>
 *   <Part><PartNumber>1</PartNumber><ETag>"etag1"</ETag></Part>
 *   <Part><PartNumber>2</PartNumber><ETag>"etag2"</ETag></Part>
 * </CompleteMultipartUpload>
 * ```
 * `uploadId` 是 **URL 参数（参与签名）**；`ETag` 取各 UploadPart 响应头里的 `ETag`（带不带引号均可，
 * COS 以实际为准）。
 *
 * 文档核对状态：**待核对**（按官方 Complete Multipart Upload 通用写法整理；首次接入对真实桶复核）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload
 */
class QCloudCosCompleteMultipartUpload extends AbstractQCloudCosObject
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
   * 追加一个分块（PartNumber => ETag）
   *
   * @param integer $partNumber 分块编号（从 1 开始）
   * @param string $etag 该分块的 ETag（可带引号）
   * @return $this
   */
  public function part($partNumber, $etag)
  {
    $parts = $this->item("Parts", []);
    $parts[] = ["PartNumber" => (int) $partNumber, "ETag" => (string) $etag];

    return $this->item("Parts", [$parts]);
  }

  /**
   * 批量设置分块清单
   *
   * 支持两种写法：
   * - 列表对：`[[1, "etag1"], [2, "etag2"]]`
   * - 关联数组（含 `PartNumber` / `ETag` 键）：`[["PartNumber" => 1, "ETag" => "etag1"], …]`
   *
   * @param array $parts 分块清单
   * @return $this
   */
  public function parts(array $parts)
  {
    $normalized = [];
    foreach ($parts as $entry) {
      if (is_array($entry) && array_keys($entry) === [0, 1]) {
        $normalized[] = ["PartNumber" => (int) $entry[0], "ETag" => (string) $entry[1]];
      } elseif (is_array($entry) && isset($entry["PartNumber"])) {
        $normalized[] = ["PartNumber" => (int) $entry["PartNumber"], "ETag" => (string) $entry["ETag"]];
      }
    }

    return $this->item("Parts", [$normalized]);
  }

  /**
   * 产出请求体 XML（`<CompleteMultipartUpload>…</CompleteMultipartUpload>`）
   *
   * 用 {@see Arr::toXML()} 拼：`Part` 是列表 ⇒ 展开成重复同名子元素；字符串逐值转义。
   *
   * @return string XML 请求体；未设置 parts 时返回空串（调用方应先校验）
   */
  public function toXml()
  {
    $parts = $this->item("Parts", []);
    if (!$parts) return "";

    $items = array_map(function ($part) {
      return [
        "PartNumber" => $part["PartNumber"],
        "ETag" => htmlspecialchars((string) $part["ETag"], ENT_QUOTES | ENT_XML1, "UTF-8"),
      ];
    }, $parts);

    return Arr::toXML(["Part" => $items], true, "CompleteMultipartUpload", false);
  }
}
