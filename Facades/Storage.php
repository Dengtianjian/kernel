<?php

namespace kernel\Facades;

use kernel\Foundation\Facade;
use kernel\Foundation\FileSystem\Storage\FileStorage as FileStorageAggregate;
use kernel\Foundation\FileSystem\Storage\Drivers\LocalStorage;

/**
 * 文件存储门面：存储聚合类 {@see FileStorageAggregate} 的静态入口
 *
 * ## 它是什么
 *
 * 本类自身几乎不含逻辑，只是一个**静态转发壳**：继承 {@see Facade} 并覆写 {@see resolve()}，
 * 因此按 {@see Facade::singleton()} 的判定规则属于**单例门面**。
 * 所有静态调用（`Storage::xxx()`）经 {@see Facade::__callStatic()} 转发到底层实例上，
 * 底层实例即 {@see FileStorageAggregate}（`kernel\Foundation\FileSystem\Storage\FileStorage`，
 * 聚合多磁盘 + 签名鉴权 + 访问控制 + 元信息落库）。
 *
 * 转发规则：
 * - 首次静态调用时 {@see Facade::accessor()} 触发本类 {@see resolve()} 创建实例并缓存到门面注册表，
 *   后续调用复用同一实例（即「单例」）；
 * - 静态调用的返回值就是底层实例方法的返回值，链式写法（`$this` 返回）可继续串下去。
 *
 * ## 装配：默认实例 vs 自定义实例
 *
 * {@see resolve()} 给出的**兜底实例只有一块本地磁盘 `local`，且未启用元信息落库**，
 * 适合「临时用一下」的场景。真实应用应在装配阶段（`app/Setup/Bootstrap.php`）替换实例：
 *
 * ```php
 * use kernel\Facades\Storage;
 * use kernel\Foundation\FileSystem\Storage\FileStorage;
 *
 * Storage::setInstance((new FileStorage([
 *   "local" => new LocalStorage(),
 *   "cos"   => $cosDisk,          // 腾讯云 COS / 阿里云 OSS 等 AbstractStorage 实现
 * ]))->enableDataSave());          // 同时启用元信息落库
 * ```
 *
 * 等价写法：直接 `new FileStorage([...])` 即可 —— 聚合类构造内会自行调用 `Storage::setInstance($this)`，
 * 因此**一次运行只应 new 一次**；需要重新解析时用 {@see Facade::clearInstance()} 清空注册表，
 * 或用 {@see Facade::getInstance()} 取当前实例（不触发解析）。
 *
 * ## 三个开关（决定其余方法的行为）
 *
 * | 开关 | 开启方式 | 影响 |
 * |---|---|---|
 * | 元信息落库 `$dataSave` | {@see FileStorageAggregate::enableDataSave()} | `save()` / `add()` / `update()` / `delete()` / `exists()` 会读写 {@see \kernel\Model\FilesModel}；未开启调用 `save()` / `add()` / `update()` 会抛 {@see \kernel\Foundation\Exception\Error} |
 * | 请求签名鉴权 `$authEnabled` | {@see FileStorageAggregate::auth()} | 开启后 {@see FileStorageAggregate::verifyRequestSignature()} 会校验 URL 签名；{@see FileStorageAggregate::url()} 只有在开启时才可能带签名 |
 * | 访问控制 `$accessControlEnabled` | {@see FileStorageAggregate::accessControl()} | 按 ACL 标签判定读 / 写权限（{@see FileStorageAggregate::checkAccessControl()}）；第三参可联动开启 / 关闭签名鉴权 |
 *
 * ACL 标签常量（{@see FileStorageAggregate} 上定义）：
 * `PRIVATE` / `PUBLIC_READ` / `PUBLIC_READ_WRITE` / `AUTHENTICATED_READ` / `AUTHENTICATED_READ_WRITE`；
 * 归属者本人恒放行，其余按标签判定，「只读」标签遇写操作一律拒绝。
 *
 * ## 两类访问地址（最易混淆）
 *
 * - {@see FileStorageAggregate::url()}：返回 {@see \kernel\Foundation\FileSystem\Storage\StorageURL}
 *   对象（**延迟构建**：参数可通过链式方法继续调整，签名在最后一次 `toString()` 时才签发），
 *   拼装为本站路由**中转地址** `{baseURL}/{prefix}/{fileKey}`，请求先落到本站控制器，
 *   可叠加本应用的签名鉴权与 ACL；`$withSignature` **默认 false**，要签名必须显式传 `true`（且已开 `auth()`）。
 *   给按动作校验的端点（`info` / `preview` / `download` / `delete` / `update` / `upload`）生成链接时
 *   **必须传 `$action`**，否则校验报「缺少参数」。
 * - {@see FileStorageAggregate::direct()}：**直连、非中转**地址，远程磁盘为带签名的原生 URL、
 *   本地磁盘为文件系统路径；请求不再落到本站，**绕过本应用的签名鉴权与 ACL 判定**，
 *   权限改由磁盘自身决定（桶 / 对象 ACL、文件系统权限）。`$withSignature` **默认 true**（与 `url()` 相反）。
 *
 * ## 常用示例
 *
 * ```php
 * use kernel\Facades\Storage;
 *
 * Storage::disk("local")->get($fileKey);        // 取指定磁盘再操作（磁盘方法见 AbstractStorage）
 * Storage::use("cos");                          // 切换当前磁盘，后续 put/delete 作用于它
 * Storage::put($uploadFile, "a/b.png");         // 只落盘、不写库，返回 StorageFile
 * Storage::save($uploadFile, "a/b", $userId);   // 落盘 + 写库（需已 enableDataSave()）
 * Storage::exists("a/b.png");                   // 存在性（启用落库时先查库）
 *
 * Storage::url($fileKey);                       // 中转地址（默认不带签名）
 * Storage::url($fileKey, [], 1800, true, "download"); // 带签名且绑定 download 动作（需先 auth(true)）
 *
 * // 链式：返回 StorageURL，参数可后调，签名在 toString() 时才签发
 * $url = Storage::url($fileKey)->withSignature()->action("preview")->expires(600);
 * $url->urlParams(["v" => 2]);                  // 构建前增改参数都安全（会一并参与签名）
 * echo $url;                                    // 触发构建，输出地址字符串
 * Storage::direct($fileKey);                    // 直连地址（默认带磁盘签名，绕过本站鉴权）
 *
 * Storage::enableDataSave()->auth(true);        // 链式开启落库与签名鉴权
 * Storage::registerRoute("get");                // 注册一条文件路由（见下）
 * ```
 *
 * ## 易踩的坑（转发到底层后的既有行为，非本门面逻辑）
 *
 * - {@see FileStorageAggregate::get()}：磁盘层返回 **false**（文件不存在）时会对 false 调 `toArray()`，
 *   属致命错误；示例里的 `disk()->get()` 走的是磁盘层方法（返回 `StorageFile|false`），不受影响。
 * - {@see FileStorageAggregate::url()} 的 `$urlParams` 会**同时**参与签名与最终 query，
 *   客户端必须原样携带；且不要在 `$urlParams` 里传 `auth` 键（会被剔除却已参与签名，必然验签失败）。
 *   文件键中的空格 / 中文等特殊字符也需调用方自行 urlencode。
 * - {@see FileStorageAggregate::save()} 第二参**含扩展名**即视为完整文件键，不含则视为目录、
 *   自动生成唯一文件键；同一文件键会先删再插（幂等）。
 * - {@see FileStorageAggregate::delete()} 返回的是**磁盘删除**是否成功，启用落库时库记录已被物理删除
 *   （不可恢复），且失败不抛出、只返回 false。
 * - {@see FileStorageAggregate::disk()} 传入不存在的磁盘名会直接取数组越界值（未做存在性校验）；
 *   {@see FileStorageAggregate::use()} 同理。
 *
 * ## 方法总览
 *
 * 磁盘与开关：
 * @method static array<string,\kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage> disks() 读取全部磁盘（磁盘名 => 磁盘实例）
 * @method static \kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage|null disk(string|null $name = null) 取指定磁盘；不传则取当前使用磁盘（`$useDisk`），仍无则回退默认磁盘，**不校验磁盘名是否存在**
 * @method static \kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage use(string|null $name = null) 切换当前使用磁盘并返回该磁盘实例；后续 put()/delete()/exists()/direct() 都作用于它
 * @method static FileStorageAggregate enableDataSave() 启用元信息落库（save/add/update/delete/exists 依赖它），返回 $this 可链式
 * @method static bool dataSave() 读取元信息落库是否已启用（只读版，与 enableDataSave() 对应）
 * @method static FileStorageAggregate|bool auth(bool|null $val = null) 读取 / 设置请求签名鉴权开关；传值设置并返回 $this，不传返回当前开关值
 * @method static bool authEnabled() 读取签名鉴权是否已启用
 * @method static FileStorageAggregate|bool accessControl(bool|null $val = null, mixed $authId = null, bool|null $enableAuth = null) 读取 / 设置访问控制开关；开启时一并记录当前认证身份（`$authId` 可为闭包，取用时才调用），`$enableAuth` 不为 null 时联动 auth()
 * @method static bool accessControlEnabled() 读取访问控制是否已启用
 * @method static FileStorageAggregate|mixed accessControlAuthId(mixed $val = null) 读取 / 设置访问控制所用的当前认证身份；读取时若其为闭包会先调用
 *
 * 文件操作：
 * @method static array|false get(string $fileKey) 取文件信息（⚠️ 磁盘层返回 false 时会对 false 调 toArray() 而 fatal，见上方「易踩的坑」）
 * @method static \kernel\Foundation\FileSystem\Storage\StorageFile|false put(array $file, string|null $saveFileName = null) 上传文件到**当前磁盘**：只落盘、不写库；返回的 owner_id/ref/type 均为 null、ACL 固定 public-read
 * @method static \kernel\Foundation\FileSystem\Storage\StorageFile|false save(array $file, string|null $fileKeyOrSavePath = null, mixed|null $ownerId = null, mixed|null $ref = null, mixed|null $type = null, string $accessControl = "authenticated-read") 上传并把元信息写库（**需已 enableDataSave()**，否则抛 Error）；第二参含扩展名=完整文件键、不含=目录（自动生成唯一键）
 * @method static int|false add(string $key, string|null $sourceFileName = null, string|null $saveFileName = null, string|null $path = null, int|null $size = null, string|null $extension = null, string|null $mimeType = null, mixed|null $ownerId = null, string $accessControl = "authenticated-read", string $disk = "local", mixed|null $ref = null, mixed|null $type = null, int|null $width = null, int|null $height = null) 仅登记元信息（文件内容已在外部落盘时使用），返回记录 ID
 * @method static mixed update(string $key, array $data) 更新元信息（不改动磁盘文件）；记录不存在返回 403 错误态
 * @method static mixed updateAccessControl(string $key, string $val) 更新文件 ACL 标签（`update()` 的便捷封装，等价只传 access_control）
 * @method static bool delete(string $key) 删除文件：启用落库时先**物理删除**库记录，再删当前磁盘文件；返回磁盘删除是否成功
 * @method static bool exists(string $key) 判断文件是否存在：启用落库时先查库（库里没有直接 false），再问磁盘
 *
 * 访问地址：
 * @method static \kernel\Foundation\FileSystem\Storage\StorageURL url(string $fileKey, array $urlParams = [], int $expires = 1800, bool $withSignature = false, string|null $action = null) **中转地址**（**延迟构建**，返回 StorageURL 对象）：参数可继续用 `->urlParams()` / `->expires()` / `->withSignature()` / `->action()` 链式调整，`toString()` / 当字符串用时才拼装并签发签名；默认不带签名（需 `$withSignature=true` 且已开 `auth()`）；按动作校验的端点**必须传 `$action`**
 * @method static string|false direct(string $fileKey, array $urlParams = [], int $expires = 1800, bool $withSignature = true) **直连（非中转）地址**：远程磁盘为带签名的原生 URL、本地磁盘为文件系统路径（**默认带磁盘签名**；绕过本应用的鉴权与 ACL；无可用磁盘时返回错误态）
 *
 * 签名与授权：
 * @method static array createAuthParams(string $key, int $expires = 600, array $urlParams = [], array $headers = [], string $httpMethod = "get", string|null $action = null) 签发签名参数（sign-algorithm/sign-time/key-time/header-list/signature/url-param-list[，action]）；`$key` 为空抛 Error，传 `$action` 会把动作写入签名以限定用途
 * @method static bool|mixed verifySignature(string $fileKey, array $rawURLParams, array $rawHeaders = [], string $httpMethod = "get", string|array|null $action = null) 校验签名参数：先查必填参数与时效，再比对签名；`$action` 字符串=精确匹配、数组=允许集合、null=不限制；通过返回 true，否则返回错误态（403 系列错误码）
 * @method static bool|mixed verifyRequestSignature(string $key, bool $silent = false, string|array|null $action = null) 从**当前请求**取 query/header/method 后转交 verifySignature()；未开启鉴权直接放行返回 true；`$silent` 为 true 时返回错误状态码而非错误态
 * @method static bool|mixed authorizeOperation(string $fileKey, string $operation = "read", string|array|null $action = null) 校验对文件的读 / 写授权：启用落库时按「归属者 + ACL」判定（{@see FileStorageAggregate::checkAccessControl()}），未启用落库时退化为仅校验请求签名
 * @method static bool checkAccessControl(string $fileKey, string $authTag, mixed $ownerId, string $operation = "read", string|array|null $action = null) 按 ACL 标签判定操作是否允许（`$operation`：read/write）；仅在「已启用落库 + 已启用访问控制」时生效，否则一律放行
 *
 * 路由与工具：
 * @method static FileStorageAggregate registerRoute(string $nameOrUri, string|null $methodOrController = null, string|null $controller = null) 注册文件路由。快捷名：get/auth/upload/update/delete/preview/download（HTTP 方法与 URI 后缀内置，第二参可覆盖默认控制器）；也可传自定义 URI（此时第二参为 HTTP 方法、第三参为控制器）
 * @method static string generateFileKey(string $extension) 生成带唯一前缀的文件键，如 `66f3a1b2c3d4.jpg`
 * @method static string buildFileKey(string $filePath, string $fileName) 拼接目录与文件名，并把分隔符统一为 `/`（跨平台一致的文件键）
 * @method static string fileKeyRoutePattern() 取文件键的路由匹配正则（可匹配含相对目录的键，如 "dir/file.txt"）
 * @method static string buildFileKeyRouteUri(string $prefix, string|null $suffix = null) 构建带 `{fileKey:...}` 占位符的路由 URI，形如 `{prefix}/{fileKey:...}[/suffix]`
 *
 * @see \kernel\Foundation\FileSystem\Storage\FileStorage 底层存储聚合类（含全部实现与常量）
 * @see \kernel\Foundation\FileSystem\Storage\Drivers\AbstractStorage 磁盘驱动抽象（本地 / COS / OSS / FTP）
 * @see \kernel\Foundation\Facade 门面基类（setInstance / getInstance / clearInstance / __callStatic）
 */
class Storage extends Facade
{
  /**
   * 兜底实例：仅注册一块本地磁盘（local），且未启用元信息落库
   *
   * 仅在门面注册表为空（尚未 {@see Facade::setInstance()} 注入自定义实例）时被调用一次，
   * 结果会被缓存复用。真实应用应在装配阶段用 {@see Facade::setInstance()} 注入
   * 带多磁盘 / 云存储 / 元信息落库的 {@see FileStorageAggregate} 实例。
   *
   * 注意：聚合类构造时会自行 `Storage::setInstance($this)`，因此本方法创建的实例同样会写入注册表。
   *
   * @return FileStorageAggregate
   */
  protected static function resolve()
  {
    return new FileStorageAggregate([
      "local" => new LocalStorage(),
    ]);
  }
}
