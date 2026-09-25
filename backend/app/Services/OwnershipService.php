<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ownership governance (specs/02): move open records between owners in one
 * audited transaction. Closed history is never touched.
 */
class OwnershipService
{
    /**
     * Move one company's open world. Returns counts + whether a client rode along.
     */
    public static function moveCompany(Company $company, User $to, int $actorId, string $via, ?string $reason = null): array
    {
        return DB::transaction(function () use ($company, $to, $actorId, $via, $reason) {
            $meta = ['from_owner_id' => $company->owner_id, 'to_owner_id' => $to->id, 'reason' => $reason];
            $moved = ['leads' => 0, 'opportunities' => 0, 'followups' => 0, 'clients' => 0];
            $company->update(['owner_id' => $to->id]);
            $meta['company'] = true;
            foreach ($company->leads()->whereNotIn('status', ['unqualified', 'converted'])->get() as $lead) {
                $lead->update(['owner_id' => $to->id]);
                $moved['leads']++;
            }
            foreach ($company->opportunities()->whereNotIn('stage', ['won', 'lost'])->get() as $opp) {
                $opp->update(['owner_id' => $to->id]);
                $moved['opportunities']++;
            }
            foreach ($company->followups()->whereNotIn('status', ['done'])->get() as $fup) {
                $fup->update(['owner_id' => $to->id]);
                $moved['followups']++;
            }
            foreach ($company->clients()->get() as $client) {
                $client->update(['owner_id' => $to->id]);
                $moved['clients']++;
            }
            $meta['moved'] = $moved;
            $company->audit('transferred', $actorId, $meta + ['via' => $via]);

            return $moved;
        });
    }

    /** Count a user's open (movable) records, grouped for handover UX. */
    public static function openCounts(User $user): array
    {
        return [
            'companies' => Company::where('owner_id', $user->id)->count(),
            'leads' => Lead::where('owner_id', $user->id)->whereNotIn('status', ['unqualified', 'converted'])->count(),
            'opportunities' => Opportunity::where('owner_id', $user->id)->whereNotIn('stage', ['won', 'lost'])->count(),
            'followups' => Followup::where('owner_id', $user->id)->whereNotIn('status', ['done'])->count(),
            'clients' => Client::where('owner_id', $user->id)->count(),
        ];
    }

    public static function hasOpen(User $user): bool
    {
        $c = self::openCounts($user);

        return ($c['companies'] + $c['leads'] + $c['opportunities'] + $c['followups']) > 0;
    }

    /**
     * Reassign everything open from one user to a successor (deactivation handover).
     * Company-rooted rows move via moveCompany; orphans move by owner column.
     */
    public static function reassignFrom(User $from, User $to, int $actorId): array
    {
        $totals = ['companies' => 0, 'leads' => 0, 'opportunities' => 0, 'followups' => 0, 'clients' => 0];
        foreach (Company::where('owner_id', $from->id)->get() as $company) {
            $moved = self::moveCompany($company->refresh(), $to, $actorId, 'deactivation_handover');
            foreach ($moved as $k => $v) {
                $totals[$k] += $v;
            }
            $totals['companies']++;
        }
        $totals['leads'] += Lead::where('owner_id', $from->id)->whereNull('company_id')
            ->whereNotIn('status', ['unqualified', 'converted'])->update(['owner_id' => $to->id]);
        $totals['opportunities'] += Opportunity::where('owner_id', $from->id)->whereNull('company_id')
            ->whereNotIn('stage', ['won', 'lost'])->update(['owner_id' => $to->id]);
        $totals['followups'] += Followup::where('owner_id', $from->id)->whereNull('company_id')
            ->whereNotIn('status', ['done'])->update(['owner_id' => $to->id]);
        $totals['clients'] += Client::where('owner_id', $from->id)->whereNull('company_id')
            ->update(['owner_id' => $to->id]);

        return $totals;
    }

    /** Suggest a successor: same-team sales rep first, then manager, then any sales. */
    public static function suggestSuccessor(User $user): ?User
    {
        $base = User::where('is_active', true)->where('id', '!=', $user->id)
            ->whereIn('role', ['sales_rep', 'manager']);
        if ($user->team_id) {
            $hit = (clone $base)->where('team_id', $user->team_id)->where('role', 'sales_rep')->orderBy('id')->first()
                ?? (clone $base)->where('team_id', $user->team_id)->orderBy('id')->first();
            if ($hit) {
                return $hit;
            }
        }

        return $base->orderBy('id')->first();
    }
}
