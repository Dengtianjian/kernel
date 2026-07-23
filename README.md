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
| [Store](ruyi-docs/docs/php/framework/store) | `Store.php` | 全局数据存储 |
| [Event](ruyi-docs/docs/php/framework/event) | `Event.php` | 事件系统 |
| Cache | `Cache.php` | 缓存处理 |
| Log | `Log.php` | 日志处理 |
| Cron | `Cron.php` | 定时任务调度 |
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
| `File/` | 文件操作辅助及管理 |
| `HTTP/` | HTTP 客户端、请求/响应处理 |
| `Network/` | 网络请求工具 |
| `Object/` | 基础对象模型 |
| `ReturnResult/` | 标准化返回结果 |
| `Storage/` | 存储抽象（本地、阿里云 OSS、腾讯云 COS） |
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

| 常量 | 说明 |
|------|------|
| `F_KERNEL` | 内核加载标识，各文件通过此常量防止直接访问 |
| `F_APP_ROOT` | App 根目录路径 |
| `F_APP_DATA` | App 数据目录路径 |
| `F_APP_STORAGE` | App 存储目录路径 |
| `F_APP_ID` | 当前 App 标识 |

## 版本管理

通过 [Provisioner](ruyi-docs/docs/php/framework/provisioner) 实现增量升级：

- `.version` 文件存储完整版本号，位于 `{F_APP_DATA}/.version`
- 升级脚本放在 `Upgrades/` 目录，按 `Upgrade_x_y_z.php` 命名
- 升级/回滚后自动持久化版本号

```php
$p = new Provisioner();
$p->upgrade('2.0.0');   // 升级
$p->rollback('1.0.0');  // 回滚
$p->getStatus();        // 查看状态
```

## 依赖

通过 Composer 管理，主要依赖：

- 阿里云 OSS SDK（`aliyuncs/oss-sdk-php`）
- 腾讯云 COS SDK（`qcloud/cos-sdk-v5`）
- 腾讯云 STS SDK（`qcloud_sts/qcloud-sts-sdk`）
- 阿里云通用 SDK（`alibabacloud/sdk`）

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
