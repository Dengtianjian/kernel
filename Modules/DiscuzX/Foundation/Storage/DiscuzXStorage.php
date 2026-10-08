<?php

namespace kernel\Modules\DiscuzX\Foundation\Storage;

use kernel\Foundation\FileSystem\Storage\FileStorage;
use kernel\Foundation\FileSystem\Storage\StorageSignature;
use kernel\Modules\DiscuzX\DiscuzXURL;
use kernel\Modules\DiscuzX\Model\DiscuzXFilesModel;

class DiscuzXStorage extends FileStorage
{

  public function __construct($disks = null)
  {
    parent::__construct($disks);
    $this->baseURL = DiscuzXURL::baseURL();
  }

  public function enableDataSave($model = null)
  {
    return parent::enableDataSave($model ?: $this->model ?: new DiscuzXFilesModel());
  }

  /**
   * 生成文件访问 URL（DiscuzX 形态：走 `plugin.php?id=...&uri=...` 路由）
   *
   * 与父类 {@see FileStorage::url()} 同义，只是 URL 形态不同（多了 `id` / `uri` 两个查询参数）。
   *
   * ⚠️ 同样支持**动作绑定**：给「按动作校验」的端点（`info` / `preview` / `download` / `delete` /
   * `update` / `upload`）生成链接时**必须传 `$action`**，否则签名里不含 `action`，
   * 校验会以「缺少参数」拒绝（`verifyAuth:403001`）。
   *
   * @param string $fileKey 文件键
   * @param array $urlParams 附加的 URL query 参数
   * @param int $expires 签名有效期（秒），默认 1800
   * @param boolean $withSignature 是否附带签名授权参数，默认 false
   * @param string|null $action 签名绑定的动作；不传则不绑定（只能用于不限制动作的端点）
   * @return string 完整文件访问 URL
   */
  public function url($fileKey, $urlParams = [], $expires = 1800, $withSignature = false, $action = null)
  {
    $accessURL = new DiscuzXURL($this->baseURL);
    $accessURL->pathName = "plugin.php";
    $accessURL->queryParam("gstudio_super_app", "id");
    $accessURL->queryParam($this->prefix . "/" . $fileKey, "uri");

    if ($this->authEnabled() && $withSignature) {
      //* 第 5 参固定 "get"，第 6 参透传 $action（参与签名，并因此出现在 URL 上）
      $urlParams = array_merge($urlParams, $this->createAuthParams($fileKey, $expires, $urlParams, [], "get", $action));
      if (array_key_exists("auth", $urlParams)) {
        unset($urlParams['auth']);
      }
    }

    $accessURL->queryParam($urlParams);

    return $accessURL->toString();
  }

  public function verifySignature($fileKey, $rawURLParams, $rawHeaders = [], $httpMethod = "get", $action = null)
  {
    $signParamKeys = ["sign-algorithm", "sign-time", "key-time", "header-list", "signature", "url-param-list"];
    if (!is_null($action)) $signParamKeys[] = "action";

    foreach ($signParamKeys as $key) {
      if (!array_key_exists($key, $rawURLParams)) {
        return $this->break(403, "verifyAuth:DZX:403001", "缺少参数");
      }
    }

    unset($rawURLParams['id']);
    unset($rawURLParams['uri']);

    $signAlgorithm = $rawURLParams['sign-algorithm'];
    $signTime = urldecode($rawURLParams['sign-time']);
    $keyTime = urldecode($rawURLParams['key-time']);
    $headerList = $rawURLParams['header-list'] ? explode(";", urldecode($rawURLParams['header-list'])) : [];
    $urlParamList = $rawURLParams['url-param-list'] ? explode(";", rawurldecode(urldecode($rawURLParams['url-param-list']))) : [];
    if ($urlParamList) {
      $urlParamList = array_map(function ($item) {
        return rawurldecode($item);
      }, $urlParamList);
    }
    $signature = $rawURLParams['signature'];


    //* 动作校验：请求携带的 action（可选）必须落在允许集合内（字符串=精确匹配，数组=允许集合）；
    //* 它同时会在下面参与验签 ⇒ 把"预览签名"拿来请求"下载"时签名本身就对不上（403012 或 403011）
    $requestAction = null;
    if (!is_null($action)) {
      $requestAction = array_key_exists("action", $rawURLParams) ? $rawURLParams['action'] : null;
      if (!StorageSignature::matchAction($action, $requestAction)) {
        return $this->break(403, "verifyAuth:DZX:403012", "签名与当前操作不符");
      }
    }

    if ($signAlgorithm !== StorageSignature::getSignAlgorithm()) return $this->break(403, "verifyAuth:DZX:403002", "参数错误");
    if (strpos($signTime, ";") === false || strpos($keyTime, ";") === false) return $this->break(403, "verifyAuth:DZX:403003", "参数错误");
    if ($signTime !== $keyTime) return $this->break(403, "verifyAuth:DZX:403004", "参数错误");
    list($startTime, $endTime) = explode(";", $signTime);
    list($keyStartTime, $keyEndTime) = explode(";", $keyTime);
    $startTime = intval($startTime);
    $endTime = intval($endTime);
    $keyStartTime = intval($keyStartTime);
    $keyEndTime = intval($keyEndTime);
    if ($endTime < $startTime) return $this->break(403, "verifyAuth:DZX:403005", "验证信息已过期");
    if ($endTime < time()) return $this->break(403, "verifyAuth:DZX:403006", "验证信息已过期");
    if ($keyEndTime < $keyStartTime) return $this->break(403, "verifyAuth:DZX:403007", "验证信息已过期");
    if ($keyEndTime < time()) return $this->break(403, "verifyAuth:DZX:403008", "验证信息已过期");

    $headers = [];
    if ($headerList) {
      foreach ($rawHeaders as $key => $value) {
        $key = rawurldecode(urldecode($key));
        $value = rawurldecode(urldecode($value));
        if (!array_key_exists($key, $headerList)) {
          return $this->break(403, "verifyAuth:DZX:403009", "头部参数缺失");
        }
        $headers[$key] = $value;
      }
    }

    $urlParams = [];
    foreach ($rawURLParams as $key => $value) {
      $key = rawurldecode(urldecode($key));
      $value = rawurldecode(urldecode($value));

      if (!$value) {
        $key = strtolower($key);
      }

      if (!in_array($key, $urlParamList)) {
        if (!in_array($key, $signParamKeys)) {
          return $this->break(403, "verifyAuth:DZX:403010", "URL 参数缺失");
        }
      }
      if (!in_array($key, $signParamKeys)) {
        $urlParams[$key] = $value;
      }
    }

    if ($this->signature->verifyAuthorization($signature, $fileKey, $startTime, $endTime, $urlParams, $headers, $httpMethod, $requestAction)) {
      return true;
    } else {
      return $this->break(403, "verifyAuth:DZX:403011", "抱歉，您没有操作该文件的权限");
    }
  }
}
