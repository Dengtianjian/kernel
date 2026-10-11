<?php

namespace kernel\Modules\Wechat\OfficialAccount\Apis\UserManagement;

use kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 用户管理（聚合类）
 *
 * 聚合微信「用户管理」分类下的接口，按子域拆分为多个子类：
 * - 用户信息：{@see User}（关注者列表、用户基本信息、备注）
 * - 用户标签：{@see Tag}（标签增删改查、批量打标签）
 * - 黑名单：{@see Blacklist}
 *
 * 使用示例：
 *
 * ```php
 * $oa->userManagement()->user()->userList();
 * $oa->userManagement()->tag()->getUsers($tagId);
 * $oa->userManagement()->blacklist()->batchAdd([$openid]);
 * ```
 */
class UserManagement extends WechatOfficialAccountApi
{
  /**
   * 用户信息相关接口
   *
   * @return User
   */
  public function user()
  {
    return new User($this->account);
  }
  /**
   * 用户标签相关接口
   *
   * @return Tag
   */
  public function tag()
  {
    return new Tag($this->account);
  }
  /**
   * 黑名单相关接口
   *
   * @return Blacklist
   */
  public function blacklist()
  {
    return new Blacklist($this->account);
  }
}
