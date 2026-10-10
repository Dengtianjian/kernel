<?php

namespace kernel\Platform\Wechat\OfficialAccount\Apis;

use kernel\Platform\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 发布能力
 *
 * 将草稿箱中的图文发布到公众号（发布不等于群发，粉丝不会收到推送消息，
 * 但会出现在公众号历史消息中，并占用发布次数）。
 *
 * 注意：查询/删除方法名刻意避开 get()/delete() 等父类 HTTP 方法名，
 * 以免覆盖 {@see WechatOfficialAccountApi::get()} 等请求封装。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Publish/Publish.html
 */
class Publish extends WechatOfficialAccountApi
{
  /**
   * 发布草稿
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Publish/Publish.html
   *
   * @param string $mediaId 草稿的 media_id
   * @return array 微信响应数组，成功时含 publish_id
   */
  public function submit($mediaId)
  {
    return $this->post("cgi-bin/freepublish/submit", [
      "media_id" => $mediaId
    ])->getData();
  }
  /**
   * 查询发布状态
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Publish/Get_status.html
   *
   * @param string $publishId 发布任务 ID（publish_id）
   * @return array 微信响应数组，成功时含 publish_id、publish_status、article_id、article_detail
   */
  public function getStatus($publishId)
  {
    return $this->post("cgi-bin/freepublish/get", [
      "publish_id" => $publishId
    ])->getData();
  }
  /**
   * 删除发布
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Publish/Delete.html
   *
   * @param string $articleId 发布成功后返回的 article_id
   * @param int|null $index 要删除的文章序号（从 1 开始，多图文时指定；不传表示删除全部）
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  public function deletePublish($articleId, $index = null)
  {
    $body = [
      "article_id" => $articleId
    ];
    if ($index !== null) {
      $body['index'] = $index;
    }

    return $this->post("cgi-bin/freepublish/delete", $body)->getData();
  }
  /**
   * 获取已发布的图文详情
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Publish/Get_article_from_id.html
   *
   * @param string $articleId 发布成功后返回的 article_id
   * @return array 微信响应数组，成功时含 news_item
   */
  public function getArticle($articleId)
  {
    return $this->post("cgi-bin/freepublish/getarticle", [
      "article_id" => $articleId
    ])->getData();
  }
  /**
   * 获取已发布图文列表
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Publish/Get_publication_records.html
   *
   * @param int $offset 从该偏移位置开始返回（0 表示从第一条记录开始）
   * @param int $count 返回条数，取值 1-20
   * @param int $noContent 是否返回图文内容：0 返回（默认），1 不返回
   * @return array 微信响应数组，成功时含 total_count、item_count、item
   */
  public function batchGet($offset = 0, $count = 20, $noContent = 0)
  {
    return $this->post("cgi-bin/freepublish/batchget", [
      "offset" => $offset,
      "count" => $count,
      "no_content" => $noContent
    ])->getData();
  }
}
