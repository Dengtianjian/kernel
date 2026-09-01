<?php

namespace kernel\Foundation\HTTP\Request\Extract;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Object\DataObject;

/**
 * 从请求中提取的排序参数集合
 *
 * 解析请求 query 中的排序相关参数（order / orderBy / orders），
 * 并标记本次请求是否实际传入了任一个排序参数（{@see sorted}）。
 */
class RequestSorting extends DataObject
{
  /**
   * 单条排序规则
   *
   * 关联数组，包含排序字段名（fieldName）与排序方式（sort）。
   *
   * @var array
   */
  protected $order = [];
  /**
   * 多条排序规则
   *
   * 关联数组，键为排序字段名，值为排序方式（DESC、ASC）。
   *
   * @var array
   */
  protected $orders = [];
  /**
   * 本次请求是否传入了任一个排序参数
   *
   * 只要 order / orderBy / orders 中有一个能从 query 取到值，即为 true。
   *
   * @var boolean
   */
  protected $sorted = false;
  /**
   * 从请求中解析排序参数
   *
   * 读取 query 中的 order / orderBy / orders（存在才取，缺失则不覆盖默认），
   * 若取到任意排序参数则标记 {@see sorted} 为 true，最后交由父类按数组填充属性。
   *
   * @param Request $R 当前请求对象
   */
  public function __construct(Request $R)
  {
    $data = [];
    /**
     * 排序相关参数处理
     */
    if ($R->query->has("order")) {
      $data['order'] = addslashes(trim($R->query->get("order")));
      $data['sorted'] = true;
    }
    if ($R->query->has("orderBy")) {
      $data['orderBy'] = addslashes(trim($R->query->get("orderBy")));
      $data['sorted'] = true;
    }
    if ($R->query->has("orders")) {
      $orders = addslashes(trim($R->query->get("orders")));

      if (strpos($orders, ",") !== false) {
        $orders = array_filter(explode(",", $orders), function ($item) {
          return addslashes(trim($item));
        });
        $orderList = [];
        foreach ($orders as $item) {
          if (strpos($item, ":") === false) {
            $orderList[$item] = $R->query->has("orderBy") ? $R->query->get("orderBy") : "ASC";
          } else {
            list($fieldName, $sortType) = explode(":", $item);
            if (!$sortType) {
              $sortType = $R->query->has("orderBy") ? $R->query->get("orderBy") : "ASC";
            }
            $orderList[$fieldName] = $sortType;
          }
        }
        $data['orders'] = $orderList;
        if (count($orderList)) {
          if (!$R->query->has("order")) {
            $data['order'] = array_keys($orderList)[0];
          }
          if (!$R->query->has("orderBy")) {
            $data['orderBy'] = $orderList[array_keys($orderList)[0]];
          }
        }
      }
    }

    parent::__construct($data);
  }
  /**
   * 设置或获取排序的字段
   *
   * 读写一体：传 $fieldName 时设置 {@see order} 的字段名并返回 $this（链式）；不传时返回当前字段名。
   *
   * @param string|null $fieldName 排序字段名称
   * @return static|string|null
   */
  public function order($fieldName = null)
  {
    if (!is_null($fieldName)) {
      $this->order['fieldName'] = $fieldName;
      return $this;
    }
    if (is_null($this->order) || !isset($this->order['fieldName'])) return null;
    return $this->order['fieldName'];
  }
  /**
   * 设置或获取排序的方式
   *
   * 读写一体：传 $sort 时设置 {@see order} 的排序方式并返回 $this（链式）；不传时返回当前排序方式。
   *
   * @param string|null $sort 排序方式：DESC、ASC
   * @return static|string|null
   */
  public function orderBy($sort = null)
  {
    if (!is_null($sort)) {
      $this->order['sort'] = $sort;
      return $this;
    }
    if (is_null($this->order) || !isset($this->order['sort'])) return null;
    return $this->order['sort'];
  }
  /**
   * 设置或获取多条排序规则
   *
   * 读写一体：传 $rules 时设置 {@see orders} 并返回 $this（链式）；不传时返回当前规则数组。
   *
   * @param array|null $rules 关联数组，键是排序的字段名，值为排序方式：DESC、ASC
   * @return static|array|null
   */
  public function orders($rules = null)
  {
    if (!is_null($rules)) {
      $this->orders = $rules;
      return $this;
    }
    if (is_null($this->orders)) return null;
    return $this->orders;
  }
  /**
   * 读取或设置「是否传入了排序参数」
   *
   * 读写一体：传 $flag 时设置 {@see sorted} 并返回 $this（链式）；不传时返回当前标记。
   *
   * @param bool|null $flag 设置值
   * @return static|bool
   */
  public function sorted($flag = null)
  {
    if (!is_null($flag)) {
      $this->sorted = $flag;
      return $this;
    }
    return $this->sorted;
  }
}
