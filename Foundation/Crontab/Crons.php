<?php

namespace kernel\Foundation\Crontab;

/**
 * 定时任务管理器
 *
 * 负责登记与执行 Cron 任务：
 *   - register(Cron) / registerClass(string)  按实例或类名登记
 *   - discover(string $dir, string $ns)        扫描目录下的全部 .php 类文件，按命名空间登记
 *   - runDue()                                 执行所有到期任务，返回执行数量
 *   - run(string $class)                       强制执行指定类名的任务（忽略到期判断）
 *
 * 典型用法（业务应用手动装配，经门面共享）：
 *   use kernel\Foundation\Crontab\Crons as CronsManager;
 *   use kernel\Facades\Crons;
 *   $crons = new CronsManager();
 *   $crons->registerClass(\isdtj\Crons\ClearAuthTokensCron::class);
 *   Crons::registerClass(\isdtj\Crons\ClearAuthTokensCron::class); // 经门面登记
 *   Crons::runDue();
 * （Crons 管理器本身不含静态单例，实例持有由 kernel\Facades\Crons 负责。）
 */
class Crons
{
  /** @var Cron[] 已登记的任务实例 */
  protected array $tasks = [];

  /** @var string[] 扫描时未找到的类名（供调用方告警） */
  protected array $notFound = [];

  /**
   * 登记一个任务实例
   *
   * @param Cron $cron
   * @return static
   */
  public function register(Cron $cron): static
  {
    $this->tasks[] = $cron;
    return $this;
  }

  /**
   * 按类名登记（自动实例化）；非 Cron 子类或类不存在则跳过
   *
   * @param string $class
   * @return static
   */
  public function registerClass(string $class): static
  {
    if (!class_exists($class)) {
      $this->notFound[] = $class;
      return $this;
    }
    if (!is_subclass_of($class, Cron::class)) {
      return $this;
    }
    $this->tasks[] = new $class();
    return $this;
  }

  /**
   * 扫描目录下的全部 .php 类文件并按命名空间登记
   *
   * 对每个文件尝试以「命名空间 + 文件名」构造类名调用 registerClass，
   * 其中非 Cron 子类或不存在的类会被自动跳过（不会报错）。
   *
   * @param string $directory 绝对目录路径
   * @param string $namespace 类命名空间（不含尾部反斜杠）
   * @return static
   */
  public function discover(string $directory, string $namespace): static
  {
    if (!is_dir($directory)) {
      return $this;
    }
    foreach (glob($directory . "/*.php") ?: [] as $file) {
      $class = rtrim($namespace, "\\") . "\\" . pathinfo($file, PATHINFO_FILENAME);
      $this->registerClass($class);
    }
    return $this;
  }

  /**
   * 执行所有到期任务
   *
   * @return integer 实际执行数量
   */
  public function runDue(): int
  {
    $ran = 0;
    foreach ($this->tasks as $cron) {
      if ($cron->due()) {
        $cron->run();
        $ran++;
      }
    }
    return $ran;
  }

  /**
   * 强制执行指定类名的任务（忽略到期判断）
   *
   * @param string $class
   * @return boolean 是否找到并执行
   */
  public function run(string $class): bool
  {
    foreach ($this->tasks as $cron) {
      if (get_class($cron) === $class || $cron instanceof $class) {
        $cron->run();
        return true;
      }
    }
    return false;
  }

  /**
   * 返回已登记的任务实例列表
   *
   * @return Cron[]
   */
  public function all(): array
  {
    return $this->tasks;
  }

  /**
   * 返回扫描时未找到的类名（供告警）
   *
   * @return string[]
   */
  public function notFound(): array
  {
    return $this->notFound;
  }
}
