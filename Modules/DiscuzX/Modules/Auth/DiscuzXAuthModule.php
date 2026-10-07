<?php

namespace kernel\Modules\DiscuzX\Modules\Auth;

use kernel\Modules\Auth\AuthModule;

class DiscuzXAuthModule extends AuthModule
{
  protected $name = "auth";
  public function onBoot()
  {
    getApp()->middleware()->set(GlobalDiscuzXAuthMiddleware::class);
    $this->loginsModel = new DiscuzXLoginsModel();
    $this->deleteExpiredTokens();
  }
}
