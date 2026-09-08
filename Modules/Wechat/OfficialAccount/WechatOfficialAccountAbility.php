<?php

namespace kernel\Platform\Wechat\OfficialAccount;

use kernel\Platform\Wechat\Wechat;

class WechatOfficialAccountAbility extends Wechat
{
  function __construct(WechatOfficialAccount $OA)
  {
    parent::__construct($OA->accessToken(), $OA->appId(), $OA->appSecret());
  }
  public function name()
  {
    return "wechatOfficialAccount";
  }
  function changeAccount(WechatOfficialAccount $OA)
  {
    $this->accessToken($OA->accessToken());
    $this->appId($OA->appId());
    $this->appSecret($OA->appSecret());

    return $this;
  }
}
