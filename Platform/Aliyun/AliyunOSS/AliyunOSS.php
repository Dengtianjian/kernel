<?php

namespace kernel\Platform\Aliyun\AliyunOSS;

use kernel\Platform\Aliyun\Aliyun;

class AliyunOSS extends Aliyun
{
  /**
   * 存储桶所在地区，如 ap-guangzhou
   *
   * @var string
   */
  protected $Region = null;
  /**
   * 存储桶名称
   *
   * @var string
   */
  protected $Bucket = null;
  /**
   * 请求的主机，腾讯云的
   *
   * @var string
   */
  protected $Host = "aliyuncs.com";

  public function __construct($SecretId, $SecretKey, $Region, $Bucket)
  {
    $this->Bucket = $Bucket;
    $this->Region = $Region;

    parent::__construct($SecretId, $SecretKey);
  }
}
