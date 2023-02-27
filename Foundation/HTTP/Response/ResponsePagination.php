<?php

namespace kernel\Foundation\HTTP\Response;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response;

class ResponsePagination extends Response
{
  /**
   * 请求体
   *
   * @var request
   */
  private $request = null;
  /**
   * 数据总量
   *
   * @var integer
   */
  private $total = null;
  /**
   * 当前页数数据量
   *
   * @var integer
   */
  private $items = null;
  /**
   * 响应分页类
   *
   * @param Request $R 请求体
   * @param integer $total 数据总量
   * @param mixed $data 数据
   */
  public function __construct(Request $R, $total, $data = null)
  {
    $this->request = $R;
    $this->total = $total;
    $this->ResponseData = $data;
    if (!is_null($data) && is_array($data)) {
      $this->items = count($data);
    }
  }
  /**
   * 设置数据总量
   *
   * @param integer $total 数据总量
   * @return ResponsePagination
   */
  public function setTotal($total)
  {
    $this->total = $total;
    return $this;
  }
  public function output()
  {
    $this->ResponseData = [
      "list" => $this->ResponseData,
      "pagination" => [
        "total" => $this->total,
        "limit" => $this->request->pagination->limit,
        "page" => $this->request->pagination->page,
        "skip" => $this->request->pagination->skip,
        "items" => $this->items
      ]
    ];

    parent::output();
  }
}
