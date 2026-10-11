<?php

namespace kernel\Modules\Wechat\OfficialAccount\Apis;

use kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccountApi;

/**
 * 账号管理
 *
 * 微信公众号账号管理相关接口（带参数二维码等）。
 *
 * @link https://developers.weixin.qq.com/doc/offiaccount/Account_Management/Generating_a_Parametric_QR_Code.html
 */
class AccountManagement extends WechatOfficialAccountApi
{
  /**
   * 生成带参数的二维码
   *
   * @param integer $sceneId 场景值ID，临时二维码时为32位非0整型，永久二维码时最大值为100000（目前参数只支持1--100000）
   * @param string $sceneStr 场景值ID（字符串形式的ID），字符串类型，长度限制为1到64
   * @param integer $expireSeconds 该二维码有效时间，以秒为单位。 最大不超过2592000（即30天），此字段如果不填，则默认有效期为60秒。
   * @param string $actionName 二维码类型，QR_SCENE为临时的整型参数值，QR_STR_SCENE为临时的字符串参数值，QR_LIMIT_SCENE为永久的整型参数值，QR_LIMIT_STR_SCENE为永久的字符串参数值
   * @return array 微信响应数组，成功时含 ticket、expire_seconds、url
   */
  public function generatingParametricQRCode($sceneId = null, $sceneStr = null, $expireSeconds = 60, $actionName = "QR_SCENE")
  {
    $actionInfo = [
      "scene" => []
    ];
    if ($sceneId) {
      $actionInfo['scene']['scene_id'] = $sceneId;
    }
    if ($sceneStr) {
      $actionInfo['scene']['scene_str'] = $sceneStr;
    }

    return $this->post("cgi-bin/qrcode/create", [
      "expire_seconds" => $expireSeconds,
      "action_name" => $actionName,
      "action_info" => $actionInfo
    ])->getData();
  }
  /**
   * 通过 ticket 换取二维码
   *
   * @param string $ticket 凭据
   * @return string|null png 格式图片的 base64 编码（data URI），获取失败时返回 null
   */
  public function showQRCode($ticket)
  {
    $res = file_get_contents("https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=$ticket");
    if (!$res) return null;

    $base64 = base64_encode($res);

    return <<<EOT
data:image/png;base64,$base64
EOT;
  }
}
