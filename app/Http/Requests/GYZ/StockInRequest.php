<?php

namespace App\Http\Requests\GYZ;

use App\Http\Requests\BaseRequest;

class StockInRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:1',
            'remark' => 'nullable|string|max:500',
        ];
    }
}
