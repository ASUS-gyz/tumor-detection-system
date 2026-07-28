# ZZT 代码与业务逻辑文档

> **开发者**：Fressia-zzt（ZZT）
> **接口总数**：44（V1 29 + V2 15）
> **涉及表**：users, appointments, medical\_records, prescriptions, prescription\_items, ai\_diagnoses, drug\_stocks, drug\_stock\_changes, drugs, personal\_access\_tokens
> **日期**：2026-07-28

***

## 目录

1. [架构总览](#1-架构总览)
2. [认证体系](#2-认证体系)
3. [认证模块（11接口）](#3-认证模块)
4. [患者端-预约管理（9接口）](#4-患者端-预约管理)
5. [患者端-AI文字诊断（5接口）](#5-患者端-ai文字诊断)
6. [患者端-病历与处方（7接口）](#6-患者端-病历与处方)
7. [医生端-病历与处方（8接口）](#7-医生端-病历与处方)
8. [医生端-模板管理（4接口）](#8-医生端-模板管理)
9. [模型关联关系](#9-模型关联关系)
10. [中间件](#10-中间件)
11. [表单验证](#11-表单验证)
12. [统一响应格式](#12-统一响应格式)
13. [文件清单](#13-文件清单)

***

## 1. 架构总览

```
请求 → routes/api.php → 中间件(auth:sanctum + role) → Controller → Model/Service → 数据库
                                                              ↓
                                                       Result::success/error
                                                              ↓
                                                     {code, msg, data, success, trace_id}
```

**分层职责**：

| 层    | 目录                      | 职责                   |
| ---- | ----------------------- | -------------------- |
| 路由   | `routes/api.php`        | URL 映射，中间件绑定         |
| 控制器  | `app/Http/Controllers/` | 请求处理，业务编排，响应格式化      |
| 表单验证 | `app/Http/Requests/`    | 参数规则校验，中文错误提示        |
| 模型   | `app/Models/`           | 数据访问，关联关系，类型转换       |
| 认证   | `app/Auth/`             | Token 生成/验证，Guard 注册 |
| 中间件  | `app/Http/Middleware/`  | 角色鉴权，链路追踪            |

***

## 2. 认证体系

### 2.1 认证机制

自建 Sanctum 风格 Token 认证（因 Laravel 13 不兼容官方 Sanctum 包）：

| 文件                                                                | 职责                                                                      |
| ----------------------------------------------------------------- | ----------------------------------------------------------------------- |
| `app/Auth/HasApiTokens.php`                                       | User 模型的 trait，提供 `createToken()` / `tokens()` / `currentAccessToken()` |
| `app/Auth/SanctumGuard.php`                                       | 自定义 Guard，从 Bearer Token 中提取验证用户                                        |
| `app/Models/PersonalAccessToken.php`                              | Token 存储模型，SHA256 哈希存储                                                  |
| `database/migrations/..._create_personal_access_tokens_table.php` | Token 表（多态关联，支持任意模型）                                                    |

**Token 生命周期**：

```
登录/注册 → createToken() → 明文 token 返回前端 → 前端存入 → 后续请求带 Authorization: Bearer xxx
                                                              ↓
                                              SanctumGuard::user() → SHA256 比对 → 返回 User
退出 → tokens()->delete() → 该用户所有 token 失效
```

### 2.2 角色鉴权

| 中间件            | 文件             | 用法                           |
| -------------- | -------------- | ---------------------------- |
| `auth:sanctum` | `SanctumGuard` | 验证 Bearer Token              |
| `role:patient` | `EnsureRole`   | 验证 `user.role === 'patient'` |
| `role:doctor`  | `EnsureRole`   | 验证 `user.role === 'doctor'`  |

注册位置：`bootstrap/app.php` → `$middleware->alias(['role' => EnsureRole::class])`

***

## 3. 认证模块（11接口）

> 文件：`app/Http/Controllers/AuthController.php`
> 路由前缀：`/api/auth`

### 3.1 接口列表

| #  | 方法     | 路径                      | 权限  | 功能                 |
| -- | ------ | ----------------------- | --- | ------------------ |
| 1  | POST   | `/auth/register`        | 公开  | 患者注册，返回 token      |
| 2  | POST   | `/auth/login`           | 公开  | 用户登录，校验密码+状态       |
| 3  | POST   | `/auth/logout`          | 已登录 | 删除所有 token         |
| 4  | GET    | `/auth/me`              | 已登录 | 获取当前用户信息           |
| 5  | PUT    | `/auth/password`        | 已登录 | 修改密码（需当前密码）        |
| 6  | POST   | `/auth/avatar`          | 已登录 | 上传头像（jpg/png/webp） |
| 7  | PUT    | `/auth/profile`         | 已登录 | 更新个人资料（医生可改职称等）    |
| 8  | POST   | `/auth/forgot-password` | 公开  | 发送重置令牌             |
| 9  | POST   | `/auth/reset-password`  | 公开  | 令牌验证+重置密码          |
| 10 | POST   | `/auth/verify-email`    | 已登录 | 标记邮箱已验证            |
| 11 | DELETE | `/auth/account`         | 已登录 | 注销账号（软标记 disabled） |

### 3.2 注册业务逻辑

```
POST /api/auth/register
  │
  ├─ RegisterRequest 验证：name(2-50字) / email(邮箱+唯一) / password(6-100) / phone(可选,正则)
  │
  ├─ User::create(['role'=>'patient', 'status'=>'active'])
  │
  ├─ $user->createToken('auth_token') → SHA256 存入 personal_access_tokens
  │
  └─ 返回 { user: {id,name,email,role,phone...}, token: "明文", token_type: "Bearer" }
```

### 3.3 登录业务逻辑

```
POST /api/auth/login
  │
  ├─ LoginRequest 验证：email / password
  │
  ├─ User::where('email', ...)->first()
  ├─ 不存在 || 密码不匹配 → 20008 "邮箱或密码不正确"
  ├─ status === 'disabled' → 20006 "账号已被禁用"
  │
  ├─ createToken('auth_token')
  └─ 返回 token
```

### 3.4 头像上传业务逻辑

```
POST /api/auth/avatar
  │
  ├─ 验证：文件必须为图片，mimes:jpg/jpeg/png/webp，max:2048KB
  │
  ├─ 删除旧头像文件（storage/app/public/avatars/xxx）
  ├─ 存储新头像 → storage/app/public/avatars/{uuid}.ext
  ├─ 更新 users.avatar_url = asset('storage/avatars/xxx')
  └─ 返回 { avatar_url: "http://..." }
```

### 3.5 忘记/重置密码流程

```
POST /api/auth/forgot-password
  ├─ 验证 email 存在
  ├─ Str::random(60) 生成 token
  ├─ DB::table('password_reset_tokens')->updateOrInsert(email, token)
  └─ 返回 token（开发模式直接返回）

POST /api/auth/reset-password
  ├─ 验证 email / token / password
  ├─ password_reset_tokens 表校验 token 有效性
  ├─ 更新 users.password
  ├─ 删除已用 token
  └─ 返回成功
```

***

## 4. 患者端-预约管理（9接口）

> 文件：`app/Http/Controllers/Patient/PatientController.php`
> 路由前缀：`/api/patient`

### 4.1 接口列表

| # | 方法     | 路径                              | 功能                        |
| - | ------ | ------------------------------- | ------------------------- |
| 1 | GET    | `/dashboard`                    | 首页统计：待就诊数/已完成数/AI诊断数/下次预约 |
| 2 | GET    | `/doctors`                      | 医生列表（分页+关键词搜索）            |
| 3 | GET    | `/doctors/{id}`                 | 医生详情（仅active）             |
| 4 | POST   | `/appointments`                 | 创建预约                      |
| 5 | GET    | `/appointments`                 | 我的预约列表（状态筛选+日期筛选）         |
| 6 | GET    | `/appointments/available-slots` | 可预约时段查询                   |
| 7 | GET    | `/appointments/{id}`            | 预约详情（含病历+处方+AI诊断）         |
| 8 | DELETE | `/appointments/{id}`            | 取消预约（仅pending状态）          |
| 9 | POST   | `/appointments/{id}/review`     | 就诊评价                      |

### 4.2 创建预约业务逻辑

```
POST /api/patient/appointments
  │
  ├─ CreateAppointmentRequest 验证：
  │   doctor_id: 存在且 role=doctor, status=active
  │   appointment_date: 日期 ≥ 今天
  │   appointment_time: 枚举 [08:30, 09:15, 10:00, 10:45, 13:30, 14:15, 15:00, 15:45]
  │
  ├─ 冲突校验：同一患者是否有 pending/called/in_progress 状态的预约
  │   └─ 有 → 40009 "您已有一个进行中的预约"
  │
  ├─ Appointment::create({patient_id, doctor_id, appointment_date, appointment_time, status:'pending'})
  └─ 返回预约信息 + 医生姓名/职称/科室
```

### 4.3 可预约时段逻辑

```
GET /api/patient/appointments/available-slots?doctor_id=X&date=YYYY-MM-DD
  │
  ├─ 全时段：[08:30, 09:15, 10:00, 10:45, 13:30, 14:15, 15:00, 15:45]
  │
  ├─ 查询已占用时段：
  │   Appointment::where(doctor_id, date)
  │     ->whereIn('status', ['pending','called','in_progress'])
  │     ->pluck('appointment_time')
  │
  └─ 可用时段 = array_diff(全时段, 已占用时段)
```

### 4.4 预约详情（聚合查询）

```
GET /api/patient/appointments/{id}
  │
  ├─ Appointment::with([
  │     'doctor:id,name,title,specialty,department',
  │     'medicalRecord',           // 病历（1:1）
  │     'prescription.items',      // 处方+明细
  │     'aiDiagnosis',             // AI诊断（文字，最新一条）
  │   ])->where('patient_id', $patientId)->find($id)
  │
  └─ 返回 预约 + 医生 + 病历 + 处方(含药品) + AI报告
```

***

## 5. 患者端-AI文字诊断（5接口）

> 文件：`app/Http/Controllers/Patient/AIDiagnosisController.php`
> 路由前缀：`/api/patient/ai-diagnosis`
> 依赖：`app/Services/AIDiagnosisService.php`

### 5.1 接口列表

| # | 方法   | 路径                          | 功能                 |
| - | ---- | --------------------------- | ------------------ |
| 1 | POST | `/ai-diagnosis`             | 创建诊断（调用AI服务→入库→返回） |
| 2 | GET  | `/ai-diagnosis`             | 诊断记录列表（分页，症状截取50字） |
| 3 | GET  | `/ai-diagnosis/{id}`        | 诊断详情（仅限本人）         |
| 4 | POST | `/ai-diagnosis/continue`    | 多轮追问（携带上下文）        |
| 5 | GET  | `/ai-diagnosis/{id}/export` | 导出PDF报告数据          |

### 5.2 AI诊断核心流程

```
POST /api/patient/ai-diagnosis
  │
  ├─ CreateDiagnosisRequest 验证：symptom_description(必填,2-2000字), appointment_id(可选)
  │
  ├─ AIDiagnosisService::textDiagnosis(症状描述, 用户ID)
  │   │
  │   ├─ 模式判断：config('ai.mode')
  │   │   ├─ 'mock' → mockTextDiagnosis() → 返回预设JSON（sleep模拟延迟）
  │   │   └─ 'remote' → callRemoteAI() → GuzzleHTTP → DeepSeek API
  │   │         ├─ System Prompt: "你是肿瘤科医生，请以JSON格式返回..."
  │   │         └─ 返回 {analysis, risk_level, risk_warning, advice, possible_conditions}
  │   │
  │   └─ 返回诊断结果数组
  │
  ├─ AIDiagnosis::create({type:'text', patient_id, symptom_description, analysis, risk_level, ...})
  │
  └─ 返回完整诊断报告
```

### 5.3 多轮追问逻辑

```
POST /api/patient/ai-diagnosis/continue
  │
  ├─ 验证：diagnosis_id(上次诊断ID), question(追问内容)
  │
  ├─ 查找上次诊断记录（验证归属本人）
  │
  ├─ 拼接上下文：原始症状 + 上次分析 + 患者追问 → 再次调用 AI
  │
  ├─ 创建新诊断记录（独立存储，非覆盖）
  │
  └─ 返回 { previous_id: 上次ID, diagnosis: 新诊断详情 }
```

***

## 6. 患者端-病历与处方（7接口）

> 文件：`app/Http/Controllers/Patient/MedicalRecordController.php` + `PrescriptionController.php`

### 6.1 接口列表

| # | 方法   | 路径                            | 功能           | 控制器                     |
| - | ---- | ----------------------------- | ------------ | ----------------------- |
| 1 | GET  | `/medical-records`            | 病历列表         | MedicalRecordController |
| 2 | GET  | `/medical-records/{id}`       | 病历详情（含医生+预约） | MedicalRecordController |
| 3 | GET  | `/prescriptions`              | 处方列表（状态筛选）   | PrescriptionController  |
| 4 | GET  | `/prescriptions/{id}`         | 处方详情（含药品明细）  | PrescriptionController  |
| 5 | POST | `/prescriptions/{id}/confirm` | 确认取药（扣减库存）   | PrescriptionController  |
| 6 | POST | `/prescriptions/{id}/refill`  | 续方申请         | PrescriptionController  |
| 7 | GET  | `/medication-reminders`       | 每日用药提醒       | PrescriptionController  |

### 6.2 确认取药（核心事务流程）

```
POST /api/patient/prescriptions/{id}/confirm
  │
  ├─ 校验：处方归属本人 + status === 'pending'
  │
  ├─ 预检查（事务外）：
  │   遍历 prescription.items：
  │     DrugStock::where('drug_id', item.drug_id)->first()
  │     库存 < 需要量 → 加入 insufficient[] 列表
  │
  │   有不足 → 返回 40004 "以下药品库存不足：奥希替尼片、吉非替尼片"
  │            附带 detail[{drug_name, need, have}]
  │
  ├─ DB::beginTransaction()
  │   │
  │   ├─ 逐项扣减：
  │   │   lockForUpdate() 锁行防并发
  │   │   before = stock.quantity
  │   │   stock.quantity -= item.quantity
  │   │   stock.save()
  │   │
  │   ├─ 记录日志：
  │   │   DrugStockChange::create({
  │   │     type: 'out', quantity: item.quantity,
  │   │     before_quantity: before, after_quantity: stock.quantity,
  │   │     related_type: 'prescription', related_id: prescription.id
  │   │   })
  │   │
  │   └─ prescription.status = 'dispensed'
  │
  ├─ DB::commit()
  │
  └─ 返回 "取药成功，库存已自动扣减"
```

### 6.3 续方申请逻辑

```
POST /api/patient/prescriptions/{id}/refill
  │
  ├─ 校验：处方归属本人 + status === 'dispensed'（已取药才能续方）
  │
  └─ 返回 { original_prescription_id, items[{drug_name, dosage, quantity}], message }
     （生产环境：可创建一条新的预约+处方记录，或发送通知给医生）
```

***

## 7. 医生端-病历与处方（8接口）

> 文件：`app/Http/Controllers/Doctor/MedicalRecordController.php` + `PrescriptionController.php`
> 路由前缀：`/api/doctor`

### 7.1 接口列表

| # | 方法   | 路径                         | 功能                   |
| - | ---- | -------------------------- | -------------------- |
| 1 | POST | `/medical-records`         | 创建病历（自动填patient\_id） |
| 2 | PUT  | `/medical-records/{id}`    | 编辑病历（部分更新）           |
| 3 | GET  | `/medical-records/{id}`    | 病历详情（含患者+预约）         |
| 4 | GET  | `/medical-records`         | 历史病历列表               |
| 5 | GET  | `/medical-records/compare` | 多份病历对比               |
| 6 | POST | `/prescriptions`           | 开具处方（库存校验不扣减）        |
| 7 | GET  | `/prescriptions/{id}`      | 处方详情                 |
| 8 | GET  | `/prescriptions`           | 历史处方列表               |

### 7.2 创建病历业务逻辑

```
POST /api/doctor/medical-records
  │
  ├─ CreateMedicalRecordRequest 验证：
  │   appointment_id(必填), symptoms(必填), imaging_findings(可选),
  │   preliminary_diagnosis(必填), treatment_plan(必填)
  │
  ├─ 校验：Appointment::where('doctor_id', $doctorId)->find(appointment_id)
  │   └─ 不存在 → 30001 "预约不存在或无权限操作"
  │
  ├─ 重复校验：MedicalRecord::where('appointment_id', ...)->exists()
  │   └─ 已存在 → 40009 "该预约已创建病历，请使用编辑功能"
  │
  ├─ MedicalRecord::create({appointment_id, patient_id(从预约取), doctor_id, ...})
  └─ 返回病历 + 患者信息 + 预约信息
```

### 7.3 开具处方业务逻辑

```
POST /api/doctor/prescriptions
  │
  ├─ CreatePrescriptionRequest 验证：
  │   appointment_id: 必填+存在
  │   items: 必填数组, min:1
  │   items.*.drug_id: 必填+存在
  │   items.*.quantity: 必填, min:1
  │   items.*.dosage: 必填
  │   items.*.instructions: 可选
  │
  ├─ 校验：Appointment::where('doctor_id', $doctorId)->find(appointment_id)
  │
  ├─ 库存校验（仅检查不扣减）：
  │   遍历 items → DrugStock::where('drug_id', ...)->first()
  │   库存 < 需要量 → 加入 insufficient 列表
  │   有不足 → 40004 "以下药品库存不足：XXX"
  │
  ├─ DB::beginTransaction()
  │   ├─ Prescription::create({appointment_id, patient_id, doctor_id, status:'pending'})
  │   └─ 逐项 PrescriptionItem::create({prescription_id, drug_id, quantity, dosage, instructions})
  ├─ DB::commit()
  │
  └─ 返回处方 + 药品明细
```

### 7.4 病历对比逻辑

```
GET /api/doctor/medical-records/compare?ids=1,2,3
  │
  ├─ 验证：ids 格式为逗号分隔数字，最多5份
  ├─ 查询：MedicalRecord::whereIn('id', ids)->where('doctor_id', $doctorId)
  └─ 返回 [{病历1}, {病历2}, {病历3}] 平铺对比
```

***

## 8. 医生端-模板管理（4接口）

> 文件：`app/Http/Controllers/Doctor/TemplateController.php`
> 存储：Laravel Cache（简化实现）

### 8.1 接口列表

| # | 方法   | 路径                          | 功能     |
| - | ---- | --------------------------- | ------ |
| 1 | POST | `/medical-record-templates` | 保存病历模板 |
| 2 | GET  | `/medical-record-templates` | 病历模板列表 |
| 3 | POST | `/prescription-templates`   | 保存处方模板 |
| 4 | GET  | `/prescription-templates`   | 处方模板列表 |

### 8.2 模板存储逻辑

```
Cache Key: "doctor_{$doctorId}_mr_templates"  // 病历模板
Cache Key: "doctor_{$doctorId}_rx_templates"  // 处方模板

存储方式：Cache::forever(key, [模板数组])  // 按医生隔离
```

***

## 9. 模型关联关系

> 目录：`app/Models/`

### 9.1 模型清单

| 模型                    | 表名                       | 核心关联                                                                                                                     |
| --------------------- | ------------------------ | ------------------------------------------------------------------------------------------------------------------------ |
| `User`                | users                    | hasMany(Appointment, patient\_id) / hasMany(Appointment, doctor\_id) / HasApiTokens                                      |
| `Appointment`         | appointments             | belongsTo(User, patient) / belongsTo(User, doctor) / hasOne(MedicalRecord) / hasOne(Prescription) / hasMany(AIDiagnosis) |
| `MedicalRecord`       | medical\_records         | belongsTo(Appointment) / belongsTo(User, patient) / belongsTo(User, doctor)                                              |
| `Prescription`        | prescriptions            | belongsTo(Appointment) / belongsTo(User, patient) / belongsTo(User, doctor) / hasMany(PrescriptionItem)                  |
| `PrescriptionItem`    | prescription\_items      | belongsTo(Prescription) / belongsTo(Drug)                                                                                |
| `AIDiagnosis`         | ai\_diagnoses            | belongsTo(User, patient) / belongsTo(User, doctor) / belongsTo(Appointment)                                              |
| `Drug`                | drugs                    | —                                                                                                                        |
| `DrugStock`           | drug\_stocks             | belongsTo(Drug)                                                                                                          |
| `DrugStockChange`     | drug\_stock\_changes     | belongsTo(Drug)                                                                                                          |
| `PersonalAccessToken` | personal\_access\_tokens | morphTo('tokenable')                                                                                                     |

### 9.2 关键关联约束

| 关联                | 约束   | 实现方式                                              |
| ----------------- | ---- | ------------------------------------------------- |
| 一个预约→一份病历         | 1:1  | `medical_records.appointment_id` UNIQUE           |
| 一个处方→多条明细         | 1:N  | `prescription_items.prescription_id` FK + CASCADE |
| 患者只能有一个进行中预约      | 业务约束 | 应用层 `whereIn(status)->exists()`                   |
| 仅 pending 状态可取消预约 | 状态机  | Appointment::canCancel()                          |
| AI诊断归属患者          | 数据隔离 | `where('patient_id', $patientId)`                 |

***

## 10. 中间件

| 文件                                   | 类名           | 职责                                  |
| ------------------------------------ | ------------ | ----------------------------------- |
| `app/Http/Middleware/EnsureRole.php` | EnsureRole   | 角色鉴权：`role:patient` / `role:doctor` |
| `app/Auth/SanctumGuard.php`          | SanctumGuard | Token 认证 Guard                      |

### EnsureRole 逻辑

```php
public function handle(Request $request, Closure $next, string ...$roles)
{
    // 检查 user->role 是否在允许角色列表中
    if (! in_array($user->role, $roles)) {
        return Result::error(FORBIDDEN, '无访问权限');
    }
    return $next($request);
}
```

***

## 11. 表单验证

> 目录：`app/Http/Requests/`

### 11.1 请求类清单

| 文件                                               | 校验字段                                                               | 所属模块 |
| ------------------------------------------------ | ------------------------------------------------------------------ | ---- |
| `Auth/RegisterRequest.php`                       | name(2-50), email(唯一), password(6-100), phone(正则)                  | 认证   |
| `Auth/LoginRequest.php`                          | email, password                                                    | 认证   |
| `Auth/ChangePasswordRequest.php`                 | current\_password, new\_password(confirmed)                        | 认证   |
| `Auth/UpdateProfileRequest.php`                  | name, phone, title, specialty...(医生字段条件允许)                         | 认证   |
| `Patient/CreateAppointmentRequest.php`           | doctor\_id(exists+role+status), date(≥today), time(枚举)             | 患者   |
| `Patient/AppointmentListRequest.php`             | status(枚举), date(格式), page, per\_page                              | 患者   |
| `Patient/AIDiagnosis/CreateDiagnosisRequest.php` | symptom\_description(2-2000), appointment\_id(可选)                  | AI诊断 |
| `Doctor/CreateMedicalRecordRequest.php`          | appointment\_id, symptoms, imaging\_findings, diagnosis, treatment | 医生   |
| `Doctor/UpdateMedicalRecordRequest.php`          | 同上但全部可选                                                            | 医生   |
| `Doctor/CreatePrescriptionRequest.php`           | appointment\_id, items数组(每项drug\_id/quantity/dosage)               | 医生   |

### 11.2 验证失败处理

所有 Request 继承 `BaseRequest`，验证失败时覆盖 `failedValidation()`：

```php
protected function failedValidation(Validator $validator): void
{
    $firstError = collect($validator->errors()->toArray())->flatten()->first();
    throw new ValidationException($validator, Result::error(PARAM_ERROR, $firstError));
}
```

***

## 12. 统一响应格式

> 类：`app/Support/Result.php`

```json
// 成功
{ "code": 0, "msg": "成功", "data": {...}, "success": true, "trace_id": "uuid" }

// 失败
{ "code": 20001, "msg": "未登录", "data": null, "success": false, "trace_id": "uuid" }
```

### 错误码速查（高频）

| code  | 含义     | 触发场景            |
| ----- | ------ | --------------- |
| 0     | 成功     | 正常返回            |
| 10001 | 参数错误   | 表单验证失败          |
| 20001 | 未登录    | 无 Token         |
| 20005 | 无访问权限  | 角色不匹配           |
| 20006 | 账号被禁用  | status=disabled |
| 20008 | 密码错误   | 登录/改密校验失败       |
| 30001 | 记录不存在  | 资源未找到           |
| 40002 | 状态不可操作 | 非预期状态流转         |
| 40004 | 库存不足   | 取药时库存不够         |
| 40009 | 重复提交   | 已有进行中预约/病历      |

***

## 13. 文件清单

### 13.1 Controllers（7 个）

| 文件                                                         | 方法数 | 接口数 |
| ---------------------------------------------------------- | --- | --- |
| `app/Http/Controllers/AuthController.php`                  | 11  | 11  |
| `app/Http/Controllers/Patient/PatientController.php`       | 9   | 9   |
| `app/Http/Controllers/Patient/AIDiagnosisController.php`   | 5   | 5   |
| `app/Http/Controllers/Patient/MedicalRecordController.php` | 2   | 2   |
| `app/Http/Controllers/Patient/PrescriptionController.php`  | 5   | 5   |
| `app/Http/Controllers/Doctor/MedicalRecordController.php`  | 5   | 5   |
| `app/Http/Controllers/Doctor/PrescriptionController.php`   | 3   | 3   |
| `app/Http/Controllers/Doctor/TemplateController.php`       | 4   | 4   |

### 13.2 Models（9 个）

| 文件                                   |
| ------------------------------------ |
| `app/Models/User.php`                |
| `app/Models/Appointment.php`         |
| `app/Models/MedicalRecord.php`       |
| `app/Models/Prescription.php`        |
| `app/Models/PrescriptionItem.php`    |
| `app/Models/AIDiagnosis.php`         |
| `app/Models/Drug.php`                |
| `app/Models/DrugStock.php`           |
| `app/Models/DrugStockChange.php`     |
| `app/Models/PersonalAccessToken.php` |

### 13.3 Auth 基础设施（3 个）

| 文件                                 |
| ---------------------------------- |
| `app/Auth/HasApiTokens.php`        |
| `app/Auth/SanctumGuard.php`        |
| `app/Auth/SanctumUserProvider.php` |

### 13.4 Middleware（1 个）

| 文件                                   |
| ------------------------------------ |
| `app/Http/Middleware/EnsureRole.php` |

### 13.5 FormRequests（10 个）

| 文件                                                                 |
| ------------------------------------------------------------------ |
| `app/Http/Requests/Auth/RegisterRequest.php`                       |
| `app/Http/Requests/Auth/LoginRequest.php`                          |
| `app/Http/Requests/Auth/ChangePasswordRequest.php`                 |
| `app/Http/Requests/Auth/UpdateProfileRequest.php`                  |
| `app/Http/Requests/Patient/CreateAppointmentRequest.php`           |
| `app/Http/Requests/Patient/AppointmentListRequest.php`             |
| `app/Http/Requests/Patient/AIDiagnosis/CreateDiagnosisRequest.php` |
| `app/Http/Requests/Doctor/CreateMedicalRecordRequest.php`          |
| `app/Http/Requests/Doctor/UpdateMedicalRecordRequest.php`          |
| `app/Http/Requests/Doctor/CreatePrescriptionRequest.php`           |

### 13.6 配置与路由

| 文件                                     | 变更内容                                    |
| -------------------------------------- | --------------------------------------- |
| `routes/api.php`                       | 68 条路由（zzt 44 + gyz 24）                 |
| `bootstrap/app.php`                    | api路由注册 + sanctum guard + role别名 + 异常处理 |
| `config/auth.php`                      | 新增 sanctum guard                        |
| `app/Providers/AppServiceProvider.php` | 注册 SanctumGuard driver                  |
| `public/openapi.json`                  | Apifox 导入用 OpenAPI 文档                   |

***

> **版本**：V2.0 | **日期**：2026-07-28 | **总接口数**：44 | **代码文件数**：33

