<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    // 变更日志无 updated_at
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected $fillable = [
        'drug_id', 'type', 'quantity', 'before_quantity', 'after_quantity',
        'reference_type', 'reference_id', 'remark', 'operator_id', 'created_at',
    ];

    // === 关联 ===

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
