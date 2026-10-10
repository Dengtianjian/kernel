<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis\UserManagement;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 用户管理 - 用户标签
 *
 * 公众号可对关注者打标签，并基于标签进行群发、菜单个性化展示等。
 *
 * 注意：列表/删除方法名刻意避开 get()/delete() 等父类 HTTP 方法名，
 * 以免覆盖 {@see WechatOfficialAccountApi::get()} 等请求封装。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/User_Tag_Management.html
 */
class Tag extends WechatOfficialAccountApi
{
  /**
   * 创建标签
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/User_Tag_Management.html
   *
   * @param string $name 标签名（30 个字符以内）
   * @return array 微信响应数组，成功时含 tag（id、name）
   */
  public function create($name)
  {
    return $this->post("cgi-bin/tags/create", [
      "tag" => [
        "name" => $name
      ]
    ])->getData();
  }
  /**
   * 获取公众号已创建的标签
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/User_Tag_Management.html
   *
   * @return array 微信响应数组，成功时含 tags
   */
  public function getList()
  {
    return $this->get("cgi-bin/tags/get")->getData();
  }
  /**
   * 编辑标签
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/User_Tag_Management.html
   *
   * @param int $id 标签 ID
   * @param string $name 新的标签名
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function update($id, $name)
  {
    return $this->post("cgi-bin/tags/update", [
      "tag" => [
        "id" => $id,
        "name" => $name
      ]
    ])->getData();
  }
  /**
   * 删除标签
   *
   * 当某个标签下的粉丝超过 10 万时不可删除；标签删除后，粉丝与标签的关系也同时解除。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/User_Tag_Management.html
   *
   * @param int $id 标签 ID
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function deleteTag($id)
  {
    return $this->post("cgi-bin/tags/delete", [
      "tag" => [
        "id" => $id
      ]
    ])->getData();
  }
  /**
   * 获取标签下粉丝列表
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/User_Tag_Management.html
   *
   * @param int $tagId 标签 ID
   * @param string|null $nextOpenid 第一个拉取的 OPENID，不填默认从头开始拉取
   * @return array 微信响应数组，成功时含 count、data、next_openid
   */
  public function getUsers($tagId, $nextOpenid = null)
  {
    $body = [
      "tagid" => $tagId
    ];
    if ($nextOpenid !== null) {
      $body['next_openid'] = $nextOpenid;
    }

    return $this->post("cgi-bin/user/tag/get", $body)->getData();
  }
  /**
   * 批量为用户打标签
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/User_Tag_Management.html
   *
   * @param int $tagId 标签 ID
   * @param array $openIds 用户 OpenID 列表（每次最多 50 个）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function batchTagging($tagId, array $openIds)
  {
    return $this->post("cgi-bin/tags/members/batchtagging", [
      "tagid" => $tagId,
      "openid_list" => array_values($openIds)
    ])->getData();
  }
  /**
   * 批量取消用户标签
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/User_Tag_Management.html
   *
   * @param int $tagId 标签 ID
   * @param array $openIds 用户 OpenID 列表
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function batchUntagging($tagId, array $openIds)
  {
    return $this->post("cgi-bin/tags/members/batchuntagging", [
      "tagid" => $tagId,
      "openid_list" => array_values($openIds)
    ])->getData();
  }
  /**
   * 获取用户身上的标签列表
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/User_Tag_Management.html
   *
   * @param string $openid 用户 OpenID
   * @return array 微信响应数组，成功时含 tagid_list
   */
  public function getUserTags($openid)
  {
    return $this->post("cgi-bin/tags/getidlist", [
      "openid" => $openid
    ])->getData();
  }
}
