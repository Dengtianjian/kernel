<?php

namespace kernel\Foundation\Extension;

use kernel\Foundation\GlobalVariables;

class Extensions
{
  /**
   * 扫描指定目录下的扩展
   *
   * @param string $rootPath 扩展文件夹目录根目录，基于插件根目录，不用加上F_ROOT
   * @param string $extensionFolderName? 指定扩展文件夹名称
   * @return array 扩展配置文件
   */
  public static function scanDir($rootPath, $extensionFolderName = "Extensions")
  {
    $extensionPath = $rootPath . "/" . $extensionFolderName;
    if (!is_dir($extensionPath)) {
      return [];
    }
    $dirs = \scandir($extensionPath);
    $extensions = [];

    foreach ($dirs as $dirItem) {
      if ($dirItem === "." || $dirItem === "..") {
        continue;
      }
      //* 配置文件
      $extensionRootPath = $extensionPath . "/" . $dirItem;
      $extensionJsonFilePath = $extensionRootPath . "/extension.json";
      $configJson = \file_get_contents($extensionJsonFilePath);
      $configJson = \json_decode($configJson, true);

      //* 子扩展
      if (is_dir($extensionRootPath . "/" . $extensionFolderName)) {
        $subExtensions = NULL;
        $subExtensions = self::scanDir($extensionRootPath, $extensionFolderName);
        foreach ($subExtensions as &$extensionConfig) {
          $extensionConfig['sub'] = true;
          if (!isset($extensionConfig['parent'])) {
            $extensionConfig['parent'] = $configJson['id'];
          }
        }
        $extensions = \array_merge($extensions, $subExtensions);
      }

      $configJson['root'] = $extensionRootPath;
      $configJson['icon'] = $extensionRootPath . "/icon.png";

      $extensions[$configJson['id']] = $configJson;
    }
    return $extensions;
  }
  /**
   * 读取扩展extension.json配置信息文件
   *
   * @param string $extensionId 扩展id
   * @param string? $extensionRootPath 扩展所在文件夹，不用加上extension.json，也不用加上F_ROOT
   * @return array 扩展配置信息
   */
  public static function config($extensionId, $extensionRootPath = NULL)
  {
    if ($extensionRootPath) {
      $configFilePath = \F_ROOT . $extensionRootPath . "/extension.json";
    } else {
      $configFilePath = F_ROOT . "source/plugin/" . GlobalVariables::getGG("id") . "/Extensions/$extensionId/extension.json";
    }
    if (!\file_exists($configFilePath)) {
      return false;
    }
    $config = \file_get_contents($configFilePath);
    $config = \json_decode($config, true);
    return $config;
  }
}
