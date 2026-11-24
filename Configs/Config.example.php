<?php

/**
 * 本地开发配置模板
 *
 * 基线 `Config.php` 的 `mode` 是 `production`（发布包直接用基线，不经任何改写）。
 * 需要开发态调试行为时，把本文件复制一份为同目录下的 `Config.local.php`：
 *
 * ```
 * cp Configs/Config.example.php Configs/Config.local.php
 * ```
 *
 * `Config.local.php` 已在 `.gitignore` 与 `PackageCommand::IGNORE` 里——既不进仓库，
 * 也不随发布包发出。修改本模板不会影响任何运行态，它只被人工复制使用。
 */
return [
  "mode" => "development", //* production development
];
