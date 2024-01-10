<?php

namespace kernel\Foundation\File\Driver;

use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileInfoData;
use kernel\Foundation\File\FileManager;
use kernel\Foundation\HTTP\URL;
use kernel\Model\FilesModel;

class FileStorageDriver extends AbstractFileDriver
{
  /**
   * 私有的，创作者与管理员具备全部权限，其他人没有权限
   */
  const PRIVATE = "private";
  /**
   * 共有读的，匿名用户具备 READ 权限，创作者与管理员具备全部权限
   */
  const PUBLIC_READ = "public-read";
  /**
   * 公有读写，创建者、管理员和匿名用户具备全部权限，通常不建议授予此权限
   */
  const PUBLIC_READ_WRITE = "public-read-write";
  /**
   * 认证用户具备 READ 权限，创作者与管理员具备全部权限
   */
  const AUTHENTICATED_READ = "authenticated-read";
  /**
   * 创建者、管理员和认证用户具备全部权限，通常不建议授予此权限
   */
  const AUTHENTICATED_READ_WRITE = "authenticated-read-write";

  /**
   * 文件表模型实例
   *
   * @var FilesModel
   */
  protected $filesModel = null;

  /**
   * 实例化文件存储类
   *
   * @param boolean $VerifyAuth 访问、上传文件需要验证授权
   * @param string $SignatureKey 本地存储签名秘钥
   * @param boolean $Record 文件信息是否存入数据库
   * @param string $RoutePrefix 路由前缀
   */
  public function __construct($VerifyAuth, $SignatureKey, $Record = TRUE, $RoutePrefix = "files")
  {
    parent::__construct($VerifyAuth, $SignatureKey, $RoutePrefix);

    if ($Record) {
      $this->filesModel = new FilesModel();
    }
  }

  /**
   * 上传文件，并且保存在服务器
   *
   * @param File $File 文件
   * @param string $FileKey 文件名
   * @param string $OwnerId 拥有者ID
   * @param string $BelongsId 关联数据ID
   * @param string $BelongsType 关联数据类型
   * @param string $ACL 文件访问权限控制
   */
  function uploadFile($File, $FileKey = null, $OwnerId = null, $BelongsId = null, $BelongsType = null, $ACL = self::PRIVATE)
  {
    $PathInfo = pathinfo($FileKey);

    $FileInfo = FileManager::upload($File, $PathInfo['dirname'], $PathInfo['basename']);
    if (!$FileInfo) {
      return $this->break(500, 500, "文件上传失败");
    }

    $FileInfo['key'] = $FileKey;
    $FileInfo['remote'] = false;

    if ($this->filesModel) {
      if ($this->filesModel->existItem($FileKey)) {
        $this->filesModel->remove($FileKey);
      }
      $this->filesModel->add($FileKey, $FileInfo['sourceFileName'], $FileInfo['fileName'], $FileInfo['path'], $FileInfo['size'], $FileInfo['extension'], $OwnerId, $ACL, false, $BelongsId, $BelongsType, $FileInfo['width'], $FileInfo['height']);
    }

    return $this->getFileInfo($FileKey);
  }
  function deleteFile($FileKey)
  {
    $DeletedResult = FileManager::deleteFile(FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey)));

    if ($DeletedResult && $this->filesModel) {
      $this->filesModel->where("key", $FileKey);
    }

    return $DeletedResult;
  }
  /**
   * 获取文件信息
   *
   * @param string $FileKey 文件名
   * @return FileInfoData 文件信息
   */
  function getFileInfo($FileKey)
  {
    $FileKey = rawurldecode(urldecode($FileKey));
    if ($this->filesModel) {
      $FileInfo = $this->filesModel->item($FileKey);
      if (!$FileInfo) {
        return $this->break(404, 404001, "文件不存在");
      };
    }

    if ($FileInfo['remote']) {
      $FileInfo['remote'] = boolval(intval($FileInfo['remote']));
      $FileInfo['filePath'] = null;
    } else {
      $LocalFileInfo = FileManager::getFileInfo(FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey)));
      if (!$LocalFileInfo) {
        return $this->break(404, 404002, "文件不存在");
      }
      if ($this->filesModel) {
        $FileInfo['width'] = $LocalFileInfo['width'];
        $FileInfo['height'] = $LocalFileInfo['height'];
        $FileInfo['remote'] = boolval(intval($FileInfo['remote']));
        $FileInfo['path'] = $FileInfo['filePath'];
        $FileInfo['filePath'] = $LocalFileInfo['filePath'];
      } else {
        $FileInfo = $LocalFileInfo;
        $FileInfo['remote'] = false;
        $FileInfo['size'] = $LocalFileInfo['size'];
      }
    }

    $FileInfo['key'] = $FileKey;
    $FileInfo['previewURL'] = $this->getFilePreviewURL($FileKey);
    $FileInfo['downloadURL'] =  $this->getFileDownloadURL($FileKey);

    return new FileInfoData($FileInfo);
  }
  /**
   * 生成远程存储授权信息
   *
   * @param string $FileKey 文件名
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param string $HTTPMethod 请求方式
   * @param boolean $toString 字符串形式返回参数，如果传入false，将会返回参数数组
   * @return string|array 授权信息
   */
  function getFileRemoteAuth($FileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get", $toString = false)
  {
    return $this->getFileAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod, $toString);
  }
  /**
   * 获取访问链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param int $Expires 签名有效期
   * @param bool $WithSignature 带有签名
   * @return string 访问URL
   */
  function getFilePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "{$this->routePrefix}/{$FileKey}/preview";

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
    }

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  /**
   * 获取远程浏览链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param int $Expires 签名有效期
   * @param bool $WithSignature 带有签名
   * @return string 访问URL
   */
  function getFileRemotePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return $this->getFilePreviewURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
  /**
   * 获取下载链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param int $Expires 签名有效期
   * @param bool $WithSignature 带有签名
   * @return string 下载URL
   */
  function getFileDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "{$this->routePrefix}/{$FileKey}/download";

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
    }

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  /**
   * 获取远程下载链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param int $Expires 签名有效期
   * @param bool $WithSignature 带有签名
   * @return string 下载URL
   */
  function getFileRemoteDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return $this->getFileDownloadURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
  /**
   * 获取图片信息
   *
   * @param string $FileKey
   */
  function getImageInfo($FileKey)
  {
    return $this->getFileInfo($FileKey);
  }
}