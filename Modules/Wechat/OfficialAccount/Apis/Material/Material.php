<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis\Material;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 素材管理（聚合类）
 *
 * 聚合微信「素材管理」分类下的接口，按子域拆分为多个子类：
 * - 临时素材：{@see TemporaryMaterial}（media_id 有效期 3 天）
 * - 永久素材：{@see PermanentMaterial}（长期保存，有数量上限）
 *
 * 使用示例：
 *
 * ```php
 * $oa->material()->temporary()->upload("image", "/path/a.jpg");
 * $oa->material()->permanent()->addNews($articles);
 * ```
 */
class Material extends WechatOfficialAccountApi
{
  /**
   * 临时素材相关接口
   *
   * @return TemporaryMaterial
   */
  public function temporary()
  {
    return new TemporaryMaterial($this->account);
  }
  /**
   * 永久素材相关接口
   *
   * @return PermanentMaterial
   */
  public function permanent()
  {
    return new PermanentMaterial($this->account);
  }
}
