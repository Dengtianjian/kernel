<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 留言管理
 *
 * 对已群发（或已发布）图文消息的留言进行开关、查看、精选、删除与回复等操作。
 * 相关接口均需提供图文的 msg_data_id 与文章序号 index（从 1 开始）。
 *
 * 注意：查询/删除方法名刻意避开 get()/delete() 等父类 HTTP 方法名，
 * 以免覆盖 {@see WechatOfficialAccountApi::get()} 等请求封装。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Comments_management/Image_Comments_Management_Interface.html
 */
class Comment extends WechatOfficialAccountApi
{
  /**
   * 打开已群发文章评论
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Comments_management/Image_Comments_Management_Interface.html
   *
   * @param string $msgDataId 群发返回的 msg_data_id
   * @param int $index 图文消息中文章的序号（从 1 开始；多图文时用于定位文章）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function open($msgDataId, $index = 0)
  {
    return $this->post("cgi-bin/comment/open", [
      "msg_data_id" => $msgDataId,
      "index" => $index
    ])->getData();
  }
  /**
   * 关闭已群发文章评论
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Comments_management/Image_Comments_Management_Interface.html
   *
   * @param string $msgDataId 群发返回的 msg_data_id
   * @param int $index 图文消息中文章的序号（从 1 开始）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function close($msgDataId, $index = 0)
  {
    return $this->post("cgi-bin/comment/close", [
      "msg_data_id" => $msgDataId,
      "index" => $index
    ])->getData();
  }
  /**
   * 查看指定文章的评论数据
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Comments_management/Image_Comments_Management_Interface.html
   *
   * @param string $msgDataId 群发返回的 msg_data_id
   * @param int $index 图文消息中文章的序号（从 1 开始）
   * @param int $begin 起始位置（从 0 开始）
   * @param int $count 拉取数目，最大 50
   * @param int $type 评论类型：0 普通评论，1 精选评论
   * @return array 微信响应数组，成功时含 total、comment
   */
  public function getList($msgDataId, $index = 0, $begin = 0, $count = 50, $type = 0)
  {
    return $this->post("cgi-bin/comment/list", [
      "msg_data_id" => $msgDataId,
      "index" => $index,
      "begin" => $begin,
      "count" => $count,
      "type" => $type
    ])->getData();
  }
  /**
   * 将评论标记精选
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Comments_management/Image_Comments_Management_Interface.html
   *
   * @param string $msgDataId 群发返回的 msg_data_id
   * @param int $index 图文消息中文章的序号（从 1 开始）
   * @param int|string $userCommentId 用户评论 ID
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function markElect($msgDataId, $index, $userCommentId)
  {
    return $this->post("cgi-bin/comment/markelect", [
      "msg_data_id" => $msgDataId,
      "index" => $index,
      "user_comment_id" => $userCommentId
    ])->getData();
  }
  /**
   * 取消评论精选
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Comments_management/Image_Comments_Management_Interface.html
   *
   * @param string $msgDataId 群发返回的 msg_data_id
   * @param int $index 图文消息中文章的序号（从 1 开始）
   * @param int|string $userCommentId 用户评论 ID
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function unmarkElect($msgDataId, $index, $userCommentId)
  {
    return $this->post("cgi-bin/comment/unmarkelect", [
      "msg_data_id" => $msgDataId,
      "index" => $index,
      "user_comment_id" => $userCommentId
    ])->getData();
  }
  /**
   * 删除评论
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Comments_management/Image_Comments_Management_Interface.html
   *
   * @param string $msgDataId 群发返回的 msg_data_id
   * @param int $index 图文消息中文章的序号（从 1 开始）
   * @param int|string $userCommentId 用户评论 ID
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function deleteComment($msgDataId, $index, $userCommentId)
  {
    return $this->post("cgi-bin/comment/delete", [
      "msg_data_id" => $msgDataId,
      "index" => $index,
      "user_comment_id" => $userCommentId
    ])->getData();
  }
  /**
   * 回复评论
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Comments_management/Image_Comments_Management_Interface.html
   *
   * @param string $msgDataId 群发返回的 msg_data_id
   * @param int $index 图文消息中文章的序号（从 1 开始）
   * @param int|string $userCommentId 用户评论 ID
   * @param string $content 回复内容
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function reply($msgDataId, $index, $userCommentId, $content)
  {
    return $this->post("cgi-bin/comment/reply/add", [
      "msg_data_id" => $msgDataId,
      "index" => $index,
      "user_comment_id" => $userCommentId,
      "content" => $content
    ])->getData();
  }
  /**
   * 删除回复
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Comments_management/Image_Comments_Management_Interface.html
   *
   * @param string $msgDataId 群发返回的 msg_data_id
   * @param int $index 图文消息中文章的序号（从 1 开始）
   * @param int|string $userCommentId 用户评论 ID
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function deleteReply($msgDataId, $index, $userCommentId)
  {
    return $this->post("cgi-bin/comment/reply/delete", [
      "msg_data_id" => $msgDataId,
      "index" => $index,
      "user_comment_id" => $userCommentId
    ])->getData();
  }
}
