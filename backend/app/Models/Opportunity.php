<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use Filterable, HasAuditLog, SoftDeletes;

    public const STAGES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];
    public const STAGE_PROBABILITY = [
        'new' => 10, 'contacted' => 20, 'qualified' => 40,
        'proposal' => 60, 'negotiation' => 80, 'won' => 100, 'lost' => 0,
    ];

    protected $fillable = [
        'client_id', 'owner_id', 'title', 'stage', 'value_centavos',
        'probability', 'expected_close_date', 'lost_reason', 'won_at', 'lost_at',
    ];

    protected function casts(): array
    {
        return [
            'value_centavos' => 'integer',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
