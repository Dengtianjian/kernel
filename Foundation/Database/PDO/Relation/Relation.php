<?php

namespace kernel\Foundation\Database\PDO\Relation;

use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Foundation\Database\PDO\Model;
use kernel\Foundation\Database\PDO\Query;

/**
 * 关联查询抽象基类
 *
 * Relation 是 ORM 关联查询的核心组件，每种关系类型（HasOne/HasMany/BelongsTo）
 * 继承此类实现各自的 JOIN 配置和结果查询逻辑。
 *
 * ## 设计原理
 *
 * Relation 内部持有 Query 实例并通过 __call 代理所有未定义方法，
 * 使得关联查询完全支持链式调用：
 *
 * ```php
 * $user->posts()->where('status', 1)->orderBy('created_at', 'DESC')->get();
 * ```
 *
 * ## 关联键约定
 *
 * 外键和主键均支持手动指定和自动推断：
 * - **外键推断**：`{从类表名}_{从类主键}`，如 `user_id`
 * - **主键推断**：`Model::$primaryKey`，默认 `id`
 *
 * @see HasOne    一对一关系
 * @see HasMany   一对多关系
 * @see BelongsTo 反向一对多关系
 */
abstract class Relation extends AbilityBaseObject
{
  /**
   * 关联的 Model 类名
   * @var string
   */
  protected string $relatedClass;

  /**
   * 关联表的外键字段名
   * @var string
   */
  protected string $foreignKey;

  /**
   * 本地表的主键字段名
   * @var string
   */
  protected string $localKey;

  /**
   * 持有该关系的父 Model 实例
   * @var Model
   */
  protected Model $parent;

  /**
   * 底层 Query 构建器，已配置 JOIN 和 WHERE 约束
   * @var Query
   */
  protected Query $query;

  /**
   * 懒加载结果缓存
   * @var mixed|null
   */
  protected mixed $results = null;

  /**
   * 构造关联关系实例
   *
   * 自动创建关联 Model 的 Query 实例，并通过 addConstraints() 配置 JOIN 条件。
   *
   * @param string $relatedClass 关联 Model 类名
   * @param Model  $parent       父 Model 实例
   * @param string $foreignKey   关联表外键（可选，自动推断）
   * @param string $localKey     本地表主键（可选，自动推断）
   */
  public function __construct(string $relatedClass, Model $parent, string $foreignKey = '', string $localKey = '')
  {
    /** @var Model $relatedInstance */
    $relatedInstance = new $relatedClass();

    $this->relatedClass = $relatedClass;
    $this->parent       = $parent;

    // 自动推断外键：{父表名}_{父表主键}
    if (empty($foreignKey)) {
      $this->foreignKey = $parent->getTableBaseName() . '_' . $parent->getPrimaryKey();
    } else {
      $this->foreignKey = $foreignKey;
    }

    // 自动推断主键：关联 Model 的主键
    if (empty($localKey)) {
      $this->localKey = $relatedInstance->getPrimaryKey();
    } else {
      $this->localKey = $localKey;
    }

    // 创建关联 Model 的 Query 实例（带软删除等全局作用域）
    // 必须用 scopedQuery()：每次都是全新实例，避免多个 Relation 之间串条件
    $this->query = $relatedInstance->scopedQuery();

    // 子类实现具体的 JOIN 配置
    $this->addConstraints();
  }

  /**
   * 配置关联查询的 JOIN 和 WHERE 约束
   *
   * 由子类实现，根据关系类型设置不同的 JOIN 方向。
   * HasOne/HasMany：JOIN 关联表 ON 本地主键 = 关联表外键
   * BelongsTo：JOIN 关联表 ON 本表外键 = 关联表主键
   */
  abstract protected function addConstraints(): void;

  /**
   * 获取关联查询结果
   *
   * 由子类实现，根据关系类型返回对应形式的结果。
   *
   * @return mixed HasOne → Model|null, HasMany → Model[], BelongsTo → Model|null
   */
  abstract public function getResults(): mixed;

  /**
   * 方法代理：未定义方法自动转发给底层 Query 对象
   *
   * 使关联查询支持完整的 Query 链式调用。
   * 若 Query 方法返回 Query 自身（链式方法），替换为 $this 以保证 Relation 链不间断。
   *
   * @param string $method     调用的方法名
   * @param array  $parameters 调用参数
   * @return mixed
   */
  public function __call($method, $parameters)
  {
    $result = $this->query->$method(...$parameters);
    return $result === $this->query ? $this : $result;
  }

  /**
   * 获取关联 Model 类名
   * @return string
   */
  public function getRelatedClass(): string
  {
    return $this->relatedClass;
  }

  /**
   * 获取底层 Query 实例
   * @return Query
   */
  public function getQuery(): Query
  {
    return $this->query;
  }

  /**
   * 获取关联的外键
   * @return string
   */
  public function getForeignKey(): string
  {
    return $this->foreignKey;
  }

  /**
   * 获取本地主键
   * @return string
   */
  public function getLocalKey(): string
  {
    return $this->localKey;
  }

  /**
   * 获取父 Model
   * @return Model
   */
  public function getParent(): Model
  {
    return $this->parent;
  }
}
