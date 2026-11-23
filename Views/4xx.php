<?php

use kernel\Foundation\App;
use kernel\Support\Output;

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $code; ?>:<?php echo $message; ?></title>
</head>

<body>
  <style>
    pre {
      padding: 10px;
      line-height: 30px;
      font-family: "Fira Code Regular", Inconsolata, 微软雅黑, Consolas, 'Courier New', monospace;
      background-color: #fafafa;
      border-radius: 5px;
    }
  </style>
  <h1><?php if ($errorCode) { ?> 「<?php echo $errorCode; ?>」 <?php } ?> <?php echo $code; ?></h1>
  <h2><?php echo $message; ?></h2>
</body>

</html>
