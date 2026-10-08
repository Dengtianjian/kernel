<?php

namespace kernel\Modules\DiscuzX\Foundation\Storage;

use kernel\Foundation\FileSystem\Storage\StorageSignature;
use kernel\Modules\DiscuzX\Foundation\DiscuzXPath;
use kernel\Modules\QCloud\QCloudCos\QCloudCOSStorage;

class DiscuzXQCloudCOSStorage extends QCloudCOSStorage
{
  /**
   * 实例化 DiscuzX 侧的 COS 存储
   *
   * 对象操作的客户端（{@see \kernel\Modules\QCloud\QCloudCos\QCloudCosClient}）与 STS 客户端
   * 都由父类 {@see QCloudCOSStorage::boot()} 初始化（挂在父类的 `$client` / `$stsClient` 上），
   * 本类不再自行创建客户端。
   *
   * @param string $secretId 密钥 ID
   * @param string $secretKey 密钥
   * @param string $region 存储桶所在的地区
   * @param string $bucket 存储桶名称
   */
  public function __construct(
    $secretId,
    $secretKey,
    $region,
    $bucket
  ) {
    $this->basePath = DiscuzXPath::storage();

    parent::__construct($secretId, $secretKey, $region, $bucket);
  }
}
