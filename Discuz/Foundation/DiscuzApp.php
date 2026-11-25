<?php

namespace kernel\Discuz\Foundation;

use kernel\Foundation\App;
use kernel\Foundation\Application\Applications;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\Date;
use kernel\Foundation\FileSystem\Path;

class DiscuzApp extends App
{
  /**
   * @var DiscuzDeployment
   */
  private $deployment = null;
  /**
   * 构造：Discuz 基座 + 装配
   *
   * 装配刻意**不交给父类构造**（`parent::__construct()` 第三参传 null）：本类要先把
   * 请求 URI 准备好，Bootstrap 里读 `$app->request()->uri()` 才拿得到真实值。
   * 因此这里在父类构造之后、URI 写入之后，再显式调 {@see App::assemble()}。
   *
   * @param string|callable|null $setup 装配类名或装配闭包；null 表示只建骨架、不装配
   * @param string $appId 应用 ID（等于插件目录名）
   * @param string $kernelId 内核 ID，默认 gstudio_kernel；传 null 时用默认值
   */
  public function __construct($setup = null, $appId = null, $kernelId = "gstudio_kernel")
  {
    if (!defined("CHARSET")) {
      define("CHARSET", "utf-8");
    }

    parent::__construct(null, $appId, $kernelId ?: "gstudio_kernel");

    //* 请求是访问即构建：这里直接写入 URI，不必提前兜底实例化
    if (isset($_GET['uri'])) {
      $this->request()->uri(daddslashes(trim($_GET['uri'])));
    } else {
      $this->request()->uri("/");
    }

    //* 异常处理
    \set_exception_handler("kernel\Foundation\Exception\ExceptionHandler::receive");
    //* 错误处理
    \set_error_handler("kernel\Foundation\Exception\ExceptionHandler::handle", E_ALL);

    $this->assemble($setup);
  }
  public function hook($uri)
  {
    $this->request()->uri($uri);
  }
  /**
   * 部署编排器（安装 / 更新 / 卸载）
   *
   * @return DiscuzDeployment
   */
  function deployment()
  {
    if (!$this->deployment) {
      $this->deployment = new DiscuzDeployment();
    }

    return $this->deployment;
  }
  /**
   * 当前运行环境是否为 Discuz
   *
   * 靠 Discuz 常量 `IN_DISCUZ` 判断，只看宿主，不依赖是否已经建过应用实例——内核在
   * 「还没有 App」的早期阶段（自动加载、模型构造、URL 拼装）也要用它。
   *
   * 两类用途：
   * - 表名：Discuz 下要带应用前缀，见 {@see \kernel\Foundation\Database\RDB\Model::discuzTableName()}；
   * - 中间件：{@see \kernel\Modules\Auth\Auth} 的构造函数据此在 Discuz 认证与通用认证之间选型。
   *
   * 只回答「宿主是不是 Discuz」，不回答「Discuz 适配层是否装配完整」。
   *
   * @return bool
   */
  public static function exists()
  {
    return defined("IN_DISCUZ");
  }
}
