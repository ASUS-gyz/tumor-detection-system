<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrugStock extends Model
{
    protected $fillable = ['drug_id', 'quantity', 'min_stock'];
    public function drug() { return $this->belongsTo(Drug::class); }
}
