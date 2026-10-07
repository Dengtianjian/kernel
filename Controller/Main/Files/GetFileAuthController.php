<?php

namespace kernel\Controller\Main\Files;

use kernel\Facades\Storage;
use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\FileSystem\Storage\FileStorage;
use kernel\Modules\Auth\Auth;

/**
 * 获取文件操作授权
 *
 * 对应存储层注册的 `auth` 路由：`{prefix}/auth/{method:(get|post|patch|delete)}`
 * （见 {@see \kernel\Foundation\FileSystem\Storage\FileStorage::registerRoute()}），
 * URL 里的 `method` 会作为 {@see data()} 的第一个参数传入，据此派发到同名方法。
 *
 * 作用是向调用方**签发带签名与有效期的授权参数**（{@see FileStorage::createAuthParams()}）：
 * 客户端先来这里换授权，再拿授权去调用真正的读取 / 上传 / 修改 / 删除接口。
 *
 * 四种方法的差异：
 * - `get`：**无需登录**（读取权限最终由文件 ACL 在真正读取时判定）；
 * - `post`：需登录；登记文件元信息（开启数据存储时写库）并返回上传授权；
 * - `patch` / `delete`：需登录；分别返回修改、删除授权。
 *
 * 请求体字段与类型转换规则见 {@see $requestBodySerializes}。
 *
 * @package kernel\Controller\Main\Files
 */
class GetFileAuthController extends AuthController
{
  /**
   * 请求体字段的类型转换规则（字段名 => 类型）
   *
   * 由框架在 {@see \kernel\Foundation\Controller\Controller::__construct()} 中传给
   * `ControllerBody`，决定 `$this->body()` 取值的类型，因此接口要用到的字段必须在这里声明。
   *
   * - `disk`：目标磁盘名（空 = 当前磁盘）；`expires`：授权有效期（秒）；
   * - `fileKey`：文件键（`get`/`patch`/`delete` 必填，`post` 可选）；
   * - 其余是 `post` 的上传元信息：`name` / `path` / `size` / `width` / `height` /
   *   `accessControl` / `ref` / `type`。
   *
   * @var array<string,string>
   */
  protected $requestBodySerializes = [
    "disk" => "string",

    "expires" => "int",
    "fileKey" => "string",
    "headers",
    "urlParams",

    //* 上传参数
    "name" => "string",
    "path" => "string",
    "size" => "int",
    "width" => "int",
    "height" => "int",
    "accessControl" => "string",
    "ref" => "string",
    "type" => "string"
  ];

  /**
   * 本次操作的文件键
   *
   * 由 {@see data()} 从请求体 `fileKey` 写入。`get` / `patch` / `delete` 要求非空；
   * `post` 允许为空（此时会按 `path` + 生成的文件名拼出文件键）。
   *
   * @var string|null
   */
  protected $fileKey = null;

  /**
   * 接口入口：按 URL 中的方法名派发，返回对应的授权参数
   *
   * 流程：校验方法名 → 从请求体取 `fileKey`（除 `post` 外必须非空）→ 调用同名方法
   * （{@see get()} / {@see post()} / {@see patch()} / {@see delete()}）；若该调用返回
   * {@see Response}（例如 `post` 的 success、或各方法的 fail）则直接作为响应返回，
   * 否则把授权参数包成 `{key, method, auth}` 返回。
   *
   * @param string $method 操作方法，取值 `post` / `get` / `patch` / `delete`
   * @return array|Response 授权参数结构；出错或 `post` 成功时为具体响应对象
   */
  public function data(string $method)
  {
    if (!in_array($method, ["post", "get", "patch", "delete"])) return $this->fail(400, 400, "非法的方法参数");

    $this->fileKey = $fileKey = $this->body("fileKey");

    if ($method !== "post" && !$fileKey) return $this->fail(400, 400, "文件名不可为空");

    $auth = $this->$method($fileKey);
    if ($auth instanceof Response) return $auth;

    return [
      "key" => $this->fileKey,
      "method" => $method,
      "auth" => $auth
    ];
  }
  /**
   * 签发「修改」授权（需登录）
   *
   * 必须已登录（{@see Auth::logged()}），否则返回 403。
   *
   * @param string $fileKey 文件键
   * @return array|Response 授权参数；未登录时返回 break 错误态
   */
  protected function patch(string $fileKey)
  {
    if (!Auth::logged()) return $this->fail(403, 403, "抱歉，您无权获取修改文件授权");

    return Storage::createAuthParams($fileKey, $this->body("expires") ?: 1800, $this->body("urlParams", []),  $this->body("headers", []), "patch");
  }
  /**
   * 签发「读取」授权（无需登录）
   *
   * 读取权限不在这里判定 —— 等客户端拿授权去真正读取时，再由文件自身的 ACL 校验。
   *
   * @param string $fileKey 文件键
   * @return array 授权参数
   */
  protected function get(string $fileKey)
  {
    return Storage::createAuthParams($fileKey, $this->body("expires") ?: 1800, $this->body("urlParams", []),  $this->body("headers", []), "get");
  }
  /**
   * 签发「上传」授权，并登记文件元信息（需登录）
   *
   * 必须已登录（{@see Auth::logged()}），否则返回 403。流程：
   * 1. 取请求体里的上传元信息（`name` / `path` / `size` / `width` / `height` /
   *    `accessControl` / `ref` / `type`）；
   * 2. 确定文件键：传入 `$fileKey` 时以它为准，并用它的 basename 作为对象文件名；
   *    否则用 {@see FileStorage::generateFileKey()} 生成随机文件名，再用
   *    {@see FileStorage::buildFileKey()} 与 `path` 拼出文件键；
   * 3. 开启数据存储（{@see FileStorage::dataSave()}）时先 `add()` 落库 —— **先登记、后上传**；
   * 4. 计算授权有效期：默认 600 秒；文件超过 10MB 时，每多 1MB 再加 60 秒；
   * 5. 在本地存储目录下创建 `path` 对应的目录；
   * 6. 返回上传授权参数（`key` / `method` / `accessControl` / `auth`）。
   *
   * 实现现状（勿与注释混淆）：
   * - 第 5 步无论目标磁盘是本地还是远程都会执行 `mkdir` / `chmod`，远程磁盘并不需要这个本地目录；
   * - `accessControl` 未传 / 传空（假值）时默认取 {@see FileStorage::PUBLIC_READ}。
   *
   * @param string|null $fileKey 客户端指定的文件键；为空时按 `path` 自动生成
   * @return Response|array 成功返回 success 响应（含授权参数）；未登录时返回 break 错误态
   */
  protected function post($fileKey = null)
  {
    if (!Auth::logged()) return $this->fail(403, 403, "抱歉，您无权获取上传文件授权");

    $body = $this->requestBody->some(
      [
        "name",
        "path",
        "size",
        "width",
        "height",
        "accessControl",
        "ref",
        "type"
      ]
    );
    $pathInfo = pathinfo($body['name']);
    $objectFileName = Storage::generateFileKey($pathInfo['extension']);

    if ($fileKey) {
      $this->fileKey = $fileKey;
      $pathInfo = pathinfo($fileKey);
      $objectFileName = $pathInfo['basename'];
    } else {
      $this->fileKey = $fileKey = Storage::buildFileKey($body['path'], $objectFileName);
    }

    $accessControl = ($this->body("accessControl") !== null) && $this->body("accessControl") ? $this->body("accessControl") : FileStorage::PUBLIC_READ;

    $disk = Storage::disk($this->body("disk") ?: null);

    if (Storage::dataSave()) {
      Storage::add($fileKey, $body['name'], $objectFileName, $body['path'], $body['size'], $pathInfo['extension'], null, Auth::userId(), $accessControl, $disk->name(), $body['ref'] ?: null, $body['type'] ?: null, $body['width'], $body['height']);
    }

    $expires = $this->body("expires") ?: 600; //* 默认是 10 分钟有效期，如果文件大小超过 10M，就会通过尺寸大小来重新计算有效期，会基于 10 分钟的基础上去增加时间
    if ($body['size'] && $body['size'] > 1024 * 1024 * 10) {
      $size = $body['size'] - (1024 * 1024 * 10);
      $expires += ceil(($size / 1024 / 1024) * 60);
    }

    $savePath = Path::join(Path::storage(), $body['path']);
    mkdir($savePath, 0755, true);
    chmod($savePath, 0755);

    $auth = Storage::createAuthParams($fileKey, $expires, $this->body("urlParams", []),  $this->body("headers", []), "post");

    return $this->success([
      "key" => $this->fileKey,
      "method" => "post",
      "accessControl" => $accessControl,
      "auth" => $auth
    ]);
  }
  /**
   * 签发「删除」授权（需登录）
   *
   * 必须已登录（{@see Auth::logged()}），否则返回 403。
   *
   * @param string $fileKey 文件键
   * @return array|Response 授权参数；未登录时返回 break 错误态
   */
  protected function delete(string $fileKey)
  {
    if (!Auth::logged()) return $this->fail(403, 403, "抱歉，您无权获取删除文件授权");

    return Storage::createAuthParams($fileKey, $this->body("expires") ?: 1800, $this->body("urlParams", []),  $this->body("headers", []), "delete");
  }
}
