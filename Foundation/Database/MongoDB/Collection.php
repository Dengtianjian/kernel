<?php

namespace kernel\Foundation\Database\MongoDB;

use kernel\Foundation\Output;

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
  public function find(array $filter = [], array $options = []): array
  {
    return Mongo::find($this->collectionName, $filter, $options);
  }
  public function findOne(array $filter = [], array $options = []): array
  {
    return Mongo::find($this->collectionName, $filter, $options);
  }
  public function insert(array $doc = [], array $options = []): int
  {
    return Mongo::insert($this->collectionName, $doc, $options);
  }
  public function update(array $query = [], array $updateData, array $options = []): \MongoDB\Driver\WriteResult
  {
    return Mongo::update($this->collectionName, $query, $updateData, $options);
  }
  public function delete(array $query = [], array $options = [])
  {
    return Mongo::delete($this->collectionName, $query, $options);
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
