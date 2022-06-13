<?php

namespace kernel\Foundation;

use ZipArchive;

class Zip
{
  public $packageFileBlackList = [
    ".git", "README.md"
  ];
  public function zipExtentions($extensionsFolderPath, $outputRoot, $localPath)
  {
    if (!\is_dir($extensionsFolderPath)) {
      return false;
    }
    $dirs = \scandir($extensionsFolderPath);
    foreach ($dirs as $dirItem) {
      if ($dirItem === "." || $dirItem === "..") {
        continue;
      }
      $this->zipDir("$extensionsFolderPath/$dirItem", "$outputRoot/$dirItem.zip", true, "$localPath/$dirItem");
    }
  }
  public function folderToZip(&$zip, $folder,  $removedLength, $localRootPath = null)
  {
    $dirs = File::scandir($folder);
    foreach ($dirs as $dirItem) {
      if (\in_array($dirItem, $this->packageFileBlackList)) {
        continue;
      }
      $sourceFilePath = File::genPath($folder, $dirItem);
      $localPath = \substr($sourceFilePath, $removedLength + 1);
      if (\is_file($sourceFilePath)) {
        $zip->addFile($sourceFilePath, $localPath);
      } else {
        $zip->addEmptyDir($localPath);
        $this->folderToZip($zip, $sourceFilePath,  $removedLength);
      }
    }
  }
  public function zipDir($sourcePath, $outputPath, $localRootPath = null)
  {
    $zip = new \ZipArchive();
    if (\file_exists($outputPath)) {
      $zip->open($outputPath, \ZipArchive::OVERWRITE);
    } else {
      $zip->open($outputPath, \ZipArchive::CREATE);
    }
    $pathInfo = \pathinfo($sourcePath);
    $this->folderToZip($zip, $sourcePath,  \strlen(File::genPath($pathInfo['dirname'], $pathInfo['basename'])), $localRootPath);

    $zip->close();
  }
  public function unzip(string $filePath, string $dest)
  {
    if (!is_file($filePath)) {
      return false;
    }
    if (!is_dir($dest)) {
      mkdir($dest, 0777, true);
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath)) {
      $zip->extractTo($dest);
      $zip->close();
      return true;
    }
    return false;
  }
}
