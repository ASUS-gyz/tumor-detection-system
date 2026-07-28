<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiDiagnosis extends Model
{
    protected $fillable = [
        'type', 'patient_id', 'doctor_id', 'appointment_id',
        'symptom_description', 'description',
        'analysis', 'risk_level', 'risk_warning', 'advice',
        'imaging_features', 'risk_assessment', 'suspected_lesions',
        'treatment_recommendations', 'confidence', 'image_url',
        'possible_conditions',
    ];

    protected function casts(): array
    {
        return [
            'possible_conditions' => 'array',
        ];
    }

    // === 关联 ===

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    // === 判断 ===

    public function isImageType(): bool
    {
        return $this->type === 'image';
    }

    public function isTextType(): bool
    {
        return $this->type === 'text';
    }
}
