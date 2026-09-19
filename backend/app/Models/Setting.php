<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Org key-value settings (specs/09). Cache 60s when reading. */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
