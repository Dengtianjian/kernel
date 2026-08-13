<?php

namespace kernel\Commands;

/**
 * 示例命令类
 *
 * 演示命令类自动发现机制（Console::discover）：
 * - $name 属性定义命令名（支持冒号命名空间）
 * - $description 属性用于帮助列表
 * - handle() 方法为命令处理器
 */
class ExampleCommand
{
  /** @var string 命令名 */
  protected $name = "example:hello";

  /** @var string 命令说明 */
  protected $description = "Say hello from a command class";

  /**
   * 命令处理器
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例（输出/交互）
   * @param array $args 位置参数
   * @param array $options 选项参数
   * @return integer 退出码，0 表示成功
   */
  public function handle($console, $args, $options): int
  {
    $name = $args[0] ?? "World";
    $console->success("Hello {$name}!");

    if (!empty($options["times"])) {
      $console->info("times = " . $options["times"]);
    }

    return 0;
  }
}
