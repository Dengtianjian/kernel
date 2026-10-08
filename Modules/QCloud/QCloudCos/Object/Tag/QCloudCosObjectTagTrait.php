<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\Tag;

/**
 * 对象标签（Tagging）请求头 / 字段的 get/set 方法组
 *
 * 以 **trait** 形式提供，供各操作的「输入容器」复用（PHP 单继承 ⇒ 只能靠 trait 组合）。
 * 调用约定同 {@see \kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject}：
 * **不传值 = 读取**、**传值 = 设置**并返回 `$this`。
 *
 * ```php
 * $put->tag(["scene" => "avatar", "env" => "prod"]);   // → x-cos-tagging: scene=avatar&env=prod
 * $put->tagging();                                     // 读回原始值
 * ```
 *
 * 依赖：使用方必须能访问 `$this->item($name, $args)`（由
 * {@see \kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject} 提供）。
 *
 * 规则（官方）：最多 **10** 个标签；`x-cos-tagging` 的 Key 与 Value **必须先 URL 编码**
 * （{@see tag()} 会自动做）；`PUT Object - Copy` 里还有 `x-cos-tagging-directive`（`Copy` / `Replaced`），
 * 那个属于复制语义，不在本 trait 里。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\Tag
 */
trait QCloudCosObjectTagTrait
{
  /**
   * x-cos-tagging（对象标签集合，形如 `Key1=Value1&Key2=Value2`）
   *
   * 最多 10 个标签；Key 与 Value **必须 URL 编码**（用 {@see tag()} 可自动编码）。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function tagging($value = null)
  {
    return $this->item("x-cos-tagging", func_get_args());
  }

  /**
   * 便捷：设置对象标签（自动 URL 编码 Key/Value）
   *
   * @param array $tags 标签键值对（最多 10 个）
   * @return $this
   */
  public function tag(array $tags)
  {
    $pairs = [];
    foreach ($tags as $key => $value) {
      $pairs[] = rawurlencode($key) . "=" . rawurlencode($value);
    }

    return $this->tagging(implode("&", $pairs));
  }
}
