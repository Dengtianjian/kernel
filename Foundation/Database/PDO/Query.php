<?php

namespace kernel\Foundation\Database\PDO;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class Query
{
  private string $executeType = "";
  private array $options = [];
  private array $conditions = [];
  public string $tableName = "";
  public string $sql = "";
  function __construct(string $tableName)
  {
    $this->tableName = $tableName;
  }
  static function ins(string $tableName)
  {
    return new Query($tableName);
  }
  function generateSql(): string
  {
    $this->sql = $sql = "";
    switch ($this->executeType) {
      case "insert":
      case "replace":
        $sql .= SQL::insert($this->tableName, $this->options['insertData'], $this->executeType === "replace");
        break;
      case "batchInsert":
      case "batchReplace":
        $sql .= SQL::batchInsert($this->tableName, $this->options['batchInsert']['fields'], $this->options['batchInsert']['values'], $this->executeType === "batchReplace");
        break;
      case "update":
        $sql = SQL::update($this->tableName, $this->options['updateData']);
        break;
      case "batchUpdate":
        $sql = SQL::batchUpdate($this->tableName, $this->options['batchUpdateData']['fields'], $this->options['batchUpdateData']['values']);
        break;
      case "delete":
        $sql = SQL::delete($this->tableName, $this->sql);
        break;
      case "get":
        $sql = SQL::select($this->tableName, isset($this->options['fields']) ? $this->options['fields'] : "*", $this->sql);
        break;
      case "count":
        $sql = SQL::count($this->tableName, $this->options['count']['field']);
        break;
      case "exist":
        $sql = SQL::exist($this->tableName, $this->sql);
        break;
    }

    if (count($this->conditions) > 0) {
      $whereSql = SQL::conditions($this->conditions);
      $sql .= $whereSql;
    }

    if (isset($this->options['order'])) {
      $limitSql = SQL::order($this->options['order']);

      $sql .= " $limitSql";
    }
    if (isset($this->options['limit'])) {
      if (isset($this->options['limit']['start']) && $this->executeType != "delete") {
        $limitSql = SQL::limit($this->options['limit']['start'], $this->options['limit']['number']);
      } else {
        $limitSql = SQL::limit($this->options['limit']['number']);
      }

      $sql .= " $limitSql";
    }
    return $sql;
  }
  function reset(): void
  {
    $this->options = [];
    $this->executeType = "";
    $this->conditions = [];
  }
  function order(string $field, string $by = "ASC")
  {
    if (!isset($this->options['order'])) {
      $this->options['order'] = [
        [
          "field" => $field,
          "by" => $by
        ]
      ];
    } else {
      array_push($this->options['order'], [
        "field" => $field,
        "by" => $by
      ]);
    }
    return $this;
  }
  function field(array $fieldNames)
  {
    if (!isset($this->options['fields'])) {
      $this->options['fields'] = $fieldNames;
    } else {
      $this->options['fields'] = \array_merge($this->options['fields'], $fieldNames);
    }
    return $this;
  }
  function limit(int $startOrNumber, int $number = null)
  {
    $data = [];
    if ($number === null) {
      $data['number'] = $startOrNumber;
    } else {
      $data['start'] = $startOrNumber;
      $data['number'] = $number;
    }
    if (isset($this->options['limit'])) {
      $this->options['limit'] = \array_merge($this->options['limit'], $data);
    } else {
      $this->options['limit'] = $data;
    }

    return $this;
  }
  function page(int $page, int $pageLimt = 10)
  {
    $start = $page * $pageLimt - $pageLimt;
    $this->limit($start, $pageLimt);
    return $this;
  }
  function skip(int $number)
  {
    if ($this->options['limit']) {
      $this->options['limit']['start'] = $number;
    } else {
      $this->options['limit'] = [
        "start" => $number
      ];
    }
    return $this;
  }
  function where($params, $value = null, $glue = "=", $operator = "AND")
  {
    // DONE 重构where方法，不管传入什么参数最后都push到conditions格式为 [field,value,glue,operator]
    if (is_string($params) && \preg_match_all("/\s+[=|<|>|BETWEEN|IN|LIKE|NULL|REGEXP]+/i", $params)) {
      array_push($this->conditions, [
        "statement" => $params,
        "fieldName" => null,
        "value" => null,
        "glue" => null,
        "operator" => $operator
      ]);
    } else if (is_array($params)) {
      foreach ($params as $fieldName => $param) {
        array_push($this->conditions, [
          "statement" => null,
          "fieldName" => $fieldName,
          "value" => $param,
          "glue" => $glue,
          "operator" => $operator
        ]);
      }
    } else {
      array_push($this->conditions, [
        "statement" => null,
        "fieldName" => $params,
        "value" => $value,
        "glue" => $glue,
        "operator" => $operator
      ]);
    }

    return $this;
  }
  function insert(array $data, $isReplaceInto = false)
  {
    if ($isReplaceInto) {
      $this->executeType = "replace";
    } else {
      $this->executeType = "insert";
    }
    $this->options['insertData'] = $data;

    $this->sql = $this->generateSql();
    $this->reset();
    return $this;
  }
  function batchInsert(array $fieldNames, array $values, bool $isReplaceInto = false)
  {
    if ($isReplaceInto) {
      $this->executeType = "batchReplace";
    } else {
      $this->executeType = "batchInsert";
    }
    $this->options['batchInsert'] = [
      "fields" => $fieldNames,
      "values" => $values
    ];

    $this->sql = $this->generateSql();
    $this->reset();
    return $this;
  }
  function update(array $data)
  {
    $this->executeType = "update";
    $this->options['updateData'] = $data;
    $this->sql = $this->generateSql();
    $this->reset();
    return $this;
  }
  function batchUpdate(array $fieldNames, array $values)
  {
    $this->executeType = "batchUpdate";
    $this->options['batchUpdateData'] = [
      "fields" => $fieldNames,
      "values" => $values
    ];
    $this->sql = $this->generateSql();
    $this->reset();
    return $this;
  }
  function delete(bool $directly = false)
  {
    if ($directly) {
      $this->executeType = "delete";
    } else {
      $this->executeType = "softDelete";
    }
    $this->sql = $this->generateSql();
    $this->reset();
    return $this;
  }
  function get()
  {
    $this->executeType = "get";
    $this->sql = $this->generateSql();
    $this->reset();
    return $this;
  }
  function count($field = "*")
  {
    $this->executeType = "count";
    $this->options["count"] = [
      "field" => $field
    ];
    $this->sql = $this->generateSql();
    $this->reset();
    return $this;
  }
  function exist()
  {
    $this->executeType = "exist";
    $this->sql = $this->generateSql();
    $this->reset();
    return $this;
  }
  function sql(): string
  {
    return $this->sql;
  }
}
