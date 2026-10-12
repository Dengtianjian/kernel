<?php

namespace kernel\Modules\WechatPay;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Result;

class WechatPayV2 extends WechatPayApi
{
  protected $apiUrl = "https://api.mch.weixin.qq.com/mmpaysptrans";
  protected $merchantId = null;
  protected $apiSecret = null;
  protected $publicKeyFilePath = null;
  protected $sslCertFilePath = null;
  protected $sslKeyFilePath = null;
  /**
   * 实例微信支付V2类
   * @link https://pay.weixin.qq.com/wiki/doc/api/index.html
   *
   * @param string $appId 公众平台的AppId
   * @param string $merchantId 微信支付平台的商户号
   * @param string $apiSecret 微信支付APIV2密钥
   * @param string $publicKeyFilePath 微信支付公钥 获取方式：https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=25_7&index=4
   * @param string $sslCertFilePath 用于请求的SSL证书文件路径。从apiclient_cert.pem中导出证书部分的文件，为pem格式，请妥善保管不要泄露和被他人复制。说明：https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=4_3
   * @param string $sslKeyFilePath 用于请求的SSL证书密钥文件路径。说明：https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=4_3
   */
  public function __construct($appId, $merchantId, $apiSecret, $publicKeyFilePath, $sslCertFilePath, $sslKeyFilePath)
  {
    $this->appId = $appId;
    $this->merchantId = $merchantId;
    $this->apiSecret = $apiSecret;
    $this->publicKeyFilePath = $publicKeyFilePath;
    $this->sslCertFilePath = $sslCertFilePath;
    $this->sslKeyFilePath = $sslKeyFilePath;

    parent::__construct($appId, $merchantId);
    $this->CURL->json(false)->headers([
      "Content-Type" => " text/xml"
    ]);
    $this->CURL->sslCert($sslCertFilePath);
    $this->CURL->sslKey($sslKeyFilePath);
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
    $dataStrings = [];
    foreach ($data as $key => $item) {
      array_push($dataStrings, $key . "=" . $item);
    }
    array_push($dataStrings, "key=" . $this->apiSecret);
    $dataString = implode("&", $dataStrings);
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
    $publicKey = file_get_contents($this->publicKeyFilePath);
    openssl_public_encrypt($data, $encryptedData, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);

    return base64_encode($encryptedData);
  }
  /**
   * 获取微信支付公钥  
   * 返回的公钥是PKCS#1 格式
   * @link https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=25_7&index=4
   *
   * @return Result
   */
  public function getPublicKey()
  {
    $oldUrl = $this->apiUrl;
    $this->apiUrl = "https://fraud.mch.weixin.qq.com";
    $body = [
      "mch_id" => $this->merchantId,
      "nonce_str" => $this->generateNonceString(),
      "sign_type" => "MD5"
    ];
    $body['sign'] = $this->generateSign($body);
    $response = $this->post("risk/getpublickey", Arr::toXML($body), [], false);
    $this->apiUrl = $oldUrl;

    $result = new Result(true);
    if ($response->errorNo()) {
      return $result->error(500, 500, "服务器错误", $response->error());
    }
    $responseData = $response->getData();
    if ($response->statusCode() > 299) {
      return $result->error(500, $response->statusCode() . ":" . $responseData['code'], "服务器错误", $responseData);
    }
    if ($responseData['result_code'] === "FAIL") {
      return $result->error(500, 500 . ":" . $responseData['FAIL'], "服务器错误", $responseData);
    }

    return $result->success($responseData['pub_key']);
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
   * @param string $transactionId 交易单号
   * @param int|string $bankCode 银行卡所在开户行编号,详见银行编号列表：https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=24_4
   * @param string $bankNo 收款方银行卡号（采用标准RSA算法，公钥由微信侧提供）；商户需确保收集用户的银行卡信息，以及向微信支付传输用户姓名和账号标识信息做一致性校验已合法征得用户授权。
   * @param string $trueName 收款方用户名（采用标准RSA算法，公钥由微信侧提供）；商户需确保收集用户的姓名信息，以及向微信支付传输用户姓名和账号标识信息做一致性校验已合法征得用户授权
   * @param int $amount 付款金额：RMB分（支付总额，不含手续费）注：大于0的整数
   * @param string $description 付款到银行卡付款说明,即订单备注（UTF8编码，允许100个字符以内）
   * @return Result
   */
  public function payBank($transactionId, $bankCode, $bankNo, $trueName, $amount, $description)
  {
    $nonceStr = $this->generateNonceString();
    $body = [
      "mch_id" => $this->merchantId,
      "partner_trade_no" => $transactionId,
      "nonce_str" => $nonceStr,
      "enc_bank_no" => $this->encryptByPublicKey($bankNo),
      "enc_true_name" => $this->encryptByPublicKey($trueName),
      "bank_code" => $bankCode,
      "amount" => $amount,
      "desc" => $description,
    ];
    $body['sign'] = $this->generateSign($body);
    $response = $this->post("pay_bank", Arr::toXML($body));
    $result = new Result(true);
    if ($response->errorNo()) {
      return $result->error(500, 500, "服务器错误", $response->error());
    }
    $responseData = $response->getData();
    if ($response->statusCode() > 299) {
      return $result->error(500, $response->statusCode() . ":" . $responseData['code'], "服务器错误", $responseData);
    }
    if ($responseData['result_code'] === "FAIL") {
      if ($responseData['err_code'] === "PARAM_ERROR") {
        return $result->error(400, 400 . ":" . $responseData['err_code'], $responseData['err_code_des'], $responseData);
      }
      return $result->error(500, 500 . ":" . $responseData['err_code'], "服务器错误", $responseData);
    }
    return $result->success([
      "appId" => $this->appId,
      "merchantId" => $responseData['mch_id'],
      "transactionId" => $transactionId,
      "tradeNo" => $transactionId,
      "returnCode" => $responseData['return_code'],
      "returnMsg" => $responseData['return_msg'],
      "resultCode" => $responseData['return_code'],
      "errCode" => $responseData['err_code'],
      "errCodeDes" => $responseData['err_code_des'],
      "paymentNo" => $responseData['payment_no'],
      "amount" => $responseData['amount'],
      "cmmsAmt" => $responseData['cmms_amt'],
    ]);
  }
  /**
   * 查询付款到银行卡结果
   * 用于对商户付款到银行卡操作进行结果查询，返回付款操作详细结果。
   * @link https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay_yhk.php?chapter=25_3
   *
   * @param string $tradeNo 商户订单号，需保持唯一（只允许数字[0~9]或字母[A~Z]和[a~z]最短8位，最长32位）
   * @return Result 返回结果是调用微信查询接口后返回的数据，键名改成了驼峰法
   */
  public function queryBank($tradeNo)
  {
    $nonceStr = $this->generateNonceString();
    $body = [
      "mch_id" => $this->merchantId,
      "partner_trade_no" => $tradeNo,
      "nonce_str" => $nonceStr
    ];
    $body['sign'] = $this->generateSign($body);
    $response = $this->post("query_bank", Arr::toXML($body));
    $result = new Result(true);
    if ($response->errorNo()) {
      return $result->error(500, 500, "服务器错误", $response->error());
    }
    $responseData = $response->getData();
    if ($response->statusCode() > 299) {
      return $result->error(500, $response->statusCode() . ":" . $responseData['code'], "服务器错误", $responseData);
    }
    if ($responseData['result_code'] === "FAIL") {
      return $result->error(500, 500 . ":" . $responseData['FAIL'], "服务器错误", $responseData);
    }
    return $result->success([
      "returnCode" => $responseData['return_code'],
      "returnMsg" => $responseData['return_msg'],
      "resultCode" => $responseData['return_code'],
      "errCode" => $responseData['err_code'],
      "errCodeDes" => $responseData['err_code_des'],
      "tradeNo" => $responseData['partner_trade_no'],
      "paymentNo" => $responseData['payment_no'],
      "amount" => $responseData['amount'],
      "cmmsAmt" => $responseData['cmms_amt'],
      "status" => $responseData['status'],
      "createTime" => $responseData['create_time'],
      "paySuccessTime" => $responseData['pay_succ_time'],
      "reason" => $responseData['reason'],
    ]);
  }
}
