<?php

namespace kernel\Platform\Wechat\Miniprogram;

/**
 * 微信学生身份快速验证
 *
 * 对应微信「微信学生身份快速验证」分类。
 */
class StudentIdentity extends WechatMiniProgramApi
{
  /**
   * 快速获取学生身份（POST /intp/quickcheckstudentidentity）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/student/api_quickcheckstudentidentity.html
   *
   * @param array $data 请求体，字段（如 name、id_card 等）以官方文档为准
   * @return array 微信响应数组，成功时含学生身份校验结果
   */
  function quickCheckStudentIdentity($data = [])
  {
    return $this->post("intp/quickcheckstudentidentity", $data)->getData();
  }
}
