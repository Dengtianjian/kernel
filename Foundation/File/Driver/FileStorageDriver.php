<?php

namespace kernel\Foundation\File\Driver;

use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileManager;
use kernel\Foundation\HTTP\URL;
use kernel\Model\FilesModel;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;

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

  public function __construct($VerifyAuth, $SignatureKey, $Record = TRUE)
  {
    parent::__construct($VerifyAuth, $SignatureKey);

    if ($Record) {
      $this->filesModel = new DiscuzXFilesModel();
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
   * @return ReturnResult{array{fileKey:string,sourceFileName:string,path:string,filePath:string,fileName:string,extension:string,fileSize:int,path:string,width:int,height:int,remote:boolean}} 文件信息
   */
  function uploadFile($File, $FileKey = null, $OwnerId = null, $BelongsId = null, $BelongsType = null, $ACL = self::PRIVATE)
  {
    $PathInfo = pathinfo($FileKey);

    $FileInfo = FileManager::upload($File, $PathInfo['dirname'], $PathInfo['basename']);
    if (!$FileInfo) return $this->return->error(500, 500, "文件上传失败");

    $FileInfo['fileKey'] = $FileKey;
    $FileInfo['fileSize'] = $FileInfo['size'];
    $FileInfo['remote'] = false;

    if ($this->filesModel) {
      $this->filesModel->add($FileKey, $FileInfo['sourceFileName'], $FileInfo['fileName'], $FileInfo['path'], $FileInfo['size'], $FileInfo['extension'], $OwnerId, $ACL, false, $BelongsId, $BelongsType, $FileInfo['width'], $FileInfo['height']);
    }

    return $this->return->success($FileInfo);
  }
  function deleteFile($FileKey)
  {
    $DeletedResult = FileManager::deleteFile(FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey)));

    if ($DeletedResult && $this->filesModel) {
      $this->filesModel->where("key", $FileKey);
    }

    return $this->return->success($DeletedResult);
  }
  /**
   * 获取文件信息
   *
   * @param string $FileKey 文件名
   * @return ReturnResult{array{fileKey:string,path:string,fileName:string,extension:string,fileSize:int,filePath:string,width:int|null,height:int|null,remote:boolean}} 文件信息
   */
  function getFileInfo($FileKey)
  {
    if ($this->filesModel) {
      $FileInfo = $this->filesModel->where("key", $FileKey)->getOne();
      if (!$FileInfo) return $this->return->error(404, 404001, "文件不存在");
    }
    $LocalFileInfo = FileManager::getFileInfo(FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey)));
    if (!$LocalFileInfo) return $this->return->error(404, 404, "文件不存在");
    if ($this->filesModel) {
      $FileInfo['width'] = $LocalFileInfo['width'];
      $FileInfo['height'] = $LocalFileInfo['height'];
      $FileInfo['fileSize'] = $LocalFileInfo['size'];
      $FileInfo['remote'] = boolval(intval($FileInfo['remote']));
      $FileInfo['path'] = $FileInfo['filePath'];
      $FileInfo['filePath'] = $LocalFileInfo['filePath'];
    } else {
      $FileInfo = $LocalFileInfo;
      $FileInfo['remote'] = false;
    }

    $FileInfo['fileKey'] = $FileKey;

    return $this->return->success($FileInfo);
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
    $AccessURL->pathName = "files/{$FileKey}/preview";

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
    $AccessURL->pathName = "files/{$FileKey}/download";

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
   * @return ReturnResult{array{fileKey:string,path:string,fileName:string,extension:string,fileSize:int,filePath:string,width:int|null,height:int|null,remote:boolean,url:string}} 文件信息
   */
  function getImageInfo($FileKey)
  {
    return $this->getFileInfo($FileKey);
  }
}
