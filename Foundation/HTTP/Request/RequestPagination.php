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
  protected $_passed = false;
  public function __construct(Request $R)
  {
    if ($R->query->has("page")) {
      $this->page((int)$R->query->get("page"));
      $this->passed(true);
    }
    if ($R->query->has("limit")) {
      $this->perPage((int)$R->query->get("limit"));
      $this->passed(true);
    }
    if ($R->query->has("perPage")) {
      $this->perPage((int)$R->query->get("perPage"));
      $this->passed(true);
    }
    if ($R->query->has("skip")) {
      $this->skip((int)$R->query->get("skip"));
      $this->passed(true);
    }
  }
  /**
   * getter 临时使用，后期删除
   *
   * @param string $name 属性名称
   * @return int|null
   */
  public function __get($name)
  {
    if ($name === "limit") $name = "perPage";
    if (property_exists($this, "_$name")) {
      return $this->{"_$name"};
    }
    return null;
  }
  /**
   * setter 临时使用，后期删除
   *
   * @param string $name 属性名称
   * @param int $value 属性新值
   */
  public function __set($name, $value)
  {
    if (property_exists($this, "_$name")) {
      $this->{"_$name"} = $value;
      return true;
    }
    return false;
  }
  /**
   * 获取/设置页数
   *
   * @param int $page 页数
   * @return int
   */
  public function page($page = null)
  {
    if ($page) {
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
    if ($count) {
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
    if ($count) {
      $this->_skip = $count;
    }
    return $this->_skip;
  }
  /**
   * 获取/设置已传入分页参数
   *
   * @param bool $flag 设置值
   * @return bool
   */
  public function passed($flag = null)
  {
    if ($flag) {
      $this->_passed = $flag;
    }
    return $this->_passed;
  }
}
