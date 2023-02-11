<?php

namespace kernel\Foundation\HTTP\Request;

use kernel\Foundation\HTTP\Request;

class RequestPagination
{
  /**
   * 页数
   *
   * @var int
   */
  public $page = 1;
  /**
   * 每页条数
   *
   * @var int
   */
  public $limit = 10;
  /**
   * 查询时跳过前面指定条数的记录
   *
   * @var int
   */
  public $skip = null;
  public function __construct(Request $R)
  {
    if ($R->query->has("page")) {
      $this->page = (int)$R->query->get("page");
    }
    if ($R->query->has("limit")) {
      $this->limit = (int)$R->query->get("limit");
    }
    if ($R->query->has("perPage")) {
      $this->limit = (int)$R->query->get("perPage");
    }
    if ($R->query->has("skip")) {
      $this->skip = (int)$R->query->get("skip");
    }
  }
}
