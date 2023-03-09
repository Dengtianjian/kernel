<?php

namespace kernel\Platform\DiscuzX\Model\Thread;

use gstudio_kernel\Foundation\ReturnResult\ReturnList;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXModel;

class DiscuzXForumThreadModel extends DiscuzXModel
{
  public $tableName = "forum_thread";
  public function items($tids = null, $subjectKeywords = null, $authorIds = null, $page = 1, $perPage = 10)
  {
    if ($tids) {
      $this->where("tid", $tids);
    }
    if ($subjectKeywords) {
      $this->where("subject", "%" . $subjectKeywords . "%", "LIKE");
    }
    if ($authorIds) {
      $this->where("authorid", $authorIds);
    }
    $T = clone $this;
    $this->page($page, $perPage);
    return new ReturnList($this->getAll(), $T->count(), $page, $perPage);
  }
}
