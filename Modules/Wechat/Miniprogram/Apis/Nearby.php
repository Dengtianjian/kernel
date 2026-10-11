<?php

namespace kernel\Modules\Wechat\Miniprogram\Apis;

use kernel\Modules\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 附近小程序
 *
 * 对应微信「附近小程序」分类，用于管理展示在小程序附近的小程序地点。
 */
class Nearby extends WechatMiniProgramApi
{
  /**
   * 添加地点（POST /wxa/addnearbypoi）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/nearby-poi/api_addnearbypoi.html
   *
   * @param array $data 请求体，常用字段：store_name（门店名称）、address（门店地址）、
   *                    map_poi_id（腾讯地图 poi_id）、principal（负责人姓名）、
   *                    principal_phone（负责人联系方式）、pic_list（门店图片）、
   *                    service_infos（服务标签）、hour（营业时间）、company_name、
   *                    qualification_list（资质材料）等，具体以官方文档为准
   * @return array 微信响应数组，成功时含 poi_id、audit_id
   */
  function addNearbyPoi($data = [])
  {
    return $this->post("wxa/addnearbypoi", $data)->getData();
  }
  /**
   * 删除地点（POST /wxa/delnearbypoi）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/nearby-poi/api_deletenearbypoi.html
   *
   * @param string $poiId 附近地点 id
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function deleteNearbyPoi($poiId)
  {
    return $this->post("wxa/delnearbypoi", [
      "poi_id" => $poiId
    ])->getData();
  }
  /**
   * 查看地点（GET /wxa/getnearbypoilist）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/nearby-poi/api_getnearbypoilist.html
   *
   * @param int $page 页码，从 1 开始
   * @param int $pageRows 每页条数
   * @return array 微信响应数组，成功时含 data（地点列表）
   */
  function getNearbyPoiList($page = 1, $pageRows = 20)
  {
    return $this->get("wxa/getnearbypoilist", [
      "page" => $page,
      "page_rows" => $pageRows
    ])->getData();
  }
  /**
   * 设置展示状态（POST /wxa/setnearbypoishowstatus）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/nearby-poi/api_setshowstatus.html
   *
   * @param string $poiId 附近地点 id
   * @param int $status 展示状态：0 不展示；1 展示
   * @return array 微信响应数组，成功时 errcode 为 0
   */
  function setNearbyPoiShowStatus($poiId, $status)
  {
    return $this->post("wxa/setnearbypoishowstatus", [
      "poi_id" => $poiId,
      "status" => $status
    ])->getData();
  }
}
