<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Server-side session tracking for 5-min idle timeout (specs/16). V1: written, not enforced. */
class UserSession extends Model
{
    protected $fillable = ['user_id', 'jti', 'ip', 'user_agent', 'last_activity_at', 'expired_at'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime', 'expired_at' => 'datetime'];
    }
}
