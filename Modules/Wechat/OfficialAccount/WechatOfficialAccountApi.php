<?php

namespace kernel\Platform\Wechat\OfficialAccount;

use kernel\Platform\Wechat\WechatApi;

/**
 * 微信公众号 API 基类
 *
 * 公众号侧各接口类的公共基类，与小程序侧的 {@see \kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi} 对称：
 * 继承 {@see WechatApi}，通过微信公众号账号（WechatAccount）获取 access_token 并自动注入请求。
 */
class WechatOfficialAccountApi extends WechatApi {}
