<?php

namespace kernel\Modules\WechatPay;

use kernel\Foundation\Exception\Error;
use kernel\Foundation\Module\Module;

/**
 * 微信支付模块
 *
 * 微信支付商户注册表 + 业务取用入口：只回答「这次调用该用哪个商户」，
 * 不负责 HTTP 请求与签名（见 {@see WechatPayApi} 与 {@see \kernel\Modules\WechatPay\V3\WechatPayV3Api}），
 * 也不负责商户凭证本身的取用（见 {@see WechatPayMerchant}）。
 *
 * 与 {@see \kernel\Modules\Wechat\WechatModule} 的分工一致，差别只在商户来源：
 * 微信模块的账号以 wechat_accounts 表为准，本模块**暂无商户表**，注册表只存在于当前请求的内存中，
 * 由调用方通过 addMerchant() 显式登记，不触发任何懒加载。
 * 将来商户落表时，只需在 {@see merchant()} 未命中处补一次懒加载，对外接口不变。
 *
 * 注册表以**商户号（merchantId）**为键，而非 AppId：商户号才是微信支付侧的唯一身份，
 * 同一个商户号可以绑定公众号 / 小程序 / APP 等多个 AppId，用 AppId 作主键会把不同商户混在一起。
 * 别名是运行时概念，仅当前请求有效，不落库。
 *
 * 取用商户时必须显式给出商户号或别名，本模块不提供「默认商户」概念：
 * 无法从登记数量、登记顺序推断业务想用哪一个，猜错会静默把请求发到别的商户号上。
 *
 * 使用示例：
 *
 * ```php
 * $merchant = new WechatPayMerchant($appId, $mchId);
 * $merchant
 *   ->apiV3Secret("...")
 *   ->apiV3PrivateKeyFilePath("/path/apiclient_key.pem")
 *   ->apiV3PrivateKeySerialNo("...")
 *   ->notifyUrl("https://example.com/wechatPayNotify.php");
 *
 * $module = new WechatPayModule();
 * $module->addMerchant($merchant, "main");     // 登记并起别名
 * $module->merchant("main");                   // 按别名取用
 * $module->hasMerchant("1900000109");          // 按商户号判存在
 * $module->getMerchants();                     // merchantId => 商户
 *
 * $pay = new WechatPayV3($module->merchant("main"));
 * ```
 */
class WechatPayModule extends Module
{
  /**
   * 模块名称（管理器中的键名）
   *
   * @var string
   */
  protected $name = "wechatPay";
  /**
   * 已登记的商户（merchantId => WechatPayMerchant）
   *
   * @var array<string, WechatPayMerchant>
   */
  protected $merchants = [];
  /**
   * 运行时别名映射（别名 => merchantId，仅当前请求有效）
   *
   * @var array<string, string>
   */
  protected $aliases = [];

  // ===================================================================
  // 注册表
  // ===================================================================

  /**
   * 登记商户
   *
   * 同一商户号重复登记时后者覆盖前者；本方法只影响当前请求的内存注册表，不写入任何表。
   *
   * @param WechatPayMerchant $merchant 商户实例
   * @param string|null $alias 可选别名，非空时建立运行时别名（仅当前请求）
   * @return static 当前实例
   */
  public function addMerchant(WechatPayMerchant $merchant, $alias = null)
  {
    $merchantId = $merchant->merchantId();
    if ($merchantId === null || $merchantId === "") {
      throw new \InvalidArgumentException("微信支付商户缺少商户号，无法登记");
    }
    $this->merchants[$merchantId] = $merchant;
    if ($alias !== null && $alias !== "") {
      $this->setAlias($merchantId, $alias);
    }

    return $this;
  }
  /**
   * 登记商户别名
   *
   * 别名为运行时概念，仅作用于当前请求，不落库；需要跨请求可用请改为直接使用商户号。
   *
   * @param string $merchantId 商户号
   * @param string $alias 别名
   * @return static 当前实例
   */
  public function setAlias($merchantId, $alias)
  {
    if ($alias === null || $alias === "") {
      throw new \InvalidArgumentException("微信支付商户别名不能为空");
    }
    $this->aliases[$alias] = $merchantId;

    return $this;
  }
  /**
   * 移除商户
   *
   * 仅作用于当前请求的内存注册表；同时清理指向该商户号的全部运行时别名。
   *
   * @param string $merchantIdOrAlias 商户号或别名
   * @return static 当前实例
   */
  public function removeMerchant($merchantIdOrAlias)
  {
    $merchantId = $this->aliases[$merchantIdOrAlias] ?? $merchantIdOrAlias;
    unset($this->merchants[$merchantId]);
    foreach ($this->aliases as $alias => $target) {
      if ($target === $merchantId) {
        unset($this->aliases[$alias]);
      }
    }

    return $this;
  }
  /**
   * 商户是否存在
   *
   * @param string $merchantIdOrAlias 商户号或别名
   * @return bool
   */
  public function hasMerchant($merchantIdOrAlias): bool
  {
    if ($merchantIdOrAlias === null || $merchantIdOrAlias === "") {
      return false;
    }

    return $this->merchant($merchantIdOrAlias) !== null;
  }
  /**
   * 获取全部已登记商户
   *
   * @return array<string, WechatPayMerchant> merchantId => 商户
   */
  public function getMerchants(): array
  {
    return $this->merchants;
  }

  // ===================================================================
  // 业务取用入口
  // ===================================================================

  /**
   * 获取商户
   *
   * 先按别名解析，未命中则视为商户号直接查找。
   *
   * @param string $merchantIdOrAlias 商户号或别名（必填，本模块不提供默认商户）
   * @return WechatPayMerchant|null 商户未登记时返回 null
   */
  public function merchant($merchantIdOrAlias)
  {
    $merchantId = $this->aliases[$merchantIdOrAlias] ?? $merchantIdOrAlias;

    return $this->merchants[$merchantId] ?? null;
  }

  // ===================================================================
  // attach 编解码
  // ===================================================================

  /**
   * 编码 attach 数据
   *
   * 把数组编码成微信支付 `attach` 字段可接受的字符串：字符串键输出 `键=值`，数值键只输出值本身，
   * 各片段用 `&` 连接；空数组与非数组一律返回空字符串。
   *
   * `attach` 由微信支付原样带回回调，是下单与回调之间唯一的自定义透传通道，通常用来携带业务
   * 参数（如回调分发的键）。这里按字符数（mb_strlen）校验微信支付的 128 字符上限，超长抛出 500 错误。
   * 编码不做 URL 转义，键与值按原样拼接。
   *
   * 与 {@see decodeAttach()} 互逆。
   *
   * @param array $data 待编码的键值数据
   * @return string 编码结果；无数据时为空字符串
   */
  public static function encodeAttach($data)
  {
    if (!is_array($data) || empty($data)) return "";
    $strs = [];
    foreach ($data as $key => $value) {
      if (is_numeric($key)) {
        array_push($strs, $value);
      } else {
        array_push($strs, "$key=$value");
      }
    }
    $str = implode("&", $strs);
    if (mb_strlen($str) > 128) {
      throw new Error("服务器错误", 500, 500, "微信支付Attach参数长度不允许超出128个字符");
    }
    return $str;
  }
  /**
   * 解码 attach 字符串
   *
   * 把 {@see encodeAttach()} 产出的字符串还原为数组：片段之间用 `&` 分隔，含 `=` 的片段按
   * `键 => 值` 归入关联项，不含 `=` 的片段按下标依次追加（对应编码时数值键的匿名项）。
   *
   * 输入即微信支付回调里的 `attach` 字段原值，未编码过该字段时为空字符串。
   *
   * @param string $str 微信支付回调返回的 attach 字段值
   * @return array 解码结果
   */
  public static function decodeAttach($str)
  {
    $data = [];
    $strs = explode("&", $str);
    foreach ($strs as $str) {
      if (strpos($str, "=") === false) {
        array_push($data, $str);
      } else {
        list($key, $value) = explode("=", $str);
        $data[$key] = $value;
      }
    }
    return $data;
  }

  // ===================================================================
  // 生命周期
  // ===================================================================

  /**
   * 启动钩子
   *
   * 本模块暂无库来源，启动期没有任何事要做：请勿在此处加入数据库查询或远程请求。
   *
   * @return void
   */
  protected function onBoot() {}
  /**
   * 停止钩子
   *
   * 释放本次运行期的注册表，便于常驻进程或测试中复用同一模块实例。
   *
   * @return void
   */
  protected function onShutdown()
  {
    $this->merchants = [];
    $this->aliases = [];
  }
}
