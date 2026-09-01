<?php

namespace kernel\Foundation\HTTP\Response;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Request\Extract\RequestPagination;
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
   * 从请求中提取的分页参数
   *
   * @var RequestPagination
   */
  private $pagination = null;
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
    $this->pagination = new RequestPagination($R);
    $this->total = $total;
    $this->responseData = $data;
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
    $limit = (int)$this->pagination->perPage;
    $page = (int)$this->pagination->page;
    $skip = $this->pagination->skip;

    $this->responseData = [
      "list" => $this->responseData,
      "pagination" => [
        "total" => $this->total,
        "limit" => $limit,
        "page" => $page,
        "skip" => $skip,
        "items" => $this->items
      ]
    ];

    parent::output();
  }
}
