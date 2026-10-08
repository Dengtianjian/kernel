<?php

namespace kernel\Modules\QCloud;

class QCloudFaceId extends QCloud
{
  public function __construct($secretId, $secretKey)
  {
    parent::__construct($secretId, $secretKey, "faceid");
  }
  /**
   * 银行卡二要素核验
   *
   * @param string $name 姓名
   * @param string $bankCard 银行卡
   * @return Result
   */
  public function BankCard2EVerification($name, $bankCard)
  {
    $action = "BankCard2EVerification";
    $version = "2018-03-01";

    return $this->post($action, $version, [
      "Name" => $name,
      "BankCard" => $bankCard
    ]);
  }
}
