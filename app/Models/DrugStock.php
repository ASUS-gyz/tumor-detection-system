<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 药品库存表
 *
 * 记录每种药品的当前库存和最低库存预警。
 */
class DrugStock extends Model
{
    protected $fillable = [
        'drug_id',
        'quantity',
        'min_stock',
    ];

    /** 关联药品 */
    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }
}
