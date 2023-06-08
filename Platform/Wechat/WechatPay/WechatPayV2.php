<?php

namespace kernel\Platform\Wechat\WechatPay;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\ReturnResult\ReturnResult;

class WechatPayV2 extends WechatPay
{
  protected $ApiUrl = "https://api.mch.weixin.qq.com/mmpaysptrans";
  protected $MerchantId = null;
  protected $ApiSecret = null;
  protected $PublicKeyFilePath = null;
  protected $SSLCertFilePath = null;
  protected $SSLKeyFilePath = null;
  /**
   * 实例微信支付V2类
   * @link https://pay.weixin.qq.com/wiki/doc/api/index.html
   *
   * @param string $AppId 公众平台的AppId
   * @param string $MerchantId 微信支付平台的商户号
   * @param string $ApiSecret 微信支付APIV2密钥
   * @param string $PublicKeyFilePath 微信支付公钥 获取方式：https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=25_7&index=4
   * @param string $SSLCertFilePath 用于请求的SSL证书文件路径。从apiclient_cert.pem中导出证书部分的文件，为pem格式，请妥善保管不要泄露和被他人复制。说明：https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=4_3
   * @param string $SSLKeyFilePath 用于请求的SSL证书密钥文件路径。说明：https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=4_3
   */
  public function __construct($AppId, $MerchantId, $ApiSecret, $PublicKeyFilePath, $SSLCertFilePath, $SSLKeyFilePath)
  {
    $this->AppId = $AppId;
    $this->MerchantId = $MerchantId;
    $this->ApiSecret = $ApiSecret;
    $this->PublicKeyFilePath = $PublicKeyFilePath;
    $this->SSLCertFilePath = $SSLCertFilePath;
    $this->SSLKeyFilePath = $SSLKeyFilePath;

    parent::__construct($AppId, $MerchantId);
    $this->CURL->json(false)->headers([
      "Content-Type" => " text/xml"
    ]);
    $this->CURL->SSLCert($SSLCertFilePath);
    $this->CURL->SSLKey($SSLKeyFilePath);
  }
  /**
   * 生成签名
   * @link https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=4_3
   *
   * @param array $data 用于生成签名的数据
   * @return string
   */
  public function generateSign($data)
  {
    ksort($data);
    $DataStrings = [];
    foreach ($data as $key => $item) {
      array_push($DataStrings, $key . "=" . $item);
    }
    array_push($DataStrings, "key=" . $this->ApiSecret);
    $dataString = implode("&", $DataStrings);
    return strtoupper(md5($dataString));
  }
  /**
   * 根据公钥加密数据
   *
   * @param string $data 被加密的数据
   * @return string
   */
  protected function encryptByPublicKey($data)
  {
    $PublicKey = file_get_contents($this->PublicKeyFilePath);
    openssl_public_encrypt($data, $encryptedData, $PublicKey, OPENSSL_PKCS1_OAEP_PADDING);

    return base64_encode($encryptedData);
  }
  /**
   * 获取微信支付公钥  
   * 返回的公钥是PKCS#1 格式
   * @link https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=25_7&index=4
   *
   * @return ReturnResult
   */
  public function getPublicKey()
  {
    $oldURL = $this->ApiUrl;
    $this->ApiUrl = "https://fraud.mch.weixin.qq.com";
    $Body = [
      "mch_id" => $this->MerchantId,
      "nonce_str" => $this->gererateNonceString(),
      "sign_type" => "MD5"
    ];
    $Body['sign'] = $this->generateSign($Body);
    $response = $this->post("risk/getpublickey", Arr::toXML($Body), [], false);
    $this->ApiUrl = $oldURL;

    $R = new ReturnResult(true);
    if ($response->errorNo()) {
      return $R->error(500, 500, "服务器错误", $response->error());
    }
    $ResponseData = $response->getData();
    if ($response->statusCode() > 299) {
      return $R->error(500, $response->statusCode() . ":" . $ResponseData['code'], "服务器错误", $ResponseData);
    }
    if ($ResponseData['result_code'] === "FAIL") {
      return $R->error(500, 500 . ":" . $ResponseData['FAIL'], "服务器错误", $ResponseData);
    }

    return $R->success($ResponseData['pub_key']);
  }
  /**
   * RSA公钥PKCS#1格式转成PKCS#8格式
   * @link https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=25_7&index=4
   *
   * @param string $filePath PKCS#1公钥文件路径
   * @return string|boolean
   */
  public function transformPKCS1ToPKCS8($filePath)
  {
    $result = exec("openssl rsa -RSAPublicKey_in -in $filePath -pubout 2>&1", $output);
    if ($result === false) {
      return false;
    }
    return implode("\n", array_slice($output, 1));
  }
  /**
   * 付款到银行卡
   * @link https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=25_2
   *
   * @param int|string $BankCode 银行卡所在开户行编号,详见银行编号列表：https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=24_4
   * @param string $BankNo 收款方银行卡号（采用标准RSA算法，公钥由微信侧提供）；商户需确保收集用户的银行卡信息，以及向微信支付传输用户姓名和账号标识信息做一致性校验已合法征得用户授权。
   * @param string $TrueName 收款方用户名（采用标准RSA算法，公钥由微信侧提供）；商户需确保收集用户的姓名信息，以及向微信支付传输用户姓名和账号标识信息做一致性校验已合法征得用户授权
   * @param int $Amount 付款金额：RMB分（支付总额，不含手续费）注：大于0的整数
   * @param string $Description 付款到银行卡付款说明,即订单备注（UTF8编码，允许100个字符以内）
   * @return ReturnResult
   */
  public function payBank($BankCode, $BankNo, $TrueName, $Amount, $Description)
  {
    $PartnerTradeNo = $this->gererateTradeNo();
    $NonceStr = $this->gererateNonceString();
    $Body = [
      "mch_id" => $this->MerchantId,
      "partner_trade_no" => $PartnerTradeNo,
      "nonce_str" => $NonceStr,
      "enc_bank_no" => $this->encryptByPublicKey($BankNo),
      "enc_true_name" => $this->encryptByPublicKey($TrueName),
      "bank_code" => $BankCode,
      "amount" => $Amount,
      "desc" => $Description,
    ];
    $Body['sign'] = $this->generateSign($Body);
    $response = $this->post("pay_bank", Arr::toXML($Body));
    $R = new ReturnResult(true);
    if ($response->errorNo()) {
      return $R->error(500, 500, "服务器错误", $response->error());
    }
    $ResponseData = $response->getData();
    if ($response->statusCode() > 299) {
      return $R->error(500, $response->statusCode() . ":" . $ResponseData['code'], "服务器错误", $ResponseData);
    }
    if ($ResponseData['result_code'] === "FAIL") {
      return $R->error(500, 500 . ":" . $ResponseData['FAIL'], "服务器错误", $ResponseData);
    }
    return $R->success([
      "appId" => $this->AppId,
      "merchantId" => $ResponseData['mch_id'],
      "returnCode" => $ResponseData['return_code'],
      "returnMsg" => $ResponseData['return_msg'],
      "resultCode" => $ResponseData['return_code'],
      "errCode" => $ResponseData['err_code'],
      "errCodeDes" => $ResponseData['err_code_des'],
      "tradeNo" => $ResponseData['partner_trade_no'],
      "paymentNo" => $ResponseData['payment_no'],
      "amount" => $ResponseData['amount'],
      "cmmsAmt" => $ResponseData['cmms_amt'],
    ]);
  }
  /**
   * 查询付款到银行卡结果
   * 用于对商户付款到银行卡操作进行结果查询，返回付款操作详细结果。
   * @link https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=25_3
   *
   * @param string $tradeNo 商户订单号，需保持唯一（只允许数字[0~9]或字母[A~Z]和[a~z]最短8位，最长32位）
   * @return ReturnResult 返回结果是调用微信查询接口后返回的数据，键名改成了驼峰法
   */
  public function queryBank($tradeNo)
  {
    $NonceStr = $this->gererateNonceString();
    $Body = [
      "mch_id" => $this->MerchantId,
      "partner_trade_no" => $tradeNo,
      "nonce_str" => $NonceStr
    ];
    $Body['sign'] = $this->generateSign($Body);
    $response = $this->post("query_bank", Arr::toXML($Body));
    $R = new ReturnResult(true);
    if ($response->errorNo()) {
      return $R->error(500, 500, "服务器错误", $response->error());
    }
    $ResponseData = $response->getData();
    if ($response->statusCode() > 299) {
      return $R->error(500, $response->statusCode() . ":" . $ResponseData['code'], "服务器错误", $ResponseData);
    }
    if ($ResponseData['result_code'] === "FAIL") {
      return $R->error(500, 500 . ":" . $ResponseData['FAIL'], "服务器错误", $ResponseData);
    }
    return $R->success([
      "returnCode" => $ResponseData['return_code'],
      "returnMsg" => $ResponseData['return_msg'],
      "resultCode" => $ResponseData['return_code'],
      "errCode" => $ResponseData['err_code'],
      "errCodeDes" => $ResponseData['err_code_des'],
      "tradeNo" => $ResponseData['partner_trade_no'],
      "paymentNo" => $ResponseData['payment_no'],
      "amount" => $ResponseData['amount'],
      "cmmsAmt" => $ResponseData['cmms_amt'],
      "status" => $ResponseData['status'],
      "createTime" => $ResponseData['create_time'],
      "paySuccessTime" => $ResponseData['pay_succ_time'],
      "reason" => $ResponseData['reason'],
    ]);
  }
}
