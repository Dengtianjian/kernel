<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

/**
 * PUT Object - Copy（复制对象）的输入容器
 *
 * 把该接口的专用请求头、条件复制头、目标对象元数据头、ACL 头、SSE 头都写成方法，
 * 调用约定（不传=读取 / 传值=设置并返回 `$this`）见 {@see AbstractQCloudCosObject}。
 *
 * ```php
 * $copy = (new QCloudCosPutObjectCopy())
 *     ->copySource("srv-1250000001.cos.ap-shanghai.myqcloud.com/example-%E8%85%BE%E8%AE%AF%E4%BA%91.jpg")
 *     ->replaceMetadata()                        // = metadataDirective("Replaced")
 *     ->contentType("image/jpeg")
 *     ->acl(QCloudCosPutObjectCopy::ACL_PUBLIC_READ);
 * ```
 *
 * ⚠️ 三条来自官方文档、实现/排障时必须知道的规则：
 * 1. **HTTP 200 不代表成功** —— 若错误发生在「复制执行期间」，仍返回 200 OK，错误信息在**响应体**里，
 *    必须解析响应体判断成败（本类不管请求，仅提醒调用方）；
 * 2. **默认不继承源对象的存储类型 / ACL / SSE** —— 除非显式指定，目标对象是标准存储、继承桶 ACL、不加密；
 * 3. **目标与源是同一个对象（即只改元数据）时，`x-cos-metadata-directive` 必须为 `Replaced`**；
 *    改标签时同理必须 `x-cos-tagging-directive: Replaced`。
 *
 * 其他要点：建议对象大小 1MB–5GB（更大用 Upload Part - Copy）；`x-cos-copy-source` 里的对象键需
 * URL encode，可用 `?versionId=` 指定源版本；**全球加速域名 / 用于全球加速源站的自定义域名不能作为 Copy 源**；
 * 源桶需 `cos:GetObject`、目标桶需 `cos:PutObject`（或源对象公有读）。
 *
 * 文档核对状态：**已按官方文档逐条核对**（PUT Object - Copy，product/436/10881，页面更新 2026-05-29）。
 * 该页**未列出** `x-cos-traffic-limit` ⇒ 本类不提供该方法（不按其他接口推断）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosPutObjectCopy extends AbstractQCloudCosObject
{
  /**
   * x-cos-copy-source（**必选**：源对象地址）
   *
   * 取值：`<源桶>.cos.<源地域>.myqcloud.com/<URL 编码后的对象键>`；
   * 需要指定源版本时追加 `?versionId=<VersionId>`。
   * **注意**：全球加速域名或用于全球加速源站的自定义域名不支持作为 Copy 源。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function copySource($value = null)
  {
    return $this->item("x-cos-copy-source", func_get_args());
  }

  /**
   * x-cos-metadata-directive（是否复制源对象元数据）
   *
   * 取值：{@see AbstractQCloudCosObject::DIRECTIVE_COPY}（默认，沿用源对象元数据）/
   * {@see AbstractQCloudCosObject::DIRECTIVE_REPLACED}（以本次请求头里的元数据作为目标对象元数据）。
   * **只改同一个对象的元数据时必须为 `Replaced`**。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function metadataDirective($value = null)
  {
    return $this->item("x-cos-metadata-directive", func_get_args());
  }

  /**
   * 便捷：`metadataDirective("Replaced")`（要在请求头里指定目标对象元数据时必须先设它）
   *
   * @return $this
   */
  public function replaceMetadata()
  {
    return $this->metadataDirective(self::DIRECTIVE_REPLACED);
  }

  /**
   * x-cos-tagging-directive（是否复制源对象标签）
   *
   * 取值：`Copy`（默认）/ `Replaced`；**只改同一个对象的标签时必须为 `Replaced`**。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function taggingDirective($value = null)
  {
    return $this->item("x-cos-tagging-directive", func_get_args());
  }

  /**
   * 便捷：`taggingDirective("Replaced")`
   *
   * @return $this
   */
  public function replaceTagging()
  {
    return $this->taggingDirective(self::DIRECTIVE_REPLACED);
  }

  /* ── 条件复制（源对象条件，不满足返回 412 Precondition Failed） ── */

  /**
   * x-cos-copy-source-If-Modified-Since（源对象在该时间**之后**被修改才复制）
   *
   * @param string|null $value RFC1123 GMT，如 `Tue, 08 Jun 2021 06:19:53 GMT`；不传=读取
   * @return mixed
   */
  public function copySourceIfModifiedSince($value = null)
  {
    return $this->item("x-cos-copy-source-If-Modified-Since", func_get_args());
  }

  /**
   * x-cos-copy-source-If-Unmodified-Since（源对象在该时间**之后未被**修改才复制）
   *
   * @param string|null $value RFC1123 GMT；不传=读取
   * @return mixed
   */
  public function copySourceIfUnmodifiedSince($value = null)
  {
    return $this->item("x-cos-copy-source-If-Unmodified-Since", func_get_args());
  }

  /**
   * x-cos-copy-source-If-Match（源对象 ETag 与指定值**一致**才复制）
   *
   * @param string|null $value ETag；不传=读取
   * @return mixed
   */
  public function copySourceIfMatch($value = null)
  {
    return $this->item("x-cos-copy-source-If-Match", func_get_args());
  }

  /**
   * x-cos-copy-source-If-None-Match（源对象 ETag 与指定值**不一致**才复制）
   *
   * @param string|null $value ETag；不传=读取
   * @return mixed
   */
  public function copySourceIfNoneMatch($value = null)
  {
    return $this->item("x-cos-copy-source-If-None-Match", func_get_args());
  }

  /* ── 目标对象元数据（前提：metadataDirective 为 Replaced） ────── */

  /**
   * Cache-Control（目标对象缓存指令）；**仅当 `metadataDirective=Replaced` 时才可用**
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function cacheControl($value = null)
  {
    return $this->item("Cache-Control", func_get_args());
  }

  /**
   * Content-Disposition（目标对象下载/预览行为）；**仅 `Replaced` 时可用**
   *
   * 取值：`inline` / `attachment` / `attachment; filename="a.jpg"`
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function contentDisposition($value = null)
  {
    return $this->item("Content-Disposition", func_get_args());
  }

  /**
   * Content-Encoding（目标对象编码格式）；**仅 `Replaced` 时可用**
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function contentEncoding($value = null)
  {
    return $this->item("Content-Encoding", func_get_args());
  }

  /**
   * Content-Type（目标对象 MIME）；**仅 `Replaced` 时可用**
   *
   * @param string|null $value 如 `image/jpeg`；不传=读取
   * @return mixed
   */
  public function contentType($value = null)
  {
    return $this->item("Content-Type", func_get_args());
  }

  /**
   * Expires（目标对象缓存失效时间）；**仅 `Replaced` 时可用**
   *
   * @param string|null $value RFC 2616 日期时间；不传=读取
   * @return mixed
   */
  public function expires($value = null)
  {
    return $this->item("Expires", func_get_args());
  }

  /* ── 源对象服务端加密（源对象用 SSE-C 时，三个头均为必选） ────── */

  /**
   * x-cos-copy-source-server-side-encryption-customer-algorithm（源对象 SSE-C 算法）
   *
   * 源对象使用 SSE-C 时**必选**；目前仅支持 `AES256`。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function copySourceCustomerAlgorithm($value = null)
  {
    return $this->item("x-cos-copy-source-server-side-encryption-customer-algorithm", func_get_args());
  }

  /**
   * x-cos-copy-source-server-side-encryption-customer-key（源对象 SSE-C 密钥，Base64）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function copySourceCustomerKey($value = null)
  {
    return $this->item("x-cos-copy-source-server-side-encryption-customer-key", func_get_args());
  }

  /**
   * x-cos-copy-source-server-side-encryption-customer-key-MD5（源对象 SSE-C 密钥 MD5，Base64）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function copySourceCustomerKeyMD5($value = null)
  {
    return $this->item("x-cos-copy-source-server-side-encryption-customer-key-MD5", func_get_args());
  }

  /**
   * 便捷：一次设置源对象的 SSE-C 三个头
   *
   * @param string $key Base64 形式的密钥
   * @param string $keyMD5 Base64 形式的密钥 MD5
   * @return $this
   */
  public function copySourceSseCustomer($key, $keyMD5)
  {
    return $this->copySourceCustomerAlgorithm("AES256")->copySourceCustomerKey($key)->copySourceCustomerKeyMD5($keyMD5);
  }
}
