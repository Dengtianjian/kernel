<?php

namespace kernel\Platform\DiscuzX\Middleware;

use kernel\Middleware\GlobalWechatOfficialAccountMiddleware;
use kernel\Platform\DiscuzX\Model\DiscuzXAccessTokenModel;

class GlobalDiscuzXWechatOfficialAccountMiddleware extends GlobalWechatOfficialAccountMiddleware
{
  public function __construct()
  {
    $this->accessTokenModel = DiscuzXAccessTokenModel::class;
  }
}
