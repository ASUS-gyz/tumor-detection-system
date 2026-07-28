<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_name', 'action', 'module', 'target_type',
        'target_id', 'content', 'ip', 'created_at',
    ];
}
