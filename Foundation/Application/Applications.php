<?php

namespace kernel\Foundation\Application;

use kernel\Foundation\Exception\Error;

/**
 * 应用（App）实例管理器
 *
 * 统一登记同一进程内创建的全部 App 实例，并维护一个「默认应用」指针：
 * - **第一个注册的应用自动成为默认应用**（`$makeDefault` 未显式指定时）；
 * - 也可以**显式指定**默认应用：注册时传 `$makeDefault = true`，或事后调用 {@see setDefault()}。
 *
 * 默认应用是 {@see default()} / {@see defaultId()} / `$app->kernelId()` 的数据来源，
 * 因此「谁被登记为默认」直接决定框架各处取到的是哪个应用。
 *
 * 典型场景与顺序：
 * - 常规应用：`new App(Bootstrap::class, "app")` 构造时自动注册，因是第一个注册者，自动成为默认；
 * - 多应用共存（如 DiscuzX 插件）：插件 App 构造时传 `$makeDefault = true`，显式接管默认位置；
 * - 测试/常驻进程：可用 {@see clear()} 重置注册表，或用 {@see setDefault()} 在多个实例间切换。
 *
 * 注意：注册表以「应用 ID」为键，重复注册同一 ID 会覆盖前者（实例替换，默认指针不变 ——
 * 若被覆盖的正是默认应用，仍指向该 ID，取用时会拿到新实例）。
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
   * 默认应用的应用ID
   *
   * 为 null 表示尚未登记任何应用。
   *
   * @var string|null
   */
  protected static $defaultId = null;
  /**
   * 装配上下文栈
   *
   * `App::setup()` 构造 Bootstrap 期间，把「正在装配的那个应用」压在这里。
   * 这样 Bootstrap 及其构造链上新建的对象（它们没被注入 app）调用 `app()` 时，
   * 拿到的是**正在装配的应用**，而不是「第一个登记成默认的应用」。
   *
   * 子系统（如 download）尤其需要：它的 Bootstrap 一旦回落到默认应用（super_app），
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
   * - 注册表里尚无默认应用时，本次注册的应用自动成为默认；
   * - `$makeDefault = true` 时**强制**把该应用设为默认（覆盖已有的默认应用）；
   * - 重复注册同一 ID 会替换实例，默认指针保持不变。
   *
   * @param Application $app 应用实例
   * @param string $id 应用ID（与项目目录同名）；空字符串会抛异常
   * @param boolean $makeDefault 是否强制指定为默认应用，默认 false（仅在还没有默认应用时自动成为默认）
   * @return Application 返回传入的应用实例，便于链式使用
   * @throws Error 应用ID为空时抛出
   */
  public static function register(Application $app, $id, $makeDefault = false): Application
  {
    if (!is_string($id) || $id === "") {
      throw new Error("注册应用失败：应用ID不能为空", 500, "500:InvalidAppId");
    }

    self::$applications[$id] = $app;

    //* 第一个注册者成为默认；或调用方显式要求抢占默认位置
    if ($makeDefault || self::$defaultId === null) {
      self::$defaultId = $id;
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
   *     "imports"   => ["kernel\Providers\Setting\SettingProvider" => "gstudio_super_app"],
   *     "exposed"   => [],
   *     "providers" => ["kernel\Providers\Setting\SettingProvider", ...],
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
   * 判断指定应用是否已登记
   *
   * @param string $id 应用ID
   * @return boolean
   */
  public static function has($id): bool
  {
    return isset(self::$applications[$id]);
  }

  /**
   * 按应用ID获取实例
   *
   * @param string $id 应用ID
   * @return Application|null 未登记时返回 null
   */
  public static function get($id): ?Application
  {
    return self::$applications[$id] ?? null;
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
   * 再回落到默认应用。
   *
   * @return Application|null 当前上下文的应用；都没有时返回 null
   */
  public static function setUpApp(): ?Application
  {
    $count = count(self::$setupStack);

    return $count > 0 ? self::$setupStack[$count - 1] : null;
  }

  /**
   * 获取默认应用实例
   *
   * @return Application|null 尚未登记任何应用时返回 null
   */
  public static function default(): ?Application
  {
    if (self::$defaultId === null) {
      return null;
    }

    return self::$applications[self::$defaultId] ?? null;
  }

  /**
   * 获取默认应用的应用ID
   *
   * @return string|null 尚未登记任何应用时返回 null
   */
  public static function defaultId(): ?string
  {
    return self::$defaultId;
  }

  /**
   * 切换默认应用
   *
   * 只接受**已登记**的应用，登记表本身不会被修改。
   *
   * @param string|Application $idOrApp 应用ID，或已登记的 Application 实例
   * @return boolean 指定成功返回 true；实例未登记 / ID 不存在返回 false
   */
  public static function setDefault($idOrApp): bool
  {
    if ($idOrApp instanceof Application) {
      $id = array_search($idOrApp, self::$applications, true);
      if ($id === false) {
        return false;
      }
      self::$defaultId = $id;

      return true;
    }

    if (!isset(self::$applications[$idOrApp])) {
      return false;
    }
    self::$defaultId = $idOrApp;

    return true;
  }

  /**
   * 移除一个已登记的应用
   *
   * 若移除的正是默认应用，则把默认指针交给**剩余应用中的第一个**（保持「总有一个默认」）；
   * 注册表被清空后默认指针归 null。
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

    if (self::$defaultId === $id) {
      $remainingIds = array_keys(self::$applications);
      self::$defaultId = $remainingIds ? $remainingIds[0] : null;
    }

    return $removed;
  }

  /**
   * 清空注册表（含默认应用）
   *
   * 仅供测试或常驻进程重置使用；清空后 Applications::default() 返回 null。
   *
   * @return void
   */
  public static function clear(): void
  {
    self::$applications = [];
    self::$defaultId = null;
    self::$setupStack = [];
  }
}
