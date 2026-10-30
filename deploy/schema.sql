-- 任务打卡系统：完整表结构 + 运行必需的基础数据（取自线上 dk.amjhly.com 2026-10-30，不含业务数据）。可重复执行。
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `admin_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `role_name` varchar(50) NOT NULL COMMENT '角色名称',
  `permissions_json` json DEFAULT NULL COMMENT '权限JSON',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态：1启用 0禁用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员角色表';

CREATE TABLE IF NOT EXISTS `admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `role_id` bigint(20) unsigned DEFAULT NULL COMMENT '角色ID',
  `username` varchar(50) NOT NULL COMMENT '登录账号',
  `password_hash` varchar(255) NOT NULL COMMENT '密码哈希',
  `real_name` varchar(50) DEFAULT NULL COMMENT '真实姓名',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机号',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态：1启用 0禁用',
  `owner_user_id` bigint(20) unsigned DEFAULT NULL COMMENT '发布者账号归属用户ID(NULL为平台管理员)',
  `last_login_at` datetime DEFAULT NULL COMMENT '最后登录时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_owner_user` (`owner_user_id`),
  CONSTRAINT `fk_admins_role_id` FOREIGN KEY (`role_id`) REFERENCES `admin_roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

CREATE TABLE IF NOT EXISTS `app_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'pk',
  `setting_key` varchar(120) NOT NULL COMMENT 'setting key',
  `setting_value` text COMMENT 'setting value',
  `updated_by` bigint(20) unsigned DEFAULT NULL COMMENT 'last updater admin id',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created at',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated at',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='global app settings';

CREATE TABLE IF NOT EXISTS `card_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL COMMENT '卡密',
  `plan_id` bigint(20) unsigned NOT NULL COMMENT '绑定套餐',
  `status` enum('unused','used') NOT NULL DEFAULT 'unused',
  `used_by` bigint(20) unsigned DEFAULT NULL COMMENT '使用者user_id',
  `used_order_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` datetime DEFAULT NULL,
  `batch_no` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_plan_status` (`plan_id`,`status`),
  KEY `idx_batch` (`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='卡密表';

CREATE TABLE IF NOT EXISTS `checkin_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `record_id` bigint(20) unsigned NOT NULL COMMENT '打卡记录ID',
  `image_path` varchar(255) NOT NULL COMMENT '图片路径',
  `image_thumb_path` varchar(255) DEFAULT NULL COMMENT '缩略图路径',
  `image_sort` int(11) NOT NULL DEFAULT '1' COMMENT '排序值',
  `file_size` bigint(20) DEFAULT NULL COMMENT '文件大小（字节）',
  `mime_type` varchar(50) DEFAULT NULL COMMENT '文件类型',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_record_id` (`record_id`),
  CONSTRAINT `fk_checkin_images_record_id` FOREIGN KEY (`record_id`) REFERENCES `checkin_records` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='打卡图片表';

CREATE TABLE IF NOT EXISTS `checkin_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `task_id` bigint(20) unsigned NOT NULL COMMENT '任务ID',
  `cycle_id` bigint(20) unsigned DEFAULT NULL COMMENT '周期ID',
  `member_id` bigint(20) unsigned NOT NULL COMMENT '人员ID',
  `task_date` date NOT NULL COMMENT '任务日期',
  `submit_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
  `is_valid` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否有效：1有效 0无效',
  `is_overridden` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否已被覆盖：1是 0否',
  `overridden_by_record_id` bigint(20) unsigned DEFAULT NULL COMMENT '被哪条记录覆盖',
  `submit_source` varchar(30) NOT NULL DEFAULT 'web' COMMENT '来源：web/wechat_browser/mobile_browser/pc',
  `client_ip` varchar(64) DEFAULT NULL COMMENT '客户端IP',
  `user_agent` varchar(500) DEFAULT NULL COMMENT '客户端UA',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `deleted_at` datetime DEFAULT NULL COMMENT '软删除时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_task_date` (`task_date`),
  KEY `idx_submit_time` (`submit_time`),
  KEY `idx_is_valid` (`is_valid`),
  KEY `idx_is_overridden` (`is_overridden`),
  CONSTRAINT `fk_checkin_records_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  CONSTRAINT `fk_checkin_records_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='打卡记录表';

CREATE TABLE IF NOT EXISTS `member_group_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'pk',
  `admin_id` bigint(20) unsigned NOT NULL COMMENT 'admin id',
  `member_id` bigint(20) unsigned NOT NULL COMMENT 'member id',
  `group_id` bigint(20) unsigned NOT NULL COMMENT 'group id',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created at',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_member_group` (`member_id`,`group_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_admin_member_id` (`admin_id`,`member_id`),
  KEY `idx_admin_group_id` (`admin_id`,`group_id`),
  KEY `idx_group_id` (`group_id`),
  CONSTRAINT `fk_member_group_members_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `fk_member_group_members_group_id` FOREIGN KEY (`group_id`) REFERENCES `member_groups` (`id`),
  CONSTRAINT `fk_member_group_members_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='member group mapping';

CREATE TABLE IF NOT EXISTS `member_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `admin_id` bigint(20) unsigned NOT NULL COMMENT '所属管理员ID',
  `group_name` varchar(100) NOT NULL COMMENT '分组名称',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态：1启用 0禁用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  CONSTRAINT `fk_member_groups_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='人员分组表';

CREATE TABLE IF NOT EXISTS `members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `admin_id` bigint(20) unsigned NOT NULL COMMENT '所属管理员ID',
  `user_id` bigint(20) unsigned DEFAULT NULL COMMENT '关联用户ID',
  `group_id` bigint(20) unsigned DEFAULT NULL COMMENT '分组ID',
  `name` varchar(50) NOT NULL COMMENT '姓名',
  `employee_no` varchar(50) DEFAULT NULL COMMENT '编号/工号',
  `department` varchar(100) DEFAULT NULL COMMENT '部门',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机号',
  `position` varchar(100) DEFAULT NULL COMMENT '职位',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态：1启用 0停用',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `is_bound` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否已绑定用户',
  `bind_time` datetime DEFAULT NULL COMMENT '绑定时间',
  `source_type` varchar(20) NOT NULL DEFAULT 'admin_create' COMMENT '来源：admin_create/user_register/pending_confirm',
  `deleted_at` datetime DEFAULT NULL COMMENT 'soft delete time',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_name` (`name`),
  KEY `idx_employee_no` (`employee_no`),
  KEY `idx_admin_id_deleted_at` (`admin_id`,`deleted_at`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_members_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `fk_members_group_id` FOREIGN KEY (`group_id`) REFERENCES `member_groups` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='打卡人员表';

CREATE TABLE IF NOT EXISTS `membership_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '等级名称',
  `max_members` int(11) NOT NULL DEFAULT '50' COMMENT '名单上限',
  `max_task_days` int(11) NOT NULL DEFAULT '7' COMMENT '任务最长天数',
  `max_active_tasks` int(11) NOT NULL DEFAULT '3' COMMENT '同时进行任务数',
  `allow_sub_register` tinyint(4) NOT NULL DEFAULT '0' COMMENT '允许发展下级',
  `allow_survey` tinyint(4) NOT NULL DEFAULT '0' COMMENT '允许问卷功能',
  `allow_message_board` tinyint(4) NOT NULL DEFAULT '0',
  `allow_announcement` tinyint(4) NOT NULL DEFAULT '0',
  `allow_custom_title` tinyint(4) NOT NULL DEFAULT '0',
  `max_surveys` int(11) NOT NULL DEFAULT '0' COMMENT '问卷数量上限',
  `max_survey_responses` int(11) NOT NULL DEFAULT '0' COMMENT '每问卷答卷数上限',
  `max_groups` int(11) NOT NULL DEFAULT '0' COMMENT '可建用户组上限',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员等级表';

CREATE TABLE IF NOT EXISTS `message_announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'pk',
  `admin_id` bigint(20) unsigned NOT NULL COMMENT 'creator admin id',
  `title` varchar(180) NOT NULL COMMENT 'title',
  `content_md` mediumtext NOT NULL COMMENT 'markdown content',
  `is_published` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1-published',
  `published_at` datetime DEFAULT NULL COMMENT 'published at',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created at',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated at',
  PRIMARY KEY (`id`),
  KEY `idx_published_created` (`is_published`,`created_at`),
  KEY `idx_admin_created` (`admin_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='public message announcements';

CREATE TABLE IF NOT EXISTS `online_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'pk',
  `publisher_admin_id` bigint(20) unsigned DEFAULT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `is_admin_reply` tinyint(4) NOT NULL DEFAULT '0',
  `nickname` varchar(100) DEFAULT NULL COMMENT 'optional nickname',
  `content` text NOT NULL COMMENT 'message content',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0-unread 1-read',
  `client_ip` varchar(64) DEFAULT NULL COMMENT 'client ip',
  `user_agent` varchar(500) DEFAULT NULL COMMENT 'user agent',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created at',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated at',
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_parent_created` (`parent_id`,`created_at`),
  KEY `idx_parent_admin` (`parent_id`,`is_admin_reply`),
  KEY `idx_pub` (`publisher_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='public online messages';

CREATE TABLE IF NOT EXISTS `operation_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `admin_id` bigint(20) unsigned NOT NULL COMMENT '管理员ID',
  `operation_type` varchar(50) NOT NULL COMMENT '操作类型',
  `target_type` varchar(50) NOT NULL COMMENT '目标类型',
  `target_id` bigint(20) unsigned DEFAULT NULL COMMENT '目标ID',
  `operation_content` text COMMENT '操作内容',
  `ip_address` varchar(64) DEFAULT NULL COMMENT 'IP地址',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_target_type_target_id` (`target_type`,`target_id`),
  CONSTRAINT `fk_operation_logs_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表';

CREATE TABLE IF NOT EXISTS `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL COMMENT '订单号',
  `user_id` bigint(20) unsigned NOT NULL,
  `plan_id` bigint(20) unsigned NOT NULL,
  `plan_name` varchar(100) NOT NULL COMMENT '下单时快照',
  `level_id` bigint(20) unsigned NOT NULL,
  `days` int(11) NOT NULL,
  `amount_fen` int(11) NOT NULL,
  `status` enum('pending','confirming','paid','cancelled') NOT NULL DEFAULT 'pending',
  `transfer_note` varchar(255) DEFAULT NULL COMMENT '用户备注',
  `admin_note` varchar(255) DEFAULT NULL COMMENT '管理员备注',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单表';

CREATE TABLE IF NOT EXISTS `point_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `change` int(11) NOT NULL COMMENT '变动量（正=增，负=减）',
  `reason` varchar(100) NOT NULL COMMENT '原因',
  `ref_user_id` bigint(20) unsigned DEFAULT NULL COMMENT '关联用户（如被推荐人）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='积分流水';

CREATE TABLE IF NOT EXISTS `popup_announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL COMMENT '公告内容(Markdown)',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1启用 0停用',
  `target_audience` enum('all','logged_in','guest') NOT NULL DEFAULT 'all' COMMENT '目标用户',
  `frequency` enum('every_time','once_per_day','once_only') NOT NULL DEFAULT 'once_per_day' COMMENT '弹窗频率',
  `created_by` bigint(20) unsigned DEFAULT NULL COMMENT '创建人admin_id',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告弹窗表';

CREATE TABLE IF NOT EXISTS `popup_read_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `popup_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `read_date` date NOT NULL COMMENT '阅读日期',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_popup_user_date` (`popup_id`,`user_id`,`read_date`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告阅读记录';

CREATE TABLE IF NOT EXISTS `publisher_announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `publisher_admin_id` bigint(20) unsigned NOT NULL,
  `content` text NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pub_status` (`publisher_admin_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='发布者公告';

CREATE TABLE IF NOT EXISTS `settings` (
  `k` varchar(64) NOT NULL,
  `v` text NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `shop_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '套餐名称',
  `level_id` bigint(20) unsigned NOT NULL COMMENT '对应会员等级ID',
  `is_trial` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否体验套餐(归入体验会员分区)',
  `is_trial_member` tinyint(1) NOT NULL DEFAULT '0',
  `days` int(11) NOT NULL COMMENT '有效天数',
  `price_fen` int(11) NOT NULL COMMENT '价格（分）',
  `is_active` tinyint(4) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商城套餐';

CREATE TABLE IF NOT EXISTS `task_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `task_id` bigint(20) unsigned NOT NULL COMMENT '任务ID',
  `member_id` bigint(20) unsigned NOT NULL COMMENT '人员ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_member` (`task_id`,`member_id`),
  KEY `idx_member_id` (`member_id`),
  CONSTRAINT `fk_task_members_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  CONSTRAINT `fk_task_members_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务参与人员表';

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `admin_id` bigint(20) unsigned NOT NULL COMMENT '所属管理员ID',
  `task_name` varchar(150) NOT NULL COMMENT '任务名称',
  `task_desc` text COMMENT '任务说明',
  `start_time` datetime NOT NULL COMMENT '任务开始时间',
  `end_time` datetime DEFAULT NULL COMMENT '任务结束时间',
  `is_long_term` tinyint(4) NOT NULL DEFAULT '0' COMMENT '是否长期有效：1是 0否',
  `allow_override` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否允许覆盖打卡',
  `duplicate_action` varchar(30) NOT NULL DEFAULT 'prompt' COMMENT '重复提交处理：prompt/reject/override',
  `min_images` int(11) NOT NULL DEFAULT '1' COMMENT '最少上传图片数',
  `max_images` int(11) NOT NULL DEFAULT '10' COMMENT '最多上传图片数',
  `allow_select_task_date` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否允许选择任务日期',
  `task_date_mode` varchar(30) NOT NULL DEFAULT 'free_in_range' COMMENT '任务日期模式：today_only/free_in_range/current_cycle',
  `task_status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '任务状态：1启用 0停用',
  `task_link_code` varchar(100) NOT NULL COMMENT '任务访问编码',
  `task_qrcode` varchar(255) DEFAULT NULL COMMENT '任务二维码路径',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序值',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_link_code` (`task_link_code`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_task_status` (`task_status`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_end_time` (`end_time`),
  KEY `idx_tasks_admin_status_id` (`admin_id`,`task_status`,`id`),
  KEY `idx_tasks_public_window` (`task_status`,`start_time`,`end_time`,`is_long_term`),
  CONSTRAINT `fk_tasks_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='打卡任务表';

CREATE TABLE IF NOT EXISTS `user_login_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `login_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录日志';

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '姓名',
  `mobile` varchar(20) NOT NULL COMMENT '手机号',
  `email` varchar(100) NOT NULL COMMENT '邮箱',
  `password_hash` varchar(255) NOT NULL COMMENT '密码哈希',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `role` varchar(20) NOT NULL DEFAULT 'user' COMMENT '角色：admin/user',
  `can_publish` tinyint(4) NOT NULL DEFAULT '1' COMMENT '是否有发布权限：1是0否',
  `membership_level_id` bigint(20) unsigned NOT NULL DEFAULT '1' COMMENT '会员等级ID',
  `membership_expire_at` datetime DEFAULT NULL COMMENT '会员到期时间,NULL为永久',
  `points` int(11) NOT NULL DEFAULT '0' COMMENT '积分',
  `balance_fen` int(11) NOT NULL DEFAULT '0' COMMENT '余额（分）',
  `referrer_id` bigint(20) unsigned DEFAULT NULL COMMENT '推荐人user_id',
  `ref_code` varchar(20) DEFAULT NULL COMMENT '发布者专属注册码',
  `parent_user_id` bigint(20) unsigned DEFAULT NULL COMMENT '上级发布者用户ID(下级用户)',
  `publisher_admin_id` bigint(20) unsigned DEFAULT NULL COMMENT '关联的隐藏管理员账号ID',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态：1正常 0停用',
  `last_login_at` datetime DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` varchar(45) DEFAULT NULL COMMENT '最后登录IP',
  `wechat_openid` varchar(100) DEFAULT NULL COMMENT '微信openid(预留)',
  `wechat_unionid` varchar(100) DEFAULT NULL COMMENT '微信unionid(预留)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mobile` (`mobile`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_ref_code` (`ref_code`),
  KEY `idx_parent_user` (`parent_user_id`),
  KEY `idx_membership_level` (`membership_level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 运行依赖的基础数据（已存在则跳过）
-- 运行依赖的基础数据（已存在则跳过）
INSERT IGNORE INTO `admin_roles` VALUES (1,'超级管理员','{\"all\": true}',1,NOW(),NOW());
INSERT IGNORE INTO `membership_levels` VALUES (1,'普通会员',50,7,3,0,0,0,0,0,0,0,0,1,NOW());
INSERT IGNORE INTO `membership_levels` VALUES (2,'高级会员',300,30,20,1,1,1,0,1,5,500,1,2,NOW());
INSERT IGNORE INTO `membership_levels` VALUES (3,'超级会员',999999,999999,999999,1,1,1,1,1,999999,999999,5,3,NOW());
INSERT IGNORE INTO `app_settings` VALUES (1,'checkin_image_compress_enabled','1',1,NOW(),NOW());
INSERT IGNORE INTO `app_settings` VALUES (5,'checkin_recycle_auto_purge_days','60',1,NOW(),NOW());

SET FOREIGN_KEY_CHECKS=1;
