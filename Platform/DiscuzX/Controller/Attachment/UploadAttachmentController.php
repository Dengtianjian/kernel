<?php

namespace kernel\Platform\DiscuzX\Controller\Attachment;

use kernel\Foundation\HTTP\Response\ResponseError;
use kernel\Platform\DiscuzX\DiscuzXAttachment;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;

class UploadAttachmentController extends DiscuzXController
{
  public $Auth = false;
  public $serializes = [
    "aid" => "int",
    "fileName" => "string",
    "isImage" => "bool",
    "size" => "double",
    "width" => "double",
    "height" => "double",
    "downloadLink" => "string",
    "thumbURL" => "string",
  ];
  public function data()
  {
    if (count($_FILES) === 0 || !$_FILES['file']) {
      return new ResponseError(400, "Attachment:400001", "请上传文件", $_FILES);
    }

    $UploadResult = DiscuzXAttachment::uploadFile($_FILES['file']);
    $Attachment = $UploadResult->getData();
    if (!$UploadResult->error) {
      $aidEncode = aidencode($Attachment['aid']);
      $url = "forum.php?mod=attachment&aid=" . $aidEncode . "&nothumb=yes";
      $thumbURL = null;
      if ($Attachment['isimage']) {
        $thumbURL = getforumimg($Attachment['aid'], 0, $Attachment['imageinfo'][0], $Attachment['imageinfo'][1], $Attachment['ext']);
      }
      $data = [
        "aid" => $Attachment['aid'],
        "fileName" => $Attachment['name'],
        "isImage" => $Attachment['isimage'],
        "size" => $Attachment['size'],
        "width" =>  $Attachment['imageinfo'] ? $Attachment['imageinfo'][0] : 0,
        "height" => $Attachment['imageinfo'] ? $Attachment['imageinfo'][1] : 0,
        "downloadLink" => $url,
        "thumbURL" => $thumbURL,
      ];
      $UploadResult->setData($data);
    }
    return $UploadResult;
  }
}
