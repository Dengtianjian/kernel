<?php
// 临时冒烟脚本，验证 DataObject 重构
require __DIR__ . '/Foundation/Object/DataObject.php';
require __DIR__ . '/Foundation/Exception/Exception.php';

use kernel\Foundation\Object\DataObject;

class SmokeDataObject extends DataObject
{
  protected $name = null;
  protected $size = 0;
  protected $flag = false;
}

$pass = 0; $fail = 0;
function check($label, $cond) {
  global $pass, $fail;
  if ($cond) { $pass++; echo "PASS: $label\n"; }
  else { $fail++; echo "FAIL: $label\n"; }
}

// 1. 正常赋值
$d = new SmokeDataObject(['name' => 'a.txt', 'size' => 10, 'flag' => true]);
check('name 赋值', $d->name === 'a.txt');
check('size 赋值', $d->size === 10);
check('flag 赋值', $d->flag === true);

// 2. 缺键保留默认值（F1/E1）
$d2 = new SmokeDataObject(['name' => 'b.txt']);
check('缺键 size 保留默认 0', $d2->size === 0);
check('缺键 flag 保留默认 false', $d2->flag === false);

// 3. __get 防御（F3）
$d3 = new SmokeDataObject([]);
check('__get 未知属性返回 null', $d3->notExist === null);

// 4. __set 拦截（F4）
$threw = false;
try { $d3->newProp = 1; } catch (\Exception $e) { $threw = true; }
check('__set 动态属性抛异常', $threw);

// 5. toArray / get / has / keys（O1/E2）
$arr = $d->toArray();
check('toArray 键完整', isset($arr['name'], $arr['size'], $arr['flag']));
check('toArray 值正确', $arr['name'] === 'a.txt' && $arr['size'] === 10);
check('get 存在键', $d->get('name') === 'a.txt');
check('get 缺省键回退默认', $d2->get('size') === 0);
check('get 不存在键回退默认值', $d2->get('no_such', 'fallback') === 'fallback');
check('has 存在', $d->has('name') === true);
check('has 不存在', $d2->has('no_such') === false);
check('keys', $d->keys() === ['name', 'size', 'flag']);

// 6. __toString / jsonSerialize（F5/E4）
check('__toString 合法 JSON', json_decode((string)$d, true)['name'] === 'a.txt');
check('jsonSerialize', json_decode(json_encode($d), true)['name'] === 'a.txt');

// 7. 对象输入（含 toArray）
$objIn = new SmokeDataObject(new SmokeDataObject(['name' => 'fromObj', 'size' => 3]));
check('对象输入 toArray', $objIn->name === 'fromObj' && $objIn->size === 3);

// 8. 纯数组对象输入（无 toArray，走 (array) 转换）
$plain = new stdClass();
$plain->name = 'plain';
$plain->size = 7;
$d4 = new SmokeDataObject($plain);
check('stdClass 输入', $d4->name === 'plain' && $d4->size === 7);

echo "\n==== $pass PASS / $fail FAIL ====\n";
exit($fail === 0 ? 0 : 1);
