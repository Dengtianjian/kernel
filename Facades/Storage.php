<?php

namespace kernel\Facades;

use kernel\Foundation\Facade\Facade;

/**
 * 文件存储门面
 *
 * 门面本身不持有实例：`Storage::get(...)` 每次都向**当前应用**的容器取
 * `Storage::class`，也就是 {@see \kernel\Providers\Storage\Storage} 构造时登记的那一份
 * （磁盘池、访问控制、落库、文件路由都在它上面）。
 *
 * 与 `$app->make(Storage::class)`、`$app->storage()` 完全同源——登记时用的是
 * `$app->instance(Storage::class, $storage, "storage")`，三个键指向同一对象。
 *
 * 未装配时直接抛错，不会反射新建一个配置全默认的空存储。
 *
 * @method static mixed get(string $fileKey) 读取文件内容
 * @method static mixed put($file, $saveFileName = null) 上传并保存
 * @method static bool delete(string $key) 删除文件
 * @method static bool exists(string $key) 判断文件是否存在
 * @method static \kernel\Providers\Storage\StorageURL url(string $fileKey) 生成访问地址
 * @method static string direct(string $fileKey, array $urlParams = [], int $expires = 1800, bool $withSignature = true) 直接向磁盘取地址
 * @method static bool verifySignature(string $fileKey, array $rawURLParams, array $rawHeaders = [], string $httpMethod = "get", $action = null) 校验访问签名
 * @method static \kernel\Providers\Storage\Storage registerRoute($nameOrUri, $methodOrController = null, $controller = null) 注册文件路由
 * @method static \kernel\Providers\Storage\Storage enableDataSave() 开启文件元信息落库
 * @method static \kernel\Providers\Storage\Storage accessControl($val = null, $authId = null, $enableAuth = null) 配置访问控制
 * @method static mixed disk($name = null) 切换磁盘
 * @method static array disks() 全部磁盘（别名 => 驱动）
 */
class Storage extends Facade
{
  /**
   * 容器键：存储实例
   *
   * 固定用基类名 Storage 而不是子类名——业务取用契约就是它，
   * 具体是哪种子类由装配它的 Provider 决定。
   *
   * @return string
   */
  protected static function accessor()
  {
    return \kernel\Providers\Storage\Storage::class;
  }
}
