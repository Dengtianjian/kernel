<?php

namespace gstudio_kernel\Platform\Aliyun;

class Aliyun
{
  protected $AppId = "";
  protected $AppSecret = "";
  function __construct($appId, $appSecret)
  {
    $this->AppId = $appId;
    $this->AppSecret = $appSecret;
  }
}
