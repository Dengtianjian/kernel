<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 微信搜一搜
 *
 * 对应微信「微信搜一搜」分类，用于向微信搜索推送小程序优质内容，便于被及时收录与展示。
 */
class Search extends WechatMiniProgramApi
{
  /**
   * 搜一搜数据推送（POST /wxa/search/wxaapi_submitpages）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/wxsearch/api_submitpages.html
   *
   * 推送小程序页面的路径、参数与结构化数据，让微信搜索更及时地收录小程序内容。
   * 注意：path + query 标识唯一页面，相同则按覆盖更新处理；每次提交页面数不超过 1000，
   * 每日提交页面上限 50 万，单个页面大小不超过 1M。
   *
   * @param array $pages 页面信息列表，元素形如
   *                     [
   *                       "path" => "pages/index/index",   // 以 pages/ 开头的页面路径
   *                       "query" => "id=1",               // 页面请求参数
   *                       "data_list" => [                 // 页面的结构化数据，可传多个
   *                         [
   *                           "@type" => "wxsearch_cpdata", // 正式数据；审核数据用 wxsearch_testcpdata
   *                           "update" => 1,               // 1 新增；3 删除
   *                           "content_id" => "1",         // 数据方自定义 id
   *                           "page_type" => 2,            // 固定填 2
   *                           "title" => "页面标题",        // 建议 20 字以内
   *                           "mainbody" => "正文内容",     // 不可带 HTML 标签
   *                           "time_publish" => 1700000000,// 发布时间（Unix 时间戳，秒）
   *                           "time_modify" => 1700000000  // 更新时间（Unix 时间戳，秒）
   *                         ]
   *                       ]
   *                     ]
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function submitPages($pages)
  {
    return $this->post("wxa/search/wxaapi_submitpages", [
      "pages" => $pages
    ])->getData();
  }
}
