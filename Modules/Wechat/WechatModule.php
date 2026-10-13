<?php

namespace kernel\Modules\Wechat;

use kernel\Foundation\Module\Module;
use kernel\Modules\Wechat\Miniprogram\WechatMiniProgram;
use kernel\Modules\Wechat\Models\WechatAccountsModel;
use kernel\Modules\Wechat\OfficialAccount\WechatOfficialAccount;

/**
 * 微信模块
 *
 * 微信账号注册表 + 业务取用入口：只回答「这次调用该用哪个账号」，
 * 不负责 HTTP 请求（见 {@see WechatApi} 与 OfficialAccount/Miniprogram 下各接口类），
 * 也不负责 token 生命周期（见 {@see WechatAccessToken}）与用户绑定（见 WechatUsersModel）。
 *
 * 账号来源与优先级：
 * 1. 内存注册表：调用方通过 addAccount() 显式登记的账号，优先级最高；
 * 2. 别名：setAlias() / addAccount() 登记的别名（运行时概念，仅当前请求有效，不落库）；
 * 3. wechat_accounts 表：以上未命中时懒加载整表，按 type 分派为
 *    {@see WechatOfficialAccount} 或 {@see WechatMiniProgram}，同一请求内只查一次。
 *
 * 取用账号时必须显式给出 appId 或别名，本模块不提供「默认账号」概念：
 * 无法从账号数量、入库顺序等信息推断业务想用哪一个，猜错会静默发错请求。
 *
 * 类型分派严格按表字段 type 进行，不做静默强转：officialAccount() 拿到的账号
 * 一定是公众号、miniProgram() 拿到的一定是小程序，否则返回 null。
 *
 * 使用示例：
 *
 * ```php
 * $oa = Wechat::officialAccount("wx1234567890");        // 指定 appId
 * $mp = Wechat::miniProgram("wx1234567890");            // 指定 appId
 * Wechat::setAlias("wx1234567890", "mini-default");     // 登记别名（仅当前请求）
 * $mp2 = Wechat::miniProgram("mini-default");           // 按别名
 * $token = Wechat::account("default")->accessToken();   // token 仍归 WechatAccessToken
 * $oa->message()->template()->send($openid, $templateId, $data);
 * ```
 *
 * 说明：账号以 wechat_accounts 表为准；别名是运行时概念，仅当前请求有效。
 */
class WechatModule extends Module
{
  /**
   * 账号类型：公众号（wechat_accounts.type）
   */
  const TYPE_OFFICIAL_ACCOUNT = "official_account";
  /**
   * 账号类型：小程序（wechat_accounts.type）
   */
  const TYPE_MINI_PROGRAM = "mini_program";

  /**
   * 模块名称（管理器中的键名）
   *
   * @var string
   */
  protected $name = "wechat";
  /**
   * 已登记的账号（appId => WechatAccount）
   *
   * @var array<string, WechatAccount>
   */
  protected $accounts = [];
  /**
   * 运行时别名映射（别名 => appId，仅当前请求有效）
   *
   * @var array<string, string>
   */
  protected $aliases = [];
  /**
   * 已确认不存在的 key（负缓存，避免同一请求内反复查库）
   *
   * @var array<string, bool>
   */
  protected $missing = [];
  /**
   * 是否已从 wechat_accounts 表加载
   *
   * @var bool
   */
  protected $loaded = false;

  // ===================================================================
  // 注册表
  // ===================================================================

  /**
   * 登记账号
   *
   * 内存注册表优先级最高；同一 appId 重复登记时后者覆盖前者。
   * 本方法只影响当前请求的内存注册表，不写入 wechat_accounts 表。
   *
   * @param WechatAccount $account 账号实例
   * @param string|null $alias 可选别名，非空时建立运行时别名（仅当前请求）
   * @return static 当前实例
   */
  public function addAccount(WechatAccount $account, $alias = null)
  {
    $appId = $account->appId();
    if ($appId === null || $appId === "") {
      throw new \InvalidArgumentException("微信账号缺少 appId，无法登记");
    }
    $this->accounts[$appId] = $account;
    if ($alias !== null && $alias !== "") {
      $this->setAlias($appId, $alias);
    }
    unset($this->missing[$appId]);

    return $this;
  }
  /**
   * 登记账号别名
   *
   * 别名为运行时概念，仅作用于当前请求，不写入 wechat_accounts 表；
   * 需要跨请求可用请改为直接使用 appId。
   *
   * @param string $appId 账号 AppId
   * @param string $alias 别名
   * @return static 当前实例
   */
  public function setAlias($appId, $alias)
  {
    if ($alias === null || $alias === "") {
      throw new \InvalidArgumentException("微信账号别名不能为空");
    }
    $this->aliases[$alias] = $appId;
    unset($this->missing[$alias]);

    return $this;
  }
  /**
   * 移除账号
   *
   * 仅作用于当前请求的内存注册表，不修改 wechat_accounts 表；
   * 同时清理指向该 appId 的全部运行时别名。
   *
   * @param string $appIdOrAlias AppId 或别名
   * @return static 当前实例
   */
  public function removeAccount($appIdOrAlias)
  {
    $appId = $this->aliases[$appIdOrAlias] ?? $appIdOrAlias;
    unset($this->accounts[$appId]);
    foreach ($this->aliases as $alias => $target) {
      if ($target === $appId) {
        unset($this->aliases[$alias]);
      }
    }

    return $this;
  }
  /**
   * 账号是否存在
   *
   * @param string $appIdOrAlias AppId 或别名
   * @return bool
   */
  public function hasAccount($appIdOrAlias): bool
  {
    if ($appIdOrAlias === null || $appIdOrAlias === "") {
      return false;
    }

    return $this->resolve($appIdOrAlias) !== null;
  }
  /**
   * 获取全部已登记账号
   *
   * 会触发一次懒加载（若尚未加载过）。
   *
   * @return array<string, WechatAccount> appId => 账号
   */
  public function getAccounts(): array
  {
    $this->loadFromStore();

    return $this->accounts;
  }

  // ===================================================================
  // 业务取用入口
  // ===================================================================

  /**
   * 获取账号（不限定类型）
   *
   * @param string $appIdOrAlias AppId 或别名（必填，本模块不提供默认账号）
   * @return WechatAccount|null 账号不存在时返回 null
   */
  public function account($appIdOrAlias)
  {
    return $this->resolve($appIdOrAlias);
  }
  /**
   * 获取公众号账号
   *
   * @param string $appIdOrAlias AppId 或别名（必填，本模块不提供默认账号）
   * @return WechatOfficialAccount|null 账号不存在或其类型不是公众号时返回 null
   */
  public function officialAccount($appIdOrAlias)
  {
    $account = $this->resolve($appIdOrAlias);

    return $account instanceof WechatOfficialAccount ? $account : null;
  }
  /**
   * 获取小程序账号
   *
   * @param string $appIdOrAlias AppId 或别名（必填，本模块不提供默认账号）
   * @return WechatMiniProgram|null 账号不存在或其类型不是小程序时返回 null
   */
  public function miniProgram($appIdOrAlias)
  {
    $account = $this->resolve($appIdOrAlias);

    return $account instanceof WechatMiniProgram ? $account : null;
  }

  // ===================================================================
  // 生命周期
  // ===================================================================

  /**
   * 启动钩子
   *
   * 刻意保持轻量、不查库：引导阶段绝大多数请求并不使用微信，
   * 账号解析全部推迟到首次取用时懒加载。请勿在此处加入数据库查询。
   *
   * @return void
   */
  protected function onBoot() {}
  /**
   * 停止钩子
   *
   * 释放本次运行期缓存，便于常驻进程或测试中复用同一模块实例。
   *
   * @return void
   */
  protected function onShutdown()
  {
    $this->accounts = [];
    $this->aliases = [];
    $this->missing = [];
    $this->loaded = false;
  }

  // ===================================================================
  // 内部实现
  // ===================================================================

  /**
   * 解析 AppId 或别名到账号（内部使用）
   *
   * 顺序：负缓存 → 内存注册表 → 别名 → 懒加载表 → 仍未命中则记入负缓存。
   *
   * @param string $appIdOrAlias AppId 或别名
   * @return WechatAccount|null
   */
  protected function resolve($appIdOrAlias)
  {
    if (isset($this->missing[$appIdOrAlias])) {
      return null;
    }

    $account = $this->match($appIdOrAlias);
    if ($account !== null) {
      return $account;
    }

    // 首次未命中：懒加载表后重试一次
    $this->loadFromStore();
    $account = $this->match($appIdOrAlias);
    if ($account !== null) {
      return $account;
    }

    $this->missing[$appIdOrAlias] = true;

    return null;
  }
  /**
   * 在已登记账号与别名中查找（内部使用，不触发加载）
   *
   * @param string $appIdOrAlias AppId 或别名
   * @return WechatAccount|null
   */
  protected function match($appIdOrAlias)
  {
    if (isset($this->accounts[$appIdOrAlias])) {
      return $this->accounts[$appIdOrAlias];
    }
    $appId = $this->aliases[$appIdOrAlias] ?? null;
    if ($appId !== null && isset($this->accounts[$appId])) {
      return $this->accounts[$appId];
    }

    return null;
  }
  /**
   * 从 wechat_accounts 表加载账号（内部使用）
   *
   * 同一请求内只加载一次；表不可用时优雅退化（置位 loaded，本次请求不再重试）。
   *
   * @return void
   */
  protected function loadFromStore()
  {
    if ($this->loaded) {
      return;
    }
    // 先置位：库不可用时不在同一请求内反复重试
    $this->loaded = true;

    try {
      $rows = WechatAccountsModel::get();
      foreach ($rows as $row) {
        $row = $this->normalizeRow($row);
        $account = $this->makeAccount($row);
        if ($account === null) {
          continue;
        }
        $this->accounts[$account->appId()] = $account;
      }
    } catch (\Throwable $e) {
      // 库不可用：返回空注册表，不中断调用
    }
  }
  /**
   * 归一化表行数据（内部使用）
   *
   * @param mixed $row wechat_accounts 行数据（数组或可转数组的对象）
   * @return array
   */
  protected function normalizeRow($row)
  {
    if (is_object($row) && method_exists($row, "toArray")) {
      $row = $row->toArray();
    }

    return (array) $row;
  }
  /**
   * 按表行数据创建账号对象（内部使用）
   *
   * @param array $row 已归一化的 wechat_accounts 行数据
   * @return WechatAccount|null 类型未知或缺少 appId 时返回 null
   */
  protected function makeAccount(array $row)
  {
    $appId = $row['app_id'] ?? null;
    if (!$appId) {
      return null;
    }
    $appSecret = $row['app_secret'] ?? null;

    switch ($row['type'] ?? null) {
      case self::TYPE_OFFICIAL_ACCOUNT:
        return new WechatOfficialAccount($appId, $appSecret);
      case self::TYPE_MINI_PROGRAM:
        return new WechatMiniProgram($appId, $appSecret);
      default:
        return null;
    }
  }
}
