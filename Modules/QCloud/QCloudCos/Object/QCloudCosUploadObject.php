<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

use kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload\QCloudCosMultipartUpload;

/**
 * 「上传对象」的**调度类**：按文件大小自动选择**简单上传**还是**分块上传**
 *
 * 本目录下 `QCloudCosPutObject` 只描述 PUT Object（简单上传）的请求头，`QCloudCosMultipartUpload`
 * 只负责分块上传的编排。本类把它们**合起来**：给一个本地文件，它按大小决定走哪条路，
 * 并产出对应的请求计划（{@see plan()}）。
 *
 * ```php
 * $upload = new QCloudCosUploadObject("images/a.png");
 *
 * // 两条路线共用的参数，先设好（headers 最终由对应容器产出）
 * $upload->put()->contentType("image/png")->acl("public-read");
 *
 * // 决定策略并产出计划
 * $upload->strategy("/tmp/a.png");                 // "simple" 或 "multipart"
 * $steps = $upload->plan("/tmp/a.png");
 * ```
 *
 * ## 判定规则
 *
 * | 条件 | 结果 |
 * |---|---|
 * | 文件大小 **>** {@see SIMPLE_MAX_SIZE}（5GB，简单上传硬上限） | **分块**（硬上限优先，{@see threshold()} 调再大也拦不住） |
 * | 文件大小 **>** {@see threshold()}（默认 = 5GB） | **分块** |
 * | 其余 | **简单上传**（一次 PUT Object） |
 *
 * `threshold` 默认取 5GB，即"**只在不得不用时才分块**"。实践中若想换来**断点续传/并发**，
 * 可把阈值调小（如 20MB）：`$upload->threshold(20 * 1024 * 1024)`。
 *
 * ## ⚠️ 与同目录其它类一样：**不发请求、不做签名**
 *
 * 它只产出「该发什么」（见 {@see plan()}）。分块路线还有**运行时依赖**（`uploadId` 来自初始化响应、
 * `ETag` 来自每个块的响应头），需要用 {@see multipart()} 拿到的实例回填，再取 {@see QCloudCosMultipartUpload::completeXml()}。
 *
 * 文档核对状态：**待核对**（5GB 为 COS 简单上传的普遍上限；分块规则见
 * {@see QCloudCosMultipartUpload}；首次接入对真实桶复核）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosUploadObject
{
  /** 策略：简单上传（PUT Object） */
  const STRATEGY_SIMPLE = "simple";
  /** 策略：分块上传 */
  const STRATEGY_MULTIPART = "multipart";

  /** 简单上传（PUT Object）的**硬上限**：5GB，超过就必须分块 */
  const SIMPLE_MAX_SIZE = 5368709120;
  /** 默认分块阈值：与硬上限一致（即"只在不得不用时才分块"） */
  const DEFAULT_THRESHOLD = 5368709120;

  /** @var string|null 对象键（原始，未编码） */
  protected $objectKey;
  /** @var integer 分块阈值（字节） */
  protected $threshold;
  /** @var QCloudCosPutObject|null 简单上传的容器 */
  protected $putObject;
  /** @var QCloudCosMultipartUpload|null 分块上传的编排器 */
  protected $multipartUpload;

  /**
   * @param string|null $objectKey 对象键
   * @param integer|null $threshold 分块阈值（字节）；不传用 {@see DEFAULT_THRESHOLD}
   */
  public function __construct($objectKey = null, $threshold = null)
  {
    $this->objectKey = $objectKey;
    $this->threshold = $threshold === null ? self::DEFAULT_THRESHOLD : (int) $threshold;
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
   * 分块阈值（字节；不传=读取，传值=设置并返回 `$this`）
   *
   * 文件**大于**该值即走分块。注意 {@see SIMPLE_MAX_SIZE} 是硬上限，
   * 即使把阈值调到 5GB 以上，超过 5GB 的文件仍然走分块。
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function threshold($value = null)
  {
    if (!func_get_args()) return $this->threshold;

    $this->threshold = (int) $value;

    return $this;
  }

  /**
   * 简单上传的参数容器（PUT Object）
   *
   * @return QCloudCosPutObject
   */
  public function put()
  {
    if (!$this->putObject) $this->putObject = new QCloudCosPutObject();

    return $this->putObject;
  }

  /**
   * 分块上传的编排器（键会与本类保持同步）
   *
   * 分块路线需要用这个实例回填 `uploadId` 与各块 `ETag`。
   *
   * @return QCloudCosMultipartUpload
   */
  public function multipart()
  {
    if (!$this->multipartUpload) {
      $this->multipartUpload = new QCloudCosMultipartUpload($this->objectKey);
    } else {
      $this->multipartUpload->key($this->objectKey);   //* key() 改过之后要同步给它
    }

    return $this->multipartUpload;
  }

  /**
   * 按**已知大小**判定策略（纯逻辑，不碰文件 —— 便于测试与流式场景）
   *
   * @param integer $size 文件字节数
   * @return string {@see STRATEGY_SIMPLE} 或 {@see STRATEGY_MULTIPART}
   */
  public function strategyForSize($size)
  {
    $size = (int) $size;
    if ($size > self::SIMPLE_MAX_SIZE) return self::STRATEGY_MULTIPART;   //* 硬上限优先

    return $size > $this->threshold ? self::STRATEGY_MULTIPART : self::STRATEGY_SIMPLE;
  }

  /**
   * 判定某个本地文件该走哪条路
   *
   * @param string $localFilePath 本地文件路径
   * @return string {@see STRATEGY_SIMPLE} 或 {@see STRATEGY_MULTIPART}
   * @throws \InvalidArgumentException 文件不存在或不可读
   */
  public function strategy($localFilePath)
  {
    return $this->strategyForSize($this->fileSize($localFilePath));
  }

  /**
   * 判定并说明依据（比 {@see strategy()} 多给出大小、阈值与原因）
   *
   * @param string $localFilePath 本地文件路径
   * @return array ["strategy","size","threshold","reason"]
   * @throws \InvalidArgumentException 文件不存在或不可读
   */
  public function decide($localFilePath)
  {
    $size = $this->fileSize($localFilePath);
    $strategy = $this->strategyForSize($size);

    if ($size > self::SIMPLE_MAX_SIZE) {
      $reason = "文件 " . self::humanSize($size) . " 超过简单上传硬上限 " . self::humanSize(self::SIMPLE_MAX_SIZE) . "，必须分块";
    } elseif ($strategy === self::STRATEGY_MULTIPART) {
      $reason = "文件 " . self::humanSize($size) . " 大于分块阈值 " . self::humanSize($this->threshold) . "，走分块";
    } else {
      $reason = "文件 " . self::humanSize($size) . " 未超过阈值 " . self::humanSize($this->threshold) . "，走简单上传（PUT Object）";
    }

    return [
      "strategy" => $strategy,
      "size" => $size,
      "threshold" => $this->threshold,
      "reason" => $reason,
    ];
  }

  /**
   * 产出该文件的上传计划（**不发请求**）
   *
   * - 简单上传：返回 **1 步**，`phase = "put"`（PUT Object，请求体是整个文件内容）；
   * - 分块上传：直接返回 {@see QCloudCosMultipartUpload::plan()} 的步骤
   *   （`initiate` → 各 `part` → `complete`）。
   *
   * @param string $localFilePath 本地文件路径
   * @param integer $partSize 分块大小（仅分块路线生效）
   * @return array 有序步骤数组
   * @throws \InvalidArgumentException 文件不存在或不可读；分块路线同 {@see QCloudCosMultipartUpload::split()}
   */
  public function plan($localFilePath, $partSize = QCloudCosMultipartUpload::DEFAULT_PART_SIZE)
  {
    $size = $this->fileSize($localFilePath);
    $strategy = $this->strategyForSize($size);

    if ($strategy === self::STRATEGY_SIMPLE) {
      return [[
        "phase" => "put",
        "strategy" => self::STRATEGY_SIMPLE,
        "method" => "PUT",
        "path" => self::encodePath((string) $this->objectKey),
        "params" => [],
        "headers" => $this->put()->headers(),
        "body" => null,
        "file" => $localFilePath,
        "size" => $size,
        "note" => "简单上传：请求体即整个文件内容；PUT Object **必带 Content-Type**，`x-cos-*` 头会参与签名",
      ]];
    }

    return $this->multipart()->plan($localFilePath, $partSize);
  }

  /* ── 内部 ─────────────────────────────────────────────────────── */

  /**
   * 取文件大小（顺带校验可读）
   *
   * @param string $localFilePath 本地文件路径
   * @return integer 字节数
   * @throws \InvalidArgumentException 文件不存在或不可读
   */
  protected function fileSize($localFilePath)
  {
    if (!is_file($localFilePath) || !is_readable($localFilePath)) {
      throw new \InvalidArgumentException("上传对象：本地文件不存在或不可读 —— {$localFilePath}");
    }

    return filesize($localFilePath);
  }

  /**
   * 对象键编码（按段 `rawurlencode` + 前导 `/`，与存储层 `encodeObjectName()` 同一套规则）
   *
   * @param string $objectKey 原始对象键
   * @return string
   */
  protected static function encodePath($objectKey)
  {
    $segments = explode("/", trim((string) $objectKey, "/"));

    return "/" . implode("/", array_map("rawurlencode", $segments));
  }

  /**
   * 字节数转人类可读（仅用于 {@see decide()} 的说明文字）
   *
   * @param integer $bytes 字节数
   * @return string
   */
  protected static function humanSize($bytes)
  {
    $units = ["B", "KB", "MB", "GB", "TB"];
    $bytes = (float) $bytes;
    $index = 0;
    while ($bytes >= 1024 && $index < count($units) - 1) {
      $bytes /= 1024;
      $index++;
    }

    return ($index === 0 ? (int) $bytes : rtrim(rtrim(sprintf("%.2f", $bytes), "0"), ".")) . $units[$index];
  }
}
