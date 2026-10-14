<?php

namespace kernel\Modules\DiscuzX\Foundation;

use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\Path;


/**
 * DiscuzX 语言包（静态注册表）
 *
 * 给 DiscuzX 适配层提供一套 kernel 风格的语言读取能力：语言项按「路径」组织，
 * 取用时以 `/` 分隔逐级下钻，例如 `DiscuzXLang::value("kernel/extension_list")`。
 * 全部为静态方法，进程内共享一份字典，无实例、无缓存。
 *
 * ## 数据来源
 *
 * 语言包文件位于 `Modules/DiscuzX/Langs/{utf-8,gbk}.php`，按站点 `CHARSET` 二选一。
 * 文件内部构造 `$langs` 数组并在末尾自行调用 {@see add()} 完成注册，{@see load()}
 * 只负责把它引进来；文件若写成 `return 数组` 的形式，返回值同样会被注册。
 *
 * ## 加载时机
 *
 * {@see DiscuzXApp::__construct()} 在应用启动时按站点 `CHARSET` 加载 `utf-8` / `gbk`，
 * 因此业务代码直接 {@see value()} 即可，无需自己 {@see load()}。
 *
 * ## 使用方式
 *
 * ```php
 * // 内核自带语言包在 kernel 下；第二个参数为基路径，默认 Path::root()（应用根）
 * DiscuzXLang::load("Modules/DiscuzX/Langs/utf-8", Path::kernel());
 *
 * DiscuzXLang::value("kernel/extension_list");              // 单个文案
 * DiscuzXLang::value("common/save", "common/cancel");       // ["保存", "取消"]
 * DiscuzXLang::connect("common/symbol/colon", "welcome");   // "：欢迎"
 * ```
 *
 * ## 注意事项
 *
 * 1. **没有默认值兜底**：键不存在时读取方法返回 null，不抛异常、也不回退到其它语言；
 * 2. **{@see add()} 是浅合并**：同名顶层键整体覆盖 —— 多个语言文件都含 `common` 子树时，
 *    后加载的那份会顶掉先加载的整棵子树；
 * 3. **键名不要带 `/`**：读取方法会把参数按 `/` 拆成键路径逐级下钻，带 `/` 的扁平键存进去也读不回来。
 */
class DiscuzXLang
{
  /**
   * 语言字典（多维数组，按业务 / 模块分组）
   *
   * 由语言包文件调用 {@see add()} 填充，结构示例：
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
   * 把语言包文件引进来并完成注册，分两步：
   *   1. 解析路径 —— `Path::join($basePath, $filePath)`，未写扩展名时自动补 `.php`；
   *      **存在性判断与实际载入使用同一个解析结果**，文件不存在直接抛 {@see Error}，不会静默跳过；
   *   2. 载入文件 —— 语言包文件末尾自行调用 {@see add()} 完成注册；
   *      若文件 `return` 了数组，返回值也在此注册。
   *
   * 由 {@see DiscuzXApp::__construct()} 在应用启动时调用（内核自带语言包 → `$basePath = Path::kernel()`）。
   *
   * @param string $filePath 语言包路径，**相对于 `$basePath`**（可省略 `.php` 扩展名）。
   *                         `Path::join` 是朴素拼接、不识别绝对路径，传绝对路径会被拼到 `$basePath`
   *                         之后导致找不到文件，请传相对路径、或用 `$basePath` 指定基准目录
   * @param string|null $basePath 基路径，默认 `Path::root()`（应用根）；内核自带语言包位于
   *                              `Path::kernel()/Modules/DiscuzX/Langs/`，需显式传入 `Path::kernel()`
   * @throws Error 解析后的文件不存在时抛出（statusCode = 500、errorCode = `DiscuzXLang:500001`、
   *               errorDetails = 解析后的绝对路径）
   * @return void
   */
  public static function load($filePath, $basePath = null)
  {
    if ($basePath === null) {
      $basePath = DiscuzXPath::root();
    }

    // 未写扩展名时补 .php（与 import() 的处理保持一致）
    $relativePath = $filePath;
    if (pathinfo($relativePath, PATHINFO_EXTENSION) === "") {
      $relativePath .= ".php";
    }
    // 存在性判断与实际载入用同一套路径解析
    $realFilePath = Path::join($basePath, $relativePath);
    if (!file_exists($realFilePath)) {
      throw new Error("编码文件不存在", 500, "DiscuzXLang:500001", $realFilePath);
    }

    // 语言包文件内部自行注册；若文件 return 了数组，在此一并注册
    $langs = import($relativePath, [], $basePath);
    if (is_array($langs)) {
      self::add($langs);
    }
  }
  /**
   * 注册语言项
   *
   * 两种用法：
   * - `add(["common" => [...]])`：批量注册，与已注册内容做**浅合并**（同名顶层键整体覆盖），
   *   此时 `$key` 忽略；
   * - `add("保存", "save")`：写入单个键，`$key` 为键名。
   *
   * 键名**不要带 `/`**：{@see value()} / {@see connect()} 会把参数按 `/` 拆成键路径逐级下钻，
   * 带 `/` 的扁平键存进去也读不回来。
   *
   * @param array|mixed $langs 语言数组（批量注册），或单个语言项的值
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
   * 与 `add($value, $key)` 等价，语义更直观一些；键名同样不要带 `/`，原因见 {@see add()}。
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
   * 这是读取的公共底座，{@see value()} / {@see connect()} 都经它取值；
   * 无默认值兜底，键不存在时返回 null。
   *
   * @param string|string[] $keys 键名，或已按 `/` 拆好的键路径数组
   * @return mixed 取到的值；键不存在时为 null
   */
  private static function getValue($keys)
  {
    //* 字符串按单个键读取，数组按键路径逐级下钻
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
   * 参数为一个或多个「`/` 分隔的键路径」，分别取值后**按参数顺序拼接成一个字符串**，
   * 适合把「符号 + 文案」「前缀 + 正文」这类多段内容拼起来。
   *
   * ```php
   * DiscuzXLang::connect("common/symbol/colon", "welcome"); // "：欢迎"
   * ```
   *
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
   * @param string ...$keys 一个或多个键路径
   * @return mixed|array 单参返回取到的值；多参返回数组
   */
  public static function value($keys)
  {
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
   * 返回按顶层键分组的原始多维结构（不做任何转换），可用于调试，
   * 或自行下发给前端 / 写入缓存。
   *
   * @return array<string, mixed> 语言字典
   */
  public static function all()
  {
    return self::$langs;
  }
}
