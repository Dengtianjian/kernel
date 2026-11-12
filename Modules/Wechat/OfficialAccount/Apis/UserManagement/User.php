<?php

namespace kernel\Modules\Wechat\OfficialAccount\Apis\UserManagement;

use kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 用户管理 - 用户信息
 *
 * 获取关注者列表、用户基本信息与设置用户备注等。
 *
 * 注意：列表/查询方法名刻意避开 get() 等父类 HTTP 方法名，
 * 以免覆盖 {@see WechatOfficialAccountApi::get()} 请求封装。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/Getting_a_User_List.html
 */
class User extends WechatOfficialAccountApi
{
  /**
   * 获取用户列表
   * 公众号可通过本接口来获取帐号的关注者列表，关注者列表由一串 OpenID（加密后的微信号，每个用户对每个公众号的 OpenID 是唯一的）组成。一次拉取调用最多拉取 10000 个关注者的 OpenID，可以通过多次拉取的方式来满足需求。
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/Getting_a_User_List.html
   *
   * @param string|null $nextOpenid 第一个拉取的 OPENID，不填默认从头开始拉取
   * @return array 微信响应数组，成功时含 total、count、data、next_openid
   */
  public function userList($nextOpenid = null)
  {
    return $this->get("cgi-bin/user/get", [
      "next_openid" => $nextOpenid
    ])->getData();
  }
  /**
   * 获取用户基本信息（单个）
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/Get_users_basic_information_UnionID.html
   *
   * @param string $openid 用户的 OpenID
   * @param string $lang 返回国家地区语言版本（zh_CN/zh_TW/en）
   * @return array 微信响应数组，成功时含 subscribe、openid、nickname、unionid 等
   */
  public function getInfo($openid, $lang = "zh_CN")
  {
    return $this->get("cgi-bin/user/info", [
      "openid" => $openid,
      "lang" => $lang
    ])->getData();
  }
  /**
   * 批量获取用户基本信息
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/Get_users_basic_information_UnionID.html
   *
   * @param array $openIds 用户 OpenID 列表（最多 100 个）
   * @param string $lang 返回国家地区语言版本（zh_CN/zh_TW/en）
   * @return array 微信响应数组，成功时含 user_info_list
   */
  public function batchGet(array $openIds, $lang = "zh_CN")
  {
    $userList = [];
    foreach (array_values($openIds) as $openid) {
      $userList[] = [
        "openid" => $openid,
        "lang" => $lang
      ];
    }

    return $this->post("cgi-bin/user/info/batchget", [
      "user_list" => $userList
    ])->getData();
  }
  /**
   * 设置用户备注名
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/Configuring_user_notes.html
   *
   * @param string $openid 用户 OpenID
   * @param string $remark 新的备注名，长度须小于 30 字符
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function updateRemark($openid, $remark)
  {
    return $this->post("cgi-bin/user/info/updateremark", [
      "openid" => $openid,
      "remark" => $remark
    ])->getData();
  }
}
