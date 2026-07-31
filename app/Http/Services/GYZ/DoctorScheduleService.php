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
        $schedule = DoctorSchedule::updateOrCreate(
            ['doctor_id' => $doctorId, 'day_of_week' => $dayOfWeek],
            [
                'is_available' => $data['is_available'] ?? true,
                'time_slots' => $data['time_slots'] ?? self::DEFAULT_SLOTS,
                'max_patients' => $data['max_patients'] ?? 20,
            ]
        );

        return [
            'day_of_week' => $schedule->day_of_week,
            'is_available' => $schedule->is_available,
            'time_slots' => $schedule->time_slots,
            'max_patients' => $schedule->max_patients,
            'updated_at' => $schedule->updated_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
        ];
    }
}
