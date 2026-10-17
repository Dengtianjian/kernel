<?php

namespace kernel\Modules\QCloud;

use kernel\Foundation\Result;

class QCloudFaceId extends QCloudApi
{
  /**
   * 实例化人脸核身客户端
   *
   * @param QCloudAccount $account 腾讯云账号，提供 SecretId / SecretKey 等凭据
   */
  public function __construct(QCloudAccount $account)
  {
    parent::__construct($account, "faceid");
  }
  /**
   * 银行卡二要素核验
   *
   * @param string $name 姓名
   * @param string $bankCard 银行卡
   * @return Result
   */
  public function bankCard2EVerification($name, $bankCard)
  {
    $action = "BankCard2EVerification";
    $version = "2018-03-01";

    return $this->post($action, $version, [
      "Name" => $name,
      "BankCard" => $bankCard
    ]);
  }
}
