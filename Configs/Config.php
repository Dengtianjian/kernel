<?php

return [
  "mode" => "development", //* production development
  "version" => "0.5.8.20260926.1810",
  "extensions" => false,
  //* Redis 连接参数：由 RedisService 读取，只在首次取连接时使用（登记 Provider 不会连）
  "redis" => [
    "host" => "127.0.0.1",
    "port" => 6379,
    "database" => 0,
    "timeout" => 0,
    "persistentId" => null,
    "retryInterval" => 0,
    "readTimeout" => 0,
    "password" => null,
  ]
];
