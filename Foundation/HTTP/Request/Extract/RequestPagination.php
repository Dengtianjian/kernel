<?php

namespace kernel\Foundation\HTTP\Request\Extract;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Object\DataObject;

/**
 * 从请求中提取的分页参数集合
 *
 * 解析请求 query 中的分页相关参数（page / perPage / limit / skip），
 * 并标记本次请求是否实际传入了任一分页参数（{@see paginated}）。
 *
 * 分页字段通过父类 {@see DataObject} 的魔术属性读写，例如：
 * - 读取：`$pagination->page`、`$pagination->perPage`
 * - 设置：`$pagination->page = 2`、`$pagination->perPage = 20`
 * 其中 limit 与 perPage 始终同步为同一值。
 */
class RequestPagination extends DataObject
{
  /**
   * 页数
   *
   * @var int
   */
  protected $page = 1;
  /**
   * 每页条数
   *
   * @var int
   */
  protected $perPage = 10;
  /**
   * 查询时跳过前面指定条数的记录
   *
   * @var int
   */
  protected $skip = null;
  /**
   * 每页条数（limit 别名）
   *
   * 与 {@see perPage} 保持同步：构造时无论传入 limit 还是 perPage，二者都会被设为相同值。
   *
   * @var int|null
   */
  protected $limit = null;
  /**
   * 本次请求是否传入了任一分页参数
   *
   * 只要 page / limit / perPage / skip 中有一个能从 query 取到值，即为 true。
   *
   * @var boolean
   */
  protected $paginated = false;
  /**
   * 从请求中解析分页参数
   *
   * 读取 query 中的 page / limit / perPage / skip（存在才取，缺失则不覆盖默认），
   * 若取到任意分页参数则标记 {@see paginated} 为 true，最后交由父类按数组填充属性。
   *
   * @param Request $R 当前请求对象
   */
  public function __construct(Request $R)
  {
    $data = [];
    if ($R->query->has("page")) {
      $data['page'] = (int)$R->query->get("page");
    }

    if ($R->query->has("limit")) {
      $data['limit'] = (int)$R->query->get("limit");
      $data["perPage"] = (int)$R->query->get("limit");
    }
    if ($R->query->has("perPage")) {
      $data["perPage"] = (int)$R->query->get("perPage");
      $data["limit"] = (int)$R->query->get("perPage");
    }
    if ($R->query->has("skip")) {
      $data["skip"] = (int)$R->query->get("skip");
    }

    if ($data) {
      $this->paginated(true);
    }

    parent::__construct($data);
  }
  /**
   * 读取或设置「是否传入了分页参数」
   *
   * 读写一体：传 $flag 时设置 {@see paginated} 并返回 $this（链式）；不传时返回当前标记。
   *
   * @param bool|null $flag 设置值
   * @return static|bool
   */
   public function paginated($flag = null)
   {
    if (!is_null($flag)) {
      $this->paginated = $flag;
      return $this;
    }
    return $this->paginated;
   }
}
