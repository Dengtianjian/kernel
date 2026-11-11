<?php

namespace kernel\Providers\Auth;

use kernel\DiscuzX\Foundation\DiscuzXApp;
use kernel\DiscuzX\Middlewares\GlobalDiscuzXAuthMiddleware;
use kernel\Foundation\Database\RDB\Drivers\Connections;
use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Middlewares\GlobalAuthMiddleware;

/**
 * 认证服务
 *
 * 两件事合一：
 * - **凭证操作**（生成、落库、吊销、清理）——可跨应用取用，download 校验 super_app 签发的 token 靠的就是它；
 * - **本进程的登录态**（token / 过期时间 / 用户 / 是否已登录）——认证中间件写入，业务层只读。
 *
 * 登录态读写方法刻意分开：写方法只给中间件 / 登录接口用（标 `@internal`），
 * 业务只调 getter——避免某个控制器顺手改登录态，把排查变成猜谜。
 *
 * 只存 id 与必要标量；用户详情按 id 查，不把整条记录长期缓存。
 *
 * 用法：
 * ```php
 * $auth = $app->make(AuthProvider::class);
 * if ($auth->logged()) {
 *   $uid = $auth->userId();
 * }
 * ```
 *
 * 构造即装配：登记自己（按基类名）、挂认证中间件、清理过期 token。
 * 首次 `make()` 时构造并登记，之后取到的都是同一份——登录态因此不会串。
 */
class AuthProvider extends AbilityBaseObject
{
  /**
   * 本次请求解析出的 token 值
   *
   * @var string|null
   */
  protected $token = null;
  /**
   * token 的绝对过期时间戳
   *
   * @var int|null
   */
  protected $tokenExpiresAt = null;
  /**
   * 当前登录用户数据
   *
   * @var mixed
   */
  protected $user = null;
  /**
   * 当前登录用户 ID
   *
   * @var string|int|null
   */
  protected $userId = null;
  /**
   * 是否已登录
   *
   * @var bool
   */
  protected $logged = false;

  public function __construct()
  {
    //* 归属由构造上下文给出（容器构造会压栈，Bootstrap 里 new 也在装配上下文里），
    //* 所以不需要再传 App 参数
    $this->app()->middleware()->set(
      DiscuzXApp::exists() ? GlobalDiscuzXAuthMiddleware::class : GlobalAuthMiddleware::class
    );

    if (!Connections::getDrivers()) {
      return;
    }

    $this->deleteExpiredTokens();
  }
  /**
   * 本应用的表访问入口
   *
   * 绑定到本服务所属应用：download 校验 super_app 签发的 token 时，
   * 查的是 super_app 的 logins 表，而不是它自己（不存在的）那张。
   *
   * @return LoginsModel
   */
  public function logins()
  {
    //* 走容器：构造参数由容器注入本应用，归属不必手传
    return $this->app()->make(LoginsModel::class);
  }

  /**
   * 按 token 值查一条**未过期**的登录记录
   *
   * app_id 匹配本应用或为空（通用 token），与改造前中间件里的判定一致，
   * 把这段「查哪张表、按什么条件查」收回服务里，中间件只负责把结果写进上下文。
   *
   * @param string $token
   * @return array|object|null 记录不存在时返回 null
   */
  public function findValidToken($token)
  {
    return $this->logins()
      ->where("token", $token)
      ->where("app_id", $this->app()->appId())
      ->orWhere("app_id", null)
      ->first();
  }

  /**
   * 生成 token（不落库）
   *
   * 使用密码学安全的随机字节生成 token 值与 salt；salt 随 createToken 一并持久化，
   * 可用于后续校验或密钥轮换。原 password_hash 方案每次输出不同且无对应验证，已弃用。
   *
   * @param integer $expireDays 有效天数
   * @return array{value:string,salt:string,expiresAt:int,expireDays:int}
   */
  public function generateToken($expireDays = 30)
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
   * @param integer $expireDays 有效天数
   * @return array{value:string,salt:string,expiresAt:int,expireDays:int}
   */
  public function createToken($userId, $expireDays = 30)
  {
    $tokenData = $this->generateToken($expireDays);
    $now = time();
    // id 为自增主键（bigint autoIncrement），无需手动生成
    $this->logins()->insert([
      "token" => $tokenData["value"],
      "salt" => $tokenData["salt"],
      "expires_at" => $tokenData["expiresAt"],
      "expire_days" => $tokenData["expireDays"],
      "user_id" => $userId,
      "app_id" => $this->app()->appId(),
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
  public function deleteToken($token)
  {
    $this->logins()->where("token", $token)->delete();
  }

  /**
   * 清理已过期的登录凭证（物理删除，含已软删除的）
   *
   * @return void
   */
  public function deleteExpiredTokens()
  {
    $this->logins()->where("expires_at", "<", time())->forceDelete();
  }

  /**
   * 吊销某用户的所有登录凭证（软删除，常用于改密码后全平台下线）
   *
   * @param string|int $userId
   * @return void
   */
  public function deleteTokensByUser($userId)
  {
    $this->logins()->where("user_id", $userId)->delete();
  }

  // --------------------------------------------------------------------------
  //  本进程的登录态（认证中间件写，业务层读）
  // --------------------------------------------------------------------------

  /**
   * 写入 token 值
   *
   * @internal 仅认证中间件 / 登录接口调用
   * @param string|null $token
   * @return $this
   */
  public function setToken($token)
  {
    $this->token = $token;

    return $this;
  }

  /**
   * 当前 token 值（未登录为 null）
   *
   * @return string|null
   */
  public function token()
  {
    return $this->token;
  }

  /**
   * 写入 token 绝对过期时间戳
   *
   * @internal 仅认证中间件调用
   * @param int|null $expiresAt
   * @return $this
   */
  public function setTokenExpiresAt($expiresAt)
  {
    $this->tokenExpiresAt = $expiresAt;

    return $this;
  }

  /**
   * token 绝对过期时间戳（未登录为 null）
   *
   * @return int|null
   */
  public function tokenExpiresAt()
  {
    return $this->tokenExpiresAt;
  }

  /**
   * 写入当前登录用户数据
   *
   * @internal 仅认证中间件 / 登录接口调用
   * @param mixed $user
   * @return $this
   */
  public function setUser($user)
  {
    $this->user = $user;

    return $this;
  }

  /**
   * 当前登录用户数据（未登录为 null）
   *
   * @return mixed
   */
  public function user()
  {
    return $this->user;
  }

  /**
   * 写入当前登录用户 ID
   *
   * @internal 仅认证中间件 / 登录接口调用
   * @param string|int|null $userId
   * @return $this
   */
  public function setUserId($userId)
  {
    $this->userId = $userId;

    return $this;
  }

  /**
   * 当前登录用户 ID（未登录为 null）
   *
   * @return string|int|null
   */
  public function userId()
  {
    return $this->userId;
  }

  /**
   * 写入登录状态
   *
   * @internal 仅认证中间件 / 登录接口调用
   * @param bool $logged
   * @return $this
   */
  public function setLogged($logged)
  {
    $this->logged = (bool) $logged;

    return $this;
  }

  /**
   * 当前是否已登录
   *
   * @return bool
   */
  public function logged()
  {
    return $this->logged;
  }
}
