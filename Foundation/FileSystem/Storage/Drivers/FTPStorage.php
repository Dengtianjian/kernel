<?php

namespace kernel\Foundation\FileSystem\Storage\Drivers;

use Exception;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\FileSystem;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\FileSystem\Storage\StorageFile;

/**
 * FTP / FTPS 磁盘存储
 *
 * {@see AbstractStorage} 的 FTP 实现，基于 PHP 的 FTP 扩展（`ftp_*`）对接 FTP 服务器：
 * 上传、读取元信息、删除、生成访问地址。实例代表「一台 FTP 服务器上的一个目录」。
 *
 * 使用要点：
 * - **磁盘名默认 `ftp`（可省略），要与装配时注册的键名一致**：`FileStorage` 会把 `name()` 写进 files 表的 `disk` 列，
 *   之后按该名字取回磁盘（如 `Storage::disk($file['disk'])`），对不上就会「磁盘未实例化」；
 * - **远端路径**统一是 `{basePath}/{fileName}` 拼成的**绝对路径**（以 `/` 开头），分隔符固定用 `/`
 *   （不经过 {@see \kernel\Foundation\FileSystem\Path::join()} —— 那个用 `DIRECTORY_SEPARATOR`，
 *   Windows 下会拼出反斜杠，对 FTP 是错的）；
 * - **{@see url()} 不含凭据**（可安全返回给客户端）；需要服务端自己去取回资源时用 {@see fetchURL()}
 *   （带凭据，**不要返回给客户端**）—— 例如预览 `ftp://` 文件时由
 *   {@see \kernel\Foundation\HTTP\Response\ResponseProxy} 代理取回（现代浏览器已移除 FTP 支持）；
 * - `put()` 是**搬运语义**：上传成功后删除本地源文件（与 `FileSystem::upload()` 一致）；
 * - `exists()` 基于 `ftp_size()`：部分服务器对目录/无权限路径也返回 -1，故它只适合判断**文件**；
 * - `get()` 返回的 `width` / `height` 固定为 `null`（无法在远端算图片尺寸），
 *   `filePath` 是**远端绝对路径**（不是本地路径，不能直接交给 `ResponseFile`）；
 * - 连接按需建立并在实例内复用，可调用 {@see close()} 主动断开（析构时也会断开）。
 *
 * @package kernel\Foundation\FileSystem\Storage\Drivers
 */
class FTPStorage extends AbstractStorage
{
  /**
   * 服务器地址（域名或 IP）
   *
   * @var string
   */
  protected $host = null;
  /**
   * 登录用户名；为 null 时走匿名登录
   *
   * @var string|null
   */
  protected $user = null;
  /**
   * 登录密码
   *
   * @var string|null
   */
  protected $password = null;
  /**
   * 端口，默认 21
   *
   * @var int
   */
  protected $port = 21;
  /**
   * 是否使用 SSL（FTPS）
   *
   * 为 true 时用 `ftp_ssl_connect()` 建连接、{@see url()} 也返回 `ftps://`。
   * 注意要和服务端的模式一致（显式 AUTH TLS / 隐式 SSL），否则连不上。
   *
   * @var boolean
   */
  protected $secure = false;
  /**
   * 是否使用被动模式（PASV），默认 true（在 NAT/防火墙后必须开）
   *
   * @var boolean
   */
  protected $passive = true;
  /**
   * 连接/读写超时（秒）
   *
   * @var int
   */
  protected $timeout = 10;
  /**
   * 复用的 FTP 连接句柄
   *
   * 注意：PHP 7 返回 resource、PHP 8.1+ 返回 `FTP\Connection` 对象，
   * 所以这里只用 null 判断是否已连接，不用 is_resource()。
   *
   * @var mixed|null
   */
  protected $connection = null;

  /**
   * 构造 FTP 磁盘
   *
   * 除 `$host` 外都可省略；磁盘名固定排在最后，默认 `ftp`：
   * `new FTPStorage("ftp.example.com")` → 匿名登录、根目录、被动模式、磁盘名 `ftp`。
   *
   * @param string $host 服务器地址（域名或 IP）；留空表示尚未配置，使用时连接会失败
   * @param string|null $user 登录用户名；为 null 时匿名登录
   * @param string|null $password 登录密码
   * @param int $port 端口，默认 21
   * @param string $basePath 服务器上的基准目录（所有 fileKey 相对它解析），默认根目录
   * @param boolean $secure 是否使用 FTPS
   * @param boolean $passive 是否使用被动模式，默认 true
   * @param int $timeout 连接/读写超时（秒），默认 10
   * @param string $name 磁盘名称，默认 `ftp`（要与装配时注册的键名一致，会写入 files 表的 disk 列）
   * @return void
   */
  public function __construct($host = "", $user = null, $password = null, $port = 21, $basePath = "", $secure = false, $passive = true, $timeout = 10, $name = "ftp")
  {
    parent::__construct($name, $basePath);

    $this->host = $host;
    $this->user = $user;
    $this->password = $password;
    $this->port = (int) $port;
    $this->secure = (bool) $secure;
    $this->passive = (bool) $passive;
    $this->timeout = (int) $timeout;
  }

  /**
   * 断开 FTP 连接（幂等）
   *
   * @return void
   */
  public function close()
  {
    if ($this->connection !== null) {
      @ftp_close($this->connection);
      $this->connection = null;
    }
  }

  public function __destruct()
  {
    $this->close();
  }

  /**
   * 获取文件信息
   *
   * 以 `ftp_size()` 判断文件是否存在：返回负数视为不存在，此时返回 false（与本地磁盘一致）。
   * `width` / `height` 固定为 null，`filePath` 是远端绝对路径。
   *
   * @param string $fileName 文件名称（含相对路径）
   * @return false|StorageFile 文件信息；文件不存在时返回 false
   */
  public function get($fileName)
  {
    $connection = $this->connect();
    if (!$connection) return false;

    $remotePath = $this->remotePath($fileName);
    $size = @ftp_size($connection, $remotePath);
    if ($size < 0) return false;

    $pathInfo = pathinfo($fileName);
    $directory = isset($pathInfo['dirname']) && $pathInfo['dirname'] !== "." ? $pathInfo['dirname'] : null;

    return new StorageFile([
      "key" => $fileName,
      "disk" => $this->name,
      "name" => $pathInfo['basename'],
      "source_file_name" => $pathInfo['basename'],
      "path" => $directory,
      "extension" => isset($pathInfo['extension']) ? $pathInfo['extension'] : null,
      "size" => $size,
      "width" => null,
      "height" => null,
      "filePath" => $remotePath,
    ]);
  }

  /**
   * 上传文件到 FTP 服务器
   *
   * 目标目录不存在会自动逐级创建；上传成功后**删除本地源文件**（搬运语义）。
   *
   * @param array $file 源文件（上传数组）
   * @param string|null $saveFileName 保存后的文件名称（含相对路径）；为 null 时用源文件名
   * @return false|StorageFile 成功返回文件信息；失败返回 break 错误态
   */
  public function put($file, $saveFileName = null)
  {
    $saveFileName = $saveFileName ?: $file['name'];
    $pathInfo =  pathinfo($saveFileName);
    $tempFileName = join("", [uniqid("temp_"), ".", $pathInfo['extension']]);
    $tempFileInfo = FileSystem::upload($file, Path::join(Path::storage(), "ftp_temp"), $tempFileName);
    if (!$tempFileInfo || !FileSystem::exists($tempFileInfo['filePath'])) {
      return $this->break(500, 500, "上传文件失败", "临时文件存储失败");
    }

    $width = 0;
    $height = 0;
    if (FileHelper::isImage($tempFileInfo['filePath'])) {
      $imageInfo = \getimagesize($tempFileInfo['filePath']);
      $width = $imageInfo[0];
      $height = $imageInfo[1];
    }

    $connection = $this->connect();
    if (!$connection) return $this->forwardBreak();

    //* 上传前预检被动模式的数据端口：不通就立刻给出可操作的错误，而不是干等到 timeout（可能几百秒）
    if (!$this->checkPassiveDataPort($connection)) return $this->forwardBreak();

    $remotePath = $this->remotePath($saveFileName);
    $this->ensureDirectory(dirname($remotePath), $connection);

    error_clear_last();
    $uploaded = @ftp_put($connection, $remotePath, $tempFileInfo['filePath'], FTP_BINARY);

    if (!$uploaded) {
      $lastError = error_get_last();
      @unlink($tempFileInfo['filePath']);

      return $this->break(500, "ftpPutFailed", "FTP 上传失败", [
        "remote" => $remotePath,
        "local" => $tempFileInfo['filePath'],
        "localReadable" => is_readable($tempFileInfo['filePath']),
        "remoteSize" => @ftp_size($connection, $remotePath),
        "passive" => $this->passive,
        "timeout" => $this->timeout,
        // 扩展抛出的警告原文（最常见如 "ftp_put(): Connection timed out"）
        "reason" => is_array($lastError) ? $lastError['message'] : null
      ]);
    }

    @unlink($tempFileInfo['filePath']);

    $fileInfo = $this->get($saveFileName);

    if (!$fileInfo) return $this->break(500, 500, "获取上传的文件信息失败");
    $fileInfo->width = $width;
    $fileInfo->height = $height;

    return $fileInfo;
  }

  /**
   * 删除 FTP 服务器上的文件
   *
   * 先经 {@see exists()} 确认存在（不存在返回 404 错误态），再删除。
   *
   * @param string $fileName 文件名称（含相对路径）
   * @return boolean 删除结果
   */
  public function delete($fileName)
  {
    if (!$this->exists($fileName)) return $this->break(404, 404, "文件不存在");

    $connection = $this->connect();
    if (!$connection) return false;

    return @ftp_delete($connection, $this->remotePath($fileName));
  }

  /**
   * 判断远端文件是否存在
   *
   * 基于 `ftp_size()`：返回负数视为不存在（注意部分服务器对目录/无权限路径同样返回 -1）。
   *
   * @param string $fileName 文件名称（含相对路径）
   * @return boolean
   */
  public function exists($fileName)
  {
    $connection = $this->connect();
    if (!$connection) return false;

    return @ftp_size($connection, $this->remotePath($fileName)) >= 0;
  }

  /**
   * 生成文件的访问地址（**不含凭据**，可安全返回给客户端）
   *
   * 形如 `ftp://host[:port]/{basePath}/{fileName}`；协议相对地址固定用 `/`。
   * 注意现代浏览器已不支持 FTP，直接给出该地址无法在页面内预览 ——
   * 预览场景应由服务端用 {@see fetchURL()} 取回后代理输出。
   *
   * @param string $fileName 文件名称（含相对路径）
   * @return string
   */
  public function url($fileName)
  {
    return $this->buildURL($fileName, false);
  }

  /**
   * 生成供**服务端取回**的访问地址（**含凭据**，切勿返回给客户端）
   *
   * 形如 `ftp://user:password@host[:port]/{basePath}/{fileName}`，交给
   * {@see \kernel\Foundation\HTTP\Response\ResponseProxy} 之类的代理用 `fopen()` 取回
   * （FTP 凭据只能放在 URL 里 —— ftp:// 流包装器的 context 不支持账号密码）。
   *
   * @param string $fileName 文件名称（含相对路径）
   * @return string
   */
  public function fetchURL($fileName)
  {
    return $this->buildURL($fileName, true);
  }

  /**
   * 拼接访问地址
   *
   * @param string $fileName 文件名称（含相对路径）
   * @param boolean $withCredentials 是否带上账号密码（仅供服务端取回使用）
   * @return string
   */
  protected function buildURL($fileName, $withCredentials = false)
  {
    $scheme = $this->secure ? "ftps" : "ftp";
    $credentials = "";

    if ($withCredentials && $this->user !== null && $this->user !== "") {
      $credentials = rawurlencode($this->user);
      if ($this->password !== null && $this->password !== "") {
        $credentials .= ":" . rawurlencode($this->password);
      }
      $credentials .= "@";
    }

    //* 标准控制端口 21 省略不写，其余端口显式带上
    $port = ($this->port && $this->port !== 21) ? ":" . $this->port : "";

    return $scheme . "://" . $credentials . $this->host . $port . $this->remotePath($fileName);
  }

  /**
   * 把文件键拼成远端绝对路径（固定用 `/` 分隔）
   *
   * @param string $fileName 文件名称（含相对路径）
   * @return string 以 `/` 开头的远端路径
   */
  protected function remotePath($fileName)
  {
    $basePath = trim((string) $this->basePath, "/");
    $fileName = ltrim((string) $fileName, "/");

    return "/" . ($basePath === "" ? "" : $basePath . "/") . $fileName;
  }

  /**
   * 逐级创建远端目录（已存在则忽略）
   *
   * FTP 没有「递归创建目录」，只能从根开始逐段 `ftp_mkdir()`；已存在的段会失败，
   * 这里静默忽略（用 `@` 抑制「目录已存在」的警告），不视为错误。
   *
   * @param string $remoteDirectory 远端目录绝对路径
   * @param mixed $connection FTP 连接句柄
   * @return boolean 始终返回 true（无法创建时会在后续 put 上报错）
   */
  protected function ensureDirectory($remoteDirectory, $connection)
  {
    $remoteDirectory = trim((string) $remoteDirectory, "/");
    if ($remoteDirectory === "") {
      return true;
    }

    $path = "";
    foreach (explode("/", $remoteDirectory) as $segment) {
      if ($segment === "." || $segment === "..") {
        continue;
      }
      $path .= "/" . $segment;
      @ftp_mkdir($connection, $path);
    }

    return true;
  }

  /**
   * 建立（或复用）FTP 连接
   *
   * 失败时通过 {@see AbstractStorage::break()} 写入错误态并返回 null；
   * 调用方需同时判断返回值与 `$this->error`。
   *
   * @return mixed|false FTP 连接句柄；失败返回 null
   */
  protected function connect()
  {
    if ($this->connection !== null) {
      return $this->connection;
    }

    if (!function_exists("ftp_connect")) {
      return $this->break(500, "ftpExtensionMissing", "PHP 未启用 FTP 扩展，无法使用 FTP 磁盘");
    }

    $connection = ($this->secure && function_exists("ftp_ssl_connect"))
      ? @ftp_ssl_connect($this->host, $this->port, $this->timeout)
      : @ftp_connect($this->host, $this->port, $this->timeout);

    if (!$connection) {
      return $this->break(500, "ftpConnectFailed", "FTP 连接失败", [
        "message" => "FTP 连接失败",
        "host" => $this->host,
        "port" => $this->port,
        "secure" => $this->secure,
      ]);
    }

    if ($this->user !== null && $this->user !== "" && !@ftp_login($connection, $this->user, $this->password)) {
      @ftp_close($connection);
      return $this->break(401, "ftpLoginFailed", "FTP 登录失败", ["message" => "FTP 登录失败", "user" => $this->user]);
    }

    @ftp_pasv($connection, $this->passive);
    @ftp_set_option($connection, FTP_TIMEOUT_SEC, $this->timeout);

    $this->connection = $connection;

    return $this->connection;
  }

  /**
   * 预检「被动模式数据端口」是否可达
   *
   * FTP 的数据传输走独立的数据连接（被动模式下由服务端回复 `PASV` 指定地址与端口）。
   * 云主机上最常见的故障是**只放行了控制端口、没放行被动端口范围** —— 此时 `ftp_put()` 会一直等到
   * 超时才失败（`$timeout` 设得大就是几百秒），错误只有一句 "Connection timed out"，很难定位。
   * 本方法先发一次 `PASV` 拿到端口，再用短超时的 `fsockopen` 探一下，不通就立即返回可操作的错误。
   *
   * 说明：
   * - 仅对被动模式（`$passive = true`）生效；主动模式由服务端反连本机，不适用；
   * - 探测用的 TCP 连接探完即关，`ftp_put()` 传输时会重新发 `PASV`，不影响后续；
   * - 拿不到 `PASV` 信息时不阻断（真失败时 `ftp_put()` 会给出原因）。
   *
   * @param mixed $connection FTP 连接
   * @return boolean 可达（或无法判定）返回 true；不可达时已写入错误态，调用方用 forwardBreak() 返回
   */
  protected function checkPassiveDataPort($connection)
  {
    if (!$this->passive) {
      return true;
    }

    $probeTimeout = 3;                                        //* 探测超时（秒），只用于本次 fsockopen
    $reply = @ftp_raw($connection, "PASV");
    if (!is_array($reply) || !preg_match('/\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', implode(" ", $reply), $matches)) {
      return true;
    }

    $dataHost = "{$matches[1]}.{$matches[2]}.{$matches[3]}.{$matches[4]}";
    $dataPort = ((int) $matches[5]) * 256 + (int) $matches[6];

    $errorNumber = 0;
    $errorString = "";
    $socket = @fsockopen($dataHost, $dataPort, $errorNumber, $errorString, $probeTimeout);
    if (!$socket) {
      return $this->break(500, "ftpDataPortUnreachable", "FTP 数据端口不可达", [
        "dataHost" => $dataHost,
        "dataPort" => $dataPort,
        "probeTimeout" => $probeTimeout,
        "socketError" => $errorString,
        "passive" => $this->passive,
        "hint" => "被动模式的数据端口被防火墙/安全组拦截（或服务端未固定被动端口范围）。请先在服务端固定被动端口范围（如 30000-30100），再在云安全组与主机防火墙放行该范围。",
      ]);
    }
    fclose($socket);

    return true;
  }
}
