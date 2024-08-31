<?php

namespace kernel\Foundation\HTTP\Request;

use kernel\Foundation\Log;

class RequestBody extends RequestData
{
  protected $body = [];
  public function __construct($dataConversion = null, $validator = null)
  {
    $this->dataConversion = $dataConversion;
    $this->validator = $validator;

    $RequestHeaders = getallheaders();
    $contentType = $RequestHeaders['Content-Type'] ?: null;
    if (strpos($contentType, "multipart/form-data") !== false) $contentType = "multipart/form-data";
    if (strpos($contentType, "application/x-www-form-urlencoded") !== false) $contentType = "application/x-www-form-urlencoded";

    $input = \file_get_contents("php://input");

    $data = [];
    if ($input) {
      switch ($contentType) {
        case "application/xml":
          $data = simplexml_load_string($input, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);
          $this->data = \array_merge($data, $_POST);
          break;
        case "application/json":
          $data = json_decode($input, true);
          $this->data = \array_merge($data, $_POST);
          break;
        case "text/plain":
        case "application/javascript":
          $this->data = addslashes(urldecode($input));
          break;
        case "text/html":
          $this->data = htmlspecialchars_decode(addslashes($input));
          break;
        case "application/x-www-form-urlencoded":
        case "multipart/form-data":
          $this->data = $_POST;
          break;
        default:
          $this->data = addslashes($input);
          break;
      }
    } else {
      $this->data = null;
    }
  }
}
