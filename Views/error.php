<?

use kernel\Foundation\Output;

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><? echo $code; ?>:<? echo $message; ?></title>
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
  <h1><? echo $code; ?></h1>
  <h2><? echo $message; ?></h2>
  <section>
    <p>
      File:<? echo $file; ?>
    </p>
    <p>
      Line:<? echo $line; ?>
    </p>
    <p>
      Trace:
    <pre><? Output::printContent(implode("\n", $traceString)) ?></pre>
    </p>
  </section>
</body>

</html>