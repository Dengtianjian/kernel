<?php

namespace kernel\Middleware;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\HTTP\Response\ResponseError;
use kernel\Foundation\Middleware\MiddlewareBase;
use kernel\Model\AccessTokenModel;
use kernel\Modules\Wechat\Facades\WechatOA;
use kernel\Platform\Wechat\AccessToken;

class WechatOfficialAccountMiddleware extends MiddlewareBase
{
  public function handle($appId, $appSecret, $next)
  {
    $Platform = "wechatOfficialAccount";

    AccessTokenModel::where("expiredAt", time(), "<")->delete(true);

    if ($appId && $appSecret) {
      $LatestAccountToken = AccessTokenModel::where("platform", $Platform)->where("appId", $appId)->where("expiredAt", time(), ">")->getOne();
      if (!$LatestAccountToken) {
        $AT = new AccessToken(null, $appId, $appSecret);
        $res = $AT->getStableAccessToken();
        if (isset($res['errcode'])) {
          return new ResponseError(500, "500:ServerError", "服务器错误", null, $res);
        }
        AccessTokenModel::add($res['access_token'], $Platform, $res['expires_in'], $appId);

        $LatestAccountToken = AccessTokenModel::where("platform", $Platform)->where("appId", $appId)->where("expiredAt", time(), ">")->getOne();
      }

      // WechatOA::accessToken($LatestAccountToken['accessToken']);
      // WechatOA::appId($LatestAccountToken['appId']);
    }

    return $next();
  }
}
