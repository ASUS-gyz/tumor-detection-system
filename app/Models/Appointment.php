<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 预约表
 *
 * 患者创建预约 → 医生叫号 → 接诊 → 完成 / 取消
 */
class Appointment extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
        ];
    }

    /** 患者 */
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /** 医生 */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /** 关联病历（1:1） */
    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class);
    }

    /** 关联处方（1:N，取第一张） */
    public function prescription()
    {
        return $this->hasOne(Prescription::class);
    }

    /** 关联处方列表 */
    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    /** 关联 AI 诊断 */
    public function aiDiagnoses()
    {
        return $this->hasMany(AIDiagnosis::class);
    }

    /** 关联 AI 诊断（取最近一条文字诊断） */
    public function aiDiagnosis()
    {
        return $this->hasOne(AIDiagnosis::class)->where('type', 'text')->latest();
    }

    // ─── 状态判断 ───────────────────────────────────────────

    /** 是否为进行中状态（可取消检查用） */
    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'called', 'in_progress']);
    }

    /** 是否可取消 */
    public function canCancel(): bool
    {
        return $this->status === 'pending';
    }
}
