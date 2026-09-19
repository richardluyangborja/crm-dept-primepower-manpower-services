<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = ['client_id', 'full_name', 'position', 'email', 'phone', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
