<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasOpaqueId;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use Filterable, HasOpaqueId, HasAuditLog, SoftDeletes;

    public const STAGES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'contract', 'won', 'lost'];
    /** The happy-path flow. `won` follows `contract`; `lost` can close from anywhere. */
    public const FLOW = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'contract'];
    public const STAGE_PROBABILITY = [
        'new' => 10, 'contacted' => 20, 'qualified' => 40,
        'proposal' => 60, 'negotiation' => 80, 'contract' => 90, 'won' => 100, 'lost' => 0,
    ];

    /**
     * Flow stages bypassed by a move (for the history timeline).
     * Backward/reopen moves bypass nothing. A loss bypasses whatever
     * remained between its stage and the contract.
     *
     * @return list<string>
     */
    public static function skippedStages(string $from, string $to): array
    {
        $flow = self::FLOW;
        if ($to === 'lost') {
            $idx = array_search($from, $flow, true);
            if ($idx === false) {
                return [];
            }

            return array_values(array_slice($flow, $idx + 1));
        }
        $fromIdx = array_search($from, $flow, true);
        $toIdx = array_search($to, $flow, true);
        if ($fromIdx === false || $toIdx === false || $toIdx <= $fromIdx + 1) {
            return [];
        }

        return array_values(array_slice($flow, $fromIdx + 1, $toIdx - $fromIdx - 1));
    }

    protected $fillable = [
        'client_id', 'company_id', 'owner_id', 'title', 'stage', 'value_centavos',
        'headcount', 'rate_per_head_centavos', 'contract_months',
        'probability', 'expected_close_date', 'lost_reason', 'won_at', 'lost_at',
    ];

    protected function casts(): array
    {
        return [
            'value_centavos' => 'integer',
            'headcount' => 'integer',
            'rate_per_head_centavos' => 'integer',
            'contract_months' => 'integer',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    /** Monthly billing = heads × rate. Null until terms are recorded. */
    public function monthlyBilling(): ?int
    {
        if ($this->headcount === null || $this->rate_per_head_centavos === null) {
            return null;
        }

        return $this->headcount * $this->rate_per_head_centavos;
    }

    /** Contract total = monthly billing × months. Null until terms are complete. */
    public function contractTotal(): ?int
    {
        $monthly = $this->monthlyBilling();
        if ($monthly === null || $this->contract_months === null) {
            return null;
        }

        return $monthly * $this->contract_months;
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
