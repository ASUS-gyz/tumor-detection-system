# Apifox 接口测试指南

> **测试范围**：ZZT 负责的 43 个接口（5 模块）
> **最后更新**：2026-07-29

---

## 一、环境准备

### 1.1 启动后端服务

```bash
cd C:\Users\ziton\Desktop\laboratory\肿瘤科智能检测门诊系统\tumor-detection-system
php artisan serve --port=8000
```

服务地址：`http://127.0.0.1:8000`

### 1.2 配置环境变量

在 Apifox 中创建环境：

| 变量名 | 值 |
|--------|-----|
| `base_url` | `http://127.0.0.1:8000/api` |
| `patient_token` | 先留空，测试时动态填入 |
| `doctor_token` | 先留空，测试时动态填入 |

### 1.3 导入接口到 Apifox

1. 打开 Apifox → 点击 **+** → **导入数据**
2. 选择 **文件导入** → 选择 `public/openapi.json`
3. 导入后，左侧会出现 5 个模块分组

### 1.4 配置邮件服务（忘记密码需要）

编辑 `.env`：

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.qq.com
MAIL_PORT=465
MAIL_USERNAME=你的QQ邮箱@qq.com
MAIL_PASSWORD=你的QQ邮箱16位授权码
MAIL_FROM_ADDRESS=你的QQ邮箱@qq.com
MAIL_FROM_NAME="肿瘤科智能检测门诊系统"
APP_LOCALE=zh_CN
AI_MODE=remote
```

> QQ 邮箱授权码：网页登录 QQ 邮箱 → 设置 → 账户 → 开启 SMTP 服务 → 得到 16 位授权码。
> `AI_MODE=mock` 则不走 DeepSeek API，使用本地模拟数据（测试时不消耗 token）。
> `APP_LOCALE=zh_CN` 确保所有验证错误消息为中文。

### 1.5 准备测试数据

终端执行：

```bash
php artisan tinker
```

```php
// 创建测试医生
User::create(['name'=>'王医生','email'=>'doctor@test.com','password'=>'Doctor@123','role'=>'doctor','title'=>'主任医师','specialty'=>'肺部肿瘤','department'=>'肿瘤内科','introduction'=>'从医20年','experience_years'=>20,'status'=>'active']);

// 创建药品
$d1 = Drug::create(['name'=>'吉非替尼片','category'=>'靶向药物','specification'=>'250mg×10片/盒','unit'=>'盒','stock_quantity'=>0,'price'=>1580.00]);
$d2 = Drug::create(['name'=>'奥希替尼片','category'=>'靶向药物','specification'=>'80mg×30片/盒','unit'=>'盒','stock_quantity'=>0,'price'=>5580.00]);

// 创建库存
DrugStock::create(['drug_id'=>$d1->id,'quantity'=>100,'min_stock'=>5]);
DrugStock::create(['drug_id'=>$d2->id,'quantity'=>50,'min_stock'=>5]);

echo 'doctor_id='.User::where('email','doctor@test.com')->value('id').' drug1='.$d1->id.' drug2='.$d2->id;
exit
```

---

## 二、测试顺序

```
注册患者 → 登录拿token → 查医生 → 创建预约 → AI诊断 → 看病历处方 → 取药
                                                                     ↓
医生登录 → 创建病历 → 编辑病历 → 开具处方 → 模板管理 → 病历对比
```

---

## 三、认证模块（10接口）

### 3.1 注册

```
POST {{base_url}}/auth/register
Authorization: 无
```

```json
{ "name": "测试患者", "email": "patient_test@apifox.com", "password": "Patient@123", "phone": "13800001111" }
```

> 密码规则：≥8位，必须包含大写+小写+数字+特殊符号中至少3种。

**后置脚本**：
```javascript
const res = JSON.parse(pm.response.text());
if (res.code === 0) { pm.environment.set("patient_token", res.data.token); }
```

### 3.2 登录

```
POST {{base_url}}/auth/login
Authorization: 无
```

```json
{ "email": "patient_test@apifox.com", "password": "Patient@123" }
```

### 3.3 获取当前用户

```
GET {{base_url}}/auth/me
Authorization: Bearer {{patient_token}}
```

### 3.4 更新资料

```
PUT {{base_url}}/auth/profile
Authorization: Bearer {{patient_token}}
```

```json
{ "name": "测试患者(已更新)", "phone": "13900002222" }
```

### 3.5 修改密码

```
PUT {{base_url}}/auth/password
Authorization: Bearer {{patient_token}}
```

```json
{ "current_password": "Patient@123", "new_password": "NewPass@456", "new_password_confirmation": "NewPass@456" }
```

### 3.6 上传头像

```
POST {{base_url}}/auth/avatar
Authorization: Bearer {{patient_token}}
Content-Type: multipart/form-data
```

| 参数 | 类型 | 值 |
|------|------|-----|
| avatar | File | 选一张 .png/.jpg 图片 |

### 3.7 忘记密码

```
POST {{base_url}}/auth/forgot-password
Authorization: 无
```

```json
{ "email": "patient_test@apifox.com" }
```

> 系统发送 6 位数字验证码到邮箱。开发阶段验证码同时写入 `storage/logs/laravel.log`，终端执行 `tail -f storage/logs/laravel.log` 可实时查看。

### 3.8 重置密码

```
POST {{base_url}}/auth/reset-password
Authorization: 无
```

```json
{ "email": "patient_test@apifox.com", "token": "123456", "password": "ResetPwd@789", "password_confirmation": "ResetPwd@789" }
```

> `token` 是邮箱收到的 6 位验证码，60 分钟有效，用完即删。

### 3.9 退出登录

```
POST {{base_url}}/auth/logout
Authorization: Bearer {{patient_token}}
```

> 退出后调 `GET /auth/me` → 应返回 20001（未登录）。

### 3.10 注销账号

```
DELETE {{base_url}}/auth/account
Authorization: Bearer {{patient_token}}
```

```json
{ "password": "Patient@123" }
```

> 注销后登录 → 20006（账号禁用）。恢复：`User::where('email','patient_test@apifox.com')->update(['status'=>'active'])`

---

## 四、患者端-预约管理（9接口）

### 4.1 首页统计

```
GET {{base_url}}/patient/dashboard
Authorization: Bearer {{patient_token}}
```

### 4.2 医生列表

```
GET {{base_url}}/patient/doctors?keyword=肿瘤&page=1&per_page=10
Authorization: Bearer {{patient_token}}
```

### 4.3 医生详情

```
GET {{base_url}}/patient/doctors/2
Authorization: Bearer {{patient_token}}
```

> `2` 替换为实际 doctor_id。

### 4.4 创建预约

```
POST {{base_url}}/patient/appointments
Authorization: Bearer {{patient_token}}
```

```json
{ "doctor_id": 2, "appointment_date": "2026-08-01", "appointment_time": "09:15" }
```

> 记下返回的 `appointment_id`。已有进行中预约 → 40009。

### 4.5 可预约时段

```
GET {{base_url}}/patient/appointments/available-slots?doctor_id=2&date=2026-08-01
Authorization: Bearer {{patient_token}}
```

### 4.6 我的预约列表

```
GET {{base_url}}/patient/appointments?status=pending&page=1
Authorization: Bearer {{patient_token}}
```

### 4.7 预约详情

```
GET {{base_url}}/patient/appointments/1
Authorization: Bearer {{patient_token}}
```

> 一条接口返回：预约+医生+病历+处方+AI诊断。

### 4.8 取消预约

```
DELETE {{base_url}}/patient/appointments/1
Authorization: Bearer {{patient_token}}
```

> 仅 `pending` 可取消。

### 4.9 就诊评价

```
POST {{base_url}}/patient/appointments/1/review
Authorization: Bearer {{patient_token}}
```

```json
{ "rating": 5, "content": "医生专业，态度好" }
```

> 仅 `completed` 可评价。

---

## 五、患者端-AI文字诊断（5接口）

### 5.1 AI诊断

```
POST {{base_url}}/patient/ai-diagnosis
Authorization: Bearer {{patient_token}}
```

```json
{ "symptom_description": "持续咳嗽三周，伴有胸痛和咳血丝，体重下降，吸烟史30年" }
```

> 调用 DeepSeek API（`.env` 中 `AI_MODE=remote`）。设 `AI_MODE=mock` 则用模拟数据。

### 5.2 诊断列表

```
GET {{base_url}}/patient/ai-diagnosis?page=1
Authorization: Bearer {{patient_token}}
```

### 5.3 诊断详情

```
GET {{base_url}}/patient/ai-diagnosis/1
Authorization: Bearer {{patient_token}}
```

### 5.4 AI追问

```
POST {{base_url}}/patient/ai-diagnosis/continue
Authorization: Bearer {{patient_token}}
```

```json
{ "diagnosis_id": 1, "question": "我需要做什么检查？" }
```

### 5.5 导出报告

```
GET {{base_url}}/patient/ai-diagnosis/1/export
Authorization: Bearer {{patient_token}}
```

---

## 六、患者端-病历与处方（7接口）

### 6.1 病历列表

```
GET {{base_url}}/patient/medical-records?page=1
Authorization: Bearer {{patient_token}}
```

### 6.2 病历详情

```
GET {{base_url}}/patient/medical-records/1
Authorization: Bearer {{patient_token}}
```

### 6.3 处方列表

```
GET {{base_url}}/patient/prescriptions?status=pending
Authorization: Bearer {{patient_token}}
```

### 6.4 处方详情

```
GET {{base_url}}/patient/prescriptions/1
Authorization: Bearer {{patient_token}}
```

### 6.5 确认取药

```
POST {{base_url}}/patient/prescriptions/1/confirm
Authorization: Bearer {{patient_token}}
```

> 事务执行：锁行 → 扣库存 → 记日志 → 改状态。库存不足 → 40004。

### 6.6 续方申请

```
POST {{base_url}}/patient/prescriptions/1/refill
Authorization: Bearer {{patient_token}}
```

> 仅 `dispensed` 可续方。

### 6.7 用药提醒

```
GET {{base_url}}/patient/medication-reminders
Authorization: Bearer {{patient_token}}
```

---

## 七、医生端-病历与处方（12接口）

### 7.0 登录医生

```
POST {{base_url}}/auth/login
Authorization: 无
```

```json
{ "email": "doctor@test.com", "password": "Doctor@123" }
```

**后置脚本**：
```javascript
const res = JSON.parse(pm.response.text());
if (res.code === 0) { pm.environment.set("doctor_token", res.data.token); }
```

> 先在 tinker 中创建预约并改状态：`Appointment::where(...)->update(['status'=>'in_progress'])`

### 7.1 创建病历

```
POST {{base_url}}/doctor/medical-records
Authorization: Bearer {{doctor_token}}
```

```json
{ "appointment_id": 1, "symptoms": "咳嗽三周，胸痛咳血", "imaging_findings": "CT示右上肺占位", "preliminary_diagnosis": "肺癌可能", "treatment_plan": "建议穿刺活检" }
```

### 7.2 编辑病历

```
PUT {{base_url}}/doctor/medical-records/1
Authorization: Bearer {{doctor_token}}
```

```json
{ "preliminary_diagnosis": "肺癌(cT1bN0M0,IA2期)", "treatment_plan": "胸腔镜手术" }
```

### 7.3 病历详情

```
GET {{base_url}}/doctor/medical-records/1
Authorization: Bearer {{doctor_token}}
```

### 7.4 历史病历

```
GET {{base_url}}/doctor/medical-records?page=1
Authorization: Bearer {{doctor_token}}
```

### 7.5 病历对比

```
GET {{base_url}}/doctor/medical-records/compare?ids=1,2
Authorization: Bearer {{doctor_token}}
```

### 7.6 开具处方

```
POST {{base_url}}/doctor/prescriptions
Authorization: Bearer {{doctor_token}}
```

```json
{ "appointment_id": 1, "items": [{ "drug_id": 1, "quantity": 3, "dosage": "每日1次每次1片", "instructions": "空腹服用" }] }
```

> 库存不足 → 40004。开具时不扣库存。

### 7.7 处方详情

```
GET {{base_url}}/doctor/prescriptions/1
Authorization: Bearer {{doctor_token}}
```

### 7.8 历史处方

```
GET {{base_url}}/doctor/prescriptions?page=1
Authorization: Bearer {{doctor_token}}
```

### 7.9-7.12 模板管理

```
POST /doctor/medical-record-templates   保存病历模板
GET  /doctor/medical-record-templates   病历模板列表
POST /doctor/prescription-templates     保存处方模板
GET  /doctor/prescription-templates     处方模板列表
```

---

## 八、测试数据清理

```php
PrescriptionItem::query()->delete();
Prescription::query()->delete();
MedicalRecord::query()->delete();
AIDiagnosis::query()->delete();
Appointment::query()->delete();
PersonalAccessToken::query()->delete();
DrugStockChange::query()->delete();
DrugStock::query()->delete();
Drug::query()->delete();
User::where('email','patient_test@apifox.com')->delete();
User::where('email','doctor@test.com')->delete();
```
