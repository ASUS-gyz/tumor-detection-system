# GYZ 模块 — 前端对接接口文档

> **Base URL：** `http://127.0.0.1:8000/api`（本地） / 线上按实际域名
>
> **鉴权方式：** 所有接口需登录，Headers 带 `Authorization: Bearer <token>`
>
> **token 获取：** `POST /auth/login` Body: `{"email":"...","password":"..."}`，从返回 `data.token` 取值
>
> **权限角色：** `doctor` 医生端 / `admin` 管理员端 / 任意登录用户（通知模块）
>
> **接口总数：** 44 个
>
> **时间格式：** 纯日期 `"2026-07-30"`，时间戳 `"2026-07-30 19:46:36"`（北京时间）

---

## 通用约定

### 响应格式

所有接口统一返回：

```json
{
    "code": 0,
    "msg": "成功",
    "data": {},
    "success": true,
    "trace_id": "uuid"
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| code | number | 业务状态码，0=成功 |
| msg | string | 提示信息 |
| data | mixed | 返回数据，可能为对象、数组或 null |
| success | boolean | true=成功 false=失败 |

### 分页响应

分页列表的 `data` 结构：

```json
{
    "list": [],
    "page": 1,
    "size": 10,
    "total": 100,
    "total_pages": 10
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 数据列表 |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

### 预约状态枚举

| 值 | 说明 |
|------|------|
| pending | 待接诊 |
| called | 已叫号 |
| in_progress | 接诊中 |
| completed | 已完成 |
| cancelled | 已取消 |

---

# 一、医生端 — 接诊管理

> **权限：** doctor 角色
>
> **Headers：** `Authorization: Bearer <token>`

---

## 1. `GET /doctor/dashboard — 医生工作台统计`

**请求参数：** 无

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| today_appointments | number | 今日预约总数 |
| pending_count | number | 待叫号数量 |
| in_progress_count | number | 接诊中数量 |
| completed_today | number | 今日已完成数量 |
| total_drugs | number | 药品种类总数 |
| low_stock_drugs | number | 低库存药品数（库存<10） |

**返回示例：**
```json
{
    "code": 0,
    "msg": "成功",
    "data": {
        "today_appointments": 8,
        "pending_count": 3,
        "in_progress_count": 1,
        "completed_today": 4,
        "total_drugs": 156,
        "low_stock_drugs": 5
    },
    "success": true
}
```

---

## 2. `GET /doctor/appointments — 预约列表`

> 默认返回今日预约，可按日期、状态、患者名筛选。

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 20，最大 100 |
| date | string | 否 | 日期 Y-m-d，不填默认当天 |
| status | string | 否 | pending / called / in_progress / completed / cancelled |
| patient_name | string | 否 | 患者姓名模糊搜索 |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 预约列表 |
| list[].id | number | 预约 ID |
| list[].patient_id | number | 患者 ID |
| list[].patient_name | string | 患者姓名 |
| list[].patient_phone | string | 患者手机号 |
| list[].appointment_date | string | 预约日期，格式 Y-m-d |
| list[].appointment_time | string | 预约时段，格式 H:i |
| list[].status | string | pending / called / in_progress / completed / cancelled |
| list[].created_at | string | 创建时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

**返回示例：**
```json
{
    "code": 0,
    "data": {
        "list": [
            {
                "id": 1,
                "patient_id": 10,
                "patient_name": "张三",
                "patient_phone": "13800000001",
                "appointment_date": "2026-07-30",
                "appointment_time": "09:15",
                "status": "pending",
                "created_at": "2026-07-29 10:30:00"
            }
        ],
        "page": 1,
        "size": 20,
        "total": 8,
        "total_pages": 1
    },
    "success": true
}
```

---

## 3. `GET /doctor/appointments/{id} — 预约详情`

> 含患者历史就诊记录和 AI 诊断记录。

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 预约 ID |

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | number | 预约 ID |
| patient_id | number | 患者 ID |
| patient_name | string | 患者姓名 |
| patient_phone | string | 患者手机号 |
| patient_email | string | 患者邮箱 |
| appointment_date | string | 预约日期，格式 Y-m-d |
| appointment_time | string | 预约时段，格式 H:i |
| status | string | pending / called / in_progress / completed / cancelled |
| history_records | array | 患者历史就诊记录 |
| history_records[].id | number | 病历 ID |
| history_records[].appointment_id | number | 关联预约 ID |
| history_records[].appointment_date | string | 就诊日期，格式 Y-m-d |
| history_records[].preliminary_diagnosis | string | 初步诊断 |
| ai_diagnoses | array | 该预约关联的 AI 诊断记录 |
| ai_diagnoses[].id | number | 诊断报告 ID |
| ai_diagnoses[].type | string | text（文字诊断）/ image（图像诊断） |
| ai_diagnoses[].risk_level | string | 风险等级（低风险/中风险/高风险） |
| ai_diagnoses[].created_at | string | 诊断时间，格式 Y-m-d H:i:s |
| created_at | string | 预约创建时间，格式 Y-m-d H:i:s |
| updated_at | string | 最后更新时间，格式 Y-m-d H:i:s |

**返回示例：**
```json
{
    "code": 0,
    "data": {
        "id": 1,
        "patient_id": 10,
        "patient_name": "张三",
        "patient_phone": "13800000001",
        "patient_email": "zhangsan@example.com",
        "appointment_date": "2026-07-30",
        "appointment_time": "09:15",
        "status": "pending",
        "history_records": [
            {
                "id": 5,
                "appointment_id": 5,
                "appointment_date": "2026-06-15",
                "preliminary_diagnosis": "右肺结节待查"
            }
        ],
        "ai_diagnoses": [
            {
                "id": 3,
                "type": "image",
                "risk_level": "高风险",
                "created_at": "2026-07-28 14:30:00"
            }
        ],
        "created_at": "2026-07-29 10:30:00",
        "updated_at": "2026-07-29 10:30:00"
    },
    "success": true
}
```

---

## 4. `POST /doctor/appointments/{id}/call — 叫号`

> 状态流转：pending → called

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 预约 ID |

**请求 Body：** 无

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | number | 预约 ID |
| status | string | called |
| updated_at | string | 更新时间，格式 Y-m-d H:i:s |

**返回示例：**
```json
{
    "code": 0,
    "msg": "叫号成功",
    "data": {
        "id": 1,
        "status": "called",
        "updated_at": "2026-07-30 09:00:05"
    },
    "success": true
}
```

**错误码：**

| code | msg | 条件 |
|------|------|------|
| 30001 | 预约不存在或不属于当前医生 | id 无效 |
| 40002 | 当前状态不可操作 | 预约状态不是 pending |

---

## 5. `POST /doctor/appointments/{id}/start — 开始接诊`

> 状态流转：called → in_progress

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 预约 ID |

**请求 Body：** 无

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | number | 预约 ID |
| status | string | in_progress |
| updated_at | string | 更新时间，格式 Y-m-d H:i:s |

**返回示例：**
```json
{
    "code": 0,
    "msg": "开始接诊",
    "data": {
        "id": 1,
        "status": "in_progress",
        "updated_at": "2026-07-30 09:02:10"
    },
    "success": true
}
```

**错误码：**

| code | msg | 条件 |
|------|------|------|
| 30001 | 预约不存在或不属于当前医生 | id 无效 |
| 40002 | 当前状态不可操作 | 预约状态不是 called |

---

## 6. `POST /doctor/appointments/{id}/complete — 结束接诊`

> 状态流转：in_progress → completed
>
> ⚠️ 前置条件：该预约必须已创建病历（POST /doctor/medical-records）且已开具处方（POST /doctor/prescriptions）

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 预约 ID |

**请求 Body：** 无

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | number | 预约 ID |
| status | string | completed |
| updated_at | string | 更新时间，格式 Y-m-d H:i:s |

**返回示例：**
```json
{
    "code": 0,
    "msg": "结束接诊",
    "data": {
        "id": 1,
        "status": "completed",
        "updated_at": "2026-07-30 09:20:30"
    },
    "success": true
}
```

**错误码：**

| code | msg | 条件 |
|------|------|------|
| 30001 | 预约不存在或不属于当前医生 | id 无效 |
| 40002 | 当前状态不可操作 | 预约状态不是 in_progress |
| 40001 | 还未填写病历，无法结束接诊 | 未创建病历 |
| 40001 | 还未开具处方，无法结束接诊 | 未开具处方 |

---

## 7. `POST /doctor/appointments/{id}/reject — 拒绝预约`

> 状态流转：pending → cancelled

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 预约 ID |

**请求 Body：** 无

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | number | 预约 ID |
| status | string | cancelled |
| updated_at | string | 更新时间，格式 Y-m-d H:i:s |

**返回示例：**
```json
{
    "code": 0,
    "msg": "已拒绝该预约",
    "data": {
        "id": 1,
        "status": "cancelled",
        "updated_at": "2026-07-30 08:50:00"
    },
    "success": true
}
```

**错误码：**

| code | msg | 条件 |
|------|------|------|
| 30001 | 预约不存在或不属于当前医生 | id 无效 |
| 40002 | 当前状态不可操作 | 预约状态不是 pending |

---

## 8. `GET /doctor/schedules — 查看排班`

**请求参数：** 无

**响应 data（数组，7 天）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| [].day_of_week | number | 0=周日 1=周一 2=周二 3=周三 4=周四 5=周五 6=周六 |
| [].day_name | string | 中文星期名 |
| [].is_available | boolean | 是否出诊 |
| [].time_slots | array | 可预约时段列表 |
| [].max_patients | number | 当日最大接诊人数 |

**返回示例：**
```json
{
    "code": 0,
    "data": [
        {
            "day_of_week": 0,
            "day_name": "周日",
            "is_available": false,
            "time_slots": [],
            "max_patients": 0
        },
        {
            "day_of_week": 1,
            "day_name": "周一",
            "is_available": true,
            "time_slots": ["08:30","09:15","10:00","10:45","13:30","14:15","15:00","15:45"],
            "max_patients": 20
        }
    ],
    "success": true
}
```

> 未配置的天数使用默认值：周一至周五出诊，8 个标准时段，每日最大 20 人。

---

## 9. `PUT /doctor/schedules — 设置排班`

**请求 Body（JSON）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| day_of_week | number | 是 | 0=周日 ~ 6=周六 |
| is_available | boolean | 否 | 是否出诊，不填默认 true |
| time_slots | array | 否 | 可预约时段列表 |
| max_patients | number | 否 | 当日最大接诊人数，不填默认 20 |

**请求示例：**
```json
{
    "day_of_week": 1,
    "is_available": true,
    "time_slots": ["08:30","09:15","10:00","10:45","14:00","14:45"],
    "max_patients": 15
}
```

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| day_of_week | number | 0=周日 ~ 6=周六 |
| is_available | boolean | 是否出诊 |
| time_slots | array | 更新后的时段列表 |
| max_patients | number | 更新后的最大接诊人数 |
| updated_at | string | 更新时间，格式 Y-m-d H:i:s |

**返回示例：**
```json
{
    "code": 0,
    "msg": "排班已更新",
    "data": {
        "day_of_week": 1,
        "is_available": true,
        "time_slots": ["08:30","09:15","10:00","10:45","14:00","14:45"],
        "max_patients": 15,
        "updated_at": "2026-07-30 10:00:00"
    },
    "success": true
}
```

---

# 二、医生端 — AI 图文诊断

> **权限：** doctor 角色
>
> **Headers：** `Authorization: Bearer <token>`

---

## 10. `POST /doctor/ai-diagnosis — 提交 AI 图文诊断`

> Content-Type: multipart/form-data

**请求参数（form-data）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| patient_id | number | 是 | 患者 ID |
| appointment_id | number | 否 | 关联预约 ID |
| image | file | 是 | CT/X光等影像文件，支持 jpg/jpeg/png/dcm，最大 20MB |
| description | string | 是 | 病情描述文字，10~2000 字符 |

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | number | 诊断报告 ID |
| type | string | 固定为 "image" |
| patient_id | number | 患者 ID |
| patient_name | string | 患者姓名 |
| appointment_id | number | 关联预约 ID |
| imaging_features | string | AI 识别的影像学特征描述 |
| risk_assessment | string | 风险评估：高风险 / 中风险 / 低风险 |
| suspected_lesions | string | 疑似病变诊断意见 |
| treatment_recommendations | string | 治疗建议 |
| confidence | string | AI 诊断置信度，如 "92%" |
| image_url | string | 上传影像的访问路径 |
| created_at | string | 诊断时间，格式 Y-m-d H:i:s |

**返回示例：**
```json
{
    "code": 0,
    "msg": "AI图文诊断完成",
    "data": {
        "id": 15,
        "type": "image",
        "patient_id": 10,
        "patient_name": "张三",
        "appointment_id": 1,
        "imaging_features": "CT影像显示：右肺上叶后段见约2.5cm×1.8cm结节影，边界欠清，呈分叶状...",
        "risk_assessment": "高风险",
        "suspected_lesions": "右肺上叶周围型肺癌可能性大...",
        "treatment_recommendations": "1. CT引导下穿刺活检；2. PET-CT全身评估；3. 肿瘤标志物全套...",
        "confidence": "92%",
        "image_url": "/storage/ai-images/xxxxx.jpg",
        "created_at": "2026-07-30 10:15:00"
    },
    "success": true
}
```

---

## 11. `GET /doctor/ai-diagnosis — AI 图文诊断记录列表`

> 只返回当前登录医生的诊断记录。

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 10 |
| patient_name | string | 否 | 患者姓名模糊搜索 |
| date_from | string | 否 | 起始日期，格式 Y-m-d |
| date_to | string | 否 | 截止日期，格式 Y-m-d |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 诊断列表 |
| list[].id | number | 诊断报告 ID |
| list[].patient_name | string | 患者姓名 |
| list[].type | string | 固定为 "image" |
| list[].risk_assessment | string | 高风险 / 中风险 / 低风险 |
| list[].confidence | string | AI 置信度 |
| list[].created_at | string | 诊断时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

**返回示例：**
```json
{
    "code": 0,
    "data": {
        "list": [
            {
                "id": 15,
                "patient_name": "张三",
                "type": "image",
                "risk_assessment": "高风险",
                "confidence": "92%",
                "created_at": "2026-07-30 10:15:00"
            }
        ],
        "page": 1,
        "size": 10,
        "total": 3,
        "total_pages": 1
    },
    "success": true
}
```

---

## 12. `GET /doctor/ai-diagnosis/{id} — AI 图文诊断报告详情`

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 诊断报告 ID |

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | number | 诊断报告 ID |
| type | string | 固定为 "image" |
| patient_id | number | 患者 ID |
| patient_name | string | 患者姓名 |
| appointment_id | number | 关联预约 ID |
| description | string | 原始病情描述 |
| imaging_features | string | AI 识别的影像学特征 |
| risk_assessment | string | 高风险 / 中风险 / 低风险 |
| suspected_lesions | string | 疑似病变诊断 |
| treatment_recommendations | string | 治疗建议 |
| confidence | string | AI 置信度 |
| image_url | string | 上传影像路径 |
| created_at | string | 诊断时间，格式 Y-m-d H:i:s |

---

# 三、医生端 — 药品库存管理

> **权限：** doctor 角色
>
> **Headers：** `Authorization: Bearer <token>`

---

## 13. `GET /doctor/drugs — 药品库存列表`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 10 |
| keyword | string | 否 | 药品名称模糊搜索 |
| category | string | 否 | 药品分类筛选 |
| low_stock | boolean | 否 | true=仅看低库存（库存<10），false或不传=全部 |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 药品列表 |
| list[].id | number | 药品 ID |
| list[].name | string | 药品名称 |
| list[].category | string | 药品分类 |
| list[].specification | string | 规格 |
| list[].unit | string | 单位（盒/支/片等） |
| list[].stock_quantity | number | 当前库存数量 |
| list[].price | number | 单价（元） |
| list[].description | string | 描述说明 |
| list[].is_low_stock | boolean | 是否低库存（库存 < 10） |
| list[].created_at | string | 创建时间，格式 Y-m-d H:i:s |
| list[].updated_at | string | 更新时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

**返回示例：**
```json
{
    "code": 0,
    "data": {
        "list": [
            {
                "id": 1,
                "name": "阿莫西林胶囊",
                "category": "抗生素",
                "specification": "0.5g×24粒",
                "unit": "盒",
                "stock_quantity": 150,
                "price": 18.50,
                "description": "适用于敏感菌所致的呼吸道感染",
                "is_low_stock": false,
                "created_at": "2026-06-01 08:00:00",
                "updated_at": "2026-07-28 14:00:00"
            }
        ],
        "page": 1,
        "size": 10,
        "total": 156,
        "total_pages": 16
    },
    "success": true
}
```

---

## 14. `POST /doctor/drugs — 新增药品`

**请求 Body（JSON）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| name | string | 是 | 药品名称，最长 255 字符 |
| category | string | 是 | 药品分类，最长 100 字符 |
| specification | string | 是 | 规格，最长 100 字符 |
| unit | string | 是 | 单位，最长 20 字符 |
| stock_quantity | number | 是 | 初始库存数量，≥0 |
| price | number | 是 | 单价（元），>0.01 |
| description | string | 否 | 描述说明 |

**请求示例：**
```json
{
    "name": "盐酸氨溴索片",
    "category": "呼吸系统用药",
    "specification": "30mg×20片",
    "unit": "盒",
    "stock_quantity": 200,
    "price": 25.80,
    "description": "适用于急慢性支气管炎"
}
```

**响应 data（对象）：** 同接口 #13 中 list 的单条结构（id, name, category, specification, unit, stock_quantity, price, description, created_at）

---

## 15. `PUT /doctor/drugs/{id} — 编辑药品信息`

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 药品 ID |

**请求 Body（JSON，只传需要修改的字段）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| name | string | 否 | 药品名称 |
| category | string | 否 | 分类 |
| specification | string | 否 | 规格 |
| unit | string | 否 | 单位 |
| price | number | 否 | 单价，>0.01 |
| description | string | 否 | 描述 |

**请求示例：**
```json
{
    "price": 28.00,
    "description": "更新后的描述信息"
}
```

**响应 data（对象）：** 同接口 #13 中 list 的单条结构

---

## 16. `POST /doctor/drugs/{id}/stock-in — 药品入库`

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 药品 ID |

**请求 Body（JSON）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| quantity | number | 是 | 入库数量，≥1 |
| remark | string | 否 | 备注，最长 500 字符 |

**请求示例：**
```json
{
    "quantity": 50,
    "remark": "2026年7月常规补货"
}
```

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| drug_id | number | 药品 ID |
| drug_name | string | 药品名称 |
| before_quantity | number | 入库前库存 |
| after_quantity | number | 入库后库存 |
| stock_movement_id | number | 库存变动记录 ID |

**返回示例：**
```json
{
    "code": 0,
    "msg": "入库成功",
    "data": {
        "drug_id": 1,
        "drug_name": "阿莫西林胶囊",
        "before_quantity": 100,
        "after_quantity": 150,
        "stock_movement_id": 456
    },
    "success": true
}
```

---

## 17. `GET /doctor/stock-movements — 库存变动日志`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 10 |
| drug_id | number | 否 | 按药品 ID 筛选 |
| type | string | 否 | in = 入库 / out = 出库 |
| date_from | string | 否 | 起始日期，格式 Y-m-d |
| date_to | string | 否 | 截止日期，格式 Y-m-d |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 变动记录列表 |
| list[].id | number | 记录 ID |
| list[].drug_id | number | 药品 ID |
| list[].drug_name | string | 药品名称 |
| list[].type | string | in = 入库 / out = 出库 |
| list[].quantity | number | 变动数量 |
| list[].before_quantity | number | 变动前库存 |
| list[].after_quantity | number | 变动后库存 |
| list[].reference_type | string | 关联业务类型 |
| list[].reference_id | number | 关联业务 ID |
| list[].remark | string | 备注 |
| list[].operator_name | string | 操作人姓名 |
| list[].created_at | string | 操作时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

---

## 18. `GET /doctor/drugs/low-stock — 低库存药品列表`

> 只返回库存 < 10 的药品。

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 10 |

**响应 data：** 字段结构同接口 #13

---

## 19. `POST /doctor/drugs/batch-stock-in — 批量入库`

**请求 Body（JSON）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| items | array | 是 | 入库明细数组 |
| items[].drug_id | number | 是 | 药品 ID |
| items[].quantity | number | 是 | 入库数量 |
| items[].remark | string | 否 | 备注 |

**请求示例：**
```json
{
    "items": [
        {"drug_id": 1, "quantity": 30, "remark": "批量补货"},
        {"drug_id": 42, "quantity": 20, "remark": null}
    ]
}
```

**响应 data（数组）：** 数组每项同接口 #16 的 data 结构

---

# 四、通知消息

> **权限：** 任意登录用户（patient / doctor / admin 都可以）
>
> **Headers：** `Authorization: Bearer <token>`

---

## 20. `GET /notifications — 通知列表`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 20 |
| is_read | number | 否 | 0=只看未读 / 1=只看已读 / 不传=全部 |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 通知列表 |
| list[].id | number | 通知 ID |
| list[].type | string | 类型：appointment_call（叫号）/ stock_warning（库存）/ prescription_ready（处方）/ system（系统） |
| list[].title | string | 通知标题 |
| list[].content | string | 通知内容 |
| list[].is_read | boolean | 是否已读 |
| list[].reference_type | string | 关联业务类型（可跳转） |
| list[].reference_id | number | 关联业务 ID |
| list[].created_at | string | 通知时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

**返回示例：**
```json
{
    "code": 0,
    "data": {
        "list": [
            {
                "id": 101,
                "type": "appointment_call",
                "title": "叫号提醒",
                "content": "患者张明已被叫号，请准备接诊。",
                "is_read": false,
                "reference_type": "appointment",
                "reference_id": 10,
                "created_at": "2026-07-31 15:01:34"
            }
        ],
        "page": 1,
        "size": 20,
        "total": 4,
        "total_pages": 1
    },
    "success": true
}
```

---

## 21. `GET /notifications/unread-count — 未读数量`

**请求参数：** 无

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| count | number | 未读通知数 |

**返回示例：**
```json
{
    "code": 0,
    "data": {
        "count": 3
    },
    "success": true
}
```

---

## 22. `PUT /notifications/{id}/read — 标记已读`

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 通知 ID |

**请求 Body：** 无

**响应 data：** null

**返回示例：**
```json
{
    "code": 0,
    "msg": "已标记为已读",
    "data": null,
    "success": true
}
```

**错误码：**

| code | msg | 条件 |
|------|------|------|
| 30001 | 记录不存在 | 通知不属于当前用户 |

---

## 23. `PUT /notifications/read-all — 全部已读`

> 将当前用户所有未读通知标记为已读。

**请求参数：** 无

**请求 Body：** 无

**响应 data：** null

**返回示例：**
```json
{
    "code": 0,
    "msg": "全部已标记为已读",
    "data": null,
    "success": true
}
```

---

# 五、管理员端 — 用户管理

> **权限：** admin 角色
>
> **Headers：** `Authorization: Bearer <token>`

---

## 24. `GET /admin/users — 用户列表`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 10 |
| role | string | 否 | 角色筛选：patient / doctor / admin |
| status | string | 否 | 状态筛选：active（启用） / disabled（禁用） |
| keyword | string | 否 | 姓名或邮箱模糊搜索 |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 用户列表 |
| list[].id | number | 用户 ID |
| list[].name | string | 姓名 |
| list[].email | string | 邮箱 |
| list[].role | string | patient / doctor / admin |
| list[].phone | string | 手机号 |
| list[].status | string | active / disabled |
| list[].created_at | string | 创建时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

**返回示例：**
```json
{
    "code": 0,
    "data": {
        "list": [
            {
                "id": 5,
                "name": "李医生",
                "email": "doctor_li@hospital.com",
                "role": "doctor",
                "phone": "13800000005",
                "status": "active",
                "created_at": "2026-01-10 08:00:00"
            }
        ],
        "page": 1,
        "size": 10,
        "total": 25,
        "total_pages": 3
    },
    "success": true
}
```

---

## 25. `POST /admin/users — 创建用户`

> 只能创建 doctor 或 admin 角色，患者需通过 /auth/register 注册。

**请求 Body（JSON）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| name | string | 是 | 姓名，2~50 字符 |
| email | string | 是 | 邮箱（唯一） |
| password | string | 是 | 初始密码，6~100 字符 |
| role | string | 是 | doctor 或 admin |
| phone | string | 否 | 手机号 |

**请求示例：**
```json
{
    "name": "王医生",
    "email": "wang_doctor@hospital.com",
    "password": "123456",
    "role": "doctor",
    "phone": "13800000010"
}
```

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | number | 用户 ID |
| name | string | 姓名 |
| email | string | 邮箱 |
| role | string | doctor / admin |
| phone | string | 手机号 |
| status | string | active |
| created_at | string | 创建时间，格式 Y-m-d H:i:s |
| updated_at | string | 更新时间，格式 Y-m-d H:i:s |

**返回示例：**
```json
{
    "code": 0,
    "msg": "账号创建成功",
    "data": {
        "id": 26,
        "name": "王医生",
        "email": "wang_doctor@hospital.com",
        "role": "doctor",
        "phone": "13800000010",
        "status": "active",
        "created_at": "2026-07-30 14:00:00",
        "updated_at": "2026-07-30 14:00:00"
    },
    "success": true
}
```

---

## 26. `GET /admin/users/{id} — 用户详情`

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 用户 ID |

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| id | number | 用户 ID |
| name | string | 姓名 |
| email | string | 邮箱 |
| role | string | patient / doctor / admin |
| phone | string | 手机号 |
| status | string | active / disabled |
| title | string | 职称（仅 doctor 角色返回） |
| specialty | string | 专长（仅 doctor 角色返回） |
| department | string | 科室（仅 doctor 角色返回） |
| introduction | string | 个人简介（仅 doctor 角色返回） |
| experience_years | number | 从业年限（仅 doctor 角色返回） |
| created_at | string | 创建时间，格式 Y-m-d H:i:s |
| updated_at | string | 更新时间，格式 Y-m-d H:i:s |

**返回示例（医生角色）：**
```json
{
    "code": 0,
    "data": {
        "id": 5,
        "name": "李医生",
        "email": "doctor_li@hospital.com",
        "role": "doctor",
        "phone": "13800000005",
        "status": "active",
        "title": "主任医师",
        "specialty": "肿瘤内科",
        "department": "肿瘤科",
        "introduction": "从事肿瘤临床工作20年",
        "experience_years": 20,
        "created_at": "2026-01-10 08:00:00",
        "updated_at": "2026-07-28 16:00:00"
    },
    "success": true
}
```

---

## 27. `PUT /admin/users/{id} — 编辑用户信息`

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 用户 ID |

**请求 Body（JSON，只传要修改的字段）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| name | string | 否 | 姓名，2~50 字符 |
| email | string | 否 | 邮箱（唯一） |
| phone | string | 否 | 手机号 |
| role | string | 否 | patient / doctor / admin |

> ⚠️ 不允许修改自己的角色

**请求示例：**
```json
{
    "name": "李华",
    "phone": "13900000005"
}
```

**响应 data（对象）：** 同接口 #26 的数据结构

---

## 28. `PUT /admin/users/{id}/status — 启用/禁用用户`

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 用户 ID |

**请求 Body（JSON）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| status | string | 是 | active（启用）/ disabled（禁用） |

> ⚠️ 不允许操作自己的账号

**请求示例：**
```json
{
    "status": "disabled"
}
```

**响应 data（对象）：** 同接口 #26 的数据结构

---

## 29. `POST /admin/users/batch-import — 批量导入用户`

**请求 Body（JSON）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| users | array | 是 | 用户数据数组 |
| users[].name | string | 是 | 姓名 |
| users[].email | string | 是 | 邮箱 |
| users[].password | string | 是 | 密码 |
| users[].role | string | 是 | doctor 或 admin |
| users[].phone | string | 否 | 手机号 |

**请求示例：**
```json
{
    "users": [
        {"name":"张医生","email":"zhang_dr@hospital.com","password":"123456","role":"doctor"},
        {"name":"刘医生","email":"liu_dr@hospital.com","password":"123456","role":"doctor"}
    ]
}
```

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| count | number | 成功导入数量 |

**返回示例：**
```json
{
    "code": 0,
    "msg": "成功导入 2 个账号",
    "data": {
        "count": 2
    },
    "success": true
}
```

---

## 30. `GET /admin/users/{id}/operation-logs — 用户操作日志`

> 查看指定用户的所有操作记录。

**请求参数（path）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| id | number | 是 | 用户 ID |

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 20 |
| module | string | 否 | 模块：user / drug / appointment / ai_diagnosis / system |
| action | string | 否 | 操作：create / update / delete / login / logout / status_change |
| date_from | string | 否 | 起始日期，格式 Y-m-d |
| date_to | string | 否 | 截止日期，格式 Y-m-d |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 日志列表 |
| list[].id | number | 日志 ID |
| list[].user_id | number | 操作人 ID |
| list[].user_name | string | 操作人姓名 |
| list[].action | string | 操作类型 |
| list[].module | string | 操作模块 |
| list[].target_type | string | 操作对象类型 |
| list[].target_id | number | 操作对象 ID |
| list[].content | string | 操作内容描述 |
| list[].ip | string | 操作 IP 地址 |
| list[].created_at | string | 操作时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

---

# 六、管理员端 — 数据监控

> **权限：** admin 角色
>
> **Headers：** `Authorization: Bearer <token>`

---

## 31. `GET /admin/dashboard — 管理后台 Dashboard 统计`

**请求参数：** 无

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| total_patients | number | 患者总数 |
| total_doctors | number | 医生总数 |
| total_appointments | number | 累计预约数 |
| today_appointments | number | 今日预约数 |
| total_prescriptions | number | 累计处方数 |
| total_ai_diagnoses | number | 累计 AI 诊断次数 |
| low_stock_drugs | number | 库存预警药品数 |

**返回示例：**
```json
{
    "code": 0,
    "data": {
        "total_patients": 320,
        "total_doctors": 12,
        "total_appointments": 1580,
        "today_appointments": 45,
        "total_prescriptions": 1200,
        "total_ai_diagnoses": 680,
        "low_stock_drugs": 5
    },
    "success": true
}
```

---

## 32. `GET /admin/appointments — 全量预约数据`

> 管理员可查看所有医生的预约记录。

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 10 |
| doctor_id | number | 否 | 按医生 ID 筛选 |
| patient_name | string | 否 | 患者姓名模糊搜索 |
| status | string | 否 | pending / called / in_progress / completed / cancelled |
| date_from | string | 否 | 起始日期，格式 Y-m-d |
| date_to | string | 否 | 截止日期，格式 Y-m-d |
| sort | string | 否 | 排序，格式 `字段:方向`，如 `created_at:desc`；可选列：id / patient_id / doctor_id / appointment_date / status / created_at |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 预约列表 |
| list[].id | number | 预约 ID |
| list[].patient_id | number | 患者 ID |
| list[].patient_name | string | 患者姓名 |
| list[].doctor_id | number | 医生 ID |
| list[].doctor_name | string | 医生姓名 |
| list[].appointment_date | string | 预约日期，格式 Y-m-d |
| list[].status | string | pending / called / in_progress / completed / cancelled |
| list[].created_at | string | 创建时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

---

## 33. `GET /admin/medical-records — 全量病历数据`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 10 |
| doctor_id | number | 否 | 按医生 ID 筛选 |
| patient_name | string | 否 | 患者姓名模糊搜索 |
| date_from | string | 否 | 起始日期，格式 Y-m-d |
| date_to | string | 否 | 截止日期，格式 Y-m-d |
| sort | string | 否 | 排序，格式 `字段:方向`，可选列：id / patient_id / doctor_id / created_at |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 病历列表 |
| list[].id | number | 病历 ID |
| list[].patient_id | number | 患者 ID |
| list[].patient_name | string | 患者姓名 |
| list[].doctor_id | number | 医生 ID |
| list[].doctor_name | string | 医生姓名 |
| list[].appointment_date | string | 就诊日期，格式 Y-m-d |
| list[].preliminary_diagnosis | string | 初步诊断 |
| list[].created_at | string | 创建时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

---

## 34. `GET /admin/prescriptions — 全量处方数据`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 10 |
| doctor_id | number | 否 | 按医生 ID 筛选 |
| patient_name | string | 否 | 患者姓名模糊搜索 |
| status | string | 否 | 处方状态筛选 |
| date_from | string | 否 | 起始日期，格式 Y-m-d |
| date_to | string | 否 | 截止日期，格式 Y-m-d |
| sort | string | 否 | 排序，格式 `字段:方向`，可选列：id / patient_id / doctor_id / status / created_at |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 处方列表 |
| list[].id | number | 处方 ID |
| list[].patient_id | number | 患者 ID |
| list[].patient_name | string | 患者姓名 |
| list[].doctor_id | number | 医生 ID |
| list[].doctor_name | string | 医生姓名 |
| list[].status | string | 处方状态 |
| list[].items_count | number | 处方包含的药品数量 |
| list[].created_at | string | 开具时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

---

## 35. `GET /admin/ai-diagnoses — 全量 AI 诊断记录`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 10 |
| type | string | 否 | text = 文字诊断 / image = 图像诊断 |
| patient_name | string | 否 | 患者姓名模糊搜索 |
| date_from | string | 否 | 起始日期，格式 Y-m-d |
| date_to | string | 否 | 截止日期，格式 Y-m-d |
| sort | string | 否 | 排序，格式 `字段:方向`，可选列：id / patient_id / type / created_at |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 诊断列表 |
| list[].id | number | 诊断报告 ID |
| list[].patient_id | number | 患者 ID |
| list[].patient_name | string | 患者姓名 |
| list[].doctor_name | string | 医生姓名 |
| list[].type | string | text / image |
| list[].risk_level | string | 风险等级 |
| list[].created_at | string | 诊断时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

---

## 36. `GET /admin/drugs — 药品数据`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 10 |
| keyword | string | 否 | 药品名称模糊搜索 |
| category | string | 否 | 药品分类筛选 |
| low_stock | boolean | 否 | true=仅看低库存 |

**响应 data：** 字段结构同接口 #13

---

## 37. `GET /admin/stock-movements — 库存变动日志`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 10 |
| drug_id | number | 否 | 按药品 ID 筛选 |
| type | string | 否 | in = 入库 / out = 出库 |
| date_from | string | 否 | 起始日期，格式 Y-m-d |
| date_to | string | 否 | 截止日期，格式 Y-m-d |

**响应 data：** 字段结构同接口 #17

---

## 38. `GET /admin/statistics/doctor-workload — 医生工作量统计`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| date_from | string | 否 | 起始日期，格式 Y-m-d |
| date_to | string | 否 | 截止日期，格式 Y-m-d |

**响应 data（数组）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| [].doctor_id | number | 医生 ID |
| [].doctor_name | string | 医生姓名 |
| [].title | string | 职称 |
| [].total_appointments | number | 总预约数 |
| [].completed_appointments | number | 已完成数 |
| [].completion_rate | number | 完成率（%） |

**返回示例：**
```json
{
    "code": 0,
    "data": [
        {
            "doctor_id": 5,
            "doctor_name": "李医生",
            "title": "主任医师",
            "total_appointments": 85,
            "completed_appointments": 78,
            "completion_rate": 91.8
        }
    ],
    "success": true
}
```

---

## 39. `GET /admin/statistics/drug-consumption — 药品消耗统计`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| date_from | string | 否 | 起始日期，格式 Y-m-d |
| date_to | string | 否 | 截止日期，格式 Y-m-d |

**响应 data（数组）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| [].drug_id | number | 药品 ID |
| [].drug_name | string | 药品名称 |
| [].specification | string | 规格 |
| [].total_dispensed | number | 总消耗数量 |
| [].dispense_count | number | 开具次数 |

**返回示例：**
```json
{
    "code": 0,
    "data": [
        {
            "drug_id": 1,
            "drug_name": "阿莫西林胶囊",
            "specification": "0.5g×24粒",
            "total_dispensed": 350,
            "dispense_count": 45
        }
    ],
    "success": true
}
```

---

## 40. `GET /admin/statistics/monthly-trend — 月度趋势统计`

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| months | number | 否 | 统计月数，默认 6，上限 24 |

**响应 data（数组）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| [].month | string | 月份，格式 Y-m |
| [].appointments | number | 月预约数 |
| [].prescriptions | number | 月处方数 |
| [].ai_diagnoses | number | 月 AI 诊断数 |
| [].medical_records | number | 月病历数 |
| [].new_patients | number | 月新增患者数 |

**返回示例：**
```json
{
    "code": 0,
    "data": [
        {
            "month": "2026-02",
            "appointments": 245,
            "prescriptions": 198,
            "ai_diagnoses": 112,
            "medical_records": 230,
            "new_patients": 45
        }
    ],
    "success": true
}
```

---

## 41. `GET /admin/statistics/drug-overview — 药品库存概览`

**请求参数：** 无

**响应 data（对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| total_drugs | number | 药品种类总数 |
| total_stock_value | number | 库存总价值（元） |
| low_stock_count | number | 低库存种类数（库存<10） |
| out_of_stock_count | number | 零库存种类数 |
| stock_in_this_month | number | 本月入库总量 |
| stock_out_this_month | number | 本月出库总量 |

**返回示例：**
```json
{
    "code": 0,
    "data": {
        "total_drugs": 156,
        "total_stock_value": 285600.50,
        "low_stock_count": 5,
        "out_of_stock_count": 0,
        "stock_in_this_month": 850,
        "stock_out_this_month": 620
    },
    "success": true
}
```

---

# 七、管理员端 — 系统管理

> **权限：** admin 角色
>
> **Headers：** `Authorization: Bearer <token>`

---

## 42. `GET /admin/operation-logs — 操作日志列表`

> 全局操作日志，不限制用户。可按条件筛选。

**请求参数（query）：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| page | number | 否 | 页码，从 1 开始，默认 1 |
| size | number | 否 | 每页条数，默认 20 |
| user_id | number | 否 | 筛选指定用户 |
| module | string | 否 | user / drug / appointment / ai_diagnosis / system |
| action | string | 否 | create / update / delete / login / logout / status_change |
| date_from | string | 否 | 起始日期，格式 Y-m-d |
| date_to | string | 否 | 截止日期，格式 Y-m-d |

**响应 data（分页对象）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| list | array | 日志列表 |
| list[].id | number | 日志 ID |
| list[].user_id | number | 操作人 ID |
| list[].user_name | string | 操作人姓名 |
| list[].action | string | 操作类型 |
| list[].module | string | 操作模块 |
| list[].target_type | string | 操作对象类型 |
| list[].target_id | number | 操作对象 ID |
| list[].content | string | 操作内容描述 |
| list[].ip | string | 操作 IP 地址 |
| list[].created_at | string | 操作时间，格式 Y-m-d H:i:s |
| page | number | 当前页码 |
| size | number | 每页条数 |
| total | number | 总记录数 |
| total_pages | number | 总页数 |

**返回示例：**
```json
{
    "code": 0,
    "data": {
        "list": [
            {
                "id": 500,
                "user_id": 1,
                "user_name": "系统管理员",
                "action": "create",
                "module": "user",
                "target_type": "User",
                "target_id": 26,
                "content": "创建用户: 王医生",
                "ip": "192.168.1.100",
                "created_at": "2026-07-30 14:00:00"
            }
        ],
        "page": 1,
        "size": 20,
        "total": 350,
        "total_pages": 18
    },
    "success": true
}
```

---

## 43. `GET /admin/system-configs — 系统配置列表`

**请求参数：** 无

**响应 data（对象，按 group 分组）：**

| 字段 | 类型 | 说明 |
|------|------|------|
| drug | object | 药品相关配置 |
| drug.stock_low_threshold | string | 库存预警阈值 |
| appointment | object | 预约相关配置 |
| appointment.time_slots | string | 可选时段（逗号分隔） |
| appointment.max_daily | string | 每日最大接诊数 |
| ai | object | AI 相关配置 |
| ai.diagnosis_mode | string | mock / remote |
| system | object | 系统配置 |
| system.name | string | 系统名称 |

**返回示例：**
```json
{
    "code": 0,
    "data": {
        "drug": {
            "stock_low_threshold": "10"
        },
        "appointment": {
            "time_slots": "08:30,09:15,10:00,10:45,13:30,14:15,15:00,15:45",
            "max_daily": "20"
        },
        "ai": {
            "diagnosis_mode": "remote"
        },
        "system": {
            "name": "肿瘤科智能检测门诊系统"
        }
    },
    "success": true
}
```

---

## 44. `PUT /admin/system-configs — 更新系统配置`

**请求 Body（JSON）：**

| 字段 | 类型 | 必填 | 说明 |
|------|------|:--:|------|
| configs | array | 是 | 配置项数组 |
| configs[].key | string | 是 | 配置键名 |
| configs[].value | string | 是 | 配置值 |

**请求示例：**
```json
{
    "configs": [
        {"key": "app_name", "value": "肿瘤科智能门诊系统 V2"},
        {"key": "ai_diagnosis_mode", "value": "mock"}
    ]
}
```

**响应 data：** 更新后的完整配置，结构同接口 #43

**返回示例：**
```json
{
    "code": 0,
    "msg": "配置已更新",
    "data": {
        "drug": { "stock_low_threshold": "10" },
        "appointment": { "time_slots": "08:30,09:15,...", "max_daily": "20" },
        "ai": { "diagnosis_mode": "mock" },
        "system": { "name": "肿瘤科智能门诊系统 V2" }
    },
    "success": true
}
```

---

# 附录 A：预约状态流转图

```
pending ──叫号──▶ called ──开始接诊──▶ in_progress ──结束接诊──▶ completed
  │
  └──拒绝──▶ cancelled
```

# 附录 B：通用错误码

| code | HTTP 状态码 | 含义 |
|------|:--:|------|
| 0 | 200 | 成功 |
| 10001 | 422 | 参数错误 |
| 10002 | 422 | 必填参数缺失 |
| 10003 | 422 | 参数格式错误 |
| 10004 | 422 | 参数超出范围 |
| 20001 | 401 | 未登录 |
| 20005 | 403 | 无权限 |
| 30001 | 404 | 数据不存在 |
| 30003 | 400 | 数据重复 |
| 40001 | 400 | 业务处理失败 |
| 40002 | 400 | 当前状态不可操作 |
| 40004 | 400 | 库存不足 |
| 40009 | 400 | 重复提交 |
| 60001 | 500 | 数据库异常 |
| 90001 | 500 | 系统异常 |

---

> 📅 2026-07-31 | 👤 GYZ | 📁 `docs/conclusion/GYZ模块-前端对接接口文档.md`
