<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis\Message;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 消息相关（聚合类）
 *
 * 聚合微信「消息相关」分类下的接口：
 * - 动态消息：{@see DynamicMessage}
 * - 订阅消息：{@see SubscribeMessage}
 *
 * 使用示例：
 *
 * ```php
 * $mp->message()->subscribe()->send($openid, $templateId, $data);
 * $mp->message()->dynamicMessage()->createActivityId(null, $openid);
 * ```
 */
class Message extends WechatMiniProgramApi
{
  /**
   * 动态消息相关接口
   *
   * @return DynamicMessage
   */
  public function dynamicMessage()
  {
    return new DynamicMessage($this->account);
  }
  /**
   * 订阅消息相关接口
   *
   * @return SubscribeMessage
   */
  public function subscribe()
  {
    return new SubscribeMessage($this->account);
  }
}
