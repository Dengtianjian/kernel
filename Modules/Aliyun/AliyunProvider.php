<?php

namespace kernel\Modules\Aliyun;

use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 阿里云装配
 *
 * 构造即装配：把本应用的 {@see AliyunAccount} 登记成应用单例（类名 + 短名 `aliyun`）。
 *
 * 内核不知道应用把密钥放在哪，所以默认**不登记**（{@see account()} 返回 null）；
 * 应用侧继承本类覆写 `account()`，从自己的设置里读出密钥即可：
 *
 * ```php
 * class AliyunProvider extends \kernel\Modules\Aliyun\AliyunProvider
 * {
 *   protected function account()
 *   {
 *     $settings = Setting::items("Aliyun_AccessKey", "Aliyun_Secret");
 *
 *     return new AliyunAccount($settings["Aliyun_AccessKey"], $settings["Aliyun_Secret"]);
 *   }
 * }
 * ```
 *
 * 登记方式：`$app->make(AliyunProvider::class)` 或 `$app->provider(new AliyunProvider())`，
 * **必须传实例**——容器对「传类名」是延迟构造，本类不被构造就不会登记账号。
 *
 * 密钥属于装配它的应用；别的应用要用，由该应用 `expose()`、使用方 `import()`。
 *
 * @see AliyunAccount
 * @see Aliyun 门面
 */
class AliyunProvider extends AbilityBaseObject
{
  /**
   * 构造即装配：登记账号
   *
   * @return void
   */
  public function __construct()
  {
    $account = $this->account();
    if ($account === null) {
      return;
    }

    //* 类名 + 短名两条路都指向同一实例：
    //* `$app->make(AliyunAccount::class)` / `$app->aliyun()` / 门面 `Aliyun::xxx()`
    $this->app()->provider($account, AliyunAccount::class);
    $this->app()->provider($account, "aliyun");
  }

  /**
   * 本应用的阿里云账号
   *
   * 默认 null（内核不知道密钥从哪读）。**在构造函数里调用**，此刻装配上下文已就位，
   * 可以放心读设置；注意本方法在父类构造期间执行，子类若有属性参与计算须先于
   * `parent::__construct()` 就绪。
   *
   * @return AliyunAccount|null
   */
  protected function account()
  {
    return null;
  }
}
