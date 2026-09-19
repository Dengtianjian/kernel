<?php

// function loader($className, $SkipClassName = [], $SDKClassName = [])
// {
//   $className = str_replace("\\", "/", $className);
//   if (strpos($className, "kernel") !== false && strpos($className, "gstudio_kernel") === false) {
//     $className = str_replace("kernel", "gstudio_kernel", $className);
//   }

//   $SDKLoaded = false;

//   foreach ($SDKClassName as $item) {
//     $item = str_replace("\\", "/", $item);
//     if (strpos($className, $item) !== false) {
//       $className = F_APP_ID . "/SDK/{$className}";
//       $SDKLoaded = true;
//     }
//   }
//   if (!$SDKLoaded) {
//     foreach ($SkipClassName as $item) {
//       $item = str_replace("\\", "/", $item);
//       if (strpos($className, $item) !== false) {
//         return;
//       }
//     }
//   }

//   $filePath = DISCUZ_ROOT . "/source/plugin/$className.php";
//   if (\file_exists($filePath)) {
//     include_once($filePath);
//   } else {
//     if (strpos($filePath, "gstudio") !== false && defined("F_APP_MODE") && F_APP_MODE === "development") {
//       debug($filePath);
//     }
//   }
// }

return function ($SkipClassName = [], $SDKClassName = []) {
  spl_autoload_register(function ($className) use ($SkipClassName, $SDKClassName) {
    $className = str_replace("\\", "/", $className);
    if (strpos($className, "kernel") !== false && strpos($className, "gstudio_kernel") === false) {
      $className = str_replace("kernel", "gstudio_kernel", $className);
    }

    $SDKLoaded = false;

    foreach ($SDKClassName as $item) {
      $item = str_replace("\\", "/", $item);
      if (strpos($className, $item) !== false) {
        $className = F_APP_ID . "/SDK/{$className}";
        $SDKLoaded = true;
      }
    }
    if (!$SDKLoaded) {
      foreach ($SkipClassName as $item) {
        $item = str_replace("\\", "/", $item);
        if (strpos($className, $item) !== false) {
          return;
        }
      }
    }

    $filePath = DISCUZ_ROOT . "/source/plugin/$className.php";
    if (\file_exists($filePath)) {
      include_once($filePath);
    } else {
      if (strpos($filePath, "gstudio") !== false && defined("F_APP_MODE") && F_APP_MODE === "development") {
        debug([
          $className,
          $filePath
        ]);
      }
    }
  }, true, true);
};
