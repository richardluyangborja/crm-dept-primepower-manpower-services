<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Company;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\Opportunity;

/**
 * Phase 1 company backfill (specs/03): one company per client (absorbing its
 * converted lead) + one per orphan lead; opportunities/activities/followups
 * link through their client. Idempotent — safe to re-run.
 */
class BackfillCompanies
{
    public static function run(): array
    {
        $made = 0;
        $linked = 0;

        $companyFor = function (string $name, array $attrs) use (&$made): Company {
            $hit = Company::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();
            if ($hit) {
                return $hit;
            }
            $made++;

            return Company::create(array_merge([
                'name' => trim($name),
            ], $attrs));
        };

        foreach (Client::all() as $client) {
            $company = $companyFor($client->name, [
                'owner_id' => $client->owner_id,
                'industry' => $client->industry,
                'address_city' => $client->address_city,
                'address_province' => $client->address_province,
                'contact_email' => $client->contact_email,
                'contact_phone' => $client->contact_phone,
                'source' => $client->source,
            ]);
            if ($client->company_id !== $company->id) {
                $client->update(['company_id' => $company->id]);
                $linked++;
            }
            if ($client->created_from_lead_id) {
                $lead = Lead::find($client->created_from_lead_id);
                if ($lead && $lead->company_id !== $company->id) {
                    $lead->update(['company_id' => $company->id]);
                    $linked++;
                }
            }
        }

        foreach (Lead::whereNull('company_id')->get() as $lead) {
            $company = $companyFor($lead->company_name ?: 'Unnamed company', [
                'owner_id' => $lead->owner_id,
                'contact_email' => $lead->contact_email,
                'contact_phone' => $lead->contact_phone,
                'source' => $lead->source,
            ]);
            $lead->update(['company_id' => $company->id]);
            $linked++;
        }

        foreach (Opportunity::whereNull('company_id')->get() as $opp) {
            $cid = $opp->client?->company_id;
            if ($cid) {
                $opp->update(['company_id' => $cid]);
                $linked++;
            }
        }

        foreach (Activity::whereNull('company_id')->whereNotNull('client_id')->get() as $act) {
            $cid = Client::whereKey($act->client_id)->value('company_id');
            if ($cid) {
                $act->update(['company_id' => $cid]);
                $linked++;
            }
        }

        foreach (Followup::whereNull('company_id')->whereNotNull('client_id')->get() as $fup) {
            $cid = Client::whereKey($fup->client_id)->value('company_id');
            if ($cid) {
                $fup->update(['company_id' => $cid]);
                $linked++;
            }
        }

        return ['companies_created' => $made, 'rows_linked' => $linked];
    }
}
