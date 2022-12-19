<?php

namespace kernel;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

$Config = [
  "mode" => "production", //* production development
  "version" => "0.1.12.20220715",
  "extensions" => false
];
