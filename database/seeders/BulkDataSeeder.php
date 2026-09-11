<?php

namespace Database\Seeders;

use App\Models\AiDiagnosis;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\Drug;
use App\Models\DrugStock;
use App\Models\DrugStockChange;
use App\Models\MedicalRecord;
use App\Models\Notification;
use App\Models\OperationLog;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\StockMovement;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 批量测试数据 Seeder：把每张业务表补足到至少 30 条，保证接口联调有充足数据。
 * 幂等：可重复执行，按各表现有数量补差；唯一字段（邮箱/药品名/配置键）自动跳过已存在项。
 */
class BulkDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /** 每张表的目标最少行数 */
    private const TARGET = 30;

    private array $timeSlots = ['08:30', '09:15', '10:00', '10:45', '13:30', '14:15', '15:00', '15:45'];

    public function run(): void
    {
        $password = Hash::make('123456');

        $this->seedUsers($password);
        $this->seedDoctorSchedules();
        $this->seedDrugsAndStocks();
        $this->seedAppointments();
        $this->seedMedicalRecords();
        $this->seedPrescriptions();
        $this->seedAiDiagnoses();
        $this->seedDrugStockChanges();
        $this->seedStockMovements();
        $this->seedNotifications();
        $this->seedOperationLogs();
        $this->seedSystemConfigs();

        $this->command->info('=== BulkDataSeeder 完成：各业务表已补足至 ' . self::TARGET . ' 条以上 ===');
    }

    // ── 用户：补足到 30（6 医生 + 18 患者 + 2 管理员）─────────────────────────

    private function seedUsers(string $password): void
    {
        $before = User::count();
        $need = max(0, self::TARGET - $before);
        $doctorNames = ['陈建国', '刘晓梅', '赵国庆', '孙丽华', '周志强', '吴雅琴'];
        $doctorTitles = ['主任医师', '副主任医师', '主治医师', '主任医师', '副主任医师', '主治医师'];
        $departments = ['肿瘤内科', '肿瘤外科', '放疗科', '肿瘤内科', '影像科', '肿瘤外科'];
        $patientNames = ['李伟', '刘杰', '陈静', '杨洋', '黄敏', '周涛', '吴刚', '徐娜', '朱磊', '马丽',
            '胡军', '郭婷', '林峰', '何雪', '高翔', '罗兰', '郑凯', '梁梅'];
        $adminNames = ['数据管理员A', '数据管理员B'];

        $created = ['doctor' => [], 'patient' => [], 'admin' => []];

        // 医生（带头像外字段，供医生列表/排班使用）
        for ($i = 0; $i < $need; $i++) {
            if ($i < count($doctorNames)) {
                $role = 'doctor';
                $name = $doctorNames[$i];
                $email = 'bulk_doctor' . ($i + 1) . '@hospital.com';
            } elseif ($i < count($doctorNames) + count($patientNames)) {
                $role = 'patient';
                $name = $patientNames[$i - count($doctorNames)];
                $email = 'bulk_patient' . ($i - count($doctorNames) + 1) . '@test.com';
            } else {
                $role = 'admin';
                $idx = $i - count($doctorNames) - count($patientNames);
                $name = $adminNames[$idx] ?? ('数据管理员' . $idx);
                $email = 'bulk_admin' . ($idx + 1) . '@oncology.com';
            }

            $attrs = [
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => $password,
                'role' => $role,
                'phone' => '138' . str_pad((string)rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'status' => 'active',
            ];
            if ($role === 'doctor') {
                $di = $i;
                $attrs += [
                    'title' => $doctorTitles[$di],
                    'specialty' => '肿瘤综合诊疗',
                    'department' => $departments[$di],
                    'introduction' => $name . '，从事肿瘤临床工作多年，擅长肿瘤综合诊疗。',
                    'experience_years' => rand(8, 30),
                ];
            }
            $user = User::firstOrCreate(['email' => $email], $attrs);
            $created[$role][] = $user->id;
        }

        $this->command->info("users: {$before} -> " . User::count());
    }

    // ── 医生排班：为每个缺排班的医生补全 7 天（doctor_id+day_of_week 唯一）──────

    private function seedDoctorSchedules(): void
    {
        $before = DoctorSchedule::count();
        $doctors = User::where('role', 'doctor')->where('status', 'active')->pluck('id');
        foreach ($doctors as $doctorId) {
            $existing = DoctorSchedule::where('doctor_id', $doctorId)->pluck('day_of_week')->all();
            foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
                if (!in_array($day, $existing)) {
                    DoctorSchedule::create([
                        'doctor_id' => $doctorId,
                        'day_of_week' => $day,
                        'is_available' => $day !== 0, // 周日不接诊
                        'time_slots' => $this->timeSlots, // 模型 array cast 自动编码，勿预 json_encode（否则双重编码）
                        'max_patients' => 20,
                    ]);
                }
            }
        }
        $this->command->info("doctor_schedules: {$before} -> " . DoctorSchedule::count());
    }

    // ── 药品 + 库存：药品种类补足到 30，库存与药品 1:1 同步 ─────────────────────

    private function seedDrugsAndStocks(): void
    {
        $before = Drug::count();
        $newDrugs = [
            ['紫杉醇注射液', '化疗药', '100ml:30mg/支', '支', 289.00, '抗微管类抗肿瘤药，用于卵巢癌、乳腺癌等。'],
            ['顺铂注射液', '化疗药', '20ml:20mg/支', '支', 45.50, '铂类配合化疗药物，用于多种实体瘤。'],
            ['卡铂注射液', '化疗药', '10ml:100mg/支', '支', 128.00, '第二代铂类抗肿瘤药，肾毒性较低。'],
            ['环磷酰胺片', '化疗药', '50mg×100片/瓶', '瓶', 36.80, '烷化剂类抗肿瘤药。'],
            ['多柔比星注射液', '化疗药', '10mg/支', '支', 156.00, '蒽环类抗肿瘤抗生素。'],
            ['吉西他滨注射液', '化疗药', '200mg/支', '支', 268.00, '嘧啶类抗代谢物，用于胰腺癌、肺癌等。'],
            ['培美曲塞二钠注射液', '化疗药', '100mg/支', '支', 1280.00, '抗叶酸代谢抗肿瘤药，用于非小细胞肺癌。'],
            ['伊立替康注射液', '化疗药', '2ml:40mg/支', '支', 335.00, '拓扑异构酶抑制剂，用于结直肠癌。'],
            ['奥沙利铂注射液', '化疗药', '50mg/支', '支', 428.00, '第三代铂类，用于结直肠癌。'],
            ['替吉奥胶囊', '化疗药', '20mg×42粒/盒', '盒', 218.00, '口服氟尿嘧啶类抗肿瘤药。'],
            ['卡培他滨片', '化疗药', '0.5g×12片/盒', '盒', 198.00, '口服氟尿嘧啶类前体药物。'],
            ['曲妥珠单抗注射液', '靶向药', '440mg/瓶', '瓶', 7600.00, '抗HER2单克隆抗体，用于HER2阳性乳腺癌。'],
            ['贝伐珠单抗注射液', '靶向药', '100mg:4ml/瓶', '瓶', 1998.00, '抗VEGF人源化单克隆抗体。'],
            ['利妥昔单抗注射液', '靶向药', '100mg/瓶', '瓶', 2350.00, '抗CD20单克隆抗体，用于B细胞淋巴瘤。'],
            ['帕妥珠单抗注射液', '靶向药', '420mg/瓶', '瓶', 8800.00, 'HER2二聚化抑制剂。'],
            ['尼妥珠单抗注射液', '靶向药', '50mg/瓶', '瓶', 1580.00, '抗EGFR人源化单克隆抗体。'],
            ['伊马替尼片', '靶向药', '0.1g×60片/盒', '盒', 980.00, '酪氨酸激酶抑制剂，用于慢粒、GIST。'],
            ['吉非替尼片', '靶向药', '0.25g×10片/盒', '盒', 460.00, 'EGFR-TKI，用于EGFR突变非小细胞肺癌。'],
            ['厄洛替尼片', '靶向药', '150mg×7片/盒', '盒', 518.00, 'EGFR-TKI口服靶向药。'],
            ['来曲唑片', '内分泌药', '2.5mg×10片/盒', '盒', 68.00, '芳香化酶抑制剂，用于激素受体阳性乳腺癌。'],
            ['他莫昔芬片', '内分泌药', '10mg×60片/盒', '盒', 32.50, '雌激素受体调节剂。'],
            ['阿那曲唑片', '内分泌药', '1mg×14片/盒', '盒', 88.00, '选择性芳香化酶抑制剂。'],
            ['依西美坦片', '内分泌药', '25mg×30片/盒', '盒', 96.00, '甾体类芳香化酶灭活剂。'],
            ['戈舍瑞林缓释植入剂', '内分泌药', '3.6mg/支', '支', 1680.00, '促黄体生成素释放激素类似物。'],
            ['唑来膦酸注射液', '辅助药', '4mg/100ml/瓶', '瓶', 520.00, '双膦酸盐类，用于肿瘤骨转移。'],
            ['重组人粒细胞刺激因子注射液', '辅助药', '150μg/支', '支', 168.00, '升白细胞辅助用药。'],
            ['盐酸帕洛诺司琼注射液', '辅助药', '0.25mg/支', '支', 89.00, '止吐辅助用药。'],
            ['注射用磷酸氟达拉滨', '化疗药', '50mg/支', '支', 386.00, '抗代谢类，用于慢性淋巴细胞白血病。'],
            ['地舒单抗注射液', '靶向药', '120mg/瓶', '瓶', 3980.00, 'RANKL抑制剂，用于骨巨细胞瘤。'],
            ['盐酸羟考酮缓释片', '止痛药', '5mg×10片/盒', '盒', 76.00, '阿片类镇痛药，用于癌痛管理。'],
        ];

        foreach ($newDrugs as [$name, $category, $spec, $unit, $price, $desc]) {
            $drug = Drug::firstOrCreate(
                ['name' => $name],
                [
                    'category' => $category,
                    'specification' => $spec,
                    'unit' => $unit,
                    'stock_quantity' => rand(20, 400),
                    'price' => $price,
                    'description' => $desc,
                ]
            );
            // 库存双轨制：每瓶药品同步一条 drug_stocks（quantity 与 drugs.stock_quantity 一致）
            DrugStock::firstOrCreate(
                ['drug_id' => $drug->id],
                ['quantity' => $drug->stock_quantity, 'min_stock' => 10]
            );
        }
        $this->command->info("drugs: {$before} -> " . Drug::count() . '（drug_stocks 同步 -> ' . DrugStock::count() . '）');
    }

    // ── 预约：补足到 35（≥30 条 completed 供病历/处方使用）─────────────────────

    private function seedAppointments(): void
    {
        $before = Appointment::count();
        $patients = User::where('role', 'patient')->where('status', 'active')->pluck('id')->all();
        $doctors = User::where('role', 'doctor')->where('status', 'active')->pluck('id')->all();

        // 已完成预约需要补足到 30（medical_records.appointment_id 唯一、与处方一对一）
        $completed = Appointment::where('status', 'completed')->count();
        $needCompleted = max(0, self::TARGET - $completed);
        $needOther = max(0, 35 - Appointment::count() - $needCompleted);

        $statusPlan = array_merge(
            array_fill(0, $needCompleted, 'completed'),
            ['pending', 'cancelled', 'called', 'in_progress', 'pending']
        );
        $statusPlan = array_slice($statusPlan, 0, $needCompleted + $needOther);

        foreach ($statusPlan as $idx => $status) {
            if ($status === 'completed') {
                $date = now()->subDays(rand(1, 25))->toDateString();
            } elseif ($status === 'pending') {
                $date = now()->addDays(rand(0, 3))->toDateString();
            } else {
                $date = now()->subDays(rand(1, 10))->toDateString();
            }
            Appointment::create([
                'patient_id' => $patients[array_rand($patients)],
                'doctor_id' => $doctors[array_rand($doctors)],
                'appointment_date' => $date,
                'appointment_time' => $this->timeSlots[$idx % count($this->timeSlots)],
                'status' => $status,
            ]);
        }
        $this->command->info("appointments: {$before} -> " . Appointment::count()
            . '（其中 completed ' . Appointment::where('status', 'completed')->count() . '）');
    }

    // ── 病历：为每个无病历的 completed 预约建档（appointment_id 唯一）────────────

    private function seedMedicalRecords(): void
    {
        $before = MedicalRecord::count();
        $symptoms = ['持续性咳嗽伴胸痛2周', '右上腹隐痛伴食欲减退', '发现颈部无痛性肿块1月', '头痛伴视物模糊1周',
            '便血伴大便习惯改变1月', '乳腺触及无痛性肿块', '腰部酸胀伴血尿', '吞咽梗阻感进行性加重',
            '阴道不规则出血2月', '骨痛夜间加重3周'];
        $findings = ['CT示占位性病变，边界欠清，约3.2cm×2.8cm', 'MRI增强扫描示病灶明显强化',
            '超声示低回声结节，形态不规则', 'PET-CT示代谢增高灶，SUVmax 6.8', 'X线示局部骨质破坏'];
        $diagnoses = ['肺恶性肿瘤（腺癌）', '肝细胞癌', '淋巴瘤待病理分型', '乳腺癌（浸润性导管癌）',
            '结直肠癌', '骨转移瘤（原发灶待查）', '食管癌', '肾细胞癌'];
        $plans = ['完善相关检查后行根治性手术，术后辅助化疗', '行同步放化疗，方案：紫杉醇+顺铂',
            '行靶向药物治疗，2周期后评估疗效', '行根治性放疗，DT 60Gy/30f', '行新辅助化疗2周期后评估手术指征',
            '以姑息止痛及营养支持治疗为主，定期复查'];

        $appointments = Appointment::where('status', 'completed')
            ->whereNotIn('id', MedicalRecord::pluck('appointment_id'))
            ->with(['patient', 'doctor'])
            ->get();

        foreach ($appointments as $appt) {
            MedicalRecord::create([
                'appointment_id' => $appt->id,
                'patient_id' => $appt->patient_id,
                'doctor_id' => $appt->doctor_id,
                'symptoms' => $symptoms[array_rand($symptoms)],
                'imaging_findings' => $findings[array_rand($findings)],
                'preliminary_diagnosis' => $diagnoses[array_rand($diagnoses)],
                'treatment_plan' => $plans[array_rand($plans)],
            ]);
        }
        $this->command->info("medical_records: {$before} -> " . MedicalRecord::count());
    }

    // ── 处方 + 明细：为每个无处方 completed 预约开方，明细补足到 ≥30 ─────────────

    private function seedPrescriptions(): void
    {
        $before = Prescription::count();
        $itemsBefore = PrescriptionItem::count();
        $dosages = ['每日3次，每次1片', '每日2次，每次1支', '每日1次，每次1瓶', '每12小时1次，每次2片'];
        $instructions = ['饭后半小时服用，避免与牛奶同服', '输液前后注意监测肝肾功能',
            '出现骨髓抑制及时就诊复查血常规', '用药期间避免妊娠，注意避孕'];

        $appointments = Appointment::where('status', 'completed')
            ->whereNotIn('id', Prescription::pluck('appointment_id'))
            ->get();
        $drugs = Drug::pluck('id')->all();
        $itemIdx = 0;

        foreach ($appointments as $appt) {
            $rx = Prescription::create([
                'appointment_id' => $appt->id,
                'patient_id' => $appt->patient_id,
                'doctor_id' => $appt->doctor_id,
                'status' => $itemIdx % 3 === 0 ? 'pending' : 'dispensed',
            ]);
            // 每张处方 1~2 个药品明细
            $n = $itemIdx % 2 + 1;
            for ($k = 0; $k < $n; $k++) {
                PrescriptionItem::create([
                    'prescription_id' => $rx->id,
                    'drug_id' => $drugs[($itemIdx * 2 + $k) % count($drugs)],
                    'quantity' => rand(1, 5),
                    'dosage' => $dosages[($itemIdx + $k) % count($dosages)],
                    'instructions' => $instructions[($itemIdx + $k) % count($instructions)],
                ]);
            }
            $itemIdx++;
        }

        // 明细不足 30 时再补（挂到最新处方上）
        while (PrescriptionItem::count() < self::TARGET && $rx = Prescription::orderByDesc('id')->first()) {
            PrescriptionItem::create([
                'prescription_id' => $rx->id,
                'drug_id' => $drugs[array_rand($drugs)],
                'quantity' => rand(1, 5),
                'dosage' => $dosages[array_rand($dosages)],
                'instructions' => $instructions[array_rand($instructions)],
            ]);
        }
        $this->command->info("prescriptions: {$before} -> " . Prescription::count()
            . "，prescription_items: {$itemsBefore} -> " . PrescriptionItem::count());
    }

    // ── AI 诊断：文字/图文各半，补足到 30 ──────────────────────────────────────

    private function seedAiDiagnoses(): void
    {
        $before = AiDiagnosis::count();
        $need = max(0, self::TARGET - $before);
        $patients = User::where('role', 'patient')->pluck('id')->all();
        $doctors = User::where('role', 'doctor')->pluck('id')->all();
        $risks = ['低风险', '中风险', '高风险'];

        for ($i = 0; $i < $need; $i++) {
            if ($i % 2 === 0) {
                AiDiagnosis::create([
                    'type' => 'text',
                    'patient_id' => $patients[array_rand($patients)],
                    'doctor_id' => null,
                    'appointment_id' => null,
                    'symptom_description' => '近两周出现持续性症状，伴乏力、食欲下降，体重无明显变化。',
                    'analysis' => '根据症状描述，需警惕消化系统或呼吸系统占位性病变，建议完善影像学及肿瘤标志物检查。',
                    'risk_level' => $risks[$i % 3],
                    'risk_warning' => $i % 3 === 2 ? '症状持续加重时请立即就医，勿延误。' : '建议两周内门诊复查。',
                    'advice' => '建议尽快至肿瘤科门诊就诊，完善增强CT与实验室检查。',
                    'possible_conditions' => ['慢性炎症待排', '早期占位性病变待排', '功能性疾病'], // 模型 array cast 自动编码，勿预 json_encode
                ]);
            } else {
                AiDiagnosis::create([
                    'type' => 'image',
                    'patient_id' => $patients[array_rand($patients)],
                    'doctor_id' => $doctors[array_rand($doctors)],
                    'appointment_id' => null,
                    'description' => '胸部CT平扫+增强影像，患者主诉咳嗽伴胸闷两周。',
                    'imaging_features' => '右肺上叶见结节状高密度影，边缘可见分叶及毛刺，增强后不均匀强化。',
                    'risk_assessment' => $risks[$i % 3],
                    'suspected_lesions' => '右肺上叶占位，考虑周围型肺癌可能性大，建议穿刺活检明确病理。',
                    'treatment_recommendations' => '建议尽快完善支气管镜或CT引导下穿刺活检，明确病理后制定治疗方案。',
                    'confidence' => (85 + $i % 10) . '%',
                    'image_url' => null,
                ]);
            }
        }
        $this->command->info("ai_diagnoses: {$before} -> " . AiDiagnosis::count());
    }

    // ── 库存变动记录（drug_stock_changes）：补足到 30 ──────────────────────────

    private function seedDrugStockChanges(): void
    {
        $before = DrugStockChange::count();
        $need = max(0, self::TARGET - $before);
        $drugs = Drug::pluck('id')->all();
        $reasonsIn = ['采购入库', '科室领用归还', '盘点补录'];
        $reasonsOut = ['处方发药', '科室领用', '效期报损'];

        for ($i = 0; $i < $need; $i++) {
            $type = $i % 2 === 0 ? 'in' : 'out';
            $qty = rand(5, 50);
            $beforeQty = rand(10, 300);
            DrugStockChange::create([
                'drug_id' => $drugs[array_rand($drugs)],
                'type' => $type,
                'quantity' => $qty,
                'before_quantity' => $beforeQty,
                'after_quantity' => $type === 'in' ? $beforeQty + $qty : max(0, $beforeQty - $qty),
                'reason' => $type === 'in' ? $reasonsIn[$i % count($reasonsIn)] : $reasonsOut[$i % count($reasonsOut)],
                'related_id' => null,
                'related_type' => null,
            ]);
        }
        $this->command->info("drug_stock_changes: {$before} -> " . DrugStockChange::count());
    }

    // ── 出入库流水（stock_movements）：补足到 30 ───────────────────────────────

    private function seedStockMovements(): void
    {
        $before = StockMovement::count();
        $need = max(0, self::TARGET - $before);
        $drugs = Drug::pluck('id')->all();
        $operators = User::whereIn('role', ['doctor', 'admin'])->pluck('id')->all();

        for ($i = 0; $i < $need; $i++) {
            $type = $i % 2 === 0 ? 'in' : 'out';
            $qty = rand(5, 50);
            $beforeQty = rand(10, 300);
            StockMovement::create([
                'drug_id' => $drugs[array_rand($drugs)],
                'type' => $type,
                'quantity' => $qty,
                'before_quantity' => $beforeQty,
                'after_quantity' => $type === 'in' ? $beforeQty + $qty : max(0, $beforeQty - $qty),
                'reference_type' => $type === 'in' ? 'manual_stock_in' : 'prescription_dispense',
                'reference_id' => $type === 'out' ? Prescription::inRandomOrder()->value('id') : null,
                'remark' => $type === 'in' ? '常规采购入库' : '处方发药出库',
                'operator_id' => $operators[array_rand($operators)],
                'created_at' => now()->subDays(rand(0, 20)),
            ]);
        }
        $this->command->info("stock_movements: {$before} -> " . StockMovement::count());
    }

    // ── 通知：补足到 30 ────────────────────────────────────────────────────────

    private function seedNotifications(): void
    {
        $before = Notification::count();
        $need = max(0, self::TARGET - $before);
        $users = User::pluck('id')->all();
        $tpl = [
            ['appointment_call', '叫号提醒', '您预约的医生已就绪，请前往诊室就诊。', 'appointment'],
            ['stock_warning', '库存预警', '紫杉醇注射液库存低于预警阈值，请及时补货。', 'drug'],
            ['prescription_ready', '处方就绪', '您的处方已开具完成，请前往药房取药。', 'prescription'],
            ['system', '系统通知', '系统将于本周六 22:00 例行维护，届时请提前保存数据。', 'system'],
        ];

        for ($i = 0; $i < $need; $i++) {
            [$type, $title, $content, $refType] = $tpl[$i % count($tpl)];
            Notification::create([
                'user_id' => $users[array_rand($users)],
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'is_read' => $i % 3 === 0,
                'reference_type' => $refType,
                'reference_id' => rand(1, 20),
                'created_at' => now()->subMinutes(rand(1, 4320)),
            ]);
        }
        $this->command->info("notifications: {$before} -> " . Notification::count());
    }

    // ── 操作日志：补足到 30 ────────────────────────────────────────────────────

    private function seedOperationLogs(): void
    {
        $before = OperationLog::count();
        $need = max(0, self::TARGET - $before);
        $users = User::select('id', 'name')->get()->all();
        $actions = ['create', 'update', 'delete', 'login', 'logout', 'status_change'];
        $modules = ['appointment', 'user', 'drug', 'ai_diagnosis', 'medical_record', 'prescription', 'system'];

        for ($i = 0; $i < $need; $i++) {
            $u = $users[array_rand($users)];
            OperationLog::create([
                'user_id' => $u->id,
                'user_name' => $u->name,
                'action' => $actions[$i % count($actions)],
                'module' => $modules[$i % count($modules)],
                'target_type' => $modules[$i % count($modules)],
                'target_id' => rand(1, 30),
                'content' => '测试数据：用户 ' . $u->name . ' 在 ' . $modules[$i % count($modules)] . ' 模块执行了 ' . $actions[$i % count($actions)] . ' 操作。',
                'ip' => '192.168.1.' . rand(2, 254),
                'created_at' => now()->subMinutes(rand(1, 10080)),
            ]);
        }
        $this->command->info("operation_logs: {$before} -> " . OperationLog::count());
    }

    // ── 系统配置：key 唯一，补足到 30 ──────────────────────────────────────────

    private function seedSystemConfigs(): void
    {
        $before = SystemConfig::count();
        $configs = [
            ['ai.risk_threshold_high', '0.85', 'AI高风险判定阈值', 'ai'],
            ['ai.risk_threshold_mid', '0.60', 'AI中风险判定阈值', 'ai'],
            ['ai.model_version', 'v2.1.0', 'AI诊断模型版本', 'ai'],
            ['ai.image_max_size_mb', '10', '影像上传最大体积（MB）', 'ai'],
            ['ai.image_formats', 'jpg,png,dcm', '支持的影像格式', 'ai'],
            ['ai.response_timeout', '30', 'AI接口超时时间（秒）', 'ai'],
            ['ai.daily_quota_per_patient', '5', '每患者每日AI诊断次数上限', 'ai'],
            ['appointment.cancel_deadline_minutes', '30', '就诊前可取消的最低时限（分钟）', 'appointment'],
            ['appointment.max_per_patient_daily', '3', '每患者每日最大预约数', 'appointment'],
            ['appointment.advance_days', '14', '可提前预约的天数', 'appointment'],
            ['appointment.remind_before_minutes', '60', '就诊前提醒时间（分钟）', 'appointment'],
            ['appointment.auto_call_interval', '5', '叫号间隔时间（分钟）', 'appointment'],
            ['appointment.time_slot_minutes', '45', '单个预约时段时长（分钟）', 'appointment'],
            ['drug.expire_warning_days', '90', '药品效期预警天数', 'drug'],
            ['drug.stock_warning_notify', 'true', '库存预警是否推送通知', 'drug'],
            ['drug.batch_stock_in_max', '50', '批量入库单次最大品类数', 'drug'],
            ['drug.dispense_negative_allowed', 'false', '是否允许库存负数出库', 'drug'],
            ['system.maintenance_mode', 'false', '系统维护模式开关', 'system'],
            ['system.version', '1.2.0', '系统当前版本号', 'system'],
            ['system.contact_phone', '400-800-9999', '客服联系电话', 'system'],
            ['system.work_hours', '08:00-17:30', '门诊工作时间', 'system'],
            ['system.session_lifetime_minutes', '120', '登录会话有效期（分钟）', 'system'],
            ['system.password_min_length', '6', '密码最小长度', 'system'],
            ['user.avatar_max_size_mb', '2', '头像上传最大体积（MB）', 'user'],
            ['user.default_status', 'active', '新注册用户默认状态', 'user'],
            ['log.retention_days', '180', '操作日志保留天数', 'system'],
            ['notification.retention_days', '90', '通知保留天数', 'system'],
            ['statistics.cache_minutes', '10', '统计数据缓存时长（分钟）', 'system'],
            ['export.pdf_watermark', '肿瘤科智能检测门诊系统', '导出PDF水印文字', 'system'],
            ['export.records_per_page', '15', '列表分页每页条数', 'system'],
        ];

        $added = 0;
        foreach ($configs as [$key, $value, $desc, $group]) {
            if (SystemConfig::where('key', $key)->exists()) {
                continue;
            }
            if (SystemConfig::count() >= self::TARGET) {
                break;
            }
            SystemConfig::create(['key' => $key, 'value' => $value, 'description' => $desc, 'group' => $group]);
            $added++;
        }
        $this->command->info("system_configs: {$before} -> " . SystemConfig::count() . "（新增 {$added}）");
    }
}
