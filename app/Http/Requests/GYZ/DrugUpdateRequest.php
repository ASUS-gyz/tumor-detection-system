<?php

namespace App\Http\Requests\GYZ;

use App\Http\Requests\BaseRequest;

class DrugUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'category' => 'string|max:100',
            'specification' => 'string|max:100',
            'unit' => 'string|max:20',
            'price' => 'numeric|min:0.01',
            'description' => 'nullable|string',
        ];
    }
}
