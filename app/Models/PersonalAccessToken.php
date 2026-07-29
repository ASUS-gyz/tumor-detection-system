<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalAccessToken extends Model
{
    protected $fillable = ['name', 'token', 'abilities', 'expires_at'];
    protected $hidden = ['token'];

    protected function casts(): array { return ['abilities' => 'json', 'last_used_at' => 'datetime', 'expires_at' => 'datetime']; }

    public function tokenable() { return $this->morphTo(); }

    public static function findToken(string $token): ?static { return static::where('token', hash('sha256', $token))->first(); }
}
