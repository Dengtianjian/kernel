<?php

namespace kernel\Traits;

use kernel\Foundation\Config;

trait FileStorageControllerTrait
{
  public function __constcutor($R)
  {
    $this->query = [
      "signature" => "string",
      "sign-algorithm" => "string",
      "sign-time" => "string",
      "key-time" => "string",
      "header-list" => "string",
      "url-param-list" => "string"
    ];
    parent::__constcutor($R);
  }
  /**
   * 获取参数
   *
   * @return array{signatureKey:string,signature:string,URLParams:array,headers:array}
   */
  public function getParams()
  {
    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    $Headers = $this->request->header->some();
    unset($URLParams['id'], $URLParams['uri']);

    return [
      "signatureKey" => $SignatureKey,
      "signature" => $Signature,
      "URLParams" => $URLParams,
      "headers" => $Headers
    ];
  }
  /**
   * 回去过滤掉签名参数的请求参数列表
   *
   * @return array
   */
  public function getRequestParams()
  {
    $URLParams = $this->request->query->some();
    unset($URLParams['id'], $URLParams['uri'], $URLParams['signature'], $URLParams['sign-algorithm'], $URLParams['sign-time'], $URLParams['key-time'], $URLParams['header-list'], $URLParams['url-param-list']);

    return $URLParams;
  }
}
