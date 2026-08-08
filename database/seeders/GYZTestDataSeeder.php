<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\Drug;
use App\Models\DrugStock;
use App\Models\Notification;
use App\Models\OperationLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GYZTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== GYZ 测试数据填充 ===');

        // ── 1. 管理员 ──
        $admin = User::firstOrCreate(['email' => 'admin@oncology.com'], [
            'name' => '系统管理员', 'password' => Hash::make('admin123'),
            'role' => 'admin', 'status' => 'active',
        ]);
        $this->command->info("Admin: {$admin->email} / admin123");

        // ── 2. 医生 ──
        $doctor = User::firstOrCreate(['email' => 'doctor_li@hospital.com'], [
            'name' => '李医生', 'password' => Hash::make('123456'),
            'role' => 'doctor', 'status' => 'active', 'phone' => '13900001111',
            'title' => '主任医师', 'specialty' => '肿瘤内科', 'department' => '肿瘤科',
            'introduction' => '从事肿瘤临床工作20年', 'experience_years' => 20,
        ]);
        $this->command->info("Doctor: {$doctor->email} / 123456");

        // ── 3. 患者 ──
        $p1 = User::firstOrCreate(['email' => 'zhangsan@test.com'], [
            'name' => '张三', 'password' => Hash::make('123456'),
            'role' => 'patient', 'status' => 'active', 'phone' => '13800000001',
        ]);
        $p2 = User::firstOrCreate(['email' => 'wangfang@test.com'], [
            'name' => '王芳', 'password' => Hash::make('123456'),
            'role' => 'patient', 'status' => 'active', 'phone' => '13800000002',
        ]);
        $this->command->info("Patients: {$p1->name}, {$p2->name}");

        // ── 4. 药品 + 库存 ──
        $drugs = [
            ['name' => '吉非替尼片', 'category' => '靶向药物', 'specification' => '250mg×10片', 'unit' => '盒', 'stock_quantity' => 100, 'price' => 1580.00],
            ['name' => '奥希替尼片', 'category' => '靶向药物', 'specification' => '80mg×30片', 'unit' => '盒', 'stock_quantity' => 50, 'price' => 5580.00],
            ['name' => '盐酸氨溴索片', 'category' => '呼吸系统', 'specification' => '30mg×20片', 'unit' => '盒', 'stock_quantity' => 200, 'price' => 25.80],
            ['name' => '顺铂注射液', 'category' => '化疗药物', 'specification' => '10mg/支', 'unit' => '支', 'stock_quantity' => 5, 'price' => 85.00],
            ['name' => '盐酸曲马多缓释片', 'category' => '镇痛药', 'specification' => '100mg×10片', 'unit' => '盒', 'stock_quantity' => 80, 'price' => 45.50],
        ];
        foreach ($drugs as $d) {
            $drug = Drug::firstOrCreate(['name' => $d['name']], $d);
            // 同步 drug_stocks
            DrugStock::updateOrCreate(['drug_id' => $drug->id], ['quantity' => $d['stock_quantity']]);
        }
        $this->command->info('Drugs: ' . Drug::count() . ' 种（drug_stocks 已同步）');

        // ── 5. 排班 ──
        $slots = ['08:30', '09:15', '10:00', '10:45', '13:30', '14:15', '15:00', '15:45'];
        for ($d = 0; $d < 7; $d++) {
            $avail = ($d >= 1 && $d <= 5);
            DoctorSchedule::firstOrCreate(
                ['doctor_id' => $doctor->id, 'day_of_week' => $d],
                ['is_available' => $avail, 'time_slots' => $avail ? $slots : [], 'max_patients' => $avail ? 20 : 0]
            );
        }
        $this->command->info('Schedules: 7 天（周一~五出诊）');

        // ── 6. 预约 ──
        $apps = [
            ['patient_id' => $p1->id, 'doctor_id' => $doctor->id, 'appointment_date' => now()->toDateString(), 'appointment_time' => '08:30', 'status' => 'pending'],
            ['patient_id' => $p2->id, 'doctor_id' => $doctor->id, 'appointment_date' => now()->toDateString(), 'appointment_time' => '09:15', 'status' => 'pending'],
            ['patient_id' => $p1->id, 'doctor_id' => $doctor->id, 'appointment_date' => now()->toDateString(), 'appointment_time' => '10:00', 'status' => 'pending'],
        ];
        foreach ($apps as $a) {
            Appointment::firstOrCreate([
                'patient_id' => $a['patient_id'], 'doctor_id' => $a['doctor_id'],
                'appointment_date' => $a['appointment_date'], 'appointment_time' => $a['appointment_time'],
            ], $a);
        }
        $this->command->info('Appointments: 3 条（今天）');

        // ── 7. 通知 ──
        $notis = [
            ['user_id' => $doctor->id, 'type' => 'stock_warning', 'title' => '库存预警', 'content' => '顺铂注射液库存仅剩5支', 'is_read' => false, 'reference_type' => 'drug', 'reference_id' => 4],
            ['user_id' => $doctor->id, 'type' => 'system', 'title' => '系统通知', 'content' => '新功能：AI图文诊断已接入千问VL模型', 'is_read' => false, 'reference_type' => null, 'reference_id' => null],
        ];
        foreach ($notis as $n) {
            Notification::firstOrCreate(['user_id' => $n['user_id'], 'title' => $n['title']], array_merge($n, ['created_at' => now()]));
        }
        $this->command->info('Notifications: 2 条');

        // ── 8. 操作日志 ──
        $logs = [
            ['user_id' => $admin->id, 'user_name' => $admin->name, 'action' => 'create', 'module' => 'user', 'target_type' => 'User', 'content' => "创建医生: {$doctor->name}", 'ip' => '127.0.0.1'],
            ['user_id' => $admin->id, 'user_name' => $admin->name, 'action' => 'update', 'module' => 'system', 'target_type' => 'SystemConfig', 'content' => '配置 AI 诊断模式', 'ip' => '127.0.0.1'],
            ['user_id' => $doctor->id, 'user_name' => $doctor->name, 'action' => 'login', 'module' => 'system', 'target_type' => null, 'content' => '医生登录', 'ip' => '127.0.0.1'],
        ];
        foreach ($logs as $l) {
            OperationLog::create(array_merge($l, ['created_at' => now()->subMinutes(rand(5, 120))]));
        }
        $this->command->info('OperationLogs: 3 条');

        $this->command->info('=== GYZ 测试数据填充完成 ===');
    }
}
