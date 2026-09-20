<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Step 6 (specs/09): org settings — readable by all, writable by superadmin, cached 60s. */
class SettingController extends Controller
{
    use ApiResponse;

    public const EDITABLE = [
        'org_name', 'timezone', 'currency', 'date_format', 'language',
        'appearance_default', 'report_schedule', 'retention_days', 'industries',
        'lead_sources', 'pipeline_stages', 'lost_reasons',
    ];

    public function index()
    {
        $settings = Cache::remember('settings:all', 60, fn () => Setting::pluck('value', 'key')->all());

        return $this->ok($settings);
    }

    public function update(Request $request)
    {
        if ($request->user()->role !== 'superadmin') {
            return $this->fail('Only superadmin can change organization settings.', 403);
        }
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);
        $saved = [];
        foreach ($data['settings'] as $key => $value) {
            if (! in_array($key, self::EDITABLE, true)) continue;
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            $saved[] = $key;
        }
        Cache::forget('settings:all');

        return $this->ok(['saved' => $saved], 'Settings saved.');
    }
}
