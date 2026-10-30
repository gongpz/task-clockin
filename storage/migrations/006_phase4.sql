-- 第四期：积分推广 + 卡密 + 用户组配额

-- 1. users 表新增字段
ALTER TABLE `users`
  ADD COLUMN `points` int(11) NOT NULL DEFAULT 0 COMMENT '积分' AFTER `membership_expire_at`,
  ADD COLUMN `balance_fen` int(11) NOT NULL DEFAULT 0 COMMENT '余额（分）' AFTER `points`,
  ADD COLUMN `referrer_id` bigint(20) unsigned DEFAULT NULL COMMENT '推荐人user_id' AFTER `balance_fen`;

-- 2. membership_levels 新增 max_groups
ALTER TABLE `membership_levels`
  ADD COLUMN `max_groups` int(11) NOT NULL DEFAULT 0 COMMENT '可建用户组上限' AFTER `max_survey_responses`;

UPDATE `membership_levels` SET `max_groups` = 0 WHERE `id` = 1;
UPDATE `membership_levels` SET `max_groups` = 1 WHERE `id` = 2;
UPDATE `membership_levels` SET `max_groups` = 5 WHERE `id` = 3;

-- 3. 全局设置：体验会员配置
INSERT INTO `settings` (`k`, `v`) VALUES
  ('trial_level_id', '2'),
  ('trial_days', '3')
ON DUPLICATE KEY UPDATE `v` = VALUES(`v`);

-- 4. 积分流水表
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

-- 5. 卡密表
CREATE TABLE IF NOT EXISTS `card_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL COMMENT '卡密',
  `plan_id` bigint(20) unsigned NOT NULL COMMENT '绑定套餐',
  `status` enum('unused','used') NOT NULL DEFAULT 'unused',
  `used_by` bigint(20) unsigned DEFAULT NULL COMMENT '使用者user_id',
  `used_order_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_plan_status` (`plan_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='卡密表';
