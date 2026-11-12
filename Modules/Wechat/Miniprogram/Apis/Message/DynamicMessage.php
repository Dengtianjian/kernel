<?php

namespace kernel\Modules\Wechat\Miniprogram\Apis\Message;

use kernel\Modules\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 动态消息
 *
 * 对应微信「消息相关 → 动态消息」分类，用于发送会在一定时间内自动更新的消息
 * （如拼团、状态变更等场景）。
 */
class DynamicMessage extends WechatMiniProgramApi
{
  /**
   * 创建 activity_id（POST /cgi-bin/message/wxopen/activityid/create）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/updatable-message/api_createactivityid.html
   *
   * 动态消息的唯一标识，一条动态消息对应一个 activity_id。
   *
   * @param int|null $unionId 目标用户的 unionid（与 openid 二选一，优先 unionid）
   * @param string|null $openid 目标用户的 openid（与 unionid 二选一）
   * @return array 微信响应数组，成功时含 activity_id、expiration_time
   */
  function createActivityId($unionId = null, $openid = null)
  {
    $body = [];
    if ($unionId !== null) {
      $body['unionid'] = $unionId;
    }
    if ($openid !== null) {
      $body['openid'] = $openid;
    }

    return $this->post("cgi-bin/message/wxopen/activityid/create", $body)->getData();
  }
  /**
   * 修改动态消息（POST /cgi-bin/message/wxopen/updatablemsg/send）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/updatable-message/api_setupdatablemsg.html
   *
   * 更新动态消息的状态与内容，同一 activity_id 的消息在有效期内会实时更新。
   *
   * @param string $activityId 动态消息的 activity_id
   * @param int $targetState 动态消息状态：0 未开始，1 已开始，2 已结束
   * @param array $templateInfo 模板信息，形如 ["parameter_list" => [["name" => "member_count", "value" => "1"]]]
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function updateDynamicMessage($activityId, $targetState, $templateInfo = [])
  {
    return $this->post("cgi-bin/message/wxopen/updatablemsg/send", [
      "activity_id" => $activityId,
      "target_state" => $targetState,
      "template_info" => $templateInfo
    ])->getData();
  }
  /**
   * 修改小程序聊天工具的动态卡片消息（POST /cgi-bin/message/wxopen/chattoolmsg/send）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/updatable-message/api_setchattoolmsg.html
   *
   * @param array $data 请求体，字段参见官方文档（如 activity_id、target_state、template_info 等）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function updateChatToolMessage($data = [])
  {
    return $this->post("cgi-bin/message/wxopen/chattoolmsg/send", $data)->getData();
  }
}
