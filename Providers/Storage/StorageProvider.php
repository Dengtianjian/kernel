<?php

namespace kernel\Providers\Storage;

use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Providers\Storage\Drivers\LocalStorage;

/**
 * 文件存储装配
 *
 * 构造即装配：用 {@see drivers()} 给的磁盘列表建 {@see Storage}，登记成应用单例
 * （业务契约键 + 短名 `storage`），再交给 {@see configure()} 收尾。
 *
 * 内核的默认实现是**兜底**：一块本地盘、不开签名、不落库，作用只是保证任何应用
 * `$app->make(Storage::class)` 都有东西可用。真实装配由应用侧继承本类：
 *
 * ```php
 * class StorageProvider extends \kernel\Providers\Storage\StorageProvider
 * {
 *   protected function drivers()
 *   {
 *     return ["local" => new AppLocalStorage(), "cos" => ...];
 *   }
 *
 *   protected function configure(Storage $storage)
 *   {
 *     $storage->enableDataSave();
 *     $storage->registerRoute("upload");
 *   }
 * }
 * ```
 *
 * 存储默认不对外开放：文件中心归装配它的应用，别的应用要用由它 expose()、使用方 import()。
 *
 * @see Storage
 */
class StorageProvider extends AbilityBaseObject
{
  /**
   * 构造即装配：建存储 → 登记成应用单例 → 交给 configure() 收尾
   *
   * 注意构造期间就会调用 {@see drivers()} 与 {@see configure()}：子类若另有属性参与计算，
   * 必须先于 `parent::__construct()` 就绪。
   *
   * @return void
   */
  public function __construct()
  {
    $storage = new Storage($this->drivers());

    //* 业务契约键 + 短名都指向同一对象：`make(Storage::class)` / `$app->storage()` /
    //* {@see \kernel\Facades\Storage} 门面三者同源
    $this->app()->provider($storage, Storage::class);
    $this->app()->provider($storage, "storage");

    $this->configure($storage);
  }

  /**
   * 磁盘列表（别名 => 驱动）
   *
   * 默认只给一块内核本地盘；应用侧覆写它追加 COS / OSS 等磁盘，列表第一块即默认盘。
   *
   * @return array<string, \kernel\Providers\Storage\Drivers\AbstractStorage>
   */
  protected function drivers()
  {
    return ["local" => new LocalStorage()];
  }

  /**
   * 装配收尾
   *
   * 默认什么都不做；应用侧覆写它来开访问控制、启用落库、注册文件路由。
   *
   * @param Storage $storage 刚建好并已登记的存储实例
   * @return void
   */
  protected function configure(Storage $storage)
  {
  }
}
