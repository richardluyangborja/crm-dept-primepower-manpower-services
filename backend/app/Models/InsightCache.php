<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cached AI/rule insight payloads (specs/15). Agents: write via Insights services only. */
class InsightCache extends Model
{
    protected $table = 'insights_cache';

    protected $fillable = ['client_id', 'opportunity_id', 'kind', 'payload', 'confidence', 'generated_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'generated_at' => 'datetime'];
    }
}
