<?php

namespace gstudio_kernel\Platform\Wechat\OfficialAccount;

/**
 * 微信公众号模板消息
 * @inheritDoc https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Template_Message_Interface.html
 * 
 */
class TemplateMessage extends WechatOfficialAccount
{
  /**
   * 设置所属行业
   * 设置行业可在微信公众平台后台完成，每月可修改行业1次，帐号仅可使用所属行业中相关的模板。
   *
   * @param string $industry_id1 公众号模板消息所属行业编号
   * @param string $industry_id2 公众号模板消息所属行业编号
   * @return array
   */
  public function setIndustry($industry_id1, $industry_id2)
  {
    return $this->post("cgi-bin/template/api_set_industry", [
      "industry_id1" => $industry_id1,
      "industry_id2" => $industry_id2,
    ]);
  }
  /**
   * 获取设置的行业信息
   * 获取帐号设置的行业信息。可登录微信公众平台，在公众号后台中查看行业信息。
   *
   * @return array
   */
  public function getIndustry()
  {
    return $this->get("cgi-bin/template/get_industry");
  }
  /**
   * 获得模板ID
   * 从行业模板库选择模板到帐号后台，获得模板 ID 的过程可在微信公众平台后台完成。
   *
   * @param string $template_id_short 模板库中模板的编号，有“TM**”和“OPENTMTM**”等形式
   * @return array
   */
  public function getTemplateId($template_id_short)
  {
    return $this->post("cgi-bin/template/api_add_template", [
      "template_id_short" => $template_id_short
    ]);
  }
  /**
   * 获取模板列表
   * 获取已添加至帐号下所有模板列表，可在微信公众平台后台中查看模板列表信息。
   *
   * @return array
   */
  public function getAllPrivateTemplate()
  {
    return $this->get("cgi-bin/template/get_all_private_template");
  }
  /**
   * 删除模板
   *
   * @param string $template_id 公众帐号下模板消息ID
   * @return array
   */
  public function deletePrivateTemplate($template_id)
  {
    return $this->post("cgi-bin/template/del_private_template");
  }
  /**
   * 发送模板消息
   * url和 miniprogram 都是非必填字段，若都不传则模板无跳转；若都传，会优先跳转至小程序。开发者可根据实际需要选择其中一种跳转方式即可。当用户的微信客户端版本不支持跳小程序时，将会跳转至url。
   *
   * @param string $touser 接收者openid
   * @param string $template_id 模板ID
   * @param array $data 模板数据
   * @param string $color 	模板内容字体颜色，不填默认为黑色
   * @param string $client_msg_id 防重入id。对于同一个openid + client_msg_id, 只发送一条消息,10分钟有效,超过10分钟不保证效果。若无防重入需求，可不填
   * @param string $url 模板跳转链接（海外帐号没有跳转能力）
   * @param string $miniprogram 	跳小程序所需数据，不需跳小程序可不用传该数据
   * @param string $appid 所需跳转到的小程序appid（该小程序 appid 必须与发模板消息的公众号是绑定关联关系，暂不支持小游戏）
   * @param string $pagepath 所需跳转到小程序的具体页面路径，支持带参数,（示例index?foo=bar），要求该小程序已发布，暂不支持小游戏
   * @return array
   */
  public function send($touser, $template_id, $data, $color = null, $client_msg_id = null, $url = null, $miniprogram = null, $appid = null, $pagepath = null)
  {
    return $this->post("cgi-bin/message/template/send", [
      "touser" => $touser,
      "template_id" => $template_id,
      "data" => $data,
      "color" => $color,
      "client_msg_id" => $client_msg_id,
      "url" => $url,
      "miniprogram" => $miniprogram,
      "appid" => $appid,
      "pagepath" => $pagepath,
    ])->https(false)->getData();
  }
}
