<?php

namespace kernel\Foundation\Data;

/**
 * 数据突变器 —— 将输入数据按类型规则进行深度转换。
 *
 * 核心能力：
 * - 单一类型转换（如整份数据转为 int）
 * - 管道链式转换（"string|int|mask:3" 或 ["string","int","mask:3"]），用 | 或数组分隔多步类型，按序处理
 * - 时间戳转换（timestamp 秒级 / timestamp_m 毫秒级）
 * - 日期转换（date 日期 / datetime 日期时间），支持 :format 自定义输出格式
 * - 密文遮盖（mask / mask:4），将字符串中间部分替换为 *，默认遮盖 80%
 * - 字符串清洗（trim / lower / upper / strip_tags / htmlspecialchars）
 * - 数值处理（number 提取数字 / abs 绝对值 / round 四舍五入 / number_format 千分位）
 * - 空值兜底（default:值），null / '' 时使用默认值
 * - JSON 编解码（json / json_decode）
 * - 数组操作（pluck 提取 / implode 拼接）
 * - 编码转换（urlencode / urldecode / base64 / base64_decode）
 * - 字符串截断（truncate:n:suffix）
 * - 关联数组逐键类型映射，支持点号路径（"user.profile.id"）和通配符（"items.*.price"）
 * - 分隔符语法：将字符串按分隔符拆分后逐元素转换（如 "int/," 按逗号拆分转 int）
 * - array<type> 语法：对索引数组逐元素指定类型
 * - 嵌套 Mutator 实例管道处理
 * - callable 自定义转换
 * - completion / removeNotExistRuleKey 控制数据结构的补全与裁剪
 *
 * 使用示例：
 * ```php
 * // 简单类型
 * (new Mutator)->data('123')->int()->convert();                    // 123
 *
 * // 时间戳
 * (new Mutator)->data('2024-01-01')->timestamp()->convert();      // 1704067200
 * (new Mutator)->data('2024-01-01')->timestamp_m()->convert();    // 1704067200000
 *
 * // 日期 & 日期时间
 * (new Mutator)->data(1704067200)->date()->convert();             // "2024-01-01"
 * (new Mutator)->data('2024-01-01')->datetime()->convert();       // "2024-01-01 00:00:00"
 * (new Mutator)->data('2024-01-01 12:30:00')->date()->convert();  // "2024-01-01"
 *
 * // 自定义格式
 * (new Mutator)->data('2024-01-01')
 *     ->convert('date:Y年m月d日');                                  // "2024年01月01日"
 *
 * // 密文遮盖
 * (new Mutator)->data('13288364266')->mask()->convert();           // "1*********6"
 * (new Mutator)->data('13288364266')
 *     ->convert('mask:4');                                          // "132****4266"
 *
 * // 管道链式转换（字符串形式和数组形式等价）
 * (new Mutator)->data('13288364266')->convert('string|mask:3');     // "132****4266"
 * (new Mutator)->data('13288364266')
 *     ->convert(['string', 'mask:3']);                               // "132****4266"
 * (new Mutator)->data('123.99')->convert('float|int|string');        // "123"
 * (new Mutator)->data('123.99')->convert(['float', 'int', 'string']); // "123"
 *
 * // 数组映射
 * (new Mutator(['name' => 'string', 'age' => 'int']))
 *     ->data(['name' => 'admin', 'age' => '18'])
 *     ->convert();
 * // => ['name' => 'admin', 'age' => 18]
 *
 * // 点号路径
 * (new Mutator(['user.profile.id' => 'int']))
 *     ->data(['user' => ['profile' => ['id' => '9910']]])
 *     ->convert();
 * // => ['user' => ['profile' => ['id' => 9910]]]
 *
 * // 通配符
 * (new Mutator(['items.*.price' => 'double']))
 *     ->data(['items' => [['price' => '9.99'], ['price' => '12.50']]])
 *     ->convert();
 * // => ['items' => [['price' => 9.99], ['price' => 12.50]]]
 * ```
 */
class Mutator
{
    /**
     * settype() 允许的类型名白名单。
     *
     * @var string[]
     */
    private const ALLOWED_TYPES = [
        'boolean', 'bool', 'integer', 'int', 'float',
        'double', 'string', 'array', 'object', 'null', 'any',
    ];

    /**
     * 需整体消费的类型名——不做数组子元素遍历，而是把整个值传给处理器。
     * 例如 json（编码整个数组）、implode（拼接整个数组）、pluck（从数组中提取键）。
     *
     * @var string[]
     */
    private const WHOLE_VALUE_TYPES = [
        'json', 'json_decode', 'implode', 'pluck',
    ];

    /**
     * 转换规则：字符串表示统一类型，数组表示逐键映射。
     *
     * @var string|array|null
     */
    private $types = null;

    /**
     * 待转换的原始数据。
     *
     * @var mixed
     */
    private $data = null;

    /**
     * 是否补全 data 中不存在但在 rules 中定义了类型的键（补为 null）。
     *
     * @var bool
     */
    private $completion = false;

    /**
     * 是否剔除 data 中存在但 rules 未定义的键。
     * 与 completion 同时为 true 时：规则外多余键被剔除，规则内缺失键补全 null。
     *
     * @var bool
     */
    private $removeNotExistRuleKey = false;

    /**
     * 构建数据突变器实例。
     *
     * @param array|string|null $types 转换规则。
     *   - null：后续通过 fluent 方法或 convert() 入参指定。
     *   - string：整份数据统一转为该类型，如 "int"、"string"。支持 | 管道链式，如 "string|int|mask:3"。
     *     支持的类型字符串：int, string, float, double, bool, array, object, null, any,
     *     timestamp, timestamp_m, date, datetime, mask, trim, lower, upper, number,
     *     strip_tags, htmlspecialchars, json, json_decode, abs, default:值,
     *     round:n, number_format:n, pluck:key, implode:sep,
     *     urlencode, urldecode, base64, base64_decode, truncate:n:suffix
     *   - string with "/"：拆分后逐元素转换，如 "int/," 按逗号拆分后每部分转 int。
     *   - array：关联数组为 field => type 映射，键支持点号语法（"a.b.c"）和通配符（"a.*.c"）；
     *     数值键表示保留该字段但不做转换（如 ["username","profile"] 保留 username 和 profile 原样）；
     *     "..." 展开标记仅对显式定义字段做转换，其余原始字段全部保留（如 ["...", "username" => "string"]）；
     *     支持排除字段如 "...|password,secret"（保留所有字段但剔除 password 和 secret）。
     *     索引数组则对 data 的每个元素应用同一套规则。
     *   - value 可以是 Mutator 实例（管道处理）或 callable（自定义函数）。
     * @param bool $completion 是否补全 data 中不存在但规则中定义了类型的键（补为 null）。
     *   例如 rules = ['username' => 'string', 'age' => 'int']，data = ['username' => 'admin']
     *   → 返回 ['username' => 'admin', 'age' => null]。
     * @param bool $removeNotExistRuleKey 是否剔除 data 中存在但规则未定义的键。
     *   例如 rules = ['username' => 'string']，data = ['username' => 'admin', 'age' => 8]
     *   → 返回 ['username' => 'admin']。
     */
    public function __construct($types = null, bool $completion = false, bool $removeNotExistRuleKey = false)
    {
        $this->types = $types;
        $this->completion = $completion;
        $this->removeNotExistRuleKey = $removeNotExistRuleKey;
    }

    // ==================== Fluent setters ====================

    /**
     * 传入待转换的原始数据。
     *
     * @param mixed $data 任意类型的数据，通常为数组或标量。
     * @return $this
     */
    public function data($data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 设定目标类型为 string。
     *
     * @return $this
     */
    public function string(): self  { $this->types = 'string';  return $this; }

    /**
     * 设定目标类型为 integer。
     *
     * @return $this
     */
    public function int(): self     { $this->types = 'integer'; return $this; }

    /**
     * 设定目标类型为 array。
     *
     * @return $this
     */
    public function array(): self  { $this->types = 'array';   return $this; }

    /**
     * 设定目标类型为 object（建议传入数组数据）。
     *
     * @return $this
     */
    public function object(): self  { $this->types = 'object';  return $this; }

    /**
     * 设定目标类型为 double（浮点数）。
     *
     * @return $this
     */
    public function double(): self  { $this->types = 'double';  return $this; }

    /**
     * 设定目标类型为 bool。
     *
     * @return $this
     */
    public function bool(): self        { $this->types = 'bool';        return $this; }

    /**
     * 设定目标类型为 timestamp（Unix 时间戳，秒）。
     *
     * 支持日期字符串、DateTime 对象、数值等输入。
     *
     * @return $this
     */
    public function timestamp(): self   { $this->types = 'timestamp';   return $this; }

    /**
     * 设定目标类型为 timestamp_m（Unix 时间戳，毫秒）。
     *
     * @return $this
     */
    public function timestamp_m(): self { $this->types = 'timestamp_m'; return $this; }

    /**
     * 设定目标类型为 date（日期字符串，默认格式 Y-m-d）。
     *
     * 支持 DateTime、时间戳、日期字符串等输入。
     * 可通过 date:format 语法指定自定义格式，如 "date:Y年m月d日"。
     *
     * @return $this
     */
    public function date(): self     { $this->types = 'date';     return $this; }

    /**
     * 设定目标类型为 datetime（日期时间字符串，默认格式 Y-m-d H:i:s）。
     *
     * 支持 DateTime、时间戳、日期字符串等输入。
     * 可通过 datetime:format 语法指定自定义格式，如 "datetime:Y年m月d日 H:i"。
     *
     * @return $this
     */
    public function datetime(): self { $this->types = 'datetime'; return $this; }

    /**
     * 设定目标类型为 mask（密文遮盖），将字符串中间部分替换为 *。
     *
     * 默认遮盖字符串整体长度的 80%（向上取整）。
     * 可通过 mask:count 语法指定精确遮盖位数，如 "mask:4"。
     *
     * @return $this
     */
    public function mask(): self     { $this->types = 'mask';     return $this; }

    /**
     * 设定目标类型为 trim（去除首尾空白字符）。
     *
     * @return $this
     */
    public function trim(): self     { $this->types = 'trim';     return $this; }

    /**
     * 设定目标类型为 lower（转为小写）。
     *
     * @return $this
     */
    public function lower(): self    { $this->types = 'lower';    return $this; }

    /**
     * 设定目标类型为 upper（转为大写）。
     *
     * @return $this
     */
    public function upper(): self    { $this->types = 'upper';    return $this; }

    /**
     * 设定目标类型为 number（提取数字字符，保留 0-9、.、-）。
     *
     * @return $this
     */
    public function number(): self   { $this->types = 'number';   return $this; }

    /**
     * 设定目标类型为 json（数组/对象 → JSON 字符串）。
     *
     * @return $this
     */
    public function json(): self     { $this->types = 'json';     return $this; }

    /**
     * 设定目标类型为 json_decode（JSON 字符串 → 关联数组）。
     *
     * @return $this
     */
    public function json_decode(): self { $this->types = 'json_decode'; return $this; }

    /**
     * 设定目标类型为 abs（取绝对值）。
     *
     * @return $this
     */
    public function abs(): self      { $this->types = 'abs';      return $this; }

    /**
     * 设定目标类型为 strip_tags（去除 HTML/PHP 标签）。
     *
     * @return $this
     */
    public function strip_tags(): self { $this->types = 'strip_tags'; return $this; }

    /**
     * 设定目标类型为 implode（数组拼接为字符串，默认逗号分隔）。
     *
     * @return $this
     */
    public function implode(): self { $this->types = 'implode'; return $this; }

    /**
     * 设定目标类型为 default（空值兜底）。
     *
     * @return $this
     */
    public function default(): self { $this->types = 'default'; return $this; }

    /**
     * 设定目标类型为 pluck（提取数组列表中指定键的值）。
     *
     * @return $this
     */
    public function pluck(): self { $this->types = 'pluck'; return $this; }

    /**
     * 设定目标类型为 urlencode（URL 编码）。
     *
     * @return $this
     */
    public function urlencode(): self { $this->types = 'urlencode'; return $this; }

    /**
     * 设定目标类型为 urldecode（URL 解码）。
     *
     * @return $this
     */
    public function urldecode(): self { $this->types = 'urldecode'; return $this; }

    /**
     * 设定目标类型为 htmlspecialchars（HTML 实体转义）。
     *
     * @return $this
     */
    public function htmlspecialchars(): self { $this->types = 'htmlspecialchars'; return $this; }

    /**
     * 设定目标类型为 round（四舍五入）。
     *
     * @return $this
     */
    public function round(): self { $this->types = 'round'; return $this; }

    /**
     * 设定目标类型为 number_format（千分位格式化）。
     *
     * @return $this
     */
    public function number_format(): self { $this->types = 'number_format'; return $this; }

    /**
     * 设定目标类型为 base64（Base64 编码）。
     *
     * @return $this
     */
    public function base64(): self { $this->types = 'base64'; return $this; }

    /**
     * 设定目标类型为 base64_decode（Base64 解码）。
     *
     * @return $this
     */
    public function base64_decode(): self { $this->types = 'base64_decode'; return $this; }

    /**
     * 设定目标类型为 truncate（截断字符串）。
     *
     * @return $this
     */
    public function truncate(): self { $this->types = 'truncate'; return $this; }

    // ==================== Main API ====================

    /**
     * 执行数据转换，返回转换后的数据。
     *
     * 类型规则支持以下形式：
     * | 规则形式 | 示例 | 说明 |
     * |---------|------|------|
     * | 字符串类型 | "int" | 整份数据转为该类型 |
     * | 管道链式(字符串) | "string\|int\|mask:3" | 用 \| 分隔多步类型，按序串行处理 |
     * | 管道链式(数组) | ["string","int","mask:3"] | 等效于字符串管道形式 |
     * | 时间戳 | "timestamp" | 将日期字符串/DateTime 转为 Unix 秒级时间戳 |
     * | 毫秒时间戳 | "timestamp_m" | 将日期字符串/DateTime 转为 Unix 毫秒级时间戳 |
     * | 日期 | "date" | 将 DateTime/时间戳/日期字符串 转为日期字符串（Y-m-d） |
     * | 日期时间 | "datetime" | 将 DateTime/时间戳/日期字符串 转为日期时间字符串（Y-m-d H:i:s） |
     * | 自定义格式 | "date:Y年m月d日" | date/datetime 支持 :format 后缀自定义输出格式 |
     * | 密文遮盖 | "mask" | 字符串中间部分替换为 *，默认遮盖 80% |
     * | 指定遮盖位数 | "mask:4" | 精确遮盖 4 位，如 13288364266 → 132****4266 |
     * | 去除空白 | "trim" | 去除首尾空白字符 |
     * | 转小写 | "lower" | 转为小写 |
     * | 转大写 | "upper" | 转为大写 |
     * | 提取数字 | "number" | 提取数值字符（保留 0-9 . -），如 "¥99.99元" → "99.99" |
     * | 去除标签 | "strip_tags" | 去除 HTML/PHP 标签；strip_tags:a,b 保留指定标签 |
     * | HTML 转义 | "htmlspecialchars" | HTML 实体转义（防 XSS） |
     * | JSON 编码 | "json" | 数组/对象 → JSON 字符串 |
     * | JSON 解码 | "json_decode" | JSON 字符串 → 关联数组 |
     * | 绝对值 | "abs" | 取绝对值 |
     * | 空值兜底 | "default:匿名" | null / '' 时使用指定默认值 |
     * | 四舍五入 | "round:2" | 保留指定小数位；round 默认 0 位 |
     * | 千分位 | "number_format" | 千分位格式化（默认 2 位小数）；number_format:0 无小数 |
     * | 数组提取 | "pluck:id" | 从数组列表中提取每项的指定键值 |
     * | 数组拼接 | "implode" | 数组拼接为字符串（默认逗号分隔）；implode:\| 指定分隔符 |
     * | URL 编码 | "urlencode" | URL 编码 |
     * | URL 解码 | "urldecode" | URL 解码 |
     * | Base64 编码 | "base64" | Base64 编码 |
     * | Base64 解码 | "base64_decode" | Base64 解码 |
     * | 字符串截断 | "truncate:100" | 截断到指定字符数，超出加 "..."；truncate:50:→ 自定义后缀 |
     * | 分隔符语法 | "int/," | 按分隔符拆分字符串后逐元素转换 |
     * | array<> 语法 | "array<int>" | 索引数组逐元素指定类型 |
     * | 关联数组 | ["name" => "string"] | 逐键映射；键支持点号路径和通配符 |
     * | 数值键保留 | ["username","profile"] | 保留字段但不做类型转换，原样透传 |
     * | 展开标记 | ["...", "name" => "string"] | 保留所有原始字段，仅对显式定义做转换；可排除字段（"...\|password,secret"） |
     * | Mutator 实例 | new Mutator("int") | 通过管道处理（索引数组自动遍历） |
     * | callable | fn($v) => intval($v) | 自定义转换函数 |
     *
     * @param array|string|null $types 转换规则。传入 null 则使用构造时或 fluent 方法设定的规则。
     * @return mixed 转换后的数据。以下情况返回 false：
     *   - $this->data 为 null
     *   - types 未指定（构造时、fluent 方法、convert() 参数均未传）
     *   - types 为数组但 data 不是数组
     */
    public function convert($types = null)
    {
        if ($this->data === null) {
            return false;
        }

        $types = $types ?? $this->types;
        if ($types === null) {
            return false;
        }

        // 字符串类型：整份数据统一转换
        if (is_string($types)) {
            return $this->convertByStringType($types);
        }

        // 序列数组视为管道链式（如 ["string","int","mask:3"]），与字符串 "string|int|mask:3" 等价
        // 仅当 data 不是数组时启用管道模式；data 为数组时序列数组作为规则数组处理（数值键=透传字段）
        if (is_array($types) && !Arr::isAssoc($types) && !is_array($this->data)) {
            $steps = $this->getPipelineSteps($types);
            if ($steps !== null) {
                return $this->applyPipelineSteps($this->data, $steps);
            }
        }

        // 数组规则需要数组数据
        if (!is_array($types) || !is_array($this->data)) {
            return false;
        }

        // 索引数组：每个元素应用同一套规则
        if (!Arr::isAssoc($this->data)) {
            return $this->convertIndexedData($types);
        }

        // 关联数组：逐键处理规则（含点号语法）
        return $this->convertAssocData($types);
    }

    // ==================== Dispatchers ====================

    /**
     * 将整份数据按照单一字符串类型转换。
     *
     * - 若 data 为索引数组 → 递归对每个元素执行同类型转换
     * - 否则 → 委托 convertValue() 处理（含管道链式、分隔符等高级语法）
     *
     * @param string $type 目标类型名称
     * @return mixed
     */
    private function convertByStringType(string $type)
    {
        // 整体消费类型（json/implode/pluck）不遍历子元素，把整个值传给处理器
        $baseType = strpos($type, ':') !== false ? strtok($type, ':') : $type;
        $isWholeValue = in_array($baseType, self::WHOLE_VALUE_TYPES, true);

        if (!$isWholeValue && is_array($this->data) && !Arr::isAssoc($this->data)) {
            $result = [];
            foreach ($this->data as $value) {
                $result[] = $this->createChild($type)->data($value)->convert();
            }
            return $result;
        }

        return $this->convertValue($this->data, $type);
    }

    /**
     * 对索引数据的每个元素递归应用同一套规则。
     *
     * @param array $types 规则数组
     * @return array
     */
    private function convertIndexedData(array $types): array
    {
        $result = [];
        foreach ($this->data as $value) {
            $result[] = $this->createChild($types)->data($value)->convert();
        }
        return $result;
    }

    /**
     * 处理关联数组规则。
     *
     * 先将规则按键名拆分为扁平规则（不含 "."）和点号规则（含 "."），
     * 分别处理后再合并结果，确保点号规则中的中间路径不影响扁平规则的初始化。
     *
     * @param array $types 规则数组
     * @return array
     */
    private function convertAssocData(array $types): array
    {
        // 检测 "..." 展开标记：保留所有原始字段，仅对显式定义的字段做转换
        // 支持 "..."、"...|field1,field2"（排除指定字段）
        $passthrough = false;
        $excludedFields = [];
        foreach ($types as $key => $type) {
            if (is_numeric($key) && is_string($type) && strpos($type, '...') === 0) {
                $passthrough = true;
                if (strlen($type) > 3 && $type[3] === '|') {
                    $excludedFields = array_map('trim', explode(',', substr($type, 4)));
                }
                unset($types[$key]);
            }
        }

        $flatRules = [];
        $dotRules = [];
        foreach ($types as $key => $type) {
            if (is_string($key) && strpos($key, '.') !== false) {
                $dotRules[$key] = $type;
            } else {
                $flatRules[$key] = $type;
            }
        }

        $result = ($passthrough || !$this->removeNotExistRuleKey) ? $this->data : [];

        foreach ($flatRules as $key => $type) {
            $this->applyFlatRule($result, $key, $type);
        }

        foreach ($dotRules as $key => $type) {
            $this->applyDotRule($result, $key, $type);
        }

        // 剔除 "..." 右侧指定的排除字段
        foreach ($excludedFields as $field) {
            if ($field !== '' && array_key_exists($field, $result)) {
                unset($result[$field]);
            }
        }

        return $result;
    }

    // ==================== Flat rule application ====================

    /**
     * 应用一条扁平规则到结果数组。
     *
     * 规则类型可以是：
     * - Mutator 实例 → 委托给 applyMutatorRule()
     * - callable → 直接用回调转换
     * - string → 通过 convertValue() 进行类型转换
     *
     * 若规则 key 为数值（如 $types = ['username']），则 key 被解释为字段名，
     * 该字段原样透传不做任何转换。
     *
     * @param array &$result 结果数组（引用传递，原地修改）
     * @param int|string $key 规则键名
     * @param string|self|callable $type 规则类型
     */
    private function applyFlatRule(array &$result, $key, $type): void
    {
        // 数值键：值本身就是字段名，无类型定义 → 原样透传，不做任何转换
        $keyStr = $key;
        if (is_numeric($key)) {
            $keyStr = (string)$type;
            if (array_key_exists($keyStr, $this->data)) {
                $result[$keyStr] = $this->data[$keyStr];
            } elseif ($this->completion) {
                $result[$keyStr] = null;
            }
            return;
        }

        if ($type instanceof self) {
            $this->applyMutatorRule($result, (string)$keyStr, $type);
            return;
        }

        if (!array_key_exists($keyStr, $this->data)) {
            if ($this->completion) {
                $result[$keyStr] = null;
            }
            return;
        }

        // 字符串类型或数组管道优先于 callable 检查，避免 "date" 等被误判为回调
        if (is_string($type) || (is_array($type) && !Arr::isAssoc($type))) {
            $result[$keyStr] = $this->convertValue($this->data[$keyStr], $type);
            return;
        }

        // 关联数组当作子规则（如 "tags" => ["id" => "string"]）
        if (is_array($type)) {
            $result[$keyStr] = $this->createChild($type)->data($this->data[$keyStr])->convert();
            return;
        }

        if (isSafeCallable($type)) {
            $result[$keyStr] = $type($this->data[$keyStr]);
            return;
        }

        $result[$keyStr] = $this->convertValue($this->data[$keyStr], (string)$type);
    }

    /**
     * 将 Mutator 实例规则应用到结果数组的指定键。
     *
     * 若该键对应的 data 值为索引数组，则对每个元素单独调用 mutator；
     * 否则将整个值传入 mutator 管道。
     *
     * @param array &$result 结果数组（引用传递）
     * @param string $key 字段名
     * @param self $mutator 子 Mutator 实例
     */
    private function applyMutatorRule(array &$result, string $key, self $mutator): void
    {
        if (!array_key_exists($key, $this->data)) {
            if ($this->completion) {
                $result[$key] = null;
            }
            return;
        }

        $value = $this->data[$key];

        if (is_array($value) && !Arr::isAssoc($value)) {
            // 索引数组：对每个元素应用 mutator
            $result[$key] = [];
            foreach ($value as $dataKey => $dataValue) {
                $result[$key][$dataKey] = $mutator->data($dataValue)->convert();
            }
        } else {
            $result[$key] = $mutator->data($value)->convert();
        }
    }

    // ==================== Dot notation rule application ====================

    /**
     * 应用一条点号语法规则。
     *
     * 流程：
     * 1. 将 "user.profile.id" 解析为路径 ['user', 'profile', 'id']
     * 2. 若路径含 "*" → 委托给 applyWildcardRule()
     * 3. 从 $this->data 中按路径取值
     * 4. 若路径不存在且 completion=false → 跳过
     * 5. 转换后通过 setNestedValue() 按原路径写回 $result
     *
     * @param array &$result 结果数组（引用传递）
     * @param string $ruleKey 点号路径规则键，如 "user.profile.id"
     * @param string|self|callable $type 转换规则
     */
    private function applyDotRule(array &$result, string $ruleKey, $type): void
    {
        $path = $this->parsePath($ruleKey);
        $wildcardIndex = array_search('*', $path, true);

        if ($wildcardIndex !== false) {
            $this->applyWildcardRule($result, $path, $wildcardIndex, $type);
            return;
        }

        $current = $this->getNestedValue($this->data, $path);

        if ($current === null && !$this->completion) {
            return;
        }

        $converted = $this->resolveConvert($current, $type);
        $this->setNestedValue($result, $path, $converted);
    }

    /**
     * 应用含通配符 "*" 的点号规则。
     *
     * "*" 表示遍历索引数组的每个元素。例如：
     * - "items.*.price" → 对 items 的每个元素取 price 字段并转换
     * - "items.*" → 对 items 的每个元素整体转换
     *
     * 处理逻辑：
     * 1. 根据 * 的位置将路径分为 prefixPath（* 之前）和 suffixPath（* 之后）
     * 2. 从 data 中按 prefixPath 取出目标数组
     * 3. 若目标不是索引数组 → 直接返回（不处理）
     * 4. 遍历目标数组的每个元素，对 suffixPath 指向的嵌套值执行转换
     * 5. 组装结果后按 prefixPath 写回 $result
     *
     * @param array        &$result       结果数组（引用传递）
     * @param string[]     $path          完整路径数组（含 "*"），如 ['items', '*', 'price']
     * @param int          $wildcardIndex  "*" 在 $path 中的索引位置
     * @param string|self|callable $type  转换规则
     */
    private function applyWildcardRule(array &$result, array $path, int $wildcardIndex, $type): void
    {
        $prefixPath = array_slice($path, 0, $wildcardIndex);
        $suffixPath = array_slice($path, $wildcardIndex + 1);

        $array = $this->getNestedValue($this->data, $prefixPath);

        // 非索引数组不处理通配符
        if (!is_array($array) || Arr::isAssoc($array)) {
            return;
        }

        $convertedArray = [];
        foreach ($array as $index => $item) {
            if (empty($suffixPath)) {
                // "items.*" — 直接转换每个元素本身
                $convertedArray[$index] = $this->resolveConvert($item, $type);
            } else {
                // "items.*.price" — 转换每个元素内的嵌套字段
                $nested = $this->getNestedValue($item, $suffixPath);
                $exists = $nested !== null;

                $convertedArray[$index] = is_array($item) ? $item : [];
                if ($exists || $this->completion) {
                    $this->setNestedValue(
                        $convertedArray[$index],
                        $suffixPath,
                        $this->resolveConvert($exists ? $nested : null, $type)
                    );
                }
            }
        }

        $this->setNestedValue($result, $prefixPath, $convertedArray);
    }

    // ==================== Value conversion ====================

    /**
     * 将单个值按字符串类型规则转换。
     *
     * 根据值的形态走不同分支：
     * - 类型为序列数组或含 "|" 的字符串 → 管道链式处理，按序串行转换
     * - 值为关联数组 → 递归创建子 Mutator 处理
     * - 值为索引数组 → 调用 convertIndexedValue()（支持 array<type> 语法）
     * - 值为标量且类型含 "/" → 调用 convertWithSeparator()（分隔符语法）
     * - 值为标量 → 直接 setType()
     *
     * @param mixed        $value 待转换的值
     * @param string|array $type  类型规则（字符串或序列数组）
     * @return mixed
     */
    private function convertValue($value, $type)
    {
        // 管道链式（字符串 "step1|step2" 或数组 ["step1","step2"]）
        $steps = $this->getPipelineSteps($type);
        if ($steps !== null) {
            return $this->applyPipelineSteps($value, $steps);
        }

        // 以下 $type 保证为字符串，统一走字符串分支
        if (is_array($value)) {
            // 整体消费类型（json/implode/pluck/json_decode）直接处理整个值，包括空数组
            if (is_string($type)) {
                $baseType = strpos($type, ':') !== false ? strtok($type, ':') : $type;
                if (in_array($baseType, self::WHOLE_VALUE_TYPES, true)) {
                    return $this->setType($value, $type);
                }
            }

            if (empty($value)) {
                return [];
            }

            if (Arr::isAssoc($value)) {
                return $this->createChild($type)->data($value)->convert();
            }

            return $this->convertIndexedValue($value, $type);
        }

        // 标量值 + 分隔符语法："int/,"
        if (strpos($type, '/') !== false) {
            return $this->convertWithSeparator($value, $type);
        }

        return $this->setType($value, $type);
    }

    /**
     * 判断 $type 是否为管道类型，若是则返回步骤数组，否则返回 null。
     *
     * @param string|array $type 类型规则
     * @return string[]|null 步骤数组，非管道类型返回 null
     */
    private function getPipelineSteps($type): ?array
    {
        // 字符串管道："string|int|mask:3"
        if (is_string($type) && strpos($type, '|') !== false) {
            $parts = explode('|', $type);
            $steps = [];
            $i = 0;
            $len = count($parts);
            while ($i < $len) {
                if ($parts[$i] === '') {
                    $i++;
                    continue;
                }
                $step = $parts[$i];
                // 若步骤以 ":" 结尾，下一个 "|" 部分是参数值（如 implode:|）
                while (substr($step, -1) === ':' && $i + 1 < $len) {
                    $i++;
                    $step .= '|' . $parts[$i];
                }
                $steps[] = $step;
                $i++;
            }
            return count($steps) > 1 ? $steps : null;
        }
        // 数组管道：["string","int","mask:3"]
        if (is_array($type) && !Arr::isAssoc($type)) {
            return $type;
        }
        return null;
    }

    /**
     * 按步骤数组将值串行转换。
     *
     * 每一步的输出作为下一步的输入，类型语法与单步完全一致。
     * 适用于字符串管道 "step1|step2" 和数组管道 ["step1","step2"]。
     *
     * @param mixed    $value 待转换的初始值
     * @param string[] $steps 步骤数组（每个元素为单步类型字符串）
     * @return mixed
     */
    private function applyPipelineSteps($value, array $steps)
    {
        $result = $value;
        foreach ($steps as $step) {
            if ($step === '') {
                continue;
            }
            $result = $this->convertValue($result, $step);
        }
        return $result;
    }

    /**
     * 转换索引数组的每个元素。
     *
     * 支持两种模式：
     * - array<type>：如 "array<int>" 指定每个元素的目标类型
     * - 其他字符串：对每个元素调用 auto() 自动识别类型
     *
     * @param array  $value 索引数组
     * @param string $type  类型规则
     * @return array
     */
    private function convertIndexedValue(array $value, string $type): array
    {
        $result = $value;

        if (preg_match('/^array<(\w+)>$/', $type, $matches)) {
            $elementType = $matches[1];
            foreach ($result as &$item) {
                $item = $this->setType($item, $elementType);
            }
        } else {
            foreach ($result as &$item) {
                $item = $this->auto($item);
            }
        }
        unset($item);

        return $result;
    }

    /**
     * 按分隔符拆分字符串后逐元素转换。
     *
     * 语法："元素类型/分隔符"，例如：
     * - "int/,"  → 按逗号拆分 "1,2,3"，得到 [1, 2, 3]
     * - "string/|" → 按竖线拆分 "a|b|c"，得到 ["a", "b", "c"]
     * - "int/"   → 分隔符缺省时默认使用逗号
     *
     * @param mixed  $value 待拆分的值（非字符串会转为字符串）
     * @param string $type  类型/分隔符规则
     * @return array
     */
    private function convertWithSeparator($value, string $type): array
    {
        list($elementType, $separator) = explode('/', $type) + [null, ''];
        $separator = $separator ?: ',';

        $result = explode($separator, is_string($value) ? $value : (string)$value);
        foreach ($result as &$item) {
            $item = $this->setType($item, $elementType);
        }
        unset($item);

        return $result;
    }

    // ==================== Type coercion ====================

    /**
     * 自动识别值类型并转换。
     *
     * 规则：
     * - null → 原样返回
     * - 数值字符串含 "." → float
     * - 数值字符串不含 "." → int
     * - 其他 → 原样返回（不处理 bool、array、object、string 等非数值类型）
     *
     * @param mixed $target 待转换的值
     * @return mixed
     */
    private function auto($target)
    {
        if ($target === null) {
            return $target;
        }

        if (is_numeric($target)) {
            return strpos((string)$target, '.') === false
                ? (int)$target
                : (float)$target;
        }

        return $target;
    }

    /**
     * 将值强制转换为指定类型。
     *
     * 特殊类型分发：
     * - "any"              → auto() 自动识别
     * - "timestamp"        → toTimestamp()（Unix 秒）
     * - "timestamp_m"      → toTimestampMs()（Unix 毫秒）
     * - "date"             → toDate()（日期字符串，默认 Y-m-d）
     * - "datetime"         → toDatetime()（日期时间字符串，默认 Y-m-d H:i:s）
     * - "date:format"      → toDate() 自定义格式
     * - "datetime:format"  → toDatetime() 自定义格式
     * - "mask"             → toMask()（密文遮盖，默认 80%）
     * - "mask:count"       → toMask() 指定遮盖位数
     * - 无效类型名（不在 ALLOWED_TYPES 中）静默返回原值
     *
     * @param mixed  $target 待转换的值
     * @param string $type   目标类型名
     * @return mixed
     */
    private function setType($target, string $type)
    {
        if ($type === 'any') {
            return $this->auto($target);
        }

        if ($type === 'timestamp') {
            return $this->toTimestamp($target);
        }

        if ($type === 'timestamp_m') {
            return $this->toTimestampMs($target);
        }

        // date / datetime，支持 :format 后缀
        if ($type === 'date' || strpos($type, 'date:') === 0) {
            return $this->toDate($target, $type);
        }

        if ($type === 'datetime' || strpos($type, 'datetime:') === 0) {
            return $this->toDatetime($target, $type);
        }

        // mask，支持 :count 后缀
        if ($type === 'mask' || strpos($type, 'mask:') === 0) {
            return $this->toMask($target, $type);
        }

        // trim
        if ($type === 'trim') {
            return $this->applyTrim($target);
        }

        // lower
        if ($type === 'lower') {
            return $this->applyCase($target, 'lower');
        }

        // upper
        if ($type === 'upper') {
            return $this->applyCase($target, 'upper');
        }

        // number（提取数值字符）
        if ($type === 'number') {
            return $this->applyNumber($target);
        }

        // json（encode）/ json_decode
        if ($type === 'json') {
            return $this->applyJsonEncode($target);
        }

        if ($type === 'json_decode') {
            return $this->applyJsonDecode($target);
        }

        // abs
        if ($type === 'abs') {
            return $this->applyAbs($target);
        }

        // urlencode / urldecode
        if ($type === 'urlencode') {
            return $this->applyUrlEncode($target);
        }

        if ($type === 'urldecode') {
            return $this->applyUrlDecode($target);
        }

        // htmlspecialchars
        if ($type === 'htmlspecialchars') {
            return $this->applyHtmlspecialchars($target);
        }

        // base64 / base64_decode
        if ($type === 'base64') {
            return $this->applyBase64Encode($target);
        }

        if ($type === 'base64_decode') {
            return $this->applyBase64Decode($target);
        }

        // default:value（空值兜底）
        if ($type === 'default' || strpos($type, 'default:') === 0) {
            return $this->applyDefault($target, $type);
        }

        // strip_tags / strip_tags:a,b
        if ($type === 'strip_tags' || strpos($type, 'strip_tags:') === 0) {
            return $this->applyStripTags($target, $type);
        }

        // implode / implode:sep
        if ($type === 'implode' || strpos($type, 'implode:') === 0) {
            return $this->applyImplode($target, $type);
        }

        // pluck:key
        if ($type === 'pluck' || strpos($type, 'pluck:') === 0) {
            return $this->applyPluck($target, $type);
        }

        // round / round:n
        if ($type === 'round' || strpos($type, 'round:') === 0) {
            return $this->applyRound($target, $type);
        }

        // number_format / number_format:n
        if ($type === 'number_format' || strpos($type, 'number_format:') === 0) {
            return $this->applyNumberFormat($target, $type);
        }

        // truncate / truncate:n / truncate:n:suffix
        if ($type === 'truncate' || strpos($type, 'truncate:') === 0) {
            return $this->applyTruncate($target, $type);
        }

        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return $target;
        }

        settype($target, $type);
        return $target;
    }

    /**
     * 将值转为 Unix 时间戳（秒）。
     *
     * 转换规则：
     * - int → 原样返回（视为已是秒级时间戳）
     * - 数值字符串 → 转 int
     * - DateTime / DateTimeInterface 对象 → getTimestamp()
     * - 日期字符串 → strtotime() 解析，失败返回 0
     * - 其他类型 → 返回 0
     *
     * @param mixed $target
     * @return int
     */
    private function toTimestamp($target): int
    {
        if (is_int($target)) {
            return $target;
        }

        if (is_numeric($target)) {
            return (int)$target;
        }

        if ($target instanceof \DateTimeInterface) {
            return $target->getTimestamp();
        }

        if (is_string($target)) {
            $ts = strtotime($target);
            return $ts !== false ? $ts : 0;
        }

        return 0;
    }

    /**
     * 将值转为 Unix 时间戳（毫秒）。
     *
     * 转换规则：
     * - int 且值 > 1e12（约 2001 年后毫秒范围） → 视为已是毫秒，原样返回
     * - int 且值较小 → 视为秒，乘以 1000
     * - 数值字符串 → 同样依据大小决定是否乘 1000
     * - DateTime / DateTimeInterface 对象 → getTimestamp() * 1000
     * - 日期字符串 → strtotime() * 1000，失败返回 0
     * - 其他类型 → 返回 0
     *
     * @param mixed $target
     * @return int
     */
    private function toTimestampMs($target): int
    {
        if (is_int($target)) {
            return $target > 1000000000000 ? $target : $target * 1000;
        }

        if (is_numeric($target)) {
            $val = (float)$target;
            return (int)($val > 1000000000000 ? $val : $val * 1000);
        }

        if ($target instanceof \DateTimeInterface) {
            return $target->getTimestamp() * 1000;
        }

        if (is_string($target)) {
            $ts = strtotime($target);
            return $ts !== false ? $ts * 1000 : 0;
        }

        return 0;
    }

    /**
     * 将值转为日期字符串（默认 Y-m-d）。
     *
     * 可通过 type 参数携带自定义格式（如 "date:Y年m月d日"），
     * 若 type 不含 ":" 则使用默认格式。
     *
     * 转换规则（与 Laravel asDate 语义一致）：
     * - int / 数值字符串 → 视为时间戳，用 date() 格式化
     * - DateTimeInterface    → $dt->format()
     * - 日期字符串           → strtotime() 解析后格式化，失败返回空字符串
     * - 其他类型             → 返回空字符串
     *
     * @param mixed  $target 待转换的值
     * @param string $type   完整的类型名（如 "date" 或 "date:Y-m-d"）
     * @return string
     */
    private function toDate($target, string $type = 'date'): string
    {
        list(, $format) = $this->parseFormattedType($type);
        return $this->formatDateValue($target, $format ?: 'Y-m-d');
    }

    /**
     * 将值转为日期时间字符串（默认 Y-m-d H:i:s）。
     *
     * 本质上是 toDate() 的别名，仅默认格式不同。
     * 可通过 "datetime:U" 等语法指定输出格式。
     *
     * @param mixed  $target 待转换的值
     * @param string $type   完整的类型名（如 "datetime" 或 "datetime:Y-m-d H:i"）
     * @return string
     */
    private function toDatetime($target, string $type = 'datetime'): string
    {
        list(, $format) = $this->parseFormattedType($type);
        return $this->formatDateValue($target, $format ?: 'Y-m-d H:i:s');
    }

    /**
     * 将任意值按指定格式转为日期字符串（底层实现）。
     *
     * toDate() / toDatetime() 均委托本方法，避免格式字符串中的 ":" 被 parseFormattedType 误解析。
     *
     * @param mixed  $target 待转换的值
     * @param string $format date() 格式字符串（如 "Y-m-d"、"Y-m-d H:i:s"）
     * @return string
     */
    private function formatDateValue($target, string $format): string
    {
        if (is_int($target) || is_numeric($target)) {
            return date($format, (int)$target);
        }

        if ($target instanceof \DateTimeInterface) {
            return $target->format($format);
        }

        if (is_string($target)) {
            $ts = strtotime($target);
            return $ts !== false ? date($format, $ts) : '';
        }

        return '';
    }

    /**
     * 将值转为密文字符串（中间遮盖为 *）。
     *
     * 遮盖规则：
     * - mask:count → 精确遮盖 count 位（如 mask:4 对 "13288364266" 得 "132****4266"）
     * - mask（无参数） → 遮盖字符串长度的 80%（向上取整）
     * - 长度 ≤ 2 → 全遮盖
     * - 遮盖后在两侧均匀保留明文字符，左侧稍少（floor(visible/2)）
     *
     * @param mixed  $target 待转换的值（非字符串会转为字符串）
     * @param string $type   完整的类型名（如 "mask" 或 "mask:4"）
     * @return string
     */
    private function toMask($target, string $type = 'mask'): string
    {
        list(, $param) = $this->parseFormattedType($type);
        $str = (string)$target;
        $len = mb_strlen($str, 'UTF-8');

        if ($len === 0) {
            return '';
        }

        if ($len <= 2) {
            return str_repeat('*', $len);
        }

        if ($param !== null && $param !== '') {
            $maskCount = (int)$param;
        } else {
            $maskCount = (int)ceil($len * 0.8);
        }

        if ($maskCount <= 0) {
            return $str;
        }

        if ($maskCount >= $len) {
            return str_repeat('*', $len);
        }

        $visible = $len - $maskCount;
        $leftVisible = (int)floor($visible / 2);
        $rightVisible = $visible - $leftVisible;

        $left = mb_substr($str, 0, $leftVisible, 'UTF-8');
        $right = $rightVisible > 0 ? mb_substr($str, -$rightVisible, null, 'UTF-8') : '';

        return $left . str_repeat('*', $maskCount) . $right;
    }

    // ==================== New type handlers ====================

    /**
     * 去除首尾空白字符。
     *
     * @param mixed $target
     * @return string
     */
    private function applyTrim($target): string
    {
        return trim((string)$target);
    }

    /**
     * 大小写转换（lower / upper 共用）。
     *
     * @param mixed  $target
     * @param string $mode   'lower' 或 'upper'
     * @return string
     */
    private function applyCase($target, string $mode): string
    {
        $str = (string)$target;
        return $mode === 'lower' ? mb_strtolower($str, 'UTF-8') : mb_strtoupper($str, 'UTF-8');
    }

    /**
     * 提取数值字符（保留 0-9、.、-）。
     *
     * @param mixed $target
     * @return string
     */
    private function applyNumber($target): string
    {
        return preg_replace('/[^0-9.\-]/', '', (string)$target);
    }

    /**
     * 数组/对象 → JSON 字符串。
     *
     * @param mixed $target
     * @return string
     */
    private function applyJsonEncode($target): string
    {
        if (is_string($target)) {
            return $target;
        }
        return json_encode($target, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * JSON 字符串 → 关联数组。
     *
     * @param mixed $target
     * @return array|null 解码失败返回 null
     */
    private function applyJsonDecode($target)
    {
        if (is_array($target)) {
            return $target;
        }
        if (!is_string($target) || $target === '') {
            return null;
        }
        $decoded = json_decode($target, true);
        return $decoded !== null ? $decoded : null;
    }

    /**
     * 取绝对值。
     *
     * @param mixed $target
     * @return int|float
     */
    private function applyAbs($target)
    {
        if (is_numeric($target)) {
            return abs($target);
        }
        return 0;
    }

    /**
     * URL 编码。
     *
     * @param mixed $target
     * @return string
     */
    private function applyUrlEncode($target): string
    {
        return urlencode((string)$target);
    }

    /**
     * URL 解码。
     *
     * @param mixed $target
     * @return string
     */
    private function applyUrlDecode($target): string
    {
        return urldecode((string)$target);
    }

    /**
     * HTML 实体转义（防 XSS）。
     *
     * @param mixed $target
     * @return string
     */
    private function applyHtmlspecialchars($target): string
    {
        return htmlspecialchars((string)$target, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Base64 编码。
     *
     * @param mixed $target
     * @return string
     */
    private function applyBase64Encode($target): string
    {
        return base64_encode((string)$target);
    }

    /**
     * Base64 解码。
     *
     * @param mixed $target
     * @return string|false 解码失败返回 false
     */
    private function applyBase64Decode($target)
    {
        $decoded = base64_decode((string)$target, true);
        return $decoded !== false ? $decoded : null;
    }

    /**
     * 空值兜底：null / '' 时使用默认值，其他情况原样返回。
     *
     * @param mixed  $target
     * @param string $type   完整类型名（如 "default:匿名"）
     * @return mixed
     */
    private function applyDefault($target, string $type)
    {
        list(, $fallback) = $this->parseFormattedType($type);
        if ($target === null || $target === '') {
            return $fallback !== null ? $fallback : '';
        }
        return $target;
    }

    /**
     * 去除 HTML/PHP 标签，可选保留指定标签。
     *
     * strip_tags  → 去除所有标签
     * strip_tags:a,b → 保留 <a> 和 <b>
     *
     * @param mixed  $target
     * @param string $type   完整类型名
     * @return string
     */
    private function applyStripTags($target, string $type): string
    {
        $str = (string)$target;
        list(, $allowed) = $this->parseFormattedType($type);
        if ($allowed !== null && $allowed !== '') {
            $tags = implode('', array_map(fn($t) => '<' . trim($t) . '>', explode(',', $allowed)));
            return strip_tags($str, $tags);
        }
        return strip_tags($str);
    }

    /**
     * 数组拼接为字符串。
     *
     * implode     → 默认逗号分隔
     * implode:sep → 指定分隔符
     *
     * @param mixed  $target 应为数组
     * @param string $type   完整类型名
     * @return string
     */
    private function applyImplode($target, string $type): string
    {
        if (!is_array($target)) {
            return (string)$target;
        }
        list(, $sep) = $this->parseFormattedType($type);
        return implode($sep ?: ',', $target);
    }

    /**
     * 从数组列表中提取指定键的值。
     *
     * pluck:key → array_column 语义
     *
     * @param mixed  $target 应为索引数组
     * @param string $type   完整类型名（如 "pluck:id"）
     * @return array
     */
    private function applyPluck($target, string $type): array
    {
        if (!is_array($target)) {
            return [];
        }
        list(, $key) = $this->parseFormattedType($type);
        if ($key === null || $key === '') {
            return $target;
        }
        return array_column($target, $key);
    }

    /**
     * 四舍五入到指定小数位。
     *
     * round   → round($target)
     * round:n → round($target, n)
     *
     * @param mixed  $target
     * @param string $type   完整类型名
     * @return float
     */
    private function applyRound($target, string $type): float
    {
        list(, $decimals) = $this->parseFormattedType($type);
        $n = ($decimals !== null && $decimals !== '') ? (int)$decimals : 0;
        return round((float)$target, $n);
    }

    /**
     * 千分位数字格式化。
     *
     * number_format   → number_format($target, 2)
     * number_format:n → number_format($target, n)
     *
     * @param mixed  $target
     * @param string $type   完整类型名
     * @return string
     */
    private function applyNumberFormat($target, string $type): string
    {
        list(, $decimals) = $this->parseFormattedType($type);
        $n = ($decimals !== null && $decimals !== '') ? (int)$decimals : 2;
        return number_format((float)$target, $n, '.', ',');
    }

    /**
     * 字符串截断，超出长度追加后缀。
     *
     * truncate:n        → 截断到 n 字符，超出加 "..."
     * truncate:n:suffix → 截断到 n 字符，超出加自定义后缀
     *
     * @param mixed  $target
     * @param string $type   完整类型名
     * @return string
     */
    private function applyTruncate($target, string $type): string
    {
        $str = (string)$target;
        list(, $param) = $this->parseFormattedType($type);

        // 默认截断 100 字符，后缀 "..."
        $limit = 100;
        $suffix = '...';

        if ($param !== null && $param !== '') {
            $colonPos = strpos($param, ':');
            if ($colonPos !== false) {
                $limit = (int)substr($param, 0, $colonPos);
                $suffix = substr($param, $colonPos + 1);
            } else {
                $limit = (int)$param;
            }
        }

        if (mb_strlen($str, 'UTF-8') <= $limit) {
            return $str;
        }

        return rtrim(mb_substr($str, 0, $limit, 'UTF-8')) . $suffix;
    }

    /**
     * 解析带参数的格式化类型名。
     *
     * 将 "date:Y年m月d日" 拆分为基础类型 "date" 和参数 "Y年m月d日"。
     * 若不包含 ":" 则参数为 null。
     *
     * @param string $type 完整的类型名
     * @return array [baseType, param|null]
     */
    private function parseFormattedType(string $type): array
    {
        $colonPos = strpos($type, ':');
        if ($colonPos !== false) {
            return [substr($type, 0, $colonPos), substr($type, $colonPos + 1)];
        }
        return [$type, null];
    }

    // ==================== Dot notation helpers ====================

    /**
     * 将点号语法键解析为路径数组。
     *
     * 例如 "user.profile.id" → ['user', 'profile', 'id']
     *
     * @param string $key 点号分隔的键名
     * @return string[] 路径段数组
     */
    private function parsePath(string $key): array
    {
        return explode('.', $key);
    }

    /**
     * 从嵌套数组中按路径读取值。
     *
     * 沿路径逐层深入；任一段缺失（键不存在或中间值非数组）时返回 null。
     * 注意：当目标值为 null 时也会返回 null，与路径缺失无法区分；
     * 调用方需结合 completion 标志判断是否补全。
     *
     * @param mixed    $data 源数据（通常为数组）
     * @param string[] $path 路径段数组
     * @return mixed|null
     */
    private function getNestedValue($data, array $path)
    {
        foreach ($path as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }
        return $data;
    }

    /**
     * 按路径向嵌套数组写入值。
     *
     * 路径上的中间层级如果不存在或非数组，会自动创建空数组。
     * 原始数组通过引用原地修改。
     *
     * 例如 setNestedValue($arr, ['user', 'profile', 'id'], 9910)
     * → $arr['user']['profile']['id'] = 9910
     *
     * @param array    &$array 目标数组（引用传递）
     * @param string[] $path   路径段数组
     * @param mixed    $value  要写入的值
     */
    private function setNestedValue(array &$array, array $path, $value): void
    {
        $lastKey = array_pop($path);
        $current = &$array;

        foreach ($path as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current[$lastKey] = $value;
    }

    // ==================== Internal helpers ====================

    /**
     * 统一分发不同类型的转换规则。
     *
     * 支持三种规则形式：
     * - Mutator 实例：委托其 convert()
     * - callable：直接调用回调
     * - 其他（通常为 string）：委托 convertValue()
     *
     * @param mixed               $value 待转换的值
     * @param string|self|callable $type  转换规则
     * @return mixed
     */
    private function resolveConvert($value, $type)
    {
        if ($type instanceof self) {
            return $type->data($value)->convert();
        }

        // 字符串类型或数组管道优先于 callable，避免 "date" 等被误判
        if (is_string($type) || (is_array($type) && !Arr::isAssoc($type))) {
            return $this->convertValue($value, $type);
        }

        // 关联数组当作子规则（如 ["id" => "string"]）
        if (is_array($type)) {
            return $this->createChild($type)->data($value)->convert();
        }

        if (isSafeCallable($type)) {
            return $type($value);
        }

        return $this->convertValue($value, (string)$type);
    }

    /**
     * 创建子 Mutator 实例。
     *
     * 子实例继承当前实例的 completion 和 removeNotExistRuleKey 标志，
     * 确保嵌套转换行为一致。
     *
     * @param array|string|null $types 子实例的转换规则
     * @return self
     */
    private function createChild($types): self
    {
        return new self($types, $this->completion, $this->removeNotExistRuleKey);
    }
}
