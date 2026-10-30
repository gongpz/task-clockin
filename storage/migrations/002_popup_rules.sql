ALTER TABLE `popup_announcements`
  ADD COLUMN `target_audience` enum('all','logged_in','guest') NOT NULL DEFAULT 'all' COMMENT '目标用户：all全部/logged_in已登录/guest未登录' AFTER `status`,
  ADD COLUMN `frequency` enum('every_time','once_per_day','once_only') NOT NULL DEFAULT 'once_per_day' COMMENT '弹窗频率：every_time每次/once_per_day每天一次/once_only仅一次' AFTER `target_audience`;
