# gstudio_kernel
Discuz!X插件内核

# Iuu
## Install
在Iuu/Install文件夹下面。  
### Install类
```php
<?php

//* 变更为当前插件的ID
namespace gstudio_kernel\Iuu\Install;

if (!defined("IN_DISCUZ") || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

class Install
{
  public function __construct()
  {
    //* 这里编写安装时需要执行的代码
  }
}
```
### 入口 install.php
```php
<?php

use gstudio_kernel\Foundation\Iuu;

if (!defined("IN_DISCUZ") || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

if (!file_exists(DISCUZ_ROOT . "source/plugin/gstudio_kernel/Autoload.php")) {
  showmessage("Need to install The Core Plugin", null, [], [
    "alert" => "error"
  ]);
  exit;
}

include_once(DISCUZ_ROOT . "source/plugin/gstudio_kernel/Autoload.php");

//* 替换插件ID
$Iuu = new Iuu("gstudio_kernel", $_GET['fromversion']);
$Iuu->install()->runInstallSql();

$finish = TRUE;
```
### 目录结构
**这些文件都可有可无的，有需要就按规范加进去**   
在Install目录下放置一个`Install.php`，用于安装时的处理逻辑。以每个编码的名称作为一个SQL文件（全大写）名，里面放置安装时需要执行的SQL文件，例如`GBK.sql`、`UTF-8.php`。  
如果就单独支持唯一编码，可在`Install`目录下只放创建一个`install.sql`文件，里面也是编写安装时执行的SQL语句。   
例如：
- Iuu
  - Install
    - GBK.sql
    - UTF-8.sql
    - Install.php
    - install.sql
- install.php
## Upgrade
更新基于`/Iuu/Upgrade/`目录  
每次用户在后台点击更新时都会读取该目录下的一级文件夹，然后循环比较版本，当旧的版本小于当前版本时会运行里面的处理类。
### 入口 upgrade.php
```php
<?php

use gstudio_kernel\Foundation\Iuu;

if (!defined("IN_DISCUZ") || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

if (!file_exists(DISCUZ_ROOT . "source/plugin/gstudio_kernel/Autoload.php")) {
  showmessage("Need to install The Core Plugin", null, [], [
    "alert" => "error"
  ]);
  exit;
}

include_once(DISCUZ_ROOT . "source/plugin/gstudio_kernel/Autoload.php");

//* 插件ID更改
$Iuu = new Iuu("gstudio_kernel", $_GET['fromversion']);
$Iuu->upgrade()->clean();

$finish = TRUE;
```
### Upgrade类
```php
<?php

//* 变更为当前插件的ID
namespace gstudio_kernel\Iuu\Upgrade;

if (!defined("IN_DISCUZ") || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

//* 这里Upgrade_前缀必须有，后面是版本，用 _ 分隔，同类文件名称
class Upgrade_0_5_0
{
  public function __construct()
  {
    //* 这里是更新时执行的代码
  }
}
```
### 目录结构
当需要编写版本更新处理时，需要在`Iuu/Upgrade`文件夹下创建一个文件夹，文件夹名称使用**数字，以及 . 分隔开来**，例如：`0.2.1`、`1.15.6`，然后在里面创建一个以`Upgrade_`为前缀，再加上以版本号用`_`分隔的文件名称，例如：`Upgrade_0_2_1`，`Upgrade_1_15_1`，该文件名同文件内的类名称一致。
- Iuu
  - Upgrade
    - 0.2.1
      - Upgrade_0_2_1.php
    - 1.15.1
      - Upgrade_1_15_1.php
### 多编码SQL
假设是0.2.1版本，就在`Iuu/Upgrade`下创建一个0.2.1文件夹，里面放置`Upgrade_0_2_1.php`文件夹，同时存放需要支持的**大写形式以编码名称为文件名称**的SQL文件，例如`GBK.sql`、`UTF-8.sql`，然后在`Upgrade_0_2_1`里读取不同编码的文件内容，通过`runquery`执行。  
文件夹结构
- Iuu
  - Upgrade
    - 0.2.1
      - Upgrade_0_2_1.php
      - GBK.sql
      - UTF-8.sql
```php
<?php
//* Upgrade_0_2_1.php

//* 变更为当前插件的ID
namespace gstudio_kernel\Iuu\Upgrade;

use gstudio_kernel\Foundation\File;

if (!defined("IN_DISCUZ") || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

//* 这里Upgrade_前缀必须有，后面是版本，用 _ 分隔，同类文件名称
class Upgrade_0_2_1
{
  public function __construct()
  {
    $sql = file_get_contents(File::genPath(F_APP_BASE,"Iuu/Upgrade/Upgrade_0_2_1",CHARSET.".sql"));
    
    \runquery($sql);
  }
}

```
## 卸载
创建入口文件`uninstall.php`即可
```php
<?php

use gstudio_kernel\Foundation\Config;
use gstudio_kernel\Foundation\File;
use gstudio_kernel\Foundation\Iuu;

if (!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

if (!file_exists(DISCUZ_ROOT . "source/plugin/gstudio_kernel/Autoload.php")) {
  showmessage("Need to install The Core Plugin", null, [], [
    "alert" => "error"
  ]);
  exit;
}

include_once(DISCUZ_ROOT . "source/plugin/gstudio_kernel/Autoload.php");

$sql = <<<SQL

SQL;

runquery($sql);

// 替换插件ID
$Iuu = new Iuu("gstudio_kernel", null);
$Iuu->uninstall();

$finish = TRUE;

```