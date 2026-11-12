<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

use kernel\Foundation\Data\Arr;

/**
 * DELETE Multiple Objects（批量删除）的输入容器
 *
 * 该接口的输入**主要不是请求头，而是请求体 XML**（`POST /?delete`），所以本类把「要删哪些对象、
 * 是否静默、每个对象的版本」写成方法，并提供一个 {@see toXml()} 直接产出请求体。
 * 调用约定（不传=读取 / 传值=设置并返回 `$this`）见 {@see AbstractQCloudCosObject}。
 *
 * ```php
 * $delete = (new QCloudCosDeleteMultipleObjects())
 *     ->keys(["images/a.png", "images/b.png"])
 *     ->quiet(true);
 *
 * $xml = $delete->toXml();
 * // <Delete><Quiet>true</Quiet><Object><Key>images/a.png</Key></Object><Object><Key>images/b.png</Key></Object></Delete>
 * ```
 *
 * 要点：`?delete` 是**子资源** ⇒ 它必须**同时**参与签名（`q-url-param-list=delete`）并出现在请求 URL 上；
 * 一次最多 **1000** 个对象；成功返回 200，响应体是 `<DeleteResult>` XML（`Quiet` 为 true 时只回失败项）。
 *
 * ⚠️ 文档核对状态：**待核对**。两处尤其要核：① 每个对象是否支持 `<VersionId>`（本类已按支持实现）；
 * ② 请求体是否**必须**带 `Content-MD5`（若必须，调用方需用 {@see contentMD5()} 设上）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosDeleteMultipleObjects extends AbstractQCloudCosObject
{
  /**
   * Quiet（静默模式：只返回删除失败的对象）
   *
   * @param boolean|null $value 不传=读取（返回布尔或 null）
   * @return mixed
   */
  public function quiet($value = null)
  {
    if (!func_get_args()) {
      $current = $this->item("Quiet", []);

      return $current === null ? null : ($current === "true");
    }

    return $this->item("Quiet", [$value ? "true" : "false"]);
  }

  /**
   * 待删除的对象键列表（**必选**）
   *
   * @param array|null $keys 对象键数组；不传=读取
   * @return mixed
   */
  public function keys($keys = null)
  {
    if (!func_get_args()) {
      return $this->item("Keys", []);
    }

    return $this->item("Keys", [array_values($keys)]);
  }

  /**
   * 指定某个待删对象的版本（与 {@see keys()} 配套，键 => 版本 ID 的映射）
   *
   * ⚠️ 待核对：`<Object>` 内是否支持 `<VersionId>`。
   *
   * @param array|null $versions 对象键 => 版本 ID；不传=读取
   * @return mixed
   */
  public function versions($versions = null)
  {
    if (!func_get_args()) {
      return $this->item("Versions", []);
    }

    return $this->item("Versions", [array_values($versions)]);
  }

  /**
   * Content-MD5（请求体 MD5，Base64）
   *
   * ⚠️ 待核对是否必填；若调用方自行产出 XML，可用 `base64_encode(md5($xml, true))` 计算。
   *
   * @param string|null $value Base64 形式；不传=读取
   * @return mixed
   */
  public function contentMD5($value = null)
  {
    return $this->item("Content-MD5", func_get_args());
  }

  /**
   * 产出请求体 XML（`<Delete>…</Delete>`）
   *
   * 用 {@see Arr::toXML()} 拼：`Keys` 是列表 ⇒ 会展开成**重复的同名子元素**
   * （`<Object><Key>…</Key></Object>`）；字符串由本方法逐值转义（`$contentWrap = false`）。
   *
   * @return string XML 请求体；未设置 keys 时返回空串（调用方应先校验）
   */
  public function toXml()
  {
    $keys = $this->item("Keys", []);
    if (!$keys) return "";

    $versions = $this->item("Versions", []);
    $objects = [];
    foreach ($keys as $index => $key) {
      $object = ["Key" => htmlspecialchars((string) $key, ENT_QUOTES | ENT_XML1, "UTF-8")];
      if (isset($versions[$index]) && $versions[$index] !== null && $versions[$index] !== "") {
        $object["VersionId"] = htmlspecialchars((string) $versions[$index], ENT_QUOTES | ENT_XML1, "UTF-8");
      }
      $objects[] = $object;
    }

    $body = [];
    if ($this->item("Quiet", []) === "true") $body["Quiet"] = "true";
    $body["Object"] = $objects;

    return Arr::toXML($body, true, "Delete", false);
  }
}
