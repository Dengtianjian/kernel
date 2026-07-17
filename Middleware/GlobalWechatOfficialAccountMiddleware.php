<?php

namespace kernel\Middleware;

use kernel\Foundation\HTTP\Response\ResponseError;
use kernel\Foundation\Middleware;
use kernel\Foundation\Store;
use kernel\Model\AccessTokenModel;
use kernel\Platform\Wechat\AccessToken;

class GlobalWechatOfficialAccountMiddleware extends Middleware
{
  protected $accessTokenModel = null;
  public function __construct($request, $controller)
  {
    parent::__construct($request, $controller);
    $this->accessTokenModel = AccessTokenModel::class;
  }
  public function handle($AppId, $AppSecret, $next)
  {
    $ATM = new $this->accessTokenModel();
    $Platform = "wechatOfficialAccount";

    $ATM->where("expiredAt", time(), "<")->delete(true);

    if ($AppId && $AppSecret) {
      $LatestAccountToken = $ATM->where("platform", $Platform)->where("appId", $AppId)->where("expiredAt", time(), ">")->getOne();
      if (!$LatestAccountToken) {
        $AT = new AccessToken(null, $AppId, $AppSecret);
        $res = $AT->getStableAccessToken();
        if (isset($res['errcode'])) {
          return new ResponseError(500, "500:ServerError", "服务器错误", null, $res);
        }
        $ATM->add($res['access_token'], $Platform, $res['expires_in'], $AppId);

        $LatestAccountToken = $ATM->where("platform", $Platform)->where("appId", $AppId)->where("expiredAt", time(), ">")->getOne();
      }

      Store::setApp([
        "Wechat" => [
          "OfficialAccount" => [
            "AccessToken" => $LatestAccountToken['accessToken'],
            "AppId" => $LatestAccountToken['appId']
          ]
        ]
      ]);
    }

    return $next();
  }
}
