<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

use kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload\QCloudCosMultipartUpload;
use kernel\Modules\QCloud\QCloudCos\QCloudCosSignture;

/**
 * 桶级操作入口：持有 bucket / region，**按传入的对象名去操作**
 *
 * 与 {@see QCloudCosObject} 的区别：后者是「一个实例 = 一个对象」（绑定键），
 * 本类是「一个实例 = 一个桶」（**不绑定任何对象**），每次操作都靠传入对象名 ——
 * 适合"一次处理很多对象"的场景（批量删、列举、按名字搬运）。
 *
 * ```php
 * $objects = new QCloudCosObjects("bkt-1250000000", "ap-guangzhou");
 *
 * $objects->upload("images/a.png", "/tmp/a.png");      // 自动按大小选简单/分块
 * $objects->download("images/a.png", "bytes=0-1023");  // 取片段
 * $objects->copy("a.png", "backup/a.png");             // 自动补 x-cos-copy-source
 * $objects->move("a.png", "archive/a.png");            // 复制 + 删源（COS 无原生 move）
 * $objects->listObjects("images/", ["max-keys" => 100]);
 * $objects->delete("images/a.png");
 * $objects->exists("images/a.png");
 * $objects->metadata("images/a.png");
 * $objects->restore("cold.zip", 7, "Expedited");
 *
 * $objects->objectUrl("images/a.png");    // 真算出可访问地址（无需签名）
 * $objects->presignedUrl("images/a.png"); // 给了签名器 ⇒ 真算出完整签名 URL
 * ```
 *
 * ## ⚠️ 只产出计划，不发请求
 *
 * 每个操作方法返回的是**步骤数组**（`phase/method/path/params/headers/body/successStatuses/note`），
 * 不是执行结果。只有两个 URL 方法是"真算"的（纯字符串/签名计算，**不涉及 HTTP**）：
 * - {@see objectUrl()}：拼接可访问地址（公共读对象可直接用）；
 * - {@see presignedUrl()}：**传入签名器**时算出完整签名 URL；没传签名器时返回"待签参数"，
 *   交给调用方自己的签名器处理。
 *
 * `params` 里的都是**参与签名的 URL 参数**（子资源如 `?restore`、业务参数如 `prefix`/`max-keys`），
 * 必须**同时**进签名与真实 URL。
 *
 * 文档核对状态：**待核对**（各操作的请求形态沿用对应容器类的说明；首次接入对真实桶复核）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosObjects
{
  /** 默认域名格式（HTTPS） */
  const HOST_PATTERN = "https://%s.cos.%s.myqcloud.com";

  /** @var string|null 桶名 */
  protected $bucket;
  /** @var string|null 地域 */
  protected $region;
  /** @var string|null 桶域名（默认由 bucket/region 拼出） */
  protected $host;
  /** @var QCloudCosSignture|null 签名器（可选；仅 {@see presignedUrl()} 需要） */
  protected $signer;

  /**
   * @param string $bucket 桶名（含 APPID，如 `bkt-1250000000`）
   * @param string $region 地域（如 `ap-guangzhou`）
   * @param string|null $host 桶域名；不传则按 {@see HOST_PATTERN} 拼
   * @param QCloudCosSignture|null $signer 签名器（可选，用于生成预签名 URL）
   */
  public function __construct($bucket, $region, $host = null, $signer = null)
  {
    $this->bucket = $bucket;
    $this->region = $region;
    $this->host = $host !== null ? $host : sprintf(self::HOST_PATTERN, $bucket, $region);
    $this->signer = $signer;
  }

  /**
   * 桶名（不传=读取，传值=设置）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function bucket($value = null)
  {
    if (!func_get_args()) return $this->bucket;

    $this->bucket = (string) $value;

    return $this;
  }

  /**
   * 地域（不传=读取，传值=设置）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function region($value = null)
  {
    if (!func_get_args()) return $this->region;

    $this->region = (string) $value;

    return $this;
  }

  /**
   * 桶域名（不传=读取，传值=设置）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function host($value = null)
  {
    if (!func_get_args()) return $this->host;

    $this->host = (string) $value;

    return $this;
  }

  /**
   * 签名器（不传=读取，传值=设置）
   *
   * @param QCloudCosSignture|null $value 不传=读取
   * @return mixed
   */
  public function signer($value = null)
  {
    if (!func_get_args()) return $this->signer;

    $this->signer = $value;

    return $this;
  }

  /**
   * 对象键编码后的路径（按段 `rawurlencode` + 前导 `/`）
   *
   * @param string $objectName 原始对象键
   * @return string
   */
  public function path($objectName)
  {
    return self::encodePath((string) $objectName);
  }

  /**
   * 拼出 `x-cos-copy-source` 需要的值（**不含**协议头）
   *
   * @param string $objectName 对象键
   * @return string 形如 `bkt-1250000000.cos.ap-guangzhou.myqcloud.com/images/a.png`
   */
  public function source($objectName)
  {
    return "{$this->bucket}.cos.{$this->region}.myqcloud.com" . $this->path($objectName);
  }

  /* ── 对象操作（都只产出计划） ─────────────────────────────────── */

  /**
   * 上传对象：按文件大小自动选**简单上传**或**分块上传**
   *
   * 委托 {@see QCloudCosUploadObject}（它会按大小决策）；`$headers` 会并入简单上传的
   * 请求头（如 `x-cos-acl` / `Content-Type`）。
   *
   * @param string $objectName 对象键
   * @param string $localFilePath 本地文件路径
   * @param array $headers 额外的请求头（仅简单上传路线生效）
   * @param integer $partSize 分块大小（分块路线生效）
   * @return array 步骤数组（简单上传 1 步；分块为 initiate → 各 part → complete）
   * @throws \InvalidArgumentException 文件不存在/不可读、块大小非法（同 {@see QCloudCosUploadObject}）
   */
  public function upload($objectName, $localFilePath, array $headers = [], $partSize = QCloudCosMultipartUpload::DEFAULT_PART_SIZE)
  {
    $uploader = new QCloudCosUploadObject($objectName);
    if ($headers) $uploader->put()->merge($headers);

    return $uploader->plan($localFilePath, $partSize);
  }

  /**
   * 下载对象（GET Object）
   *
   * @param string $objectName 对象键
   * @param string|null $range HTTP Range，如 `bytes=0-1023`；不传取整个对象
   * @param array $headers 额外的请求头（如 `If-Match`、`response-content-type` 等）
   * @return array 步骤数组
   */
  public function download($objectName, $range = null, array $headers = [])
  {
    if ($range !== null) $headers["Range"] = $range;

    return [[
      "phase" => "get",
      "objectName" => $objectName,
      "method" => "GET",
      "path" => $this->path($objectName),
      "params" => [],
      "headers" => $headers,
      "body" => null,
      "successStatuses" => [200, 206],
      "note" => "带 Range 时成功码为 206；内容整体进内存（现有 Curl 强制 RETURNTRANSFER，无法流式落盘）",
    ]];
  }

  /**
   * 复制对象（PUT Object - Copy）—— 自动补 `x-cos-copy-source`
   *
   * @param string $sourceObjectName 源对象键
   * @param string $destinationObjectName 目标对象键
   * @param array $headers 额外的请求头（如 `x-cos-metadata-directive`；已给的 `x-cos-copy-source` 不会被覆盖）
   * @return array 步骤数组
   */
  public function copy($sourceObjectName, $destinationObjectName, array $headers = [])
  {
    $headers = array_merge(["x-cos-copy-source" => $this->source($sourceObjectName)], $headers);

    return [[
      "phase" => "copy",
      "objectName" => $destinationObjectName,
      "sourceObjectName" => $sourceObjectName,
      "method" => "PUT",
      "path" => $this->path($destinationObjectName),
      "params" => [],
      "headers" => $headers,
      "body" => null,
      "successStatuses" => [200],
      "note" => "PUT 到目标键，源由 x-cos-copy-source 指定；响应体含 <CopyObjectResult>",
    ]];
  }

  /**
   * 移动对象 = **复制 + 删除源**（COS 没有原生的 move）
   *
   * ⚠️ 这不是原子操作：两步之间失败会留下"已复制但未删源"的中间态，
   * 调用方需按返回的 `phase` 逐步执行并自行处理失败（如回滚：删掉目标）。
   *
   * @param string $sourceObjectName 源对象键
   * @param string $destinationObjectName 目标对象键
   * @param array $headers 复制那一步的额外请求头
   * @return array 两步：copy → delete
   */
  public function move($sourceObjectName, $destinationObjectName, array $headers = [])
  {
    $steps = $this->copy($sourceObjectName, $destinationObjectName, $headers);
    $steps[0]["phase"] = "move-copy";

    $delete = $this->delete($sourceObjectName);
    $delete[0]["phase"] = "move-delete";

    return array_merge($steps, $delete);
  }

  /**
   * 删除对象（DELETE Object）
   *
   * @param string $objectName 对象键
   * @return array 步骤数组
   */
  public function delete($objectName)
  {
    return [[
      "phase" => "delete",
      "objectName" => $objectName,
      "method" => "DELETE",
      "path" => $this->path($objectName),
      "params" => [],
      "headers" => [],
      "body" => null,
      "successStatuses" => [204],
      "note" => "成功 204 无内容；版本控制场景另需 versionId（URL 参数）",
    ]];
  }

  /**
   * 判断对象是否存在（HEAD Object）
   *
   * 判定口径：**只有 200 才算存在**；403（没权限）与 404（不存在）都算"不存在"。
   *
   * @param string $objectName 对象键
   * @return array 步骤数组
   */
  public function exists($objectName)
  {
    $step = $this->metadata($objectName);
    $step[0]["phase"] = "exists";
    $step[0]["note"] = "只有 200 才算存在；403/404 一律视为不存在（分不清「无」与「无权限」）";

    return $step;
  }

  /**
   * 查询对象元数据（HEAD Object）—— 元信息在**响应头**里
   *
   * @param string $objectName 对象键
   * @return array 步骤数组
   */
  public function metadata($objectName)
  {
    return [[
      "phase" => "metadata",
      "objectName" => $objectName,
      "method" => "HEAD",
      "path" => $this->path($objectName),
      "params" => [],
      "headers" => [],
      "body" => null,
      "successStatuses" => [200],
      "note" => "响应头含 Content-Length / ETag / Last-Modified / x-cos-meta-* 等（可用 QCloudCosObject::fill() 回填）",
    ]];
  }

  /**
   * 列举对象（GET Bucket，**桶级**，不是某个对象）
   *
   * @param string|null $prefix 前缀过滤（如 `images/`）
   * @param array $params 其它 URL 参数：`delimiter` / `marker` / `max-keys` / `encoding-type`
   * @return array 步骤数组
   */
  public function listObjects($prefix = null, array $params = [])
  {
    if ($prefix !== null) $params = array_merge(["prefix" => $prefix], $params);

    return [[
      "phase" => "list",
      "objectName" => null,
      "method" => "GET",
      "path" => "/",
      "params" => $params,
      "headers" => [],
      "body" => null,
      "successStatuses" => [200],
      "note" => "桶级请求（路径为 /）；响应体是 <ListBucketResult>；这些参数都参与签名",
    ]];
  }

  /**
   * 恢复归档对象（POST Object restore）
   *
   * @param string $objectName 对象键
   * @param integer $days 解冻后副本保留天数
   * @param string $tier 解冻优先级：`Expedited` / `Standard` / `Bulk`
   * @return array 步骤数组
   */
  public function restore($objectName, $days = 30, $tier = "Standard")
  {
    $restore = new QCloudCosPostObjectRestore();
    $restore->days($days)->tier($tier);

    return [[
      "phase" => "restore",
      "objectName" => $objectName,
      "method" => "POST",
      "path" => $this->path($objectName),
      "params" => ["restore" => ""],
      "headers" => ["Content-Type" => "application/xml"],
      "body" => $restore->toXml(),
      "successStatuses" => [202],
      "note" => "?restore 是子资源（须同时进签名与 URL）；解冻是异步的，完成后才能 GET",
    ]];
  }

  /* ── URL（真算，不发请求） ────────────────────────────────────── */

  /**
   * 对象的可访问地址（**真算**；公共读对象可直接使用）
   *
   * @param string $objectName 对象键
   * @return string 形如 `https://bkt-1250000000.cos.ap-guangzhou.myqcloud.com/images/a.png`
   */
  public function objectUrl($objectName)
  {
    return $this->host . $this->path($objectName);
  }

  /**
   * 生成预签名 URL（**真算**，不需要发请求）
   *
   * - **传了签名器**（构造时或 {@see signer()} 设置）⇒ 返回完整签名 URL 字符串；
   * - **没传签名器** ⇒ 返回"待签参数"数组（`path`/`method`/`expires`/`urlParams`/`headers`/`baseUrl`），
   *   交给调用方自己的签名器算出 `q-*` 后拼到 `baseUrl` 上。
   *
   * 注意：签名里 `$httpMethod` 与实际使用方式**必须一致**，否则 COS 判签名不符。
   *
   * @param string $objectName 对象键
   * @param integer $expires 有效期（秒）
   * @param string $httpMethod 入签的 HTTP 方法
   * @param array $urlParams 参与签名的 URL 参数（子资源/业务参数）
   * @param array $headers 参与签名的请求头
   * @return string|array 完整 URL（有签名器）或待签参数数组（无签名器）
   */
  public function presignedUrl($objectName, $expires = 1800, $httpMethod = "get", array $urlParams = [], array $headers = [])
  {
    $path = $this->path($objectName);
    $baseUrl = $this->host . $path;

    if (!$this->signer) {
      return [
        "baseUrl" => $baseUrl,
        "path" => $path,
        "method" => strtolower($httpMethod),
        "expires" => $expires,
        "urlParams" => $urlParams,
        "headers" => $headers,
        "note" => "未设置签名器 ⇒ 请用签名器算出 q-* 后拼到 baseUrl 上（q-* 与 urlParams 一起作为 query）",
      ];
    }

    $sign = $this->signer->createAuthorization($path, $urlParams, $headers, $expires, $httpMethod);
    $query = array_merge($sign, $urlParams);

    return $baseUrl . "?" . http_build_query($query);
  }

  /* ── 内部 ─────────────────────────────────────────────────────── */

  /**
   * 对象键编码（按段 `rawurlencode` + 前导 `/`，与存储层 `encodeObjectName()` 同一套规则）
   *
   * @param string $objectName 原始对象键
   * @return string
   */
  protected static function encodePath($objectName)
  {
    $segments = explode("/", trim((string) $objectName, "/"));

    return "/" . implode("/", array_map("rawurlencode", $segments));
  }
}
