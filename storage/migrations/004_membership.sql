-- 第二期：会员分级系统
-- 不限额度统一用大数 999999 表示

CREATE TABLE IF NOT EXISTS `membership_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '等级名称',
  `max_members` int(11) NOT NULL DEFAULT 50 COMMENT '名单上限',
  `max_task_days` int(11) NOT NULL DEFAULT 7 COMMENT '任务最长天数',
  `max_active_tasks` int(11) NOT NULL DEFAULT 3 COMMENT '同时进行任务数',
  `allow_sub_register` tinyint(4) NOT NULL DEFAULT 0 COMMENT '允许发展下级',
  `allow_survey` tinyint(4) NOT NULL DEFAULT 0 COMMENT '允许问卷功能',
  `max_surveys` int(11) NOT NULL DEFAULT 0 COMMENT '问卷数量上限',
  `max_survey_responses` int(11) NOT NULL DEFAULT 0 COMMENT '每问卷答卷数上限',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员等级表';

INSERT INTO `membership_levels`
  (`id`, `name`, `max_members`, `max_task_days`, `max_active_tasks`, `allow_sub_register`, `allow_survey`, `max_surveys`, `max_survey_responses`, `sort_order`)
VALUES
  (1, '普通会员', 50, 7, 3, 0, 0, 0, 0, 1),
  (2, '高级会员', 300, 30, 20, 1, 1, 5, 500, 2),
  (3, '超级会员', 999999, 999999, 999999, 1, 1, 999999, 999999, 3);

ALTER TABLE `users`
  ADD COLUMN `membership_level_id` bigint(20) unsigned NOT NULL DEFAULT 1 COMMENT '会员等级ID' AFTER `can_publish`,
  ADD COLUMN `membership_expire_at` datetime DEFAULT NULL COMMENT '会员到期时间,NULL为永久' AFTER `membership_level_id`,
  ADD KEY `idx_membership_level` (`membership_level_id`);

UPDATE `users` SET `membership_level_id` = 1 WHERE `membership_level_id` IS NULL OR `membership_level_id` = 0;
