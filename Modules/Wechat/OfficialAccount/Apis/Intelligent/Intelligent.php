<?php

namespace kernel\Modules\Wechat\OfficialAccount\Apis\Intelligent;

use kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 智能接口（聚合类）
 *
 * 聚合微信「智能接口」分类下的接口，按子域拆分为多个子类：
 * - 语义理解：{@see Semantic}
 * - 智能语音（语音转文字、文本翻译）：{@see Voice}
 *
 * 使用示例：
 *
 * ```php
 * $oa->intelligent()->semantic()->search("查一下明天从北京到上海的机票", "flight");
 * $oa->intelligent()->voice()->queryRecoResultForText($voiceId);
 * ```
 */
class Intelligent extends WechatOfficialAccountApi
{
  /**
   * 语义理解相关接口
   *
   * @return Semantic
   */
  public function semantic()
  {
    return new Semantic($this->account);
  }
  /**
   * 智能语音相关接口（语音转文字、文本翻译）
   *
   * @return Voice
   */
  public function voice()
  {
    return new Voice($this->account);
  }
}
