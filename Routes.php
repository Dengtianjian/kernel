<?php

namespace gstudio_kernel;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Router;
use gstudio_kernel\App\Main as Main;
use gstudio_kernel\App\Api as Api;
use gstudio_kernel\App\IndexController;
use gstudio_kernel\Foundation\Config;

//* 附件相关
Router::get("_download", Api\Attachments\DownloadAttachmentController::class);
Router::post("_upload", Api\Attachments\UploadAttachmentController::class);
Router::delete("_deleteAttachment", Api\Attachments\DeleteAttachmentController::class);
Router::get([
  "_attachments",
  "attachmentId" => "\d+"
], Api\Attachments\GetAttachmentController::class);

//* 扩展相关
Router::post("_extension/install", Main\Extensions\InstallExtensionController::class);
Router::post("_extension/upgrade", Main\Extensions\UpgradeExtensionController::class);
Router::post("_extension/uninstall", Main\Extensions\UninstallExtensionController::class);
Router::post("_extension/openClose", Main\Extensions\OpenCloseExtensionController::class);

if (Config::get("mode") === "development") {
  Router::any("/", IndexController::class);
}
