<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis\QrCodeAndLink;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 小程序链接（URL Scheme / URL Link / Short Link）
 *
 * 对应微信「小程序码与小程序链接」分类下的 URL Scheme、URL Link、Short Link 三组接口。
 */
class Link extends WechatMiniProgramApi
{
  /**
   * 获取加密 scheme 码（POST /wxa/generatescheme）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/qrcode-link/url-scheme/api_generatescheme.html
   *
   * 用于从短信、邮件、微信外网页等场景打开小程序。
   *
   * @param array $jumpWxa 打开的小程序信息，形如 ["path" => "pages/index/index", "query" => "", "env_version" => "release"]
   * @param bool $isExpire 生成的 scheme 码类型：false 永久有效，true 定时过期
   * @param int $expireType 过期类型：0 间隔失效（$expireInterval），1 指定时间失效（$expireTime）
   * @param int|null $expireTime 到期失效的 scheme 码的失效时间（Unix 时间戳，秒）
   * @param int|null $expireInterval 到期失效的 scheme 码的失效间隔（秒）
   * @return array 微信响应数组，成功时含 openlink
   */
  function generateScheme($jumpWxa = [], $isExpire = false, $expireType = 0, $expireTime = null, $expireInterval = null)
  {
    $body = [
      "is_expire" => $isExpire,
      "expire_type" => $expireType
    ];
    if ($jumpWxa) {
      $body['jump_wxa'] = $jumpWxa;
    }
    if ($expireTime !== null) {
      $body['expire_time'] = $expireTime;
    }
    if ($expireInterval !== null) {
      $body['expire_interval'] = $expireInterval;
    }

    return $this->post("wxa/generatescheme", $body)->getData();
  }
  /**
   * 查询 scheme 码（POST /wxa/queryscheme）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/qrcode-link/url-scheme/api_queryscheme.html
   *
   * @param string $scheme 获取到的 scheme 码
   * @return array 微信响应数组，成功时含 scheme_info、quota_info
   */
  function queryScheme($scheme)
  {
    return $this->post("wxa/queryscheme", [
      "scheme" => $scheme
    ])->getData();
  }
  /**
   * 获取 NFC 的小程序 scheme（POST /wxa/generatenfcscheme）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/qrcode-link/url-scheme/api_generatenfcscheme.html
   *
   * 用于 NFC 标签碰一碰打开小程序的场景。
   *
   * @param string $sn 设备 sn
   * @param string $modelId 设备 model_id
   * @param array $jumpWxa 打开的小程序信息，形如 ["path" => "pages/index/index", "query" => "", "env_version" => "release"]
   * @return array 微信响应数组，成功时含 openlink
   */
  function generateNfcScheme($sn, $modelId, $jumpWxa = [])
  {
    $body = [
      "sn" => $sn,
      "model_id" => $modelId
    ];
    if ($jumpWxa) {
      $body['jump_wxa'] = $jumpWxa;
    }

    return $this->post("wxa/generatenfcscheme", $body)->getData();
  }
  /**
   * 获取加密 URLLink（POST /wxa/generate_urllink）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/qrcode-link/url-link/api_generateurllink.html
   *
   * 用于从短信、邮件、微信外网页等场景打开小程序，与 scheme 码的区别是可在微信内直接打开。
   *
   * @param string $path 通过 URL Link 进入的小程序页面路径，不能为空
   * @param string $query 通过 URL Link 进入小程序时的 query 参数
   * @param bool $isExpire 生成的 URL Link 类型：false 永久有效，true 定时过期
   * @param int $expireType 过期类型：0 间隔失效（$expireInterval），1 指定时间失效（$expireTime）
   * @param int|null $expireTime 到期失效的 URL Link 的失效时间（Unix 时间戳，秒）
   * @param int|null $expireInterval 到期失效的 URL Link 的失效间隔（秒）
   * @param array|null $cloudBase 云开发静态网站自定义 H5 域名信息
   * @param string $envVersion 打开的小程序版本：release / trial / develop
   * @return array 微信响应数组，成功时含 url_link
   */
  function generateUrlLink($path, $query = "", $isExpire = false, $expireType = 0, $expireTime = null, $expireInterval = null, $cloudBase = null, $envVersion = "release")
  {
    $body = [
      "path" => $path,
      "query" => $query,
      "is_expire" => $isExpire,
      "expire_type" => $expireType,
      "env_version" => $envVersion
    ];
    if ($expireTime !== null) {
      $body['expire_time'] = $expireTime;
    }
    if ($expireInterval !== null) {
      $body['expire_interval'] = $expireInterval;
    }
    if ($cloudBase) {
      $body['cloud_base'] = $cloudBase;
    }

    return $this->post("wxa/generate_urllink", $body)->getData();
  }
  /**
   * 查询加密 URLLink（POST /wxa/query_urllink）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/qrcode-link/url-link/api_queryurllink.html
   *
   * @param string $link 获取到的 url link
   * @return array 微信响应数组，成功时含 url_link_info、quota_info
   */
  function queryUrlLink($link)
  {
    return $this->post("wxa/query_urllink", [
      "link" => $link
    ])->getData();
  }
  /**
   * 获取 Short Link（POST /wxa/genwxashortlink）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/qrcode-link/short-link/api_generateshortlink.html
   *
   * 将小程序页面路径转成短链，可用于短信、邮件等场景。
   *
   * @param string $pageUrl 通过 Short Link 进入的小程序页面路径，必须是已发布的小程序路径
   * @param string|null $pageTitle Short Link 对应的页面标题
   * @param bool $isPermanent 生成的 Short Link 类型：false 默认（30 天有效），true 永久有效
   * @return array 微信响应数组，成功时含 link
   */
  function generateShortLink($pageUrl, $pageTitle = null, $isPermanent = false)
  {
    $body = [
      "page_url" => $pageUrl,
      "is_permanent" => $isPermanent
    ];
    if ($pageTitle !== null) {
      $body['page_title'] = $pageTitle;
    }

    return $this->post("wxa/genwxashortlink", $body)->getData();
  }
}
