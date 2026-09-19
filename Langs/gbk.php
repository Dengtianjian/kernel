<?php

use kernel\Foundation\Lang;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

$langs = [
  "common" => [
    "install" => "��װ",
    "uninstall" => "ж��",
    "upgrade" => "����",
    "update" => "����",
    "version" => "�汾",
    "symbol" => [
      "colon" => "��"
    ]
  ],
  "kernel" => [
    "all" => "ȫ��",
    "footer_copyright" => "COOOCC ��Ȩ����",
    "backend" => "��̨",
    "logo_text" => "���������̨",
    "site_homepage" => "վ����ҳ",
    "addon_center" => "Ӧ������",
    "please_choose" => "��ѡ��",
    "form_color_input_placeholder" => "������16������ɫֵ ���� #AABBCC",
    "save" => "����",
    "choose_upload_img_type_png_jpg_jpeg" => "��ѡ��png��jpg��jpeg ���͵��ļ�",
    "dictionary_file_does_not_exist" => "δ�ҵ� " . CHARSET . " ��������԰��ļ�",
    "llleal_submission" => "�Ƿ��ύ",
    "saved_successfully" => "����ɹ�",
    "not_logged_in" => "δ��¼",
    "authentication_failed_need_to_log_in_again" => "��֤ʧ�ܣ������µ�¼",
    "login_has_expired_please_log_in_again" => "��¼�ѹ��ڣ������µ�¼",
    "unauthorized_access" => "�Ƿ�����",
    "need_to_install_the_core_plugin" => "��ǰ��Ӧ�����İ�װ���Ĳ��",
    "route_does_not_exits" => "·�ɲ�����",
    "method_not_allowed" => "δ���������󷽷�",
    "turn_on" => "����",
    "close" => "�ر�",
    "lllegal_submission" => "�Ƿ��ύ",
    "clean_set_image" => "���ͼƬ",
    "service_tablename_empty" => "Service��TableNameδ��д",
    "insert_data_must_an_assoc_array" => "insert��������Ĳ��������ǹ�������",
    "no_settings" => "����������",
    "switch_app" => "�л�Ӧ��",
    "switch" => "�л�",
    "view_template_file_not_exist" => "��ͼģ���ļ�������",
    "experimenttal_feature_not_turned_on" => "ʵ���Թ��ܣ��ݲ�����",
    "function_under_development_not_yet_open" => "�����еĹ��ܣ��ݲ�����",
    "middleware_execution_error" => "�м��ִ�д���",
    "homepage" => "��ҳ",
    "extension" => "��չ",
    "serverError" => "����������",
    "extension_list" => "��չ�б�",
    "extension_new_technology_tips" => "?��ҳ���ý��µļ����������뾡��ʹ��Chrome��������ʣ����ұ�����������µ����°汾������չͼ�겻��ʾ����ҳ���Ҳ�����ͼ��?��ϵ�ͷ���?лл��",
    "extension_no_install_any" => "��δ��װ�κ���չŶ~",
    "extensionTurnOn" => "����",
    "extensionClose" => "�ر�",
    "extensionLastUpgradeTime" => "���һ������ʱ�䣺",
    "extensionNotExists" => "��չ������",
    "extensionDoNotInstall" => "��չ�Ѱ�װ�������ظ���װ",
    "extensionFileCorrupted" => "��չ�ļ����𻵻򲻴��ڣ������°�װ��չ",
    "extensionAlreadyOn" => "��ǰ��չ���ǿ���״̬",
    "extensionClosed" => "��ǰ��չ���ǹر�״̬",
    "extensionNoNeedToUpgrade" => "��չ�������°棬��������",
    "extensionInstalling" => "��װ�У����Ժ�",
    "extensionInstalledSuccessfully" => "��װ�ɹ�",
    "extensionUpdatingData" => "���������У����Ժ�",
    "extensionUpgrading" => "�����У����Ժ�",
    "extensionUpdateSuccessed" => "���³ɹ�",
    "extensionUninstalling" => "ж���У����Ժ�",
    "extensionClosing" => "�ر��У����Ժ�",
    "extensionOpening" => "�����У����Ժ�",
    "extensionClosed" => "�ѹر�",
    "extensionTurnedOn" => "�ѿ���",
    "languages" => [
      "zh" => "����",
      "en" =>    "Ӣ��",
      "yue" =>    "����",
      "wyw" =>   "������",
      "jp" =>    "����",
      "kor" =>    "����",
      "fra" =>    "����",
      "spa" =>  "��������",
      "th" =>    "̩��",
      "ara" =>  "��������",
      "ru" =>    "����",
      "pt" =>  "��������",
      "de" =>    "����",
      "it" =>  "�������",
      "el" =>   "ϣ����",
      "nl" =>   "������",
      "pl" =>   "������",
      "bul" => "����������",
      "est" => "��ɳ������",
      "dan" =>   "������",
      "fin" =>   "������",
      "cs" =>   "�ݿ���",
      "rom" => "����������",
      "slo" => "˹����������",
      "swe" =>   "�����",
      'hu' =>  "��������",
      "cht" =>  "��������",
      "vie" =>   "Խ����",
    ],
    "attachments" => [
      "notExist" => "���������ڻ���ɾ��",
      "notExistDetails" => "������¼������",
      "deletedFailed" => "����ɾ��ʧ��",
      "pleaseUploadFile" => "���ϴ��ļ�"
    ],
    "request" => [
      "disallowGetRequests" => "��ֹGET����"
    ],
    "controller" => [
      "asyncMethodIsMissing" => "������ȱʧasync����",
      "dataOrPostMethodIsMissing" => "������ȱ��data|post����",
      "dataMethodIsMissing" => "������ȱ��data����",
    ],
    "file" => [
      "saveFailed" => "�����ļ�ʧ��"
    ],
    "serializer" => [
      "ruleExist" => "���л������Ѿ�����",
      "ruleNotExist" => "���л����򲻴���",
    ],
    "validator" => [
      "pleaseInput" => "������",
      "verifyFailed" => "У��ʧ��"
    ],
    "auth" => [
      "needLogin" => "���¼������",
      "emptyToken" => "��TOKEN",
      "headerTOKENParamError" => "ͷ����token��������",
      "headerAuthorizationEmpty" => "ͷ��Authorizationֵ�ǿյ�",
      "invalidToken" => "token��Ч",
      "expiredToken" => "tokenʧЧ",
      "loginExpired" => "��¼�ѹ��ڣ������µ�¼",
      "noAccess" => "��Ȩ����",
      "insufficientPermissions" => "Ȩ�޲���"
    ]
  ]
];

Lang::add($langs);
