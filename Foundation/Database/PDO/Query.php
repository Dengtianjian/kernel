<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\BaseObject;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class Query extends BaseObject
{
  private $executeType = "";
  private $options = [];
  private $conditions = [];
  private $filterNullConditions = [];
  public $tableName = "";
  public $sql = "";
  public $_reset = true;
  /**
   * @deprecated version
   *
   * @var boolean
   */
  private $_whereFilterNull = false;
  function __construct($tableName)
  {
    $this->tableName = $tableName;
  }
  static function ins($tableName)
  {
    return new Query($tableName);
  }
  function generateSql()
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
      case "increment":
        $sql = SQL::increment($this->tableName,  $this->options['increment']['field'], $this->options['increment']['value']);
        break;
      case "decrement":
        $sql = SQL::decrement($this->tableName,  $this->options['decrement']['field'], $this->options['decrement']['value']);
        break;
      case "exist":
        $sql = SQL::exist($this->tableName, $this->sql);
        break;
    }

    if (count($this->conditions) > 0 || count($this->filterNullConditions) > 0) {
      $conditions = array_filter($this->filterNullConditions, function ($item) {
        return !is_null($item['value']) || !empty($item['value']);
      });
      $conditions = array_merge($this->conditions, $conditions);
      if ($this->_whereFilterNull) {
        $conditions = array_filter($conditions, function ($item) {
          return !is_null($item['value']) || !empty($item['value']);
        });
        $conditions = array_values($conditions);
        $lastIndex = count($conditions) - 1;
        $conditions[$lastIndex]['operator'] = null;
      }

      if (count($conditions)) {
        $whereSql = SQL::conditions($conditions);
        $sql .= $whereSql;
      }
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
    if (isset($this->options['groupBy'])) {

      $sql .= " " . SQL::groupBy($this->options['groupBy']);
    }
    return $sql;
  }
  function reset($flag = null)
  {
    if (!is_null($flag)) {
      $this->_reset = $flag;
      return $this;
    }
    if ($this->_reset === false) {
      $this->_reset = true;
      return $this;
    }
    $this->options = [];
    $this->executeType = "";
    $this->conditions = [];
    $this->filterNullConditions = [];
  }
  /**
   * @deprecated version
   *
   * @param boolean $flag
   * @return void
   */
  function whereFilterNull($flag = true)
  {
    $this->_whereFilterNull = $flag;
    return $this;
  }
  function order($field, $by = "ASC")
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
  function field($fieldNames)
  {
    if (!isset($this->options['fields'])) {
      $this->options['fields'] = $fieldNames;
    } else {
      $this->options['fields'] = \array_merge($this->options['fields'], $fieldNames);
    }
    return $this;
  }
  function distinct($fieldName)
  {
    $sql = "DISTINCT " . SQL::addQuote([$fieldName])[0];
    if (!isset($this->options['fields'])) {
      $this->options['fields'] = [$sql];
    } else {
      \array_unshift($this->options['fields'], $sql);
    }

    return $this;
  }
  function groupBy($fieldName)
  {
    $this->options['groupBy'] = $fieldName;

    return $this;
  }
  function limit($startOrNumber, $number = null)
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
  function page($page, $pageLimt = 10)
  {
    $start = 0;
    if ($page > 0) {
      $start = $page * $pageLimt - $pageLimt;
    }
    $this->limit($start, $pageLimt);
    return $this;
  }
  function skip($number)
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
  function clearPage()
  {
    unset($this->options['limit']);
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
  function filterNullWhere($params, $value = null, $glue = "=", $operator = "AND")
  {
    if (is_string($params) && \preg_match_all("/\s+[=|<|>|BETWEEN|IN|LIKE|NULL|REGEXP]+/i", $params)) {
      array_push($this->filterNullConditions, [
        "statement" => $params,
        "fieldName" => null,
        "value" => null,
        "glue" => null,
        "operator" => $operator
      ]);
    } else if (is_array($params)) {
      foreach ($params as $fieldName => $param) {
        array_push($this->filterNullConditions, [
          "statement" => null,
          "fieldName" => $fieldName,
          "value" => $param,
          "glue" => $glue,
          "operator" => $operator
        ]);
      }
    } else {
      array_push($this->filterNullConditions, [
        "statement" => null,
        "fieldName" => $params,
        "value" => $value,
        "glue" => $glue,
        "operator" => $operator
      ]);
    }

    return $this;
  }
  function insert($data, $isReplaceInto = false)
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
  function batchInsert($fieldNames, $values, $isReplaceInto = false)
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
  function update($data)
  {
    $this->executeType = "update";
    $this->options['updateData'] = $data;
    $this->sql = $this->generateSql();
    $this->reset();
    return $this;
  }
  function batchUpdate($fieldNames, $values)
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
  function delete($directly = false)
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
  function increment($field, $value)
  {
    $this->executeType = "increment";
    $this->options["increment"] = [
      "field" => $field,
      "value" => $value
    ];
    $this->sql = $this->generateSql();
    $this->reset();
    return $this;
  }
  function decrement($field, $value)
  {
    $this->executeType = "decrement";
    $this->options["decrement"] = [
      "field" => $field,
      "value" => $value
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
  function sql()
  {
    return $this->sql;
  }
}
