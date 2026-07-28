<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 病历表
 *
 * 一个预约对应一份病历，由医生在接诊中填写。
 */
class MedicalRecord extends Model
{
    protected $fillable = [
        'appointment_id',
        'patient_id',
        'doctor_id',
        'symptoms',
        'imaging_findings',
        'preliminary_diagnosis',
        'treatment_plan',
    ];

    /** 关联预约 */
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
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
}
