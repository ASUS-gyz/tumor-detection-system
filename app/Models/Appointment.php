<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'patient_id', 'doctor_id', 'appointment_date', 'appointment_time', 'status',
    ];

    // === 关联 ===

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function aiDiagnoses()
    {
        return $this->hasMany(AiDiagnosis::class);
    }

    // === 状态判断 ===

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCalled(): bool
    {
        return $this->status === 'called';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }
}
