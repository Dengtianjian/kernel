<?php

namespace kernel\Modules\DiscuzX\Foundation;

use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\Path;


/**
 * DiscuzX 语言包（静态注册表）
 *
 * 给 DiscuzX 适配层提供一套 kernel 风格的语言读取能力：语言项按「路径」组织，
 * 取用时以 `/` 分隔逐级下钻，例如 `DiscuzXLang::value("kernel/extension_list")`。
 *
 * 数据来源：语言包文件 `Modules/DiscuzX/Langs/{utf-8,gbk}.php`（按站点 `CHARSET` 二选一）。
 * 这两个文件内部构造 `$langs` 数组，并在末尾**自行调用** `DiscuzXLang::add($langs)` ——
 * 也就是说「注册」这一步是文件自己完成的，`load()` 只负责把文件引进来。
 *
 * 用法：
 * ```php
 * // 内核自带语言包在 kernelRoot 下；第二个参数为基路径，默认 Path::root()（应用根）
 * DiscuzXLang::load("Modules/DiscuzX/Langs/utf-8", Path::kernelRoot());
 *
 * DiscuzXLang::value("kernel/extension_list");              // 单个文案
 * DiscuzXLang::value("common/save", "common/cancel");       // ["保存", "取消"]
 * DiscuzXLang::connect("common/symbol/colon", "welcome");   // "：欢迎"
 * ```
 *
 * 加载时机：`DiscuzXApp::__construct()` 在应用启动时按站点 `CHARSET` 加载 `utf-8` / `gbk`，
 * 并把语言表写入 `$GLOBALS['_STORE']['__App']['langs']`（供前端 `GLANG` 使用），
 * 因此业务代码直接 `value()` 即可，无需自己 `load()`。
 *
 * 注意（使用前先看这三点）：
 * 1. **键不存在时没有默认值兜底**：`getValue()` 直接按路径访问数组下标，
 *    键缺失会触发 PHP 警告并返回 null；
 * 2. **`add()` 是浅合并**：内部用 `array_merge`，同名顶层键会被整体覆盖 ——
 *    多个语言文件都含 `common` 子树时，后加载的那份会顶掉先加载的整棵子树；
 * 3. **注册靠语言包文件自身**：语言包文件末尾会调用 `DiscuzXLang::add($langs)`，
 *    `load()` 只负责把它引进来（若语言包改成 `return [...]` 形式，`load()` 也会注册其返回值）。
 */
class DiscuzXLang
{
  /**
   * 语言字典（多维数组，按业务 / 模块分组）
   *
   * 由语言包文件调用 `add()` 填充，结构示例：
   * ```php
   * [
   *   "common" => [
   *     "install" => "安装",
   *     "symbol"  => ["colon" => "："],
   *   ],
   *   "kernel" => ["extension_list" => "扩展列表"],
   * ]
   * ```
   * 读取时把各层键用 `/` 连起来：`common/symbol/colon`。
   *
   * @var array<string, mixed>
   */
  private static $langs = [];
  /**
   * 加载语言包
   *
   * 把语言包文件「引进来」并触发其自注册，分两步：
   *   1. 按与 `import()` 相同的规则解析路径 —— `Path::join($basePath, $filePath)`，
   *      未写扩展名时自动补 `.php`；**存在性判断也用这个结果**，避免出现
   *      「守卫通过、实际找不到文件、静默不注册」；
   *   2. `import($filePath, [], $basePath)` 载入文件；若文件 `return` 了数组，再 `add()` 注册。
   *
   * 注册主要由语言包文件自身完成（其末尾会调用 `DiscuzXLang::add($langs)`）；
   * 第 2 步的类型判断既兼容「`return` 数组」的新写法，也避免 `include` 对
   * 「没有 return 的文件」返回 `1` 而被当成值写入一个键为空的噪音项。
   *
   * 由 `DiscuzXApp::__construct()` 在应用启动时调用（内核自带语言包 → `$basePath = Path::kernelRoot()`）。
   *
   * @param string $filePath 语言包路径，**相对于 `$basePath`**（可省略 `.php` 扩展名）。
   *                         注意 `Path::join` 只是朴素拼接、不识别绝对路径：传绝对路径会被拼到
   *                         `$basePath` 之后导致找不到文件并抛错，请传相对路径或用 `$basePath` 指定基准目录
   * @param string|null $basePath 基路径，默认 `Path::root()`（应用根）；内核自带语言包位于
   *                              `Path::kernelRoot()/Modules/DiscuzX/Langs/`，需显式传入 `Path::kernelRoot()`
   * @return void
   *
   * @throws Error 解析后的文件不存在时抛出（statusCode = 500、errorCode = `DiscuzXLang:500001`、
   *               errorDetails = 解析后的绝对路径）
   */
  public static function load($filePath, $basePath = null)
  {
    if ($basePath === null) {
      $basePath = Path::root();
    }

    // 未写扩展名时补 .php（与 import() 的处理保持一致）
    $relativePath = $filePath;
    if (pathinfo($relativePath, PATHINFO_EXTENSION) === "") {
      $relativePath .= ".php";
    }
    // 存在性判断与 import() 用同一套路径解析，避免「守卫通过、实际找不到文件」的静默失败
    $realFilePath = Path::join($basePath, $relativePath);
    if (!file_exists($realFilePath)) {
      throw new Error("编码文件不存在", 500, "DiscuzXLang:500001", $realFilePath);
    }

    // import() 以 include 载入文件；语言包文件内部会自行 DiscuzXLang::add($langs) 完成注册。
    // 若语言包改成 return [...] 形式，返回值也会在这里被注册
    // （include 对「没有 return 的文件」返回 1，故必须先判类型，否则会写入一个键为空的噪音项）
    $langs = import($relativePath, [], $basePath);
    if (is_array($langs)) {
      self::add($langs);
    }
  }
  /**
   * 注册语言项
   *
   * 两种用法（现有语言包文件用的是第一种）：
   * - `add(["common" => [...]])`：批量注册。与已注册内容做 `array_merge`（**浅合并**，
   *   同名顶层键整体覆盖；如需深合并请自行处理），此时 `$key` 忽略；
   * - `add("保存", "save")`：写入单个键，`$key` 为键名。注意键名**不要带 `/`** ——
   *   `value()` / `connect()` 会把参数按 `/` 拆成路径逐级下钻，带 `/` 的扁平键存进去也读不回来。
   *
   * @param array|mixed $langs 语言数组（批量），或单个语言项的值
   * @param string|null $key   单键写入时的键名；`$langs` 为数组时不生效
   * @return void
   */
  public static function add($langs, $key = null)
  {
    if (\is_array($langs)) {
      self::$langs = array_merge(self::$langs, $langs);
    } else {
      self::$langs[$key] = $langs;
    }
  }
  /**
   * 覆盖单个语言项
   *
   * 与 `add($value, $key)` 等价，语义更直观一些；同样建议 `$key` 不带 `/`（原因见 `add()`）。
   *
   * @param string $key   语言项键名
   * @param mixed  $value 语言项内容
   * @return void
   */
  public static function change($key, $value)
  {
    self::$langs[$key] = $value;
  }
  /**
   * 按键（或键路径）取值（内部方法）
   *
   * - 字符串：当作**单个键**直接读取，如 `"kernel"`；
   * - 数组：当作键路径逐级下钻，如 `["common", "symbol", "colon"]`。
   *
   * 无兜底：键不存在时会触发 PHP 警告（`Undefined array key` /
   * `Trying to access array offset on value of type null`）并最终返回 null。
   *
   * @param string|string[] $keys 键名，或已按 `/` 拆好的键路径数组
   * @return mixed 取到的值；键不存在时为 null
   */
  private static function getValue($keys)
  {
    //* all || [ kernel,view_template ]
    if (\is_string($keys)) {
      return self::$langs[$keys];
    } else {
      $value = self::$langs;
      foreach ($keys as $key) {
        $value = $value[$key];
      }
      return $value;
    }
  }
  /**
   * 拼接多个语言片段
   *
   * 每个参数都是一条 `/` 分隔的键路径，分别取值后**按参数顺序拼接成一个字符串**，
   * 适合把「符号 + 文案」「前缀 + 正文」这类多段内容拼起来。
   *
   * ```php
   * DiscuzXLang::connect("common/symbol/colon", "welcome"); // "：欢迎"
   * ```
   *
   * @param string ...$keys 一个或多个键路径
   * @return string 拼接结果；取不到（null）的片段按空串参与拼接
   */
  public static function connect()
  {
    $keys = \func_get_args();
    foreach ($keys as &$keyItem) {
      $keyItem = \explode("/", $keyItem);
      $keyItem = self::getValue($keyItem);
    }
    return implode("", $keys);
  }
  /**
   * 取语言文案（最常用的读取入口）
   *
   * 变参：每个参数是一条 `/` 分隔的键路径。
   * - 只传一个：返回该语言项的值（标量或子数组）；
   * - 传多个：返回按参数顺序排列的值数组。
   *
   * ```php
   * DiscuzXLang::value("kernel/extension_list");          // "扩展列表"
   * DiscuzXLang::value("common/save", "common/cancel");   // ["保存", "取消"]
   * ```
   *
   * @param string ...$keys 一个或多个键路径（形参仅作声明，实现里取 `func_get_args()` 的全部实参）
   * @return mixed|array 单参返回取到的值；多参返回数组
   */
  public static function value($keys)
  {
    //* all | all,save,...
    $keys = func_get_args();
    foreach ($keys as &$keyItem) {
      $keyItem = self::getValue(\explode("/", $keyItem));
    }
    if (\count($keys) === 1) {
      return $keys[0];
    }
    return $keys;
  }
  /**
   * 获取整份语言字典
   *
   * 返回按顶层键分组的原始多维结构（不做任何转换），可用于调试、
   * 或自行下发给前端 / 写入缓存。
   *
   * `DiscuzXApp::__construct()` 在加载语言包后用它把语言表写入
   * `$GLOBALS['_STORE']['__App']['langs']`，供 `GlobalDiscuzXMultipleEncodeMiddleware` 注入前端 `GLANG`。
   *
   * @return array<string, mixed> 语言字典
   */
  public static function all()
  {
    return self::$langs;
  }
}
