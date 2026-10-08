<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

/**
 * PUT Object（上传对象）的输入容器
 *
 * 把「上传对象时能带的请求头」写成方法，避免调用方去记 `x-cos-*` 的字面量与取值。
 *
 * **继承关系**：本类继承 {@see AbstractQCloudCosObject}，因此以下通用能力由基类提供，不在此重复：
 * - 容器：`all()` / `isEmpty()` / `clear()` / `merge()`（`headers()` 是本类与 `all()` 的等价别名）；
 * - 对象属性：`storageClass()` / `tagging()` / `tag()` / `forbidOverwrite()`；
 * - 自定义元数据：`meta()` / `metas()` / `allMetas()`；
 * - ACL：`acl()` / `grantRead()` / `grantReadAcp()` / `grantWriteAcp()` / `grantFullControl()` / 静态 `grantId()`；
 * - 服务端加密：`serverSideEncryption()` / `kmsKeyId()` / `kmsContext()` / `customerAlgorithm()` /
 *   `customerKey()` / `customerKeyMD5()` 及 `sseCos()` / `sseKms()` / `sseCustomer()`；
 * - 常量：`ACL_*`、`STORAGE_*`、`DIRECTIVE_*`（可透过本类访问，如 `QCloudCosPutObject::ACL_PUBLIC_READ`）。
 *
 * 本类只保留 **PUT Object 特有**的部分：常规请求头（`Cache-Control` / `Content-Disposition` /
 * `Content-Encoding` / `Content-Type` / `Content-MD5` / `Expires` / `Transfer-Encoding`）与单链接限速头。
 *
 * 调用约定（所有此类方法一致）：
 * - **不传值 = 读取**：`$put->acl()` 返回当前值（未设置时返回 null）
 * - **传值 = 设置**：`$put->acl("public-read")` 写入并返回 `$this`，可链式
 *
 * ```php
 * $headers = (new QCloudCosPutObject())
 *     ->contentType("image/png")                       // 必选头，一般由上传逻辑给出
 *     ->cacheControl("max-age=31536000")
 *     ->acl(QCloudCosPutObject::ACL_PUBLIC_READ)       // 来自基类常量
 *     ->storageClass(QCloudCosPutObject::STORAGE_STANDARD_IA)
 *     ->meta("src", "web")                             // → x-cos-meta-src
 *     ->tag(["scene" => "avatar"])                     // → x-cos-tagging
 *     ->headers();                                     // 汇总成 ["Content-Type" => …, "x-cos-acl" => …]
 * ```
 *
 * 与签名/请求的关系（配合 {@see \kernel\Modules\QCloud\QCloudCos\QCloudCosSignture} 使用时注意）：
 * - `x-cos-` 开头的头**一律参与签名**（含 ACL / meta / storage-class / encryption / tagging 等）；
 * - 常规头里 `Cache-Control` / `Content-Disposition` / `Content-Encoding` / `Content-MD5` /
 *   `Content-Type` / `Expires` / `Transfer-Encoding` 都在签名器的白名单内，**也会入签**；
 * - 所以本类产出的头必须**同时**用于「签名」与「真实请求」，两边逐字一致，否则 COS 会判签名不符。
 *
 * 实现现状：
 * - 本类只是**值容器**（不发起请求、不做网络/校验），取值合法性以注释说明为准，不抛异常；
 * - `Expect: 100-continue` **不在** COS 文档的请求头清单里（属于客户端行为，cURL 层处理），故本类不提供。
 *
 * 文档依据：腾讯云 COS「PUT Object」（product/436/7749，页面更新于 2026-06-11）。
 * 其中 `x-cos-grant-write` **未被该页面列出**，因此基类只提供文档列出的四个 grant 头。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosPutObject extends AbstractQCloudCosObject
{
  /**
   * 取出全部已设置的头（与基类的 {@see AbstractQCloudCosObject::all()} 等价）
   *
   * 保留这个别名是因为「PUT Object 的输入就是请求头」，用 `headers()` 更贴合语义。
   *
   * @return array 头名 => 值（只含已显式设置的项 ⇒ "没设就不发"）
   */
  public function headers()
  {
    return $this->all();
  }

  /* ── 常规请求头 ───────────────────────────────────────────────── */

  /**
   * Cache-Control（RFC 2616 缓存指令，会作为对象元数据保存）
   *
   * 在签名器白名单内 ⇒ 会参与签名。
   *
   * @param string|null $value 不传=读取
   * @return mixed 读取时返回当前值；设置时返回 $this
   */
  public function cacheControl($value = null)
  {
    return $this->item("Cache-Control", func_get_args());
  }

  /**
   * Content-Disposition（下载/预览行为，会作为对象元数据保存）
   *
   * 允许取值：`inline`（直接预览）、`attachment`（以原文件名下载）、
   * `attachment; filename="example.jpg"`（自定义下载文件名）。可先用 {@see inline()} / {@see attachment()}。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function contentDisposition($value = null)
  {
    return $this->item("Content-Disposition", func_get_args());
  }

  /**
   * 便捷：设为 `inline`（浏览器直接预览，如图片）
   *
   * @return $this
   */
  public function inline()
  {
    return $this->contentDisposition("inline");
  }

  /**
   * 便捷：设为 `attachment`（下载）
   *
   * @param string|null $fileName 自定义下载文件名；不传则 `attachment`（以原文件名下载）
   * @return $this
   */
  public function attachment($fileName = null)
  {
    return $this->contentDisposition($fileName ? "attachment; filename=\"{$fileName}\"" : "attachment");
  }

  /**
   * Content-Encoding（RFC 2616 编码格式，会作为对象元数据保存，如 gzip）
   *
   * 在签名器白名单内 ⇒ 会参与签名。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function contentEncoding($value = null)
  {
    return $this->item("Content-Encoding", func_get_args());
  }

  /**
   * Content-Type（MIME；**PUT Object 的必选头**，会作为对象元数据保存）
   *
   * 在签名器白名单内 ⇒ 会参与签名。上传前记得设上（否则对象类型多半是 octet-stream）。
   *
   * @param string|null $value 如 `image/png`；不传=读取
   * @return mixed
   */
  public function contentType($value = null)
  {
    return $this->item("Content-Type", func_get_args());
  }

  /**
   * Content-MD5（内容的 MD5 的 Base64 值，用于 COS 校验上传内容）
   *
   * ⚠️ COS 要求的是 **Base64**（如 `U5L61r7jcwdNvT7frmUG8g==`），不是十六进制。
   * 在签名器白名单内 ⇒ 会参与签名。可先用 {@see contentMD5Base64()} 计算。
   *
   * @param string|null $value Base64 形式的 MD5；不传=读取
   * @return mixed
   */
  public function contentMD5($value = null)
  {
    return $this->item("Content-MD5", func_get_args());
  }

  /**
   * 便捷：按本地文件内容计算 Content-MD5（Base64）
   *
   * 大文件会把内容读进内存计算（PHP 无流式 md5）⇒ 超大对象建议由调用方分批或省略该校验。
   *
   * @param string $localFilePath 本地文件路径
   * @return $this
   */
  public function contentMD5Base64($localFilePath)
  {
    return $this->contentMD5(base64_encode(md5_file($localFilePath, true)));
  }

  /**
   * Expires（RFC 2616 绝对日期时间，会作为对象元数据保存）
   *
   * 在签名器白名单内 ⇒ 会参与签名。
   *
   * @param string|null $value 如 `Wed, 21 Oct 2026 07:28:00 GMT`；不传=读取
   * @return mixed
   */
  public function expires($value = null)
  {
    return $this->item("Expires", func_get_args());
  }

  /**
   * Transfer-Encoding（传输编码）
   *
   * 仅 `chunked`（分块传输时指定；**指定后不能再带 Content-Length**）。在签名器白名单内 ⇒ 会参与签名。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function transferEncoding($value = null)
  {
    return $this->item("Transfer-Encoding", func_get_args());
  }

  /* ── PUT Object 特有的专用请求头 ──────────────────────────────── */

  /**
   * x-cos-traffic-limit（本次上传的限速值，单位 bit/s）
   *
   * 必须为数字，允许范围 **819200 – 838860800**（800Kb/s – 800Mb/s），超出会被 COS 以 400 拒绝。
   * 本类不做范围校验（保持"纯容器"），调用方注意。
   *
   * 说明：`x-cos-storage-class` / `x-cos-tagging` / `x-cos-forbid-overwrite` / `x-cos-meta-*`
   * 同样是上传时的专用头，但它们在多个操作中共用 ⇒ 已放在基类 {@see AbstractQCloudCosObject}。
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function trafficLimit($value = null)
  {
    return $this->item("x-cos-traffic-limit", func_get_args());
  }
}
