<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\Data\DataConversion;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Output;
use kernel\Foundation\Data\Serializer;

class Controller
{
  /**
   * query数据转换规则
   * 实例化后该值会变成ControllerQuery实例
   *
   * @var array|\kernel\Foundation\Data\DataConversion|ControllerQuery
   */
  protected $query = null;
  /**
   * 请求体数据转换规则
   * 实例化后该值会变成ControllerBody实例
   *
   * @var array|\kernel\Foundation\Data\DataConversion|ControllerBody
   */
  protected $body = null;
  /**
   * 查询参数校验器
   *
   * @var array|\kernel\Foundation\Validate\Validator
   */
  protected $queryValidator = [];
  /**
   * 请求体数据校验器
   *
   * @var array|\kernel\Foundation\Validate\Validator
   */
  protected $bodyValidator = [];
  /**
   * 响应的数据序列化规则
   *
   * @var array
   */
  protected $serializes = null;

  /**
   * 请求实例
   *
   * @var \kernel\Foundation\HTTP\Request
   */
  public $request = null;
  /**
   * 控制器执行完data方法后返回的响应实例
   *
   * @var \kernel\Foundation\HTTP\Response
   */
  public $response = null;

  function __construct(Request $request)
  {
    $this->request = $request;
    $this->query = new ControllerQuery($request, $this->query, $this->queryValidator);
    $this->body = new ControllerBody($request, $this->body, $this->bodyValidator);
  }
  final function completed()
  {
    if (!is_null($this->serializes) || is_array($this->serializes) && count($this->serializes)) {
      $this->serialization();
    }
  }
  private function serialization()
  {
    if ($this->serializes instanceof DataConversion) {
      $this->response->addData($this->serializes->data($this->response->getData())->convert(), true);
    } else if ($this->serializes instanceof Serializer) {
      $this->response->addData(Serializer::serialization($this->serializes->useRuleName, $this->response->getData()), true);
    } else if (is_array($this->serializes)) {
      $this->response->addData(Serializer::serialization($this->serializes, $this->response->getData()), true);
    }
  }
}
