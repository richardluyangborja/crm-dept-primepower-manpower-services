<?php

namespace App\Http\Controllers;

use App\Models\InsightFeedback;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Insight thumbs up/down — trains/evaluates v2 models (specs/15). */
class InsightFeedbackController extends Controller
{
    use ApiResponse;

    public function store(Request $request)
    {
        $data = $request->validate([
            'insight_key' => ['required', 'string', 'max:100'],
            'rating' => ['required', 'in:up,down'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $fb = InsightFeedback::create($data + ['user_id' => auth('api')->id()]);

        return $this->created(['id' => $fb->id], 'Thanks — feedback recorded.');
    }
}
