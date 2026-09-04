<?php

namespace kernel\Modules\Auth;

use kernel\Foundation\App;
use kernel\Foundation\Module\Module;

/**
 * 认证模块
 *
 * 负责登录凭证（token）的生成、落库与清理，并通过全局中间件 GlobalAuthMiddleware
 * 在每个请求里校验 Authorization 头中的 token，解析出当前登录用户状态。
 * 解析出的状态（token / user / logged 等）由中间件写入本模块实例，业务层可经门面 Auth 读取。
 *
 * @property-read string $name 模块标识
 * @property LoginsModel $loginsModel 登录凭证模型（延迟初始化）
 * @property string|null $token 当前请求的 token 值（中间件写入）
 * @property int|null $tokenExpiresAt token 的绝对过期时间戳（中间件写入）
 * @property mixed $user 当前登录用户数据（中间件写入，未登录为 null）
 * @property string|int|null $userId 当前登录用户 ID（中间件写入，未登录为 null）
 * @property bool $logged 是否已登录（中间件写入）
 */
class AuthModule extends Module
{
  /** @var string 模块标识，用于注册与查找 */
  protected string $name = "auth";

  /** @var LoginsModel|null 登录凭证模型，首次访问时延迟初始化 */
  protected $loginsModel = null;

  /** @var string|null 当前请求的 token 值（由中间件写入） */
  protected $token = null;
  /** @var int|null token 的绝对过期时间戳（由中间件写入） */
  protected $tokenExpiresAt = null;
  /** @var mixed 当前登录用户数据（由中间件写入，未登录为 null） */
  protected $user = null;
  /** @var string|int|null 当前登录用户 ID（由中间件写入，未登录为 null） */
  protected $userId = null;
  /** @var bool 是否已登录（由中间件写入） */
  protected $logged = false;

  /**
   * 模块启动钩子
   *
   * 注册全局认证中间件，并在启动期清理已过期凭证，避免 logins 表无限增长。
   *
   * @return void
   */
  function onBoot(): void
  {
    getApp()->middleware()->set(GlobalAuthMiddleware::class);
    $this->loginsModel = new LoginsModel();
    // 启动期清理过期凭证，避免 logins 表无限增长
    $this->deleteExpiredTokens();
  }
  /**
   * 生成 token（不落库）
   *
   * 使用密码学安全的随机字节生成 token 值与 salt；salt 随 createToken 一并持久化，
   * 可用于后续校验或密钥轮换。原 password_hash 方案每次输出不同且无对应验证，已弃用。
   *
   * @param integer $expireDays
   * @return array{value:string,salt:string,expiresAt:int,expireDays:int}
   */
  function generateToken($expireDays = 30)
  {
    $salt = bin2hex(random_bytes(16));
    $value = bin2hex(random_bytes(32));
    $expiresAt = time() + (86400 * $expireDays);
    return [
      "value" => $value,
      "salt" => $salt,
      "expiresAt" => $expiresAt,
      "expireDays" => $expireDays
    ];
  }
  /**
   * 生成并保存 token 到 logins 表
   *
   * 内部调用 generateToken 生成凭证，并写入当前模型（snake_case）。
   * id 为自增主键；salt 与 expire_days 一并持久化。
   *
   * @param string|int $userId 关联用户 ID（写入 user_id 字段）
   * @param integer $expireDays
   * @return array{value:string,salt:string,expiresAt:int,expireDays:int}
   */
  function createToken($userId, $expireDays = 30)
  {
    $tokenData = $this->generateToken($expireDays);
    $now = time();
    // id 为自增主键（bigint autoIncrement），无需手动生成
    $this->model()->insert([
      "token" => $tokenData["value"],
      "salt" => $tokenData["salt"],
      "expires_at" => $tokenData["expiresAt"],
      "expire_days" => $tokenData["expireDays"],
      "user_id" => $userId,
      "app_id" => App::id(),
      "created_at" => $now,
      "updated_at" => $now,
    ]);
    return $tokenData;
  }
  /**
   * 按 token 值删除登录凭证（软删除）
   *
   * @param string $token
   * @return void
   */
  function deleteToken($token)
  {
    $this->model()->where("token", $token)->delete();
  }
  /**
   * 清理已过期的登录凭证（物理删除，含已软删除的）
   *
   * @return void
   */
  function deleteExpiredTokens()
  {
    $this->model()->where("expires_at", time(), "<")->forceDelete();
  }
  /**
   * 吊销某用户的所有登录凭证（软删除，常用于改密码后全平台下线）
   *
   * @param string|int $userId
   * @return void
   */
  function deleteTokensByUser($userId)
  {
    $this->model()->where("user_id", $userId)->delete();
  }
  /**
   * 获取或替换登录凭证模型（读写一体）
   *
   * 无参调用返回当前模型（延迟初始化：CLI 等未走 onBoot 的场景首次访问时自动 new LoginsModel）；
   * 传入实例则替换并返回自身，便于测试注入。
   *
   * @param \kernel\Modules\Auth\LoginsModel|null $val
   * @return \kernel\Modules\Auth\LoginsModel|static
   */
  function model($val = null)
  {
    if (!is_null($val)) {
      $this->loginsModel = $val;
      return $this;
    }
    // 延迟初始化：CLI（schedule:run）等未走 onBoot 的场景也能直接使用
    if ($this->loginsModel === null) {
      $this->loginsModel = new LoginsModel();
    }
    return $this->loginsModel;
  }
  /**
   * 读取或设置当前 token（读写一体）
   *
   * 有参调用写入并返回自身；无参调用返回当前值。
   *
   * @param string|null $val
   * @return string|null|static
   */
  function token($val = null)
  {
    if (func_num_args()) {
      $this->token = $val;
      return $this;
    }

    return $this->token;
  }
  /**
   * 读取或设置 token 绝对过期时间戳（读写一体）
   *
   * 有参调用写入并返回自身；无参调用返回当前值。
   *
   * @param int|null $val
   * @return int|null|static
   */
  function tokenExpiresAt($val = null)
  {
    if (func_num_args()) {
      $this->tokenExpiresAt = $val;
      return $this;
    }

    return $this->tokenExpiresAt;
  }
  /**
   * 读取或设置登录状态（读写一体）
   *
   * 有参调用写入并返回自身；无参调用返回当前值。
   *
   * @param bool $val
   * @return bool|static
   */
  function logged($val = null)
  {
    if (func_num_args()) {
      $this->logged = $val;
      return $this;
    }

    return $this->logged;
  }
  /**
   * 读取或设置当前登录用户数据（读写一体）
   *
   * 有参调用写入并返回自身；无参调用返回当前值（未登录为 null）。
   *
   * @param mixed $val
   * @return mixed|static
   */
  function user($val = null)
  {
    if (func_num_args()) {
      $this->user = $val;
      return $this;
    }

    return $this->user;
  }
  /**
   * 读取或设置当前登录用户 ID（读写一体）
   *
   * 有参调用写入并返回自身；无参调用返回当前值（未登录为 null）。
   *
   * @param string|int|null $val
   * @return string|int|null|static
   */
  function userId($val = null)
  {
    if (func_num_args()) {
      $this->userId = $val;
      return $this;
    }

    return $this->userId;
  }
}
