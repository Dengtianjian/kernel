<?php

namespace kernel\Foundation\Database\MongoDB;

class Driver
{
  private ?\MongoDB\Driver\Manager $instance = null;
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
  }
  private function genNamespace(string $extra): string
  {
    return $this->config['databaseName'] . ".$extra";
  }
  public function query(string $setName, array $filter = [], array $options = []): array
  {
    $query = new \MongoDB\Driver\Query($filter, $options);
    $rows = $this->instance->executeQuery($this->genNamespace($setName), $query);

    $result = [];
    foreach ($rows as $row) {
      if (isset($row->_id) && $row->_id instanceof \MongoDB\BSON\ObjectId) {
        $row->_id = $row->_id->__toString();
      }
      array_push($result, $row);
    }

    return $result;
  }
  public function id(string $id = ""): \MongoDB\BSON\ObjectId
  {
    if (empty($id)) {
      return new \MongoDB\BSON\ObjectId();
    }
    return new \MongoDB\BSON\ObjectId($id);
  }
  public function insert(string $setName, array $doc, array $options = []): int
  {
    $bulk = new \MongoDB\Driver\BulkWrite();
    $bulk->insert($doc);
    return $this->instance->executeBulkWrite($this->genNamespace($setName), $bulk, $options)->getInsertedCount();
  }
  public function update(string $setName, array $query = [], array $updateData = [], array $options = []): \MongoDB\Driver\WriteResult
  {
    $bulk = new \MongoDB\Driver\BulkWrite();
    $bulk->update($query, $updateData);
    return $this->instance->executeBulkWrite($this->genNamespace($setName), $bulk, $options);
  }
  public function delete(string $setName, array $query, array $options = []): int
  {
    $bulk = new \MongoDB\Driver\BulkWrite();
    $bulk->delete($query, $options);
    return $this->instance->executeBulkWrite($this->genNamespace($setName), $bulk, $options)->getDeletedCount();
  }
  public function commamd(array $commands = [], ?array $options = []): \MongoDB\Driver\Command
  {
    return new \MongoDB\Driver\Command($commands, $options);
  }
  public function execCommand(string $databaseName, \MongoDB\Driver\Command $command, array $options = []): array
  {
    $cursor = $this->instance->executeCommand($databaseName, $command, $options);
    $rows = $cursor->toArray();
    $result = [];
    foreach ($rows as $row) {
      if (isset($row->_id) && $row->_id instanceof \MongoDB\BSON\ObjectId) {
        $row->_id = $row->_id->__toString();
      }
      array_push($result, $row);
    }
    return $result;
  }
}
