<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    protected $fillable = ['name', 'category', 'specification', 'unit', 'stock_quantity', 'price', 'description'];
}
