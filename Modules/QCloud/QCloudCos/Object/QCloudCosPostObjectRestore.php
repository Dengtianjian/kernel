<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

use kernel\Foundation\Data\Arr;

/**
 * POST Object restore（解冻归档/深度归档对象）的输入容器
 *
 * 该接口的输入**不是请求头而是请求体 XML**（`POST /<ObjectKey>?restore`），所以本类把
 * 「保留天数、解冻优先级」写成方法，并提供 {@see toXml()} 直接产出请求体。
 * 调用约定（不传=读取 / 传值=设置并返回 `$this`）见 {@see AbstractQCloudCosObject}。
 *
 * ```php
 * $restore = (new QCloudCosPostObjectRestore())
 *     ->days(7)
 *     ->tier(QCloudCosPostObjectRestore::TIER_EXPEDITED);
 *
 * $xml = $restore->toXml();
 * // <RestoreRequest><Days>7</Days><CASJobParameters><Tier>Expedited</Tier></CASJobParameters></RestoreRequest>
 * ```
 *
 * 要点：`?restore` 是**子资源** ⇒ 必须**同时**参与签名（`q-url-param-list=restore`）并出现在请求 URL 上；
 * 解冻是**异步**的，成功返回 **202**，要等回热完成后才能 GET 到内容；归档对象在回热前读取会失败。
 *
 * 文档核对状态：XML 结构（`RestoreRequest` / `Days` / `CASJobParameters` / `Tier`）依据官方写法整理，
 * **待与官方 POST Object restore 页面逐条核对**（尤其 `Tier` 的取值大小写：`Expedited`/`Standard`/`Bulk`）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosPostObjectRestore extends AbstractQCloudCosObject
{
  /** 解冻优先级：加急 */
  const TIER_EXPEDITED = "Expedited";
  /** 解冻优先级：标准 */
  const TIER_STANDARD = "Standard";
  /** 解冻优先级：批量 */
  const TIER_BULK = "Bulk";

  /**
   * Days（解冻后副本的保留天数）
   *
   * @param integer|null $value 不传=读取
   * @return mixed
   */
  public function days($value = null)
  {
    if (!func_get_args()) {
      return $this->item("Days", []);
    }

    return $this->item("Days", [(int) $value]);
  }

  /**
   * Tier（解冻优先级）
   *
   * 取值：{@see TIER_EXPEDITED} / {@see TIER_STANDARD} / {@see TIER_BULK}。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function tier($value = null)
  {
    return $this->item("Tier", func_get_args());
  }

  /**
   * 产出请求体 XML（`<RestoreRequest>…</RestoreRequest>`）
   *
   * 用 {@see Arr::toXML()} 拼（嵌套关联数组递归成子元素），字符串 `Tier` 逐值转义。
   * 未设置 `Days` 时返回空串（调用方应先设好）。
   *
   * @return string XML 请求体
   */
  public function toXml()
  {
    $days = $this->item("Days", []);
    if (!$days) return "";

    $tier = $this->item("Tier", []);
    if ($tier === null) $tier = self::TIER_STANDARD;

    return Arr::toXML([
      "Days" => (int) $days,
      "CASJobParameters" => ["Tier" => htmlspecialchars((string) $tier, ENT_QUOTES | ENT_XML1, "UTF-8")],
    ], true, "RestoreRequest", false);
  }
}
