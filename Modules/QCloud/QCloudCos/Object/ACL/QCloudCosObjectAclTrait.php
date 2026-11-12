<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\ACL;

/**
 * 对象访问控制（ACL）请求头的 get/set 方法组
 *
 * 以 **trait** 形式提供，供各操作的「输入容器」复用 —— PHP 是单继承，所以需要 ACL 能力的类
 * 只能靠 trait 组合（这也是仓内 `Transforms/*` 的既有做法）。
 *
 * 调用约定与 {@see \kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject} 完全一致：
 * **不传值 = 读取**（未设置返回 null）、**传值 = 设置**并返回 `$this`（可链式）。
 *
 * ```php
 * $put->acl(QCloudCosPutObject::ACL_PUBLIC_READ)
 *     ->grantRead(QCloudCosObjectAclTrait::grantId("100000000001"));
 * ```
 *
 * 依赖与边界：
 * - 使用方必须能访问 `$this->item($name, $args)` —— 由 {@see \kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject} 提供；
 * - ACL 预设常量（`ACL_DEFAULT` / `ACL_PRIVATE` / `ACL_PUBLIC_READ`）**仍留在基类上**：
 *   PHP 7 的 trait **不能声明常量**；
 * - 逐个授予子用户/用户组权限需要 PUT Bucket policy，不在对象 ACL 头的能力范围内。
 *
 * 文档依据：腾讯云 COS「PUT Object acl」（product/436/7748，页面更新 2026-03-12）与「PUT Object」的
 * ACL 请求头部分。两页都**未列出** `x-cos-grant-write`，故本 trait 只提供文档列出的四个 grant 头。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\ACL
 */
trait QCloudCosObjectAclTrait
{
  /**
   * x-cos-acl（对象 ACL，默认 default = 继承存储桶权限）
   *
   * 取值：`default` / `private` / `public-read`（见基类常量 `ACL_*`；完整枚举见官方 ACL 概述）。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function acl($value = null)
  {
    return $this->item("x-cos-acl", func_get_args());
  }

  /**
   * x-cos-grant-read（赋予被授权者读取对象的权限）
   *
   * 格式 `id="[OwnerUin]"`，多组用半角逗号分隔；可用 {@see grantId()} 拼。
   * 注意：**仅可对 CAM 主账号或匿名用户授予**（子用户/用户组要走 PUT Bucket policy）。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function grantRead($value = null)
  {
    return $this->item("x-cos-grant-read", func_get_args());
  }

  /**
   * x-cos-grant-read-acp（赋予被授权者读取对象 ACL 的权限）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function grantReadAcp($value = null)
  {
    return $this->item("x-cos-grant-read-acp", func_get_args());
  }

  /**
   * x-cos-grant-write-acp（赋予被授权者写入对象 ACL 的权限）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function grantWriteAcp($value = null)
  {
    return $this->item("x-cos-grant-write-acp", func_get_args());
  }

  /**
   * x-cos-grant-full-control（赋予被授权者操作对象的所有权限）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function grantFullControl($value = null)
  {
    return $this->item("x-cos-grant-full-control", func_get_args());
  }

  /**
   * 便捷：把 UIN 拼成授权头要求的格式 `id="[OwnerUin]"`
   *
   * @param string|array $uin 单个 UIN 或 UIN 数组（多个自动用逗号连接）
   * @return string 形如 `id="100000000001"` 或 `id="1",id="2"`
   */
  public static function grantId($uin)
  {
    $uins = is_array($uin) ? $uin : [$uin];
    $ids = [];
    foreach ($uins as $item) {
      $ids[] = 'id="' . $item . '"';
    }

    return implode(",", $ids);
  }
}
