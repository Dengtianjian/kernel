<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload;

use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * List Multipart Uploads（列举进行中的分块上传）的输入容器
 *
 * 请求：`GET /<ObjectKey>?uploads[&prefix=][&delimiter=][&max-uploads=][&key-marker=][&upload-id-marker=][&encoding-type=]`。
 *
 * - `?uploads` 是**子资源**（参与签名，且必须出现在请求 URL 上）—— 用 {@see uploads()} 设置；
 * - 其余都是 **URL 参数（参与签名）**。
 *
 * 成功返回 200，响应体为 `<ListMultipartUploadsResult>`。
 *
 * 文档核对状态：**待核对**（按官方 List Multipart Uploads 通用写法整理；首次接入对真实桶复核）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload
 */
class QCloudCosListMultipartUploads extends AbstractQCloudCosObject
{
  /**
   * uploads（子资源标记；设空串即可，如 `$obj->uploads("")`）
   *
   * @param string|null $value 不传=读取（返回当前值或 null）
   * @return mixed
   */
  public function uploads($value = null)
  {
    return $this->item("uploads", func_get_args());
  }

  /**
   * prefix（按对象键前缀过滤）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function prefix($value = null)
  {
    return $this->item("prefix", func_get_args());
  }

  /**
   * delimiter（分隔符，用于模拟文件夹层次）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function delimiter($value = null)
  {
    return $this->item("delimiter", func_get_args());
  }

  /**
   * max-uploads（单次列举的最大上传数，默认 1000）
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function maxUploads($value = null)
  {
    return $this->item("max-uploads", func_get_args());
  }

  /**
   * key-marker（分页起点：从上一次返回的 `NextKeyMarker` 取）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function keyMarker($value = null)
  {
    return $this->item("key-marker", func_get_args());
  }

  /**
   * upload-id-marker（分页起点：配合 key-marker 使用）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function uploadIdMarker($value = null)
  {
    return $this->item("upload-id-marker", func_get_args());
  }

  /**
   * encoding-type（对响应中的 delimiter 之后的内容做 URL 编码，如 `url`）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function encodingType($value = null)
  {
    return $this->item("encoding-type", func_get_args());
  }
}
