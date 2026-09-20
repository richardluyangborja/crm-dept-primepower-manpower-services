<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * v1.0.1 regression: every list endpoint must return Resource-shaped rows
 * (specs/14) — raw-model envelopes once blanked the whole SPA because the
 * frontend renders shaped fields (attachments[], client_name,
 * weighted_centavos, is_overdue) that raw models omit or null out.
 */
class ListEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function rep(string $email): User
    {
        return User::factory()->create(['email' => $email, 'role' => 'sales_rep']);
    }

    protected function auth(User $u): array
    {
        return ['Authorization' => 'Bearer '.auth('api')->login($u)];
    }

    public function test_activities_index_is_resource_shaped(): void
    {
        $rep = $this->rep('rep.env@primepower.ph');
        $client = Client::create(['owner_id' => $rep->id, 'name' => 'Env Client', 'status' => 'active']);
        \App\Models\Activity::create([
            'owner_id' => $rep->id, 'client_id' => $client->id, 'type' => 'note',
            'subject' => 'Shape check', 'occurred_at' => now(),
        ]);

        $row = $this->getJson('/api/v1/activities', $this->auth($rep))
            ->assertOk()->json('data.0');

        $this->assertIsArray($row['attachments'], 'attachments must be [] never null (SPA maps .length)');
        $this->assertSame('Env Client', $row['client_name']);
        $this->assertArrayHasKey('meta', $this->getJson('/api/v1/activities', $this->auth($rep))->json());
    }

    public function test_followups_index_works_and_is_shaped(): void
    {
        $rep = $this->rep('rep.env2@primepower.ph');
        $client = Client::create(['owner_id' => $rep->id, 'name' => 'Env Client 2', 'status' => 'active']);
        \App\Models\Followup::create([
            'owner_id' => $rep->id, 'client_id' => $client->id, 'title' => 'Shape check',
            'due_at' => now()->addDay(),
        ]);

        $row = $this->getJson('/api/v1/followups', $this->auth($rep))
            ->assertOk()->json('data.0');

        $this->assertSame('Env Client 2', $row['client_name']);
        $this->assertArrayHasKey('is_overdue', $row);
    }

    public function test_opportunities_index_carries_weighted_value(): void
    {
        $rep = $this->rep('rep.env3@primepower.ph');
        $client = Client::create(['owner_id' => $rep->id, 'name' => 'Env Client 3', 'status' => 'active']);
        \App\Models\Opportunity::create([
            'owner_id' => $rep->id, 'client_id' => $client->id, 'title' => 'Shape check',
            'stage' => 'proposal', 'value_centavos' => 100000, 'probability' => 60,
        ]);

        $row = $this->getJson('/api/v1/opportunities', $this->auth($rep))
            ->assertOk()->json('data.0');

        $this->assertSame(60000, $row['weighted_centavos']);
        $this->assertSame('Env Client 3', $row['client_name']);
    }

    public function test_leads_and_clients_indexes_are_shaped(): void
    {
        $rep = $this->rep('rep.env4@primepower.ph');
        \App\Models\Lead::create(['owner_id' => $rep->id, 'company_name' => 'Env Co', 'contact_name' => 'Env Person']);
        Client::create(['owner_id' => $rep->id, 'name' => 'Env Client 4', 'status' => 'prospect']);

        $lead = $this->getJson('/api/v1/leads', $this->auth($rep))->assertOk()->json('data.0');
        $this->assertArrayHasKey('score', $lead);
        $client = $this->getJson('/api/v1/clients', $this->auth($rep))->assertOk()->json('data.0');
        $this->assertSame('Env Client 4', $client['name']);
    }
}
