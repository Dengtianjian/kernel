<?php

namespace gstudio_kernel\Platform\Wechat\OfficialAccount;

class AccountManagement extends WechatOfficialAccount
{
  /**
   * 生成带参数的二维码
   *
   * @param integer $sceneId 场景值ID，临时二维码时为32位非0整型，永久二维码时最大值为100000（目前参数只支持1--100000）
   * @param string $sceneStr 场景值ID（字符串形式的ID），字符串类型，长度限制为1到64
   * @param integer $expireSeconds 该二维码有效时间，以秒为单位。 最大不超过2592000（即30天），此字段如果不填，则默认有效期为60秒。
   * @param string $actionName 二维码类型，QR_SCENE为临时的整型参数值，QR_STR_SCENE为临时的字符串参数值，QR_LIMIT_SCENE为永久的整型参数值，QR_LIMIT_STR_SCENE为永久的字符串参数值
   * @param array $actionInfo 	二维码详细信息
   * @return array
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
    ])->https(false)->send()->getData();
  }
  /**
   * 通过 ticket 换取二维码
   *
   * @param string $ticket 凭据
   * @return string png格式图片的base64编码
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
