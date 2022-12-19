<?php

namespace gstudio_kernel\Platform\Aliyun;

use gstudio_kernel\Foundation\Network\Curl;

class AliyunRequest extends Aliyun
{
  protected function params($params = [])
  {
    date_default_timezone_set("GMT");

    $publicParams = [
      "Format" => "json",
      "Version" => "2019-12-30",
      "AccessKeyId" => $this->AppId,
      'SignatureVersion' => '1.0',
      "SignatureMethod" => "HMAC-SHA1",
      'SignatureNonce' => uniqid(),
      "Timestamp" => date('Y-m-d\TH:i:s\Z', time() - date('Z')),
      "SignatureVersion" => "1.0"
    ];

    return array_merge($publicParams, $params);
  }
  public function send($url, $action, $params = [])
  {
    $AS = new AliyunSignature($this->AppId, $this->AppSecret);
    $params = array_merge($params, [
      "Action" =>  $action,
    ]);
    $params = $this->params($params);
    $params['Signature'] = $AS->generate($params);

    $request = new Curl();
    $request->url($url, $params);
    return $request->get()->getData();
  }
}
