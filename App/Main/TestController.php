<?php

namespace kernel\App\Main;

use Exception;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Data\DataConversion;
use kernel\Foundation\Data\Serializer;
use kernel\Foundation\Exception\Exception as ExceptionException;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\HTTP\Response\ResponseView;
use kernel\Foundation\Output;
use kernel\Foundation\ReturnResult;
use kernel\Foundation\Validate\ValidateArray;
use kernel\Foundation\Validate\ValidateRules;
use kernel\Foundation\Validate\Validator;

class TestController extends Controller
{
  // public $query = null;
  // public $body = [
  //   "uid" => "int",
  //   "user" => [
  //     "username" => "string",
  //     "age" => "int",
  //     "keys" => [
  //       "one" => "int",
  //       "three" => "string"
  //     ]
  //   ]
  // ];
  public function __construct(Request $R)
  {
    $this->serializes = [
      "uid" => "int",
      "country",
      "json",
      "serialize" => "serialize",
      "keys" => [
        "one"
      ],
      "user"
    ];
    // $this->query = new DataConversion([
    //   "username",
    //   "age" => "int"
    // ], true);
    // $this->body = new DataConversion([
    //   "uid" => "int",
    //   "age" => "int",
    //   "user" => [
    //     "username",
    //     "age" => "int",
    //     "keys" => new DataConversion([
    //       "one" => "string",
    //       "four"
    //     ], true, true)
    //   ]
    // ], true, true);
    // $this->body = new DataConversion([
    //   "keys" => new DataConversion([
    //     "one" => "string"
    //   ])
    // ], true, true);

    // $UsernameValidateRule = new ValidateRules();
    // $UsernameValidateRule->required("请输入用户名")->minLength(3, "用户名最少3个字符");
    // $PasswordValidateRule = new ValidateRules();
    // $PasswordValidateRule->minLength(7, "密码最少7个字符");
    // $NumberValidateRule = new ValidateRules();
    // $NumberValidateRule->type("int", "UID必须传入数值类型");

    // $QV = new ValidateArray([
    //   "username" => $UsernameValidateRule,
    //   "password" => $PasswordValidateRule
    // ]);

    // $AgeValidate = new ValidateRules();
    // $AgeValidate->type("int", "请输入正确的年龄数值")->range(0, 100, "年龄最小0，最大100");
    // $EmailValidate = new ValidateRules();
    // $EmailValidate->custom(function ($value) {
    //   $R = new ReturnResult(true);
    //   if (!preg_match("/^[a-zA-Z0-9_-]+@[a-zA-Z0-9_-]+(.[a-zA-Z0-9_-]+)+$/", $value)) {
    //     $R->error(400, 400, "请输入正确的邮箱地址", null, [
    //       "email" => $value
    //     ]);
    //   }

    //   return $R;
    // });

    // $AgesValidate = new ValidateArray();
    // $AgesValidate->type("int");
    // $KeyOneValidate = new ValidateRules();
    // $KeyOneValidate->required("one必传")->type("int", "one必须是数值类型");

    // $KeyValidate = new ValidateArray([
    //   "one" => $KeyOneValidate
    // ]);
    // $KeysValidate = new ValidateArray();
    // $KeysValidate->use($KeyValidate);
    // $UserValidate = new ValidateArray([
    //   "age" => $AgeValidate,
    //   "email" => $EmailValidate,
    //   "ages" => $AgesValidate,
    //   "keys" => $KeysValidate
    // ]);

    // $BV = new ValidateArray([
    //   // "uid" => $NumberValidateRule,
    //   "user" => $UserValidate
    // ]);
    // $this->queryValidator = new Validator($QV);
    // $this->bodyValidator = new Validator($BV);

    parent::__construct($R);
  }
  public function data(Request $R, $username)
  {
    // throw new Exception("test error");
    // Output::debug($this->body->some());
    return new Response(1);
    // $VR->minLength(2, "数组的每个元素最少2个字符");
    // $VR->type(["int", "string"], "数组的每个元素必须是数值类型");

    $V = new ValidateRules();
    $V->custom(function ($value) {
      $R = new ReturnResult(true);
      if (!preg_match("/^[a-zA-Z0-9_-]+@[a-zA-Z0-9_-]+(.[a-zA-Z0-9_-]+)+$/", $value)) {
        $R->error(400, 400, "请输入正确的邮箱地址");
      }

      return $R;
    });

    $VV = new Validator($V, "mail@isdtj.com");
    return $VV->validate();
    // $UsernameValidator = new Validator();
    // $UsernameValidator->minLength(3, "用户名最短3个字符");
    // $V = new Validator([
    //   "username" => $UsernameValidator
    // ]);
    // $V->data([
    //   "username" => "1"
    // ]);
    // return $V->validate();
    // return [
    //   1, 2, 3
    // ];
    // $Pagination = new ResponsePagination($R, 200);
    // $Pagination->addData([
    //   [
    //     "username" => "admin"
    //   ],
    //   [
    //     "username" => "test"
    //   ],
    // ]);
    // return $Pagination;
    // $Download = new ResponseDownload($R, File::genPath(F_APP_ROOT, ".git.zip"), null, 10);
    // return $Download;
    // $Res = new HTTPResponse();
    // return $Res->json()->success([
    //   "username" => "admin"
    // ], 200);
    // $R = new ResponseView("template", [
    //   "name" =>  $username,
    //   "age" => 18
    // ]);
    // $R->layout("default");

    return new ResponseView("template", [
      "name" => "test"
    ]);
    // View::page("template", [
    //   "name" => $username,
    //   "age" => 18
    // ]);
    // exit;
  }
}
