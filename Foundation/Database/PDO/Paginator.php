<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 分页结果封装
 *
 * Paginator 由 Query::paginate() 自动创建并返回，封装了分页查询的
 * 结果数据和元信息（页码、每页条数、总条数），提供统一的数据访问接口。
 *
 * ## 获取方式
 *
 * ```php
 * $paginator = DB::table('users')->where('status', 1)->paginate(['page' => 2, 'perPage' => 15]);
 *
 * // 遍历当前页数据
 * foreach ($paginator->getItems() as $item) { ... }
 *
 * // 获取分页元信息
 * $paginator->getPage();      // 当前页码
 * $paginator->getTotal();     // 总条数
 * $paginator->getPageSize();  // 当前页实际数据量
 * ```
 *
 * ## 安全边界
 *
 * `getFirstItem()` / `getLastItem()` 在数据为空时返回 `null` 而非触发 PHP 警告。
 *
 * @see Query::paginate() 查询构建器分页方法
 */
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

  /**
   * 构造分页结果
   *
   * 通常由 Query::paginate() 自动调用，也可手动构造用于特殊场景。
   *
   * @param array $pageItems 当前页的数据数组
   * @param int   $page      当前页码
   * @param int   $perPage   每页获取的条数
   * @param int   $total     总条数
   */
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
   * @return int
   */
  function getPage()
  {
    return $this->page;
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
   * 设置当前页数据（用于 eager loading 注入 Model 实例）
   * @param array $items
   */
  function setItems(array $items)
  {
    $this->items = $items;
  }
  /**
   * 获取当前页第一条数据
   * @return mixed|null 第一条数据，如果当前页为空则返回 null
   */
  function getFirstItem()
  {
    return $this->items ? $this->items[0] : null;
  }
  /**
   * 获取当前页最后一条数据
   * @return mixed|null 最后一条数据，如果当前页为空则返回 null
   */
  function getLastItem()
  {
    return $this->items ? $this->items[count($this->items) - 1] : null;
  }
  /**
   * 将分页数据导出为数组
   * @return array 当前页的数据数组，等同于 getItems()
   */
  function toArray()
  {
    return $this->items;
  }
}