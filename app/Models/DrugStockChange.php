<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugStockChange extends Model
{
    protected $fillable = ['drug_id', 'type', 'quantity', 'before_quantity', 'after_quantity', 'reason', 'related_id', 'related_type'];
    public function drug() { return $this->belongsTo(Drug::class); }
}
