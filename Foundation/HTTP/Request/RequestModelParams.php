<?php

namespace kernel\Foundation\HTTP\Request;

use kernel\Foundation\HTTP\Request;

class RequestModelParams extends RequestData
{
  private $_order = [];
  private $_orders = [];
  /**
   * 是否有传排序相关参数
   *
   * @var boolean
   */
  protected $_ordered = false;

  /**
   * 页数
   *
   * @var int
   */
  protected $_page = 1;
  /**
   * 每页条数
   *
   * @var int
   */
  protected $_perPage = 10;
  /**
   * 查询时跳过前面指定条数的记录
   *
   * @var int
   */
  protected $_skip = null;
  /**
   * 是否有传分页相关参数
   *
   * @var boolean
   */
  protected $_paged = false;

  public function __construct(Request $R)
  {
    /**
     * 分页相关参数处理
     */
    if ($R->query->has("page")) {
      $this->page((int)$R->query->get("page"));
      $this->paged(true);
    }
    if ($R->query->has("limit")) {
      $this->perPage((int)$R->query->get("limit"));
      $this->paged(true);
    }
    if ($R->query->has("perPage")) {
      $this->perPage((int)$R->query->get("perPage"));
      $this->paged(true);
    }
    if ($R->query->has("skip")) {
      $this->skip((int)$R->query->get("skip"));
      $this->paged(true);
    }

    /**
     * 排序相关参数处理
     */
    if ($R->query->has("order")) {
      $this->order(addslashes(trim($R->query->get("order"))));
      $this->ordered(true);
    }
    if ($R->query->has("orderBy")) {
      $this->orderBy(addslashes(trim($R->query->get("orderBy"))));
      $this->ordered(true);
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
        $this->orders($orderList);
        if (count($orderList)) {
          if (!$R->query->has("order")) {
            $this->order(array_keys($orderList)[0]);
          }
          if (!$R->query->has("orderBy")) {
            $this->orderBy($orderList[array_keys($orderList)[0]]);
          }
        }
      }
    }
  }
  /**
   * 设置|获取排序的字段
   *
   * @param string $fieldName 排序字段名称
   * @return string 排序字段名称
   */
  public function order($fieldName = null)
  {
    if (!is_null($fieldName)) {
      $this->_order['fieldName'] = $fieldName;
    }
    if (is_null($this->_order) || !isset($this->_order['fieldName'])) return null;
    return $this->_order['fieldName'];
  }
  /**
   * 设置|获取排序的方式
   *
   * @param string $sort 排序的方式：DESC、ASC
   * @return string 排序的方式：DESC、ASC
   */
  public function orderBy($sort = null)
  {
    if (!is_null($sort)) {
      $this->_order['sort'] = $sort;
    }
    if (is_null($this->_order) || !isset($this->_order['sort'])) return null;
    return $this->_order['sort'];
  }
  /**
   * 设置|获取多条排序规则
   *
   * @param array $rules 关联数组，键是排序的字段名，值为排序的方式：DESC、ASC
   * @return array 关联数组，键是排序的字段名，值为排序的方式：DESC、ASC
   */
  public function orders($rules = null)
  {
    if (!is_null($rules)) {
      $this->_orders = $rules;
    }
    if (is_null($this->_orders)) return null;
    return $this->_orders;
  }
  /**
   * 获取|标记有传排序参数
   *
   * @param bool $flag 如果有传该参数，就是标记为有传排序相关参数
   * @return bool
   */
  public function ordered($flag = null)
  {
    if (!is_null($flag)) {
      $this->_ordered = $flag;
    }
    return $this->_ordered;
  }


  /**
   * 获取/设置页数
   *
   * @param int $page 页数
   * @return int
   */
  public function page($page = null)
  {
    if (!is_null($page)) {
      $this->_page = $page;
    }
    return $this->_page;
  }
  /**
   * 获取/设置每页数量
   *
   * @param int $count 每页数量
   * @return int
   */
  public function perPage($count = null)
  {
    if (!is_null($count)) {
      $this->_perPage = $count;
    }
    return $this->_perPage;
  }
  /**
   * 获取/设置分页跳过前面多少条数据
   *
   * @param int $count 跳过数量
   * @return int
   */
  public function skip($count = null)
  {
    if (!is_null($count)) {
      $this->_skip = $count;
    }
    return $this->_skip;
  }
  /**
   * 获取是否被标记为已分页|标记为已传入分页参数
   *
   * @param bool $flag 设置值
   * @return bool
   */
  public function paged($flag = null)
  {
    if (!is_null($flag)) {
      $this->_paged = $flag;
    }
    return $this->_paged;
  }
}
