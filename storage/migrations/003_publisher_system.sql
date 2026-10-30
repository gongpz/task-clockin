-- 第一期：用户发布系统（方案B：每个发布者挂一条隐藏 admins 账号）

-- users 表：发布权限、专属注册码、上级发布者、关联的隐藏管理员账号
ALTER TABLE `users`
  ADD COLUMN `can_publish` tinyint(4) NOT NULL DEFAULT 1 COMMENT '是否有发布权限：1是0否' AFTER `role`,
  ADD COLUMN `ref_code` varchar(20) DEFAULT NULL COMMENT '发布者专属注册码' AFTER `can_publish`,
  ADD COLUMN `parent_user_id` bigint(20) unsigned DEFAULT NULL COMMENT '上级发布者用户ID(下级用户)' AFTER `ref_code`,
  ADD COLUMN `publisher_admin_id` bigint(20) unsigned DEFAULT NULL COMMENT '关联的隐藏管理员账号ID' AFTER `parent_user_id`,
  ADD UNIQUE KEY `uk_ref_code` (`ref_code`),
  ADD KEY `idx_parent_user` (`parent_user_id`);

-- admins 表：标记发布者账号及其归属用户
ALTER TABLE `admins`
  ADD COLUMN `owner_user_id` bigint(20) unsigned DEFAULT NULL COMMENT '发布者账号归属用户ID(NULL为平台管理员)' AFTER `status`,
  ADD KEY `idx_owner_user` (`owner_user_id`);

-- 给现有所有用户生成专属注册码
UPDATE `users` SET `ref_code` = CONCAT('U', LPAD(id, 6, '0')) WHERE `ref_code` IS NULL;
