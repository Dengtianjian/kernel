<?php

namespace kernel\Platform\Wechat\Miniprogram\ImageAndText;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 图像处理与文字识别（聚合类）
 *
 * 聚合微信「图像处理与文字识别」分类下的接口：
 * - 图像处理：{@see Image}
 * - 文字识别：{@see Ocr}
 *
 * 使用示例：
 *
 * ```php
 * $mp->imageAndText()->image()->qrCode($imgUrl);
 * $mp->imageAndText()->ocr()->idCard($imgUrl);
 * ```
 */
class ImageAndText extends WechatMiniProgramApi
{
  /**
   * 图像处理相关接口
   *
   * @return Image
   */
  public function image()
  {
    return new Image($this->account);
  }
  /**
   * 文字识别（OCR）相关接口
   *
   * @return Ocr
   */
  public function ocr()
  {
    return new Ocr($this->account);
  }
}
