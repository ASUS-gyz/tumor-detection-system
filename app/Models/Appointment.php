<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = ['patient_id', 'doctor_id', 'appointment_date', 'appointment_time', 'status'];

    protected function casts(): array { return ['appointment_date' => 'date']; }

    public function patient() { return $this->belongsTo(User::class, 'patient_id'); }
    public function doctor() { return $this->belongsTo(User::class, 'doctor_id'); }
    public function medicalRecord() { return $this->hasOne(MedicalRecord::class); }
    public function prescription() { return $this->hasOne(Prescription::class); }
    public function prescriptions() { return $this->hasMany(Prescription::class); }
    public function aiDiagnoses() { return $this->hasMany(AiDiagnosis::class); }
    public function aiDiagnosis() { return $this->hasOne(AiDiagnosis::class)->where('type', 'text')->latest(); }

    public function isActive(): bool { return in_array($this->status, ['pending', 'called', 'in_progress']); }
    public function canCancel(): bool { return $this->status === 'pending'; }
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isCalled(): bool { return $this->status === 'called'; }
    public function isInProgress(): bool { return $this->status === 'in_progress'; }
}
