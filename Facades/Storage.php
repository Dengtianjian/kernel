<?php

namespace kernel\Facades;

use kernel\Foundation\Facade;
use kernel\Foundation\FileSystem\Storage\FileStorage as FileStorageAggregate;
use kernel\Foundation\FileSystem\Storage\Drivers\LocalStorage;

/**
 * 文件存储门面（存储聚合类的静态入口）
 *
 * 单例门面：继承 {@see Facade} 并覆写 {@see resolve()}，静态调用会被转发到解析出的实例上。
 * 解析出的底层实例是存储聚合类 {@see FileStorageAggregate}
 * （`kernel\Foundation\FileSystem\Storage\FileStorage`，聚合多磁盘 + 签名鉴权 + 访问控制 + 元信息落库）。
 *
 * 用法：
 * ```php
 * use kernel\Facades\Storage;
 *
 * Storage::disk("local")->get($fileKey);        // 取指定磁盘再操作（磁盘方法见 AbstractStorage）
 * Storage::put($uploadFile, "a/b.png");         // 用当前磁盘上传，返回 StorageFile
 * Storage::url($fileKey);                       // 访问地址：走本站路由中转（默认不带签名）
 * Storage::url($fileKey, [], 1800, true);       // 同上，附带签名（需先开启 auth()）
 * Storage::direct($fileKey);                    // 直连地址：远程磁盘为带签名的原生 URL（默认带签名）
 * Storage::enableDataSave()->auth(true);       // 取出默认实例后链式扩展
 * ```
 *
 * 装配：
 * - 默认实例（{@see resolve()}）**只注册一块本地磁盘（local）**，且**未启用数据存储**；
 * - 需要多磁盘 / 云存储 / 元信息落库时，在应用装配阶段替换实例：
 *   ```php
 *   Storage::setInstance((new FileStorageAggregate([
 *     "local" => new LocalStorage(),
 *     "cos"   => $cosDisk,
 *   ]))->enableDataSave());
 *   ```
 * - 也可直接 `new FileStorageAggregate([...])` —— 聚合类构造时会自行 `Storage::setInstance($this)`；
 *   用 {@see Facade::clearInstance()} 清除后可重新解析。
 *
 * 注意（转发到底层后的既有行为，非本门面逻辑）：
 * - {@see FileStorageAggregate::get()}：磁盘层返回 **false**（文件不存在）时会对 false 调 `toArray()`，
 *   属致命错误；上面示例里的 `disk()->get()` 走的是磁盘层方法（返回 `StorageFile|false`），不受影响。
 * - {@see FileStorageAggregate::url()}：`$withSignature` **默认 false**，要带签名必须显式传参，
 *   且仅在已开启签名鉴权（{@see FileStorageAggregate::auth()}）时才生效。
 * - {@see FileStorageAggregate::direct()}：取的是**非中转地址**，请求不再落到本站，因此**绕过本应用的
 *   签名鉴权与 ACL 判定**（权限改由磁盘自身决定，如桶/对象 ACL、文件系统权限）；其 `$withSignature`
 *   **默认 true**（与 `url()` 相反），传 false 得到公开地址（需桶为公共读）。
 *
 * 磁盘与开关：
 * @method static array<string,\kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage> disks() 读取全部磁盘（磁盘名 => 磁盘实例）
 * @method static \kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage|null disk(string|null $name = null) 取指定磁盘；不传则取当前使用磁盘
 * @method static \kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage use(string|null $name = null) 切换当前使用磁盘，返回该磁盘
 * @method static \kernel\Model\FilesModel|null model(\kernel\Model\FilesModel|null $model = null) 读取/设置文件数据模型
 * @method static FileStorageAggregate enableDataSave(\kernel\Model\FilesModel|null $model = null) 启用元信息落库（save/add/delete/exists 依赖）
 * @method static bool dataSave() 读取元信息落库是否启用
 * @method static FileStorageAggregate|bool auth(bool|null $val = null) 读取/开启请求签名鉴权
 * @method static bool authEnabled() 读取签名鉴权是否启用
 * @method static FileStorageAggregate|bool accessControl(bool|null $val = null, mixed $authId = null, bool|null $enableAuth = null) 读取/开启访问控制（开启时记录认证身份，`$authId` 可为闭包）
 * @method static bool accessControlEnabled() 读取访问控制是否启用
 * @method static FileStorageAggregate|mixed accessControlAuthId(mixed $val = null) 读取/设置访问控制的当前认证身份
 *
 * 文件操作：
 * @method static array|false get(string $fileKey) 取文件信息（⚠️ 磁盘层返回 false 时会 fatal，见类注释）
 * @method static \kernel\Foundation\FileSystem\Storage\StorageFile|false put(array $file, string|null $saveFileName = null) 上传到当前磁盘（只落盘、不写库）
 * @method static \kernel\Foundation\FileSystem\Storage\StorageFile|false save(array $file, string|null $fileKeyOrSavePath = null, mixed|null $ownerId = null, mixed|null $ref = null, mixed|null $type = null, string $accessControl = "authenticated-read") 上传并写入元信息（需已启用数据存储）
 * @method static int|false add(string $key, string|null $sourceFileName = null, string|null $saveFileName = null, string|null $path = null, int|null $size = null, string|null $extension = null, string|null $mimeType = null, mixed|null $ownerId = null, string $accessControl = "authenticated-read", string $disk = "local", mixed|null $ref = null, mixed|null $type = null, int|null $width = null, int|null $height = null) 仅登记元信息（不传文件内容），返回记录 ID
 * @method static mixed update(string $key, array $data) 更新元信息
 * @method static mixed updateAccessControl(string $key, string $val) 更新文件 ACL 标签（`update()` 的便捷封装）
 * @method static bool delete(string $key) 删除文件（先删库记录、再删磁盘文件）
 * @method static bool exists(string $key) 判断文件是否存在（启用落库时先查库）
 *
 * 访问地址：
 * @method static string url(string $fileKey, array $urlParams = [], int $expires = 1800, bool $withSignature = false) 本站路由中转地址（**默认不带签名**；仅带签名请求才附带，且需已开启 `auth()`）
 * @method static string|false direct(string $fileKey, array $urlParams = [], int $expires = 1800, bool $withSignature = true) **直连（非中转）地址**：远程磁盘为带签名的原生 URL、本地为文件系统路径（**默认带磁盘签名**；绕过本门面鉴权/ACL；无可用磁盘时返回 false）
 *
 * 签名与授权：
 * @method static array createAuthParams(string $key, int $expires = 600, array $urlParams = [], array $headers = [], string $httpMethod = "get", string|null $action = null) 生成签名授权参数（传 `$action` 会把动作写入签名，限定其只能用于该动作）
 * @method static bool|mixed verifySignature(string $fileKey, array $rawURLParams, array $rawHeaders = [], string $httpMethod = "get", string|array|null $action = null) 校验签名（`$action`：字符串=精确匹配、数组=允许集合、null=不限制）
 * @method static bool|mixed verifyRequestSignature(string $key, bool $silent = false, string|array|null $action = null) 从当前请求取参数并校验签名
 * @method static bool|mixed authorizeOperation(string $fileKey, string $operation = "read", string|array|null $action = null) 校验读/写授权（启用落库时按归属者 + ACL，否则仅校验签名）
 * @method static bool checkAccessControl(string $fileKey, string $authTag, mixed $ownerId, string $operation = "read", string|array|null $action = null) 按 ACL 标签判定操作是否允许（`$operation`：read/write）
 *
 * 路由与工具（静态方法，经门面转发到实例上调用）：
 * @method static FileStorageAggregate registerRoute(string $nameOrUri, string|null $methodOrController = null, string|null $controller = null) 注册文件路由（快捷名：get/auth/upload/update/delete/preview/download；也可传自定义 URI）
 * @method static string generateFileKey(string $extension) 生成带唯一前缀的文件键，如 `66f3a1b2c3d4.jpg`
 * @method static string buildFileKey(string $filePath, string $fileName) 拼接目录与文件名，统一为 `/` 分隔的文件键
 * @method static string fileKeyRoutePattern() 取文件键的路由匹配正则
 * @method static string buildFileKeyRouteUri(string $prefix, string|null $suffix = null) 构建带文件键占位符的路由 URI
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
