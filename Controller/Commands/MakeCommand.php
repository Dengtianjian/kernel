<?php

namespace kernel\Controller\Commands;

/**
 * 生成类命令的抽象基类
 *
 * 为 make:model / make:controller / make:middleware 提供公共能力：
 * - 参数解析：支持 "Admin/User" 形式的子目录/子命名空间
 * - 命名空间拼接
 * - 骨架文件写入（含已存在保护与 --force 覆盖）
 *
 * 注意：本类为抽象基类，不定义 $name 属性，不会被注册为命令。
 */
abstract class MakeCommand
{
  /**
   * 将输入参数解析为 [相对目录, 类短名]
   *
   * 支持正斜杠/反斜杠分隔，如 "Admin/User" 或 "Admin\User"。
   *
   * @param string $arg 如 "Admin/User"
   * @return array{0:string,1:string} [子目录（可为空）, 类短名]
   */
  protected function split(string $arg): array
  {
    $parts = explode("/", str_replace("\\", "/", trim($arg, "/")));
    $shortName = array_pop($parts);
    return [implode("/", $parts), $shortName];
  }

  /**
   * 拼接命名空间
   *
   * @param string $base 基础命名空间，如 "app\Model"
   * @param string $subDir 子目录，如 "Admin"，为空时不追加
   * @return string
   */
  protected function joinNamespace(string $base, string $subDir): string
  {
    if ($subDir === "") {
      return $base;
    }
    return $base . "\\" . str_replace("/", "\\", $subDir);
  }

  /**
   * 写入骨架文件
   *
   * 目标已存在且未传 --force 时拒绝覆盖。
   *
   * @param string $targetDir 目标根目录（含子目录由 className 路径决定）
   * @param string $className 完整类名（含命名空间内子目录），如 "Admin/UserModel"
   * @param string $namespace 类命名空间
   * @param string $body 类体（use 语句 + class 定义，不含 <?php）
   * @param bool $force 是否强制覆盖已存在文件
   * @param \kernel\Foundation\Console\Console $console 控制台实例，用于输出
   * @return bool 是否写入成功（false 表示文件已存在且未覆盖）
   */
  protected function write(string $targetDir, string $className, string $namespace, string $body, bool $force, $console): bool
  {
    $filePath = $targetDir . "/" . $className . ".php";
    if (file_exists($filePath) && !$force) {
      $console->error("File already exists: {$filePath}");
      $console->info("Use --force to overwrite it.");
      return false;
    }
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
    $content = "<?php\n\nnamespace {$namespace};\n\n" . $body . "\n";
    file_put_contents($filePath, $content);
    $console->success("Created: {$filePath}");
    return true;
  }

  /**
   * 由模型类名推断数据表名
   *
   * 规则与 Model::getDefaultTableName() 一致：
   * UserModel → user，OrderItemModel → order_item。
   *
   * @param string $className 模型类名（含 Model 后缀）
   * @return string
   */
  protected function tableName(string $className): string
  {
    $name = preg_replace('/Model$/', '', $className);
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
  }
}
