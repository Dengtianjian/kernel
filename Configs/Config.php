<?php

return [
  /**
   * 基线即生产：发布包直接取自本目录，不做任何改写。
   * 本地开发用 `Config.local.php` / `Config.development.php` 声明 development 覆盖——
   * 这两层会被打包忽略（见 PackageCommand::IGNORE），不会随包发布。
   * 五层覆盖顺序：Config.php → development → local → production → release（后读覆盖先读）。
   */
  "mode" => "production", //* production development
  "version" => "0.6.0.20261115.0849"
];
