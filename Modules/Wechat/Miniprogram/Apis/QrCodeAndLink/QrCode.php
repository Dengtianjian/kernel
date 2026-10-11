<?php

namespace kernel\Modules\Wechat\Miniprogram\Apis\QrCodeAndLink;

use kernel\Modules\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 小程序码
 *
 * 对应微信「小程序码与小程序链接 → 小程序码」分类。
 *
 * 注意：本类接口成功时返回的是图片二进制内容，失败时返回 JSON 字符串（含 errcode/errmsg），
 * 调用方可通过 errcode 判断是否成功。
 */
class QrCode extends WechatMiniProgramApi
{
  /**
   * 获取小程序码（POST /wxa/getwxacode）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/qrcode-link/qr-code/api_getqrcode.html
   *
   * 永久有效、数量有限（10 万个），适用于数量较少的长期使用场景。
   *
   * @param string $path 扫码进入的小程序页面路径，如 "pages/index/index"，不能为空
   * @param int $width 二维码宽度（px），默认 430
   * @param bool $autoColor 是否自动配置线条颜色，为 true 时忽略 $lineColor
   * @param array|null $lineColor 线条颜色，形如 ["r" => 0, "g" => 0, "b" => 0]
   * @param bool $isHyaline 是否透明底色
   * @param string $envVersion 打开的小程序版本：release（正式）/ trial（体验）/ develop（开发）
   * @return string 成功返回图片二进制内容；失败返回 JSON 字符串
   */
  function getWxaCode($path, $width = 430, $autoColor = false, $lineColor = null, $isHyaline = false, $envVersion = "release")
  {
    $body = [
      "path" => $path,
      "width" => $width,
      "auto_color" => $autoColor,
      "is_hyaline" => $isHyaline,
      "env_version" => $envVersion
    ];
    if ($lineColor) {
      $body['line_color'] = $lineColor;
    }

    return $this->post("wxa/getwxacode", $body)->getData();
  }
  /**
   * 获取不限制的小程序码（POST /wxa/getwxacodeunlimit）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/qrcode-link/qr-code/api_getunlimitedqrcode.html
   *
   * 永久有效、数量不限，适用于需要大量生成的场景，通过 scene 携带业务参数。
   *
   * @param string $scene 业务参数，最大 32 个可见字符，只支持数字、大小写英文及部分特殊符号
   * @param string $page 扫码进入的页面路径，默认 "index"（首页），不能带参数
   * @param bool $checkPath 是否检查 page 是否存在（为 false 时允许跳到不存在的页面，开发版默认不检查）
   * @param string $envVersion 打开的小程序版本：release / trial / develop
   * @param int $width 二维码宽度（px），默认 430
   * @param bool $autoColor 是否自动配置线条颜色
   * @param array|null $lineColor 线条颜色，形如 ["r" => 0, "g" => 0, "b" => 0]
   * @param bool $isHyaline 是否透明底色
   * @return string 成功返回图片二进制内容；失败返回 JSON 字符串
   */
  function getWxaCodeUnlimit($scene, $page = "index", $checkPath = true, $envVersion = "release", $width = 430, $autoColor = false, $lineColor = null, $isHyaline = false)
  {
    $body = [
      "scene" => $scene,
      "page" => $page,
      "check_path" => $checkPath,
      "env_version" => $envVersion,
      "width" => $width,
      "auto_color" => $autoColor,
      "is_hyaline" => $isHyaline
    ];
    if ($lineColor) {
      $body['line_color'] = $lineColor;
    }

    return $this->post("wxa/getwxacodeunlimit", $body)->getData();
  }
  /**
   * 获取小程序二维码（POST /cgi-bin/wxaapp/createwxaqrcode）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/qrcode-link/qr-code/api_createqrcode.html
   *
   * 永久有效、数量有限（10 万个），与小程序码的区别是生成的是普通二维码。
   *
   * @param string $path 扫码进入的小程序页面路径，不能为空，且不能带参数
   * @param int $width 二维码宽度（px），默认 430
   * @return string 成功返回图片二进制内容；失败返回 JSON 字符串
   */
  function createWxaQrCode($path, $width = 430)
  {
    return $this->post("cgi-bin/wxaapp/createwxaqrcode", [
      "path" => $path,
      "width" => $width
    ])->getData();
  }
}
