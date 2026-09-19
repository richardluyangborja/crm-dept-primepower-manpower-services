<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** OTP codes — bcrypt-hashed, 5-min TTL, single-use (see specs/16). V1: rows written in mock mode only. */
class Otp extends Model
{
    protected $fillable = ['user_id', 'purpose', 'code_hash', 'expires_at', 'attempts', 'consumed_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
