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

## 变更 4：存量 JSON 数据修复（2026-09-11）

- **变更类型**：数据修复（非结构变更，字段类型/长度/索引均未动）
- **修改日期**：2026-09-11 15:04:17
- **修改人**：ASUS-gyz（git config user.name）
- **原因**：BulkDataSeeder 对 `time_slots`/`possible_conditions` 预先 json_encode，而模型 array cast 会再编码一次，导致入库值双重编码（如 `"[\"08:30\",...]"`），cast 后得到字符串而非数组，排班时段校验、AI 可能疾病列表展示失效。种子代码已同步修复（代码修改记录 2026-09-11 变更 4）。

### SQL 语句

通过 Laravel tinker 逐行判断并规范化（JSON 字符串解包一层），等效于：

```sql
-- doctor_schedules：42 行双重编码值修复
UPDATE doctor_schedules SET time_slots = JSON_UNQUOTE(time_slots)
WHERE JSON_TYPE(time_slots) = 'STRING';
-- ai_diagnoses：15 行双重编码值修复
UPDATE ai_diagnoses SET possible_conditions = JSON_UNQUOTE(possible_conditions)
WHERE possible_conditions IS NOT NULL AND JSON_TYPE(possible_conditions) = 'STRING';
```

实际执行方式为 tinker 内 PHP 逐行 `json_decode` 判定后 Eloquent update（等价于上述 SQL，且额外校验了解码结果为合法 JSON）。

### 说明

- 修复后复核：`doctor_schedules` 与 `ai_diagnoses` 双重编码行数均为 0。
- 涉及表：doctor_schedules（time_slots 列值）、ai_diagnoses（possible_conditions 列值）。未修改任何表结构。

## 变更 5：医学影像私有化迁移与 image_url 数据修复（2026-09-11）

- **变更类型**：数据修复（非结构变更，字段类型/长度/索引均未动）
- **修改日期**：2026-09-11 07:55:25
- **修改人**：ASUS-gyz（git config user.name）
- **原因**：医学影像（CT/MRI 等敏感数据）原存公有磁盘 `storage/app/public/ai-images/`，`image_url` 为 `/storage/...` 永久公开地址，无任何鉴权（issue #24）。私有化改造后（代码修改记录 2026-09-11 变更 9），存量文件与数据需同步迁移。

### 操作语句

通过 Laravel tinker 执行，等效于：

```sql
-- ai_diagnoses：3 行存量地址重写为文件名（签名 URL 由应用层实时生成）
UPDATE ai_diagnoses
SET image_url = SUBSTRING_INDEX(image_url, '/', -1)
WHERE image_url IS NOT NULL AND image_url <> '';
```

文件迁移：`storage/app/public/ai-images/*` 10 个文件 → `storage/app/private/ai-images/`（公有目录原文件删除，迁移后公有侧剩余 0、私有侧 10）。

### 说明

- 修复后复核：`ai_diagnoses` 中 `image_url` 非空的 4 行（含迁移期间新写入 1 行）均为纯文件名格式，签名 URL 访问实测通过。
- 涉及表：ai_diagnoses（image_url 列值）。未修改任何表结构。
