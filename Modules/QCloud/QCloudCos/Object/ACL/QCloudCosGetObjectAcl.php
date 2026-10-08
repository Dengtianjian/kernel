<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\ACL;

use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * GET Object acl（读取对象访问控制列表）的输入容器
 *
 * 请求：`GET /<ObjectKey>?acl`。
 *
 * ⚠️ **本接口没有专用请求头，也没有请求体** —— 因此本类除基类能力（容器方法，以及继承来的
 * ACL 头方法，它们在 GET 场景**不会生效**）之外不新增方法。保留它是为了让三个 ACL 接口
 * （PUT / GET）与目录结构一一对应；真正的结果在**响应体 XML**（`AccessControlPolicy`）里。
 *
 * 排障提示：响应里没有 `<AccessControlList>`/`<Grant>` 时，说明对象只是**继承**了桶的 ACL
 * （即 `x-cos-acl` 为 `default`），并非"没有权限"。
 *
 * 文档核对状态：**待核对**（"无专用请求头"这一结论依据通用实践整理，尚未逐条对官方 GET Object acl 页面）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\ACL
 */
class QCloudCosGetObjectAcl extends AbstractQCloudCosObject
{
}
