<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Thumbs up/down per insight — trains/evaluates v2 models (specs/15). */
class InsightFeedback extends Model
{
    protected $table = 'insight_feedback';

    protected $fillable = ['user_id', 'insight_key', 'rating', 'note'];
}
