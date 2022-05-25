<?php

namespace kernel\Foundation\Database\MongoDB;

use kernel\Foundation\Output;

class Driver
{
  public function connect()
  {
    $manager = new \MongoDB\Driver\Manager("mongodb://localhost");
    $stats = new \MongoDB\Driver\Command([
      "listDatabases" => 1
    ]);
    $res = $manager->executeCommand("admin", $stats);
    Output::debug($res->toArray());
  }
}
