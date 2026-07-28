<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model
{
    protected $fillable = ['doctor_id', 'day_of_week', 'is_available', 'time_slots', 'max_patients'];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'time_slots' => 'array',
            'max_patients' => 'integer',
            'day_of_week' => 'integer',
        ];
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
