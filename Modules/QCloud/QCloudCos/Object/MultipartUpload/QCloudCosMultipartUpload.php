<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload;

/**
 * 分块上传的**聚合/编排类**（把一个文件按分块上传的全流程组织起来）
 *
 * 本目录另外 7 个类各自只描述「某一步的参数」（初始化 / 上传块 / 复制块 / 完成 / 终止 / 列举块 /
 * 列举上传中任务）。本类把它们**聚合**起来，并补上「一个本地文件怎么切成块」这一步，从而能一次性
 * 产出**完整的分步请求计划**（{@see plan()}）。
 *
 * ```php
 * $upload = new QCloudCosMultipartUpload("images/big.zip");
 *
 * // 1) 设置各阶段参数（拿到的就是那 7 个容器类，按各自方法设即可）
 * $upload->initiate()->contentType("application/zip")->storageClass("STANDARD_IA");
 * $upload->part()->contentMD5("");
 *
 * // 2) 切块 + 拿到完整计划
 * $steps = $upload->plan("/tmp/big.zip", 5 * 1024 * 1024);
 *
 * // 3) 由调用方按计划逐步发请求（见 plan() 的返回结构）
 * ```
 *
 * ⚠️ **本类不发请求、不做签名**：它只产出「每一步应该发什么」。原因是分块上传各步之间有
 * **运行时的依赖**——`uploadId` 来自「初始化」的响应 XML，`ETag` 来自每个「上传块」的响应头，
 * 都必须回填给后续步骤（用 {@see uploadId()} / {@see etag()}）。因此流程无法在这一层一次性跑完，
 * 只能由持有客户端（能发请求、能读响应）的一方按计划逐步推进。
 * 等 `DiscuzXQCloudCOS` 补上分块上传的底层方法后，接线就是把每一步的
 * `method/path/params/headers/body` 交给它。
 *
 * 分块规则（官方）：
 * - 每块 **1MB – 5GB**，**最后一块可以小于 1MB**；块编号 **1 – 10000**；
 * - 「完成」时必须按 `PartNumber` **升序**提交各块的 `ETag`（{@see completeXml()} 会自动排序）；
 * - 小于 1MB 的文件其实用 `PUT Object` 一次传完更划算；**0 字节**文件本类仍会给出 1 个 0 字节的块。
 *
 * 依赖方向：本类**不引用**存储层（`DiscuzXQCloudCOS`）以免 QCloud → DiscuzX 逆向依赖；
 * 对象键的编码方式与该类的 `encodeObjectName()` 一致（按段 `rawurlencode` + 前导 `/`）。
 *
 * 文档核对状态：**待核对**（分块规则与请求形态按官方分块上传通用写法整理；首次接入对真实桶复核）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload
 */
class QCloudCosMultipartUpload
{
  /** 单块最小字节数（最后一块除外） */
  const MIN_PART_SIZE = 1048576;
  /** 默认分块大小：5MB */
  const DEFAULT_PART_SIZE = 5242880;
  /** 一次分块上传允许的最大块数 */
  const MAX_PARTS = 10000;

  /** @var string|null 对象键（原始，未编码） */
  protected $objectKey;
  /** @var string|null 由「初始化」响应回填的 UploadId */
  protected $uploadId;
  /** @var array 已聚合的容器实例（类名 => 实例） */
  protected $containers = [];
  /** @var array 已记录的块 ETag（PartNumber => ETag） */
  protected $etags = [];
  /** @var array 最近一次 {@see split()} 的切块结果 */
  protected $parts = [];

  /**
   * @param string|null $objectKey 对象键（原始，带不带前导 `/` 均可）
   * @param string|null $uploadId 已存在的 UploadId（继续一次未完成的上传时传入）
   */
  public function __construct($objectKey = null, $uploadId = null)
  {
    $this->objectKey = $objectKey;
    $this->uploadId = $uploadId;
  }

  /**
   * 对象键（不传=读取，传值=设置并返回 `$this`）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function key($value = null)
  {
    if (!func_get_args()) return $this->objectKey;

    $this->objectKey = (string) $value;

    return $this;
  }

  /**
   * UploadId（不传=读取，传值=设置并返回 `$this`）
   *
   * 由「初始化」响应 XML 里的 `<UploadId>` 回填；后续每个块与「完成」都要带它。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function uploadId($value = null)
  {
    if (!func_get_args()) return $this->uploadId;

    $this->uploadId = (string) $value;

    return $this;
  }

  /**
   * 对象键编码后的路径（按段 `rawurlencode`，带前导 `/`）
   *
   * @return string
   */
  public function path()
  {
    return self::encodePath((string) $this->objectKey);
  }

  /* ── 聚合：分块上传的每一步（返回的都是本目录的容器类） ────────── */

  /**
   * 「初始化分块上传」的参数容器（POST ?uploads）
   *
   * @return QCloudCosInitiateMultipartUpload
   */
  public function initiate()
  {
    return $this->container("QCloudCosInitiateMultipartUpload");
  }

  /**
   * 「上传分块」的参数容器（PUT ?partNumber&uploadId）
   *
   * @return QCloudCosUploadPart
   */
  public function part()
  {
    return $this->container("QCloudCosUploadPart");
  }

  /**
   * 「复制分块」的参数容器（PUT ?partNumber&uploadId + x-cos-copy-source）
   *
   * @return QCloudCosUploadPartCopy
   */
  public function partCopy()
  {
    return $this->container("QCloudCosUploadPartCopy");
  }

  /**
   * 「完成分块上传」的参数容器（POST ?uploadId + XML 体）
   *
   * @return QCloudCosCompleteMultipartUpload
   */
  public function complete()
  {
    return $this->container("QCloudCosCompleteMultipartUpload");
  }

  /**
   * 「终止分块上传」的参数容器（DELETE ?uploadId）
   *
   * @return QCloudCosAbortMultipartUpload
   */
  public function abort()
  {
    return $this->container("QCloudCosAbortMultipartUpload");
  }

  /**
   * 「列举已上传分块」的参数容器（GET ?uploadId）
   *
   * @return QCloudCosListParts
   */
  public function listParts()
  {
    return $this->container("QCloudCosListParts");
  }

  /**
   * 「列举进行中的分块上传」的参数容器（GET ?uploads）
   *
   * @return QCloudCosListMultipartUploads
   */
  public function listMultipartUploads()
  {
    return $this->container("QCloudCosListMultipartUploads");
  }

  /* ── 文件切块 ─────────────────────────────────────────────────── */

  /**
   * 把本地文件切成若干块（只算偏移与长度，**不读内容**）
   *
   * 返回形如 `[["partNumber" => 1, "offset" => 0, "length" => 5242880], …]`。
   * 块编号从 1 开始、连续递增；最后一块长度可能小于 `$partSize`。
   *
   * @param string $localFilePath 本地文件路径
   * @param integer $partSize 每块字节数（≥ {@see MIN_PART_SIZE}）
   * @return array 切块结果（同时缓存在 {@see parts()} 里）
   * @throws \InvalidArgumentException 文件不可读 / 块大小过小 / 块数超过 {@see MAX_PARTS}
   */
  public function split($localFilePath, $partSize = self::DEFAULT_PART_SIZE)
  {
    if (!is_file($localFilePath) || !is_readable($localFilePath)) {
      throw new \InvalidArgumentException("分块上传：本地文件不存在或不可读 —— {$localFilePath}");
    }

    $partSize = (int) $partSize;
    if ($partSize < self::MIN_PART_SIZE) {
      throw new \InvalidArgumentException("分块上传：单块不得小于 " . self::MIN_PART_SIZE . " 字节（当前 {$partSize}）");
    }

    $size = filesize($localFilePath);
    $count = $size > 0 ? (int) ceil($size / $partSize) : 1;   //* 空文件也给 1 个 0 字节的块
    if ($count > self::MAX_PARTS) {
      throw new \InvalidArgumentException("分块上传：块数 {$count} 超过上限 " . self::MAX_PARTS . "（请调大 \$partSize）");
    }

    $parts = [];
    for ($index = 1; $index <= $count; $index++) {
      $offset = ($index - 1) * $partSize;
      $parts[] = [
        "partNumber" => $index,
        "offset" => $offset,
        "length" => max(0, min($partSize, $size - $offset)),
      ];
    }

    $this->parts = $parts;

    return $parts;
  }

  /**
   * 最近一次 {@see split()} 的切块结果（未切过则返回空数组）
   *
   * @return array
   */
  public function parts()
  {
    return $this->parts;
  }

  /* ── ETag 回填 ────────────────────────────────────────────────── */

  /**
   * 记录/读取某个块的 ETag（来自该块上传响应头里的 `ETag`）
   *
   * @param integer $partNumber 块编号
   * @param string|null $etag 传值=记录；只传块编号=读取
   * @return mixed 读取时返回 ETag（未记录返回 null）；记录时返回 $this
   */
  public function etag($partNumber, $etag = null)
  {
    if (func_num_args() < 2) {
      return isset($this->etags[(int) $partNumber]) ? $this->etags[(int) $partNumber] : null;
    }

    $this->etags[(int) $partNumber] = (string) $etag;

    return $this;
  }

  /**
   * 全部已记录的 ETag（PartNumber => ETag）
   *
   * @return array
   */
  public function etags()
  {
    return $this->etags;
  }

  /**
   * 产出「完成分块上传」的请求体 XML（按 PartNumber **升序**）
   *
   * 用 {@see QCloudCosCompleteMultipartUpload::toXml()} 拼；未记录任何 ETag 时返回空串。
   *
   * @param string|null $uploadId 覆盖 UploadId（不传则用 {@see uploadId()} 里的值，仅用于设置容器）
   * @return string XML 请求体
   */
  public function completeXml($uploadId = null)
  {
    if (!$this->etags) return "";

    $etags = $this->etags;
    ksort($etags);

    $complete = $this->complete();
    if ($uploadId !== null) $this->uploadId($uploadId);
    $complete->uploadId($this->uploadId());

    $pairs = [];
    foreach ($etags as $partNumber => $etag) {
      $pairs[] = [$partNumber, $etag];
    }

    return $complete->parts($pairs)->toXml();
  }

  /* ── 请求计划 ─────────────────────────────────────────────────── */

  /**
   * 产出「把这个文件分块上传」的完整分步计划（**不发请求**）
   *
   * 每一步的结构：
   * ```
   * [
   *   "phase"      => "initiate" | "part" | "complete",
   *   "method"     => "POST" | "PUT",
   *   "path"       => "/images/big.zip",        // 已编码
   *   "params"     => ["uploads" => ""],        // 参与签名的 URL 参数
   *   "headers"    => ["Content-Type" => …],    // 该阶段容器里已设置的头
   *   "body"       => null | "<xml…>",          // 请求体；块的二进制数据另见 offset/length
   *   "partNumber" => 1,                        // 仅 part 阶段
   *   "offset"     => 0, "length" => 5242880,   // 仅 part 阶段：本地文件的读取范围
   *   "needs"      => ["uploadId"],             // 该步依赖的前置值（尚未具备时见 pending）
   *   "pending"    => true,                     // 为 true 表示缺少 uploadId，需先跑上一步并回填
   *   "note"       => "…",                      // 该步要从响应里取什么回填
   * ]
   * ```
   *
   * @param string $localFilePath 本地文件路径
   * @param integer $partSize 每块字节数
   * @return array 有序步骤数组（初始化 → 各块 → 完成）
   * @throws \InvalidArgumentException 同 {@see split()}
   */
  public function plan($localFilePath, $partSize = self::DEFAULT_PART_SIZE)
  {
    $parts = $this->split($localFilePath, $partSize);
    $path = $this->path();
    $uploadId = $this->uploadId();

    $steps = [];

    //* ① 初始化：拿到 UploadId 后才能发后续步骤
    $steps[] = [
      "phase" => "initiate",
      "method" => "POST",
      "path" => $path,
      "params" => ["uploads" => ""],
      "headers" => $this->initiate()->all(),
      "body" => null,
      "needs" => [],
      "pending" => false,
      "note" => "从响应 XML 的 <UploadId> 取回，调用 \$upload->uploadId(\$id) 回填",
    ];

    //* ② 逐块上传：每块都要带 uploadId，并从响应头取回 ETag
    foreach ($parts as $part) {
      $steps[] = [
        "phase" => "part",
        "method" => "PUT",
        "path" => $path,
        "params" => [
          "partNumber" => $part["partNumber"],
          "uploadId" => $uploadId,
        ],
        "headers" => $this->part()->all(),
        "body" => null,
        "partNumber" => $part["partNumber"],
        "offset" => $part["offset"],
        "length" => $part["length"],
        "needs" => ["uploadId"],
        "pending" => $uploadId === null,
        "note" => "请求体为该块字节（offset/length）；从响应头 ETag 取回，调用 \$upload->etag({$part["partNumber"]}, \$etag) 回填",
      ];
    }

    //* ③ 完成：提交各块的 ETag（升序）
    $steps[] = [
      "phase" => "complete",
      "method" => "POST",
      "path" => $path,
      "params" => ["uploadId" => $uploadId],
      "headers" => ["Content-Type" => "application/xml"],
      "body" => $this->completeXml(),
      "needs" => ["uploadId", "etag"],
      "pending" => true,   //* ETag 只能来自真实的上传响应，计划阶段必然未齐
      "note" => "body 由 completeXml() 产出（需先回填各块 ETag）",
    ];

    return $steps;
  }

  /* ── 内部 ─────────────────────────────────────────────────────── */

  /**
   * 取（并缓存）某个容器实例 —— 聚合的关键：同一阶段反复取到的是同一个实例
   *
   * @param string $shortClass 本目录下的类名
   * @return object
   */
  protected function container($shortClass)
  {
    if (!isset($this->containers[$shortClass])) {
      $class = __NAMESPACE__ . "\\" . $shortClass;
      $this->containers[$shortClass] = new $class();
    }

    return $this->containers[$shortClass];
  }

  /**
   * 对象键编码（按段 `rawurlencode` + 前导 `/`，与存储层的 `encodeObjectName()` 同一套规则）
   *
   * @param string $objectKey 原始对象键
   * @return string
   */
  protected static function encodePath($objectKey)
  {
    $segments = explode("/", trim((string) $objectKey, "/"));

    return "/" . implode("/", array_map("rawurlencode", $segments));
  }
}
