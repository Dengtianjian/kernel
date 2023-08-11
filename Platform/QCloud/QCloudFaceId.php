<?php

namespace kernel\Platform\QCloud;

class QCloudFaceId extends QCloud
{
  public function __construct($secretId, $secretKey)
  {
    parent::__construct($secretId, $secretKey, "faceid");
  }
  /**
   * 银行卡二要素核验
   *
   * @param string $Name 姓名
   * @param string $BankCard 银行卡
   * @return ReturnResult
   */
  public function BankCard2EVerification($Name, $BankCard)
  {
    $Action = "BankCard2EVerification";
    $Version = "2018-03-01";

    return $this->post($Action, $Version, [
      "Name" => $Name,
      "BankCard" => $BankCard
    ]);
  }
}
