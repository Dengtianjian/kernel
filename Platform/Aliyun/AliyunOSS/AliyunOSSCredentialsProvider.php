<?php

namespace kernel\Platform\Aliyun\AliyunOSS;

use OSS\Credentials\Credentials;
use OSS\Credentials\CredentialsProvider;

class AliyunOSSCredentialsProvider implements CredentialsProvider
{
  protected $accessKeyId = null;
  protected $accessKeySecret = null;
  protected $token = null;
  public function __construct($AccessKeyId, $AccessKeySecret, $Token = null)
  {
    $this->accessKeyId = $AccessKeyId;
    $this->accessKeySecret = $AccessKeySecret;
    $this->token = $Token;
  }
  /**
   * @return Credentials
   * @throws OssException
   */
  public function getCredentials()
  {
    return new Credentials($this->accessKeyId, $this->accessKeySecret, $this->token);
  }
}
