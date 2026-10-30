-- 体验套餐标记：用于商城页将体验套餐归入"体验会员"分区
ALTER TABLE `shop_plans`
  ADD COLUMN `is_trial` tinyint(4) NOT NULL DEFAULT 0 COMMENT '是否体验套餐(归入体验会员分区)' AFTER `level_id`;

-- 现有"体验会员·3天"套餐标记为体验
UPDATE `shop_plans` SET `is_trial` = 1 WHERE `name` LIKE '体验%';
