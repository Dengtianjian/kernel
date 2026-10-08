<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\Tag;

use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * GET Object tagging（读取对象标签）的输入容器
 *
 * 请求：`GET /<ObjectKey>?tagging`。
 *
 * ⚠️ **本接口没有专用请求头，也没有请求体** —— 因此本类除基类能力外不新增方法；保留它是为了让
 * 标签这一组的三个接口（PUT / GET / DELETE）与目录结构一一对应。
 * 结果在**响应体 XML** 里（`<Tagging><TagSet><Tag><Key/><Value/></Tag>…</TagSet></Tagging>`）。
 *
 * 排障提示：响应里 `<TagSet>` 为空 ⇒ 该对象没有标签（不要与"没有权限"混淆，后者是 403 + `AccessDenied`）。
 *
 * 文档核对状态：**待核对**（"无专用请求头"这一结论依据通用实践整理，尚未逐条对官方 GET Object tagging 页面）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\Tag
 */
class QCloudCosGetObjectTagging extends AbstractQCloudCosObject
{
}
