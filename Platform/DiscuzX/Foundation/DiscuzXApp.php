<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\App;

class DiscuzXApp extends App
{
  public function hook($uri)
  {
    $this->request->set($uri, "get");
  }
}
