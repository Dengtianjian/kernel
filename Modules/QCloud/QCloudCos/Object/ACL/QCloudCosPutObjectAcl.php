<?php

namespace kernel\Modules\QCloud\QCloudCos\Object\ACL;

use kernel\Modules\QCloud\QCloudCos\Object\AbstractQCloudCosObject;

/**
 * PUT Object acl（写入对象访问控制列表）的输入容器
 *
 * 请求：`PUT /<ObjectKey>?acl`。**请求头与请求体二选一**：
 * - **请求头**：`x-cos-acl` 或 `x-cos-grant-*`（本类继承 {@see QCloudCosObjectAclTrait}，方法直接可用）；
 * - **请求体 XML**：`AccessControlPolicy`（`Owner` + `AccessControlList/Grant`），用 {@see toXml()} 产出。
 *
 * ```php
 * // 走请求头
 * (new QCloudCosPutObjectAcl())->acl(AbstractQCloudCosObject::ACL_PUBLIC_READ)->all();
 *
 * // 走请求体（显式授权）
 * $xml = (new QCloudCosPutObjectAcl())
 *     ->ownerId("qcs::cam::uin/100000000001:uin/100000000001")
 *     ->grants([["id" => "qcs::cam::uin/100000000002:uin/100000000002", "permission" => "READ"]])
 *     ->toXml();
 * ```
 *
 * 要点（官方）：
 * - 是**覆盖**操作：新 ACL 覆盖原 ACL；单个 ACL 最多 **100** 条 Grant；
 * - **仅可对 CAM 主账号或匿名用户授予权限**；给子用户/用户组授权要用 PUT Bucket policy；
 * - 请求者需对该对象具备写入 ACL 的权限（action `cos:PutObjectACL`）；响应体为空。
 *
 * 文档核对状态：**已按官方文档核对**（PUT Object acl，product/436/7748，页面更新 2026-03-12）。
 *
 * @package kernel\Modules\QCloud\QCloudCos\Object\ACL
 */
class QCloudCosPutObjectAcl extends AbstractQCloudCosObject
{
  /** Grant 的 Grantee 类型：CAM 用户（用 ID） */
  const GRANTEE_CANONICAL_USER = "CanonicalUser";
  /** Grant 的 Grantee 类型：用户组（用 URI） */
  const GRANTEE_GROUP = "Group";

  /** Permission：读 */
  const PERMISSION_READ = "READ";
  /** Permission：写 */
  const PERMISSION_WRITE = "WRITE";
  /** Permission：读写 ACL */
  const PERMISSION_READ_ACP = "READ_ACP";
  /** Permission：写 ACL */
  const PERMISSION_WRITE_ACP = "WRITE_ACP";
  /** Permission：所有权限 */
  const PERMISSION_FULL_CONTROL = "FULL_CONTROL";

  /**
   * Owner/ID（对象属主 ID）
   *
   * 官方格式：`qcs::cam::uin/[OwnerUin]:uin/[OwnerUin]`。
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function ownerId($value = null)
  {
    return $this->item("OwnerId", func_get_args());
  }

  /**
   * Owner/DisplayName（属主显示名，可选）
   *
   * @param string|null $value 不传=读取
   * @return mixed
   */
  public function ownerDisplayName($value = null)
  {
    return $this->item("OwnerDisplayName", func_get_args());
  }

  /**
   * 授权列表（**必选**：走请求体时至少要有一条）
   *
   * 每项支持这些键：
   * - `type`：{@see GRANTEE_CANONICAL_USER}（默认，用 `id`）/ {@see GRANTEE_GROUP}（用 `uri`）
   * - `id` / `uri`：被授权者标识
   * - `permission`：{@see PERMISSION_READ} / `WRITE` / `READ_ACP` / `WRITE_ACP` / {@see PERMISSION_FULL_CONTROL}
   *
   * @param array|null $grants 授权数组；不传=读取
   * @return mixed
   */
  public function grants($grants = null)
  {
    if (!func_get_args()) {
      return $this->item("Grants", []);
    }

    return $this->item("Grants", [array_values($grants)]);
  }

  /**
   * 产出请求体 XML（`<AccessControlPolicy>`）
   *
   * ⚠️ 这里是**手工拼串**而非 {@see \kernel\Foundation\Data\Arr::toXML()}：官方 XML 需要
   * `Grantee` 上的 **`xsi:type` 属性**，而 `Arr::toXML()` 不支持属性。所有文本值逐值转义。
   *
   * @return string XML 请求体；未设置 grants 时返回空串（调用方应先校验）
   */
  public function toXml()
  {
    $grants = $this->item("Grants", []);
    if (!$grants) return "";

    $escape = function ($value) {
      return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, "UTF-8");
    };

    $xml = '<AccessControlPolicy xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';

    //* Owner 可省略（官方允许只带 AccessControlList），有值才输出
    $ownerId = $this->item("OwnerId", []);
    $ownerDisplayName = $this->item("OwnerDisplayName", []);
    if ($ownerId !== null || $ownerDisplayName !== null) {
      $xml .= "<Owner>";
      if ($ownerId !== null) $xml .= "<ID>" . $escape($ownerId) . "</ID>";
      if ($ownerDisplayName !== null) $xml .= "<DisplayName>" . $escape($ownerDisplayName) . "</DisplayName>";
      $xml .= "</Owner>";
    }

    $xml .= "<AccessControlList>";
    foreach ($grants as $grant) {
      $grant = is_array($grant) ? $grant : [];
      $type = isset($grant["type"]) && $grant["type"] === self::GRANTEE_GROUP ? self::GRANTEE_GROUP : self::GRANTEE_CANONICAL_USER;
      $permission = isset($grant["permission"]) ? $grant["permission"] : self::PERMISSION_READ;

      $xml .= "<Grant><Grantee xsi:type=\"{$type}\">";
      if ($type === self::GRANTEE_GROUP) {
        $xml .= "<URI>" . $escape(isset($grant["uri"]) ? $grant["uri"] : "") . "</URI>";
      } else {
        $xml .= "<ID>" . $escape(isset($grant["id"]) ? $grant["id"] : "") . "</ID>";
      }
      $xml .= "</Grantee><Permission>" . $escape($permission) . "</Permission></Grant>";
    }
    $xml .= "</AccessControlList></AccessControlPolicy>";

    return $xml;
  }
}
