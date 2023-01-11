<?php

namespace gstudio_kernel\Platform\Aliyun;

class AliyunSignature extends Aliyun
{
  private function percentEncode($value = null)
  {
    $en = urlencode($value);
    $en = str_replace("+", "%20", $en);
    $en = str_replace("*", "%2A", $en);
    $en = str_replace("%7E", "~", $en);
    return $en;
  }
  public function generate($parameters = [], $method = "GET")
  {
    date_default_timezone_set("GMT");
    ksort($parameters);
    $canonicalizedQueryString = '';
    foreach ($parameters as $key => $value) {
      $canonicalizedQueryString .= '&' . $this->percentEncode($key)
        . '=' . $this->percentEncode($value);
    }
    $stringToSign = $method . '&%2F&' . $this->percentencode(substr($canonicalizedQueryString, 1));
    return base64_encode(hash_hmac('sha1', $stringToSign, $this->AppSecret . '&', true));
  }
}
