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
   * @param string $SignatureKey 本地存储签名秘钥
   * @param boolean $Record 文件信息是否存入数据库
   * @param string $RoutePrefix 路由前缀
   */
  public function __construct($SignatureKey, $Record = TRUE, $RoutePrefix = "files")
  {
    parent::__construct($SignatureKey, $RoutePrefix);

    if ($Record) {
      $this->filesModel = new FilesModel();
    }
  }

  /**
   * 当前登录态下的用户标识
   *
   * @var string
   */
  protected $currentLoginId = NULL;
  protected $ACLEnabled = FALSE;
  /**
   * 开启ACL，并且设置当前登录态下的用户ID  
   * 用于把ownerId和当前传入的ID比较，判断是否一致再允许进一步的操作  
   * 传入FALSE或者未调用过该方法将不会校验ACL
   *
   * @param string $ID 登录态下的用户ID
   */
  public function enableACL($ID)
  {
    $this->currentLoginId = $ID;
    $this->ACLEnabled = true;
    $this->enableAuth();
    return $this;
  }
  /**
   * 问价授权校验
   *
   * @param string $FileKey 文件键
   * @param string $AuthTag 授权值 
   * @param string $OwnerId 拥有者ID
   * @param "read"|"write $action 操作，只允许传入read（读）和write（写）参数
   * @return boolean TRUE=授权校验通过，FALSE=授权校验失败
   */
  public function FileAuthorizationVerification($FileKey, $AuthTag, $OwnerId, $action = "read")
  {
    if (!$this->filesModel || !$this->ACLEnabled) return TRUE;
    $action = strtolower($action);

    if ($OwnerId != $this->currentLoginId) {
      if ($AuthTag === self::PRIVATE) {
        return FALSE;
      } else if (in_array($AuthTag, [
        self::AUTHENTICATED_READ_WRITE,
        self::AUTHENTICATED_READ
      ])) {
        if ($AuthTag === self::AUTHENTICATED_READ && $action !== "read") {
          return FALSE;
        }
        $Verifed = $this->verifyRequestAuth($FileKey, TRUE);
        return is_numeric($Verifed) || $Verifed === FALSE ? FALSE : TRUE;
      } else if (in_array($AuthTag, [
        self::PUBLIC_READ,
        self::PUBLIC_READ_WRITE
      ])) {
        if ($AuthTag === self::PUBLIC_READ && $action !== "read") {
          return FALSE;
        }
      }
    }

    return TRUE;
  }

  /**
   * 添加文件记录
   *
   * @param string $FileKey 文件键
   * @param string $SourceFileName 原文件名
   * @param string $SaveFileName 现文件名
   * @param string $FilePath 文件保存路径
   * @param int $FileSize 文件大小
   * @param string $Extension 文件扩展名
   * @param string $OwnerId 拥有者ID
   * @param string $ACL 访问权限控制
   * @param boolean $Remote 是否是远程存储
   * @param string $BelongsId 关联数据ID
   * @param string $BelongsType 关联数据类型
   * @param int $Width 媒体文件宽度
   * @param int $Height 媒体文件高度
   * @return int|boolean
   */
  public function addFile($FileKey, $SourceFileName, $SaveFileName, $FilePath, $FileSize, $Extension, $OwnerId = null, $ACL = 'private', $Remote = false, $BelongsId = null, $BelongsType = null, $Width = null, $Height = null)
  {
    if ($this->filesModel) {
      if ($this->filesModel->existItem($FileKey)) {
        $this->filesModel->remove($FileKey);
      }

      return $this->filesModel->add($FileKey, $SourceFileName, $SaveFileName, $FilePath, $FileSize, $Extension, $OwnerId, $ACL, $Remote, $BelongsId, $BelongsType, $Width, $Height);
    }
    return FALSE;
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
    if ($this->FileAuthorizationVerification($FileKey, $ACL, $OwnerId, "write") === FALSE) {
      return $this->break(403, 403, "抱歉，您没有上传该文件的权限");
    }

    $PathInfo = pathinfo($FileKey);

    $FileInfo = FileManager::upload($File, $PathInfo['dirname'], $PathInfo['basename']);
    if (!$FileInfo) {
      return $this->break(500, 500, "文件上传失败");
    }

    $FileInfo['name'] = $FileInfo['basename'];
    $FileInfo['key'] = $FileKey;
    $FileInfo['remote'] = false;

    if ($this->filesModel) {
      if ($this->filesModel->existItem($FileKey)) {
        $this->filesModel->remove(true, $FileKey);
      }
      $this->filesModel->add($FileKey, $FileInfo['sourceFileName'], $FileInfo['fileName'], $FileInfo['path'], $FileInfo['size'], $FileInfo['extension'], $OwnerId, $ACL, false, $BelongsId, $BelongsType, $FileInfo['width'], $FileInfo['height']);
    }

    return $this->getFileInfo($FileKey);
  }
  function deleteFile($FileKey)
  {
    $FileInfo = $this->getFileInfo($FileKey);
    if ($this->error) return $this->return();

    if ($this->FileAuthorizationVerification($FileKey, $FileInfo->acl, $FileInfo->ownerId) === FALSE) {
      return $this->break(403, 403001, "抱歉，您无权删除该文件");
    }

    $DeletedResult = FileManager::deleteFile(FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey)));

    if ($DeletedResult && $this->filesModel) {
      $this->filesModel->remove(true, $FileKey);
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
        $FileInfo['acl'] = NULL;
        $FileInfo['ownerId'] = NULL;
      }
    }
    if ($this->FileAuthorizationVerification($FileKey, $FileInfo['acl'], $FileInfo['ownerId'], "read") === FALSE) {
      return $this->break(403, 403001, "抱歉，您无权查看该文件信息");
    }

    $FileInfo['key'] = $FileKey;
    $FileInfo['path'] = pathinfo($FileKey, PATHINFO_DIRNAME);
    $FileInfo['name'] = pathinfo($FileKey, PATHINFO_BASENAME);
    $FileInfo['previewURL'] = $this->getFilePreviewURL($FileKey);
    $FileInfo['downloadURL'] = $this->getFileDownloadURL($FileKey);

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
    return null;
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
    return null;
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
    return null;
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
