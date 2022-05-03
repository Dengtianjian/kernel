<?php

namespace kernel;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

$Config = [
  "mode" => "development", //* production development
  "version" => "0.1.1",
  "extensions" => false
];
