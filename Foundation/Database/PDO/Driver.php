<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Log;
use kernel\Foundation\Response;
use PDO;

class Driver
{
  private \PDO $PDOInstance;
  function __construct(string $hostname = null, string $username = null, string $password = null, string $database = null, int $port = 3306, $options = null)
  {
    $link = new \PDO("mysql:dbname=$database;host:$hostname;port=$port", $username, $password, $options);

    if (!$link) {
      Response::error(500, "PDO:500001", $link->connect_errno() . ":" . $link->connect_error());
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
  public function query(string $query)
  {
    $result = null;
    $isSelect = strtoupper(substr($query, 0, strpos($query, " "))) === "SELECT";
    $data = $this->PDOInstance->query($query);
    if ($data === false) {
      $errorDetails = [
        "error" => $this->error(),
        "trace" => debug_backtrace(),
        "sql" => $query
      ];
      Log::record($errorDetails);
      Response::error(500, "DatabaseError:500001-" . $this->errno(), "服务器错误", $errorDetails);
    } else {
      if ($isSelect) {
        $result = $data->fetchAll(PDO::FETCH_ASSOC);
      } else {
        $result = $data->rowCount();
      }
    }

    return $result;
  }
}
