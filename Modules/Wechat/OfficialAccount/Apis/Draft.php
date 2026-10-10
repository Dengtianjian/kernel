<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 草稿管理
 *
 * 草稿箱用于暂存图文内容（newspublish 接口已下线，图文统一走草稿箱 + 发布能力流程）。
 *
 * 注意：查询/删除方法名刻意避开 get()/delete() 等父类 HTTP 方法名，
 * 以免覆盖 {@see WechatOfficialAccountApi::get()} 等请求封装。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Draft_Box/Add_draft.html
 */
class Draft extends WechatOfficialAccountApi
{
  /**
   * 新增草稿
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Draft_Box/Add_draft.html
   *
   * @param array $articles 图文数组，每项含 title、author、digest、content、content_source_url、
   *                        thumb_media_id、need_open_comment、only_fans_can_comment 等
   * @return array 微信响应数组，成功时含 media_id
   */
  public function add(array $articles)
  {
    return $this->post("cgi-bin/draft/add", [
      "articles" => array_values($articles)
    ])->getData();
  }
  /**
   * 获取草稿
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Draft_Box/Get_draft.html
   *
   * @param string $mediaId 草稿的 media_id
   * @return array 微信响应数组，成功时含 news_item
   */
  public function getDraft($mediaId)
  {
    return $this->post("cgi-bin/draft/get", [
      "media_id" => $mediaId
    ])->getData();
  }
  /**
   * 修改草稿
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Draft_Box/Update_draft.html
   *
   * @param string $mediaId 草稿的 media_id
   * @param int $index 要更新的文章在草稿中的位置（从 0 开始）
   * @param array $articles 更新后的图文数组
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function update($mediaId, $index, array $articles)
  {
    return $this->post("cgi-bin/draft/update", [
      "media_id" => $mediaId,
      "index" => $index,
      "articles" => array_values($articles)
    ])->getData();
  }
  /**
   * 删除草稿
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Draft_Box/Delete_draft.html
   *
   * @param string $mediaId 草稿的 media_id
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function deleteDraft($mediaId)
  {
    return $this->post("cgi-bin/draft/delete", [
      "media_id" => $mediaId
    ])->getData();
  }
  /**
   * 获取草稿列表
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Draft_Box/Get_draft_list.html
   *
   * @param int $offset 从该偏移位置开始返回（0 表示从第一份草稿开始）
   * @param int $count 返回草稿的数量，取值 1-20
   * @param int $noContent 是否返回草稿内容：0 返回（默认），1 不返回
   * @return array 微信响应数组，成功时含 total_count、item_count、item
   */
  public function batchGet($offset = 0, $count = 20, $noContent = 0)
  {
    return $this->post("cgi-bin/draft/batchget", [
      "offset" => $offset,
      "count" => $count,
      "no_content" => $noContent
    ])->getData();
  }
  /**
   * 获取草稿总数
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Draft_Box/Get_draft_count.html
   *
   * @return array 微信响应数组，成功时含 total_count
   */
  public function getCount()
  {
    return $this->get("cgi-bin/draft/count")->getData();
  }
}
