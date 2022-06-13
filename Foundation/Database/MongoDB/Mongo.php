<?php

namespace kernel\Foundation\Database\MongoDB;

use stdClass;

class Mongo
{
  private static Driver $driver;
  public static function driver(Driver $driver)
  {
    self::$driver = $driver;
  }
  public static function realId(string $id = ""): string
  {
    $objId = self::id($id);
    return $objId->__toString();
  }
  public static function id(string $id = ""): \MongoDB\BSON\ObjectId
  {
    return self::$driver->id($id);
  }
  public static function find(string $setName, array $filter = [], array $options = [])
  {
    return self::$driver->query($setName, $filter, $options);
  }
  public static function findOne(string $setName, array $filter = [], array $options = [])
  {
    $result = self::find($setName, $filter, $options);
    if (!empty($result)) return $result[0];
    return null;
  }
  public static function insert(string $setName, array $doc, array $options = [])
  {
    return self::$driver->insert($setName, $doc, $options);
  }
  public static function update(string $setName, array $query = [], array $updateData, array $options = [])
  {
    return self::$driver->update($setName, $query, $updateData, $options);
  }
  public static function delete(string $setName, array $query = [], array $options = [])
  {
    return self::$driver->delete($setName, $query, $options);
  }
  public static function command(array $commands): \MongoDB\Driver\Command
  {
    return self::$driver->commamd($commands);
  }
  public static function execCommand(string $databaseName, array $commands, array $options = [])
  {
    if (isset($commands['pipeline'])) {
      if (!isset($commands['cursor'])) {
        $commands['cursor'] = new stdClass;
      }
    }
    $command = self::$driver->commamd($commands);
    return self::$driver->execCommand($databaseName, $command, $options);
  }
  public static function optimParams(array $params): array
  {
    if (isset($params['_id'])) {
      if (!$params['_id'] instanceof \MongoDB\BSON\ObjectId) {
        $params['_id'] = self::id($params['_id']);
      }
    }
    return $params;
  }
}
