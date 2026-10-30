# 8项需求实施计划

## 1. 体验会员可选体验等级（后台）
- 套餐管理页顶部加：体验等级下拉(高级/超级) + 体验天数 input
- `/admin/shop-plans` POST 保存 trial_level_id / trial_days（set_setting）
- 视图传入 `list_membership_levels()` + 当前 trial 设置

## 2. “体验套餐”→“推荐商品”
- 商城 shop.php：删除「购买套餐」标题；第一个商品组标题(普通会员)→「推荐商品」
- 套餐管理页表头「体验套餐」列 → 「推荐商品」
- shop_level.php 相应文案保持等级名

## 3. 推广积分 = 手机号唯一
- register_user 已强制 mobile UNIQUE（重复注册抛错，不会重复加分）→ 已满足
- 加固：在 add_points 推广前确认 referrer 仅在新用户成功插入后 +1（现状已是）
- 无需改动，仅确认；如需更明确可加注释

## 4. 价格口径 ÷10（资金体系统一改为 分/10 = 元）
**口径：¥ = fen / 10（原 /100）；10积分 = ¥1 = 10分**
- services.php：
  - convert_points_to_balance：`$fenAdded = intdiv($points,10)*10`（原 *100）
  - pay_order_by_balance 报错文案 `/10`
- 所有显示 `/100` → `/10`：shop.php、shop_level.php、order_detail.php、user_center.php、admin_shop_plans.php、admin_card_keys.php、admin_orders.php（余额/价格展示处）
- **数据迁移（一次性，先备份）**：`UPDATE shop_plans SET price_fen = price_fen/10`；`UPDATE users SET balance_fen = balance_fen/10`；orders 历史 amount_fen 同步 /10（仅 pending 未支付的，已支付不动）
- 迁移脚本跑完即删，记录原值备份表

## 5. 卡密导出 TXT
- 新路由 `GET /admin/card-keys/export?batch=xxx`（或 ?plan_id + status）
- 输出 `Content-Type: text/plain; Content-Disposition: attachment` 每行一个卡密

## 6. 卡密按批次管理
- 表加 `batch_no varchar(32)`（迁移 ALTER TABLE，存量 NULL）
- generate_card_keys / import 写入 batch_no = 时间戳+planId+序号
- 卡密管理页：列表改为按 batch_no 分组展示（批次=时间+套餐+数量+状态统计），点击批次展开/进入查看该批卡密
- 每批次提供「导出TXT」按钮 → /admin/card-keys/export?batch=

## 7. 用户管理：操作收进编辑 + 编辑积分余额 + 绑定列只显名字
- 编辑弹窗内整合：重置密码、解除绑定、更换绑定、设置等级 入口 + 积分/余额输入
- `/admin/users/update` 扩展：接受 points、balance_fen（按元输入→存分*10），覆盖更新
- 列表「操作」列只留「编辑」「删除」「停用/启用」；其余移入编辑弹窗
- 绑定成员列：只显示 `bound_member.name`，去掉手机号

## 8. 个人中心
- 个人资料卡：增加「绑定成员」行（显示绑定人员名+手机）
- 会员状态卡图标：普通=星(rose)、高级=钻石(blue)、超级=皇冠(gold)，替换现错误图标(人/电话)
- 「升级会员」按钮移到会员卡右下角（absolute bottom-right）

## 涉及文件
- index.php：/admin/shop-plans POST、/admin/card-keys(列表/generate/import/export)、/admin/users/update、/shop 等
- app/services.php：convert_points_to_balance、generate/import card_keys(batch_no)、update_user(points/balance)、卡密批次查询、export
- 视图：shop.php、shop_level.php、order_detail.php、user_center.php、admin_shop_plans.php、admin_card_keys.php、admin_users.php、admin_orders.php
- DB 迁移：card_keys.batch_no 列；价格/余额 ÷10（备份后执行）

## 验证
- php -l 全部改动文件
- 卡密：生成→批次分组→导出TXT→直接兑换 闭环测试（测试数据清理）
- 价格迁移后：商城/订单/个人中心 金额显示正确；余额支付门槛一致
- 用户编辑：改积分/余额/等级/绑定 生效
- 部署 + 线上 curl/渲染检查
