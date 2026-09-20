<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 2B contract: mock AR aging, payments, collection hooks, win linkage. */
class InvoiceFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function org(): array
    {
        $team = Team::create(['name' => 'Manila']);
        $mk = fn ($email, $role) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $team->id]);
        return ['mgr' => $mk('mgr.inv@primepower.ph', 'manager'), 'rep' => $mk('rep.inv@primepower.ph', 'sales_rep'), 'team' => $team];
    }

    protected function clientFor(User $owner): Client
    {
        return Client::create(['owner_id' => $owner->id, 'name' => 'Invoice Client '.uniqid(), 'status' => 'active']);
    }

    protected function invoiceFor(User $owner, Client $client, array $over = []): Invoice
    {
        return Invoice::create(array_merge([
            'client_id' => $client->id, 'owner_id' => $owner->id,
            'ref' => 'INV-TEST-'.uniqid(), 'title' => 'Test invoice',
            'amount_centavos' => 100000, 'balance_centavos' => 100000,
            'status' => 'sent', 'due_at' => now()->addDays(30)->toDateString(),
        ], $over));
    }

    protected function token(User $u): string { return auth('api')->login($u); }

    public function test_win_creates_invoice_row_idempotently(): void
    {
        $o = $this->org();
        $t = $this->token($o['rep']);
        $client = $this->clientFor($o['rep']);
        $oppId = $this->postJson('/api/v1/opportunities', [
            'client_id' => $client->id, 'title' => 'Invoice deal', 'value_centavos' => 500000,
        ], ['Authorization' => "Bearer $t"])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/opportunities/$oppId/win", [], ['Authorization' => "Bearer $t"])->assertOk();
        $inv = Invoice::where('opportunity_id', $oppId)->firstOrFail();
        $this->assertSame('sent', $inv->status);
        $this->assertSame(500000, $inv->balance_centavos);
        $this->assertStringStartsWith('INV-', $inv->ref);

        // Re-win after manager reopen reuses the row (no duplicate ref).
        $mt = $this->token($o['mgr']);
        $this->postJson("/api/v1/opportunities/$oppId/move", ['stage' => 'proposal', 'reopen_note' => 'Revived'], ['Authorization' => "Bearer $mt"])->assertOk();
        $this->postJson("/api/v1/opportunities/$oppId/win", [], ['Authorization' => "Bearer $mt"])->assertOk();
        $this->assertSame(1, Invoice::where('opportunity_id', $oppId)->count());
    }

    public function test_list_scoped_and_shaped(): void
    {
        $o = $this->org();
        $other = User::factory()->create(['email' => 'other.inv@primepower.ph', 'role' => 'sales_rep']);
        $this->invoiceFor($o['rep'], $this->clientFor($o['rep']));

        $mine = $this->getJson('/api/v1/invoices', ['Authorization' => 'Bearer '.$this->token($o['rep'])])->assertOk();
        $this->assertCount(1, $mine->json('data'));
        $this->assertArrayHasKey('is_overdue', $mine->json('data.0'));
        $this->assertArrayHasKey('days_overdue', $mine->json('data.0'));

        $theirs = $this->getJson('/api/v1/invoices', ['Authorization' => 'Bearer '.$this->token($other)])->assertOk();
        $this->assertCount(0, $theirs->json('data'));

        // Manager sees the team book.
        $team = $this->getJson('/api/v1/invoices', ['Authorization' => 'Bearer '.$this->token($o['mgr'])])->assertOk();
        $this->assertCount(1, $team->json('data'));
    }

    public function test_partial_and_full_payment_guards(): void
    {
        $o = $this->org();
        $t = $this->token($o['rep']);
        $inv = $this->invoiceFor($o['rep'], $this->clientFor($o['rep']));

        $this->postJson("/api/v1/invoices/{$inv->id}/pay", ['amount_centavos' => 150000], ['Authorization' => "Bearer $t"])->assertStatus(422);
        $this->postJson("/api/v1/invoices/{$inv->id}/pay", ['amount_centavos' => 40000], ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.balance_centavos', 60000)->assertJsonPath('data.status', 'sent');
        $this->postJson("/api/v1/invoices/{$inv->id}/pay", [], ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.balance_centavos', 0)->assertJsonPath('data.status', 'paid');
        $this->postJson("/api/v1/invoices/{$inv->id}/pay", ['amount_centavos' => 100], ['Authorization' => "Bearer $t"])->assertStatus(422);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'invoices', 'entity_id' => $inv->id, 'action' => 'payment_recorded']);
    }

    public function test_collect_creates_high_priority_followup(): void
    {
        $o = $this->org();
        $t = $this->token($o['rep']);
        $client = $this->clientFor($o['rep']);
        $inv = $this->invoiceFor($o['rep'], $client);

        $fid = $this->postJson("/api/v1/invoices/{$inv->id}/collect", [], ['Authorization' => "Bearer $t"])
            ->assertCreated()->json('data.followup_id');
        $this->assertDatabaseHas('followups', ['id' => $fid, 'client_id' => $client->id, 'priority' => 'high', 'owner_id' => $o['rep']->id]);
    }

    public function test_summary_buckets_reconcile(): void
    {
        $o = $this->org();
        $t = $this->token($o['rep']);
        $client = $this->clientFor($o['rep']);
        $this->invoiceFor($o['rep'], $client, ['ref' => 'INV-S1', 'amount_centavos' => 100000, 'balance_centavos' => 100000, 'due_at' => now()->addDays(10)->toDateString()]);
        $this->invoiceFor($o['rep'], $client, ['ref' => 'INV-S2', 'amount_centavos' => 200000, 'balance_centavos' => 200000, 'due_at' => now()->subDays(10)->toDateString()]);
        $this->invoiceFor($o['rep'], $client, ['ref' => 'INV-S3', 'amount_centavos' => 400000, 'balance_centavos' => 400000, 'due_at' => now()->subDays(40)->toDateString()]);
        $this->invoiceFor($o['rep'], $client, ['ref' => 'INV-S4', 'amount_centavos' => 800000, 'balance_centavos' => 0, 'status' => 'paid', 'due_at' => now()->subDays(70)->toDateString()]);

        $s = $this->getJson('/api/v1/finance/summary', ['Authorization' => "Bearer $t"])->assertOk()->json('data');
        $this->assertSame(700000, $s['outstanding_total_centavos']);
        $this->assertSame(600000, $s['overdue_total_centavos']);
        $this->assertSame(100000, $s['aging_buckets_centavos']['current']);
        $this->assertSame(200000, $s['aging_buckets_centavos']['d1_30']);
        $this->assertSame(400000, $s['aging_buckets_centavos']['d31_60']);
        $this->assertSame(700000, $s['per_client'][0]['outstanding_centavos']);
        $this->assertTrue($s['mock']);
    }
}
