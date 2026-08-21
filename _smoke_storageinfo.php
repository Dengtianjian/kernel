<?php
// 临时冒烟脚本：验证真实子类 StorageFileInfoData 在重构后行为
spl_autoload_register(function ($class) {
  $prefix = 'kernel\\';
  if (strpos($class, $prefix) === 0) {
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/../kernel/' . $rel . '.php';
    if (file_exists($file)) { require $file; }
  }
});

use kernel\Foundation\FileSystem\Storage\StorageFileInfoData;

$pass = 0; $fail = 0;
function check($label, $cond) {
  global $pass, $fail;
  if ($cond) { $pass++; echo "PASS: $label\n"; }
  else { $fail++; echo "FAIL: $label\n"; }
}

// 构造时含 filePath（path/name 缺失），保留传入值
$d = new StorageFileInfoData([
  'key' => 'abc',
  'name' => 'a.txt',
  'path' => '/dir',
  'filePath' => 'CUSTOM_PATH',
]);
check('filePath 已有则保留', $d->filePath === 'CUSTOM_PATH');
check('key 填充', $d->key === 'abc');
check('name 填充', $d->name === 'a.txt');

// 缺 filePath，自动拼接 path + name
$d2 = new StorageFileInfoData([
  'key' => 'abc2',
  'name' => 'b.txt',
  'path' => '/x',
]);
check('filePath 自动拼接', $d2->filePath !== null && $d2->filePath !== '');
check('remote 默认 false', $d2->remote === false);
check('platform 默认 local', $d2->platform === 'local');

// 缺键（如 url）保留默认 null，不告警
$d3 = new StorageFileInfoData(['key' => 'k3']);
check('缺 url 保留默认 null', $d3->url === null);
check('缺 ownerId 保留默认 false', $d3->ownerId === false);
// path/name 缺失时不拼 filePath（原逻辑会 Undefined key 告警），保留默认 null
check('缺 path/name 不告警且 filePath 保留 null', $d3->filePath === null);

// 读取未知属性 __get 防御
check('未知属性返回 null', $d3->noSuchField === null);

// toArray 键数 = 18 个属性
$arr = $d3->toArray();
check('toArray 含全部属性键', count($arr) === 18);

// __toString 合法
check('__toString 合法', json_decode((string)$d3, true) !== null);

echo "\n==== $pass PASS / $fail FAIL ====\n";
exit($fail === 0 ? 0 : 1);
