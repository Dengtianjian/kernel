-- ----------------------------
-- Table structure for pre_kernel_access_token
-- ----------------------------
DROP TABLE IF EXISTS `pre_kernel_access_token`;
CREATE TABLE `pre_kernel_access_token`  (
  `accessToken` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'access_token',
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `platform` enum('wechatOfficialAccount','dingtalk') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '����������ƽ̨',
  `createdAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '����ʱ��',
  `expiredAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '����ʱ��',
  `expires` varchar(6) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '��Ч��',
  `appId` varchar(60) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '������ƽ̨��appid',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '������ƽ̨��AccessToken' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for pre_kernel_extensions
-- ----------------------------
DROP TABLE IF EXISTS `pre_kernel_extensions`;

CREATE TABLE `pre_kernel_extensions` (
  `id` int(12) NOT NULL AUTO_INCREMENT COMMENT '����',
  `install_time` int(13) NULL DEFAULT NULL COMMENT '��װʱ��',
  `upgrade_time` int(13) NULL DEFAULT NULL COMMENT '����ʱ��',
  `local_version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '���ذ汾',
  `plugin_id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '�������id��kernel����ϵͳ��չ',
  `extension_id` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '��չid',
  `enabled` tinyint(1) NULL DEFAULT NULL COMMENT '�ѿ���',
  `installed` tinyint(4) NULL DEFAULT NULL COMMENT '�Ѱ�װ',
  `path` varchar(535) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '��չ��·��',
  `parent_id` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '����չID',
  `created_time` int(13) NULL DEFAULT NULL COMMENT '��¼����ʱ��',
  `name` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '��չ����',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `extension_id`(`extension_id`) USING BTREE,
  INDEX `plugin_id`(`plugin_id`) USING BTREE,
  INDEX `parent_id`(`parent_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for pre_kernel_logins
-- ----------------------------
DROP TABLE IF EXISTS `pre_kernel_logins`;

CREATE TABLE `pre_kernel_logins` (
  `id` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'id',
  `token` varchar(260) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'tokenֵ',
  `expiration` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '��Ч����',
  `userId` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '�����û�',
  `appId` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '�������ID�����Ϊ�ռ�Ϊͨ��',
  `createdAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '����ʱ��',
  `updatedAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '������ʱ��',
  `deletedAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'ɾ��ʱ��',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for pre_kernel_wechat_users
-- ----------------------------
DROP TABLE IF EXISTS `pre_kernel_wechat_users`;

CREATE TABLE `pre_kernel_wechat_users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `memberId` bigint(20) NULL DEFAULT NULL COMMENT '���󶨵Ļ�ԱID',
  `openId` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'openId',
  `unionId` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'unionId',
  `phone` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '�ֻ�����',
  `createdAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '����ʱ��',
  `updatedAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '������ʱ��',
  `deletedAt` varchar(12) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '��ɾ��ʱ��',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `memberId`(`memberId`) USING BTREE COMMENT '��ԱID����',
  INDEX `unionId`(`unionId`) USING BTREE COMMENT 'UnionId����',
  INDEX `openId`(`openId`) USING BTREE COMMENT 'OpenId����',
  INDEX `phone`(`phone`) USING BTREE COMMENT '�ֻ�������'
) ENGINE = InnoDB AUTO_INCREMENT = 21 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '΢���û���' ROW_FORMAT = Dynamic;