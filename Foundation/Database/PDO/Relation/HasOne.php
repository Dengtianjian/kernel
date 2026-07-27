<?php

namespace kernel\Foundation\Database\PDO\Relation;

use kernel\Foundation\Database\PDO\Model;

/**
 * 一对一关系：当前 Model 拥有一条关联 Model 记录
 *
 * JOIN 方向：关联表 INNER JOIN 当前表，ON 当前表.主键 = 关联表.外键
 *
 * 使用场景：
 * - User hasOne Profile（一个用户有一个档案）
 * - Order hasOne Invoice（一个订单有一张发票）
 *
 * ```php
 * class User extends Model {
 *     public function profile() {
 *         return $this->hasOne(Profile::class);
 *     }
 * }
 *
 * // 懒加载
 * $profile = $user->profile;  // Profile|null
 *
 * // 链式查询
 * $profile = $user->profile()->select('bio', 'avatar')->first();
 * ```
 */
class HasOne extends Relation
{
  protected function addConstraints(): void
  {
    $parentTable = $this->parent->tableName;
    $relatedTable = $this->query->getTableName();

    // JOIN 关联表 ON 当前表.主键 = 关联表.外键
    $this->query->join(
      $parentTable,
      "{$parentTable}.{$this->localKey}",
      '=',
      "{$relatedTable}.{$this->foreignKey}"
    );

    // WHERE 当前表.主键 = 父实例的主键值
    $parentKeyValue = $this->parent->{$this->localKey};
    $this->query->where("{$parentTable}.{$this->localKey}", $parentKeyValue);
  }

  public function getResults(): mixed
  {
    if ($this->results === null) {
      $row = $this->query->first();
      if ($row) {
        /** @var Model $instance */
        $instance = new $this->relatedClass();
        $this->hydrate($instance, $row);
        $this->results = $instance;
      } else {
        $this->results = null;
      }
    }
    return $this->results;
  }

  /**
   * 将数据库行数据填充到 Model 实例
   */
  protected function hydrate(Model $instance, array $row): void
  {
    foreach ($row as $key => $value) {
      $instance->$key = $value;
    }
  }
}
