<?php

namespace App\Models;

use App\Traits\HasOpaqueId;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasOpaqueId;
    protected $fillable = ['user_id', 'type', 'title', 'body', 'link', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
