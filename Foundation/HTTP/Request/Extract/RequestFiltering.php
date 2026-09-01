<?php

namespace kernel\Foundation\HTTP\Request\Extract;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Object\DataObject;

/**
 * 从请求中提取的筛选参数集合
 *
 * 解析请求 query 中的筛选相关参数（filter / search），
 * 并标记本次请求是否实际传入了任一个筛选参数（{@see filtered}）。
 */
class RequestFiltering extends DataObject
{
  /**
   * 筛选条件
   *
   * 关联数组，键为字段名，值为筛选值。由 query 的 filter 参数解析得到。
   *
   * @var array
   */
  protected $filter = [];
  /**
   * 模糊搜索关键词
   *
   * @var string|null
   */
  protected $search = null;
  /**
   * 本次请求是否传入了任一个筛选参数
   *
   * 只要 filter / search 中有一个能从 query 取到值，即为 true。
   *
   * @var boolean
   */
  protected $filtered = false;
  /**
   * 从请求中解析筛选参数
   *
   * 读取 query 中的 filter / search（存在才取，缺失则不覆盖默认），
   * 若取到任意筛选参数则标记 {@see filtered} 为 true，最后交由父类按数组填充属性。
   *
   * filter 支持逗号分隔的多条 `字段:值`，例如 `status:active,type:post`。
   *
   * @param Request $R 当前请求对象
   */
  public function __construct(Request $R)
  {
    $data = [];
    /**
     * 筛选条件处理
     */
    if ($R->query->has("filter")) {
      $filter = addslashes(trim($R->query->get("filter")));
      $conditions = [];
      if (strpos($filter, ",") !== false) {
        foreach (explode(",", $filter) as $item) {
          if (strpos($item, ":") !== false) {
            list($field, $value) = explode(":", $item, 2);
            $conditions[trim($field)] = trim($value);
          }
        }
      } elseif (strpos($filter, ":") !== false) {
        list($field, $value) = explode(":", $filter, 2);
        $conditions[trim($field)] = trim($value);
      }
      $data['filter'] = $conditions;
      $data['filtered'] = true;
    }
    if ($R->query->has("search")) {
      $data['search'] = addslashes(trim($R->query->get("search")));
      $data['filtered'] = true;
    }

    parent::__construct($data);
  }
  /**
   * 设置或获取单条筛选条件
   *
   * 读写一体：
   * - 仅传 $field 时返回该字段的筛选值（不存在返回 null）；
   * - 传 $field 与 $value 时设置该字段的值并返回 $this（链式）；
   * - 两者都不传时返回全部筛选条件数组。
   *
   * @param string|null $field 字段名
   * @param mixed       $value 筛选值
   * @return static|array|mixed
   */
  public function filter($field = null, $value = null)
  {
    if (!is_null($field) && !is_null($value)) {
      $this->filter[$field] = $value;
      return $this;
    }
    if (!is_null($field)) {
      return $this->filter[$field] ?? null;
    }
    return $this->filter;
  }
  /**
   * 设置或获取模糊搜索关键词
   *
   * 读写一体：传 $keyword 时设置 {@see search} 并返回 $this（链式）；不传时返回当前关键词。
   *
   * @param string|null $keyword 搜索关键词
   * @return static|string|null
   */
  public function search($keyword = null)
  {
    if (!is_null($keyword)) {
      $this->search = $keyword;
      return $this;
    }
    return $this->search;
  }
  /**
   * 读取或设置「是否传入了筛选参数」
   *
   * 读写一体：传 $flag 时设置 {@see filtered} 并返回 $this（链式）；不传时返回当前标记。
   *
   * @param bool|null $flag 设置值
   * @return static|bool
   */
  public function filtered($flag = null)
  {
    if (!is_null($flag)) {
      $this->filtered = $flag;
      return $this;
    }
    return $this->filtered;
  }
}
