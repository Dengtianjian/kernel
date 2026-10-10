<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis\QrCodeAndLink;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 小程序码和小程序链接（聚合类）
 *
 * 聚合微信「小程序码与小程序链接」分类下的接口：
 * - 小程序码：{@see QrCode}
 * - URL Scheme / URL Link / Short Link：{@see Link}
 *
 * 使用示例：
 *
 * ```php
 * $mp->qrCodeAndLink()->qrCode()->getWxaCodeUnlimit("scene=1");
 * $mp->qrCodeAndLink()->link()->generateScheme(["path" => "pages/index/index"]);
 * ```
 */
class QrCodeAndLink extends WechatMiniProgramApi
{
  /**
   * 小程序码相关接口
   *
   * @return QrCode
   */
  public function qrCode()
  {
    return new QrCode($this->account);
  }
  /**
   * 小程序链接相关接口（URL Scheme / URL Link / Short Link）
   *
   * @return Link
   */
  public function link()
  {
    return new Link($this->account);
  }
}
