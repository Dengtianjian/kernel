<?php

namespace kernel\Platform\Wechat\Miniprogram;

/**
 * 小程序用户相关接口
 *
 * 封装小程序用户信息相关服务端接口（插件用户 openpid、加密信息校验、
 * 支付后获取 unionid、用户 encryptKey、手机号等）。
 *
 * 这些接口都需要 access_token，由父类 WechatApi 自动获取并注入，
 * 且只允许在服务端调用。方法返回微信原始响应数组，失败时含 errcode/errmsg。
 */
class User extends WechatMiniProgramApi
{
  /**
   * 获取插件用户 openpid
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/user-info/basic-info/api_getpluginopenpid.html
   *
   * 通过 wx.pluginLogin 获得插件用户标志凭证 code 后，在服务端换取插件用户的唯一标识 openpid。
   *
   * @param string $code 插件用户标志凭证（wx.pluginLogin 获得），5 分钟内有效，一个 code 只能获取一次
   * @return array 微信响应数组，成功时含 openpid
   */
  function getPluginOpenPId($code)
  {
    return $this->post("wxa/getpluginopenpid", [
      "code" => $code
    ])->getData();
  }
  /**
   * 检查加密信息
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/user-info/basic-info/api_checkencrypteddata.html
   *
   * 检查加密信息是否由微信生成（目前仅支持手机号加密数据），只能检测最近 3 天内生成的加密数据。
   * 加密数据由小程序端获取（如 getPhoneNumber），本方法会自动计算其 SHA-256 值（Hex 编码）。
   *
   * @param string $encryptedData 加密数据（encryptedData 原始字符串）
   * @return array 微信响应数组，成功时含 vaild（是否为合法数据，官方字段拼写即为 vaild）、create_time
   */
  function checkEncryptedData($encryptedData)
  {
    return $this->checkEncryptedMsg(hash("sha256", $encryptedData));
  }
  /**
   * 检查加密信息（直接传 SHA-256 值）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/user-info/basic-info/api_checkencrypteddata.html
   *
   * @param string $encryptedMsgHash 加密数据的 SHA-256 值（Hex/Base16 编码后的字符串）
   * @return array 微信响应数组，成功时含 vaild、create_time
   */
  function checkEncryptedMsg($encryptedMsgHash)
  {
    return $this->post("wxa/business/checkencryptedmsg", [
      "encrypted_msg_hash" => $encryptedMsgHash
    ])->getData();
  }
  /**
   * 支付后获取 unionid
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/user-info/basic-info/api_getpaidunionid.html
   *
   * 用户支付完成后，无需用户授权即可获取该用户的 unionid。
   *
   * 订单信息二选一：
   * - 传微信支付订单号：$transactionId
   * - 传商户订单号 + 商户号：$outTradeNo 与 $mchId
   *
   * @param string $openid 支付该笔订单的用户的 openid
   * @param string|null $transactionId 微信支付订单号
   * @param string|null $outTradeNo 商户订单号（需同时传 $mchId）
   * @param string|null $mchId 微信支付商户号（使用商户订单号查询时必传）
   * @return array 微信响应数组，成功时含 unionid
   */
  function getPaidUnionId($openid, $transactionId = null, $outTradeNo = null, $mchId = null)
  {
    $query = [
      "openid" => $openid
    ];
    if ($transactionId) {
      $query['transaction_id'] = $transactionId;
    } else {
      $query['out_trade_no'] = $outTradeNo;
      $query['mch_id'] = $mchId;
    }

    return $this->get("wxa/getpaidunionid", $query)->getData();
  }
  /**
   * 获取用户 encryptKey
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/user-info/internet/api_getuserencryptkey.html
   *
   * 用于解密小程序端通过 userCryptoManager.getLatestUserKey 加密的数据。
   *
   * @param string $openid 用户 openid
   * @param string $signature session_key 对空字符串做 hmac_sha256 签名的结果
   * @param string $sigMethod 签名方法，目前只支持 hmac_sha256
   * @return array 微信响应数组，成功时含 key_info_list（最近 3 次的密钥，每个存活 3600 秒）
   */
  function getUserEncryptKey($openid, $signature, $sigMethod = "hmac_sha256")
  {
    return $this->get("wxa/business/getuserencryptkey", [
      "openid" => $openid,
      "signature" => $signature,
      "sig_method" => $sigMethod
    ])->getData();
  }
  /**
   * 获取手机号
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/user-info/phone-number/api_getphonenumber.html
   *
   * 使用小程序端 getPhoneNumber 回调返回的动态令牌 code 换取用户手机号，无需使用 session_key 解密。
   * code 只能使用一次，5 分钟内有效。
   *
   * @param string $code 手机号获取凭证（前端 getPhoneNumber 回调的 detail.code）
   * @param string|null $openid 用户 openid，传入后会校验 openid 与 code 的绑定关系，不匹配时报错
   * @return array 微信响应数组，成功时含 phone_info（phoneNumber、purePhoneNumber、countryCode、watermark）
   */
  function getPhoneNumber($code, $openid = null)
  {
    $body = [
      "code" => $code
    ];
    if ($openid) {
      $body['openid'] = $openid;
    }

    return $this->post("wxa/business/getuserphonenumber", $body)->getData();
  }
}
