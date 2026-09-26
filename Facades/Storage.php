<?php

namespace kernel\Facades;

use kernel\Foundation\Facade;
use kernel\Foundation\FileSystem\Storage\FileStorage as FileStorageAggregate;
use kernel\Foundation\FileSystem\Storage\LocalStorage;

/**
 * 文件存储门面
 *
 * 继承 Facade 并覆写 resolve()，自动判定为**单例门面**；底层实例为
 * {@see FileStorageAggregate}（聚合多磁盘 + 签名鉴权 + 访问控制 + 文件元信息落库）。
 *
 * 通过本门面可静态调用文件存储能力：
 *
 * ```php
 * use kernel\Facades\Storage;
 *
 * Storage::disk("local")->getFileInfo($fileKey);   // 取某磁盘再操作
 * $result = Storage::put($file, $fileKey);          // 直接走当前磁盘
 * $url    = Storage::url($fileKey);                 // 生成带签名访问 URL
 * ```
 *
 * 默认实例仅注册一块本地磁盘（{@see LocalStorage}），且未启用数据存储。
 * 需要多磁盘 / 云存储 / 文件元信息落库时，可在应用装配阶段用
 * {@see Facade::setInstance()} 替换默认实例：
 *
 * ```php
 * use kernel\Facades\Storage;
 * use kernel\Foundation\FileSystem\Storage\FileStorage as FileStorageAggregate;
 * use kernel\Foundation\FileSystem\Storage\LocalStorage;
 *
 * Storage::setInstance(
 *   (new FileStorageAggregate([
 *     "local" => new LocalStorage(),
 *     "cos"   => $cosDisk,
 *   ]))->enableDataSave()
 * );
 * ```
 *
 * 也可取出默认实例后按需扩展（`Storage::enableDataSave()`、`Storage::auth(true)` 等），
 * 或用 {@see Facade::clearInstance()} 清除后重新解析。
 *
 * 注：本门面转发到的底层是存储聚合类 `kernel\Foundation\FileSystem\Storage\FileStorage`
 * （此处别名为 FileStorageAggregate）。聚合类构造时会自动 `Storage::setInstance($this)`，
 * 因此装配阶段 `new FileStorage([...])` 即可让本门面就绪，无需手动注入。
 *
 * @method static array<string,\kernel\Foundation\FileSystem\Storage\AbstractStorage> disks() 获取全部磁盘（磁盘名 => 磁盘实例）
 * @method static \kernel\Foundation\FileSystem\Storage\AbstractStorage|null disk(string|null $name = null) 获取指定磁盘或当前使用磁盘
 * @method static \kernel\Foundation\FileSystem\Storage\AbstractStorage use(string|null $name = null) 切换当前使用磁盘
 * @method static \kernel\Foundation\FileSystem\Storage\FileStorage enableDataSave(\kernel\Model\FilesModel|null $model = null) 启用文件元信息落库（save/add/delete/exists 依赖）
 * @method static \kernel\Foundation\FileSystem\Storage\FileStorage|\kernel\Model\FilesModel|null model(\kernel\Model\FilesModel|null $model = null) 读取/设置文件数据模型
 * @method static \kernel\Foundation\FileSystem\Storage\FileStorage|boolean auth(boolean|null $val = null) 读取/开启请求签名鉴权
 * @method static \kernel\Foundation\FileSystem\Storage\FileStorage|boolean accessControl(boolean|null $val = null) 读取/开启访问控制
 * @method static \kernel\Foundation\FileSystem\Storage\FileStorage|mixed accessControlAuthId(mixed|null $val = null) 读取/设置访问控制认证身份
 * @method static array|false get(string $fileKey) 获取文件信息
 * @method static \kernel\Foundation\FileSystem\Storage\StorageFile|false put(array $file, string|null $saveFileName = null) 上传文件到当前磁盘
 * @method static \kernel\Foundation\FileSystem\Storage\StorageFile|false save(array $file, string|null $fileKeyOrSavePath = null, mixed|null $ownerId = null, mixed|null $ref = null, mixed|null $type = null, string $accessControl = "authenticated-read") 上传文件并写入元信息
 * @method static int|false add(string $key, string|null $sourceFileName = null, string|null $saveFileName = null, string|null $path = null, int|null $size = null, string|null $extension = null, string|null $mimeType = null, mixed|null $ownerId = null, string $accessControl = "authenticated-read", string $disk = "local", mixed|null $ref = null, mixed|null $type = null, int|null $width = null, int|null $height = null) 仅登记文件元信息
 * @method static mixed update(string $key, array $data) 更新文件元信息
 * @method static mixed updateAccessControl(string $key, string $val) 更新文件 ACL 访问标签
 * @method static boolean delete(string $key) 删除文件
 * @method static boolean exists(string $key) 判断文件是否存在
 * @method static string url(string $fileKey, array $urlParams = [], int $expires = 1800, boolean $withSignature = true) 生成文件访问 URL
 * @method static array createAuthParams(string $key, int $expires = 600, array $urlParams = [], array $headers = [], string $httpMethod = "get") 生成文件签名授权参数
 * @method static boolean|mixed verifySignature(string $fileKey, array $rawURLParams, array $rawHeaders = [], string $httpMethod = "get") 校验文件访问签名
 * @method static boolean|mixed verifyRequestSignature(string $key, boolean $silent = false) 从当前请求提取参数并校验签名
 * @method static boolean|mixed authorizeOperation(string $fileKey, string $operation = "read") 校验对文件执行读/写的授权
 * @method static boolean checkAccessControl(string $fileKey, string $authTag, mixed $ownerId, string $action = "read") 基于 ACL 标签判定操作是否允许
 * @method static string generateFileKey(string $extension) 生成带唯一前缀的文件键
 */
class Storage extends Facade
{
  /**
   * 兜底实例：默认注册一块本地磁盘（local），未启用数据存储
   *
   * 云存储 / 多磁盘 / 元信息落库等，可在应用装配阶段调用门面上的方法扩展该实例。
   *
   * @return object
   */
  protected static function resolve(): object
  {
    return new FileStorageAggregate([
      "local" => new LocalStorage(),
    ]);
  }
}
