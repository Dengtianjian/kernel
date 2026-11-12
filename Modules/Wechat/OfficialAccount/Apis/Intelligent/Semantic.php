<?php

namespace kernel\Modules\Wechat\OfficialAccount\Apis\Intelligent;

use kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 智能接口 - 语义理解
 *
 * 基于微信语义理解服务，将用户自然语言解析为结构化的意图与服务结果。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Intelligent_Interface/Natural_Language_Processing.html
 */
class Semantic extends WechatOfficialAccountApi
{
  /**
   * 语义理解（搜索）
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Intelligent_Interface/Natural_Language_Processing.html
   *
   * @param string $query 用户输入的文本（不能为空，长度限制以官方文档为准）
   * @param string $category 需要调用的服务类别，多个以逗号分隔，如 "flight,hotel"
   * @param string|null $appId 公众号 AppId，不传时取当前账号的 AppId
   * @param string|null $uid 用户唯一标识（可选）
   * @param string|null $latitude 纬度（可选）
   * @param string|null $longitude 经度（可选）
   * @param string|null $city 城市（可选）
   * @param string|null $region 区域（可选）
   * @return array 微信响应数组，成功时含 query、semantic 等字段
   */
  public function search($query, $category, $appId = null, $uid = null, $latitude = null, $longitude = null, $city = null, $region = null)
  {
    $body = [
      "query" => $query,
      "category" => $category,
      "appid" => $appId ?: $this->account->appId()
    ];
    // 仅提交调用方显式传入的可选字段，避免向微信提交 null
    $optionals = [
      "uid" => $uid,
      "latitude" => $latitude,
      "longitude" => $longitude,
      "city" => $city,
      "region" => $region
    ];
    foreach ($optionals as $key => $value) {
      if ($value !== null) {
        $body[$key] = $value;
      }
    }

    return $this->post("semantic/semproxy/search", $body)->getData();
  }
}
