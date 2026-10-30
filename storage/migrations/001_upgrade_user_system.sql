-- 打卡系统升级：用户体系 + 公告弹窗
-- 执行时间：2026-05-26

-- 1. 用户表
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '姓名',
  `mobile` varchar(20) NOT NULL COMMENT '手机号',
  `email` varchar(100) NOT NULL COMMENT '邮箱',
  `password_hash` varchar(255) NOT NULL COMMENT '密码哈希',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `role` varchar(20) NOT NULL DEFAULT 'user' COMMENT '角色：admin/user',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '状态：1正常 0停用',
  `last_login_at` datetime DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` varchar(45) DEFAULT NULL COMMENT '最后登录IP',
  `wechat_openid` varchar(100) DEFAULT NULL COMMENT '微信openid(预留)',
  `wechat_unionid` varchar(100) DEFAULT NULL COMMENT '微信unionid(预留)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mobile` (`mobile`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 2. 用户登录日志
CREATE TABLE IF NOT EXISTS `user_login_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `login_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录日志';

-- 3. 公告弹窗表
CREATE TABLE IF NOT EXISTS `popup_announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL COMMENT '公告内容(Markdown)',
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `created_by` bigint(20) unsigned DEFAULT NULL COMMENT '创建人admin_id',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告弹窗表';

-- 4. 公告阅读记录
CREATE TABLE IF NOT EXISTS `popup_read_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `popup_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `read_date` date NOT NULL COMMENT '阅读日期',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_popup_user_date` (`popup_id`, `user_id`, `read_date`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告阅读记录';

-- 5. members 表新增字段
ALTER TABLE `members`
  ADD COLUMN `user_id` bigint(20) unsigned DEFAULT NULL COMMENT '关联用户ID' AFTER `admin_id`,
  ADD COLUMN `is_bound` tinyint(4) NOT NULL DEFAULT 0 COMMENT '是否已绑定用户' AFTER `remark`,
  ADD COLUMN `bind_time` datetime DEFAULT NULL COMMENT '绑定时间' AFTER `is_bound`,
  ADD COLUMN `source_type` varchar(20) NOT NULL DEFAULT 'admin_create' COMMENT '来源：admin_create/user_register/pending_confirm' AFTER `bind_time`;

ALTER TABLE `members`
  ADD KEY `idx_user_id` (`user_id`);
