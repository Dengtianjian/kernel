<?php

namespace kernel\DiscuzX\Foundation;

use kernel\Foundation\FileSystem\FileSystem;
use kernel\Modules\Aliyun\AliyunOSS\AliyunOSSStorage;
use kernel\Modules\QCloud\QCloudAccount;
use kernel\Modules\QCloud\QCloudCos\QCloudCOSStorage;

/**
 * Discuz! X5.0「后台对象存储（X5.6b）」适配器能力（trait）
 *
 * 业务插件在自己的 oss/init.php 中声明（X5.6b 强制品类，oss::loadOSS() 加载）：
 *
 *   class oss_plugin_{插件ID} extends oss_base
 *   {
 *     use DiscuzXOssAdapterTrait;
 *   }
 *
 * 即满足 X5.6b 适配器形态（oss_base 15 抽象方法 + load($param) 入口），全部对象
 * 操作委托基础插件云驱动（凭据注入式构造，零状态薄壳；不依赖 discuz_upload，
 * CLI/Web 一致）。需要定制时覆盖对应方法即可（类方法优先于 trait 方法）；
 * 私有辅助以 discuzX 前缀命名，避免与业务类冲突。
 */
trait DiscuzXOssAdapterTrait
{
  /** X5 后台的 oss 设置（oss_id / oss_key / oss_bucket / oss_bucket_url / oss_url / oss_rootpath） */
  private $discuzXOssConfig;
  /** 云厂商：aliyun / qcloud（按 oss_bucket_url 域名判定，X5 后台不区分厂商） */
  private $discuzXVendor = "aliyun";
  /** 惰性构造的云驱动实例 */
  private $discuzXDriver = null;

  /**
   * oss::loadOSS() 的入口：返回适配器实例
   *
   * @param array $param X5 后台 oss 设置
   * @return self
   */
  public static function load($param)
  {
    return new self($param);
  }

  public function __construct($param)
  {
    $this->discuzXOssConfig = $param;
    $url = strtolower((string) ($param['oss_bucket_url'] ?? ""));
    $this->discuzXVendor = str_contains($url, "myqcloud.com") ? "qcloud" : "aliyun";
  }

  /**
   * 云厂商区域，从 oss_bucket_url 域名解析
   *
   * 阿里：{bucket}.oss-cn-hangzhou.aliyuncs.com → oss-cn-hangzhou
   * 腾讯：{bucket}.cos.ap-shanghai.myqcloud.com → ap-shanghai
   *
   * @return string
   */
  private function discuzXRegion()
  {
    $host = strtolower(parse_url($this->discuzXOssConfig['oss_bucket_url'] ?? "", PHP_URL_HOST) ?: "");
    if ($this->discuzXVendor === "qcloud") {
      return preg_replace('/^[^.]+\./', '', str_replace('.myqcloud.com', '', $host)) ?: "ap-shanghai";
    }
    return preg_replace('/^[^.]+\./', '', str_replace('.aliyuncs.com', '', $host)) ?: "oss-cn-hangzhou";
  }

  /**
   * 惰性构造基础插件云驱动（凭据注入式构造，详见各驱动的 __construct）
   *
   * @return AliyunOSSStorage|QCloudCOSStorage
   */
  private function discuzXDriver()
  {
    if ($this->discuzXDriver !== null) {
      return $this->discuzXDriver;
    }

    if ($this->discuzXVendor === "qcloud") {
      $this->discuzXDriver = new QCloudCOSStorage(
        new QCloudAccount($this->discuzXOssConfig['oss_id'], $this->discuzXOssConfig['oss_key']),
        $this->discuzXRegion(),
        $this->discuzXOssConfig['oss_bucket']
      );
    } else {
      $this->discuzXDriver = new AliyunOSSStorage(
        $this->discuzXOssConfig['oss_id'],
        $this->discuzXOssConfig['oss_key'],
        $this->discuzXRegion(),
        $this->discuzXOssConfig['oss_bucket']
      );
    }

    return $this->discuzXDriver;
  }

  /**
   * 桶连通性测试：true = 凭据与桶可用
   * @return boolean
   */
  public function testOSS()
  {
    return $this->discuzXDriver()->testBucket();
  }

  /**
   * 桶 CORS 配置（委托驱动；腾讯 COS 为控制台配置口径）
   * @return boolean
   */
  public function setCors()
  {
    return $this->discuzXDriver()->setBucketCors([[
      "allowedOrigin" => "*",
      "allowedMethod" => ["GET", "PUT", "POST", "DELETE", "HEAD"],
      "allowedHeader" => "*"
    ]]);
  }

  /**
   * 桶 Referer 防盗链配置（委托驱动；腾讯 COS 为控制台配置口径）
   * @return boolean
   */
  public function RefererConfig()
  {
    return $this->discuzXDriver()->setBucketReferer([
      "referList" => [],
      "allowEmpty" => true
    ]);
  }

  /**
   * 对象是否存在
   * @param string $object 对象名（含 oss_rootpath）
   * @return boolean
   */
  public function isObject($object)
  {
    return $this->discuzXDriver()->exists($object);
  }

  /**
   * 对象 ACL（腾讯 COS 对象权限继承桶策略，驱动层直接确认）
   * @param string $object 对象名
   * @param string|null $Acl "public" / "private" 等
   * @return boolean
   */
  public function setAcl($object, $Acl = null)
  {
    return $this->discuzXDriver()->setObjectAcl($object, $Acl === "public" ? "public-read" : $Acl);
  }

  /**
   * 上传本地文件为对象
   *
   * @param string $file 本地文件路径（X5 调用点传 $_G['setting']['attachdir'] 拼接的路径）
   * @param string $object 对象名（含 oss_rootpath）
   * @param string|null $Acl "public" / "private" 等
   * @return boolean
   */
  public function uploadFile($file, $object, $Acl = null)
  {
    $this->discuzXDriver()->putObjectData(file_get_contents($file), $object);
    if ($Acl) {
      $this->discuzXDriver()->setObjectAcl($object, $Acl === "public" ? "public-read" : $Acl);
    }
    return true;
  }

  /**
   * 列举对象
   * @param string $prefix 前缀
   * @param string $marker 分页位置
   * @param integer $limit 条数
   * @param string $delimiter 分隔符
   * @return array
   */
  public function getFilesList($prefix = '', $marker = '', $limit = 100, $delimiter = '')
  {
    return $this->discuzXDriver()->listObjects($prefix, $marker, $limit, $delimiter);
  }

  /**
   * 数据内容直写为对象
   * @param string $data 对象内容
   * @param string $object 对象名（含 oss_rootpath）
   * @param string|null $Acl
   * @return boolean
   */
  public function uploadData($data, $object, $Acl = null)
  {
    $this->discuzXDriver()->putObjectData($data, $object);
    if ($Acl) {
      $this->discuzXDriver()->setObjectAcl($object, $Acl === "public" ? "public-read" : $Acl);
    }
    return true;
  }

  /**
   * 重命名对象（复制 + 删源）
   * @param string $oldObject 旧对象名
   * @param string $newObject 新对象名
   * @param string|null $MimeType
   * @return boolean
   */
  public function renameObject($oldObject, $newObject, $MimeType = null)
  {
    $driver = $this->discuzXDriver();
    $driver->copyObject($oldObject, $newObject);
    $driver->delete($oldObject);
    return true;
  }

  /**
   * 删除对象（单个对象名或对象名数组）
   * @param string|array $objects 对象名
   * @return boolean
   */
  public function deleteFile($objects)
  {
    foreach ((array) $objects as $object) {
      $this->discuzXDriver()->delete($object);
    }
    return true;
  }

  /**
   * 下载对象到本地文件
   * @param string $file 本地保存路径
   * @param string $object 对象名（含 oss_rootpath）
   * @return boolean
   */
  public function downFile($file, $object)
  {
    return $this->discuzXDriver()->downloadObject($object, $file);
  }

  /**
   * 对象访问签名 URL
   *
   * X5 调用点为 signUrl($object, $attach['filename'], 3600)（下载重命名 + 有效期），
   * 落盘与签名细节由基础插件驱动的 url() 承担；重命名参数在联调时按 X5 消费点校准。
   *
   * @param string $object 对象名（含 oss_rootpath）
   * @return string 签名后的访问 URL
   */
  public function signUrl($object)
  {
    return $this->discuzXDriver()->url($object);
  }

  /**
   * 直传策略（前端直传用）
   *
   * 返回结构与 X5 直传前端约定在联调时校准；阿里走 PostObject 签名
   * （policy / signature / accessKeyId），腾讯 COS 走临时密钥（getTempKeys）。
   *
   * @param string $dir 上传目录
   * @param string $object 对象名
   * @param integer $length 体积上限
   * @return array
   */
  public function getPolicy($dir, $object, $length = 1048576000)
  {
    $policy = [
      "oss_url" => $this->discuzXOssConfig['oss_url'],
      "oss_bucket_url" => $this->discuzXOssConfig['oss_bucket_url'],
      "dir" => $dir,
      "maxSize" => $length
    ];
    if ($this->discuzXVendor === "qcloud") {
      $policy["credentials"] = $this->discuzXDriver()->getTempKeys($dir);
    } else {
      $conditions = [["content-length-range", 0, $length], ["starts-with", '$key', rtrim($dir, '/') . '/']];
      $policyText = base64_encode(json_encode($conditions));
      $policy["policy"] = $policyText;
      $policy["signature"] = base64_encode(hash_hmac('sha1', $policyText, (string) $this->discuzXOssConfig['oss_key'], true));
      $policy["accessKeyId"] = (string) $this->discuzXOssConfig['oss_id'];
    }
    return $policy;
  }

  /**
   * 上传回调配置（X5 直传流程消费；返回结构与直传前端在联调时校准）
   * @param string $object 对象名
   * @return array|null
   */
  public function getCallback($object)
  {
    return ["callback_url" => rtrim((string) ($_SERVER['HTTP_HOST'] ?? ""), '/') . "/plugin.php?id=gstudio_kernel:storage/callback", "object" => $object];
  }

  /**
   * 图片处理样式 URL（阿里 x-oss-process=style/{style}；腾讯 imageMogr2）
   * @param string $style 样式名
   * @return string
   */
  public function getImgStyle($style)
  {
    $url = rtrim((string) ($this->discuzXOssConfig['oss_url'] ?? ""), '/');
    return $this->discuzXVendor === "qcloud" ? "{$url}?imageMogr2/thumbnail/!{$style}p" : "{$url}?x-oss-process=style/{$style}";
  }
}
