<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI 诊断报告表
 *
 * 统一存储患者端文字诊断和医生端图文诊断。
 */
class AIDiagnosis extends Model
{
    protected $table = 'ai_diagnoses';

    protected $fillable = [
        'type',
        'patient_id',
        'doctor_id',
        'appointment_id',
        'symptom_description',
        'analysis',
        'risk_level',
        'risk_warning',
        'advice',
        'possible_conditions',
        'description',
        'imaging_features',
        'risk_assessment',
        'suspected_lesions',
        'treatment_recommendations',
        'confidence',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'possible_conditions' => 'json',
        ];
    }

    /** 患者 */
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /** 医生（图文诊断时） */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /** 关联预约 */
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
