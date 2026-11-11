<?php

namespace kernel\Foundation\Facade;

use kernel\Foundation\Exception\Error;
use kernel\Foundation\Application\Applications;

/**
 * 门面基类：静态入口 → 当前应用的容器
 *
 * 门面**不持有实例、也不实例化任何东西**，只做一次名字到实例的解析转发：
 *
 * ```
 * Storage::get("x")
 *   → Applications::default()                    当前应用
 *   → resolve("storage")          该应用容器里已登记的单例
 *   → 那个实例的 get("x")
 * ```
 *
 * 这样三点自然成立：
 * - 实例跟着应用走（多应用同进程不串）；
 * - 生命周期归容器管（应用级复用、请求级在 `flush()` 后重建）；
 * - 门面与 `$app->storage()` / `$app->make(FileStorage::class)` 必然同一份。
 *
 * 代价是门面不再「开箱即用」：目标必须由 Provider / Bootstrap 先登记。这是刻意的——
 * 门面自己 new 出来的实例一定带着默认配置（没有多磁盘、没有访问控制），
 * 与装配出来的那份不是一回事，宁可报错也不要静默用错。
 *
 * 子类二选一：
 * - 覆写 {@see accessor()}：纯容器代理（推荐）；
 * - 覆写 {@see resolve()}：容器里没有时兜底造一个，产物会写回容器，仍与 `$app->xxx()` 同源。
 *
 * @see \kernel\Foundation\Application\Container::instance() 装配期替换实例
 */
abstract class Facade
{
  /**
   * 容器键（门面转发的目标）
   *
   * 必须与登记时用的主键一致：`$app->instance(FileStorage::class, $storage, "storage")`
   * 这种登记下，写 `FileStorage::class` 或 `"storage"` 都能命中；写子类名则会取到别的东西。
   *
   * @return string|null 未覆写时返回 null
   */
  protected static function accessor()
  {
    return null;
  }

  /**
   * 兜底实例工厂（可选覆写）
   *
   * 仅当容器里没有该键时调用；返回值会被 `$app->instance()` 写回容器，
   * 保证「门面拿到的」与「`$app->xxx()` 拿到的」是同一个。
   *
   * @return object|null
   */
  protected static function resolve()
  {
    return null;
  }

  /**
   * 取门面转发的实例
   *
   * 顺序：容器已有 → 兜底工厂 → 抛错。绝不反射新建未登记的类
   * （那会得到一个配置全默认的临时对象，且下次调用又是新的）。
   *
   * @return object
   * @throws Error 未注册应用 / 未声明 accessor / 目标未装配时抛出
   */
  public static function instance()
  {
    $app = Applications::default();
    if ($app === null) {
      throw new Error(static::class . " 门面无法解析：尚未注册任何应用", 500, "500:FacadeAppMissing");
    }

    $key = static::accessor();
    if ($key === null) {
      throw new Error(static::class . " 门面未声明 accessor()：无法知道要转发到容器里的哪个键", 500, "500:FacadeAccessorMissing");
    }

    //* 用 has() 而不是 resolved()：延迟登记（只记了类名、还没构造）也算「有」，
    //* 此时 make() 会当场构造并转成单例；resolved() 只看已构造的实例，会误判成没登记。
    if ($app->has($key)) {
      return $app->make($key);
    }

    $instance = static::resolve();
    if ($instance === null) {
      throw new Error(
        static::class . " 的目标未装配：{$key}（请先 \$app->provider() 登记）",
        500,
        "500:FacadeTargetMissing"
      );
    }

    $app->provider($instance, $key);

    return $instance;
  }

  /**
   * 静态转发：把未定义的静态方法转给底层实例
   *
   * @param string $method 方法名
   * @param array $arguments 参数列表
   * @return mixed
   */
  public static function __callStatic(string $method, array $arguments)
  {
    return static::instance()->$method(...$arguments);
  }

  /**
   * 包装任意类为门面代理（无需手动实例化、**不经过容器**）
   *
   * 返回 {@see FacadeProxy}，同一目标类名首次实例化后复用。
   * 只适合**无状态工具类**（Arr / Str 之类）；有状态的组件请走容器 + 上面的门面，
   * 否则会绕开 Provider 的装配，拿到配置全默认的实例。
   *
   * ```php
   * Facade::of(SomeUtil::class)->doSomething($a);
   * ```
   *
   * @param string $class 目标类名（完整命名空间）
   * @param mixed ...$args 目标类构造参数（仅首次实例化生效）
   * @return FacadeProxy
   */
  public static function of(string $class, ...$args)
  {
    return new FacadeProxy($class, $args);
  }
}
