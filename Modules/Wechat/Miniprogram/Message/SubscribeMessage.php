<?php

namespace kernel\Platform\Wechat\Miniprogram\Message;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 订阅消息
 *
 * 对应微信「消息相关 → 订阅消息」分类：包含模板管理与发送订阅消息。
 * 模板管理类接口需要小程序账号具备相应权限（一般通过第三方平台代调用）。
 */
class SubscribeMessage extends WechatMiniProgramApi
{
  /**
   * 发送订阅消息（POST /cgi-bin/message/subscribe/send）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/subscribe-message/api_sendmessage.html
   *
   * @param string $touser 接收者（用户）的 openid
   * @param string $templateId 所需下发的订阅模板 id
   * @param array $data 模板内容，形如 ["thing1" => ["value" => "内容"], "time2" => ["value" => "2026-01-01 00:00:00"]]
   * @param string $page 点击模板卡片后的跳转页面（可带参数，仅限本小程序内的页面）
   * @param string $miniprogramState 跳转小程序类型：developer（开发版）/ trial（体验版）/ formal（正式版）
   * @param string $lang 进入小程序查看的语言类型：zh_CN / en_US / zh_HK / zh_TW
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function send($touser, $templateId, $data = [], $page = null, $miniprogramState = "formal", $lang = "zh_CN")
  {
    $body = [
      "touser" => $touser,
      "template_id" => $templateId,
      "data" => $data,
      "miniprogram_state" => $miniprogramState,
      "lang" => $lang
    ];
    if ($page !== null) {
      $body['page'] = $page;
    }

    return $this->post("cgi-bin/message/subscribe/send", $body)->getData();
  }
  /**
   * 获取已有模板列表（GET /wxaapi/newtmpl/gettemplate）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/subscribe-message/api_getwxapubnewtemplate.html
   *
   * @return array 微信响应数组，成功时含 data（模板列表）
   */
  function getTemplateList()
  {
    return $this->get("wxaapi/newtmpl/gettemplate")->getData();
  }
  /**
   * 获取类目（GET /wxaapi/newtmpl/getcategory）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/subscribe-message/api_getcategory.html
   *
   * @return array 微信响应数组，成功时含 data（类目列表）
   */
  function getCategory()
  {
    return $this->get("wxaapi/newtmpl/getcategory")->getData();
  }
  /**
   * 获取类目下的公共模板（GET /wxaapi/newtmpl/getpubtemplatetitles）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/subscribe-message/api_getpubnewtemplatetitles.html
   *
   * @param string $ids 类目 id，多个用逗号分隔
   * @param int $start 用于分页，表示拉取第几页，从 0 开始
   * @param int $limit 每页记录数，最大 30
   * @return array 微信响应数组，成功时含 data（公共模板列表）
   */
  function getPubTemplateTitles($ids, $start = 0, $limit = 30)
  {
    return $this->get("wxaapi/newtmpl/getpubtemplatetitles", [
      "ids" => $ids,
      "start" => $start,
      "limit" => $limit
    ])->getData();
  }
  /**
   * 获取模板中的关键词（GET /wxaapi/newtmpl/getpubtemplatekeywords）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/subscribe-message/api_getpubnewtemplatekeywords.html
   *
   * @param string $tid 模板标题 id
   * @return array 微信响应数组，成功时含 data（关键词列表）
   */
  function getPubTemplateKeywords($tid)
  {
    return $this->get("wxaapi/newtmpl/getpubtemplatekeywords", [
      "tid" => $tid
    ])->getData();
  }
  /**
   * 选用模板（POST /wxaapi/newtmpl/addtemplate）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/subscribe-message/api_addwxanewtemplate.html
   *
   * @param string $tid 模板标题 id
   * @param array $kidList 开发者自行组合好的模板关键词列表，关键词顺序可以自由搭配
   * @param string $sceneDesc 服务场景描述，15 个字以内
   * @return array 微信响应数组，成功时含 priTmplId（添加至帐号下的模板 id）
   */
  function addTemplate($tid, $kidList, $sceneDesc = null)
  {
    $body = [
      "tid" => $tid,
      "kidList" => $kidList
    ];
    if ($sceneDesc !== null) {
      $body['sceneDesc'] = $sceneDesc;
    }

    return $this->post("wxaapi/newtmpl/addtemplate", $body)->getData();
  }
  /**
   * 删除模板（POST /wxaapi/newtmpl/deltemplate）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/subscribe-message/api_delwxanewtemplate.html
   *
   * @param string $priTmplId 要删除的模板 id
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function deleteTemplate($priTmplId)
  {
    return $this->post("wxaapi/newtmpl/deltemplate", [
      "priTmplId" => $priTmplId
    ])->getData();
  }
  /**
   * 激活与更新服务卡片（POST /wxa/set_user_notify）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/subscribe-message/api_setusernotify.html
   *
   * @param string $openid 用户 openid
   * @param string $categoryId 服务卡片类目 id
   * @param int $type 操作类型（以官方文档为准）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function setUserNotify($openid, $categoryId, $type = 1)
  {
    return $this->post("wxa/set_user_notify", [
      "openid" => $openid,
      "categoryId" => $categoryId,
      "type" => $type
    ])->getData();
  }
  /**
   * 更新服务卡片扩展信息（POST /wxa/set_user_notifyext）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/subscribe-message/api_setusernotifyext.html
   *
   * @param string $openid 用户 openid
   * @param string $categoryId 服务卡片类目 id
   * @param array $extInfo 扩展信息（以官方文档为准）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function setUserNotifyExt($openid, $categoryId, $extInfo = [])
  {
    return $this->post("wxa/set_user_notifyext", [
      "openid" => $openid,
      "categoryId" => $categoryId,
      "extInfo" => $extInfo
    ])->getData();
  }
  /**
   * 查询服务卡片状态（POST /wxa/get_user_notify）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/mp-message-management/subscribe-message/api_getusernotify.html
   *
   * @param string $openid 用户 openid
   * @param string $categoryId 服务卡片类目 id
   * @return array 微信响应数组，成功时含服务卡片状态信息
   */
  function getUserNotify($openid, $categoryId)
  {
    return $this->post("wxa/get_user_notify", [
      "openid" => $openid,
      "categoryId" => $categoryId
    ])->getData();
  }
}
