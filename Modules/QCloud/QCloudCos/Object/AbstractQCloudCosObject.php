<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

use kernel\Modules\QCloud\QCloudCos\Object\ACL\QCloudCosObjectAclTrait;
use kernel\Modules\QCloud\QCloudCos\Object\Tag\QCloudCosObjectTagTrait;

/**
 * COS 对象操作的「输入容器」基类
 *
 * 各操作（PutObject / GetObject / MultipartUpload…）的专用请求头、ACL 头、表单字段、请求体字段
 * 都写成方法，统一遵循同一套约定：
 *
 * - **不传值 = 读取**：`$obj->storageClass()` 返回当前值（未设置返回 null）
 * - **传值 = 设置**：`$obj->storageClass("STANDARD_IA")` 写入并返回 `$this`，可链式
 *
 * 实现要点：子类（或 trait）方法一行即可 —— `return $this->item("x-cos-storage-class", func_get_args());`。
 * 用 `func_get_args()` 判断"有没有传参"，而不是 `$value === null` ⇒ **显式传 null/空串也算设置**，
 * 不会与"读取"混淆。
 *
 * 每个子类通过 {@see all()} 交出「已设置项」的字典（键名就是最终的头名/字段名/参数名），
 * 交给请求层拼接；因此"没设就不发"是天然成立的，不需要额外判断。
 *
 * ## 能力分区（按 COS 文档的接口分类，各自独立成命名空间）
 *
 * | 能力 | 位置 | 说明 |
 * |---|---|---|
 * | 容器本体 | 本类 | `item()` / `all()` / `isEmpty()` / `clear()` / `merge()` |
 * | **访问控制（ACL）** | {@see QCloudCosObjectAclTrait}（`Object\ACL`） | `acl()` / 四个 `grant*()` / 静态 `grantId()` |
 * | **对象标签** | {@see QCloudCosObjectTagTrait}（`Object\Tag`） | `tagging()` / `tag()` |
 * | 对象属性 / 元数据 / 加密 | 本类 | `storageClass()` / `forbidOverwrite()` / `meta()` / `metas()` / `allMetas()` / SSE 六个头 + `sse*()` |
 *
 * 两个能力用 **trait** 组合进来（PHP 单继承 ⇒ 需要跨类复用的能力只能用 trait），因此
 * **所有**输入容器都天然具备 ACL 与标签方法；不需要的容器不调用即可（"没设就不发"）。
 * ACL 预设常量留在本类：**PHP 7 的 trait 不能声明常量**。
 *
 * ## 与签名/请求的关系
 *
 * 配合 {@see \kernel\Modules\QCloud\QCloudCos\QCloudCosSignture} 使用时必须注意：
 * `x-cos-` 开头的头**一律参与签名**；常规头（`Cache-Control` / `Content-Type` / `Range` 等）
 * 命中签名器白名单时也会入签 ⇒ 容器产出的内容必须**同时**用于「签名」与「真实请求」，两边逐字一致。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
abstract class AbstractQCloudCosObject
{
  //* 访问控制（ACL）与对象标签的方法组：代码分别落在 Object\ACL 与 Object\Tag 命名空间下
  use QCloudCosObjectAclTrait;
  use QCloudCosObjectTagTrait;

  /* ── 共用取值常量 ─────────────────────────────────────────────── */

  /** ACL：继承权限（默认） */
  const ACL_DEFAULT = "default";
  /** ACL：私有读写 */
  const ACL_PRIVATE = "private";
  /** ACL：公有读私有写 */
  const ACL_PUBLIC_READ = "public-read";

  const STORAGE_STANDARD = "STANDARD";
  const STORAGE_STANDARD_IA = "STANDARD_IA";
  const STORAGE_ARCHIVE = "ARCHIVE";
  const STORAGE_DEEP_ARCHIVE = "DEEP_ARCHIVE";
  const STORAGE_MAZ_STANDARD = "MAZ_STANDARD";
  const STORAGE_MAZ_STANDARD_IA = "MAZ_STANDARD_IA";
  const STORAGE_MAZ_ARCHIVE = "MAZ_ARCHIVE";
  const STORAGE_INTELLIGENT_TIERING = "INTELLIGENT_TIERING";
  const STORAGE_MAZ_INTELLIGENT_TIERING = "MAZ_INTELLIGENT_TIERING";

  /** 元数据/标签复制指令：沿用源对象 */
  const DIRECTIVE_COPY = "Copy";
  /** 元数据/标签复制指令：以本次请求指定值替换 */
  const DIRECTIVE_REPLACED = "Replaced";

  /**
   * 已设置的项（头名/字段名 => 值）
   *
   * @var array
   */
  protected $items = [];

  /**
   * get/set 通用实现（子类与各 trait 的方法一行调用）
   *
   * @param string $name 头名/字段名/参数名
   * @param array $args 调用方用 `func_get_args()` 传入的实参：空=读取，否则取第 1 个为值
   * @return mixed 读取时返回值（未设置 null）；设置时返回 $this
   */
  protected function item($name, array $args)
  {
    if (!$args) {
      return isset($this->items[$name]) ? $this->items[$name] : null;
    }

    $this->items[$name] = $args[0];

    return $this;
  }

  /**
   * 取出全部已设置项（键名即最终的头名/字段名）
   *
   * @return array
   */
  public function all()
  {
    return $this->items;
  }

  /**
   * 是否一项都没设置
   *
   * @return boolean
   */
  public function isEmpty()
  {
    return !$this->items;
  }

  /**
   * 清空全部已设置项（复用同一实例做多次请求时必须调它，否则上次的值会泄漏到下次）
   *
   * @return $this
   */
  public function clear()
  {
    $this->items = [];

    return $this;
  }

  /**
   * 批量并入原始键值（后写覆盖先写）
   *
   * @param array $items 键名 => 值
   * @return $this
   */
  public function merge(array $items)
  {
    foreach ($items as $name => $value) {
      $this->items[$name] = $value;
    }

    return $this;
  }

  /* ── 对象属性（多操作共用） ─────────────────────────────────────── */

  /**
   * x-cos-storage-class（对象存储类型，默认 STANDARD）
   *
   * 取值见本类 `STORAGE_*` 常量；部分类型仅特定地域开放，MAZ 与智能分层需在桶上开启。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function storageClass($value = null)
  {
    return $this->item("x-cos-storage-class", func_get_args());
  }

  /**
   * x-cos-forbid-overwrite（未开版本控制的桶里是否禁止覆盖同名对象）
   *
   * `true` ⇒ 禁止覆盖（存在同名对象时返回 409 `FileAlreadyExists`）；不携带或 `false` ⇒ 覆盖。
   * **桶开启版本控制时该头无效**。
   *
   * @param boolean|null $value 不传=读取（返回布尔或 null）
   * @return mixed
   */
  public function forbidOverwrite($value = null)
  {
    if (!func_get_args()) {
      $current = $this->item("x-cos-forbid-overwrite", []);

      return $current === null ? null : ($current === "true");
    }

    return $this->item("x-cos-forbid-overwrite", [$value ? "true" : "false"]);
  }

  /**
   * x-cos-meta-[后缀]（自定义元数据，单个键的读取/设置）
   *
   * 后缀规则（官方）：支持**减号 `-`、数字、小写字母 a-z**；大写会被转成小写；
   * **后缀不支持下划线 `_`**（元数据的「值」可以）；单条 ≤ 2KB。
   * 本方法只做**小写化**，其余规则不校验（保持"纯容器"）。
   *
   * @param string $key 元数据后缀（如 `via`）
   * @param string|null $value 传值=设置；只传 $key=读取
   * @return mixed 读取时返回值（未设置 null）；设置时返回 $this
   */
  public function meta($key, $value = null)
  {
    $name = "x-cos-meta-" . strtolower((string) $key);

    if (func_num_args() < 2) {
      return $this->item($name, []);
    }

    return $this->item($name, [$value]);
  }

  /**
   * 批量设置自定义元数据
   *
   * @param array $metas 后缀 => 值
   * @return $this
   */
  public function metas(array $metas)
  {
    foreach ($metas as $key => $value) {
      $this->meta($key, $value);
    }

    return $this;
  }

  /**
   * 读取全部已设置的自定义元数据（后缀 => 值）
   *
   * @return array
   */
  public function allMetas()
  {
    $metas = [];
    foreach ($this->items as $name => $value) {
      if (strpos($name, "x-cos-meta-") === 0) {
        $metas[substr($name, strlen("x-cos-meta-"))] = $value;
      }
    }

    return $metas;
  }

  /* ── 目标对象服务端加密（SSE，多操作共用） ─────────────────────── */

  /**
   * x-cos-server-side-encryption（服务端加密方式）
   *
   * SSE-COS 用 `AES256`（{@see sseCos()}）；SSE-KMS 用 `cos/kms`（{@see sseKms()}）。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function serverSideEncryption($value = null)
  {
    return $this->item("x-cos-server-side-encryption", func_get_args());
  }

  /**
   * x-cos-server-side-encryption-cos-kms-key-id（SSE-KMS 的 KMS 密钥 ID）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function kmsKeyId($value = null)
  {
    return $this->item("x-cos-server-side-encryption-cos-kms-key-id", func_get_args());
  }

  /**
   * x-cos-server-side-encryption-context（SSE-KMS 的加密上下文，Base64）
   *
   * @param string|null $value Base64 字符串；不传=读取
   * @return mixed
   */
  public function kmsContext($value = null)
  {
    return $this->item("x-cos-server-side-encryption-context", func_get_args());
  }

  /**
   * x-cos-server-side-encryption-customer-algorithm（SSE-C 算法，固定 AES256）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function customerAlgorithm($value = null)
  {
    return $this->item("x-cos-server-side-encryption-customer-algorithm", func_get_args());
  }

  /**
   * x-cos-server-side-encryption-customer-key（SSE-C 密钥，Base64）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function customerKey($value = null)
  {
    return $this->item("x-cos-server-side-encryption-customer-key", func_get_args());
  }

  /**
   * x-cos-server-side-encryption-customer-key-MD5（SSE-C 密钥 MD5，Base64）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function customerKeyMD5($value = null)
  {
    return $this->item("x-cos-server-side-encryption-customer-key-MD5", func_get_args());
  }

  /**
   * 便捷：启用 SSE-COS（COS 托管密钥）
   *
   * @return $this
   */
  public function sseCos()
  {
    return $this->serverSideEncryption("AES256");
  }

  /**
   * 便捷：启用 SSE-KMS（KMS 托管密钥）
   *
   * @param string $keyId KMS 密钥 ID
   * @param string|null $context Base64 形式的加密上下文；不传则不带头
   * @return $this
   */
  public function sseKms($keyId, $context = null)
  {
    $this->serverSideEncryption("cos/kms")->kmsKeyId($keyId);
    if ($context !== null) {
      $this->kmsContext($context);
    }

    return $this;
  }

  /**
   * 便捷：启用 SSE-C（客户自备密钥）
   *
   * @param string $key Base64 形式的密钥
   * @param string $keyMD5 Base64 形式的密钥 MD5
   * @return $this
   */
  public function sseCustomer($key, $keyMD5)
  {
    return $this->customerAlgorithm("AES256")->customerKey($key)->customerKeyMD5($keyMD5);
  }
}
