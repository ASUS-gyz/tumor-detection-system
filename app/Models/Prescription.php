<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 处方表
 *
 * 一次就诊可开具多张处方，患者确认后取药。
 */
class Prescription extends Model
{
    protected $fillable = [
        'appointment_id',
        'patient_id',
        'doctor_id',
        'status',
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

    /** 处方明细 */
    public function items()
    {
        return $this->hasMany(PrescriptionItem::class);
    }
}
