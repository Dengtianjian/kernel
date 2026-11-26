<?php

namespace kernel\Foundation\Database\RDB;

/**
 * 单列变更定义
 *
 * 由 {@see TableBuilder} 的类型方法创建，链式设置可空、默认值、注释、重命名等，
 * 最后由 TableBuilder 统一生成 ALTER TABLE。
 *
 * 默认是 ADD COLUMN；调用 `change()` 变为 MODIFY COLUMN，调用 `rename()` 变为 CHANGE COLUMN。
 */
class ColumnDefinition
{
  /**
   * 所属表构建器
   *
   * @var TableBuilder
   */
  private $builder;
  /**
   * 列定义
   *
   * @var Schema
   */
  private $schema;
  /**
   * 变更类型：add / modify / rename
   *
   * @var string
   */
  private $operation = "add";
  /**
   * 重命名后的列名
   *
   * @var string|null
   */
  private $newName = null;
  /**
   * 位置：放在指定列之后
   *
   * @var string|null
   */
  private $after = null;
  /**
   * 是否放到第一列
   *
   * @var bool
   */
  private $first = false;

  public function __construct(TableBuilder $builder, Schema $schema)
  {
    $this->builder = $builder;
    $this->schema = $schema;
  }

  /**
   * @return string
   */
  public function getName()
  {
    return $this->schema->getName();
  }

  /**
   * @return Schema
   */
  public function getSchema()
  {
    return $this->schema;
  }

  /**
   * @return string
   */
  public function getOperation()
  {
    return $this->operation;
  }

  /**
   * @return string|null
   */
  public function getNewName()
  {
    return $this->newName;
  }

  /**
   * @return string|null
   */
  public function getAfter()
  {
    return $this->after;
  }

  /**
   * @return bool
   */
  public function isFirst()
  {
    return $this->first;
  }

  /**
   * 声明为修改已有列
   *
   * @return $this
   */
  public function change()
  {
    if ($this->operation !== "rename") {
      $this->operation = "modify";
    }

    return $this;
  }

  /**
   * 声明重命名为新列名
   *
   * @param string $newName 新列名
   * @return $this
   */
  public function rename($newName)
  {
    $this->newName = (string) $newName;
    $this->operation = "rename";

    return $this;
  }

  /**
   * 是否允许 NULL
   *
   * @param bool $nullable
   * @return $this
   */
  public function nullable($nullable = true)
  {
    $this->schema->nullable((bool) $nullable);

    return $this;
  }

  /**
   * 不允许 NULL
   *
   * @return $this
   */
  public function notNullable()
  {
    return $this->nullable(false);
  }

  /**
   * 设置默认值
   *
   * @param mixed $value
   * @return $this
   */
  public function default($value)
  {
    $this->schema->default($value);

    return $this;
  }

  /**
   * 设置注释
   *
   * @param string $comment
   * @return $this
   */
  public function comment($comment)
  {
    $this->schema->comment((string) $comment);

    return $this;
  }

  /**
   * 设置无符号
   *
   * @return $this
   */
  public function unsigned()
  {
    $this->schema->unsigned();

    return $this;
  }

  /**
   * 设置自增
   *
   * @return $this
   */
  public function autoIncrement()
  {
    $this->schema->autoIncrement();

    return $this;
  }

  /**
   * 添加唯一索引
   *
   * 不传 $columns 时作用于当前列；传 $columns 时可声明联合唯一索引。
   * 注意与 {@see TableBuilder::unique()} 的区别：表级必须显式传索引列，本方法允许省略。
   *
   * @param string|null $name 索引名，默认 uk_{列名}
   * @param array|null $columns 索引列
   * @return $this
   */
  public function unique($name = null, array $columns = null)
  {
    $columns = $columns ?: [$this->getName()];
    $this->builder->unique($name ?: "uk_{$columns[0]}", $columns);

    return $this;
  }

  /**
   * 添加普通索引
   *
   * 不传 $columns 时作用于当前列；传 $columns 时可声明联合索引。
   * 注意与 {@see TableBuilder::index()} 的区别：表级必须显式传索引列，本方法允许省略。
   *
   * @param string|null $name 索引名，默认 idx_{列名}
   * @param array|null $columns 索引列
   * @return $this
   */
  public function index($name = null, array $columns = null)
  {
    $columns = $columns ?: [$this->getName()];
    $this->builder->index($name ?: "idx_{$columns[0]}", $columns);

    return $this;
  }

  /**
   * 放到指定列之后
   *
   * @param string $column
   * @return $this
   */
  public function after($column)
  {
    $this->after = (string) $column;
    $this->first = false;

    return $this;
  }

  /**
   * 放到第一列
   *
   * @return $this
   */
  public function first()
  {
    $this->first = true;
    $this->after = null;

    return $this;
  }

  /**
   * 未定义的列方法转交给表构建器
   *
   * 让一个 Builder 可以连续声明多列：
   * `$table->string('a')->integer('b')->text('c')->sync()`。
   *
   * @param string $method
   * @param array $parameters
   * @return mixed
   */
  public function __call($method, $parameters)
  {
    return $this->builder->{$method}(...$parameters);
  }

  /**
   * 执行整张表的变更
   *
   * @return string[]
   */
  public function sync()
  {
    return $this->builder->sync();
  }

  /**
   * 预览整张表的 SQL
   *
   * @return string[]
   */
  public function sql()
  {
    return $this->builder->sql();
  }
}