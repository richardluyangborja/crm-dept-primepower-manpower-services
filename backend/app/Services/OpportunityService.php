<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Services\Contracts\BillingServiceInterface;
use App\Services\Contracts\JobOrderServiceInterface;
use App\Services\Contracts\NotifyServiceInterface;
use Illuminate\Support\Facades\DB;

/** Opportunity domain logic (specs/05). Terminal moves trigger mock Dept 1 + 5 docs. */
class OpportunityService
{
    public function __construct(
        protected JobOrderServiceInterface $jobs,
        protected BillingServiceInterface $billing,
        protected NotifyServiceInterface $notify,
    ) {}

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException (403 reopen denied)
     */
    public function moveStage(Opportunity $opp, array $input, int $actorId, string $actorRole): Opportunity
    {
        $from = $opp->stage;
        $to = $input['stage'];

        $reopening = in_array($from, ['won', 'lost'], true) && ! in_array($to, ['won', 'lost'], true);
        if ($reopening && $actorRole === 'sales_rep') {
            abort(403, 'Only managers can reopen a closed opportunity.');
        }
        // Money gates (specs/05): a deal can never be worth ₱0 past qualified,
        // and winning requires a signed contract — no contract-less wins.
        if ($to === 'qualified' && (int) $opp->value_centavos <= 0) {
            abort(422, 'Qualifying needs a peso value — set it before moving.');
        }
        if ($to === 'qualified' && ($opp->headcount === null || $opp->rate_per_head_centavos === null || $opp->contract_months === null)) {
            abort(422, 'Qualifying needs manpower terms (heads, rate, months) — set them before moving.');
        }
        if ($to === 'won' && ! \App\Models\Contract::where('opportunity_id', $opp->id)->where('status', 'active')->exists()) {
            abort(422, 'Winning needs a signed contract first — sign the terms before marking won.');
        }
        if ($reopening && empty($input['reopen_note'])) {
            abort(422, 'Reopening a closed opportunity needs a note.');
        }

        return DB::transaction(function () use ($opp, $input, $from, $to, $actorId, $reopening) {
            $opp->stage = $to;
            $opp->probability = $input['probability'] ?? Opportunity::STAGE_PROBABILITY[$to];
            $effective = isset($input['effective_date']) ? \Carbon\Carbon::parse($input['effective_date']) : now();
            if ($to === 'won') {
                $opp->won_at = $effective;
                $opp->lost_at = null;
                $opp->lost_reason = null;
            } elseif ($to === 'lost') {
                $opp->lost_at = $effective;
                $opp->won_at = null;
                $opp->lost_reason = $input['lost_reason'];
            } elseif ($reopening) {
                $opp->won_at = null;
                $opp->lost_at = null;
                $opp->lost_reason = null;
            }
            $opp->save();

            $meta = ['from' => $from, 'to' => $to];
            if ($to === 'contract') {
                // Signing requires agreed terms: heads, monthly rate, months, start date.
                $headcount = $input['headcount'] ?? $opp->headcount;
                $rate = $input['rate_per_head_centavos'] ?? $opp->rate_per_head_centavos;
                $months = $input['contract_months'] ?? $opp->contract_months;
                $start = $input['start_date'] ?? null;
                if (! $headcount || $rate === null || ! $months || ! $start) {
                    abort(422, 'Signing needs headcount, monthly rate, contract months, and start date.');
                }
                $opp->fill([
                    'headcount' => $headcount,
                    'rate_per_head_centavos' => $rate,
                    'contract_months' => $months,
                ]);
                $opp->save();
                $monthly = $headcount * $rate;
                $ref = 'CTR-2026-'.str_pad((string) $opp->id, 4, '0', STR_PAD_LEFT);
                $contract = \App\Models\Contract::withTrashed()->firstOrNew(
                    ['opportunity_id' => $opp->id, 'status' => 'active']
                );
                if ($contract->trashed()) {
                    $contract->restore();
                }
                $contract->fill([
                    'client_id' => $opp->client_id,
                    'owner_id' => $opp->owner_id,
                    'headcount' => $headcount,
                    'rate_per_head_centavos' => $rate,
                    'contract_months' => $months,
                    'monthly_billing_centavos' => $monthly,
                    'contract_total_centavos' => $monthly * $months,
                    'start_date' => $start,
                    'ref' => $ref,
                    'payload' => ['mock' => true, 'depts' => ['core3_docs', 'governance_legal', 'facilities_contracts']],
                ]);
                $contract->save();
                $meta['contract_id'] = $contract->id;
                $meta['contract_ref'] = $contract->ref;
                $meta['monthly_billing_centavos'] = $monthly;
            }
            if ($to === 'won') {
                // Mock cross-dept docs (specs/11) persisted as a first-class
                // JobOrder so the client timeline can show the journey (specs/18).
                // Idempotent: re-winning reuses the existing row for this opp.
                $jo = $this->jobs->pushWonOpportunity($opp);
                $inv = $this->billing->createDraftInvoice($opp);
                $meta['job_order'] = $jo;
                $meta['invoice'] = $inv;
                $jobOrder = \App\Models\JobOrder::withTrashed()->firstOrCreate(
                    ['opportunity_id' => $opp->id],
                    [
                        'client_id' => $opp->client_id,
                        'owner_id' => $opp->owner_id,
                        'ref' => $jo['job_order_ref'],
                        'title' => $opp->title,
                        'headcount' => $opp->headcount,
                        'value_centavos' => $opp->contractTotal() ?? $opp->value_centavos,
                        'status' => 'draft',
                        'invoice_ref' => $inv['invoice_ref'],
                        'payload' => ['mock' => true, 'job_order' => $jo, 'invoice' => $inv],
                    ]
                );
                if ($jobOrder->trashed()) {
                    $jobOrder->restore();
                }
                $meta['job_order_id'] = $jobOrder->id;
                // Monthly per-head billing: the first invoice covers ONE month,
                // not the contract total (refined model). Idempotent per opp.
                $monthly = $opp->monthlyBilling() ?? $opp->value_centavos;
                $invoice = \App\Models\Invoice::withTrashed()->firstOrCreate(
                    ['opportunity_id' => $opp->id],
                    [
                        'client_id' => $opp->client_id,
                        'job_order_id' => $jobOrder->id,
                        'owner_id' => $opp->owner_id,
                        'ref' => $inv['invoice_ref'],
                        'title' => $opp->title.' — month 1',
                        'amount_centavos' => $monthly,
                        'balance_centavos' => $monthly,
                        'status' => 'sent',
                        'due_at' => now()->addDays(30)->toDateString(),
                        'payload' => ['mock' => true, 'invoice' => $inv, 'billing' => 'monthly'],
                    ]
                );
                if ($invoice->trashed()) {
                    $invoice->restore();
                }
                $meta['invoice_id'] = $invoice->id;
                $this->notify->send($opp->owner_id, 'won', "Won: {$opp->title}", "Job order {$jobOrder->ref} created — staffing starts (mock).", '/clients/'.$opp->client->opaqueId());
            }
            if ($to === 'lost') {
                $meta['lost_reason'] = $opp->lost_reason;
            }
            if ($reopening) {
                $meta['reopen_note'] = $input['reopen_note'] ?? null;
            }
            $opp->audit('stage_moved', $actorId, $meta);

            return $opp->refresh();
        });
    }
}
