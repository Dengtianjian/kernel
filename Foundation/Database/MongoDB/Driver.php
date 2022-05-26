<?php

namespace kernel\Foundation\Database\MongoDB;

use kernel\Foundation\Output;

class Driver
{
  private $instance = null;
  private $bulk = null;
  private $config = [
    "host" => "localhost",
    "port" => 27017,
    "username" => "",
    "password" => "",
    "databaseName" => "",
    "options" => []
  ];
  function __construct(string $host = "localhost", int $port = 27017, string $username, string $password, string $databaseName, array $options = [])
  {
    $this->config['host'] = $host;
    $this->config['port'] = $port;
    $this->config['username'] = $username;
    $this->config['password'] = $password;
    $this->config['databaseName'] = $databaseName;
    $this->config['options'] = $options;

    $optionStrings = [];
    foreach ($options as $itemKey => $optionItem) {
      array_push($optionStrings, "$itemKey=$optionItem");
    }
    $optionStrings = implode(";", $optionStrings);
    $this->instance = new \MongoDB\Driver\Manager("mongodb://$username:$password@$host:$port/$databaseName?$optionStrings");
    $this->bulk = new \MongoDB\Driver\BulkWrite;
  }
  private function genNamespace(string $extra): string
  {
    return $this->config['databaseName'] . ".$extra";
  }
  public function query(string $setName, array $filter = [], array $options = [])
  {
    $query = new \MongoDB\Driver\Query($filter, $options);
    $rows = $this->instance->executeQuery($this->genNamespace($setName), $query);

    $result = [];
    foreach ($rows as $row) {
      if (isset($row->_id)) {
        $row->_id = $row->_id->__toString();
      }
      array_push($result, $row);
    }

    return $result;
  }
  public function id()
  {
    return new \MongoDB\BSON\ObjectId();
  }
  public function insert(string $setName, array $doc, array $options = [])
  {
    $this->bulk->insert($doc);
    return $this->instance->executeBulkWrite($this->genNamespace($setName), $this->bulk, $options);
  }
  public function update(string $setName, array $query = [], array $updateData = [], array $options = [])
  {
    $this->bulk->update($query, $updateData);
    return $this->instance->executeBulkWrite($this->genNamespace($setName), $this->bulk, $options);
  }
}
