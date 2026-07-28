<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id', 'drug_id', 'quantity', 'dosage', 'instructions',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }
}
