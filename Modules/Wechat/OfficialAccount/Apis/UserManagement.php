<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 用户管理
 *
 * 微信公众号用户管理相关接口。
 */
class UserManagement extends WechatOfficialAccountApi
{
  /**
   * 获取用户列表
   * 公众号可通过本接口来获取帐号的关注者列表，关注者列表由一串OpenID（加密后的微信号，每个用户对每个公众号的 OpenID 是唯一的）组成。一次拉取调用最多拉取10000个关注者的OpenID，可以通过多次拉取的方式来满足需求。
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/Getting_a_User_List.html
   *
   * @param string $next_openid 第一个拉取的OPENID，不填默认从头开始拉取
   * @return array 微信响应数组，成功时含 total、count、data、next_openid
   */
  public function userList($next_openid = null)
  {
    return $this->get("cgi-bin/user/get", [
      "next_openid" => $next_openid
    ])->getData();
  }
}
