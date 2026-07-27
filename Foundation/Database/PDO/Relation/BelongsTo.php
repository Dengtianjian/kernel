<?php

namespace kernel\Foundation\Database\PDO\Relation;

use kernel\Foundation\Database\PDO\Model;

/**
 * 反向一对多关系：当前 Model 属于一条父 Model 记录
 *
 * JOIN 方向：父表 INNER JOIN 当前表，ON 当前表.外键 = 父表.主键
 *
 * 使用场景：
 * - Comment belongsTo Post（评论属于一篇文章）
 * - Order belongsTo User（订单属于一个用户）
 *
 * 注意：BelongsTo 的外键约定与 HasOne/HasMany 不同：
 * - foreignKey：当前表的外键字段（默认：{关联表名}_{关联表主键}）
 * - localKey：关联表的主键字段（默认：关联 Model 的 primaryKey）
 *
 * ```php
 * class Comment extends Model {
 *     public function post() {
 *         return $this->belongsTo(Post::class);
 *     }
 * }
 *
 * // 懒加载
 * $post = $comment->post;  // Post|null
 * ```
 */
class BelongsTo extends Relation
{
  protected function addConstraints(): void
  {
    $parentTable = $this->parent->tableName;
    $relatedTable = $this->query->getTableName();

    // JOIN 父表 ON 当前表.外键 = 父表.主键
    $this->query->join(
      $parentTable,
      "{$relatedTable}.{$this->localKey}",
      '=',
      "{$parentTable}.{$this->foreignKey}"
    );

    // WHERE 当前表.外键 = 父实例的外键值
    $parentKeyValue = $this->parent->{$this->foreignKey};
    $this->query->where("{$relatedTable}.{$this->localKey}", $parentKeyValue);
  }

  public function getResults(): mixed
  {
    if ($this->results === null) {
      $row = $this->query->first();
      if ($row) {
        /** @var Model $instance */
        $instance = new $this->relatedClass();
        foreach ($row as $key => $value) {
          $instance->$key = $value;
        }
        $this->results = $instance;
      } else {
        $this->results = null;
      }
    }
    return $this->results;
  }
}
