<?php

namespace kernel\Foundation;

use kernel\Foundation\Database\PDO\DB;
use kernel\Service\RequestService;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

/** Install Upgrade Uninstall */
class Iuu
{
  public string $Path = "";
  protected string $version = "";
  protected string $oldVersion = "";
  public function __construct()
  {
    $this->Path = F_APP_ROOT . "/Iuu";
    if (file_exists($this->Path . "/.version")) {
      $this->oldVersion = $this->version = file_get_contents($this->Path . "/.version");
    }
  }
  /**
   * 运行SQL文件
   *
   * @param string $filePath SQL文件完整路径
   * @return boolean 运行结果
   */
  public function runSqlFile(string $filePath): bool
  {
    if (!file_exists($filePath)) return false;
    return DB::query(file_get_contents($filePath));
  }
  /**
   * 验证密钥
   *
   * @param string|null $key 密钥
   * @param boolean $directResponse 遇错直接HTTP响应，不用返回结果
   * @return boolean 验证捅过？
   */
  public static function verifyKey(?string $key, bool $directResponse = false): bool
  {
    $keyFilePath = File::genPath(F_APP_ROOT, "Iuu", ".key");
    if (!file_exists($keyFilePath)) {
      Log::record("校验Iuu的Key：.key文件不存在");
      if ($directResponse) {
        Response::error(500, "KeyFileNotExist:500001", "系统错误", [], [
          "content" => "IUU下的.key文件不存在",
          "keyPath" => $keyFilePath
        ]);
      }
      return false;
    }
    $keyContent = file_get_contents($keyFilePath);
    if ($key !== $keyContent) {
      Log::record("校验Iuu的Key：输入的key错误");
      if ($directResponse) {
        Response::error(400, "AlreadyInitialized:400001", "安装密钥错误");
      }
      return false;
    }
    return true;
  }
  /**
   * 安装
   *
   * @param string|null $key 密钥
   * @return bool 安装成功？
   */
  public function install(?string $key): bool
  {
    Iuu::verifyKey($key, true);
    set_time_limit(0); //* 不超时断开Http链接
    $versionFilePath = File::genPath($this->Path, ".version");
    if (file_exists($versionFilePath)) {
      Response::error(400, "AlreadyInitialized:400002", "已经初始化过了");
    }
    $initedMarkFile = File::genPath($this->Path, ".init");
    if (file_exists($initedMarkFile)) {
      Response::error(400, "Initing:400000", "已经在初始化中了");
    }
    file_put_contents($initedMarkFile, time());
    Response::intercept(function () use ($initedMarkFile) {
      @unlink($initedMarkFile);
    });
    Log::record("系统安装：" . Config::get("version"));
    file_put_contents($versionFilePath, Config::get("version"));

    return true;
  }
  /**
   * 是否已经安装了
   *
   * @return boolean 已经初始化过了？
   */
  public function isInstalled(): bool
  {
    $versionFile = File::genPath($this->Path, ".version");
    return file_exists($versionFile);
  }
  /**
   * 是否建议更新的逻辑运算
   *
   * @param string $version 比较的版本号
   * @return boolean 目标版本建议更新？
   */
  private function _compareVersion(string $version): bool
  {
    //* -1小于 0等于 1大于
    //* 需要大于 旧版本 并且 小于等于 新版本
    $oldVersionCompare = intval(version_compare($version, $this->oldVersion, ">"));
    $newVersionCompare = intval(version_compare($version, $this->version, "<="));
    return intval($oldVersionCompare === 1 && $newVersionCompare === 1);
  }
  /**
   * 版本比较
   *
   * @param array $versions 版本号数组。允许传一个关联数组，键是版本号，值是回调函数，当比较到该版本号时会调用回调函数。
   * @example array $version [ "0.1.1"=>function(string $version 遍历到的版本号,bool $upgrade 建议更新？) {  }]
   * @param callable $callback 回调函数。遍历版本号数组时都会调用一次
   * @return void
   */
  public function compareVersion(array $versions, callable $callback): void
  {
    foreach ($versions as $versionKey => $version) {
      if (is_callable($version)) {
        $upgrade = $this->_compareVersion($versionKey);

        $version($version, $upgrade);
        $callback($version, $upgrade);
      } else {
        call_user_func($callback,  $version, $this->_compareVersion($version), $versionKey);
      }
    }
  }
  /**
   * 更新
   *
   * @param string|null $key 密钥
   * @return boolean 更新成功？
   */
  public function upgrade(?string $key): bool
  {
    set_time_limit(0); //* 不超时断开Http链接
    Iuu::verifyKey($key, true);
    $versionFilePath = File::genPath($this->Path, ".version");
    file_put_contents($versionFilePath, Config::get("version"));
    Log::record("系统升级到：" . Config::get("version"));

    $this->version = Config::get("version");
    return true;
  }
  /**
   * 卸载
   *
   * @param string|null $key 密钥
   * @return boolean 更新成功？
   */
  public function uninstall(?string $key): bool
  {
    Iuu::verifyKey($key, true);

    $versionFile = File::genPath($this->Path, ".version");
    $initedMarkFile = File::genPath($this->Path, ".init");

    unlink($versionFile);
    unlink($initedMarkFile);

    return true;
  }
}
