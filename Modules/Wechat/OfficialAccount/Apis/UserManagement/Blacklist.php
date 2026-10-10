<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis\UserManagement;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 用户管理 - 黑名单
 *
 * 拉黑后用户无法接收公众号的群发与模板消息；每个公众号最多拉黑 100000 个用户。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/Manage_blacklist.html
 */
class Blacklist extends WechatOfficialAccountApi
{
  /**
   * 获取黑名单列表
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/Manage_blacklist.html
   *
   * @param string|null $beginOpenid 起始 OpenID（分页），不填默认从头开始
   * @return array 微信响应数组，成功时含 total、count、data、next_openid
   */
  public function getList($beginOpenid = null)
  {
    $body = [];
    if ($beginOpenid !== null) {
      $body['begin_openid'] = $beginOpenid;
    }

    return $this->post("cgi-bin/tags/members/getblacklist", $body)->getData();
  }
  /**
   * 批量拉黑用户
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/Manage_blacklist.html
   *
   * @param array $openIds 用户 OpenID 列表（每次最多 20 个）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function batchAdd(array $openIds)
  {
    return $this->post("cgi-bin/tags/members/batchblacklist", [
      "openid_list" => array_values($openIds)
    ])->getData();
  }
  /**
   * 批量取消拉黑用户
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/Manage_blacklist.html
   *
   * @param array $openIds 用户 OpenID 列表（每次最多 20 个）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function batchRemove(array $openIds)
  {
    return $this->post("cgi-bin/tags/members/batchunblacklist", [
      "openid_list" => array_values($openIds)
    ])->getData();
  }
}
