<?php

namespace App\Http\Requests\GYZ;

use App\Http\Requests\BaseRequest;

class DrugCreateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'specification' => 'required|string|max:100',
            'unit' => 'required|string|max:20',
            'stock_quantity' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
        ];
    }
}
