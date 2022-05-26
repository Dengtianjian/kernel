<?php

namespace kernel\Foundation\Database\MongoDB;

class Mongo
{
  private static Driver $driver;
  public static function driver(Driver $driver)
  {
    self::$driver = $driver;
  }
  public static function id(): string
  {
    return self::$driver->id();
  }
  public static function find(string $setName, array $filter = [], array $options = [])
  {
    return self::$driver->query($setName, $filter, $options);
  }
  public static function findOne(string $setName, array $filter = [], array $options = [])
  {
    $result = self::find($setName, $filter, $options);
    if (!empty($result)) return $result[0];
    return [];
  }
  public static function insert(string $setName, array $doc, array $options = [])
  {
    return self::$driver->insert($setName, $doc, $options);
  }
  public static function update(string $setName, array $query = [], array $updateData, array $options = [])
  {
    return self::$driver->update($setName, $query, $updateData, $options);
  }
}
