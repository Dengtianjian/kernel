<?php

namespace kernel\Commands;
use kernel\Foundation\FileSystem\Path;

use kernel\Foundation\App;

/**
 * 生成模型命令
 *
 * 命令名 make:model 在 kernel/console 中注册（Console::register）。
 *
 * 用法：
 *   php kernel/console make:model User              // 生成 UserModel
 *   php kernel/console make:model Admin/User        // 生成 Admin/UserModel（子命名空间）
 *   php kernel/console make:model User --table=xx   // 指定数据表
 *   php kernel/console make:model User --force      // 覆盖已存在文件
 *
 * 骨架参照 kernel\Foundation\Model 基类约定。
 */
class MakeModelCommand extends MakeCommand
{
  /**
   * 命令处理器
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例
   * @param array $args 位置参数：[0] 模型名（如 "User" 或 "Admin/User"）
   * @param array $options 选项参数：table 指定数据表，force 是否覆盖已存在文件
   * @return integer 退出码，0 表示成功
   */
  public function handle($console, $args, $options): int
  {
    $name = $args[0] ?? "";
    if ($name === "") {
      $console->error("Usage: make:model <ModelName>");
      $console->info("Example: make:model User  or  make:model Admin/User");
      return 1;
    }

    [$subDir, $shortName] = $this->split($name);
    $className = $shortName . "Model";
    $classPath = $subDir !== "" ? $subDir . "/" . $className : $className;
    $namespace = $this->joinNamespace(App::id() . "\\Model", $subDir);
    $table = $options["table"] ?? $this->tableName($shortName);

    $body = <<<PHP
use kernel\\Foundation\\Model;

/**
 * {$shortName} 模型
 *
 * @property int \$id
 */
class {$className} extends Model
{
  /**
   * 数据表名
   *
   * @var string
   */
  public static \$tableName = "{$table}";

  /**
   * 是否自动维护 created_at / updated_at
   *
   * @var bool
   */
  public static \$timestamps = false;
}
PHP;

    return $this->write(Path::root() . "/Model", $classPath, $namespace, $body, !empty($options["force"]), $console) ? 0 : 1;
  }
}
