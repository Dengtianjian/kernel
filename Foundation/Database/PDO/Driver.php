<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Exception\RuyiException;
use kernel\Foundation\Log;
use kernel\Foundation\Output;
use kernel\Foundation\Response;
use PDO;

class Driver
{
  private \PDO $PDOInstance;
  function __construct(string $hostname = null, string $username = null, string $password = null, string $database = null, int $port = 3306, $options = null)
  {
    $link = null;
    try {
      $link = new \PDO("mysql:dbname=$database;host:$hostname;port=$port", $username, $password, $options);
    } catch (\Exception $e) {
      throw new Exception("数据连接失败：" . $e->getMessage(), 500, "PDO:500001", $e->getMessage() . ":" . $e->getCode());
    }


    if (!$link) {
      throw new Exception("数据连接失败", 500, "PDO:500001", $link->connect_errno() . ":" . $link->connect_error());
    }
    $this->PDOInstance = $link;
    return $this;
  }
  public function error()
  {
    return $this->PDOInstance->errorInfo();
  }
  public function errno()
  {
    return $this->PDOInstance->errorCode();
  }
  public function insertId()
  {
    return $this->PDOInstance->lastInsertId();
  }
  public function query(string $query, $selectCallback = null)
  {
    $result = null;
    $isSelect = strtoupper(substr($query, 0, strpos($query, " "))) === "SELECT";
    $data = $this->PDOInstance->query($query);
    if ($data === false) {
      $errorDetails = [
        "message" => join(" ", $this->error()),
        "error" => $this->error(),
        "trace" => debug_backtrace(),
        "sql" => $query
      ];
      throw new RuyiException("数据库错误", 500, "DatabaseError:500:" . $this->error()[0], $errorDetails);
    } else {
      if ($isSelect) {
        if ($selectCallback) {
          while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
            $selectCallback($row);
          }
          $result = null;
        } else {
          $result = $data->fetchAll(PDO::FETCH_ASSOC);
        }
      } else {
        $result = $data->rowCount();
      }
    }

    return $result;
  }
}
