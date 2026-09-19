<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyTemplate extends Model
{
    protected $fillable = ['name', 'type', 'questions', 'is_active'];

    protected function casts(): array
    {
        return ['questions' => 'array', 'is_active' => 'boolean'];
    }
}
