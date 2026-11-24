# gstudio_kernel — DiscuzX 基础插件

## 定位

gstudio_kernel 是运行于 **Discuz! X3.5** 平台的**基础插件**（非业务插件）：
自身不提供任何业务功能、不注册业务入口（无 `.inc.php` 业务模块、无 hook 嵌入点），
职责是为其他业务插件提供统一的 RESTful 接口开发能力——控制器 / 模型 / 中间件 /
路由 / 数据库 / 存储 / 认证 / 支付等基础组件，由业务插件依赖复用。

与 Discuz 官方体系的协作方式（`DiscuzX/` 适配层）：

- 数据表读写走官方 `C::t()` 接口；
- 语言包按站点编码（GBK / UTF-8）加载官方体系内的语言文件；
- 上传走官方 `discuz_upload` 白名单与落盘校验（`DiscuzX/Foundation/Storage/`）；
- 会员注册 / 登录复用 Discuz 官方 `common_member` 体系与 `updatemembercount` 等接口。

## 目录结构

```
gstudio_kernel/
├── Foundation/     # 基础组件（应用、路由、数据库、HTTP、文件系统、缓存等）
├── DiscuzX/        # DiscuzX 平台适配（语言包、原生模型、存储驱动、控制器基类）
├── Commands/       # 命令行工具（make:app / make:controller / make:model / make:middleware / package 等）
├── Controllers/    # 文件相关控制器（基类在 Foundation/Controller）
├── Middlewares/    # 全局中间件子类（基类在 Foundation/Middleware）
├── Models/         # 模型层
├── Services/       # 服务层（云存储 / 微信 / 支付 / 设置 / 认证）
├── Providers/      # 服务装配（把服务绑进应用容器；不默认注册，需要时在应用 Bootstrap 里 register）
├── Contexts/       # 请求上下文（每请求一份，如 AuthContext，永不跨应用）
├── Crons/          # 定时任务（Cron 子类）
├── Facades/        # 静态门面
├── Routes/         # 路由定义
├── Setup/          # 装配类（Console / Bootstrap）
├── Configs/        # 配置文件
├── Views/          # 视图模板
├── Assets/         # 静态资源
├── Data/           # 运行期数据（日志等）
├── index.php       # Web 入口
├── console         # CLI 入口
└── composer.json   # Composer 配置（PSR-4: kernel\）
```

## Foundation 基础组件

| 组件 | 文件 | 说明 |
|------|------|------|
| App | `App.php` | 应用主类，负责初始化、中间件注册、路由匹配和请求分发 |
| Lifecycle | `Builtin/Lifecycle.php` | 应用生命周期管理（bootUp/shutdown/error 三钩子） |
| Router | `Router/Router.php` | 路由器，支持标准路由和 RESTful 风格 |
| Config | `Builtin/Config.php` | 配置管理（一个应用一份实例，`$app->config()` 取用） |
| Event | `Builtin/Event.php` | 事件系统 |
| Cache | `Builtin/Cache.php` | 缓存处理 |
| Log | `Builtin/Log.php` | 日志处理 |
| Command | `Console/Command.php` | 命令行命令基类 |
| Output | `Console/Output.php` | 输出处理 |

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
| `FileSystem/` | 文件系统（路径计算与文件操作，含扩展名校验工具） |
| `FileSystem/Storage/` | 存储抽象（本地、阿里云 OSS、腾讯云 COS） |
| `FileSystem/Zip.php` | Zip 压缩解压：目录打包与安全解压（zip slip / zip bomb 防护） |
| `HTTP/` | HTTP 客户端、请求/响应处理 |
| `Object/` | 基础对象模型 |
| `Validation/` | 数据验证 |
| `Facade.php` | 门面基类：静态转发 + 单例/多例自动判定 |
| `Crontab/` | 定时任务（`Cron` 基类 + `Crons` 管理器），由 `schedule:run` 经门面驱动 |

## 入口文件

### Web 入口（`index.php`）

```php
$App = new App("myapp");
$App->setup(\myapp\Setup\Bootstrap::class);   // 装配：按需注入中间件 / 生命周期钩子
$App->run();
```

组件（`config` / `request` / `router` / `middleware` / `lifeCycle`）都是「访问即构建」：
`$app->config()`、`$app->request()` 等首次调用才实例化，之后复用。装配类里
**不必**为了「能用」而手动 new 一遍；只有在需要换成自己的实现时才注入。

### CLI 入口（`console`）

命令行操作入口，加载 autoload 后执行命令分发。

## 关键常量与路径

| 常量/方法 | 说明 |
|------|------|
| `Path::project()` | 项目根目录路径（DiscuzX 平台取 `DISCUZ_ROOT`） |
| `Path::kernel()` | 本基础插件目录路径（自动计算的静态 getter） |
| `Path::root()` | 业务应用根目录路径（按默认应用 ID 推导） |
| `Path::data()` | 业务应用数据目录路径 |
| `Path::storage()` | 业务应用存储目录路径 |
| `$app->appId()` | 当前应用标识（实例方法；静态上下文用 `Applications::defaultId()`） |

## 运行模式

`mode` 决定错误详情、测试路由、CORS 放宽、`_method` / `x-async` 覆盖等开发期行为是否生效。

**基线即生产**：`Configs/Config.php` 默认 `production`，发布包直接用基线，打包过程不改写任何配置。

**本地开发覆盖**：把模板复制一份即可切回开发态——

```bash
cp Configs/Config.example.php Configs/Config.local.php
```

模板内容即 `["mode" => "development"]`。`Config.local.php` 在 `.gitignore` 与 `PackageCommand::IGNORE`
里，既不进仓库也不随包发布；`Config.example.php` 本身同样被排除在打包之外。配置分五层读取，
后读覆盖先读：`Config.php → Config.development.php → Config.local.php → Config.production.php → Config.release.php`。

## 依赖

本插件**零第三方依赖**，`composer.json` 仅声明 PSR-4 自动加载。云存储 SDK 由
**业务应用按需安装**到自身的 `vendor/`：

| 功能 | 包名 |
|------|------|
| 腾讯云 COS 对象存储 | `qcloud/cos-sdk-v5` |
| 腾讯云 STS 临时密钥 | `qcloud_sts/qcloud-sts-sdk` |
| 阿里云 OSS 对象存储 | `aliyuncs/oss-sdk-php` |
| 阿里云 STS 临时密钥 | `alibabacloud/sts-20150401` |

云存储类按需加载，未安装对应 SDK 且不使用该功能时无任何影响。

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

命名空间 `kernel\` 直接映射到本插件目录根。
