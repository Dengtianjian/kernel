<?php

namespace kernel\Platform\Wechat;

use kernel\Foundation\Module\Module;

class WechatModule extends Module
{
  protected $name = "wechat";
  protected $accounts = [];

  public function account(string $appIdOrAlias)
  {
    return $this->account[$appIdOrAlias];
  }
  /**
   * 添加账号
   * @param WechatAccount $account
   * @param string $alias
   * @return static
   */
  public function add($account, $alias = null)
  {
    $this->accounts[$alias ?: $account->appId()] = $account;
    return $this;
  }
  public function remove($appIdOrAlias)
  {
    unset($this->accounts[$appIdOrAlias]);

    return $this;
  }
  public function exists($appIdOrAlias)
  {
    return array_key_exists($appIdOrAlias, $this->accounts);
  }
}
