<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

/**
 * GET Object（读取对象）的输入容器
 *
 * 把读取时可带的请求头写成方法（调用约定见 {@see AbstractQCloudCosObject}）；
 * ACL / 存储类型 / meta / SSE 等「目标对象属性」头在 GET 场景无意义，本类不提供。
 *
 * ```php
 * $get = (new QCloudCosGetObject())
 *     ->range("bytes=0-1023")                                  // 断点续传/取片段（成功码 206）
 *     ->ifNoneMatch('"abc123"')                                // 条件读取，可省流量
 *     ->responseContentDisposition('attachment; filename="a.png"')  // 覆盖响应头（不改对象元数据）
 *     ->responseCacheControl("max-age=60");
 * ```
 *
 * ⚠️ 两处与「头」无关、但很容易混淆的输入，**不在本类**：
 * - **对象版本**：走 URL 参数 `?versionId=…`（不是请求头）；
 * - **图片处理 / 数据万象**：走 URL 查询参数（如 `?imageMogr2/thumbnail/!50p`、`?ci-process=…`），
 *   且这类参数**不入签**、必须**逐字**拼进 URL（详见相关设计说明）。
 *
 * ⚠️ 文档核对状态：**待核对**（本页头清单依据通用实践整理，尚未逐条对官方 GET Object 页面）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosGetObject extends AbstractQCloudCosObject
{
  /**
   * Range（读取的字节范围）
   *
   * 形如 `bytes=0-1023`、`bytes=0-`（从偏移读到末尾）；命中时 COS 返回 **206 Partial Content**。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function range($value = null)
  {
    return $this->item("Range", func_get_args());
  }

  /**
   * x-cos-traffic-limit（本次下载的限速值，bit/s）
   *
   * ⚠️ 待核对：GET Object 是否支持该头及其取值范围（PUT Object 页给的是 819200–838860800）。
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function trafficLimit($value = null)
  {
    return $this->item("x-cos-traffic-limit", func_get_args());
  }

  /* ── 条件读取（不满足时按 HTTP 语义返回 304 或 412） ───────────── */

  /**
   * If-Modified-Since（对象在该时间**之后**被修改才返回内容）
   *
   * @param string|null $value RFC1123 GMT；不传=读取
   * @return mixed
   */
  public function ifModifiedSince($value = null)
  {
    return $this->item("If-Modified-Since", func_get_args());
  }

  /**
   * If-Unmodified-Since（对象在该时间**之后未被**修改才返回内容）
   *
   * @param string|null $value RFC1123 GMT；不传=读取
   * @return mixed
   */
  public function ifUnmodifiedSince($value = null)
  {
    return $this->item("If-Unmodified-Since", func_get_args());
  }

  /**
   * If-Match（对象 ETag 与指定值**一致**才返回内容）
   *
   * @param string|null $value ETag；不传=读取
   * @return mixed
   */
  public function ifMatch($value = null)
  {
    return $this->item("If-Match", func_get_args());
  }

  /**
   * If-None-Match（对象 ETag 与指定值**不一致**才返回内容）
   *
   * @param string|null $value ETag；不传=读取
   * @return mixed
   */
  public function ifNoneMatch($value = null)
  {
    return $this->item("If-None-Match", func_get_args());
  }

  /* ── 响应头覆盖（只影响本次响应，不改对象自身元数据） ─────────── */

  /**
   * response-cache-control（覆盖本次响应的 Cache-Control）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function responseCacheControl($value = null)
  {
    return $this->item("response-cache-control", func_get_args());
  }

  /**
   * response-content-disposition（覆盖本次响应的 Content-Disposition）
   *
   * 常用于"同一对象按不同文件名下载"，例如 `attachment; filename="new.png"`。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function responseContentDisposition($value = null)
  {
    return $this->item("response-content-disposition", func_get_args());
  }

  /**
   * response-content-encoding（覆盖本次响应的 Content-Encoding）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function responseContentEncoding($value = null)
  {
    return $this->item("response-content-encoding", func_get_args());
  }

  /**
   * response-content-language（覆盖本次响应的 Content-Language）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function responseContentLanguage($value = null)
  {
    return $this->item("response-content-language", func_get_args());
  }

  /**
   * response-content-type（覆盖本次响应的 Content-Type）
   *
   * @param string|null $value 如 `image/png`；不传=读取
   * @return mixed
   */
  public function responseContentType($value = null)
  {
    return $this->item("response-content-type", func_get_args());
  }

  /**
   * response-expires（覆盖本次响应的 Expires）
   *
   * @param string|null $value RFC 2616 日期时间；不传=读取
   * @return mixed
   */
  public function responseExpires($value = null)
  {
    return $this->item("response-expires", func_get_args());
  }
}
