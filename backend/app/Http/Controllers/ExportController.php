<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;

/** Step 6 (specs/09): CSV export per entity (superadmin). No Faker, plain Eloquent. */
class ExportController extends Controller
{
    use ApiResponse;

    protected const ENTITIES = [
        'users' => [\App\Models\User::class, ['id', 'name', 'email', 'role', 'team_id', 'is_active', 'last_login_at']],
        'clients' => [\App\Models\Client::class, ['id', 'name', 'industry', 'address_city', 'address_province', 'status', 'owner_id']],
        'leads' => [\App\Models\Lead::class, ['id', 'company_name', 'contact_name', 'contact_email', 'status', 'score', 'owner_id']],
        'opportunities' => [\App\Models\Opportunity::class, ['id', 'client_id', 'title', 'stage', 'value_centavos', 'probability', 'owner_id']],
        'activities' => [\App\Models\Activity::class, ['id', 'client_id', 'type', 'subject', 'outcome', 'occurred_at', 'owner_id']],
        'surveys' => [\App\Models\Survey::class, ['id', 'client_id', 'template_id', 'status', 'channel', 'due_at']],
        'followups' => [\App\Models\Followup::class, ['id', 'client_id', 'title', 'due_at', 'priority', 'status', 'owner_id']],
    ];

    public function csv(string $entity)
    {
        if (auth('api')->user()->role !== 'superadmin') {
            return $this->fail('Only superadmin can export data.', 403);
        }
        if (! isset(self::ENTITIES[$entity])) {
            return $this->fail('Unknown entity. Allowed: '.implode(', ', array_keys(self::ENTITIES)).'.', 404);
        }
        [$model, $columns] = self::ENTITIES[$entity];
        $filename = "{$entity}-".now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($model, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            $model::query()->orderBy('id')->chunk(500, function ($rows) use ($out, $columns) {
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn ($c) => $this->cell($row->{$c}), $columns));
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function cell(mixed $v): string
    {
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d H:i:s');
        if (is_bool($v)) return $v ? '1' : '0';
        if (is_array($v)) return json_encode($v);
        return (string) ($v ?? '');
    }
}
