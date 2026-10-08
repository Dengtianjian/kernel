<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

/**
 * POST Object（表单上传）的输入容器
 *
 * ⚠️ 与其它容器类不同：POST Object 的对象属性、ACL、加密等**走表单字段**（`form-data` 的 key），
 * 而不是 HTTP 请求头 —— 所以本类用 {@see fields()} 取结果（内容语义与 {@see all()} 相同）。
 *
 * ⚠️ **文档核对状态：未核对**。抓取官方「POST Object」页时拿到的是「PUT Object acl」页（链接不对），
 * 因此本类的字段清单**依据的是通用实践与相邻接口**，尚未逐条对过官方页面 ⇒ 首次接入前请对着
 * 「POST Object」文档核一遍（尤其回调字段的**连字符风格**：`callbackBody` 无连字符，而
 * `callback-var` 有连字符，这类细节最容易写错）。
 *
 * ```php
 * $post = (new QCloudCosPostObject())
 *     ->key("images/a.png")
 *     ->contentType("image/png")                    // 表单字段：描述「对象」的 MIME
 *     ->acl(QCloudCosPostObject::ACL_PUBLIC_READ)
 *     ->meta("author", "gstudio")
 *     ->successActionStatus(204);
 * ```
 *
 * 几条容易搞混的（同名不同义）：
 * - **`Content-Type`**：作为 **HTTP 头**必须是 `multipart/form-data; boundary=…`（描述整个请求体，
 *   由 cURL 自动生成，**不要**手设）；作为**表单字段**才是**对象自身**的 MIME 类型 —— 本类管的是后者；
 * - **`Content-MD5`**：作为 HTTP 头校验整个请求体；作为表单字段校验**对象内容**（实践中通常只用后者）；
 * - **`policy` 与 `q-*` 签名字段**：由签名/上传逻辑生成（见 {@see \kernel\Modules\DiscuzX\Foundation\Storage\QCloud\DiscuzXQCloudCOS::postObject()}），
 *   本类虽提供 `policy()` 等方法，但正常流程不需要手动设。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosPostObject extends AbstractQCloudCosObject
{
  /**
   * 取出全部表单字段（与 {@see all()} 等价，语义更贴合 POST Object）
   *
   * @return array 字段名 => 值
   */
  public function fields()
  {
    return $this->all();
  }

  /**
   * key（**必选**：对象键 / 文件名）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function key($value = null)
  {
    return $this->item("key", func_get_args());
  }

  /**
   * policy（Base64 编码的策略；使用签名时必选）
   *
   * 通常由上传逻辑根据 policy JSON 生成，无需手工设置。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function policy($value = null)
  {
    return $this->item("policy", func_get_args());
  }

  /**
   * Content-Type（**表单字段**：对象自身的 MIME 类型）
   *
   * ⚠️ 与 HTTP 头的 `Content-Type`（必须是 `multipart/form-data; boundary=…`，由 cURL 生成）**不同义**。
   *
   * @param string|null $value 如 `image/png`；不传=读取
   * @return mixed
   */
  public function contentType($value = null)
  {
    return $this->item("Content-Type", func_get_args());
  }

  /**
   * Content-MD5（**表单字段**：对象内容的 MD5，Base64）
   *
   * COS 会用它校验对象内容。可用 {@see \kernel\Modules\QCloud\QCloudCos\Object\QCloudCosPutObject::contentMD5Base64()} 同款方式计算。
   *
   * @param string|null $value Base64 形式；不传=读取
   * @return mixed
   */
  public function contentMD5($value = null)
  {
    return $this->item("Content-MD5", func_get_args());
  }

  /**
   * x-cos-security-token（临时密钥的会话令牌；用临时密钥上传时必填）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function securityToken($value = null)
  {
    return $this->item("x-cos-security-token", func_get_args());
  }

  /**
   * success_action_status（上传成功时返回给客户端的状态码）
   *
   * 常用 `200` / `201` / `204`。
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function successActionStatus($value = null)
  {
    return $this->item("success_action_status", func_get_args());
  }

  /**
   * x-cos-traffic-limit（本次上传的限速值，bit/s）
   *
   * ⚠️ 待核对：该字段在 POST Object 中是否支持、以及取值范围（PUT Object 页给的是 819200–838860800）。
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function trafficLimit($value = null)
  {
    return $this->item("x-cos-traffic-limit", func_get_args());
  }

  /**
   * callback（上传成功后的回调 URL，Base64 编码的 JSON）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function callback($value = null)
  {
    return $this->item("callback", func_get_args());
  }

  /**
   * callbackBody（回调内容模板）
   *
   * ⚠️ 待核对字段名（注意与其他回调字段的连字符风格可能不一致）。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function callbackBody($value = null)
  {
    return $this->item("callbackBody", func_get_args());
  }

  /**
   * callbackBodyType（回调 body 的类型，如 `application/json`）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function callbackBodyType($value = null)
  {
    return $this->item("callbackBodyType", func_get_args());
  }

  /**
   * callbackHost（回调请求携带的 Host 头）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function callbackHost($value = null)
  {
    return $this->item("callbackHost", func_get_args());
  }

  /**
   * callback-var（回调变量，JSON）
   *
   * ⚠️ 字段名带连字符（与上面的 `callbackBody` 风格不一致），待核对。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function callbackVar($value = null)
  {
    return $this->item("callback-var", func_get_args());
  }

  /**
   * Pic-Operations（图片处理参数，Base64 编码的 JSON）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function picOperations($value = null)
  {
    return $this->item("Pic-Operations", func_get_args());
  }
}
