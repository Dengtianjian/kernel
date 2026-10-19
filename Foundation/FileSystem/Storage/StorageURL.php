<?php

namespace kernel\Foundation\FileSystem\Storage;

use kernel\Foundation\HTTP\URL;

/**
 * 存储文件访问地址（延迟构建的 URL）
 *
 * {@see FileStorage::url()} 的返回值：把 `url($fileKey, $urlParams, $expires, $withSignature, $action)`
 * 的每个参数都变成一个链式方法，参数改完后再取字符串 —— 只有调用 {@see toString()}
 * （或把对象当字符串用，触发 `__toString()`）时才真正拼装路径、合并参数并**签发签名**。
 *
 * 这样解决了「先拿到地址、之后才决定参数」的问题：签名在**最后一次**构建时才计算，
 * 因此构建前通过 {@see urlParams()} / {@see action()} 增改的参数都会被纳入签名，
 * 不会出现「先签名再改参数 → 验签失败（verifyAuth:403010）」的情况。
 *
 * 用法：
 * ```php
 * // 等价于 Storage::url("a/b.png", ["v" => 2], 600, true, "preview")
 * $url = Storage::url("a/b.png")
 *   ->urlParams(["v" => 2])
 *   ->expires(600)
 *   ->withSignature(true)
 *   ->action("preview");
 *
 * echo $url;              // 触发构建并输出地址字符串
 * $str = $url->toString(); // 同上
 * $url->build();           // 只构建不取字符串（构建后可读 $url->pathName / $url->queryParams）
 * ```
 *
 * 结构：
 * - 路径：`{prefix}/{fileKey}` 在 {@see fileKey()} 时即写入 `$pathName`（无需构建即可读写，
 *   可自行追加路径段，如 `$url->pathName .= "/preview"`；构建时不会覆盖非空 pathName）；
 * - 主机：`protocol` / `host` / `port` / `user` / `password` 取自存储门面的基础地址（{@see FileStorage::baseURL()}）；
 * - 查询参数：`$urlParams`（{@see urlParams()}）+ 经 {@see URL::queryParam()} 追加的参数，开启签名时再并入签名参数；
 * - 签名：仅当存储门面已开启签名鉴权（{@see FileStorage::auth()}）且 {@see withSignature()} 为真时才签发，
 *   HTTP 方法固定按 "get" 参与签名。
 *
 * 注意：
 * - 构建是幂等的，可重复调用；每次构建都会按当前参数重新签发（时间戳会更新）；
 * - 构建前从 `$queryParams` 里手动塞的参数同样会被纳入，但**签名参数键**
 *   （sign-algorithm / sign-time / key-time / header-list / signature / url-param-list，
 *   以及绑定动作时的 action）会被剔除后重算，避免重复构建把它们当成业务参数；
 * - 不要在 `$urlParams` 里传 `auth` 键：它会参与签名计算却又会被剔除，导致验签失败。
 *
 * @see FileStorage::url() 创建入口
 * @see URL 父类（host / pathName / queryParams 等公开属性与 queryParam()）
 */
class StorageURL extends URL
{
  /**
   * 提供基础地址、路径前缀与签名能力的存储门面
   *
   * @var FileStorage|null
   */
  protected $storage = null;
  /**
   * 文件键（可含相对目录，如 "dir/a.jpg"）
   *
   * @var string|null
   */
  protected $fileKey = null;
  /**
   * 参与签名的附加 URL 参数，对应 `url()` 的第二参 `$urlParams`
   *
   * @var array
   */
  protected $storageParams = [];
  /**
   * 签名有效期（秒）
   *
   * @var integer
   */
  protected $expires = 1800;
  /**
   * 是否附带签名授权参数
   *
   * @var boolean
   */
  protected $withSignature = false;
  /**
   * 签名绑定的动作（如 preview / download / info），null 表示不绑定
   *
   * @var string|null
   */
  protected $action = null;

  /**
   * 构造存储访问地址
   *
   * 仅记录存储门面与文件键并推导路径 `{prefix}/{fileKey}`，**不解析基础地址、不签发签名**，
   * 这些留到 {@see build()} / {@see toString()} 时再执行。
   *
   * @param FileStorage $storage 存储门面（提供 baseURL / prefix / authEnabled / createAuthParams）
   * @param string|null $fileKey 文件键
   */
  public function __construct(FileStorage $storage, $fileKey = null)
  {
    parent::__construct(null);

    $this->storage = $storage;
    if (!is_null($fileKey)) {
      $this->fileKey($fileKey);
    }
  }

  /**
   * 读取或设置文件键
   *
   * 设置时会同步把 `$pathName` 重置为 `{prefix}/{fileKey}`；
   * 若此前手动改过 `$pathName`，设置文件键会覆盖它（如需保留请重设）。
   *
   * @param string|null $fileKey 文件键；不传表示仅读取
   * @return static|string|null 设置时返回 $this，读取时返回当前文件键
   */
  public function fileKey($fileKey = null)
  {
    if (func_num_args() === 0) {
      return $this->fileKey;
    }

    $this->fileKey = $fileKey;
    $this->pathName = $this->storage->prefix() . "/" . $fileKey;

    return $this;
  }

  /**
   * 读取或设置参与签名的附加 URL 参数（对应 `url()` 的第二参）
   *
   * 这些参数既会拼进最终 query，也会参与签名（记入 `url-param-list`），
   * 因此访问方必须原样携带。**不要**传入 `auth` 键（构建时会被剔除却已参与签名）。
   *
   * @param array|null $urlParams 参数键值对；不传表示仅读取
   * @return static|array 设置时返回 $this，读取时返回当前参数
   */
  public function urlParams($urlParams = null)
  {
    if (func_num_args() === 0) {
      return $this->storageParams;
    }

    $this->storageParams = $urlParams ?: [];

    return $this;
  }

  /**
   * 读取或设置签名有效期（秒）
   *
   * 仅在「已开启签名鉴权 + {@see withSignature()} 为真」时生效。
   *
   * @param int|null $expires 有效期秒数；不传表示仅读取
   * @return static|int 设置时返回 $this，读取时返回当前有效期
   */
  public function expires($expires = null)
  {
    if (func_num_args() === 0) {
      return $this->expires;
    }

    $this->expires = intval($expires);

    return $this;
  }

  /**
   * 读取或设置是否附带签名授权参数
   *
   * 需存储门面已开启签名鉴权（{@see FileStorage::auth()}），否则即使为真也不会签名。
   *
   * @param boolean|null $withSignature 是否签名；不传表示仅读取
   * @return static|boolean 设置时返回 $this，读取时返回当前开关
   */
  public function withSignature($withSignature = null)
  {
    if (func_num_args() === 0) {
      return $this->withSignature;
    }

    $this->withSignature = (bool)$withSignature;

    return $this;
  }

  /**
   * 读取或设置签名绑定的动作
   *
   * 传值会把动作写入签名，使该地址**只能用于该动作**的端点
   * （`info` / `preview` / `download` / `delete` / `update` / `upload` 等按动作校验的端点必须绑定）；
   * 不传表示不绑定，此时只能用于不限制动作的端点。
   *
   * @param string|null $action 动作标识；不传表示仅读取（显式传 null 表示清除绑定）
   * @return static|string|null 设置时返回 $this，读取时返回当前动作
   */
  public function action($action = null)
  {
    if (func_num_args() === 0) {
      return $this->action;
    }

    $this->action = $action;

    return $this;
  }

  /**
   * 构建地址（解析基础地址、合并参数、按需签发签名）
   *
   * 可重复调用：每次都会按当前 `$urlParams` / `$expires` / `$withSignature` / `$action`
   * 重新计算，签名时间戳随之刷新。构建结果写回父类的公开属性
   * （`protocol` / `host` / `port` / `pathName` / `queryParams`），不返回字符串。
   *
   * @return $this
   */
  public function build()
  {
    $baseURL = new URL($this->storage->baseURL());
    $this->protocol = $baseURL->protocol;
    $this->host = $baseURL->host;
    $this->port = $baseURL->port;
    $this->user = $baseURL->user;
    $this->password = $baseURL->password;

    //* pathName 已在 fileKey() 时推导，这里仅在为空时兜底，避免覆盖调用方手动追加的路径段
    if (!$this->pathName) {
      $this->pathName = $this->storage->prefix() . "/" . $this->fileKey;
    }

    //* 剔除上一次构建写入的签名参数键，只保留业务参数，避免重复构建时把它们当成业务参数参与签名
    $signParamKeys = ["sign-algorithm", "sign-time", "key-time", "header-list", "signature", "url-param-list"];
    if (!is_null($this->action)) {
      $signParamKeys[] = "action";
    }
    $queryParams = array_diff_key($this->queryParams, array_flip($signParamKeys));
    $queryParams = array_merge($queryParams, $this->storageParams);

    if ($this->storage->authEnabled() && $this->withSignature) {
      //* 第 5 参固定 "get"（链接是 GET 访问），第 6 参把 $action 透传进去并参与签名
      $queryParams = array_merge(
        $queryParams,
        $this->storage->createAuthParams($this->fileKey, $this->expires, $queryParams, [], "get", $this->action)
      );
      //* `auth` 键已被计入签名，保留会造成「签名含 auth 但地址里没有」→ 验签必然失败
      unset($queryParams['auth']);
    }

    $this->queryParams = $queryParams;

    return $this;
  }

  /**
   * 输出地址字符串
   *
   * 先 {@see build()} 再交给父类拼装，因此每次调用都会按当前参数重新签发签名。
   *
   * @return string
   */
  public function toString()
  {
    $this->build();

    return parent::toString();
  }

  /**
   * 把对象当字符串使用时自动输出地址
   *
   * @return string
   */
  public function __toString()
  {
    return $this->toString();
  }
}
