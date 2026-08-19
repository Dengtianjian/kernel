# Kernel

天衍 PHP 框架，提供路由、数据库、HTTP、存储、事件等基础设施，App 基于此内核构建自己的业务逻辑。

## 目录结构

```
kernel/
├── Foundation/         # 框架核心（App、路由、数据库、HTTP 等）
├── Platform/           # 平台适配层（DiscuzX）
├── Controller/         # 控制器
├── Middleware/         # 全局中间件
├── Model/              # 模型层
├── Service/            # 服务层
├── Views/              # 视图模板
├── Configs/            # 配置文件
├── Routes/             # 路由定义
├── Langs/              # 语言包
├── Traits/             # Trait 复用
├── Iuu/                # 插件标识相关
├── Assets/             # 静态资源
├── vendor/             # Composer 第三方依赖
├── index.php           # Web 入口
├── console             # CLI 入口
├── composer.json       # Composer 配置（PSR-4: kernel\）
└── ErrorCodes.php      # 错误码定义
```

## Foundation 核心组件

| 组件 | 文件 | 说明 |
|------|------|------|
| App | `App.php` | 应用主类，负责初始化、中间件注册、路由匹配和请求分发 |
| Router | `Router.php` | 路由器，支持标准路由和 RESTful 风格 |
| [Provisioner](ruyi-docs/docs/php/framework/provisioner) | `Provisioner.php` | 生命周期编排器，管理应用安装、升级、回滚和卸载 |
| Config | `Config.php` | 配置管理 |
| [Event](ruyi-docs/docs/php/framework/event) | `Event.php` | 事件系统 |
| Cache | `Cache.php` | 缓存处理 |
| Log | `Log.php` | 日志处理 |
| Command | `Command.php` | 命令行命令基类 |
| Lang | `Lang.php` | 语言包管理 |
| Middleware | `Middleware.php` | 中间件基类 |
| Output | `Output.php` | 输出处理 |

### 子模块

| 目录 | 说明 |
|------|------|
| `Console/` | 控制台命令支持 |
| `Controller/` | 控制器基类及请求/响应抽象 |
| `Data/` | 数据处理工具（数组、字符串、序列化等） |
| `Database/` | 数据库抽象层（PDO/MySQL、MongoDB、SQLite） |
| `Exception/` | 异常体系及错误码 |
| `Extension/` | 扩展管理机制 |
| `FileSystem/` | 文件系统总管理（路径计算与文件操作） |
| `FileSystem/Storage/` | 存储抽象（本地、阿里云 OSS、腾讯云 COS） |
| `HTTP/` | HTTP 客户端、请求/响应处理 |
| `Network/` | 网络请求工具 |
| `Object/` | 基础对象模型 |
| `ReturnResult/` | 标准化返回结果 |
| `Validation/` | 数据验证框架 |

## 入口文件

### Web 入口 (`index.php`)

```php
$App = new App("myapp");
$App->setMiddlware(MyMiddleware::class);
$App->run();
```

### CLI 入口 (`console`)

命令行操作入口，加载 autoload 后执行脚本。

## 关键常量

| 常量/属性 | 说明 |
|------|------|
| `F_KERNEL` | 内核加载标识，各文件通过此常量防止直接访问 |
| `App::id()` | 当前 App 标识（静态方法，从当前实例读取，未实例化返回 null） |
| `App::kernelId()` | 内核标识（静态方法，从当前实例读取，未实例化返回 null） |
| `FileSystem::projectRoot()` | 项目根目录路径（自动计算的静态 getter） |
| `FileSystem::kernelRoot()` | 内核根目录路径（自动计算的静态 getter） |
| `FileSystem::root()` | App 根目录路径（自动计算的静态 getter） |
| `FileSystem::data()` | App 数据目录路径（自动计算的静态 getter） |
| `FileSystem::storage()` | App 存储目录路径（自动计算的静态 getter） |
| `FileSystem::kernelDir()` | 内核目录相对路径（自动计算的静态 getter） |
| `FileSystem::dir()` | App 目录相对路径（自动计算的静态 getter） |

> FileSystem 无任何静态属性，7 个路径 getter 在每次静态方法调用时自动计算：`kernelRoot` 为本类所在内核目录（永远正确）；`projectRoot` 在 DiscuzX 平台取 `DISCUZ_ROOT`（去尾斜杠）、普通项目为内核上级目录；`root`/`data`/`storage` 由内核同级目录与 `App::id()` 推导；`kernelDir`/`dir` 为对应绝对路径相对 `projectRoot` 的相对路径。`App` 构造在 `defineConstants()` 之后执行 `new FileSystem`（无参构造，构造时确保 `data`/`storage` 目录存在；未实例化 App 时跳过目录创建）。依赖 `App::id()` 的 getter（`root`/`data`/`storage`/`dir`）在未实例化 App 时返回 null；DiscuzX 平台无需任何额外配置。FileSystem 同时承担文件系统总管理：13 个文件操作方法（upload/createFile/deleteDirectory/clearFolder/copyFolder/getFileInfo/deleteFile/readFile/copyFile/moveFile/ensureDirectory/fileSize/cloneDirectory）已并入本类，原 FileManager 类已删除。

## 版本管理

通过 [Provisioner](ruyi-docs/docs/php/framework/provisioner) 实现增量升级：

- `.version` 文件存储完整版本号，位于 `{FileSystem::data()}/.version`
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
