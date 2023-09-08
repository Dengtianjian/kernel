<?php

namespace kernel\Service;

use kernel\Foundation\File;
use kernel\Foundation\Router;
use kernel\Foundation\Service;
use kernel\Model\AttachmentsModel;

use kernel\Controller\Main\Attachments as Attachments;
use kernel\Model\AttachmentKeysModel;

class AttachmentsService extends Service
{
  /**
   * 生成附件ID
   *
   * @param string $savePath 保存的路径
   * @param string $fileName 文件名称（含扩展名）
   * @return string
   */
  protected static function genAttachId($savePath, $fileName)
  {
    $savePath = substr($savePath, stripos($savePath, "/") + 1);
    return md5($savePath . "/" . $fileName . ":" . uniqid("attachment"));
  }
  /**
   * 获取附件下载地址
   *
   * @param string $attachmentId 附件ID
   * @return string
   */
  static function getDownloadURL($attachmentId, $withKey = false, $userId = null, $periodSeconds = 300, $preview = true, $width = null, $height = null)
  {
    $QueryStrings = [];
    if ($withKey) {
      $QueryStrings['key'] = self::getAccessKey($attachmentId, $userId, $periodSeconds, $preview, true, $width, $height);
    }
    if ($width) {
      $QueryStrings['w'] = $width;
    }
    if ($height) {
      $QueryStrings['w'] = $height;
    }
    return F_BASE_URL . "/attachments/$attachmentId"  . "/download?" . http_build_query($QueryStrings);
  }
  /**
   * 获取附件预览地址
   *
   * @param string $attachmentId 附件ID
   * @return string
   */
  static function getPreviewURL($attachmentId, $withKey = false, $userId = null, $periodSeconds = 300, $download = true, $width = null, $height = null)
  {
    $QueryStrings = [];
    if ($withKey) {
      $QueryStrings['key'] = self::getAccessKey($attachmentId, $userId, $periodSeconds, true, $download, $width, $height);
    }
    if ($width) {
      $QueryStrings['w'] = $width;
    }
    if ($height) {
      $QueryStrings['w'] = $height;
    }
    return F_BASE_URL . "/attachments/$attachmentId"  . "/preview?" . http_build_query($QueryStrings);
  }
  /**
   * 生成附件访问秘钥
   *
   * @param string $attachId 附件ID
   * @param int $userId 可访问的用户ID
   * @param integer $periodSeconds 有效期，秒级时间戳
   * @param integer $expirationTime 有效期至，秒级时间戳
   * @param boolean $preview 可预览
   * @param boolean $download 可下载
   * @param integer $width 宽度，图片附件独有参数。如果传入该参数，当访问或者下载时通过URL获取到的宽度与该参数不一致时会响应403
   * @param integer $height 高度，图片附件独有参数。如果传入该参数，当访问或者下载时通过URL获取到的高度与该参数不一致时会响应403
   * @param integer $ratio 比例，图片附件独有参数。如果传入该参数，当访问或者下载时通过URL获取到的比例与该参数不一致时会响应403
   * @return string 秘钥
   */
  static function generateAccessKey($attachId, $userId = null, $periodSeconds = 300, $expirationTime = null, $preview = true, $download = true, $width = null, $height = null, $ratio = null)
  {
    $preview = boolval(intval($preview));
    $download = boolval(intval($download));
    $expirationTime = $expirationTime ?: time() + $periodSeconds;
    $key = md5(implode("|", array_filter([
      $attachId,
      $expirationTime,
      $preview,
      $download,
      $width,
      $height,
      $ratio
    ], function ($item) {
      return !is_null($item);
    })));
    return md5($key);
  }
  /**
   * 获取附件访问秘钥
   *
   * @param string $attachId 附件ID
   * @param int $userId 可访问的用户ID
   * @param integer $periodSeconds 有效期，秒级时间戳
   * @param boolean $preview 可预览
   * @param boolean $download 可下载
   * @param integer $width 宽度，图片附件独有参数，0或者null即为无限制。如果传入该参数，当访问或者下载时通过URL获取到的宽度与该参数不一致时会响应403
   * @param integer $height 高度，图片附件独有参数，0或者null即为无限制。如果传入该参数，当访问或者下载时通过URL获取到的高度与该参数不一致时会响应403
   * @return string|boolean 秘钥，false=生成失败
   */
  static function getAccessKey($attachId, $userId = null, $periodSeconds = 300, $preview = true, $download = true, $width = 0, $height = 0)
  {
    $ExpirationTime = time() + $periodSeconds;
    $key = self::generateAccessKey($attachId, $userId, $periodSeconds, $ExpirationTime, $preview, $download, $width, $height);
    if ($key) {
      $KeyId = AttachmentKeysModel::singleton()->add($attachId, $key, $userId, $download, $preview, $ExpirationTime);
      if ($KeyId) {
        return $key;
      }
    }

    return false;
  }
  /**
   * 上传文件
   *
   * @param File $file 文件
   * @param string $savePath 基于项目根目录
   * @return Array 附件数据
   */
  public static function upload($file, $savePath = "Data/Attachments")
  {
    if (!$file) return 0;

    $SaveFilePath = File::genPath(F_APP_ROOT, $savePath);
    if (!is_dir($SaveFilePath)) {
      File::mkdir($SaveFilePath);
    }

    $saveFileResult = File::upload($file, $SaveFilePath);
    if (!$saveFileResult) return $saveFileResult;
    if (!$savePath) {
      $savePath = $saveFileResult['relativePath'];
    }
    $attachId = self::genAttachId($savePath, $file['name']);

    return AttachmentsModel::singleton()->add($attachId, 0, $saveFileResult['sourceFileName'], $saveFileResult['saveFileName'], $saveFileResult['size'], $savePath, $saveFileResult['width'], $saveFileResult['height'], $saveFileResult['extension']);
  }
  /**
   * 根据附件ID删除附件
   *
   * @param string $attachmentId 附件ID
   * @return boolean
   */
  static function deleteByAttachmentId($attachmentId)
  {
    $AM = new AttachmentsModel();
    $attachment = self::getAttachment($attachmentId);
    if ($attachment) {
      $attachmentSavePath = File::genPath(F_APP_ROOT, $attachment['filePath'], $attachment['fileName']);
      unlink($attachmentSavePath);
      $AM->deleteItem($attachmentId);
    }
    return true;
  }
  /**
   * 获取附件信息
   *
   * @param string $attachmentId 附件ID
   * @return array
   */
  static function getAttachment($attachmentId)
  {
    $AM = new AttachmentsModel();
    $attachment = $AM->where("attachId", $attachmentId)->getOne();
    if (!$attachment) return null;
    return $attachment;
  }
  static function useService()
  {
    //* 附件路由注册
    Router::post("attachments", Attachments\UploadAttachmentController::class);
    Router::get("attachments/{attachId:\w+}", Attachments\GetAttachmentController::class);
    Router::delete("attachments/{attachId:\w+}", Attachments\DeleteAttachmentController::class);
    Router::get("attachments/{attachId:\w+}/download", Attachments\DownloadAttachmentController::class);
    Router::get("attachments/{attachId:\w+}/preview", Attachments\PreviewAttachmentController::class);
  }
}
