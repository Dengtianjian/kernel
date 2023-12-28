<?php

namespace kernel\Platform\DiscuzX\Model;

use kernel\Model\FilesModel;

class DiscuzXFilesModel extends FilesModel
{
  function __construct()
  {
    $this->tableName = F_APP_ID . "_files";
  }
}
