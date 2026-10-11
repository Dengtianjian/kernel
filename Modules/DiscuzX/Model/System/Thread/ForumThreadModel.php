<?php

namespace kernel\Modules\DiscuzX\Model\System;

use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXModel;

class ForumThreadModel extends DiscuzXModel
{
  public $tableName = "forum_thread";
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
