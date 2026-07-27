<?php

namespace kernel\Foundation\Database\PDO\Relation;

use kernel\Foundation\Database\PDO\Model;

/**
 * 一对多关系：当前 Model 拥有多条关联 Model 记录
 *
 * JOIN 方向：关联表 INNER JOIN 当前表，ON 当前表.主键 = 关联表.外键
 *
 * 使用场景：
 * - Post hasMany Comments（一篇文章有多条评论）
 * - User hasMany Orders（一个用户有多个订单）
 *
 * ```php
 * class Post extends Model {
 *     public function comments() {
 *         return $this->hasMany(Comment::class);
 *     }
 * }
 *
 * // 懒加载
 * $comments = $post->comments;  // Model[]
 *
 * // 链式查询
 * $comments = $post->comments()->where('status', 1)->get();
 * ```
 */
class HasMany extends HasOne
{
  public function getResults(): mixed
  {
    if ($this->results === null) {
      $rows = $this->query->get();
      $this->results = [];
      foreach ($rows as $row) {
        /** @var Model $instance */
        $instance = new $this->relatedClass();
        $this->hydrate($instance, $row);
        $this->results[] = $instance;
      }
    }
    return $this->results;
  }
}
