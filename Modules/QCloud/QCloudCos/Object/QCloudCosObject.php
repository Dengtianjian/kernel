<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\Str;
use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Modules\QCloud\QCloudCos\Object\ACL\QCloudCosPutObjectAcl;
use kernel\Modules\QCloud\QCloudCos\Object\MultipartUpload\QCloudCosMultipartUpload;
use kernel\Modules\QCloud\QCloudCos\Object\Tag\QCloudCosPutObjectTagging;

/**
 * 一个「存储对象」：实例即某个对象键，可以对它做各种操作
 *
 * 本目录其它类都是「某一步的参数容器」（`QCloudCosPutObject` 描述 PUT Object 的头、
 * `QCloudCosMultipartUpload` 编排分块上传…）。本类换了个角度：**它本身就是那个对象** ——
 * 持有一个对象键（以及它的元信息），然后把各操作的参数容器**聚合**到自己身上，
 * 并能按操作名产出「该发什么」（{@see plan()}）。
 *
 * ```php
 * $object = new QCloudCosObject("images/a.png", "mybucket-1250000000", "ap-guangzhou");
 *
 * // ① 直接拿某个操作的容器设参数（同一操作反复取到的是同一个实例）
 * $object->put()->contentType("image/png")->acl("public-read");
 * $object->get()->range("bytes=0-1023");
 *
 * // ② 按操作名产出请求计划
 * $steps = $object->plan("put", "/tmp/a.png");     // → [PUT Object 一步]
 * $steps = $object->plan("get");                   // → [GET Object 一步]
 * $steps = $object->plan("upload", "/tmp/big.zip");// → 自动按大小选简单/分块
 *
 * // ③ 把 HEAD/GET 的响应头回填进来，之后可直接读元信息
 * $object->fill($responseHeaders);
 * $object->size();   // Content-Length
 * $object->etag();   // ETag
 * ```
 *
 * ## ⚠️ 与同目录其它类一致：**不发请求、不做签名**
 *
 * 它产出的是「每一步该发什么」（`method`/`path`/`params`/`headers`/`body`/`successStatuses`）。
 * 其中 `params` 是**参与签名的 URL 参数**（子资源如 `?restore`/`?select`/`?acl`/`?tagging`），
 * 与 `?delete` 同一套机制 —— 必须**同时**进签名与真实 URL。
 * 真正发送要等 `DiscuzXQCloudCOS` 的 `$options` 地基落地；接线即把每步交给它。
 *
 * `bucket` / `region` 是**可选**的：只在需要自动拼 `x-cos-copy-source`（复制时指向本对象）时才用得上。
 *
 * 文档核对状态：**待核对**（各操作的请求形态沿用对应容器类里的说明；首次接入对真实桶复核）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosObject extends AbilityBaseObject
{
  /** @var string|null 对象键（原始，未编码） */
  protected $objectKey;
  /** @var string|null 最近一次取过的操作名（`get()` / `put()` …），供 {@see run()} 无参调用时使用 */
  protected $lastOperation = null;
  /** @var string|null 桶名（可选，用于自动拼 copy-source） */
  protected $bucket;
  /** @var string|null 地域（可选，用于自动拼 copy-source） */
  protected $region;
  /** @var array 由 {@see fill()} 回填的元信息 */
  protected $attributes = [];
  /** @var array 已聚合的操作容器（键 => 实例） */
  protected $containers = [];
  /** @var object|null 执行器（QCloudCosClient）；**绑定后**各操作访问器返回可执行包装 */
  protected $executor = null;

  /**
   * @param string|null $objectKey 对象键
   * @param string|null $bucket 桶名（可选；用于自动拼 `x-cos-copy-source`）
   * @param string|null $region 地域（可选）
   * @param object|null $executor 执行器（QCloudCosClient，可选）；绑了就能 `->get()->run()`
   */
  public function __construct($objectKey = null, $bucket = null, $region = null, $executor = null)
  {
    $this->objectKey = $objectKey;
    $this->bucket = $bucket;
    $this->region = $region;
    $this->executor = $executor;
  }

  /**
   * 执行器（不传=读取，传值=设置）
   *
   * 只有**绑定了执行器**（`QCloudCosClient`），{@see run()} 才能真正发请求；
   * 未绑定时 {@see run()} 会记录错误态并返回 `false`。
   *
   * 各操作访问器（`get()` / `put()` …）**无论绑没绑**都返回参数容器（只负责配置），不会变。
   *
   * @param object|null $value 不传=读取
   * @return mixed
   */
  public function executor($value = null)
  {
    if (!func_get_args()) return $this->executor;

    $this->executor = $value;

    return $this;
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
   * 桶名（可选；不传=读取，传值=设置）
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
   * 地域（可选；不传=读取，传值=设置）
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
   * 对象键编码后的路径（按段 `rawurlencode` + 前导 `/`）
   *
   * @return string
   */
  public function path()
  {
    return self::encodePath((string) $this->objectKey);
  }

  /**
   * 本对象的 `x-cos-copy-source` 值（复制时作为源）
   *
   * 需要 {@see bucket()} 与 {@see region()} 都已设置，否则返回 null。
   *
   * @return string|null 形如 `bucket.cos.region.myqcloud.com/images/a.png`
   */
  public function source()
  {
    if (!$this->bucket || !$this->region) return null;

    return "{$this->bucket}.cos.{$this->region}.myqcloud.com" . $this->path();
  }

  /* ── 元信息（由响应头回填） ───────────────────────────────────── */

  /**
   * 用响应头回填元信息（HEAD Object / GET Object 的响应头）
   *
   * 头名**大小写不敏感**；`x-cos-meta-*` 会自动收进 {@see metas()}。
   *
   * @param array $headers 响应头（头名 => 值）
   * @return $this
   */
  public function fill(array $headers)
  {
    $map = [
      "content-length" => "size",
      "etag" => "etag",
      "last-modified" => "lastModified",
      "content-type" => "contentType",
      "x-cos-storage-class" => "storageClass",
      "x-cos-object-type" => "objectType",
    ];

    foreach ($headers as $name => $value) {
      $lower = strtolower((string) $name);

      if (strpos($lower, "x-cos-meta-") === 0) {
        $this->attributes["metas"][substr($lower, strlen("x-cos-meta-"))] = $value;
        continue;
      }

      if (isset($map[$lower])) {
        $this->attributes[$map[$lower]] = $value;
      }
    }

    return $this;
  }

  /**
   * 通用元信息读写（不传=读取，传值=设置）
   *
   * @param string $name 元信息名
   * @param array $args 用 `func_get_args()` 传入
   * @return mixed
   */
  protected function attribute($name, array $args)
  {
    if (!$args) {
      return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    $this->attributes[$name] = $args[0];

    return $this;
  }

  /**
   * 对象大小（来自 `Content-Length`；不传=读取，传值=设置）
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function size($value = null)
  {
    return $this->attribute("size", func_get_args());
  }

  /**
   * ETag（来自 `ETag`）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function etag($value = null)
  {
    return $this->attribute("etag", func_get_args());
  }

  /**
   * 最后修改时间（来自 `Last-Modified`）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function lastModified($value = null)
  {
    return $this->attribute("lastModified", func_get_args());
  }

  /**
   * Content-Type
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function contentType($value = null)
  {
    return $this->attribute("contentType", func_get_args());
  }

  /**
   * 存储类型（来自 `x-cos-storage-class`）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function storageClass($value = null)
  {
    return $this->attribute("storageClass", func_get_args());
  }

  /**
   * 单个自定义元数据（`x-cos-meta-*`，读取/设置）
   *
   * @param string $key 元数据后缀
   * @param string|null $value 传值=设置；只传 key=读取
   * @return mixed
   */
  public function meta($key, $value = null)
  {
    $key = strtolower((string) $key);

    if (func_num_args() < 2) {
      return isset($this->attributes["metas"][$key]) ? $this->attributes["metas"][$key] : null;
    }

    $this->attributes["metas"][$key] = $value;

    return $this;
  }

  /**
   * 全部自定义元数据（后缀 => 值）
   *
   * @return array
   */
  public function metas()
  {
    return isset($this->attributes["metas"]) ? $this->attributes["metas"] : [];
  }

  /**
   * 已回填的全部元信息（不含 `metas`）
   *
   * @return array
   */
  public function info()
  {
    $info = $this->attributes;
    unset($info["metas"]);

    return $info;
  }

  /* ── 聚合：各操作的参数容器 ───────────────────────────────────── */

  /**
   * PUT Object（简单上传）的容器
   *
   * @return QCloudCosPutObject
   */
  public function put()
  {
    return $this->container("put", "QCloudCosPutObject");
  }

  /**
   * PUT Object - Copy 的容器
   *
   * @return QCloudCosPutObjectCopy
   */
  public function copy()
  {
    return $this->container("copy", "QCloudCosPutObjectCopy");
  }

  /**
   * POST Object（表单上传）的容器
   *
   * @return QCloudCosPostObject
   */
  public function post()
  {
    return $this->container("post", "QCloudCosPostObject");
  }

  /**
   * GET Object 的容器
   *
   * @return QCloudCosGetObject
   */
  public function get()
  {
    return $this->container("get", "QCloudCosGetObject");
  }

  /**
   * HEAD Object 的容器
   *
   * @return QCloudCosHeadObject
   */
  public function head()
  {
    return $this->container("head", "QCloudCosHeadObject");
  }

  /**
   * DELETE Object 的容器（该接口按现行文档无专用请求头）
   *
   * @return QCloudCosDeleteObject
   */
  public function delete()
  {
    return $this->container("delete", "QCloudCosDeleteObject");
  }

  /**
   * OPTIONS Object（CORS 预检）的容器
   *
   * @return QCloudCosOptionsObject
   */
  public function options()
  {
    return $this->container("options", "QCloudCosOptionsObject");
  }

  /**
   * POST Object restore（解冻）的容器
   *
   * @return QCloudCosPostObjectRestore
   */
  public function restore()
  {
    return $this->container("restore", "QCloudCosPostObjectRestore");
  }

  /**
   * SELECT Object Content 的容器
   *
   * @return QCloudCosSelectObjectContent
   */
  public function select()
  {
    return $this->container("select", "QCloudCosSelectObjectContent");
  }

  /**
   * PUT Object acl（写 ACL）的容器
   *
   * 读取 ACL 用 `GET ?acl`，对应的类是 `Object\ACL\QCloudCosGetObjectAcl`（本类不聚合，
   * 因为「读」没有请求参数）。
   *
   * @return QCloudCosPutObjectAcl
   */
  public function acl()
  {
    return $this->container("acl", "QCloudCosPutObjectAcl", "ACL");
  }

  /**
   * PUT Object tagging（写标签）的容器
   *
   * 读/删标签分别是 `Object\Tag\QCloudCosGetObjectTagging` / `QCloudCosDeleteObjectTagging`
   * （都没有请求参数，故本类不聚合）。
   *
   * @return QCloudCosPutObjectTagging
   */
  public function tagging()
  {
    return $this->container("tagging", "QCloudCosPutObjectTagging", "Tag");
  }

  /**
   * 分块上传的编排器（键与本类同步）
   *
   * @return QCloudCosMultipartUpload
   */
  public function multipart()
  {
    if (!isset($this->containers["multipart"])) {
      $this->containers["multipart"] = new QCloudCosMultipartUpload($this->objectKey);
    } else {
      $this->containers["multipart"]->key($this->objectKey);
    }

    return $this->containers["multipart"];
  }

  /**
   * 「上传对象」调度器（按大小自动选简单/分块，键与本类同步）
   *
   * @return QCloudCosUploadObject
   */
  public function upload()
  {
    if (!isset($this->containers["upload"])) {
      $this->containers["upload"] = new QCloudCosUploadObject($this->objectKey);
    } else {
      $this->containers["upload"]->key($this->objectKey);
    }

    return $this->containers["upload"];
  }

  /* ── 执行（需要绑定执行器） ───────────────────────────────────── */

  /**
   * 最近一次取用的操作名（`get()` / `put()` …）
   *
   * 取操作容器（如 `$object->get()`）时会记下它 ⇒ 之后 {@see run()} 不传操作名就执行这个。
   *
   * @return string|null
   */
  public function lastOperation()
  {
    return $this->lastOperation;
  }

  /**
   * **执行**某个操作（本类唯一的"发请求"入口）
   *
   * 约定：**取操作容器 = 配置**（`get()` / `put()` … 返回的容器只用来设参数），**`run()` = 执行**。
   *
   * ```php
   * $object = $cos->object("3.png");
   * $object->get()->range("bytes=0-9");     // 配置（容器）
   * $result = $object->run();               // 执行**最后取用的**操作（这里是 get）
   *
   * $result = $object->run("get");          // 也可以显式指定操作名
   * $ok     = $object->put()->run("/tmp/a.png");   // ← 注意：put() 返回的是容器，run() 在句柄上
   * ```
   *
   * 返回值语义（失败一律 `false`）：
   * - `get` / `select` ⇒ 响应体（内容字符串）
   * - `head` / `options` ⇒ 响应头数组
   * - 其余（`put` / `delete` / `copy` / `restore` / `acl` / `tagging` / `upload` / `multipart`）⇒ `true`
   *
   * 失败时用 {@see break()} 记录错误态并返回 `false` ⇒ 调用方可用 `isError()` /
   * `getErrorCode()` / `getErrorMessage()` / `getStatusCode()` / `getErrorDetails()` 读详情
   * （均来自 {@see AbilityBaseObject}，本类继承它，因此也具备 {@see forwardBreak()}）。
   *
   * @param string|null $operation 操作名（见 {@see operations()}）；不传则用 {@see lastOperation()}
   * @param string|null $argument 随操作而异（put/upload/multipart 为本地文件；copy 为目标键）
   * @param integer $partSize 分块大小（分块类操作生效）
   * @return mixed 成功按语义返回；**失败返回 false**
   */
  public function run($operation = null, $argument = null, $partSize = QCloudCosMultipartUpload::DEFAULT_PART_SIZE)
  {
    $operation = $operation !== null ? $operation : $this->lastOperation;

    if (!$operation) {
      return $this->break(400, 400, "未指定要执行的操作：请先取一次操作（如 \$object->get()）再 run()，或直接 run(\"get\")");
    }
    if (!$this->executor) {
      return $this->break(500, 500, "未绑定执行器：请用 QCloudCosClient::object() 获取句柄，或先调 executor(\$client)");
    }

    try {
      $steps = $this->plan($operation, $argument, $partSize);
    } catch (\Exception $e) {
      //* 不支持的操作（如 post）、或计划阶段的参数校验失败 ⇒ 转成错误态返回 false
      return $this->break(400, 400, $e->getMessage());
    }

    //* 分块/上传类步骤需要本地文件来读对应字节区间
    $needsFile = in_array($operation, ["put", "upload", "multipart"], true);
    $results = $this->executor->execute($steps, $needsFile ? $argument : null);

    $last = end($results);
    $result = $last["result"];

    if (!$result["ok"]) {
      $status = $result["status"] ? $result["status"] : 500;
      if (array_key_exists("body", $result) && is_string($result['body']) && strpos($result['body'], "xml") !== false) {
        $result['body'] = Str::fromXml($result['body']);
      }

      return $this->break($status, $status, $result["error"] ? $result["error"] : "COS 请求失败", $result);
    }

    switch ($operation) {
      case "get":
      case "select":
        return $result["body"];

      case "head":
      case "options":
        return (array) $result["headers"];

      default:
        return true;
    }
  }

  /* ── 请求计划 ─────────────────────────────────────────────────── */

  /**
   * {@see plan()} 支持的操作名
   *
   * @return array
   */
  public function operations()
  {
    return [
      "put",
      "get",
      "head",
      "delete",
      "copy",
      "options",
      "restore",
      "select",
      "acl",
      "tagging",
      "upload",
      "multipart",
    ];
  }

  /**
   * 产出某个操作的请求计划（**不发请求**）
   *
   * 每一步含 `phase / method / path / params / headers / body / successStatuses / note`（分块类
   * 另见 `QCloudCosMultipartUpload::plan()`）。`params` 是**参与签名的 URL 参数**。
   *
   * `$argument` 的用途随操作而定：
   * - `put` / `upload` / `multipart`：本地文件路径（必填）
   * - `copy`：目标对象键（不传则复制到本对象自身路径）
   * - 其余：不需要
   *
   * @param string $operation 操作名（见 {@see operations()}）
   * @param string|null $argument 随操作而异的参数
   * @param integer $partSize 分块大小（仅 `upload` / `multipart` 生效）
   * @return array 有序步骤数组
   * @throws \InvalidArgumentException 操作名不支持（或分块类对文件的校验失败）
   */
  public function plan($operation, $argument = null, $partSize = QCloudCosMultipartUpload::DEFAULT_PART_SIZE)
  {
    $path = $this->path();

    switch ($operation) {
      case "put":
        return [[
          "phase" => "put",
          "method" => "PUT",
          "path" => $path,
          "params" => [],
          "headers" => $this->put()->headers(),
          "body" => null,
          "file" => $argument,
          "successStatuses" => [200],
          "note" => "请求体即整个文件内容；PUT Object 必带 Content-Type",
        ]];

      case "get":
        return [[
          "phase" => "get",
          "method" => "GET",
          "path" => $path,
          "params" => [],
          "headers" => $this->get()->all(),
          "body" => null,
          "successStatuses" => [200, 206],
          "note" => "带 Range 时成功码为 206",
        ]];

      case "head":
        return [[
          "phase" => "head",
          "method" => "HEAD",
          "path" => $path,
          "params" => [],
          "headers" => $this->head()->all(),
          "body" => null,
          "successStatuses" => [200],
          "note" => "元信息在响应头里（可用 fill() 回填到本对象）",
        ]];

      case "delete":
        return [[
          "phase" => "delete",
          "method" => "DELETE",
          "path" => $path,
          "params" => [],
          "headers" => $this->delete()->all(),
          "body" => null,
          "successStatuses" => [204],
          "note" => "成功 204 无内容；版本控制场景另需 versionId（URL 参数）",
        ]];

      case "copy":
        $headers = $this->copy()->all();
        //* 未显式指定源、且已知桶与地域 ⇒ 自动把源指向**本对象**
        if (!isset($headers["x-cos-copy-source"]) && $this->source() !== null) {
          $headers["x-cos-copy-source"] = $this->source();
        }

        return [[
          "phase" => "copy",
          "method" => "PUT",
          "path" => $argument === null ? $path : self::encodePath((string) $argument),
          "params" => [],
          "headers" => $headers,
          "body" => null,
          "successStatuses" => [200],
          "note" => "PUT 到**目标**键，源由 x-cos-copy-source 指定",
        ]];

      case "options":
        return [[
          "phase" => "options",
          "method" => "OPTIONS",
          "path" => $path,
          "params" => [],
          "headers" => $this->options()->all(),
          "body" => null,
          "successStatuses" => [200, 204],
          "note" => "CURL 无 OPTIONS 方法 ⇒ 需用 CURLOPT_CUSTOMREQUEST 覆写并关掉 CURLOPT_HTTPGET",
        ]];

      case "restore":
        return [[
          "phase" => "restore",
          "method" => "POST",
          "path" => $path,
          "params" => ["restore" => ""],
          "headers" => ["Content-Type" => "application/xml"],
          "body" => $this->restore()->toXml(),
          "successStatuses" => [202],
          "note" => "?restore 是子资源，须同时进签名与 URL；解冻是异步的",
        ]];

      case "select":
        return [[
          "phase" => "select",
          "method" => "POST",
          "path" => $path,
          "params" => ["select" => ""],
          "headers" => ["Content-Type" => "application/xml"],
          "body" => $this->select()->toXml(),
          "successStatuses" => [200],
          "note" => "?select 是子资源；响应是事件流，需自行拆帧",
        ]];

      case "acl":
        return [[
          "phase" => "acl",
          "method" => "PUT",
          "path" => $path,
          "params" => ["acl" => ""],
          "headers" => $this->acl()->all(),
          "body" => $this->acl()->toXml(),
          "successStatuses" => [200],
          "note" => "请求头与请求体二选一（body 为空串时即走头）；?acl 是子资源",
        ]];

      case "tagging":
        return [[
          "phase" => "tagging",
          "method" => "PUT",
          "path" => $path,
          "params" => ["tagging" => ""],
          "headers" => ["Content-Type" => "application/xml"],
          "body" => $this->tagging()->toXml(),
          "successStatuses" => [200],
          "note" => "?tagging 是子资源",
        ]];

      case "upload":
        return $this->upload()->plan($argument, $partSize);

      case "multipart":
        return $this->multipart()->plan($argument, $partSize);
    }

    throw new \InvalidArgumentException(
      "QCloudCosObject：不支持的操作「{$operation}」；可用：" . implode(" / ", $this->operations())
    );
  }

  /* ── 内部 ─────────────────────────────────────────────────────── */

  /**
   * 取（并缓存）某个操作容器
   *
   * @param string $name 缓存键
   * @param string $shortClass 类名（不含命名空间）
   * @param string $subNamespace 子命名空间（空=与本类同级）
   * @return object
   */
  protected function container($name, $shortClass, $subNamespace = "")
  {
    if (!isset($this->containers[$name])) {
      $class = __NAMESPACE__ . ($subNamespace ? "\\" . $subNamespace : "") . "\\" . $shortClass;
      $this->containers[$name] = new $class();
    }

    //* 记下"最近取用的操作" ⇒ 之后 `run()` 不传操作名时就执行它
    $this->lastOperation = $name;

    //* 访问器**永远**返回参数容器：它只负责"配置"，发请求统一走 {@see run()}
    return $this->containers[$name];
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
}
