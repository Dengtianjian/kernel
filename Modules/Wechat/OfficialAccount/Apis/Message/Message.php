<?php

namespace kernel\Modules\Wechat\OfficialAccount\Apis\Message;

use kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 基础消息（聚合类）
 *
 * 聚合微信「消息管理」分类下的接口，按子域拆分为多个子类：
 * - 客服消息：{@see CustomMessage}
 * - 群发消息：{@see MassMessage}
 * - 模板消息：{@see TemplateMessage}
 * - 一次性订阅消息：{@see SubscribeMessage}
 *
 * 使用示例：
 *
 * ```php
 * $oa->message()->custom()->send($openid, "text", ["content" => "你好"]);
 * $oa->message()->mass()->sendAll("text", ["content" => "hello"]);
 * $oa->message()->template()->send($openid, $templateId, $data);
 * ```
 */
class Message extends WechatOfficialAccountApi
{
  /**
   * 客服消息相关接口
   *
   * @return CustomMessage
   */
  public function custom()
  {
    return new CustomMessage($this->account);
  }
  /**
   * 群发消息相关接口
   *
   * @return MassMessage
   */
  public function mass()
  {
    return new MassMessage($this->account);
  }
  /**
   * 模板消息相关接口
   *
   * @return TemplateMessage
   */
  public function template()
  {
    return new TemplateMessage($this->account);
  }
  /**
   * 一次性订阅消息相关接口
   *
   * @return SubscribeMessage
   */
  public function subscribe()
  {
    return new SubscribeMessage($this->account);
  }
}
