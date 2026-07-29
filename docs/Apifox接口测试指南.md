# Apifox 接口测试指南

> **测试范围**：ZZT 负责的 43 个接口（5 模块）
> **测试顺序**：按接口间的数据依赖关系编排

***

## 一、环境准备

### 1.1 启动后端服务

```bash
cd C:\Users\ziton\Desktop\laboratory\肿瘤科智能检测门诊系统\tumor-detection-system
php artisan serve --port=8000
```

服务地址：`http://127.0.0.1:8000`

### 1.2 配置邮件服务（忘记密码重置功能依赖）

编辑 `.env` 文件，填入 QQ 邮箱 SMTP 信息：

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.qq.com
MAIL_PORT=465
MAIL_USERNAME=你的QQ邮箱@qq.com
MAIL_PASSWORD=你的QQ邮箱16位授权码
MAIL_FROM_ADDRESS=你的QQ邮箱@qq.com
MAIL_FROM_NAME="肿瘤科智能检测门诊系统"
```

> QQ 邮箱授权码获取：网页登录 QQ 邮箱 → 设置 → 账户 → 开启 SMTP 服务 → 发短信验证 → 得到 16 位授权码。
>
> 不配邮件也能测试——开发模式下 `forgot-password` 会额外返回 `debug_token`。

### 1.3 导入接口到 Apifox

1. 打开 Apifox → 点击 **+** → **导入数据**
2. 选择 **文件导入** → 选择 `public/openapi.json`
3. 导入后，左侧会出现 5 个模块分组

### 1.4 配置环境变量

在 Apifox 中创建环境：

| 变量名             | 值                           |
| --------------- | --------------------------- |
| `base_url`      | `http://127.0.0.1:8000/api` |
| `patient_token` | 先留空，测试时动态填入                 |
| `doctor_token`  | 先留空，测试时动态填入                 |

### 1.5 准备测试数据

数据库需要预先准备 test doctor。在终端执行：

```bash
php artisan tinker
```

```php
// 创建测试医生（后续接口依赖）—— 医生由后台创建，不走注册校验，密码可以简单
User::create(['name'=>'王医生','email'=>'doctor@test.com','password'=>'Doctor@123','role'=>'doctor','title'=>'主任医师','specialty'=>'肺部肿瘤','department'=>'肿瘤内科','introduction'=>'从医20年','experience_years'=>20,'status'=>'active']);

// 创建测试药品（处方接口依赖）
$d = Drug::create(['name'=>'吉非替尼片','category'=>'靶向药物','specification'=>'250mg×10片/盒','unit'=>'盒','stock_quantity'=>0,'price'=>1580.00]);
DrugStock::create(['drug_id'=>$d->id,'quantity'=>100,'min_stock'=>5]);

$d2 = Drug::create(['name'=>'奥希替尼片','category'=>'靶向药物','specification'=>'80mg×30片/盒','unit'=>'盒','stock_quantity'=>0,'price'=>5580.00]);
DrugStock::create(['drug_id'=>$d2->id,'quantity'=>50,'min_stock'=>5]);

// 记录 doctor_id 和 drug_id
echo 'doctor_id=' . User::where('email','doctor@test.com')->value('id');
echo 'drug1_id=' . $d->id . ' drug2_id=' . $d2->id;
exit
```

***

## 二、测试顺序（依赖链）

```
① 注册患者 → ② 登录获取token → ③ 查医生列表 → ④ 创建预约
                                            ↓
⑤ 患者端：查仪表盘、查预约、AI诊断、看病历处方、取药
                                            ↓
⑥ 医生端：创建病历 → 编辑病历 → 开具处方 → 病历对比 → 模板管理
```

***

## 三、模块一：认证模块（10接口）

### 3.1 注册患者

```
POST {{base_url}}/auth/register
Authorization: 无
```

**密码规则**（新增）：最少 8 位，必须包含大写字母、小写字母、数字、特殊符号中至少 3 种。

**请求体**：

```json
{
  "name": "测试患者",
  "email": "patient_test@apifox.com",
  "password": "Patient@123",
  "phone": "13800001111"
}
```

**预期响应**：`code: 0`，返回 `user` 对象和 `token` 字符串。

**Apifox 后置脚本**（自动提取 token）：

```javascript
const res = JSON.parse(pm.response.text());
if (res.code === 0) {
    pm.environment.set("patient_token", res.data.token);
}
```

### 3.2 登录

```
POST {{base_url}}/auth/login
Authorization: 无
```

```json
{
  "email": "patient_test@apifox.com",
  "password": "Patient@123"
}
```

**预期**：`code: 0`，`token_type: "Bearer"`。

### 3.3 获取当前用户信息

```
GET {{base_url}}/auth/me
Authorization: Bearer {{patient_token}}
```

**预期**：返回用户的完整信息（id, name, email, role, phone...）。

### 3.4 更新个人资料

```
PUT {{base_url}}/auth/profile
Authorization: Bearer {{patient_token}}
```

```json
{
  "name": "测试患者（已更新）",
  "phone": "13900002222"
}
```

**预期**：`code: 0`，返回更新后的用户信息。

### 3.5 修改密码

```
PUT {{base_url}}/auth/password
Authorization: Bearer {{patient_token}}
```

```json
{
  "current_password": "Patient@123",
  "new_password": "NewPass@456",
  "new_password_confirmation": "NewPass@456"
}
```

**预期**：`code: 0`，`msg: "密码修改成功"`。

> 新密码同样需要满足 3/4 复杂度规则。修改后用新密码重新登录确认，然后改回原密码以继续测试。

### 3.6 上传头像

```
POST {{base_url}}/auth/avatar
Authorization: Bearer {{patient_token}}
Content-Type: multipart/form-data
```

| 参数     | 类型   | 值                  |
| ------ | ---- | ------------------ |
| avatar | File | 选一张本地 .png/.jpg 图片 |

**预期**：`code: 0`，返回 `avatar_url`。

### 3.7 忘记密码

```
POST {{base_url}}/auth/forgot-password
Authorization: 无
```

```json
{
  "email": "patient_test@apifox.com"
}
```

**业务逻辑**：

```
输入邮箱 → 生成6位数字验证码(60分钟有效) → 发送邮件 → 返回"验证码已发送"
```

**预期**：`code: 0`，`msg: "验证码已发送至您的邮箱，请查收"`。

> ⚠️ 验证码只发到邮箱，API 响应中不包含验证码。
> Apifox 测试时查看验证码：另开终端执行 `tail -f storage/logs/laravel.log`，发送请求后日志会输出 `密码重置验证码`。

### 3.8 重置密码

```
POST {{base_url}}/auth/reset-password
Authorization: 无
```

```json
{
  "email": "patient_test@apifox.com",
  "token": "邮箱收到的6位数字验证码",
  "password": "ResetPwd@789",
  "password_confirmation": "ResetPwd@789"
}
```

**校验规则**：

1. 验证码必须与邮箱收到的或日志中的一致
2. 验证码自创建起 **60 分钟内有效**，过期需重新获取

**预期**：`code: 0`，`msg: "密码重置成功"`。

**异常情况**：

| 场景        | code  | msg            |
| --------- | ----- | -------------- |
| 邮箱未注册     | 10001 | 该邮箱未注册         |
| 验证码无效     | 10005 | 重置令牌无效         |
| 验证码超过60分钟 | 10005 | 重置令牌已过期，请重新获取  |
| 密码不符合复杂度  | 10001 | 密码必须包含3种以上字符类型 |
| 两次密码不一致   | 10001 | 两次输入的密码不一致     |

> 重置后的密码同样需要满足 3/4 复杂度规则。验证码使用后立即删除，不能重复使用。

### 3.9 退出登录

```
POST {{base_url}}/auth/logout
Authorization: Bearer {{patient_token}}
```

**预期**：`code: 0`。

> 退出后立即调用 `GET /auth/me` → 应返回 `code: 20001`（未登录）。

### 3.10 账号注销

```
DELETE {{base_url}}/auth/account
Authorization: Bearer {{patient_token}}
```

```json
{
  "password": "Patient@123"
}
```

**预期**：`code: 0`，`msg: "账号已注销"`。

> 注销后尝试登录 → 应返回 `code: 20006`（账号被禁用）。
>
> 手动恢复：`php artisan tinker` → `User::where('email','patient_test@apifox.com')->update(['status'=>'active'])`

***

## 四、模块二：患者端-预约管理（9接口）

> 前置条件：已登录（有 `patient_token`），数据库中存在医生。

### 4.1 患者首页统计

```
GET {{base_url}}/patient/dashboard
Authorization: Bearer {{patient_token}}
```

**预期**：

```json
{
  "code": 0,
  "data": {
    "pending_count": 0,
    "completed_count": 0,
    "ai_diagnosis_count": 0,
    "next_appointment": null
  }
}
```

### 4.2 医生列表

```
GET {{base_url}}/patient/doctors?keyword=肿瘤&page=1&per_page=10
Authorization: Bearer {{patient_token}}
```

**预期**：分页返回 `active` 的医生列表，包含 name/title/specialty/department。

### 4.3 医生详情

```
GET {{base_url}}/patient/doctors/2
Authorization: Bearer {{patient_token}}
```

> `2` 替换为实际 doctor\_id。

**预期**：返回医生完整信息（含 phone）。

### 4.4 创建预约

```
POST {{base_url}}/patient/appointments
Authorization: Bearer {{patient_token}}
```

```json
{
  "doctor_id": 2,
  "appointment_date": "2026-08-01",
  "appointment_time": "09:15"
}
```

**预期**：`code: 0`，返回预约信息 + 医生姓名/职称。

> 记下返回的 `appointment_id`，后续步骤要用。

### 4.5 可预约时段查询

```
GET {{base_url}}/patient/appointments/available-slots?doctor_id=2&date=2026-08-01
Authorization: Bearer {{patient_token}}
```

**预期**：

```json
{
  "code": 0,
  "data": {
    "all_slots": ["08:30","09:15","10:00","10:45","13:30","14:15","15:00","15:45"],
    "booked_slots": ["09:15"],
    "available_slots": ["08:30","10:00","10:45","13:30","14:15","15:00","15:45"]
  }
}
```

### 4.6 我的预约列表

```
GET {{base_url}}/patient/appointments?status=pending&page=1&per_page=10
Authorization: Bearer {{patient_token}}
```

**预期**：分页返回本人预约。

### 4.7 预约详情

```
GET {{base_url}}/patient/appointments/1
Authorization: Bearer {{patient_token}}
```

> `1` 替换为实际 appointment\_id。

**预期**：返回预约 + 医生 + 病历(null) + 处方(null) + AI诊断(null)。

### 4.8 取消预约

```
DELETE {{base_url}}/patient/appointments/1
Authorization: Bearer {{patient_token}}
```

**预期**：`code: 0`，`msg: "预约已取消"`。

> 重新创建一个预约（供后续测试用）。

### 4.9 就诊评价

```
POST {{base_url}}/patient/appointments/1/review
Authorization: Bearer {{patient_token}}
```

```json
{
  "rating": 5,
  "content": "王医生态度好，诊断准确"
}
```

**预期**：仅 `completed` 状态可评价。如预约不是此状态会返回 `40002`。

***

## 五、模块三：患者端-AI文字诊断（5接口）

### 5.1 AI文字智能诊断

```
POST {{base_url}}/patient/ai-diagnosis
Authorization: Bearer {{patient_token}}
```

```json
{
  "symptom_description": "持续咳嗽三周，伴有胸痛和偶尔咳血丝，最近体重下降明显，有30年吸烟史"
}
```

**预期**：`code: 0`，返回完整诊断报告（analysis / risk\_level / risk\_warning / advice / possible\_conditions）。

> 记下返回的 `id`（diagnosis\_id），后续步骤要用。

### 5.2 AI诊断记录列表

```
GET {{base_url}}/patient/ai-diagnosis?page=1&per_page=10
Authorization: Bearer {{patient_token}}
```

**预期**：分页列表，`symptom_description` 截取前 50 字。

### 5.3 AI诊断报告详情

```
GET {{base_url}}/patient/ai-diagnosis/1
Authorization: Bearer {{patient_token}}
```

> `1` 替换为实际 diagnosis\_id。

**预期**：完整诊断信息，含 `possible_conditions` JSON 数组。

### 5.4 AI诊断追问（多轮对话）

```
POST {{base_url}}/patient/ai-diagnosis/continue
Authorization: Bearer {{patient_token}}
```

```json
{
  "diagnosis_id": 1,
  "question": "我需要做什么检查来进一步确认？"
}
```

**预期**：`code: 0`，返回追问回复（新诊断记录附带 `previous_id`）。

### 5.5 导出PDF诊断报告

```
GET {{base_url}}/patient/ai-diagnosis/1/export
Authorization: Bearer {{patient_token}}
```

**预期**：返回报告结构化数据（title / report\_no / risk\_level / analysis / disclaimer）。

***

## 六、模块四：患者端-病历与处方（7接口）

> 前置条件：需要医生端创建病历和处方（先跳到第七节创建数据，再回来测试查看功能）。

### 6.1 我的病历列表

```
GET {{base_url}}/patient/medical-records?page=1&per_page=10
Authorization: Bearer {{patient_token}}
```

**预期**：分页列表，含症状/诊断截取 + 医生姓名 + 预约日期。

### 6.2 病历详情

```
GET {{base_url}}/patient/medical-records/1
Authorization: Bearer {{patient_token}}
```

**预期**：完整病历 + 医生信息 + 预约时间。

### 6.3 我的处方列表

```
GET {{base_url}}/patient/prescriptions?status=pending&page=1
Authorization: Bearer {{patient_token}}
```

**预期**：分页列表，含 `item_count`。

### 6.4 处方详情

```
GET {{base_url}}/patient/prescriptions/1
Authorization: Bearer {{patient_token}}
```

**预期**：处方 + 药品明细（drug\_name / specification / quantity / dosage / instructions）。

### 6.5 确认取药 ⭐核心事务

```
POST {{base_url}}/patient/prescriptions/1/confirm
Authorization: Bearer {{patient_token}}
```

**预期**：`code: 0`，`msg: "取药成功，库存已自动扣减"`。

> **验证库存扣减**：终端执行 `php artisan tinker` → `DrugStock::first()->quantity` → 应减少对应数量。
>
> 再次点击确认取药 → 应返回 `code: 40002`"该处方已取药，请勿重复操作"。

### 6.6 处方续方申请

```
POST {{base_url}}/patient/prescriptions/1/refill
Authorization: Bearer {{patient_token}}
```

**预期**：`code: 0`，返回原处方药品列表 + "续方申请已提交"。

> 仅 `dispensed` 状态可续方。

### 6.7 每日用药提醒

```
GET {{base_url}}/patient/medication-reminders
Authorization: Bearer {{patient_token}}
```

**预期**：返回 `pending` 状态的处方药品列表。

***

## 七、模块五：医生端-病历与处方（12接口）

> 前置条件：需要 doctor\_token。

### 7.0 登录医生获取 token

```
POST {{base_url}}/auth/login
Authorization: 无
```

```json
{
  "email": "doctor@test.com",
  "password": "Doctor@123"
}
```

**Apifox 后置脚本**：

```javascript
const res = JSON.parse(pm.response.text());
if (res.code === 0) {
    pm.environment.set("doctor_token", res.data.token);
}
```

> 预先创建一个患者预约（用 `patient_token` 调 4.4）。
>
> 然后在 tinker 中将预约状态改为接诊中：
>
> ```php
> Appointment::where('patient_id', 患者ID)->update(['status'=>'in_progress'])
> ```

### 7.1 创建病历

```
POST {{base_url}}/doctor/medical-records
Authorization: Bearer {{doctor_token}}
```

```json
{
  "appointment_id": 1,
  "symptoms": "患者咳嗽三周，伴有胸痛和咳血丝，近期体重下降。吸烟史30年。",
  "imaging_findings": "胸部CT：右上肺见约3cm×2.5cm结节，边缘毛糙，有毛刺征。",
  "preliminary_diagnosis": "右上肺占位，考虑周围型肺癌可能。",
  "treatment_plan": "建议CT引导下穿刺活检；完善肿瘤标志物和PET-CT。"
}
```

**预期**：`code: 0`，返回病历 + 患者信息 + 预约信息。

### 7.2 编辑病历

```
PUT {{base_url}}/doctor/medical-records/1
Authorization: Bearer {{doctor_token}}
```

```json
{
  "preliminary_diagnosis": "右上肺占位，考虑周围型肺癌（cT1bN0M0，IA2期）。",
  "treatment_plan": "建议胸腔镜下右上肺叶切除+纵隔淋巴结清扫术。"
}
```

**预期**：`code: 0`，仅更新传入的字段。

### 7.3 病历详情

```
GET {{base_url}}/doctor/medical-records/1
Authorization: Bearer {{doctor_token}}
```

**预期**：完整病历 + 患者信息(name/phone) + 预约时间。

### 7.4 历史病历列表

```
GET {{base_url}}/doctor/medical-records?page=1&per_page=10
Authorization: Bearer {{doctor_token}}
```

**预期**：分页列表，含患者姓名/手机/症状截取。

### 7.5 多份病历对比

```
GET {{base_url}}/doctor/medical-records/compare?ids=1,2,3
Authorization: Bearer {{doctor_token}}
```

**预期**：平铺返回多份病历的完整信息。

### 7.6 开具处方 ⭐

```
POST {{base_url}}/doctor/prescriptions
Authorization: Bearer {{doctor_token}}
```

```json
{
  "appointment_id": 1,
  "items": [
    {
      "drug_id": 2,
      "quantity": 3,
      "dosage": "每日1次，每次1片，空腹服用",
      "instructions": "服药期间避免暴晒，注意皮疹不良反应"
    },
    {
      "drug_id": 3,
      "quantity": 2,
      "dosage": "每日2次，每次1片",
      "instructions": "定期复查肝功能"
    }
  ]
}
```

> `drug_id` 替换为实际药品 ID。

**预期**：`code: 0`，返回处方 + 药品明细(name/specification/dosage)。

> **库存不够时**（如填 quantity:9999）→ `code: 40004`"以下药品库存不足：XXX"。

### 7.7 处方详情

```
GET {{base_url}}/doctor/prescriptions/1
Authorization: Bearer {{doctor_token}}
```

**预期**：处方 + 患者信息 + 药品明细。

### 7.8 历史处方列表

```
GET {{base_url}}/doctor/prescriptions?page=1&per_page=10
Authorization: Bearer {{doctor_token}}
```

**预期**：分页列表，含患者姓名/手机/`item_count`。

### 7.9 保存病历模板

```
POST {{base_url}}/doctor/medical-record-templates
Authorization: Bearer {{doctor_token}}
```

```json
{
  "name": "肺癌初诊模板",
  "symptoms": "咳嗽、胸痛、咯血、消瘦",
  "imaging_findings": "CT示肺部占位",
  "preliminary_diagnosis": "肺部占位待查",
  "treatment_plan": "建议穿刺活检"
}
```

**预期**：`code: 0`，返回模板。

### 7.10 病历模板列表

```
GET {{base_url}}/doctor/medical-record-templates
Authorization: Bearer {{doctor_token}}
```

**预期**：列出已保存的模板。

### 7.11 保存处方模板

```
POST {{base_url}}/doctor/prescription-templates
Authorization: Bearer {{doctor_token}}
```

```json
{
  "name": "肺癌靶向方案",
  "items": [
    {"drug_id": 2, "quantity": 3, "dosage": "每日1次，每次1片", "instructions": "空腹服用"}
  ]
}
```

### 7.12 处方模板列表

```
GET {{base_url}}/doctor/prescription-templates
Authorization: Bearer {{doctor_token}}
```

***

## 八、异常场景测试清单

| #  | 测试场景           | 接口                                       | 预期 code |
| -- | -------------- | ---------------------------------------- | ------- |
| 1  | 未登录访问需认证接口     | 任意 protect 接口                            | 20001   |
| 2  | 患者访问医生接口       | GET /doctor/medical-records              | 20005   |
| 3  | 登录禁用账号         | POST /auth/login                         | 20006   |
| 4  | 登录错误密码         | POST /auth/login                         | 20008   |
| 5  | 注册已存在邮箱        | POST /auth/register                      | 30003   |
| 6  | 创建第二个进行中预约     | POST /patient/appointments               | 40009   |
| 7  | 取消非pending预约   | DELETE /patient/appointments/{id}        | 40002   |
| 8  | 取药库存不足         | POST /patient/prescriptions/{id}/confirm | 40004   |
| 9  | 重复确认取药         | POST /patient/prescriptions/{id}/confirm | 40002   |
| 10 | 查看他人病历         | GET /patient/medical-records/{id}        | 30001   |
| 11 | 上传非图片头像        | POST /auth/avatar                        | 10001   |
| 12 | 预约过去日期         | POST /patient/appointments               | 10001   |
| 13 | 无效预约时段         | POST /patient/appointments               | 10001   |
| 14 | 重复创建同一预约的病历    | POST /doctor/medical-records             | 40009   |
| 15 | 空症状描述AI诊断      | POST /patient/ai-diagnosis               | 10001   |
| 16 | 密码少于8位         | POST /auth/register                      | 10001   |
| 17 | 密码复杂度不足（仅2种类型） | POST /auth/register                      | 10001   |

***

## 九、测试数据清理

测试完成后清理：

```php
// php artisan tinker
PrescriptionItem::query()->delete();
Prescription::query()->delete();
MedicalRecord::query()->delete();
AIDiagnosis::query()->delete();
Appointment::query()->delete();
PersonalAccessToken::query()->delete();
DrugStock::query()->delete();
DrugStockChange::query()->delete();
Drug::query()->delete();
User::where('email', 'patient_test@apifox.com')->delete();
User::where('email', 'doctor@test.com')->delete();
```

***

> **文档版本**：V1.0 | **日期**：2026-07-29

