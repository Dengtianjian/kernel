<?php

namespace kernel\Facades;

use kernel\Foundation\Facade;
use kernel\Foundation\FileSystem\Storage\FileStorage as FileStorageAggregate;
use kernel\Foundation\FileSystem\Storage\Drivers\LocalStorage;

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
 * Storage::disk("local")->get($fileKey);            // 取某磁盘再操作（磁盘方法见 AbstractStorage）
 * $file   = Storage::put($uploadFile, "a/b.png");   // 直接走当前磁盘（上传，返回 StorageFile）
 * $url    = Storage::url($fileKey);                 // 生成文件访问 URL（**默认不带签名**）
 * $signed = Storage::url($fileKey, [], 1800, true); // 需要带签名时显式传第 4 参
 * $direct = Storage::direct($fileKey);              // 直连地址（非中转：远程为带签名的原生 URL，本地为文件系统路径）
 * ```
 *
 * 默认实例仅注册一块本地磁盘（{@see LocalStorage}），且未启用数据存储。
 * 需要多磁盘 / 云存储 / 文件元信息落库时，可在应用装配阶段用
 * {@see Facade::setInstance()} 替换默认实例：
 *
 * ```php
 * use kernel\Facades\Storage;
 * use kernel\Foundation\FileSystem\Storage\FileStorage as FileStorageAggregate;
 * use kernel\Foundation\FileSystem\Storage\Drivers\LocalStorage;
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
 * 另需留意（与磁盘层有关，非本门面逻辑）：
 * - {@see FileStorageAggregate::get()} 在**磁盘层返回 false**（文件不存在）时会直接对 false 调 `toArray()`，
 *   属致命错误；上面那句 `disk()->get()` 取的是磁盘层方法（返回 `StorageFile|false`，不会 fatal）。
 * - {@see FileStorageAggregate::url()} 的 `$withSignature` **默认 false**，要带签名必须显式传参。
 * - {@see FileStorageAggregate::direct()} 取的是**非中转地址**：请求不再落到本应用，因此**绕过本门面的
 *   签名鉴权与 ACL 判定**（权限改由磁盘自身决定，如桶/对象 ACL、文件系统权限）；其 `$withSignature`
 *   **默认 true**（与 `url()` 相反），传 false 得到公开地址（需桶为公共读）。
 *
 * @method static array<string,\kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage> disks() 获取全部磁盘（磁盘名 => 磁盘实例）
 * @method static \kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage|null disk(string|null $name = null) 获取指定磁盘或当前使用磁盘
 * @method static \kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage use(string|null $name = null) 切换当前使用磁盘
 * @method static \kernel\Foundation\FileSystem\Storage\FileStorage enableDataSave(\kernel\Model\FilesModel|null $model = null) 启用文件元信息落库（save/add/delete/exists 依赖）
 * @method static boolean dataSave() 读取文件数据存储是否启用
 * @method static \kernel\Foundation\FileSystem\Storage\FileStorage|\kernel\Model\FilesModel|null model(\kernel\Model\FilesModel|null $model = null) 读取/设置文件数据模型
 * @method static \kernel\Foundation\FileSystem\Storage\FileStorage|boolean auth(boolean|null $val = null) 读取/开启请求签名鉴权
 * @method static boolean authEnabled() 读取请求签名鉴权是否启用
 * @method static \kernel\Foundation\FileSystem\Storage\FileStorage|boolean accessControl(boolean|null $val = null, mixed|null $authId = null, boolean|null $enableAuth = null) 读取/开启访问控制（开启时记录认证身份；$authId 可为闭包）
 * @method static boolean accessControlEnabled() 读取访问控制是否启用
 * @method static \kernel\Foundation\FileSystem\Storage\FileStorage|mixed accessControlAuthId(mixed|null $val = null) 读取/设置访问控制认证身份
 * @method static array|false get(string $fileKey) 获取文件信息（注意：磁盘层返回 false 时会 fatal，见类注释）
 * @method static \kernel\Foundation\FileSystem\Storage\StorageFile|false put(array $file, string|null $saveFileName = null) 上传文件到当前磁盘（只落盘、不写库）
 * @method static \kernel\Foundation\FileSystem\Storage\StorageFile|false save(array $file, string|null $fileKeyOrSavePath = null, mixed|null $ownerId = null, mixed|null $ref = null, mixed|null $type = null, string $accessControl = "authenticated-read") 上传文件并写入元信息
 * @method static int|false add(string $key, string|null $sourceFileName = null, string|null $saveFileName = null, string|null $path = null, int|null $size = null, string|null $extension = null, string|null $mimeType = null, mixed|null $ownerId = null, string $accessControl = "authenticated-read", string $disk = "local", mixed|null $ref = null, mixed|null $type = null, int|null $width = null, int|null $height = null) 仅登记文件元信息
 * @method static mixed update(string $key, array $data) 更新文件元信息
 * @method static mixed updateAccessControl(string $key, string $val) 更新文件 ACL 访问标签
 * @method static boolean delete(string $key) 删除文件
 * @method static boolean exists(string $key) 判断文件是否存在
 * @method static string url(string $fileKey, array $urlParams = [], int $expires = 1800, boolean $withSignature = false) 生成文件访问 URL（默认**不带**签名；`$withSignature` 为 true 且已开启签名鉴权时才附带）
 * @method static string|false direct(string $fileKey, array $urlParams = [], int $expires = 1800, boolean $withSignature = true) 获取文件的**直连（非中转）地址**（委托当前磁盘的 `url()`：远程为带签名的原生 URL、本地为文件系统路径；**绕过本门面的签名/ACL 判定**；默认**带**磁盘签名；无可用磁盘时返回 false）
 * @method static array createAuthParams(string $key, int $expires = 600, array $urlParams = [], array $headers = [], string $httpMethod = "get", string|null $action = null) 生成文件签名授权参数（传 `$action` 会把动作写入签名，用于限定该签名只能在对应动作使用）
 * @method static boolean|mixed verifySignature(string $fileKey, array $rawURLParams, array $rawHeaders = [], string $httpMethod = "get", string|array|null $action = null) 校验文件访问签名（`$action` 为允许动作：字符串=精确匹配、数组=允许集合、null=不限制）
 * @method static boolean|mixed verifyRequestSignature(string $key, boolean $silent = false, string|array|null $action = null) 从当前请求提取参数并校验签名
 * @method static boolean|mixed authorizeOperation(string $fileKey, string $operation = "read", string|array|null $action = null) 校验对文件执行读/写的授权
 * @method static boolean checkAccessControl(string $fileKey, string $authTag, mixed $ownerId, string $operation = "read", string|array|null $action = null) 基于 ACL 标签判定操作是否允许（`$operation`：read/write；`$action` 为允许动作，透传给签名校验）
 * @method static string generateFileKey(string $extension) 生成带唯一前缀的文件键
 * @method static string buildFileKey(string $filePath, string $fileName) 拼接目录与文件名，构建统一正斜杠分隔的文件键
 * @method static string fileKeyRoutePattern() 获取文件键的路由匹配正则
 * @method static string buildFileKeyRouteUri(string $prefix, string|null $suffix = null) 构建带文件键占位符的路由 URI
 * @method static \kernel\Foundation\FileSystem\Storage\FileStorage registerRoute(string $nameOrUri, string|null $methodOrController = null, string|null $controller = null) 注册文件相关路由（快捷名：get/auth/upload/update/delete/preview/download；也可传自定义 URI）
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
  protected static function resolve()
  {
    return new FileStorageAggregate([
      "local" => new LocalStorage(),
    ]);
  }
}
