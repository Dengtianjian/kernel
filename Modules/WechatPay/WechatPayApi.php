<?php

namespace kernel\Modules\WechatPay;

use kernel\Foundation\Data\Str;
use kernel\Foundation\HTTP\Curl;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 微信支付接口客户端基类
 *
 * 承载各版本接口客户端（{@see \kernel\Modules\WechatPay\V3\WechatPayV3}、
 * {@see WechatPayV2} 等）共用的能力：身份信息（AppId、商户号）、
 * 接口地址前缀、HTTP 客户端与请求方法。版本之间的差异（签名算法、报文格式）由子类实现
 * ——例如 V3 使用 JSON 报文 + RSA 签名。
 *
 * 与微信模块 {@see \kernel\Modules\Wechat\WechatApi} 的分工一致：本类负责「怎么发请求」，
 * 子类负责「发什么、怎么签」。
 *
 * 本类是客户端基类、不是门面：模块的静态入口见门面 {@see WechatPay}，
 * 商户注册表见 {@see WechatPayModule}，商户凭证见 {@see WechatPayMerchant}。
 */
class WechatPayApi extends AbilityBaseObject
{
  /**
   * 接口基础地址前缀
   *
   * 由子类覆写（如 V3 为 https://api.mch.weixin.qq.com/v3）；
   * 个别接口需临时切换域名时，子类可保存该属性、改值、请求后再改回。
   *
   * @var string|null
   */
  protected $apiUrl = null;
  /**
   * CURL 客户端实例，用于发起 HTTP 请求
   *
   * 实例在整个对象生命周期内复用，每次请求时重新设置 URL 与请求体。
   *
   * @var Curl
   */
  protected $CURL = null;
  /**
   * 公众平台 AppId
   *
   * @var string|null
   */
  protected $appId = null;
  /**
   * 微信支付商户号
   *
   * @var string|null
   */
  protected $merchantId = null;
  /**
   * 实例化微信支付类
   *
   * @param string $appId 公众平台 AppId
   * @param string $merchantId 微信支付平台商户号
   */
  function __construct($appId, $merchantId)
  {
    $this->appId = $appId;
    $this->merchantId = $merchantId;

    $this->CURL = new Curl();
  }
  /**
   * 生成单号
   *
   * @return string
   */
  protected function generateTradeNo()
  {
    return Str::serialNo();
  }
  /**
   * 生成32位随机字符串
   *
   * @return string
   */
  protected function generateNonceString()
  {
    return md5(uniqid() . time());
  }
  /**
   * 发送 GET 请求到微信支付接口
   *
   * 路径拼接在 {@see $apiUrl} 之后；具体域名由子类设定。
   * 返回值是复用的 CURL 实例，可继续调用 getData()/statusCode()/errorNo() 读取响应。
   *
   * @param string $uri 业务接口路径，如 "pay/transactions/out-trade-no/{单号}"
   * @param array $query 附加的 query 参数
   * @return Curl 复用的 CURL 实例
   */
  protected function get($uri, $query = [])
  {
    return $this->CURL->url($this->apiUrl . "/" . $uri, $query)->get();
  }
  /**
   * 发送 POST 请求到微信支付接口
   *
   * 路径拼接在 {@see $apiUrl} 之后；请求体是数组还是 JSON 字符串取决于子类在构造中
   * 对 CURL 的配置（如 V3 为 json(true)、V2 为 json(false) + XML 字符串）。
   * 返回值是复用的 CURL 实例，可继续调用 getData()/statusCode()/errorNo() 读取响应。
   *
   * @param string $uri 业务接口路径，如 "pay/transactions/jsapi"
   * @param array|string $body 请求体
   * @param array $query 附加的 query 参数
   * @return Curl 复用的 CURL 实例
   */
  protected function post($uri, $body = [], $query = [])
  {
    return $this->CURL->url($this->apiUrl . "/" . $uri, $query)->post($body);
  }
  /**
   * 发送 PUT 请求到微信支付接口
   *
   * 与 {@see post()} 同构，只是请求方法不同，用于微信要求 PUT 的接口
   * （如消费者投诉的投诉通知回调地址更新）。
   *
   * @param string $uri 业务接口路径
   * @param array|string $body 请求体
   * @param array $query 附加的 query 参数
   * @return Curl 复用的 CURL 实例
   */
  protected function put($uri, $body = [], $query = [])
  {
    return $this->CURL->url($this->apiUrl . "/" . $uri, $query)->put($body);
  }
  /**
   * 发送 DELETE 请求到微信支付接口
   *
   * 与 {@see post()} 同构，只是请求方法不同，用于微信要求 DELETE 的接口
   * （如消费者投诉的投诉通知回调地址删除）。
   *
   * @param string $uri 业务接口路径
   * @param array|string $body 请求体
   * @param array $query 附加的 query 参数
   * @return Curl 复用的 CURL 实例
   */
  protected function delete($uri, $body = [], $query = [])
  {
    return $this->CURL->url($this->apiUrl . "/" . $uri, $query)->delete($body);
  }
  /**
   * 获取并解析最近一次响应为数组
   *
   * CURL 客户端在响应为 JSON 时已自动解析为关联数组，此时直接返回；否则再尝试按 JSON 解码。
   *
   * @return array|null 解析后的关联数组；响应非 JSON 或解析失败时返回 null
   */
  public function getJsonData()
  {
    $data = $this->CURL->getData();
    if (is_array($data)) {
      return $data;
    }

    return json_decode($data, true);
  }
}
