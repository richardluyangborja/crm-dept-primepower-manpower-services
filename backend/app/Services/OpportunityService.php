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
                        'value_centavos' => $opp->value_centavos,
                        'status' => 'draft',
                        'invoice_ref' => $inv['invoice_ref'],
                        'payload' => ['mock' => true, 'job_order' => $jo, 'invoice' => $inv],
                    ]
                );
                if ($jobOrder->trashed()) {
                    $jobOrder->restore();
                }
                $meta['job_order_id'] = $jobOrder->id;
                // Phase 2B: the mock draft invoice also becomes a first-class
                // row so AR aging/payments reconcile (idempotent per opp).
                $invoice = \App\Models\Invoice::withTrashed()->firstOrCreate(
                    ['opportunity_id' => $opp->id],
                    [
                        'client_id' => $opp->client_id,
                        'job_order_id' => $jobOrder->id,
                        'owner_id' => $opp->owner_id,
                        'ref' => $inv['invoice_ref'],
                        'title' => $opp->title,
                        'amount_centavos' => $opp->value_centavos,
                        'balance_centavos' => $opp->value_centavos,
                        'status' => 'sent',
                        'due_at' => now()->addDays(30)->toDateString(),
                        'payload' => ['mock' => true, 'invoice' => $inv],
                    ]
                );
                if ($invoice->trashed()) {
                    $invoice->restore();
                }
                $meta['invoice_id'] = $invoice->id;
                $this->notify->send($opp->owner_id, 'won', "Won: {$opp->title}", "Job order {$jobOrder->ref} created — staffing starts (mock).", "/clients/{$opp->client_id}");
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
