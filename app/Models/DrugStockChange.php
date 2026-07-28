<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 库存变动记录表
 *
 * 不可变日志，记录每次入库/出库操作。
 */
class DrugStockChange extends Model
{
    protected $fillable = [
        'drug_id',
        'type',
        'quantity',
        'before_quantity',
        'after_quantity',
        'reason',
        'related_id',
        'related_type',
    ];

    /** 关联药品 */
    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }
}
