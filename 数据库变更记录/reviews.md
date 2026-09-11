# reviews 表变更记录

## 变更 1：新建 reviews 表（评价持久化）

- **变更类型**：表添加
- **修改人**：ASUS-gyz（git config user.name）
- **修改日期**：2026-09-11 17:30:21
- **关联**：GitHub issue #25（评价/续方接口假成功）；迁移文件 `database/migrations/2026_09_11_172957_create_reviews_table.php`
- **授权**：用户已明确授权（原系统无评价持久化，患者提交评价仅原样返回不落库）

### SQL 语句（Laravel Schema Builder 等价 SQL）

```sql
CREATE TABLE `reviews` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `appointment_id` BIGINT UNSIGNED NOT NULL COMMENT '关联预约ID（一预约一评价）',
  `patient_id` BIGINT UNSIGNED NOT NULL COMMENT '评价患者ID',
  `doctor_id` BIGINT UNSIGNED NOT NULL COMMENT '被评价医生ID',
  `rating` TINYINT UNSIGNED NOT NULL COMMENT '评分1-5',
  `content` VARCHAR(500) DEFAULT NULL COMMENT '评价内容',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_appointment_id_unique` (`appointment_id`),
  KEY `reviews_doctor_id_index` (`doctor_id`),
  CONSTRAINT `reviews_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `reviews_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`),
  CONSTRAINT `reviews_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 设计说明

- `appointment_id` 唯一约束：一预约一评价，防重复提交（应用层同时预检查 + 1062 兜底）。
- `doctor_id` 冗余自预约关系并建索引：便于后续按医生聚合评价。
- 未修改任何现有表；续方功能复用现有 `prescriptions` 表（pending 新处方），无本表之外的结构变更。
