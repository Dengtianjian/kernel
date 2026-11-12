<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

/**
 * HEAD Object（取对象元信息）的输入容器
 *
 * 只发 HEAD、**不返回内容**，因此可带的输入比 GET 少：主要是条件头和 SSE-C（解密元信息时需要）。
 * 调用约定见 {@see AbstractQCloudCosObject}。
 *
 * ```php
 * $head = (new QCloudCosHeadObject())
 *     ->ifNoneMatch('"abc123"');        // 未变更则 304，省一次传输
 * ```
 *
 * 与 {@see QCloudCosGetObject} 的差别：
 * - HEAD **没有** `Range`（不返回内容，无区间可分）；
 * - `response-*` 覆盖头是否适用于 HEAD **待核对** ⇒ 本类**未提供**（如确认支持可补，加一行即可）。
 *
 * ⚠️ 文档核对状态：**待核对**（本页头清单依据通用实践整理，尚未逐条对官方 HEAD Object 页面）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosHeadObject extends AbstractQCloudCosObject
{
  /**
   * If-Modified-Since（对象在该时间**之后**被修改才返回）
   *
   * @param string|null $value RFC1123 GMT；不传=读取
   * @return mixed
   */
  public function ifModifiedSince($value = null)
  {
    return $this->item("If-Modified-Since", func_get_args());
  }

  /**
   * If-Unmodified-Since（对象在该时间**之后未被**修改才返回）
   *
   * @param string|null $value RFC1123 GMT；不传=读取
   * @return mixed
   */
  public function ifUnmodifiedSince($value = null)
  {
    return $this->item("If-Unmodified-Since", func_get_args());
  }

  /**
   * If-Match（对象 ETag 与指定值**一致**才返回）
   *
   * @param string|null $value ETag；不传=读取
   * @return mixed
   */
  public function ifMatch($value = null)
  {
    return $this->item("If-Match", func_get_args());
  }

  /**
   * If-None-Match（对象 ETag 与指定值**不一致**才返回）
   *
   * @param string|null $value ETag；不传=读取
   * @return mixed
   */
  public function ifNoneMatch($value = null)
  {
    return $this->item("If-None-Match", func_get_args());
  }
}
