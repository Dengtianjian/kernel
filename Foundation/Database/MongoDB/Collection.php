<?php

namespace kernel\Foundation\Database\MongoDB;

use kernel\Foundation\Output;
use stdClass;

class Collection
{
  public string $collectionName = "";
  public static ?\kernel\Foundation\Database\MongoDB\Collection $instance = null;
  public static function instance()
  {
    if (!self::$instance) {
      self::$instance = new static();
    }
    return self::$instance;
  }
  public function id(string $id = ""): \MongoDB\BSON\ObjectId
  {
    return Mongo::id($id);
  }
  public function realId(string $id): string
  {
    return Mongo::realId($id);
  }
  public function find(array $filter = [], array $options = [], bool $associative = false): array|stdClass
  {
    $filter = Mongo::optimParams($filter);
    $result = Mongo::find($this->collectionName, $filter, $options);
    if ($result && is_array($result) && !empty($result) && $associative) {
      return json_decode(json_encode($result), true);
    }
    return $result;
  }
  public function findOne(array $filter = [], array $options = [], bool $associative = false): stdClass|null|array
  {
    $filter = Mongo::optimParams($filter);
    $result = Mongo::findOne($this->collectionName, $filter, $options);

    if ($result && is_array($result) && !empty($result) && $associative) {
      return json_decode(json_encode($result), true);
    }
    return $result;
  }
  public function insert(array $doc = [], array $options = []): int
  {
    $doc = Mongo::optimParams($doc);
    return Mongo::insert($this->collectionName, $doc, $options);
  }
  public function update(array $query = [], array $updateData, array $options = []): \MongoDB\Driver\WriteResult
  {
    $query = Mongo::optimParams($query);
    return Mongo::update($this->collectionName, $query, $updateData, $options);
  }
  public function delete(array $query = [], array $options = [])
  {
    $query = Mongo::optimParams($query);
    return Mongo::delete($this->collectionName, $query, $options);
  }
  public function exist(array $filter): bool
  {
    return $this->findOne($filter) !== null;
  }
  public function command(array $commands): \MongoDB\Driver\Command
  {
    return Mongo::command($commands);
  }
  public function execCommand(array $commands, array $options = [])
  {
    return Mongo::execCommand($this->collectionName, $commands, $options);
  }
}
