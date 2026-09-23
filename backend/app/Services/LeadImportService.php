<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/** Phase 2C: CSV lead import with per-row validation report (specs/04 gaps). */
class LeadImportService
{
    public const HEADERS = ['company_name', 'contact_name', 'contact_email', 'contact_phone', 'source', 'notes'];
    public const MAX_ROWS = 500;

    public function template(): string
    {
        $lines = [implode(',', self::HEADERS)];
        $lines[] = 'BGC Tech Solutions Inc.,Paolo Gutierrez,hrd@bgctech.ph,+639171111111,referral,Needs 40 service crew';

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array{imported: int, failed: array<int, array{row: int, errors: array<string>}>}
     */
    public function import(UploadedFile $file, int $ownerId, LeadService $leads): array
    {
        $rows = array_map('str_getcsv', explode("\n", trim($file->getContent())));
        if ($rows === []) {
            return ['imported' => 0, 'failed' => [['row' => 0, 'errors' => ['Empty file.']]]];
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows));
        if ($header !== self::HEADERS) {
            return ['imported' => 0, 'failed' => [['row' => 0, 'errors' => ['Header must be: '.implode(',', self::HEADERS).'. Download the template.']]]];
        }

        $imported = 0;
        $failed = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $i => $cols) {
            if (count(array_filter($cols, fn ($c) => trim((string) $c) !== '')) === 0) continue; // skip blanks
            $data = array_combine(self::HEADERS, array_pad($cols, count(self::HEADERS), ''));
            $data = array_map(fn ($v) => trim((string) $v) === '' ? null : trim((string) $v), $data);
            $v = Validator::make($data, [
                'company_name' => ['required', 'string', 'max:255'],
                'contact_name' => ['required', 'string', 'max:255'],
                'contact_email' => ['nullable', 'email', 'max:255'],
                'contact_phone' => ['nullable', 'regex:/^\+63\d{10}$/'],
                'source' => ['nullable', 'in:'.implode(',', Lead::SOURCES)],
                'notes' => ['nullable', 'string'],
            ]);
            if ($v->fails()) {
                $failed[] = ['row' => $i + 2, 'errors' => $v->errors()->all()];
                continue;
            }
            $lead = Lead::create($v->validated() + ['owner_id' => $ownerId]);
            $company = \App\Models\Company::whereRaw('LOWER(name) = ?', [mb_strtolower($lead->company_name)])->first()
                ?? \App\Models\Company::create([
                    'owner_id' => $ownerId, 'name' => $lead->company_name,
                    'contact_email' => $lead->contact_email, 'contact_phone' => $lead->contact_phone,
                    'source' => $lead->source,
                ]);
            $open = $company->leads()->whereNotIn('status', ['unqualified', 'converted'])->where('id', '!=', $lead->id)->first();
            if ($open) {
                $lead->delete();
                $failed[] = ['row' => $i + 2, 'errors' => ["{$company->name} already has an open lead."]];
                continue;
            }
            $lead->update(['company_id' => $company->id]);
            $leads->score($lead);
            $lead->audit('imported', $ownerId, ['company_id' => $company->id]);
            $imported++;
        }

        return ['imported' => $imported, 'failed' => $failed];
    }
}
