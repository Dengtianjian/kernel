<?php

namespace kernel\Modules\WechatPay\V3;


/**
 * 微信支付 APIv3 账单
 *
 * APIv3 的账单是「先申请、再下载」两步：申请接口返回 `download_url`，再按该地址下载文件。
 * 本类把两步都收齐——申请交易账单、申请资金账单、下载账单。
 *
 * 注意：下载账单返回的是**账单文件本身**（CSV 文本，或 GZIP 压缩后的二进制），不是 JSON，
 * 因此 {@see download()} 走非 JSON 分支、直接给出原始响应内容。
 * 分账单（申请分账账单）属于分账领域，见 {@see WechatPayProfitSharingApi::bill()}。
 */
class WechatPayBillApi extends WechatPayV3Api
{
  /**
   * 申请交易账单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_6.shtml
   *
   * 账单日期仅支持近三个月内；生成时间按官方说明通常为次日，当日账单当天申请会返回错误。
   *
   * @param string $billDate 账单日期，格式 yyyy-MM-DD
   * @param string $billType 账单类型：ALL（默认，全部）/ SUCCESS（支付成功）/ REFUND（退款）/ RECHARGE_REFUND（充值退款）
   * @param string|null $tarType 压缩类型，传 GZIP 时下载地址返回的是压缩包；默认不压缩
   * @return array|boolean 成功时返回含 hash_type、hash_value、download_url
   */
  public function tradeBill($billDate, $billType = "ALL", $tarType = null)
  {
    $query = [
      "bill_date" => $billDate,
      "bill_type" => $billType
    ];

    return $this->request("GET", "/v3/bill/tradebill", [], $this->withTarType($query, $tarType));
  }

  /**
   * 申请资金账单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_7.shtml
   *
   * 资金账单记录账户资金流水（提现、充值、退款等），与交易账单的区别是不含具体交易明细。
   *
   * @param string $billDate 账单日期，格式 yyyy-MM-DD
   * @param string $accountType 资金账户类型：BASIC（默认，基本账户）/ OPERATION（运营账户）/ ALL（全部账户）
   * @param string|null $tarType 压缩类型，传 GZIP 时下载地址返回的是压缩包；默认不压缩
   * @return array|boolean 成功时返回含 hash_type、hash_value、download_url
   */
  public function fundFlowBill($billDate, $accountType = "BASIC", $tarType = null)
  {
    $query = [
      "bill_date" => $billDate,
      "account_type" => $accountType
    ];

    return $this->request("GET", "/v3/bill/fundflowbill", [], $this->withTarType($query, $tarType));
  }

  /**
   * 下载账单
   * @link https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_8.shtml
   *
   * 按申请接口返回的 download_url 原样下载；该地址带 token，不能自行拼接。
   * 下载同样需要 APIv3 鉴权头，因此这里解析出 URL 的路径与查询串后走统一的签名请求，
   * 而不是直接把地址丢给 CURL。
   *
   * @param string $downloadUrl 申请账单接口返回的 download_url
   * @return array|boolean 成功时返回账单文件内容（CSV 文本；若申请时传了 GZIP 则为压缩后的二进制串）
   */
  public function download($downloadUrl)
  {
    $path = parse_url($downloadUrl, PHP_URL_PATH);
    parse_str((string) parse_url($downloadUrl, PHP_URL_QUERY), $query);

    return $this->request("GET", $path, [], $query, false);
  }

  /**
   * 按需补上 tar_type 查询参数（内部使用）
   *
   * @param array $query 已有查询参数
   * @param string|null $tarType 压缩类型，为空时不附加
   * @return array
   */
  protected function withTarType($query, $tarType)
  {
    if ($tarType !== null && $tarType !== "") {
      $query['tar_type'] = $tarType;
    }

    return $query;
  }
}
