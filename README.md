# Kernel

kernel 插件为 DiscuzX 而生，旨在更好的写 restful 接口，并且增加了控制器、模型、中间件，更好的开发 SPA 项目

## 目录结构

```
kernel/
├── Foundation/     # 核心（App、路由、数据库、HTTP 等）
├── DiscuzX/        # DiscuzX 平台适配（语言包、原生模型、存储驱动、控制器基类）
├── Commands/       # 内核命令（make:app / make:controller / make:model / make:middleware / schedule:run）
├── Controllers/    # 业务控制器（基类在 Foundation/Controller）
├── Middlewares/    # 全局中间件子类（基类在 Foundation/Middleware）
├── Models/         # 模型层
├── Services/       # 服务层（云 / 微信 / 支付 / 设置 / 认证）
├── Providers/      # 服务装配（把服务绑进应用容器；不默认注册，需要时在应用 Bootstrap 里 register）
├── Contexts/       # 请求上下文（每请求一份，如 AuthContext，永不跨应用）
├── Crons/          # 定时任务（Cron 子类）
├── Facades/        # 静态门面（只剩 Crons；业务服务一律容器取用）
├── Routes/         # 路由定义
├── Setup/          # 装配类（Console / Bootstrap）
├── Configs/        # 配置文件
├── Views/          # 视图模板
├── Assets/         # 静态资源
├── Data/           # 运行期数据（日志等）
├── vendor/         # Composer 第三方依赖
├── index.php       # Web 入口
├── console         # CLI 入口
└── composer.json   # Composer 配置（PSR-4: kernel\）
```

## Foundation 核心组件

| 组件 | 文件 | 说明 |
|------|------|------|
| App | `App.php` | 应用主类，负责初始化、中间件注册、路由匹配和请求分发 |
| [Lifecycle](ruyi-docs/docs/php/framework/lifecycle) | `Lifecycle.php` | 应用生命周期管理（bootUp/shutdown/error 三钩子：引导、关闭、错误） |
| Router | `Router.php` | 路由器，支持标准路由和 RESTful 风格 |
| [Provisioner](ruyi-docs/docs/php/framework/provisioner) | `Provisioner.php` | 生命周期编排器，管理应用安装、升级、回滚和卸载 |
| Config | `Config.php` | 配置管理（**一个应用一份实例**，`$app->config()` 取用；静态 `Config::of()` 仅用于无实例上下文） |
| [Event](ruyi-docs/docs/php/framework/event) | `Event.php` | 事件系统 |
| Cache | `Cache.php` | 缓存处理 |
| Log | `Log.php` | 日志处理 |
| Command | `Command.php` | 命令行命令基类 |
| Output | `Output.php` | 输出处理 |

### 子模块

| 目录 | 说明 |
|------|------|
| `Console/` | 控制台命令支持 |
| `Controllers/` | 控制器基类及请求/响应抽象 |
| `Middlewares/` | 全局中间件子类（Middleware 管理器与 MiddlewareBase 基类在 Foundation/Middleware） |
| `Data/` | 数据处理工具（数组、字符串、序列化等） |
| `Database/` | 数据库抽象层（PDO/MySQL、MongoDB、SQLite） |
| `Exception/` | 异常体系及错误码 |
| `Extension/` | 扩展管理机制 |
| `FileSystem/` | 文件系统总管理（路径计算与文件操作） |
| `FileSystem/Storage/` | 存储抽象（本地、阿里云 OSS、腾讯云 COS） |
| `FileSystem/Zip.php` | [Zip 压缩解压](ruyi-docs/docs/php/framework/zip)：目录打包与安全解压（zip slip / zip bomb 防护） |
| `HTTP/` | HTTP 客户端、请求/响应处理 |
| `Object/` | 基础对象模型 |
| `Result.php` | 标准化返回结果（Result，继承 Response） |
| `Validation/` | 数据验证 |
| `Facade.php` | [门面基类](ruyi-docs/docs/php/api/foundation/facade)：静态转发 + 单例/多例自动判定（`resolve()`/`accessor()`） |
| `Crontab/` | [定时任务](ruyi-docs/docs/php/api/foundation/crontab/cron)（`Cron` 基类 + `Crons` 管理器），由 `schedule:run` 经门面驱动 |

## 入口文件

### Web 入口 (`index.php`)

```php
$App = new App("myapp");
$App->setup(\myapp\Setup\Bootstrap::class);   // 装配：new FileSystem/Cache、按需 new Middlewares/Lifecycle 注入
$App->run();
```

组件（`config` / `request` / `router` / `middleware` / `lifeCycle`）都是「访问即构建」：
`$app->config()`、`$app->request()` 等首次调用才实例化，之后复用。所以装配类里**不必**为了「能用」而去 new 一遍；
只有在需要换成自己的实现时才注入（访问器每次调用先查容器，注入的一定生效）。

中间件与生命周期钩子仍统一在 `Setup/Bootstrap.php` 装配类中手动 `new Middleware` 后 `->set(...)`、
手动 `new Lifecycle` 后 `->onBootUp(...)` 等注册，再按类名注入：`$app->instance(Middleware::class, $middleware)`、
`$app->instance(Lifecycle::class, $lifeCycle)`。`Bootstrap` 构造接收当前 App 实例：`public function __construct($app)`。

### CLI 入口 (`console`)

命令行操作入口，加载 autoload 后执行脚本。

## 关键常量

| 常量/属性 | 说明 |
|------|------|
| `F_KERNEL` | 内核加载标识，各文件通过此常量防止直接访问 |
| `$app->appId()` | 当前 App 标识（实例方法；静态上下文用 `Applications::defaultId()`，未登记应用返回 null） |
| `App::kernelId()` | 内核标识（静态方法，从当前实例读取，未实例化返回 null） |
| `Path::project()` | 项目根目录路径（自动计算的静态 getter） |
| `Path::kernel()` | 内核根目录路径（自动计算的静态 getter） |
| `Path::root()` | App 根目录路径（自动计算的静态 getter） |
| `Path::data()` | App 数据目录路径（自动计算的静态 getter） |
| `Path::storage()` | App 存储目录路径（自动计算的静态 getter） |
| `Path::kernelDir()` | 内核目录相对路径（自动计算的静态 getter） |
| `Path::dir()` | App 目录相对路径（自动计算的静态 getter） |

> 路径体系已抽离到 `Path` 类（`kernel\Foundation\FileSystem\Path`，纯静态、无副作用），FileSystem 只保留文件操作。Path 无任何静态属性，7 个路径 getter 在每次静态方法调用时自动计算：`kernel` 为本类所在内核目录（永远正确）；`project` 在 DiscuzX 平台取 `DISCUZ_ROOT`（去尾斜杠）、普通项目为内核上级目录；`root`/`data`/`storage` 由内核同级目录与默认应用 ID（`Applications::defaultId()`）推导；`kernelDir`/`dir` 为对应绝对路径相对 `project` 的相对路径。`App` 构造在 `defineConstants()` 之后执行 `new FileSystem`（无参构造，构造时通过 `Path::root()` 确保 `data`/`storage` 目录存在；未实例化 App 时跳过目录创建）。依赖默认应用 ID 的 getter（`root`/`data`/`storage`/`dir`）在未实例化 App 时返回 null；DiscuzX 平台无需任何额外配置。FileSystem 承担文件操作：13 个方法（upload/createFile/deleteDirectory/clearFolder/copyFolder/getFileInfo/deleteFile/readFile/copyFile/moveFile/ensureDirectory/fileSize/cloneDirectory）已并入本类，原 FileManager 类已删除。

## 版本管理

通过 [Provisioner](ruyi-docs/docs/php/framework/provisioner) 实现增量升级：

- `.version` 文件存储完整版本号，位于 `{Path::data()}/.version`
- 升级脚本放在 `Upgrades/` 目录，按 `Upgrade_x_y_z.php` 命名
- 升级/回滚后自动持久化版本号

```php
$p = new Provisioner();
$p->upgrade('2.0.0');   // 升级
$p->rollback('1.0.0');  // 回滚
$p->getStatus();        // 查看状态
```

## 依赖

内核**零第三方依赖**，`composer.json` 仅声明 PSR-4 自动加载，`vendor/` 只有 `composer dump-autoload` 生成的轻量映射（约 44K）。云存储 SDK 由**业务应用按需安装**到自身的 `vendor/`：

| 功能 | 包名 | 安装命令（在应用目录执行） |
|------|------|------------------------------|
| 腾讯云 COS 对象存储 | `qcloud/cos-sdk-v5` | `composer require qcloud/cos-sdk-v5:2.*` |
| 腾讯云 STS 临时密钥 | `qcloud_sts/qcloud-sts-sdk` | `composer require qcloud_sts/qcloud-sts-sdk:^3.0` |
| 阿里云 OSS 对象存储 | `aliyuncs/oss-sdk-php` | `composer require aliyuncs/oss-sdk-php:^2.7` |
| 阿里云 STS 临时密钥 | `alibabacloud/sts-20150401` | `composer require alibabacloud/sts-20150401:^1.1` |

云存储类按需加载，未安装对应 SDK 且不使用该功能时无任何影响；使用时会抛 `Class not found` 提示先安装。详见[依赖按需安装](ruyi-docs/docs/php/framework/dependencies)。

## PSR-4 自动加载

```json
{
  "autoload": {
    "psr-4": {
      "kernel\\": ""
    }
  }
}
```

命名空间 `kernel\` 直接映射到 kernel 目录根。
