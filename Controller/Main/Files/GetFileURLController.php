<?php

namespace kernel\Controller\Main\Files;

use kernel\Facades\Storage;
use kernel\Foundation\Controller\Controller;

class GetFileURLController extends Controller
{
  public $requestBodySerializes = [
    "key" => "string",
    "keys" => "array<string>",
    "urlParams",
    "expires" => "int",
    "withSignature" => "boolean",
    "suffix" => "string"
  ];
  protected function buildURL(string $key)
  {
    $fileURL = Storage::url($key);
    if ($this->requestBody->has("urlParams")) {
      $fileURL->urlParams($this->body("urlParams"));
    }
    if ($this->requestBody->has("expires")) {
      $fileURL->expires($this->body("expires"));
    }
    if ($this->requestBody->has("withSignature")) {
      $fileURL->withSignature($this->body("withSignature"));
    }
    if ($this->requestBody->has("suffix")) {
      $fileURL->suffix($this->body("suffix"));
    }

    if (!$fileURL) return null;

    return $fileURL;
  }
  public function data()
  {
    $data = null;

    if ($this->requestBody->has("key")) {
      $data = $this->buildURL($this->body("key"));
      $data = $data ? $data->toString() : null;
    } else if ($this->requestBody->has("keys")) {
      $data = [];
      foreach ($this->body("keys") as $item) {
        $data[$item] = $this->buildURL($item);
        $data[$item] =  $data[$item] ?  $data[$item]->toString() : null;
      }
    }

    return $data;
  }
}
