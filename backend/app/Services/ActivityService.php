<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Followup;

/** Communication logging (specs/07): touch client, store files, hook follow-ups. */
class ActivityService
{
    protected const ALLOWED_MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    /**
     * @return array{activity: Activity, followup: ?Followup}
     */
    public function log(array $input, int $actorId): Activity|array
    {
        $files = [];
        foreach ((array) ($input['attachments'] ?? []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store('comms', 'local');
            $files[] = [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'path' => $path,
            ];
        }

        $activity = Activity::create([
            'owner_id' => $input['owner_id'],
            'client_id' => $input['client_id'],
            'opportunity_id' => $input['opportunity_id'] ?? null,
            'type' => $input['type'],
            'subject' => $input['subject'] ?? null,
            'body' => $input['body'] ?? null,
            'outcome' => $input['outcome'] ?? null,
            'duration_minutes' => $input['duration_minutes'] ?? null,
            'occurred_at' => $input['occurred_at'] ?? now(),
            'attachments' => $files ?: null,
        ]);
        $activity->client()->update(['last_contacted_at' => $activity->occurred_at]);
        $activity->audit('logged', $actorId, ['type' => $activity->type, 'files' => count($files)]);

        $followup = null;
        if (! empty($input['create_followup'])) {
            $followup = Followup::create([
                'owner_id' => $input['owner_id'],
                'client_id' => $input['client_id'],
                'opportunity_id' => $input['opportunity_id'] ?? null,
                'title' => $input['followup_title'],
                'due_at' => $input['followup_due_at'],
                'priority' => 'medium',
            ]);
            $followup->audit('created', $actorId, ['via' => 'activity', 'activity_id' => $activity->id]);
        }

        return ['activity' => $activity, 'followup' => $followup];
    }

    /** Canned templates (PH-flavored). v1 reads a versioned JSON fixture — Step 6 can move to DB master data. */
    public function templates(): array
    {
        $file = database_path('fixtures/message_templates.json');

        return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    }
}
