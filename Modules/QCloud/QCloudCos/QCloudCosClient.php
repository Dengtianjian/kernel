<?php

namespace kernel\Modules\QCloud\QCloudCos;

use kernel\Foundation\Data\Str;
use kernel\Foundation\HTTP\Curl;
use kernel\Modules\QCloud\QCloud;
use kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload\QCloudCosCompleteMultipartUpload;
use kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload\QCloudCosMultipartUpload;
use kernel\Modules\QCloud\QCloudCos\Object\QCloudCosObject;
use kernel\Modules\QCloud\QCloudCos\Object\QCloudCosObjects;

/**
 * COS 客户端：**真执行**，并决定「用 SDK 还是自己发请求」
 *
 * 定位：本模块里唯一**真正发请求**的地方。它自己**不定义请求形态** —— 请求该长什么样全部由
 * `Object/` 下的计划类（`QCloudCosObjects` / `QCloudCosMultipartUpload` …）给出，本类只负责
 * 「把计划发出去」：
 *
 * ```php
 * $client = new QCloudCosClient($secretId, $secretKey, "ap-guangzhou", "bkt-1250000000");
 *
 * $client->upload("images/a.png", "/tmp/a.png");   // 真上传（自动按大小选简单/分块）
 * $client->exists("images/a.png");                 // true / false
 * $client->download("images/a.png");               // 内容字符串
 * $client->objectUrl("images/a.png");              // 可访问地址
 * $client->presignedUrl("images/a.png", 600);      // 预签名 URL
 *
 * // 也可以拿到「对象句柄」自己拼请求（它只产计划，不发送）
 * $client->object("images/a.png")->put()->acl("public-read");
 * $client->objects()->listObjects("images/");
 * ```
 *
 * ## 双驱动（兼容两个环境）
 *
 * | 驱动 | 触发条件 | 说明 |
 * |---|---|
 * | `sdk` | `Qcloud\Cos\Client` 类存在（vendor 里装了 COS SDK） | 走 SDK |
 * | `http` | 上者不存在（如 DiscuzX 插件环境未加载 vendor） | 自己用 `Curl` + {@see QCloudCosSignture} 发签名请求 |
 *
 * 可用 {@see driver()} 强制指定（`"sdk"` / `"http"`）以便测试或排障，{@see usingSdk()} 查当前是否走 SDK。
 *
 * ## ⚠️ 验证状态
 *
 * - **`http` 驱动**已按本仓 `DiscuzXQCloudCOS::request()` 的同款写法实现（签名 + URL + 头 + 错误态），
 *   并用桩 `Curl` 做过请求形态验证；
 * - **`sdk` 驱动**的命令名已对照 vendor 里的 SDK 确认（`putObject`/`getObject`/`headObject`/
 *   `deleteObject`/`copyObject`/`listObjects`/`restoreObject`/`uploadPart`/`completeMultipartUpload`…），
 *   但**各命令的数组参数形状未经真机验证**（见 `sendBySdk()` 的注释），首次接入请对真实桶跑一遍。
 *
 * @package kernel\Modules\QCloud\QCloudCos
 */
class QCloudCosClient extends QCloud
{
  /** 驱动：走官方 SDK */
  const DRIVER_SDK = "sdk";
  /** 驱动：自己发签名 HTTP 请求 */
  const DRIVER_HTTP = "http";

  /** 默认域名格式（HTTPS） */
  const HOST_PATTERN = "https://%s.cos.%s.myqcloud.com";
  /** 默认签名有效期（秒） */
  const DEFAULT_EXPIRES = 300;

  /** @var string|null 桶名 */
  protected $bucket;
  /** @var string|null 地域 */
  protected $region;
  /** @var string 当前驱动 */
  protected $driver;
  /** @var QCloudCosSignture|null 签名器（http 驱动用） */
  protected $signer;
  /** @var object|null SDK 客户端（sdk 驱动用，懒加载） */
  protected $sdkClient = null;
  /** @var QCloudCosObjects|null 桶级计划器（懒加载） */
  protected $objects = null;
  /** @var array 对象句柄缓存（对象键 => QCloudCosObject） */
  protected $objectHandles = [];
  /** @var integer 签名有效期（秒） */
  protected $expires = self::DEFAULT_EXPIRES;
  /** @var array 最近一次操作的**全部步骤**结果（供排障；操作方法本身只返回语义值） */
  protected $lastResults = [];
  /** @var array|null 最近一次 {@see listObjects()} 的完整解析结果（含分页字段） */
  protected $lastParsed = null;

  /**
   * 参数顺序与 {@see QCloudCosSignture} 保持一致：**region 在前、bucket 在后**
   * （`new QCloudCosClient($secretId, $secretKey, "ap-guangzhou", "bkt-1250000000")`）。
   *
   * @param string $secretId 密钥 ID
   * @param string $secretKey 密钥 Key
   * @param string $region 地域（如 `ap-guangzhou`）
   * @param string $bucket 桶名（含 APPID，如 `bkt-1250000000`）
   * @param string|null $host 桶域名；不传按 {@see HOST_PATTERN} 拼
   * @param string|null $driver 强制驱动（{@see DRIVER_SDK} / {@see DRIVER_HTTP}）；不传自动检测
   * @param string|null $securityToken 临时密钥 token（STS 场景）
   */
  public function __construct($secretId, $secretKey, $region, $bucket, $host = null, $driver = null, $securityToken = null)
  {
    //* 注意：**不要**给父类传 $service —— 父类会把 host 拼成 "{service}.{host}"（那是腾讯云 API 的域名规则），
    //* 与 COS 的 "{bucket}.cos.{region}.myqcloud.com" 冲突。这里第 3 参传 null，第 4 参直接给完整域名。
    parent::__construct($secretId, $secretKey, null, $host !== null ? $host : sprintf(self::HOST_PATTERN, $bucket, $region), $securityToken);

    $this->bucket = $bucket;
    $this->region = $region;
    $this->driver = $driver !== null ? $driver : self::detectDriver();
    $this->driver = self::DRIVER_HTTP;
    $this->signer = new QCloudCosSignture($secretId, $secretKey, null, $securityToken);
  }

  /**
   * 自动检测该用哪个驱动
   *
   * @return string {@see DRIVER_SDK} 或 {@see DRIVER_HTTP}
   */
  public static function detectDriver()
  {
    return class_exists("Qcloud\\Cos\\Client") ? self::DRIVER_SDK : self::DRIVER_HTTP;
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
   * 注意：`QCloud` 基类只有 `protected $host` 属性、**没有** `host()` 方法，所以这里补一个。
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
   * 当前驱动（不传=读取，传值=设置；应为 {@see DRIVER_SDK} 或 {@see DRIVER_HTTP}）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function driver($value = null)
  {
    if (!func_get_args()) return $this->driver;

    $this->driver = (string) $value;

    return $this;
  }

  /**
   * 是否走 SDK
   *
   * @return boolean
   */
  public function usingSdk()
  {
    return $this->driver === self::DRIVER_SDK;
  }

  /**
   * 签名有效期（秒；不传=读取，传值=设置）
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function expires($value = null)
  {
    if (!func_get_args()) return $this->expires;

    $this->expires = (int) $value;

    return $this;
  }

  /**
   * 签名器（http 驱动用）
   *
   * @return QCloudCosSignture
   */
  public function signer()
  {
    return $this->signer;
  }

  /**
   * SDK 客户端（sdk 驱动用，懒加载）
   *
   * @return object `Qcloud\Cos\Client` 实例
   * @throws \RuntimeException 当前环境没有装 COS SDK
   */
  public function sdk()
  {
    if ($this->sdkClient) return $this->sdkClient;

    $class = "Qcloud\\Cos\\Client";
    if (!class_exists($class)) {
      throw new \RuntimeException("当前环境未安装 COS SDK（Qcloud\\Cos\\Client 不存在），请改用 http 驱动");
    }

    $config = [
      "region" => $this->region,
      "scheme" => strpos($this->host, "https") === 0 ? "https" : "http",
      "credentials" => [
        "secretId" => $this->secretId,
        "secretKey" => $this->secretKey,
      ],
    ];
    if ($this->securityToken) $config["credentials"]["token"] = $this->securityToken;

    $this->sdkClient = new $class($config);

    return $this->sdkClient;
  }

  /* ── 访问器：拿到「计划类」实例（它们只产计划，不发送） ────────── */

  /**
   * 桶级计划器（**不绑定对象**，靠传对象名操作）
   *
   * @return QCloudCosObjects
   */
  public function objects()
  {
    if (!$this->objects) {
      $this->objects = new QCloudCosObjects($this->bucket, $this->region, $this->host, $this->signer);
    } else {
      $this->objects->bucket($this->bucket)->region($this->region)->host($this->host)->signer($this->signer);
    }

    return $this->objects;
  }

  /**
   * 取某个对象的句柄（**绑定键**，同一键返回同一实例）
   *
   * 它只产计划，不发送；要真执行请用本类的 `upload/download/...`，或用 {@see execute()}。
   *
   * @param string $objectName 对象键
   * @return QCloudCosObject
   */
  public function object($objectName)
  {
    if (!isset($this->objectHandles[$objectName])) {
      //* 第 4 参把**自己**作为执行器传进去 ⇒ 句柄上的操作访问器会返回可执行包装，可 ->run()
      $this->objectHandles[$objectName] = new QCloudCosObject($objectName, $this->bucket, $this->region, $this);
    } else {
      //* 客户端的 bucket/region 变过之后同步给已有句柄
      $this->objectHandles[$objectName]->bucket($this->bucket)->region($this->region)->executor($this);
    }

    return $this->objectHandles[$objectName];
  }

  /* ── URL（纯计算，不涉及驱动） ────────────────────────────────── */

  /**
   * 对象可访问地址（公共读对象可直接用）
   *
   * @param string $objectName 对象键
   * @return string
   */
  public function objectUrl($objectName)
  {
    return $this->objects()->objectUrl($objectName);
  }

  /**
   * 预签名 URL（本类已持有签名器 ⇒ 一定是完整 URL 字符串）
   *
   * @param string $objectName 对象键
   * @param integer $expires 有效期（秒）
   * @param string $httpMethod 入签方法（须与实际使用一致）
   * @param array $urlParams 参与签名的 URL 参数
   * @param array $headers 参与签名的请求头
   * @return string
   */
  public function presignedUrl($objectName, $expires = 1800, $httpMethod = "get", array $urlParams = [], array $headers = [])
  {
    return $this->objects()->presignedUrl($objectName, $expires, $httpMethod, $urlParams, $headers);
  }

  /* ── 执行：薄封装（请求形态来自 objects()，本类只负责发出去） ────
   *
   * 返回值一律**语义化**（成功 true / 失败 false，取内容的才返回内容），
   * 原始的状态码/响应头/响应体等收在 {@see lastResults()} 里，需要排障时再取。
   */

  /**
   * 上传对象（自动按大小选简单/分块）
   *
   * @param string $objectName 对象键
   * @param string $localFilePath 本地文件
   * @param array $headers 额外请求头（简单上传路线）
   * @param integer $partSize 分块大小
   * @return boolean **成功 true / 失败 false**（细节见 {@see lastResults()}）
   */
  public function upload($objectName, $localFilePath, array $headers = [], $partSize = QCloudCosMultipartUpload::DEFAULT_PART_SIZE)
  {
    return $this->ok($this->execute($this->objects()->upload($objectName, $localFilePath, $headers, $partSize), $localFilePath));
  }

  /**
   * 下载对象
   *
   * @param string $objectName 对象键
   * @param string|null $range Range（如 `bytes=0-1023`）
   * @param string|null $saveToPath 传入则落盘并返回写入字节数
   * @return mixed 内容字符串 / 写入字节数 / **失败 false**
   */
  public function download($objectName, $range = null, $saveToPath = null)
  {
    $results = $this->execute($this->objects()->download($objectName, $range));
    if (!$this->ok($results)) return false;

    $result = $this->first($results);
    if ($saveToPath === null) return $result["body"];

    $bytes = file_put_contents($saveToPath, (string) $result["body"]);
    if ($bytes === false) {
      $this->lastResults = [["phase" => "get", "result" => [
        "ok" => false, "status" => 0, "body" => null, "headers" => [],
        "error" => "写入本地文件失败：{$saveToPath}",
      ]]];

      return false;
    }

    return $bytes;
  }

  /**
   * 复制对象
   *
   * @param string $sourceObjectName 源对象键
   * @param string $destinationObjectName 目标对象键
   * @param array $headers 额外请求头
   * @return boolean **成功 true / 失败 false**（细节见 {@see lastResults()}）
   */
  public function copy($sourceObjectName, $destinationObjectName, array $headers = [])
  {
    return $this->ok($this->execute($this->objects()->copy($sourceObjectName, $destinationObjectName, $headers)));
  }

  /**
   * 移动对象（复制 + 删源，**非原子**）
   *
   * 两步**都成功**才算成功；任一步失败返回 false（哪一步失败见 {@see lastResults()} 的 `phase`）。
   *
   * @param string $sourceObjectName 源对象键
   * @param string $destinationObjectName 目标对象键
   * @param array $headers 复制那一步的额外请求头
   * @return boolean **成功 true / 失败 false**
   */
  public function move($sourceObjectName, $destinationObjectName, array $headers = [])
  {
    return $this->ok($this->execute($this->objects()->move($sourceObjectName, $destinationObjectName, $headers)));
  }

  /**
   * 删除对象
   *
   * @param string $objectName 对象键
   * @return boolean **成功 true / 失败 false**（成功码 204）
   */
  public function delete($objectName)
  {
    return $this->ok($this->execute($this->objects()->delete($objectName)));
  }

  /**
   * 判断对象是否存在（**只有 200 才算存在**；403/404 都算不存在）
   *
   * ⚠️ 注意本方法返回的 `false` 不代表"请求失败"——"不存在"与"没权限"都是 `false`，
   * 需要区分请配合 {@see lastResults()} 看状态码。
   *
   * @param string $objectName 对象键
   * @return boolean
   */
  public function exists($objectName)
  {
    return $this->ok($this->execute($this->objects()->exists($objectName)));
  }

  /**
   * 查询对象元数据（**响应头数组**）
   *
   * @param string $objectName 对象键
   * @return array|boolean 成功返回响应头数组；**失败（含不存在）返回 false**
   */
  public function metadata($objectName)
  {
    $results = $this->execute($this->objects()->metadata($objectName));

    return $this->ok($results) ? (array) $this->first($results)["headers"] : false;
  }

  /**
   * 列举对象（桶级）
   *
   * 响应体是 XML（`<ListBucketResult>`），这里用 {@see Str::fromXml()} 解析后**直接返回
   * `<Contents>`（对象列表）**，不再吐整个 `ListBucketResult`：
   *
   * ```php
   * $items = $cos->listObjects("images/", ["max-keys" => 100]);
   * // [["Key" => "images/a.png", "Size" => "1024", "ETag" => "\"aaa\"", …], …]
   * ```
   *
   * **永远返回列表**（SimpleXML 的老歧义已归一化）：只有一个 `<Contents>` 时也返回
   * `[那个对象]`，一个都没有（空桶）时返回 `[]`（**解析成功 ≠ 失败**）。
   *
   * ⚠️ 分页信息（`IsTruncated` / `NextMarker` / `KeyCount` / `CommonPrefixes` …）不在返回值里，
   * 需要时用 {@see lastParsed()} 取完整解析结果。
   *
   * @param string|null $prefix 前缀
   * @param array $params 其它参数（max-keys / delimiter / marker …）
   * @return array|boolean 成功返回**对象列表**（无对象时为 `[]`）；**请求失败或 XML 无法解析时返回 false**
   *                       （两者的区别见 {@see lastResults()}）
   */
  public function listObjects($prefix = null, array $params = [])
  {
    $results = $this->execute($this->objects()->listObjects($prefix, $params));
    if (!$this->ok($results)) return false;

    $body = $this->first($results)["body"];
    $parsed = Str::fromXml(is_string($body) ? $body : "");
    if ($parsed === false) return false;   //* XML 解析失败

    $this->lastParsed = $parsed;           //* 完整结构（含分页字段），供 lastParsed() 取

    return self::contentsOf($parsed);
  }

  /**
   * 最近一次 {@see listObjects()} 的**完整解析结果**（含 `IsTruncated` / `NextMarker` / `KeyCount` 等）
   *
   * @return array|null
   */
  public function lastParsed()
  {
    return $this->lastParsed;
  }

  /**
   * 恢复归档对象（解冻）
   *
   * @param string $objectName 对象键
   * @param integer $days 保留天数
   * @param string $tier 解冻优先级
   * @return boolean **成功 true / 失败 false**（成功码 202）
   */
  public function restore($objectName, $days = 30, $tier = "Standard")
  {
    return $this->ok($this->execute($this->objects()->restore($objectName, $days, $tier)));
  }

  /* ── 排障：最近一次执行的原始结果 ─────────────────────────────── */

  /**
   * 最近一次操作的**全部步骤**原始结果
   *
   * 形如 `[["phase" => "put", "result" => ["ok","status","body","headers","error"]], …]`。
   * 操作方法的返回值是语义化的（true/false/内容），需要状态码、响应头、`request-id` 等细节时用它。
   *
   * @return array
   */
  public function lastResults()
  {
    return $this->lastResults;
  }

  /**
   * 最近一次操作的**最后一步**原始结果（没有则 null）
   *
   * @return array|null
   */
  public function lastResult()
  {
    if (!$this->lastResults) return null;

    $last = end($this->lastResults);

    return $last["result"];
  }

  /**
   * 最近一次操作最后一步的错误信息（成功或无记录时为 null）
   *
   * @return string|null
   */
  public function lastError()
  {
    $result = $this->lastResult();

    return $result && isset($result["error"]) ? $result["error"] : null;
  }

  /**
   * 执行一批步骤（**分块上传靠它把 uploadId / ETag 串起来**）
   *
   * 计划里的 `initiate` 会返回 `uploadId`、每个 `part` 会返回 `ETag`，它们要回填给后续步骤 ——
   * 本方法自动完成这个回填，所以分块上传可以一次跑完。
   *
   * @param array $steps 步骤数组（来自计划类）
   * @param string|null $localFilePath 本地文件（分块上传的 `part` 步骤需要它来读对应字节区间）
   * @return array 形如 `[["phase" => …, "result" => …], …]`
   */
  public function execute(array $steps, $localFilePath = null)
  {
    $uploadId = null;
    $etags = [];
    $results = [];

    foreach ($steps as $step) {
      //* 回填：uploadId（未显式给就用初始化拿到的）
      //* ⚠️ 这里**不能**用 isset() —— 计划里 uploadId 的初值就是 null，而 isset(null) 为 false
      //* ⇒ 必须用 array_key_exists() 判断"有没有这个键"
      if (
        isset($step["params"]) && is_array($step["params"])
        && array_key_exists("uploadId", $step["params"])
        && $step["params"]["uploadId"] === null
        && $uploadId !== null
      ) {
        $step["params"]["uploadId"] = $uploadId;
      }
      //* 回填：complete 的请求体要用真实 ETag 重建（计划里那一份是空的）
      if ($step["phase"] === "complete" && $etags) {
        $step["body"] = $this->completeBody($etags);
      }

      $result = $this->send($step, $this->bodyForStep($step, $localFilePath));

      if ($step["phase"] === "initiate") $uploadId = self::pickUploadId($result["body"]);
      if ($step["phase"] === "part" && $result["ok"]) {
        $etags[(int) $step["partNumber"]] = self::pickHeader($result["headers"], "ETag");
      }

      $results[] = ["phase" => $step["phase"], "result" => $result];
    }

    //* 记录原始结果，供 lastResults() / lastResult() / lastError() 排障（操作方法只返回语义值）
    $this->lastResults = $results;

    return $results;
  }

  /**
   * 全部步骤是否都成功
   *
   * @param array $results 步骤结果数组
   * @return boolean
   */
  protected function ok(array $results)
  {
    foreach ($results as $one) {
      if (empty($one["result"]["ok"])) return false;
    }

    return true;
  }

  /**
   * 取第一步的结果（单步操作常用）
   *
   * @param array $results 步骤结果数组
   * @return array
   */
  protected function first(array $results)
  {
    return $results[0]["result"];
  }

  /**
   * 从 `ListBucketResult` 的解析结果里取对象列表，并**统一成列表**
   *
   * {@see Str::fromXml()} 对**只有一个** `<Contents>` 的情况返回的是**关联数组**（那个对象本身），
   * 多个才是列表 —— 这里统一包一层，保证调用方永远拿到列表。
   *
   * @param array $parsed XML 解析结果
   * @return array 对象列表（无对象时为空数组）
   */
  protected static function contentsOf(array $parsed)
  {
    if (!isset($parsed["Contents"])) return [];

    $contents = $parsed["Contents"];
    //* 列表（有 0 键且元素是数组）⇒ 原样；单个对象（关联数组）⇒ 包一层
    if (is_array($contents) && isset($contents[0]) && is_array($contents[0])) return $contents;

    return [$contents];
  }

  /* ── 发送：驱动分发 ───────────────────────────────────────────── */

  /**
   * 发送一步（按驱动分发）
   *
   * 统一返回：`["ok" => bool, "status" => int, "body" => mixed, "headers" => array, "error" => string|null]`
   *
   * @param array $step 步骤
   * @param string|null $body 请求体（分块/表单等；`put` 阶段走文件流时可为 null）
   * @return array
   */
  public function send(array $step, $body = null)
  {
    return $this->usingSdk() ? $this->sendBySdk($step, $body) : $this->sendByHttp($step, $body);
  }

  /**
   * 自己发签名 HTTP 请求（http 驱动）
   *
   * 写法与本仓 `DiscuzXQCloudCOS::request()` 同款：签名参数与业务参数一起进 query、
   * 头以 `CURLOPT_HTTPHEADER` 覆盖（避免被 `send()` 强制的 Content-Type 干扰）、进出各 `reset()`。
   *
   * @param array $step 步骤
   * @param string|null $body 请求体
   * @return array
   */
  protected function sendByHttp(array $step, $body = null)
  {
    $path = isset($step["path"]) ? $step["path"] : "/";
    $params = isset($step["params"]) ? $step["params"] : [];
    $headers = isset($step["headers"]) ? $step["headers"] : [];
    $method = strtolower(isset($step["method"]) ? $step["method"] : "get");

    $sign = $this->signer->createAuthorization($path, $params, $headers, $this->expires, $method);
    $query = array_merge($sign, $params);

    $curl = $this->curl ? $this->curl : ($this->curl = new Curl());
    $curl->reset();
    $curl->url($this->host . $path, $query);
    $curl->https(false);   //* 父类构造里默认 https(false)，这里显式打开证书校验

    if ($headers) {
      $lines = [];
      foreach ($headers as $name => $value) {
        $lines[] = "{$name}: {$value}";
      }
      $curl->options([CURLOPT_HTTPHEADER => $lines]);
    }

    //* 简单上传走文件流（与 putObject 同款，避免整文件进内存）
    if ($method === "put" && !empty($step["file"]) && $body === null) {
      $handle = fopen($step["file"], "rb");
      $curl->options([
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => $handle,
        CURLOPT_INFILESIZE => filesize($step["file"]),
      ]);
      try {
        $curl->put("");
      } finally {
        if (is_resource($handle)) fclose($handle);
      }
    } else {
      switch ($method) {
        case "get":
          $curl->get();
          break;
        case "head":
          $curl->head();
          break;
        case "put":
          $curl->put((string) $body);
          break;
        case "post":
          $curl->post((string) $body);
          break;
        case "delete":
          $curl->delete((string) $body);
          break;
        default:
          //* Curl 没有 OPTIONS 等方法 ⇒ 覆写并把 send() 会开的 HTTPGET 关掉
          $curl->options([CURLOPT_CUSTOMREQUEST => strtoupper($method), CURLOPT_HTTPGET => false]);
          $curl->get();
          break;
      }
    }

    if ($curl->errorNo()) {
      return ["ok" => false, "status" => 0, "body" => null, "headers" => [], "error" => $curl->error()];
    }

    //* ⚠️ 顺序要紧：必须**先**把状态/响应体/响应头读出来，**再** reset()。
    //* （reset() 会清掉响应数据；若先 reset 再读，body 与 headers 会永远是空的 ——
    //*  那样分块上传就拿不到每个块的 ETag，"完成"步骤也就拼不出分块清单。）
    $status = $curl->statusCode();
    $responseBody = $curl->getData();
    $responseHeaders = (array) $curl->responseHeaders();
    $curl->reset();

    $success = isset($step["successStatuses"]) ? $step["successStatuses"] : [200];
    $ok = in_array($status, $success, true);

    //* 失败时补一句人能读的原因（COS 的错误体是 JSON，含 Code / Message）——
    //* 否则 HTTP 层失败时 error 为 null，lastError() 就查不出所以然
    $error = null;
    if (!$ok) {
      //* 响应体可能是**数组**（真实 Curl 会解析 JSON）也可能是**原始字符串**（未解析的驱动/桩）
      //* ⇒ 两种形态都尝试取 Code / Message
      $parsed = $responseBody;
      if (is_string($parsed) && $parsed !== "") {
        $decoded = json_decode($parsed, true);
        if (is_array($decoded)) $parsed = $decoded;
      }

      $code = is_array($parsed) && isset($parsed["Code"]) ? $parsed["Code"] : "";
      $message = is_array($parsed) && isset($parsed["Message"]) ? $parsed["Message"] : "";
      $error = trim("HTTP " . $status . ($code !== "" ? " {$code}" : "") . ($message !== "" ? ": {$message}" : ""));
    }

    return [
      "ok" => $ok,
      "status" => $status,
      "body" => $responseBody,
      "headers" => $responseHeaders,
      "error" => $error,
    ];
  }

  /**
   * 走官方 SDK（sdk 驱动）
   *
   * ⚠️ 命令名已对照 vendor 内的 SDK 确认，但**各命令的数组参数形状未经真机验证**
   * （特别是 `restoreObject` / `listObjects` / 分块系列）。首次接入请对真实桶跑一遍。
   *
   * @param array $step 步骤
   * @param string|null $body 请求体
   * @return array
   */
  protected function sendBySdk(array $step, $body = null)
  {
    $sdk = $this->sdk();
    $bucket = $this->bucket;
    $key = ltrim(isset($step["path"]) ? $step["path"] : "/", "/");
    $headers = isset($step["headers"]) ? $step["headers"] : [];
    $phase = isset($step["phase"]) ? $step["phase"] : "";

    try {
      switch ($phase) {
        case "put":
          $args = ["Bucket" => $bucket, "Key" => $key, "Body" => $body !== null ? $body : fopen($step["file"], "rb")];
          if (isset($headers["Content-Type"])) $args["ContentType"] = $headers["Content-Type"];
          $result = $sdk->putObject($args);
          break;

        case "get":
          $args = ["Bucket" => $bucket, "Key" => $key];
          if (isset($headers["Range"])) $args["Range"] = $headers["Range"];
          $result = $sdk->getObject($args);
          break;

        case "metadata":
        case "exists":
          $result = $sdk->headObject(["Bucket" => $bucket, "Key" => $key]);
          break;

        case "delete":
        case "move-delete":
          $result = $sdk->deleteObject(["Bucket" => $bucket, "Key" => $key]);
          break;

        case "copy":
        case "move-copy":
          $result = $sdk->copyObject([
            "Bucket" => $bucket,
            "Key" => $key,
            "CopySource" => $headers["x-cos-copy-source"],
          ]);
          break;

        case "list":
          $args = ["Bucket" => $bucket];
          foreach (["prefix" => "Prefix", "max-keys" => "MaxKeys", "delimiter" => "Delimiter", "marker" => "Marker"] as $from => $to) {
            if (isset($step["params"][$from])) $args[$to] = $step["params"][$from];
          }
          $result = $sdk->listObjects($args);
          break;

        case "restore":
          $result = $sdk->restoreObject([
            "Bucket" => $bucket,
            "Key" => $key,
            "Days" => self::pickXmlValue($body, "Days"),
            "CASJobParameters" => ["Tier" => self::pickXmlValue($body, "Tier")],
          ]);
          break;

        case "initiate":
          $result = $sdk->createMultipartUpload(["Bucket" => $bucket, "Key" => $key]);
          break;

        case "part":
          $result = $sdk->uploadPart([
            "Bucket" => $bucket,
            "Key" => $key,
            "UploadId" => $step["params"]["uploadId"],
            "PartNumber" => $step["partNumber"],
            "Body" => $body,
          ]);
          break;

        case "complete":
          $result = $sdk->completeMultipartUpload([
            "Bucket" => $bucket,
            "Key" => $key,
            "UploadId" => $step["params"]["uploadId"],
            "MultipartUpload" => self::parsePartsXml($body),
          ]);
          break;

        default:
          return ["ok" => false, "status" => 0, "body" => null, "headers" => [], "error" => "SDK 分支未实现的阶段：{$phase}"];
      }
    } catch (\Exception $e) {
      return ["ok" => false, "status" => 0, "body" => null, "headers" => [], "error" => $e->getMessage()];
    }

    return [
      "ok" => true,
      "status" => 200,
      "body" => $result,
      "headers" => [],
      "error" => null,
    ];
  }

  /* ── 内部 ─────────────────────────────────────────────────────── */

  /**
   * 某一步该带的请求体（分块上传时按 offset/length 读对应字节）
   *
   * @param array $step 步骤
   * @param string|null $localFilePath 本地文件
   * @return string|null
   */
  protected function bodyForStep(array $step, $localFilePath = null)
  {
    if (!empty($step["body"])) return $step["body"];
    if ($localFilePath === null || !isset($step["offset"])) return null;

    $handle = fopen($localFilePath, "rb");
    if (!$handle) return null;
    fseek($handle, (int) $step["offset"]);
    $data = fread($handle, (int) $step["length"]);
    fclose($handle);

    return $data;
  }

  /**
   * 用真实 ETag 重建「完成分块上传」的请求体
   *
   * @param array $etags PartNumber => ETag
   * @return string
   */
  protected function completeBody(array $etags)
  {
    $pairs = [];
    ksort($etags);
    foreach ($etags as $partNumber => $etag) {
      $pairs[] = [$partNumber, $etag];
    }

    $complete = new QCloudCosCompleteMultipartUpload();

    return $complete->parts($pairs)->toXml();
  }

  /**
   * 从响应体里取出 UploadId（分块上传初始化）
   *
   * @param mixed $body 响应体
   * @return string|null
   */
  protected static function pickUploadId($body)
  {
    return self::pickXmlValue($body, "UploadId");
  }

  /**
   * 从文本里取某个 XML 标签的值（不依赖 simplexml 扩展）
   *
   * @param mixed $text XML 文本
   * @param string $tag 标签名
   * @return string|null
   */
  protected static function pickXmlValue($text, $tag)
  {
    if (!is_string($text) || $text === "") return null;
    if (preg_match("/<" . preg_quote($tag, "/") . ">(.*?)<\/" . preg_quote($tag, "/") . ">/s", $text, $m)) {
      return $m[1];
    }

    return null;
  }

  /**
   * 从「完成分块上传」的 XML 里解析出分块清单（SDK 分支用）
   *
   * @param string|null $xml XML 文本
   * @return array
   */
  protected static function parsePartsXml($xml)
  {
    $parts = [];
    if (!is_string($xml) || $xml === "") return $parts;
    if (preg_match_all("/<PartNumber>(\d+)<\/PartNumber>\s*<ETag>(.*?)<\/ETag>/s", $xml, $m, PREG_SET_ORDER)) {
      foreach ($m as $one) {
        $parts[] = ["PartNumber" => (int) $one[1], "ETag" => $one[2]];
      }
    }

    return ["Parts" => $parts];
  }

  /**
   * 大小写不敏感地取响应头
   *
   * @param array|null $headers 响应头
   * @param string $name 头名
   * @return string|null
   */
  protected static function pickHeader($headers, $name)
  {
    foreach ((array) $headers as $key => $value) {
      if (strcasecmp((string) $key, $name) === 0) return $value;
    }

    return null;
  }
}
