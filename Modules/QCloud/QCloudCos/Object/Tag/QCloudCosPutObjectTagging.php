<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\Tag;

use kernel\Foundation\Data\Arr;
use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * PUT Object tagging（设置对象标签）的输入容器
 *
 * 请求：`PUT /<ObjectKey>?tagging`，输入是**请求体 XML**：
 * ```xml
 * <Tagging><TagSet>
 *   <Tag><Key>scene</Key><Value>avatar</Value></Tag>
 *   <Tag><Key>env</Key><Value>prod</Value></Tag>
 * </TagSet></Tagging>
 * ```
 *
 * ```php
 * $xml = (new QCloudCosPutObjectTagging())
 *     ->tags(["scene" => "avatar", "env" => "prod"])
 *     ->toXml();
 * ```
 *
 * 要点：最多 **10** 个标签；`?tagging` 是**子资源** ⇒ 必须同时参与签名与出现在请求 URL 上；
 * 覆盖语义（整份 TagSet 替换原标签）。**注意区分**：走请求头设置标签用的是 `x-cos-tagging`
 * （见 {@see QCloudCosObjectTagTrait::tagging()}，主要用于上传时顺带打标），
 * 那是"上传参数"，不是本接口的 TagSet 语义。
 *
 * 文档核对状态：**待核对**（XML 结构依据官方写法整理，尚未逐条对官方 PUT Object tagging 页面；
 * 尤其"<Key>/<Value> 是否需要 URL 编码"—— 请求头那侧官方明确要求编码，请求体这侧待确认）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\Tag
 */
class QCloudCosPutObjectTagging extends AbstractQCloudCosObject
{
  /** 单个对象允许的最大标签数（官方） */
  const MAX_TAGS = 10;

  /**
   * 标签集合（**必选**：至少 1 个，最多 {@see MAX_TAGS} 个）
   *
   * @param array|null $tags 键值对；不传=读取
   * @return mixed
   */
  public function tags($tags = null)
  {
    if (!func_get_args()) {
      return $this->item("Tags", []);
    }

    return $this->item("Tags", [$tags]);
  }

  /**
   * 设置单个标签（可链式调用多次）
   *
   * @param string $key 标签键
   * @param string $value 标签值
   * @return $this
   */
  public function tag($key, $value)
  {
    $tags = $this->item("Tags", []);
    $tags = is_array($tags) ? $tags : [];
    $tags[$key] = $value;

    return $this->item("Tags", [$tags]);
  }

  /**
   * 产出请求体 XML（`<Tagging><TagSet>…</TagSet></Tagging>`）
   *
   * 用 {@see Arr::toXML()} 拼：`Tag` 是列表 ⇒ 展开成重复的 `<Tag>` 子元素；字符串逐值转义。
   * 未设置标签时返回空串。
   *
   * @return string XML 请求体
   */
  public function toXml()
  {
    $tags = $this->item("Tags", []);
    if (!$tags) return "";

    $items = [];
    foreach ($tags as $key => $value) {
      $items[] = [
        "Key" => htmlspecialchars((string) $key, ENT_QUOTES | ENT_XML1, "UTF-8"),
        "Value" => htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, "UTF-8"),
      ];
    }

    return Arr::toXML(["TagSet" => ["Tag" => $items]], true, "Tagging", false);
  }
}
