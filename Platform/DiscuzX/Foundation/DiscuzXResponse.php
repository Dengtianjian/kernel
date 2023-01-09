<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Response;

class DiscuzXResponse extends Response
{
  static public function xml($value)
  {
    header("Content-Type: text/xml; charset=" . strtolower(CHARSET));
    echo Arr::toXML($value, true, "root");
    exit;
  }
}
