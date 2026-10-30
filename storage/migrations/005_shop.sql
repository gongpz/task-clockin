-- 第三期：商城/付费开通会员

CREATE TABLE IF NOT EXISTS `shop_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '套餐名称',
  `level_id` bigint(20) unsigned NOT NULL COMMENT '对应会员等级ID',
  `days` int(11) NOT NULL COMMENT '有效天数',
  `price_fen` int(11) NOT NULL COMMENT '价格（分）',
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商城套餐';

INSERT INTO `shop_plans` (`name`, `level_id`, `days`, `price_fen`, `is_active`, `sort_order`) VALUES
  ('体验会员·3天',  2,   3,   100, 1, 1),
  ('高级会员·1个月', 2,  30,   500, 1, 2),
  ('高级会员·3个月', 2,  90,  1500, 1, 3),
  ('高级会员·1年',  2, 365,  5000, 1, 4),
  ('超级会员·1个月', 3,  30,   800, 1, 5),
  ('超级会员·3个月', 3,  90,  2400, 1, 6),
  ('超级会员·1年',  3, 365,  8800, 1, 7);

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

-- 全局配置（收款信息等）
CREATE TABLE IF NOT EXISTS `settings` (
  `k` varchar(64) NOT NULL,
  `v` text NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`k`, `v`) VALUES
  ('transfer_info', '请将款项转至：\n银行：XXX银行\n账号：XXXXXXXXXXXX\n户名：XXX\n\n转账时请备注您的手机号，转账后点击"我已付款"。');
