<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

use kernel\Foundation\Data\Arr;

/**
 * SELECT Object Content（用 SQL 查询对象内容）的输入容器
 *
 * 该接口的输入**不是请求头而是请求体 XML**（`POST /<ObjectKey>?select`），所以本类把
 * SQL 表达式、序列化配置、进度事件写成方法，并提供 {@see toXml()} 直接产出请求体。
 * 调用约定（不传=读取 / 传值=设置并返回 `$this`）见 {@see AbstractQCloudCosObject}。
 *
 * ```php
 * $select = (new QCloudCosSelectObjectContent())
 *     ->expression("SELECT * FROM COSObject WHERE _1 = '1'")
 *     ->inputSerialization(QCloudCosSelectObjectContent::SERIALIZATION_CSV)
 *     ->outputSerialization(["JSON" => ["Type" => "LINES"]]);
 *
 * $xml = $select->toXml();
 * ```
 *
 * ⚠️ 两个必须知道的点：
 * 1. **响应不是普通 XML**：COS 返回的是**事件流分块数据**（含 Records / Stats / End 等事件），
 *    需要按 event-stream 协议自己拆帧（仓内 `DiscuzXQCloudCOS::selectObjectContent()` 目前**原样返回**不解析）；
 * 2. `?select` 是**子资源** ⇒ 必须**同时**参与签名（`q-url-param-list=select`）并出现在请求 URL 上。
 *
 * 文档核对状态：XML 结构（`SelectRequest` / `Expression` / `ExpressionType` / `InputSerialization` /
 * `OutputSerialization` / `RequestProgress`）依据官方写法整理，**待与官方 SELECT Object Content 页面逐条核对**。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosSelectObjectContent extends AbstractQCloudCosObject
{
  /** 表达式类型：SQL */
  const EXPRESSION_TYPE_SQL = "SQL";

  /** 序列化配置预设：CSV（无附加参数） */
  const SERIALIZATION_CSV = ["CSV" => ""];
  /** 序列化配置预设：JSON（按行） */
  const SERIALIZATION_JSON_LINES = ["JSON" => ["Type" => "LINES"]];

  /**
   * Expression（**必选**：SQL 表达式）
   *
   * 表名固定为 `COSObject`，如 `SELECT * FROM COSObject WHERE _1 = '1'`。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function expression($value = null)
  {
    return $this->item("Expression", func_get_args());
  }

  /**
   * ExpressionType（表达式类型，默认 SQL）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function expressionType($value = null)
  {
    return $this->item("ExpressionType", func_get_args());
  }

  /**
   * InputSerialization（输入序列化配置，数组）
   *
   * 默认 {@see SERIALIZATION_CSV}；细配置示例：`["CSV" => ["FileHeaderInfo" => "USE"]]`、
   * `["CompressionType" => "GZIP", "CSV" => ""]`、`["JSON" => ["Type" => "LINES"]]`。
   *
   * @param array|null $value 不传=读取
   * @return mixed
   */
  public function inputSerialization($value = null)
  {
    return $this->item("InputSerialization", func_get_args());
  }

  /**
   * OutputSerialization（输出序列化配置，数组）
   *
   * 默认 {@see SERIALIZATION_CSV}；也可用 {@see SERIALIZATION_JSON_LINES}。
   *
   * @param array|null $value 不传=读取
   * @return mixed
   */
  public function outputSerialization($value = null)
  {
    return $this->item("OutputSerialization", func_get_args());
  }

  /**
   * RequestProgress（是否返回进度事件）
   *
   * `true` ⇒ XML 里加 `<RequestProgress><Enabled>true</Enabled></RequestProgress>`。
   *
   * @param boolean|null $value 不传=读取（返回布尔或 null）
   * @return mixed
   */
  public function requestProgress($value = null)
  {
    if (!func_get_args()) {
      $current = $this->item("RequestProgress", []);

      return $current === null ? null : ($current === "true");
    }

    return $this->item("RequestProgress", [$value ? "true" : "false"]);
  }

  /**
   * 产出请求体 XML（`<SelectRequest>…</SelectRequest>`）
   *
   * 用 {@see Arr::toXML()} 拼：`Expression` / `ExpressionType` 逐值转义后写入，
   * 两个序列化配置按调用方给的数组原样递归展开（与本仓 `DiscuzXQCloudCOS::selectObjectContent()` 一致）。
   * 未设置 `Expression` 时返回空串。
   *
   * @return string XML 请求体
   */
  public function toXml()
  {
    $expression = $this->item("Expression", []);
    if (!$expression) return "";

    $escape = function ($value) {
      return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, "UTF-8");
    };

    $body = [
      "Expression" => $escape($expression),
      "ExpressionType" => $escape($this->item("ExpressionType", []) ?: self::EXPRESSION_TYPE_SQL),
      "InputSerialization" => $this->item("InputSerialization", []) ?: self::SERIALIZATION_CSV,
      "OutputSerialization" => $this->item("OutputSerialization", []) ?: self::SERIALIZATION_CSV,
    ];

    if ($this->item("RequestProgress", []) === "true") {
      $body["RequestProgress"] = ["Enabled" => "true"];
    }

    return Arr::toXML($body, true, "SelectRequest", false);
  }
}
