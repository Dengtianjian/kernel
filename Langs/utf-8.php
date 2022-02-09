<?php

use kernel\Foundation\Lang;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

$langs = [
  "common" => [
    "install" => "安装",
    "uninstall" => "卸载",
    "upgrade" => "升级",
    "update" => "更新",
    "version" => "版本",
    "symbol" => [
      "colon" => "："
    ]
  ],
  "kernel" => [
    "all" => "全部",
    "footer_copyright" => "COOOCC 版权所有",
    "backend" => "后台",
    "logo_text" => "插件管理后台",
    "site_homepage" => "站点首页",
    "addon_center" => "应用中心",
    "please_choose" => "请选择",
    "form_color_input_placeholder" => "请输入16进制颜色值 例如 #AABBCC",
    "save" => "保存",
    "choose_upload_img_type_png_jpg_jpeg" => "请选择png、jpg、jpeg 类型的文件",
    "dictionary_file_does_not_exist" => "未找到 " . CHARSET . " 编码的语言包文件",
    "llleal_submission" => "非法提交",
    "saved_successfully" => "保存成功",
    "not_logged_in" => "未登录",
    "authentication_failed_need_to_log_in_again" => "验证失败，请重新登录",
    "login_has_expired_please_log_in_again" => "登录已过期，请重新登录",
    "unauthorized_access" => "非法访问",
    "need_to_install_the_core_plugin" => "请前往应用中心安装核心插件",
    "route_does_not_exits" => "路由不存在",
    "method_not_allowed" => "未允许的请求方法",
    "turn_on" => "开启",
    "close" => "关闭",
    "lllegal_submission" => "非法提交",
    "clean_set_image" => "清除图片",
    "service_tablename_empty" => "Service的TableName未填写",
    "insert_data_must_an_assoc_array" => "insert方法传入的参数必须是关联数组",
    "no_settings" => "暂无设置项",
    "switch_app" => "切换应用",
    "switch" => "切换",
    "view_template_file_not_exist" => "视图模板文件不存在",
    "experimenttal_feature_not_turned_on" => "实验性功能，暂不开放",
    "function_under_development_not_yet_open" => "开发中的功能，暂不开放",
    "middleware_execution_error" => "中间件执行错误",
    "homepage" => "首页",
    "extension" => "扩展",
    "serverError" => "服务器错误",
    "extension_list" => "扩展列表",
    "extension_new_technology_tips" => "❗本页采用较新的技术开发，请尽量使用Chrome浏览器访问，并且保持浏览器更新到最新版本。如扩展图标不显示请点击页面右侧悬浮图标😀联系客服，🙌谢谢。",
    "extension_no_install_any" => "还未安装任何扩展哦~",
    "extensionTurnOn" => "开启",
    "extensionClose" => "关闭",
    "extensionLastUpgradeTime" => "最后一次升级时间：",
    "extensionNotExists" => "扩展不存在",
    "extensionDoNotInstall" => "扩展已安装，请勿重复安装",
    "extensionFileCorrupted" => "扩展文件已损坏或不存在，请重新安装扩展",
    "extensionAlreadyOn" => "当前扩展已是开启状态",
    "extensionClosed" => "当前扩展已是关闭状态",
    "extensionNoNeedToUpgrade" => "扩展已是最新版，无需升级",
    "extensionInstalling" => "安装中，请稍后",
    "extensionInstalledSuccessfully" => "安装成功",
    "extensionUpdatingData" => "更新数据中，请稍后",
    "extensionUpgrading" => "更新中，请稍后",
    "extensionUpdateSuccessed" => "更新成功",
    "extensionUninstalling" => "卸载中，请稍后",
    "extensionClosing" => "关闭中，请稍后",
    "extensionOpening" => "开启中，请稍后",
    "extensionClosed" => "已关闭",
    "extensionTurnedOn" => "已开启",
  ]
];

Lang::add($langs);
