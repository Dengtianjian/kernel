<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\Tag;

use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * DELETE Object tagging（删除对象标签）的输入容器
 *
 * 请求：`DELETE /<ObjectKey>?tagging`。
 *
 * ⚠️ **本接口没有专用请求头，也没有请求体** —— 因此除基类能力（容器方法，以及继承来的
 * ACL/标签 trait 方法——它们在 DELETE 场景**不会生效**）之外不新增方法；保留它是为了让
 * 标签这一组的三接口（PUT / GET / DELETE）与 `Object/Tag` 目录一一对应。成功返回 **204**（无内容）；
 * 失败为 404（对象不存在）或 403（无权限）。
 *
 * 文档核对状态：**待核对**（"无专用请求头"依据通用实践整理，尚未逐条对官方 DELETE Object tagging 页面）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\Tag
 */
class QCloudCosDeleteObjectTagging extends AbstractQCloudCosObject
{
}
