<?php

namespace kernel\DiscuzX\Foundation\Storage;

use kernel\Providers\Storage\StorageSignature;
use kernel\DiscuzX\Foundation\DiscuzXPath;
use kernel\Providers\Cloud\QCloudAccount;
use kernel\Providers\Cloud\QCloudCos\QCloudCOSStorage;

class DiscuzXQCloudCOSStorage extends QCloudCOSStorage
{
  /**
   * 实例化 DiscuzX 侧的 COS 存储
   *
   * 对象操作的客户端（{@see \kernel\Providers\Cloud\QCloudCos\QCloudCosClient}）与 STS 客户端
   * 都由父类 {@see QCloudCOSStorage::boot()} 初始化（挂在父类的 `$client` / `$stsClient` 上），
   * 本类不再自行创建客户端。
   *
   * @param QCloudAccount $account 腾讯云账号，提供 SecretId / SecretKey 等凭据
   * @param string $region 存储桶所在的地区
   * @param string $bucket 存储桶名称
   */
  public function __construct(
    QCloudAccount $account,
    $region,
    $bucket
  ) {
    $this->basePath = DiscuzXPath::storage();

    parent::__construct($account, $region, $bucket);
  }
}
