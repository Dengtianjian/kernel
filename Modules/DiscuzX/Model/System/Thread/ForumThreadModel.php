<?php

namespace kernel\Modules\DiscuzX\Model\System\Thread;

use kernel\Modules\DiscuzX\Model\DiscuzXSystemModel;

/**
 * Discuz 主题表模型
 *
 * 对应 Discuz 系统表 `forum_thread`（真实表名含站点 `tablepre` 前缀，由基类补全）。
 *
 * @see DiscuzXSystemModel 基类：补 Discuz 表前缀、关闭时间戳自动维护
 */
class ForumThreadModel extends DiscuzXSystemModel
{
  public $tableName = "forum_thread";

  /** @var string 主键字段名 */
  protected $primaryKey = "tid";

  public function items($tids = null, $subjectKeywords = null, $authorIds = null, $page = 1, $perPage = 10)
  {
    $builder = $this->page($page, $perPage);
    if ($tids) {
      $builder->where("tid", $tids);
    }
    if ($subjectKeywords) {
      $builder->where("subject", "%" . $subjectKeywords . "%", "LIKE");
    }
    if ($authorIds) {
      $builder->where("authorid", $authorIds);
    }
    return $builder->paginate();
  }
}
