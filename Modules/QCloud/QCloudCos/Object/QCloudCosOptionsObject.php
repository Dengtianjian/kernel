<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

/**
 * OPTIONS Object（CORS 预检）的输入容器
 *
 * 把预检请求要带的三个头写成方法（调用约定见 {@see AbstractQCloudCosObject}）：
 * `Origin` / `Access-Control-Request-Method` / `Access-Control-Request-Headers`。
 *
 * ```php
 * $options = (new QCloudCosOptionsObject())
 *     ->origin("https://example.com")
 *     ->accessControlRequestMethod("PUT")
 *     ->requestHeaders(["content-type", "x-cos-acl"]);
 * ```
 *
 * 结果（**响应**头，不是本类的输入）：`Access-Control-Allow-Origin` / `-Methods` / `-Headers` /
 * `-Expose-Headers` / `-Max-Age`。**没有这些响应头 ⇒ 桶未配置对应的跨域规则**（预检失败多半是桶规则问题）。
 *
 * 文档核对状态：本类的三个头即 COS `OPTIONS Object` 的标准 CORS 预检头，与仓内既有实现一致
 * （见 {@see \kernel\Modules\DiscuzX\Foundation\Storage\QCloud\DiscuzXQCloudCOS::optionsObject()}）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosOptionsObject extends AbstractQCloudCosObject
{
  /**
   * Origin（**必选**：预检来源）
   *
   * 如 `https://example.com`；必须是完整来源（scheme + host + 可选端口），不能带路径。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function origin($value = null)
  {
    return $this->item("Origin", func_get_args());
  }

  /**
   * Access-Control-Request-Method（**必选**：预检的目标方法）
   *
   * 如 `GET` / `PUT` / `POST`（前端直传场景通常是 PUT 或 POST）。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function accessControlRequestMethod($value = null)
  {
    return $this->item("Access-Control-Request-Method", func_get_args());
  }

  /**
   * Access-Control-Request-Headers（预检的请求头名列表）
   *
   * 值形如 `content-type, x-cos-acl`；用 {@see requestHeaders()} 可自动拼。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function accessControlRequestHeaders($value = null)
  {
    return $this->item("Access-Control-Request-Headers", func_get_args());
  }

  /**
   * 便捷：传入头名数组，自动拼成逗号分隔（并统一小写）
   *
   * @param array $headerNames 头名数组，如 ["content-type", "x-cos-acl"]
   * @return $this
   */
  public function requestHeaders(array $headerNames)
  {
    return $this->accessControlRequestHeaders(implode(", ", array_map("strtolower", $headerNames)));
  }
}
