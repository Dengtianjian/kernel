<?php

namespace kernel\Commands;

/**
 * 生成模型命令
 *
 * 用法：
 *   php kernel/console make:model User            // 生成 UserModel，表名 users
 *   php kernel/console make:model Admin/User      // 生成 Admin/UserModel（子命名空间）
 *   php kernel/console make:model User --force    // 覆盖已存在文件
 *
 * 骨架参照 kernel\Model 下现有模型约定（继承 PDO Model 基类）。
 */
class MakeModelCommand extends MakeCommand
{
  /** @var string 命令名 */
  protected $name = "make:model";

  /** @var string 命令说明 */
  protected $description = "Create a new model class";

  /**
   * 命令处理器
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例
   * @param array $args 位置参数：[0] 模型名（如 "User" 或 "Admin/User"）
   * @param array $options 选项参数：force 是否覆盖已存在文件
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
    $namespace = $this->joinNamespace(F_APP_ID . "\\Model", $subDir);
    $tableName = $this->tableName($className);

    $body = <<<PHP
use kernel\\Foundation\\Database\\PDO\\Model;

/**
 * {$shortName} 模型
 */
class {$className} extends Model
{
  /** @var string 数据表名 */
  public \$tableName = "{$tableName}";

  /** @var string 建表 SQL，用于 Provisioner/Iuu 安装时创建表 */
  public \$tableStructureSQL = "";

  public function __construct()
  {
    parent::__construct(\$this->tableName);
  }
}
PHP;

    return $this->write(F_APP_ROOT . "/Model", $classPath, $namespace, $body, !empty($options["force"]), $console) ? 0 : 1;
  }
}
