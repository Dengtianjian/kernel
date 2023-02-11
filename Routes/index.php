<?php

use kernel\App\Api\Attachment as Attachment;
use kernel\Foundation\Router;
use kernel\App\Main as Main;
use kernel\App\Main\TestController;
use kernel\Foundation\Config;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\HTTP\Response\ResponseView;
use kernel\Middleware\RouteTestMiddleware;

//* 扩展相关
// Router::get("_extensions", Main\Extensions\ExtensionListViewController::class);
// Router::post("_extension/install", Main\Extensions\InstallExtensionController::class);
// Router::post("_extension/upgrade", Main\Extensions\UpgradeExtensionController::class);
// Router::post("_extension/uninstall", Main\Extensions\UninstallExtensionController::class);
// Router::post("_extension/openClose", Main\Extensions\OpenCloseExtensionController::class);

//* 附件
// Router::post("/attachment", Attachment\UploadAttachmentController::class);
// Router::get("/attachment", Attachment\GetAttachmentController::class);
// Router::delete("/attachment", Attachment\DeleteAttachmentController::class);
// Router::get("/downloadAttachment", Attachment\DownloadAttachmentController::class);
// Router::get("/thumbnail", Attachment\GetImageThumbnailViewController::class);

Router::get("/",TestController::class);