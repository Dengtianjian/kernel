<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\Data\DataConversion;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Output;
use kernel\Foundation\Data\Serializer;
use kernel\Foundation\HTTP\Response;

class Controller
{
  /**
   * query数据转换规则
   * 实例化后该值会变成ControllerQuery实例
   *
   * @var array|\kernel\Foundation\Data\DataConversion|ControllerQuery
   */
  public $query = null;
  /**
   * 请求体数据转换规则
   * 实例化后该值会变成ControllerBody实例
   *
   * @var array|\kernel\Foundation\Data\DataConversion|ControllerBody
   */
  public $body = null;
  /**
   * 查询参数校验器
   *
   * @var array|\kernel\Foundation\Validate\Validator
   */
  public $queryValidator = [];
  /**
   * 请求体数据校验器
   *
   * @var array|\kernel\Foundation\Validate\Validator
   */
  public $bodyValidator = [];
  /**
   * 响应的数据序列化规则
   *
   * @var array
   */
  public $serializes = null;
  /**
   * 输出数据处理管道
   *
   * @var array
   */
  public $pipes = [];
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
    $this->response = new Response(null);
    $this->query = new ControllerQuery($request, $this->query, $this->queryValidator);
    $this->body = new ControllerBody($request, $this->body, $this->bodyValidator);
  }
  /**
   * 控制器处理方法执行完成，执行完成方法
   *
   * @return void
   */
  final function completed()
  {
    if (!is_null($this->pipes) && is_array($this->pipes) && count($this->pipes)) {
      $this->pipe();
    }
    if (!is_null($this->serializes) || is_array($this->serializes) && count($this->serializes)) {
      $this->serialization();
    }
  }
  /**
   * 序列化数据
   *
   * @return void
   */
  private function serialization()
  {
    $ClassNamespace = explode("\\", get_class($this));
    $ClassName = $ClassNamespace[count($ClassNamespace) - 1];
    $ClassName = str_replace("Controller", "", $ClassName);
    $ClassName = lcfirst($ClassName);
    if ($this->serializes instanceof DataConversion) {
      $this->response->addData($this->serializes->data($this->response->getData())->convert(), true);
    } else if ($this->serializes instanceof Serializer) {
      $this->response->addData(Serializer::serialization($this->serializes->useRuleName, $this->response->getData()), true);
    } else if (is_array($this->serializes)) {
      $this->response->addData(Serializer::serialization($this->serializes, $this->response->getData(), $ClassName), true);
    }
  }
  /**
   * 输出数据管道处理
   *
   * @return void
   */
  private function pipe()
  {
    if (!$this->request->query->has("_pipes") && !$this->request->body->has("_pipes")) {
      return;
    }
    if ($this->response->error) return;
    $requestPipes = $this->request->query->get("_pipes");
    if (!$requestPipes) {
      $requestPipes = $this->request->body->get("_pipes");
    }
    if (!is_array($requestPipes)) {
      $requestPipes = explode(",", $this->request->query->get("_pipes"));
    }

    $requestPipes = array_intersect($requestPipes, $this->pipes);
    foreach ($requestPipes as $PipeName) {
      if (method_exists($this, $PipeName)) {
        $this->$PipeName();
        if ($this->response->error) {
          break;
        }
      }
    }
  }
}
