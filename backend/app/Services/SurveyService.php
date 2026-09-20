<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Survey;
use App\Models\SurveyTemplate;
use Illuminate\Support\Str;

/** Survey lifecycle (specs/06): token issuance + due handling + comms + follow-up ties. */
class SurveyService
{
    public function sendSurvey(array $data, int $actorId): Survey
    {
        $survey = Survey::create([
            'template_id' => $data['template_id'],
            'client_id' => $data['client_id'],
            'sent_by' => $actorId,
            'channel' => $data['channel'] ?? 'link',
            'token' => Str::random(32),
            'due_at' => $data['due_at'] ?? now()->addDays(14),
            'status' => 'sent',
        ]);

        // Log as communication for timeline visibility (specs/07 ties).
        Activity::create([
            'owner_id' => $actorId, 'client_id' => $data['client_id'],
            'type' => 'email', 'subject' => 'Survey sent: '.SurveyTemplate::find($data['template_id'])?->name,
            'body' => 'Share link: /s/'.$survey->token.' ('.($data['channel'] ?? 'link').' mock — no real email sent.)',
            'outcome' => 'sent',
        ]);

        return $survey;
    }

    public function recordResponse(Survey $survey, array $validated): \App\Models\SurveyResponse
    {
        if ($survey->status === 'expired') abort(410, 'This survey has expired.');
        if ($survey->responses()->exists()) abort(409, 'This survey has already been answered. Edit window is 24 hours after first response.');

        $response = $survey->responses()->create([
            'score' => $validated['score'],
            'answers' => $validated['answers'] ?? null,
            'comment' => $validated['comment'] ?? null,
            'responded_at' => now(),
        ]);
        $survey->update(['status' => 'responded']);

        return $response;
    }

    public function updateResponse(Survey $survey, array $validated): \App\Models\SurveyResponse
    {
        $existing = $survey->responses()->latest()->firstOrFail();
        if ($existing->responded_at && $existing->responded_at->lt(now()->subDay())) abort(410, 'Edit window has closed (24 hours).');
        $existing->update([
            'score' => $validated['score'],
            'answers' => $validated['answers'] ?? $existing->answers,
            'comment' => $validated['comment'] ?? $existing->comment,
        ]);
        return $existing->refresh();
    }

    /**
     * Mark overdue sent surveys as expired. Each newly-expired survey spawns
     * one collection-style follow-up for the sender (specs/06 → 08 hook).
     * Idempotent: only the sent→expired transition creates the reminder.
     */
    public function expireOverdue(): int
    {
        $count = 0;
        Survey::where('status', 'sent')->where('due_at', '<', now())
            ->chunkById(100, function ($surveys) use (&$count) {
                foreach ($surveys as $survey) {
                    $survey->update(['status' => 'expired']);
                    \App\Models\Followup::create([
                        'owner_id' => $survey->sent_by,
                        'client_id' => $survey->client_id,
                        'title' => "Survey expired unanswered — check in with {$survey->client?->name}",
                        'due_at' => now()->addDays(2)->toIso8601String(),
                        'priority' => 'medium',
                    ]);
                    $count++;
                }
            });

        return $count;
    }
}
