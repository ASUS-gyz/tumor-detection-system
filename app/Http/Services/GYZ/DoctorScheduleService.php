<?php

namespace App\Http\Services\GYZ;

use App\Models\DoctorSchedule;

class DoctorScheduleService
{
    /** 默认排班（周一至周五 8个时段） */
    private const DEFAULT_SLOTS = ['08:30', '09:15', '10:00', '10:45', '13:30', '14:15', '15:00', '15:45'];

    /**
     * 查看医生排班
     */
    public function get(int $doctorId): array
    {
        $schedules = DoctorSchedule::where('doctor_id', $doctorId)
            ->orderBy('day_of_week')
            ->get();

        // 返回完整7天，没有配置的用默认值
        $result = [];
        for ($d = 0; $d < 7; $d++) {
            $row = $schedules->firstWhere('day_of_week', $d);
            $result[] = [
                'day_of_week' => $d,
                'day_name' => ['周日', '周一', '周二', '周三', '周四', '周五', '周六'][$d],
                'is_available' => $row ? $row->is_available : ($d > 0 && $d < 6),
                'time_slots' => $row?->time_slots ?? self::DEFAULT_SLOTS,
                'max_patients' => $row?->max_patients ?? 20,
            ];
        }

        return $result;
    }

    /**
     * 设置某天排班
     */
    public function set(int $doctorId, int $dayOfWeek, array $data): array
    {
        $newRow = function () use ($doctorId, $dayOfWeek): DoctorSchedule {
            $s = DoctorSchedule::firstOrNew(['doctor_id' => $doctorId, 'day_of_week' => $dayOfWeek]);
            if (! $s->exists) {
                // 仅新建行使用缺省值；已有行未提交字段保持原值，避免部分更新把停诊/自定义时段静默重置
                $s->is_available = true;
                $s->time_slots = self::DEFAULT_SLOTS;
                $s->max_patients = 20;
            }
            return $s;
        };

        $schedule = $newRow();
        $this->applyData($schedule, $data);

        try {
            $schedule->save();
        } catch (\Illuminate\Database\QueryException $e) {
            // 并发首次设置同一星期几：撞 (doctor_id, day_of_week) 唯一键 → 取既有行按更新路径重放
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            $schedule = $newRow();
            $this->applyData($schedule, $data);
            $schedule->save();
        }

        return [
            'day_of_week' => $schedule->day_of_week,
            'is_available' => $schedule->is_available,
            'time_slots' => $schedule->time_slots,
            'max_patients' => $schedule->max_patients,
            'updated_at' => $schedule->updated_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 仅应用请求中显式提交的字段（缺省值只用于新建行）
     */
    private function applyData(DoctorSchedule $s, array $data): void
    {
        $defaults = ['is_available' => true, 'time_slots' => self::DEFAULT_SLOTS, 'max_patients' => 20];
        foreach ($defaults as $field => $default) {
            if (array_key_exists($field, $data)) {
                $s->{$field} = $data[$field] ?? $default;
            }
        }
    }
}
