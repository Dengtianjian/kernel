<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\Data\DataConversion;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Output;
use kernel\Foundation\Data\Serializer;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\ReturnResult\ReturnResult;

class Controller
{
  /**
   * query数据转换规则
   * 实例化后该值会变成ControllerQuery实例
   *
   * @var array|\kernel\Foundation\Data\DataConversion|ControllerQuery
   * @deprecated
   */
  protected $query = null;
  /**
   * 请求体数据转换规则
   * 实例化后该值会变成ControllerBody实例
   *
   * @var array|\kernel\Foundation\Data\DataConversion|ControllerBody
   * @deprecated 序列化使用$requestBodySerializes，获取数据使用$requestBody
   */
  protected $body = null;

  /**
   * 查询参数校验器
   *
   * @var array|\kernel\Foundation\Validate\Validator
   * @deprecated 查询参数校验器使用 $requestQueryValidator
   */
  protected $queryValidator = [];
  /**
   * 请求体数据校验器
   *
   * @var array|\kernel\Foundation\Validate\Validator
   * @deprecated 校验使用$requestBodyValidator
   */
  protected $bodyValidator = [];
  /**
   * 响应的数据序列化规则
   *
   * @var array
   * @deprecated 响应数据序列化使用 $responseSerializes
   */
  protected $serializes = null;

  /**
   * 请求体数据
   * @var ControllerBody
   */
  protected $requestBody = null;
  /**
   * 请求体序列化
   * @var array
   */
  protected $requestBodySerializes = null;

  /**
   * 查询参数校验器
   *
   * @var array|\kernel\Foundation\Validate\Validator
   */
  protected $requestBodyValidator = null;

  /**
   * 请求的查询参数
   *
   * @var ControllerQuery
   * 
   */
  protected $requestQuery = null;

  /**
   * 请求的查询参数序列化
   * @var array
   */
  protected $requestQuerySerializes = null;
  /**
   * 查询参数校验器
   *
   * @var array|\kernel\Foundation\Validate\Validator'
   */
  protected $requestQueryValidator = null;

  /**
   * 响应的数据序列化规则
   *
   * @var array|string|boolean|integer|int|double|float
   */
  protected $responseSerializes = null;
  /**
   * 输出数据处理管道
   *
   * @var array
   */
  protected $pipes = [];
  /**
   * 请求实例
   *
   * @var \kernel\Foundation\HTTP\Request
   */
  protected $request = null;
  /**
   * 控制器执行完data方法后返回的响应实例
   *
   * @var ControllerResponse
   */
  public $response = null;

  function __construct(Request $request)
  {
    $this->request = $request;
    $this->response = new ControllerResponse(null);

    if ($this->query && !$this->requestQuerySerializes) {
      $this->requestQuerySerializes = $this->query;
    }
    if ($this->queryValidator && !$this->requestQueryValidator) {
      $this->requestQueryValidator = $this->queryValidator;
    }

    if ($this->body && !$this->requestBodySerializes) {
      $this->requestBodySerializes = $this->body;
    }
    if ($this->bodyValidator && !$this->requestBodyValidator) {
      $this->requestBodyValidator = $this->bodyValidator;
    }

    $this->requestQuery = new ControllerQuery($request, $this->requestQuerySerializes, $this->requestQueryValidator);
    $this->requestBody = new ControllerBody($request, $this->requestBodySerializes, $this->requestBodyValidator);

    $this->query = new ControllerQuery($request, $this->query, $this->queryValidator);
    $this->body = new ControllerBody($request, $this->body, $this->bodyValidator);
  }
  /**
   * 控制器执行处理方法执行前钩子
   *
   * @return void
   */
  final function before()
  {
    if ($this->query->validatedResult->error) {
      $this->response = $this->query->validatedResult;
    }
    if ($this->body->validatedResult->error) {
      $this->response = $this->body->validatedResult;
    }
  }
  /**
   * 控制器执行处理方法执行后钩子
   *
   * @return void
   */
  final function after()
  {
    if (!$this->response->error) {
      if (!is_null($this->pipes) && is_array($this->pipes) && count($this->pipes)) {
        $this->pipe();
      }
      if (!is_null($this->serializes) || is_array($this->serializes) && count($this->serializes)) {
        $this->serialization();
      }
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
    } else if (gettype($this->serializes) === "string") {
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
    if ($this->response->error)
      return;
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
