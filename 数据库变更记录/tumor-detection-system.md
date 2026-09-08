# 数据库变更记录：tumor-detection-system

## 变更 1：新建数据库（2026-09-08）

- **变更类型**：建库（新增数据库）
- **修改日期**：2026-09-08 11:24:46
- **修改人**：ASUS-gyz（git config user.name）
- **原因**：原 RDS 实例（rm-cn-xot4w0n3h0001ico.rwlb.cn-chengdu.rds.aliyuncs.com）DNS 已失效、实例释放，数据无法恢复；切换至新 RDS 实例 rm-bp1362om983d343hdpo.mysql.rds.aliyuncs.com，经用户明确授权（"你创一个"）新建业务数据库。

### SQL 语句

```sql
CREATE DATABASE `tumor-detection-system`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 说明

- 执行实例：rm-bp1362om983d343hdpo.mysql.rds.aliyuncs.com:3306（MySQL 8.4.7）
- 账号：nick3814674495
- 建库时为空库，无任何表结构；表结构将由 Laravel migrations 迁移创建（另行记录）。

## 变更 2：Laravel 迁移建表 + 测试数据填充（2026-09-08）

- **变更类型**：表添加（经用户授权，执行 `php artisan migrate --seed`）
- **修改日期**：2026-09-08 11:35:00
- **修改人**：ASUS-gyz（git config user.name）

### SQL 语句

由 Laravel migrations 自动执行，共创建 17 张表：

```sql
-- 框架基础表
users, cache, jobs, personal_access_tokens
-- 业务表
appointments, drugs, drug_stocks, drug_stock_changes, stock_movements,
ai_diagnoses, medical_records, prescriptions, prescription_items,
doctor_schedules, notifications, operation_logs, system_configs
```

中间过程说明：首次迁移因 `drug_stocks`/`drug_stock_changes` 顺序问题失败，残留一张无外键的 `drug_stocks` 表；因新库无业务数据，经 `migrate:fresh --seed` 全量重建，该残留表被 DROP 后按正确顺序重建。

### Seeder 填充的测试数据

- 管理员：admin@oncology.com / admin123
- 医生：doctor_li@hospital.com / 123456
- 患者：张三、王芳
- 药品 5 种（drug_stocks 已同步）、排班 7 天、今日预约 3 条、通知 2 条、操作日志 3 条

## 变更 3：批量测试数据填充（2026-09-08）

- **变更类型**：数据填充（无结构变更，经用户要求"每个表都添加30个数据"）
- **修改日期**：2026-09-08 12:30:00
- **修改人**：ASUS-gyz（git config user.name）

### 执行方式

`php artisan db:seed --class=BulkDataSeeder`（Seeder 为幂等补差逻辑，可重复执行）

### SQL 语句（等价于 Seeder 生成的批量 INSERT）

```sql
INSERT INTO users / doctor_schedules / drugs / drug_stocks / appointments /
medical_records / prescriptions / prescription_items / ai_diagnoses /
drug_stock_changes / stock_movements / notifications / operation_logs /
system_configs ...  -- 各表补足至 30 条以上
```

### 填充后各表数量

users 30、doctor_schedules 49、drugs 33、drug_stocks 33、appointments 35（completed 30）、medical_records 30、prescriptions 30、prescription_items 45、ai_diagnoses 30、drug_stock_changes 30、stock_movements 30、notifications 30、operation_logs 30、system_configs 30。此后接口联调又产生了少量测试数据（如"接口测试药品"、apitest 用户等），属正常联调痕迹。
