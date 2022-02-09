<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Str;

class Model
{
  public $tableName = "";
  private Query $query;
  private $returnSql = false;
  function __construct(string $tableName = null)
  {
    if ($tableName) {
      $this->tableName = $tableName;
    }
    $this->query = new Query($this->tableName);
  }
  function order(string $field, string $by = "ASC")
  {
    $this->query->order($field, $by);
    return $this;
  }
  function field(...$fieldNames)
  {
    $this->query->field($fieldNames);
    return $this;
  }
  function limit(int $startOrNumber, int $number = null)
  {
    $this->query->limit($startOrNumber, $number);
    return $this;
  }
  function page(int $pages, int $pageLimit = 110)
  {
    $this->query->page($pages, $pageLimit);
    return $this;
  }
  function skip($number)
  {
    $this->query->skip($number);
    return $this;
  }
  function where($fieldNameOrFieldValue, $value = null, $glue = "=", $operator = "AND")
  {
    $this->query->where($fieldNameOrFieldValue, $value, $glue, $operator);
    return $this;
  }
  function sql($yes = true)
  {
    $this->returnSql = $yes;
    return $this;
  }
  function insert(array $data, bool $isReplaceInto = false)
  {
    $sql = $this->query->insert($data, $isReplaceInto)->sql();
    if ($this->returnSql) return $sql;
    return DB::query($sql);
  }
  function insertId()
  {
    return DB::insertId();
  }
  function batchInsert(array $fieldNames, array $values, bool $isReplaceInto = false)
  {
    $sql = $this->query->batchInsert($fieldNames, $values, $isReplaceInto)->sql();
    if ($this->returnSql) return $sql;
    return DB::query($sql);
  }
  function update(array $data)
  {
    $sql = $this->query->update($data)->sql();
    if ($this->returnSql) return $sql;
    return DB::query($sql);
  }
  function batchUpdate(array $fieldNames, array $values)
  {
    $sql = $this->query->batchUpdate($fieldNames, $values)->sql();
    if ($this->returnSql) return $sql;
    return DB::query($sql);
  }
  function delete(bool $directly = false)
  {
    $sql = $this->query->delete($directly)->sql();
    if ($this->returnSql) return $sql;
    return DB::query($sql);
  }
  function getAll()
  {
    if ($this->returnSql) return $this->query->get()->sql();
    return DB::getAll($this->query);
  }
  function getOne()
  {
    if ($this->returnSql) return $this->query->limit(1)->get()->sql();
    return DB::getOne($this->query);
  }
  function count($field = "*")
  {
    $sql = $this->query->count($field)->sql();
    if ($this->returnSql) return $sql;
    $countResult = DB::query($sql);
    if (!empty($countResult)) {
      return (int)$countResult['0']["COUNT($field)"];
    }
    return null;
  }
  function genId($prefix = "", $suffix = "")
  {
    $nowTime = time();
    return $nowTime . substr(md5($prefix . time() . Str::generateRandomString(8) . $suffix), 0, 24 - strlen($nowTime));
  }
}
