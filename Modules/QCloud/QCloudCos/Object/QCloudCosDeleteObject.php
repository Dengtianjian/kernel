<?php

namespace kernel\Modules\QCloud\QCloudCos\Object;

/**
 * DELETE Object（删除单个对象）的输入容器
 *
 * ⚠️ **本接口按现行文档没有专用请求头**，因此本类除基类提供的通用能力外**不新增任何方法** ——
 * 保留它是为了与其他操作保持一一对应的结构；真正需要表达的两件事都不在「请求头」上：
 *
 * - **删除指定版本**：走 URL 参数 `?versionId=…`（不是请求头）；
 * - **删除结果**：成功返回 **204 No Content**（无响应体）。
 *
 * 若后续核对官方文档发现该接口确有专用头（例如版本、条件删除相关），在类里加一个
 * `return $this->item("头名", func_get_args());` 的方法即可。
 *
 * ⚠️ 文档核对状态：**待核对**（结论"无专用请求头"依据通用实践整理，尚未逐条对官方 DELETE Object 页面）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object
 */
class QCloudCosDeleteObject extends AbstractQCloudCosObject
{
}
