<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    protected $fillable = ['survey_id', 'score', 'answers', 'comment', 'responded_at'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'responded_at' => 'datetime'];
    }

    public function survey(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }
}
