<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 处方明细表
 *
 * 处方中的每一条药品记录。
 */
class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id',
        'drug_id',
        'quantity',
        'dosage',
        'instructions',
    ];

    /** 关联处方 */
    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    /** 关联药品 */
    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }
}
