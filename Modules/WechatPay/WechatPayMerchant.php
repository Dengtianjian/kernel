<?php

namespace kernel\Modules\WechatPay;

use kernel\Foundation\Exception\Error;
use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Modules\WechatPay\V3\WechatPayV3;

/**
 * 微信支付商户（APIv3）
 *
 * 承载单个微信支付商户的身份与凭证，作为 {@see WechatPayV3} 取用凭证的来源——
 * 与微信模块中 {@see \kernel\Modules\Wechat\WechatAccount} 承载 AppId/AppSecret
 * 的分工一致。
 *
 * 本模块目前**只支持 APIv3**，因此本类只承载 V3 所需凭证：
 * - **身份**：`appId`（公众平台 AppId）与 `merchantId`（商户号），构造时传入；
 * - **凭证**：APIv3 密钥、商户私钥（**文件路径或 PEM 内容**，二者其一即可）、证书序列号、
 *   回调地址，按需通过同名「读写一体」方法设置；
 * - **平台证书**：验证回调签名用的**微信支付平台证书**（微信方的证书，不是本商户的），
 *   通过 {@see platformCertificates()} 显式配置——本模块不会去调用接口拉取平台证书；
 * - **微信支付公钥**：公钥模式下的验签密钥，替代平台证书（灰度期可能并存），
 *   通过 {@see platformPublicKeyContent()} 或 {@see platformPublicKeyFilePath()} 二选一配置。
 *
 * 是否具备发起请求的条件由 {@see hasApiV3Credentials()} 判断，避免把「半配置」的商户
 * 交给接口客户端。注意该判定**不含验签密钥**（平台证书、微信支付公钥）——它们是
 * 收报文时用的，不是发请求的前提。
 *
 * 使用示例：
 *
 * ```php
 * $merchant = new WechatPayMerchant($appId, $mchId);
 *
 * $merchant
 *   ->apiV3Secret("...")
 *   ->apiV3PrivateKeyFilePath("/path/apiclient_key.pem") // 或 apiV3PrivateKeyContent(PEM 文本)，二选一
 *   ->apiV3PrivateKeySerialNo("...")
 *   ->notifyUrl("https://example.com/wechatPayNotify.php");
 *
 * $merchant->hasApiV3Credentials(); // true：凭证已齐备
 * $merchant->merchantId();          // 读取商户号
 *
 * // 验证回调签名用的微信支付平台证书：serial_no => PEM 内容 或 文件路径
 * $merchant->platformCertificates([
 *   "43C1DCC87122AAE50991BFC2D7D21B1560FD7B1E" => "/path/wechatpay_platform_cert.pem",
 * ]);
 *
 * $pay = $merchant->v3();           // 实例化 APIv3 接口客户端
 * $pay->jsapi()->order($outTradeNo, $openId, 100, "测试商品");
 * ```
 */
class WechatPayMerchant extends AbilityBaseObject
{
  /**
   * 回调地址的格式校验规则
   *
   * 只校验**格式**：必须 http/https 开头、主机名合法、端口可选（可带基础认证信息）。
   * 不校验微信侧的可用性——微信还要求 notify_url 公网可访问、必须 https（走专线接入时可用 http）、
   * 且不能携带参数。
   */
  const NOTIFY_URL_PATTERN = '~^https?://([^\s/?#\[\]\@]+\@)?([^\s/?#\@:]+)(?::\d{2,5})?([^\s?#\[\]]*)$~';
  /**
   * 公众平台 AppId
   *
   * 请求下单接口时注意其应用属性需与支付场景匹配（如公众号场景需使用服务号 AppId）。
   *
   * @var string|null
   */
  protected $appId = null;
  /**
   * 微信支付商户号
   *
   * 由微信支付生成并下发，即请求报文与签名中的 `mchid`。
   *
   * @var string|null
   */
  protected $merchantId = null;
  /**
   * APIv3 密钥
   *
   * @var string|null
   */
  protected $apiV3Secret = null;
  /**
   * APIv3 商户私钥文件路径（apiclient_key.pem）
   *
   * 与 {@see $apiV3PrivateKeyContent} 二选一：两者都设置时以**私钥内容**为准，
   * 本字段作为回退，见 {@see \kernel\Modules\WechatPay\V3\WechatPayV3Api::signMessage()}。
   *
   * @var string|null
   */
  protected $apiV3PrivateKeyFilePath = null;
  /**
   * APIv3 商户私钥内容（PEM 文本）
   *
   * 供私钥不由本模块托管、而是存在别处（如平台的支付配置项）的场景使用：
   * 那边只存 PEM 文本、没有可读文件，落盘反而多一份泄露面，
   * 因此这里允许直接持有内容，签名时无需再碰磁盘。
   *
   * 与 {@see $apiV3PrivateKeyFilePath} 二选一，两者都设置时以本字段为准。
   *
   * @var string|null
   */
  protected $apiV3PrivateKeyContent = null;
  /**
   * APIv3 商户证书序列号
   *
   * @var string|null
   */
  protected $apiV3PrivateKeySerialNo = null;
  /**
   * 支付结果异步通知的回调地址
   *
   * 必须为外网可访问的 URL 且不能携带参数；公网域名必须为 https。
   *
   * @var string|null
   */
  protected $notifyUrl = null;
  /**
   * 微信支付平台证书（serial_no => PEM 内容）
   *
   * 只服务于回调签名验证：微信支付回调头 `Wechatpay-Serial` 指明用哪一张。
   * 微信支持新旧平台证书并行（同一时间可能两张都有效），因此按序列号索引。
   * 由 {@see platformCertificates()} 设置，设置时已归一化为 PEM 内容。
   *
   * @var array<string, string>
   */
  protected $platformCertificates = [];
  /**
   * 微信支付公钥文件路径（pub_key.pem）
   *
   * **公钥模式**下的验签密钥（与平台证书模式二选一，灰度期可能并存）。
   * 与 {@see $platformPublicKeyContent} 二选一，两者都设置时以内容为准。
   *
   * @var string|null
   */
  protected $platformPublicKeyFilePath = null;
  /**
   * 微信支付公钥内容（PEM 文本）
   *
   * 与 {@see $platformPublicKeyFilePath} 二选一的另一种写法：公钥存在别处
   * （如平台的配置项）时直接持有文本，验签无需碰磁盘。
   *
   * @var string|null
   */
  protected $platformPublicKeyContent = null;

  /**
   * 初始化微信支付商户
   *
   * 只设置身份信息（AppId 与商户号），其余凭证后续按需通过同名方法链式设置。
   *
   * @param string|null $appId 公众平台 AppId
   * @param string|null $merchantId 微信支付商户号
   */
  function __construct($appId, $merchantId)
  {
    $this->appId = $appId;
    $this->merchantId = $merchantId;
  }

  /**
   * 实例化 APIv3 接口客户端
   *
   * 返回以本商户为凭证来源的 {@see WechatPayV3}（V3 聚合入口），再由它取用各接口对象：
   * 各支付产品（jsapi()/app()/h5()/native()/miniProgram()）、合单（combine()）、
   * 账单（bill()）、分账（profitSharing()）、商家转账（transfer()）。
   *
   * ```php
   * $merchant->v3()->jsapi()->order($outTradeNo, $openId, 100, "测试商品");
   * $merchant->v3()->jsapi()->queryByOutTradeNo($outTradeNo);
   * ```
   *
   * 每次调用返回新实例（不复用），与微信模块按需实例化各接口对象的做法一致。
   *
   * 本方法**不校验凭证是否齐备**：{@see hasApiV3Credentials()} 要求回调地址（notifyUrl）也已配置，
   * 那是「下单」的必要条件；而本方法同时也是查单、关单、退款、账单等不需要 notify_url 的入口，
   * 在此拦截会把合法调用一并挡掉。下单前请自行用 hasApiV3Credentials() 自检。
   *
   * @return WechatPayV3
   */
  public function v3()
  {
    return new WechatPayV3($this);
  }

  /**
   * 读写一体：获取或设置 AppId
   *
   * 传参即设置并返回 `$this`（便于链式调用），不传参则读取当前值。
   *
   * @param string|null $data 待设置的 AppId
   * @return static|string|null 设置时返回当前实例；读取时返回当前值
   */
  public function appId($data = null)
  {
    if (func_num_args()) {
      $this->appId = $data;
      return $this;
    }

    return $this->appId;
  }

  /**
   * 读写一体：获取或设置商户号
   *
   * 传参即设置并返回 `$this`（便于链式调用），不传参则读取当前值。
   *
   * @param string|null $data 待设置的商户号
   * @return static|string|null 设置时返回当前实例；读取时返回当前值
   */
  public function merchantId($data = null)
  {
    if (func_num_args()) {
      $this->merchantId = $data;
      return $this;
    }

    return $this->merchantId;
  }

  /**
   * 读写一体：获取或设置 APIv3 密钥
   *
   * 传参即设置并返回 `$this`（便于链式调用），不传参则读取当前值。
   *
   * @param string|null $data 待设置的 APIv3 密钥
   * @return static|string|null 设置时返回当前实例；读取时返回当前值
   */
  public function apiV3Secret($data = null)
  {
    if (func_num_args()) {
      $this->apiV3Secret = $data;
      return $this;
    }

    return $this->apiV3Secret;
  }

  /**
   * 读写一体：获取或设置 APIv3 商户私钥文件路径
   *
   * 传参即设置并返回 `$this`（便于链式调用），不传参则读取当前值。
   *
   * @param string|null $data 待设置的私钥文件路径
   * @return static|string|null 设置时返回当前实例；读取时返回当前值
   */
  public function apiV3PrivateKeyFilePath($data = null)
  {
    if (func_num_args()) {
      if (!file_exists($data)) {
        throw new Error("微信支付 v3 密钥文件不存在：{$data}", 500, 500, $data);
      }
      if (!is_readable($data)) {
        throw new Error("微信支付 v3 密钥文件不可读：{$data}", 500, 500, $data);
      }
      $this->apiV3PrivateKeyFilePath = $data;
      return $this;
    }

    return $this->apiV3PrivateKeyFilePath;
  }

  /**
   * 读写一体：获取或设置 APIv3 商户私钥内容（PEM 文本）
   *
   * 传参即设置并返回 `$this`（便于链式调用），不传参则读取当前值。
   *
   * 适用的场景是私钥**不由本模块托管**：例如平台把商户私钥整体存在配置项里，
   * 只有 PEM 文本、没有对应的可读文件。
   * 与 {@see apiV3PrivateKeyFilePath()} 二选一即可；两者都设置时以本方法设置的**内容**为准，
   * 签名时优先取内容、内容为空才回退读文件（见
   * {@see \kernel\Modules\WechatPay\V3\WechatPayV3Api::signMessage()}）。
   *
   * 设置时用 `openssl_pkey_get_private()` 校验能否解析为私钥（与
   * {@see platformCertificates()} 设置时即校验证书的做法一致），避免无效文本留到签名时才失败；
   * 传 null 或空串视为「不设置/清空私钥内容」，不做校验。
   *
   * @param string|null $data 待设置的私钥 PEM 内容
   * @return static|string|null 设置时返回当前实例；读取时返回当前值
   * @throws Error 传入内容无法解析为私钥时抛出
   */
  public function apiV3PrivateKeyContent($data = null)
  {
    if (func_num_args()) {
      if ($data !== null && $data !== "" && !@openssl_pkey_get_private($data)) {
        //* 私钥内容不能进错误详情（会被写进日志与响应），只报结论
        throw new Error("微信支付 v3 商户私钥无法解析", 500, 500);
      }
      $this->apiV3PrivateKeyContent = $data;
      return $this;
    }

    return $this->apiV3PrivateKeyContent;
  }

  /**
   * 读写一体：获取或设置 APIv3 商户证书序列号
   *
   * 传参即设置并返回 `$this`（便于链式调用），不传参则读取当前值。
   *
   * @param string|null $data 待设置的证书序列号
   * @return static|string|null 设置时返回当前实例；读取时返回当前值
   */
  public function apiV3PrivateKeySerialNo($data = null)
  {
    if (func_num_args()) {
      $this->apiV3PrivateKeySerialNo = $data;
      return $this;
    }

    return $this->apiV3PrivateKeySerialNo;
  }

  /**
   * 读写一体：获取或设置支付结果通知的回调地址
   *
   * 传参即设置并返回 `$this`（便于链式调用），不传参则读取当前值。
   *
   * 设置时按 {@see NOTIFY_URL_PATTERN} 校验格式，不合规直接抛
   * {@see Error}（与 {@see apiV3PrivateKeyFilePath()} 的处理方式一致）；
   * 传 null 或空串视为「不设置/清空回调地址」，不做校验。
   *
   * @param string|null $data 待设置的回调地址
   * @return static|string|null 设置时返回当前实例；读取时返回当前值
   * @throws Error 回调地址格式不合规时抛出
   */
  public function notifyUrl($data = null)
  {
    if (func_num_args()) {
      if ($data !== null && $data !== "" && !preg_match(self::NOTIFY_URL_PATTERN, $data)) {
        throw new Error("微信支付回调地址格式不正确：{$data}", 500, 500, $data);
      }
      $this->notifyUrl = $data;
      return $this;
    }

    return $this->notifyUrl;
  }

  /**
   * 读写一体：获取或设置微信支付平台证书
   *
   * 验签用的是**微信支付平台证书**的公钥（微信方的证书），不是本商户的 apiclient 证书；
   * 微信支付回调头 `Wechatpay-Serial` 指明用哪一张，且微信支持新旧平台证书并行，
   * 因此这里按 `serial_no => 证书` 的映射保存。
   *
   * 证书值支持两种写法，设置时自动识别并**统一归一化为 PEM 内容**（读取拿到的是 PEM）：
   * - **PEM 内容**：含 `-----BEGIN CERTIFICATE-----`，直接使用；
   * - **文件路径**：其余情况视为路径，读取文件内容（不存在/不可读直接抛 Error，同
   *   {@see apiV3PrivateKeyFilePath()} 的处理方式）。
   *
   * 归一化在设置时完成，运行时验签不再碰磁盘；也正因为如此，平台证书**过期或轮换**后
   * 必须重新调用本方法（本模块不会自动拉取或刷新平台证书）。
   *
   * @param array|null $data 待设置的映射（serial_no => PEM 内容或文件路径）
   * @return static|array<string, string> 设置时返回当前实例；读取时返回归一化后的映射
   * @throws Error 入参不是数组、证书为空、路径不可读或不是合法证书时抛出
   */
  public function platformCertificates($data = null)
  {
    if (func_num_args()) {
      if (!is_array($data)) {
        throw new Error("微信支付平台证书需以「serial_no => 证书」数组传入", 500, 500, $data);
      }

      $certificates = [];
      foreach ($data as $serialNo => $certificate) {
        if (!is_string($certificate) || $certificate === "") {
          throw new Error("微信支付平台证书不能为空（serial_no: {$serialNo}）", 500, 500, $certificate);
        }
        if (strpos($certificate, "-----BEGIN CERTIFICATE-----") === false) {
          if (!file_exists($certificate)) {
            throw new Error("微信支付平台证书文件不存在：{$certificate}", 500, 500, $certificate);
          }
          if (!is_readable($certificate)) {
            throw new Error("微信支付平台证书文件不可读：{$certificate}", 500, 500, $certificate);
          }
          $certificate = file_get_contents($certificate);
        }
        if (!@openssl_x509_read($certificate)) {
          throw new Error("微信支付平台证书无法解析（serial_no: {$serialNo}）", 500, 500, $certificate);
        }

        $certificates[$serialNo] = $certificate;
      }

      $this->platformCertificates = $certificates;

      return $this;
    }

    return $this->platformCertificates;
  }

  /**
   * 读写一体：获取或设置微信支付公钥文件路径
   *
   * 传参即设置并返回 `$this`（便于链式调用），不传参则读取当前值。
   *
   * 设置时只校验文件存在与可读（同 {@see apiV3PrivateKeyFilePath()}），不解析内容；
   * 真正取用见 {@see platformPublicKey()}。传 null 或空串视为「不设置/清空」，不做校验。
   *
   * @param string|null $data 待设置的公钥文件路径
   * @return static|string|null 设置时返回当前实例；读取时返回当前值
   * @throws Error 文件不存在或不可读时抛出
   */
  public function platformPublicKeyFilePath($data = null)
  {
    if (func_num_args()) {
      if ($data !== null && $data !== "") {
        if (!file_exists($data)) {
          throw new Error("微信支付公钥文件不存在：{$data}", 500, 500, $data);
        }
        if (!is_readable($data)) {
          throw new Error("微信支付公钥文件不可读：{$data}", 500, 500, $data);
        }
      }
      $this->platformPublicKeyFilePath = $data;
      return $this;
    }

    return $this->platformPublicKeyFilePath;
  }

  /**
   * 读写一体：获取或设置微信支付公钥内容（PEM 文本）
   *
   * 传参即设置并返回 `$this`（便于链式调用），不传参则读取当前值。
   *
   * 设置时用 `openssl_pkey_get_public()` 校验能否解析为公钥（与
   * {@see apiV3PrivateKeyContent()} 校验私钥的做法一致），避免无效文本留到验签时才失败；
   * 传 null 或空串视为「不设置/清空」，不做校验。
   *
   * @param string|null $data 待设置的公钥 PEM 内容
   * @return static|string|null 设置时返回当前实例；读取时返回当前值
   * @throws Error 传入内容无法解析为公钥时抛出
   */
  public function platformPublicKeyContent($data = null)
  {
    if (func_num_args()) {
      if ($data !== null && $data !== "" && !@openssl_pkey_get_public($data)) {
        //* 公钥内容不进错误详情（会被写进日志与响应），只报结论
        throw new Error("微信支付公钥无法解析", 500, 500);
      }
      $this->platformPublicKeyContent = $data;
      return $this;
    }

    return $this->platformPublicKeyContent;
  }

  /**
   * 取出实际可用的微信支付公钥（PEM 文本）
   *
   * 取值顺序与私钥一致：**先内容、后文件**（见
   * {@see \kernel\Modules\WechatPay\V3\WechatPayV3Api::signMessage()} 对私钥的处理）。
   * 两处都没配、或文件读不到时返回 null，由验签方按「未配置」处理。
   *
   * @return string|null 公钥 PEM；未配置或文件不可读时返回 null
   */
  public function platformPublicKey()
  {
    if ($this->platformPublicKeyContent) {
      return $this->platformPublicKeyContent;
    }
    if (!$this->platformPublicKeyFilePath) {
      return null;
    }
    if (!file_exists($this->platformPublicKeyFilePath) || !is_readable($this->platformPublicKeyFilePath)) {
      return null;
    }

    return file_get_contents($this->platformPublicKeyFilePath) ?: null;
  }

  /**
   * 是否具备发起 APIv3 请求的凭证
   *
   * 判定条件：AppId、商户号、APIv3 密钥、商户私钥（**文件路径或 PEM 内容**，二者其一）、
   * 证书序列号与回调地址均已配置（V3 下单必须携带 notify_url）。
   *
   * @return boolean
   */
  public function hasApiV3Credentials()
  {
    return (bool) (
      $this->appId
      && $this->merchantId
      && $this->apiV3Secret
      && ($this->apiV3PrivateKeyFilePath || $this->apiV3PrivateKeyContent)
      && $this->apiV3PrivateKeySerialNo
      && $this->notifyUrl
    );
  }
}
