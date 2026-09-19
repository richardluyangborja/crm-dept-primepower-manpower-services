<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use Filterable;

    public const TYPES = ['call', 'email', 'meeting', 'site_visit', 'note'];

    protected $fillable = [
        'owner_id', 'client_id', 'opportunity_id', 'type',
        'subject', 'body', 'outcome', 'occurred_at', 'attachments',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'attachments' => 'array'];
    }
}
