<?php

namespace kernel\Foundation\ReturnResult;

use kernel\Foundation\ReturnResult\ReturnResult;

class ReturnList extends ReturnResult
{
  /**
   * 实例化列表返回
   *
   * @param array $list 数据列表
   * @param integer $total 总条数
   * @param int $page 页数
   * @param int $limit 每页数量
   * @param int $items 当前页数据量
   */
  public function __construct($list, $total = 0, $page = null, $limit = null, $items = null)
  {
    $this->ResponseData = $list;
    $this->_total = $total;
    if ($page) {
      $this->_page = $page;
    }
    if ($limit) {
      $this->_limit = $limit;
    }
    if ($items) {
      $this->_items = $items;
    } else {
      $this->_items = is_array($list) ? count($list) : 0;
    }
  }
  private $_page = null;
  /**
   * 获取/设置页数，传入页数即为设置页数
   *
   * @param int $page 页数，传入即为设置页数
   * @return ReturnList|int
   */
  public function page($page = null)
  {
    if ($page) {
      $this->_page = $page;
      return $this;
    }
    return $this->_page;
  }
  private $_limit = null;
  /**
   * 获取/设置每页数量，传入即为设置每页数量
   *
   * @param int $limit 每页数量，传入即为设置每页数量
   * @return ReturnList|int
   */
  public function limit($limit = null)
  {
    if ($limit) {
      $this->_limit = $limit;
      return $this;
    }
    return $this->_limit;
  }
  private $_total = null;
  /**
   * 获取/设置总条数，传入即为设置列表总条数
   *
   * @param int $total 总条数，传入即为设置列表总条数
   * @return ReturnList|int
   */
  public function total($total = null)
  {
    if ($total) {
      $this->_total = $total;
      return $this;
    }
    return $this->_total;
  }
  private $_items = null;
  /**
   * 获取/设置总条数，传入即为设置列表总条数
   *
   * @param int $total 总条数，传入即为设置列表总条数
   * @return ReturnList|int
   */
  public function items($items = null)
  {
    if ($items) {
      $this->_items = $items;
      return $this;
    }
    return $this->_items;
  }
}
