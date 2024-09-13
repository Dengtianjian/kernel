<?php

namespace kernel\Foundation\Storage;

abstract class AbstractOSSStroage extends AbstractStorage
{
  protected $platform = null;
  protected $client = null;
  protected $stsClient = null;
  protected $sdkClient = null;
  protected $bucket = null;
  protected $region = null;

  protected $secretId = null;
  protected $secretKey = null;

  public function __construct($secretId, $secretKey, $region, $bucket, $SignatureKey = "ruyi_storage", $RoutePrefix = "files", $BaseURL = F_BASE_URL, $Platform = "local")
  {
    $this->secretId = $secretId;
    $this->secretKey = $secretKey;
    $this->region = $region;
    $this->bucket = $bucket;

    $this->loadSDK();

    parent::__construct($SignatureKey, $RoutePrefix, $BaseURL, $Platform);
  }

  protected function loadSDK()
  {

    return $this;
  }

  public function bucket($name = null)
  {
    if (func_num_args()) {
      $this->bucket = $name;
      return $this;
    };

    return $this->bucket;
  }
  public function region($name = null)
  {
    if (func_num_args()) {
      $this->region = $name;

      $this->loadSDK();

      return $this;
    };

    return $this->region;
  }
}
