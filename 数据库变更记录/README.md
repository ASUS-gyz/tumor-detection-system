# 数据库变更记录

> 按开发手册规范：所有数据库变更必须记录于此目录

## 规范

1. **文档命名**：以表名作为文件名，如 `users.md`、`appointments.md`、`drugs.md`
2. **每次变更必须记录**，标明类型：字段添加、字段修改、字段删除、表删除
3. **记录 SQL 语句**
4. **记录修改日期**，格式：YYYY-MM-DD HH:mm:ss
5. **记录修改人**：读取 `git config user.name`

## 模板参考

```markdown
## 变更记录

### 2026-07-26 22:00:00
- **修改人**: ASUS-gyz
- **变更类型**: 字段添加
- **变更说明**: 新增 phone 字段
- **SQL**:
  ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email;
```
