<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Followup;
use App\Models\Invoice;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Phase 2B: mock AR — aging, payments, collection follow-ups. All mock-labeled. */
class InvoiceController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);
        $invoices = Invoice::visibleTo($request->user())->with('client:id,name')
            ->filter($request, ['status', 'client_id', 'owner_id'])
            ->orderByDesc('id')->paginate(min(100, (int) $request->query('per_page', 25)));

        return $this->paginated(InvoiceResource::collection($invoices));
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $invoice->load('client:id,name');

        return $this->ok(new InvoiceResource($invoice));
    }

    /** AR summary: totals + aging buckets + per-client outstanding (mock Dept 5). */
    public function summary(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);
        // Slim rows only (no full models, no per-row relations) — was a full
        // collection load with PHP grouping over fat rows.
        $rows = Invoice::visibleTo($request->user())->where('status', '!=', 'paid')
            ->get(['id', 'client_id', 'balance_centavos', 'due_at', 'status']);
        $names = $rows->isEmpty() ? collect() : \App\Models\Client::whereIn('id', $rows->pluck('client_id')->unique())
            ->pluck('name', 'id');

        $buckets = ['current' => 0, 'd1_30' => 0, 'd31_60' => 0, 'd61_90' => 0, 'd90plus' => 0];
        $overdueTotal = 0;
        $today = now()->startOfDay();
        foreach ($rows as $inv) {
            // Same math as Invoice::daysOverdue().
            $overdue = $inv->status === 'overdue'
                || ($inv->due_at !== null && \Carbon\Carbon::parse($inv->due_at)->lt($today));
            $days = (! $overdue || $inv->due_at === null)
                ? 0
                : $today->diffInDays(\Carbon\Carbon::parse($inv->due_at), true);
            if ($days <= 0) {
                $buckets['current'] += $inv->balance_centavos;
            } else {
                $overdueTotal += $inv->balance_centavos;
                if ($days <= 30) {
                    $buckets['d1_30'] += $inv->balance_centavos;
                } elseif ($days <= 60) {
                    $buckets['d31_60'] += $inv->balance_centavos;
                } elseif ($days <= 90) {
                    $buckets['d61_90'] += $inv->balance_centavos;
                } else {
                    $buckets['d90plus'] += $inv->balance_centavos;
                }
            }
        }

        $perClient = $rows->groupBy('client_id')->map(fn ($g) => [
            'client_id' => \App\Models\Client::encodeId($g->first()->client_id),
            'client_name' => $names[$g->first()->client_id] ?? null,
            'outstanding_centavos' => $g->sum('balance_centavos'),
            'invoices' => $g->count(),
        ])->values()->all();

        return $this->ok([
            'outstanding_total_centavos' => $rows->sum('balance_centavos'),
            'overdue_total_centavos' => $overdueTotal,
            'aging_buckets_centavos' => $buckets,
            'per_client' => $perClient,
            'mock' => true,
        ]);
    }

    /**
     * Record a mock payment. Amount optional → defaults to full balance
     * (powers both "Record payment" and "Mark paid" buttons).
     */
    public function pay(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $data = $request->validate(['amount_centavos' => ['sometimes', 'integer', 'min:1']]);
        if ($invoice->status === 'paid') {
            return $this->fail("{$invoice->ref} is already paid.", 422);
        }
        $amount = $data['amount_centavos'] ?? $invoice->balance_centavos;
        if ($amount > $invoice->balance_centavos) {
            return $this->fail("Overpayment rejected: balance is {$invoice->balance_centavos} centavos.", 422);
        }

        return DB::transaction(function () use ($invoice, $amount, $request) {
            $invoice->update([
                'balance_centavos' => $invoice->balance_centavos - $amount,
                'status' => ($invoice->balance_centavos - $amount) === 0 ? 'paid' : $invoice->status,
            ]);
            $invoice->audit('payment_recorded', $request->user()->id, [
                'amount_centavos' => $amount,
                'mock' => true,
            ]);

            return $this->ok(new InvoiceResource($invoice->refresh()), "Mock payment of {$amount} centavos recorded.");
        });
    }

    /** Collection follow-up hook: overdue invoice → reminder (ties into 08). */
    public function collect(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $data = $request->validate([
            'due_at' => ['sometimes', 'date', 'after:now'],
            'title' => ['sometimes', 'string', 'max:255'],
        ]);
        $followup = Followup::create([
            'owner_id' => $invoice->owner_id,
            'client_id' => $invoice->client_id,
            'title' => $data['title'] ?? "Collect {$invoice->ref} ({$invoice->balance_centavos} centavos)",
            'due_at' => $data['due_at'] ?? now()->addDays(3)->toIso8601String(),
            'priority' => 'high',
        ]);
        $followup->audit('created', $request->user()->id, ['via' => 'invoice_collect', 'invoice_id' => $invoice->id]);
        $invoice->audit('collection_started', $request->user()->id, ['followup_id' => $followup->id]);

        return $this->created(['followup_id' => $followup->id], 'Collection reminder created.');
    }
}
