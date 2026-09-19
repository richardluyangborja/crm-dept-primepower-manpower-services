<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Generated management report packs (specs/15). */
class Report extends Model
{
    protected $fillable = ['type', 'period_from', 'period_to', 'team_id', 'payload', 'file_path', 'generated_by'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'payload' => 'array'];
    }
}
