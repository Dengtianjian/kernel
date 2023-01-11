<?php

namespace gstudio_kernel\Platform\Discuzx;

use gstudio_kernel\Foundation\Data\Arr;
use gstudio_kernel\Foundation\Response;

class DiscuzXResponse extends Response
{
  static public function xml($value)
  {
    header("Content-Type: text/xml; charset=" . strtolower(CHARSET));
    echo Arr::toXML($value, true, "root");
    exit;
  }
}
