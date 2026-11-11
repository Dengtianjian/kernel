<?php

namespace kernel\Providers\Cloud;

use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 腾讯云服务装配
 *
 * 构造函数里一次做完两件事：
 * - 把 {@see QCloud} 绑成应用单例——账号注册表必须整个请求共用一份，
 *   否则装配阶段登记的账号与业务里取到的账号会是两个空表；
 * - 登记本应用的账号。默认没有账号（内核不知道应用把密钥放在哪），
 *   应用侧继承本类覆盖 {@see account()}，从自己的设置里读出密钥返回即可。
 *
 * 登记方式：`$app->register(new QCloudProvider())`。**必须传实例**——容器对「传类名」
 * 的登记是延迟的（首次取用才构造），本类不被构造就不会登记账号。
 *
 * 默认不对外开放：腾讯云密钥属于装配它的应用，别的应用要用，请该应用先 `expose()`，
 * 使用方再 `import()`。
 *
 * @see \kernel\Providers\Cloud\QCloud
 */
class QCloudProvider extends AbilityBaseObject
{
  /**
   * 构造即装配：绑服务 → 登记账号
   *
   * @return void
   */
  public function __construct()
  {
    $this->app()->provider(QCloud::class);

    $account = $this->account();
    if ($account === null) {
      return;
    }

    $this->app()->make(QCloud::class)->addAccount($account);
  }

  /**
   * 本应用的腾讯云账号
   *
   * 默认 null。**在构造函数里调用**，此刻 `setup()` 已完成、数据库已接，可以放心读设置；
   * 但要注意本方法在父类构造期间执行：子类若另有属性参与计算，必须先于
   * `parent::__construct()` 就绪（实践上直接读设置、不依赖子类构造体里赋的值最稳）。
   *
   * 返回 null 时不登记，此时服务里的取用入口一律拿到 null——与「密钥未配置」的表现一致。
   *
   * ```php
   * protected function account()
   * {
   *   $settings = $this->app()->make(SettingProvider::class)->items("QCloud_SecretId", "QCloud_SecretKey");
   *
   *   return new QCloudAccount($settings["QCloud_SecretId"], $settings["QCloud_SecretKey"], "main");
   * }
   * ```
   *
   * @return QCloudAccount|null
   */
  protected function account()
  {
    return null;
  }
}
