<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 药品表
 */
class Drug extends Model
{
    protected $fillable = [
        'name',
        'category',
        'specification',
        'unit',
        'stock_quantity',
        'price',
        'description',
    ];
class Drug extends Model
{
    protected $fillable = [
        'name', 'category', 'specification', 'unit',
        'stock_quantity', 'price', 'description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'stock_quantity' => 'integer',
        ];
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function prescriptionItems()
    {
        return $this->hasMany(PrescriptionItem::class);
    }
}
