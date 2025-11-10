<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Object\AbilityBaseObject;

class Paginator extends AbilityBaseObject
{
  /**
   * 当前页码
   * @var int
   */
  private $page = 1;
  /**
   * 每页获取的数量
   * @var int
   */
  private $perPage = 0;
  /**
   * 总条数
   * @var int
   */
  private $total = 0;
  /**
   * 当前页的数据条数
   * @var int
   */
  private $pageSize = 0;
  /**
   * 当前页的数据
   * @var array
   */
  private $items = [];

  function __construct($pageItems, $page, $perPage, $total)
  {
    $this->page = $page;
    $this->items = $pageItems;
    $this->perPage = $perPage;
    $this->total = $total;
    $this->pageSize = count($pageItems);
  }

  /**
   * 当前页码
   */
  function getPage()
  {
    return $this->currentPage;
  }
  /**
   * 每页获取的条数
   * @return int
   */
  function getPerPage()
  {
    return $this->perPage;
  }
  /**
   * 总条数
   * @return int
   */
  function getTotal()
  {
    return $this->total;
  }
  /**
   * 当前页获取到的数据数量
   * @return int
   */
  function getPageSize()
  {
    return $this->pageSize;
  }
  /**
   * 获取数据
   * @return array
   */
  function getItems()
  {
    return $this->items;
  }
  /**
   * 获取当前页第一条数据
   */
  function getFirstItem()
  {
    return $this->items[0];
  }
  /**
   * 获取当前页最后一条数据
   */
  function getLastItem()
  {
    return $this->items[count($this->items) - 1];
  }
  function toArray()
  {
    return $this->items;
  }
}