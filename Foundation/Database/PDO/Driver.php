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
  private PDO $PDOInstance;
  /**
   * 构建 PDO 驱动
   * @param string $hostname 连接主机名
   * @param string $username 连接到数据库的用户名
   * @param string $password 连接到数据库时的密码
   * @param string $database 使用的数据库名称
   * @param int $port 连接主机的端口
   * @param array $options PDO 实例时的选项
   * @throws \kernel\Foundation\Exception\Exception
   * @link https://www.php.net/manual/zh/pdo.construct
   */
  function __construct($hostname = null, $username = null, $password = null, $database = null, $port = 3306, $options = null)
  {
    /**
     * @var PDO
     */
    $link = null;
    try {
      $link = new PDO("mysql:dbname=$database;host:$hostname;port=$port", $username, $password, $options);
    } catch (\Exception $e) {
      throw new RuyiException(
        "数据连接失败：" . $e->getMessage(),
        500,
        join(['PDO', 500000, $e->getCode()], ":"),
        $e->getTrace()
      );
    }

    if (!$link) {
      throw new RuyiException(
        "数据连接失败",
        500,
        join(['PDO', 500001, $link->errorCode()], ":"),
        $link->errorInfo()
      );
    }
    $this->PDOInstance = $link;
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
  /**
   * 执行SQL语句
   * @param string $querySQL SQL 语句
   * @throws \kernel\Foundation\Exception\RuyiException
   * @return bool|int|\PDOStatement
   */
  public function query($querySQL)
  {
    $result = null;
    $isSelect = strtoupper(substr($querySQL, 0, strpos($querySQL, " "))) === "SELECT";
    $data = $this->PDOInstance->query($querySQL);
    if ($data === false) {
      $errorDetails = [
        "message" => join(" ", $this->error()),
        "error" => $this->error(),
        "trace" => debug_backtrace(),
        "sql" => $querySQL
      ];
      throw new RuyiException("数据库错误", 500, "DatabaseError:500:" . $this->errno(), $this->error());
    } else {
      if ($isSelect) {
        $result = $data;
      } else {
        $result = $data->rowCount();
      }
    }

    return $result;
  }
  /**
   * 开始事务
   * @throws \kernel\Foundation\Exception\RuyiException
   * @return bool 成功时返回 `true` ， 或者在失败时返回 `false` 
   */
  public function beginTransaction()
  {
    $BeignResult = $this->PDOInstance->beginTransaction();
    if (!$BeignResult) {
      throw new RuyiException("数据库错误", 500, "beginTranscationError:500:" . $this->errno(), $this->error());
    }

    return true;
  }
  /**
   * 提交一个事务，数据库连接返回到自动提交模式直到下次调用 PDO::beginTransaction() 开始一个新的事务为止
   * @throws \kernel\Foundation\Exception\RuyiException
   * @return bool 成功时返回 `true` ， 或者在失败时返回 `false` 
   */
  public function commit()
  {
    $CommitResult = $this->PDOInstance->commit();
    if (!$CommitResult) {
      throw new RuyiException("数据库错误", 500, "commitTranscationError:500:" . $this->errno(), $this->error());
    }

    return true;
  }
  /**
   * 检查是否在事务内 检查驱动内的事务当前是否处于激活。此方法仅对支持事务的数据库驱动起作用
   * @return bool 如果当前事务处于激活，则返回 `true` ，否则返回 `false`
   */
  public function inTranscation()
  {
    return $this->PDOInstance->inTransaction();
  }
  /**
   * 回滚事务 回滚由 PDO::beginTransaction() 发起的当前事务。如果没有事务激活，将抛出一个 PDOException 异常。
   * @throws \kernel\Foundation\Exception\RuyiException
   * @return bool 成功时返回 `true` ， 或者在失败时返回 `false`s
   */
  public function rollBack()
  {
    $RollbackResult = $this->PDOInstance->rollBack();

    if (!$RollbackResult) {
      throw new RuyiException("数据库错误", 500, "transcationRollbackError:500:" . $this->errno(), $this->error());
    }

    return true;
  }
  /**
   * 预处理要执行的语句，并返回语句对象
   * 为 PDOStatement::execute() 方法预处理待执行的 SQL 语句。 语句模板可以包含零个或多个参数占位标记，格式是命名（:name）或问号（?）的形式，当它执行时将用真实数据取代。 在同一个语句模板里，命名形式和问号形式不能同时使用；只能选择其中一种参数形式。 请用参数形式绑定用户输入的数据，不要直接字符串拼接到查询里。
   *
   * @param string $query 必须是对目标数据库服务器有效的 SQL 语句模板。
   * @param array $options 数组包含一个或多个 key=>value 键值对，为返回的 PDOStatement 对象设置属性。 常见用法是：设置 `PDO::ATTR_CURSOR` 为 `PDO::CURSOR_SCROLL`，将得到可滚动的光标。 某些驱动有驱动级的选项，在 prepare 时就设置。
   * @return bool|\PDOStatement 如果数据库服务器已经成功预处理语句， PDO::prepare() 返回 PDOStatement 对象。 如果数据库服务器无法预处理语句， PDO::prepare() 返回 `false` 或抛出 PDOException (取决于 错误处理 )。
   */
  function prepare($query, $options = [])
  {
    return $this->PDOInstance->prepare($query, $options);
  }
  /**
   * 查询
   * @param string $querySQL 查询语句
   * @param PDO::FETCH_ASSOC|PDO::FETCH_BOTH|PDO::FETCH_BOUND|PDO::FETCH_CLASS|PDO::FETCH_INTO|PDO::FETCH_LAZY|PDO::FETCH_NAMED|PDO::FETCH_NUM|PDO::FETCH_OBJ|PDO::FETCH_PROPS_LATE $mode 控制下一行如何返回给调用者。此值必须是 PDO::FETCH_* 系列常量中的一个，缺省为 PDO::ATTR_DEFAULT_FETCH_MODE 的值 （默认为 PDO::FETCH_BOTH ）。  
- PDO::FETCH_ASSOC：返回一个索引为结果集列名的数组
- PDO::FETCH_BOTH（默认）：返回一个索引为结果集列名和以0开始的列号的数组
- PDO::FETCH_BOUND：返回 true ，并分配结果集中的列值给 PDOStatement::bindColumn() 方法绑定的 PHP 变量。
- PDO::FETCH_CLASS：返回请求类的新实例，通过将结果集的列映射到类的属性以实现对象初始化。此过程发生于构造方法调用之前，允许填充属性，无论其可见性如何，也不管是否被标记为 readonly。若类中不存在对应属性且存在魔术方法 __set() ，则调用该方法；否则创建动态 public 属性。但如果同时指定了 PDO::FETCH_PROPS_LATE flag，则将在填充属性 前 调用构造函数。若 mode 包含 PDO::FETCH_CLASSTYPE （例如 PDO::FETCH_CLASS | PDO::FETCH_CLASSTYPE ），类名由第一列的值确定。
- PDO::FETCH_INTO：更新一个被请求类已存在的实例，映射结果集中的列到类中命名的属性
- PDO::FETCH_LAZY： PDO::FETCH_BOTH 和 PDO::FETCH_OBJ 组合使用，返回 PDORow 对象，该对象在访问时创建对象属性名。
- PDO::FETCH_NAMED：返回与 PDO::FETCH_ASSOC 具有相同形式的数组，除了如果有多个同名列，则该键引用的值将是具有该列名的行中所有值的数组
- PDO::FETCH_NUM：返回一个索引为以0开始的结果集列号的数组
- PDO::FETCH_OBJ：返回一个属性名对应结果集列名的匿名对象
- PDO::FETCH_PROPS_LATE：当与 PDO::FETCH_CLASS 一起使用时，类的构造方法在从相应的列值分配属性之前被调用。
   * @param mixed $cursorOrientation **暂不可用** 对于 一个 PDOStatement 对象表示的可滚动游标，该值决定了哪一行将被返回给调用者。此值必须是 PDO::FETCH_ORI_* 系列常量中的一个，默认为 PDO::FETCH_ORI_NEXT。要想让 PDOStatement 对象使用可滚动游标，必须在用 PDO::prepare() 预处理SQL语句时，设置 PDO::ATTR_CURSOR 属性为 PDO::CURSOR_SCROLL。
   * @param mixed $cursorOffset **暂不可用** 对于一个 cursorOrientation 参数设置为 PDO::FETCH_ORI_ABS 的 PDOStatement 对象代表的可滚动游标，此值指定结果集中想要获取行的绝对行号。
    对于一个 cursorOrientation 参数设置为 PDO::FETCH_ORI_REL 的 PDOStatement 对象代表的可滚动游标，此值指定想要获取行相对于调用 PDOStatement::fetch() 前游标的位置
   */
  public function fetch($querySQL, $mode = PDO::FETCH_ASSOC, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
  {
    $PDOStatement = $this->query($querySQL);

    return $PDOStatement->fetch($mode, $cursorOrientation, $cursorOffset);
  }
  public function fetchAll($querySQL, $mode = PDO::FETCH_ASSOC)
  {
    $PDOStatement = $this->query($querySQL);

    return $PDOStatement->fetchAll($mode);
  }
  public function fetchColumn($querySQL, $column = 0)
  {
    $PDOStatement = $this->query($querySQL);

    return $PDOStatement->fetchColumn($column);
  }
  public function fetchObject($querySQL, $class = "stdClass", $constructorArgs = [])
  {
    $PDOStatement = $this->query($querySQL);

    return $PDOStatement->fetchObject($class, $constructorArgs);
  }
  public function fetchFunc($querySQL, $callback)
  {
    $PDOStatement = $this->query($querySQL);

    return $PDOStatement->fetchAll(PDO::FETCH_FUNC, $callback);
  }
}
