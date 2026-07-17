<?php

namespace kernel\Platform\DiscuzX\Foundation\Storage\QCloud\QCloudSTS;

use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 腾讯云STS安全凭证服务  
 * 基于腾讯云的STS类扩展
 * @inheritDoc STS实例文档 https://github.com/tencentyun/qcloud-cos-sts-sdk/tree/master/php
 */
class DiscuzXQCloudSTS extends AbilityBaseObject
{
  /**
   * 腾讯云用户ID
   *
   * @var int
   */
  public $UserId = null;
  /**
   * 云 API 密钥 Id
   *
   * @var string
   */
  private $SecretId = null;
  /**
   * 云 API 密钥 key
   *
   * @var string
   */
  private $SecretKey = null;
  /**
   * 存储桶名称：bucketName-appid, 如 test-125000000
   *
   * @var string
   */
  public $Bucket = null;
  /**
   * 存储桶所属地域，如 ap-guangzhou
   *
   * @var string
   */
  public $Region = null;
  /**
   * STS类实例
   *
   * @var Sts
   */
  private $STSInstance = null;
  /**
   * 创建腾讯云STS服务实例
   *
   * @param string $SecretId 云 API 密钥 Id
   * @param string $SecretKey 云 API 密钥 key
   * @param string $Region 存储桶所属地域，如 ap-guangzhou
   * @param string $Bucket 存储桶所属地域，如 ap-guangzhou
   */
  function __construct($SecretId, $SecretKey, $Region, $Bucket)
  {
    $this->STSInstance = new DiscuzXQCloudStsBase();

    $this->SecretId = $SecretId;
    $this->SecretKey = $SecretKey;
    $this->Bucket = $Bucket;
    $this->Region = $Region;
    $this->UserId = substr($Bucket, 1 + strripos($Bucket, '-'));
  }
  /**
   * 处理sts请求的响应信息
   *
   * @param mixed $ResponseData 响应数据
   * @return array
   */
  protected function handleResponseData($ResponseData)
  {
    return  json_decode(json_encode($ResponseData), true);
  }
  /**
   * 获取临时密钥
   *
   * @param string|string[] $AllowPrefix 资源的前缀，如授予操作所有资源，则为*；如授予操作某个路径a下的所有资源,则为 a/*，如授予只能操作特定的文件a/test.jpg, 则为a/test.jpg
   * @param array $AllowActions 授予 COS API 权限集合, 如简单上传操作：name/cos:PutObject。  
   * 权限名称文档地址：https://cloud.tencent.com/document/product/436/31923#.E6.A6.82.E8.BF.B0，文档代码片段中的action值
   * @param integer $DurationSeconds 要申请的临时密钥最长有效时间，单位秒，默认 1800，最大可设置 7200
   * @return array
   * 返回值说明
    |字段|类型|描述|
    | ---- | ---- | ---- |
    |credentials | string | 临时密钥信息 |
    |tmpSecretId | string | 临时密钥 Id，可用于计算签名 |
    |tmpSecretKey | string | 临时密钥 Key，可用于计算签名 |
    |sessionToken | string | 请求时需要用的 token 字符串，最终请求 COS API 时，需要放在 Header 的 x-cos-security-token 字段 |
    |startTime | string | 密钥的起始时间，是 UNIX 时间戳 |
    |expiredTime | string | 密钥的失效时间，是 UNIX 时间戳 |
   */
  function getTempKeys($AllowPrefix,  $AllowActions, $DurationSeconds = 1800)
  {
    $Config = [
      'secretId' => $this->SecretId,
      'secretKey' => $this->SecretKey,
      'bucket' => $this->Bucket,
      'region' => $this->Region,
      'durationSeconds' => $DurationSeconds,
      'allowPrefix' => $AllowPrefix,
      "allowActions" => $AllowActions
    ];

    $tempKeys = $this->STSInstance->getTempKeys($Config);
    return $this->handleResponseData($tempKeys);
  }
  /**
   * 基于策略来获取临时秘钥
   * @inheritDoc 授权策略使用指引 https://cloud.tencent.com/document/product/436/31923#.E6.A6.82.E8.BF.B0
   * @inheritDoc 策略语法 https://cloud.tencent.com/document/product/598/10603
   *
   * @param array $Statement 授予该临时访问凭证权限的CAM策略语法。描述一条或多条权限的详细信息。该元素包括 principal、action、resource、condition、effect 等多个其他元素的权限或权限集合。一条策略有且仅有一个 statement 元素。  
    示例值：[{"effect":"allow","action":"sts:AssumeRole","resource":"*"}]
   * @param integer $DurationSeconds 要申请的临时密钥最长有效时间，单位秒，默认 1800，最大可设置 7200
   * @param string $Version 描述策略语法版本
   * @return array
   * 返回值说明
    |字段|类型|描述|
    | ---- | ---- | ---- |
    |credentials | string | 临时密钥信息 |
    |tmpSecretId | string | 临时密钥 Id，可用于计算签名 |
    |tmpSecretKey | string | 临时密钥 Key，可用于计算签名 |
    |sessionToken | string | 请求时需要用的 token 字符串，最终请求 COS API 时，需要放在 Header 的 x-cos-security-token 字段 |
    |startTime | string | 密钥的起始时间，是 UNIX 时间戳 |
    |expiredTime | string | 密钥的失效时间，是 UNIX 时间戳 |
   */
  function getTempKeysByPolicy($Statement, $DurationSeconds = 1800, $Version = "2.0")
  {
    $Config = [
      'secretId' => $this->SecretId,
      'secretKey' => $this->SecretKey,
      'bucket' => $this->Bucket,
      'region' => $this->Region,
      'durationSeconds' => $DurationSeconds,
      "policy" => [
        "version" => $Version,
        "statement" => $Statement
      ]
    ];
    return $Config;
    $tempKeys = $this->STSInstance->getTempKeys($Config);
    return $this->handleResponseData($tempKeys);
  }
  /**
   * 生成资源描述文本
   * @inheritDoc 资源描述方式 https://cloud.tencent.com/document/product/598/10606
   * 
   * @param string $ResourceName 描述各产品的具体资源详情，目前支持两种方式描述资源信息，resource_type/${resourceid} 和 <resource_type>/<resource_path>。  
    resource_type/${resourceid}：resourcetype 为资源前缀，描述资源类型，详细可查看 支持 CAM 的业务接口 中产品的资源六段式；${resourceid} 为具体的资源 ID，可前往各个产品控制台查看，值为 * 时代表该类型资源的所有资源。  
    <resource_type>/<resource_path>：resourcetype 为资源前缀，描述资源类型；  
    <resource_path> 为资源路径，该方式下，支持目录级的前缀匹配。详细可查看 支持 CAM 的业务接口 中产品的资源六段式。  
    \*（星号） 为所有资源
   * @param string $ServiceType 描述产品简称，详细可查看 支持 CAM 的产品 中的 “CAM 中简称”(https://cloud.tencent.com/document/product/598/67350)。值为空时表示所有产品。
   * 
   * @return string
   */
  function generateResourceDescription($ResourceName, $ServiceType = "cos")
  {
    return "qcs::{$ServiceType}:{$this->Region}:uid/{$this->UserId}:{$this->Bucket}/{$ResourceName}";
  }
  /**
   * 生成策略描述语句
   * @inheritDoc 语法结构 https://cloud.tencent.com/document/product/598/10604
   *
   * @param array|string $Action 描述允许或拒绝的操作。操作可以是 API（以 name 前缀描述）或者功能集（一组特定的 API，以 actionName 前缀描述）  \*（星号） 为所有操作  
   * @param array|string $Resource 描述授权的具体数据。资源是用六段式描述。每款产品的资源定义详情会有所区别，详情请参见 资源描述方式。   \*（星号） 为所有资源  
   * **建议调用当前实例中的generateResourceDescription方法生成资源描述** 
   * @param string $Effect 描述声明产生的结果是“允许”还是“显式拒绝”。包括 allow（允许）和 deny （显式拒绝）两种情况
   * @param array $Condition 描述策略生效的约束条件。条件包括操作符、操作键和操作值组成。条件值可包括时间、IP 地址信息。有些服务允许您在条件中指定其他值。详情请参见 条件键和条件运算符(https://cloud.tencent.com/document/product/598/10608)。
   * @return array
   */
  function generatePolicyStatement($Action, $Resource, $Effect = "allow", $Condition = [])
  {
    return [
      "action" => $Action,
      "resource" => $Resource,
      "effect" => $Effect,
      "condition" => $Condition
    ];
  }
}
