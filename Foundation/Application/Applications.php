<?php

namespace kernel\Foundation\Application;

use kernel\Foundation\Exception\Error;

/**
 * 应用（App）实例管理器
 *
 * 统一登记同一进程内创建的全部 App 实例，并维护「当前主运行应用」指针：
 * - 每个请求/命令只有一个入口应用，**第一个注册的应用成为当前应用**；
 * - 其它应用通过 {@see get()} 取用（借能力），不会覆盖当前应用；
 * - 不设主从层级，所有应用在注册表里平行。
 *
 * 当前应用是 {@see current()} / {@see currentId()} 的数据来源，也是 kernel 各门面在
 * 「没有对象上下文」时的解析目标。业务对象自身的归属一律用 `$this->app()->appId()`。
 *
 * 注意：注册表以「应用 ID」为键，重复注册同一 ID 会覆盖前者（实例替换，当前指针不变）。
 *
 * 类型上只认 {@see Application}：具体的 Web 应用 / 未来的 console 应用都是它的子类，
 * 管理器不需要知道是哪种。
 */
class Applications
{
  /**
   * 已登记的应用实例
   *
   * 结构：应用ID => Application 实例
   *
   * @var array<string, Application>
   */
  protected static $applications = [];
  /**
   * 当前主运行应用的应用ID
   *
   * 为 null 表示尚未登记任何应用。只由首个注册的应用设置，后续 get() 取用不覆盖。
   *
   * @var string|null
   */
  protected static $currentId = null;
  /**
   * 装配上下文栈
   *
   * `App::setup()` 构造 Bootstrap 期间，把「正在装配的那个应用」压在这里。
   * 这样 Bootstrap 及其构造链上新建的对象（它们没被注入 app）调用 `app()` 时，
   * 拿到的是**正在装配的应用**，而不是「第一个登记成当前的应用」。
   *
   * 子系统（如 download）尤其需要：它的 Bootstrap 一旦回落到当前应用（super_app），
   * `import()` 就会把绑定写进 super_app 的容器，把 super_app 自己的服务改成
   * 「去 super_app 取」——解析时自引用，无限递归。
   *
   * 用栈而不是单值，是因为装配里可能再触发别的应用的懒装配。
   *
   * @var Application[]
   */
  protected static $setupStack = [];

  /**
   * 登记一个应用实例
   *
   * - 注册表里还没有当前应用时，本次注册的应用成为当前应用；
   * - 已有当前应用时不覆盖（其它应用通过 get() 取用，不改变当前）；
   * - 重复注册同一 ID 会替换实例，当前指针保持不变。
   *
   * @param Application $app 应用实例
   * @param string $id 应用ID（与项目目录同名）；空字符串会抛异常
   * @return Application 返回传入的应用实例，便于链式使用
   * @throws Error 应用ID为空时抛出
   */
  public static function register(Application $app, $id): Application
  {
    if (!is_string($id) || $id === "") {
      throw new Error("注册应用失败：应用ID不能为空", 500, "500:InvalidAppId");
    }

    self::$applications[$id] = $app;

    //* 首个注册者成为当前应用；已有当前应用时不覆盖
    if (self::$currentId === null) {
      self::$currentId = $id;
    }

    return $app;
  }

  /**
   * 获取全部已登记的应用实例
   *
   * @return array<string, Application> 应用ID => 实例
   */
  public static function all(): array
  {
    return self::$applications;
  }

  /**
   * 依赖图：每个应用引了谁、开放了什么、装了哪些装配单元
   *
   * 排查「这个能力究竟从哪个应用来的」先看它。开发模式下 {@see \kernel\Foundation\App::run()}
   * 会在装配完成后写进日志（`app.dependencyGraph`），生产环境不写，避免每请求多一次 IO。
   *
   * 返回结构：
   * ```php
   * [
   *   "gstudio_download" => [
   *     "imports"   => ["kernel\Modules\Setting\Setting" => "gstudio_super_app"],
   *     "exposed"   => [],
   *     "providers" => ["kernel\Modules\Setting\Setting", ...],
   *   ],
   *   // ...
   * ]
   * ```
   *
   * @return array<string, array{imports: array<string,string>, providers: array<int,string>}>
   */
  public static function graph(): array
  {
    $graph = [];

    foreach (self::$applications as $id => $app) {
      $graph[$id] = [
        "imports" => $app->imports(),
        "providers" => array_keys($app->providers()),
      ];
    }

    return $graph;
  }

  /**
   * 获取全部已登记的应用ID
   *
   * @return string[]
   */
  public static function ids(): array
  {
    return array_keys(self::$applications);
  }

  /**
   * 应用ID归一化
   *
   * Discuz 环境下应用目录统一带 `gstudio_` 前缀；调用方写短名（如 `super_app`）时，
   * 若注册表里没有该短名、但存在 `gstudio_` + 短名的应用，则按后者解析。
   * 精确 ID 优先，非 Discuz 环境不做任何前缀处理。
   *
   * @param string $id 应用ID（完整 ID 或短名）
   * @return string 归一化后的应用ID
   */
  protected static function resolveId($id): string
  {
    $id = (string) $id;
    if ($id === "" || isset(self::$applications[$id])) {
      return $id;
    }

    if (defined("IN_DISCUZ") && !str_starts_with($id, "gstudio_")) {
      $alias = "gstudio_" . $id;
      if (isset(self::$applications[$alias])) {
        return $alias;
      }
    }

    return $id;
  }

  /**
   * 是否已登记指定应用
   *
   * @param string $id 应用ID（Discuz 下可省略 `gstudio_` 前缀）
   * @return boolean
   */
  public static function has($id): bool
  {
    return isset(self::$applications[self::resolveId($id)]);
  }

  /**
   * 按应用ID获取实例
   *
   * @param string $id 应用ID（Discuz 下可省略 `gstudio_` 前缀）
   * @return Application|null 未登记时返回 null
   */
  public static function get($id): ?Application
  {
    return self::$applications[self::resolveId($id)] ?? null;
  }

  /**
   * 进入装配 / 构造上下文
   *
   * 两处成对调用（见 {@see leaveSetup()}）：
   * - `App::assemble()`：构造 Bootstrap 期间；
   * - `Container::buildClass()` / `buildConcrete()`：构造对象、执行工厂期间。
   *
   * 目的是让对象在构造函数里调用 `$this->app()` 时能拿到正确的归属——
   * `bindApp()` 在构造之后才生效，构造期只能靠这个栈。
   *
   * @internal
   * @param Application $app 当前正在构造/装配的应用
   * @return void
   */
  public static function enterSetup(Application $app): void
  {
    self::$setupStack[] = $app;
  }

  /**
   * 退出装配上下文
   *
   * 与 {@see enterSetup()} 成对；栈空时静默返回，保证 boot 失败也不会把栈弄乱。
   *
   * @internal
   * @return void
   */
  public static function leaveSetup(): void
  {
    array_pop(self::$setupStack);
  }

  /**
   * 取当前装配 / 构造上下文里的应用
   *
   * 既不在装配期也不在构造期时返回 null——调用方（{@see \kernel\Foundation\Object\BaseObject::app()}）
   * 再回落到当前应用。
   *
   * @return Application|null 当前上下文的应用；都没有时返回 null
   */
  public static function setUpApp(): ?Application
  {
    $count = count(self::$setupStack);

    return $count > 0 ? self::$setupStack[$count - 1] : null;
  }

  /**
   * 获取当前主运行应用实例
   *
   * @return Application|null 尚未登记任何应用时返回 null
   */
  public static function current(): ?Application
  {
    if (self::$currentId === null) {
      return null;
    }

    return self::$applications[self::$currentId] ?? null;
  }

  /**
   * 获取当前主运行应用的应用ID
   *
   * @return string|null 尚未登记任何应用时返回 null
   */
  public static function currentId(): ?string
  {
    return self::$currentId;
  }

  /**
   * 移除一个已登记的应用
   *
   * 若移除的正是当前应用，则把当前指针交给**剩余应用中的第一个**（保持「总有一个当前」）；
   * 注册表被清空后当前指针归 null。
   *
   * @param string $id 应用ID
   * @return Application|null 被移除的实例；未登记时返回 null
   */
  public static function remove($id): ?Application
  {
    if (!isset(self::$applications[$id])) {
      return null;
    }

    $removed = self::$applications[$id];
    unset(self::$applications[$id]);

    if (self::$currentId === $id) {
      $remainingIds = array_keys(self::$applications);
      self::$currentId = $remainingIds ? $remainingIds[0] : null;
    }

    return $removed;
  }

  /**
   * 清空注册表（含当前应用）
   *
   * 仅供测试或常驻进程重置使用；清空后 Applications::current() 返回 null。
   *
   * @return void
   */
  public static function clear(): void
  {
    self::$applications = [];
    self::$currentId = null;
    self::$setupStack = [];
  }
}
