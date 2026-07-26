# 代码修改记录

> 按开发手册规范：所有代码变更必须记录于此目录

## 规范

1. **文档命名**：文件名格式 `YYYY-MM-DD.md`，如 `2026-07-26.md`
2. **一次需求对应一份记录**：不得拆分多个文档
3. **内容要求**：先记录删除代码范围，再记录新增代码范围
4. **修改日期**：自动获取当前日期作为文件名

## 模板参考

```markdown
# 2026-07-26 代码修改记录

## 修改概述
- 修改人: ASUS-gyz
- 需求: 添加患者预约功能

## 删除代码

### 文件: app/Http/Controllers/Controller.php
- 无删除

## 新增代码

### 文件: app/Http/Controllers/Patient/AppointmentController.php
- 新增预约控制器，包含 index、store、cancel 方法

### 文件: app/Services/AppointmentService.php
- 新增预约服务层，处理预约创建、取消逻辑
```
