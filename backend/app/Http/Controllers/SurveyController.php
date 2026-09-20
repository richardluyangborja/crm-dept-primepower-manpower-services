<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSurveyRequest;
use App\Http\Requests\StoreSurveyResponseRequest;
use App\Http\Resources\SurveyResource;
use App\Models\Survey;
use App\Services\SurveyAnalyticsService;
use App\Services\SurveyService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Step 5 (specs/06): inbox, send, public respond, analytics. */
class SurveyController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Survey::class);
        $q = Survey::visibleTo($request->user())->with(['template', 'client', 'responses'])
            ->filter($request, ['status', 'client_id', 'template_id']);
        if ($request->query('q')) {
            $term = $request->query('q');
            $q->where(function ($qq) use ($term) {
                $qq->whereHas('client', fn ($c) => $c->where('name', 'ilike', "%{$term}%"))
                    ->orWhere('token', 'ilike', "%{$term}%");
            });
        }

        return $this->paginated(SurveyResource::collection($q->latest()->paginate(min(100, (int) $request->query('per_page', 25)))));
    }

    public function store(StoreSurveyRequest $request, SurveyService $service)
    {
        $this->authorize('create', Survey::class);
        $survey = $service->sendSurvey($request->validated(), $request->user()->id);
        $survey->load(['template', 'client']);

        return $this->created(new SurveyResource($survey), 'Survey sent — share link copied.');
    }

    public function show(Survey $survey)
    {
        $this->authorize('view', $survey);
        $survey->load(['template', 'client', 'responses']);

        return $this->ok(new SurveyResource($survey));
    }

    /** Public: view survey by token (no auth) — throttled 30/min at route. */
    public function publicShow(string $token, SurveyService $service)
    {
        $service->expireOverdue();
        $survey = Survey::where('token', $token)->with(['template', 'client', 'responses'])->firstOrFail();
        $out = (new SurveyResource($survey))->toArray(request());
        // Don't leak internal ids beyond token
        return $this->ok([
            'survey' => ['token' => $survey->token, 'status' => $survey->status, 'due_at' => $survey->due_at, 'client_name' => $survey->client?->name, 'template' => $survey->template],
            'existing_response' => $out['response'] ?? null,
        ]);
    }

    public function respond(StoreSurveyResponseRequest $request, string $token, SurveyService $service)
    {
        $survey = Survey::where('token', $token)->firstOrFail();
        $response = $service->recordResponse($survey, $request->validated());

        return $this->created(['score' => $response->score, 'comment' => $response->comment], 'Salamat! Response recorded.');
    }

    public function updateResponse(StoreSurveyResponseRequest $request, string $token, SurveyService $service)
    {
        $survey = Survey::where('token', $token)->firstOrFail();
        $response = $service->updateResponse($survey, $request->validated());

        return $this->ok(['score' => $response->score, 'comment' => $response->comment], 'Response updated.');
    }

    public function analytics(Request $request, SurveyAnalyticsService $svc)
    {
        $this->authorize('viewAny', Survey::class);
        $teamId = $request->query('team_id') ? (int) $request->query('team_id') : null;
        $data = $svc->forScope($teamId, $request->query('from'), $request->query('to'));

        return $this->ok($data);
    }
}
