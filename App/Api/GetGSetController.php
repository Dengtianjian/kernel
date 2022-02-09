<?php

namespace kernel\App\Api;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Config;
use kernel\Foundation\Controller;
use kernel\Foundation\Request;
use kernel\Foundation\Response;

class GetGSetController extends Controller
{
  private $whiteListOfKeys = [
    "uid", "username", "adminid", "groupid", "setting/accessemail"
  ];
  public function data(Request $request)
  {
    $this->whiteListOfKeys = array_merge($this->whiteListOfKeys, Config::get("DZXGlobalVariablesWhiteList"));
    $keys = $request->body("key");
    if ($keys === null) {
      Response::success([]);
    }
    $sets = [];
    if (\is_string($keys)) {
      $keys = \explode(",", $keys);
    }
    foreach ($keys as $key) {
      if (in_array($key, $this->whiteListOfKeys)) {
        $ex = \explode("/", $key);
        $newKey = $key;
        if (count($ex) > 2) {
          $ex = \array_slice($ex, count($ex) - 2);
          $newKey = \implode("/", $ex);
        }

        // $sets[$newKey] = \getglobal($key);
      }
    }
    return $sets;
  }
}
