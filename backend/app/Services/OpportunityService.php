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
            if ($to === 'won') {
                $opp->won_at = now();
                $opp->lost_at = null;
                $opp->lost_reason = null;
            } elseif ($to === 'lost') {
                $opp->lost_at = now();
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
                // Mock cross-dept docs (specs/11): same interface live impls will use in v2.
                $meta['job_order'] = $this->jobs->pushWonOpportunity($opp);
                $meta['invoice'] = $this->billing->createDraftInvoice($opp);
                $this->notify->send($opp->owner_id, 'won', "Won: {$opp->title}", 'Job order + draft invoice created (mock).', "/pipeline?stage=won");
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
