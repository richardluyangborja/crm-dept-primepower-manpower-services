<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lead & Client hub (specs/04 hub): cross-client contacts directory scoping + search. */
class LeadClientHubTest extends TestCase
{
    use RefreshDatabase;

    protected function org(): array
    {
        $team = Team::create(['name' => 'Cebu']);
        $mk = fn ($email, $role) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $team->id]);
        return ['mgr' => $mk('mgr.hub2@primepower.ph', 'manager'), 'rep' => $mk('rep.hub2@primepower.ph', 'sales_rep'), 'team' => $team];
    }

    public function test_contacts_directory_lists_visible_clients_with_names_and_search(): void
    {
        $o = $this->org();
        $client = Client::create(['owner_id' => $o['rep']->id, 'name' => 'Hub2 Client', 'status' => 'active', 'last_contacted_at' => now()]);
        Contact::create(['client_id' => $client->id, 'full_name' => 'Hub2 Person', 'position' => 'HR Manager', 'email' => 'hub2@example.ph', 'phone' => '+639171111111', 'is_primary' => true]);

        $t = auth('api')->login($o['rep']);
        $row = $this->getJson('/api/v1/contacts', ['Authorization' => "Bearer $t"])
            ->assertOk()->json('data.0');
        $this->assertSame('Hub2 Person', $row['full_name']);
        $this->assertSame('Hub2 Client', $row['client_name']);

        // Search by person, position, and client name.
        foreach (['hub2 person', 'hr manager', 'hub2 client'] as $term) {
            $this->getJson('/api/v1/contacts?q='.urlencode($term), ['Authorization' => "Bearer $t"])
                ->assertOk()->assertJsonPath('meta.total', 1);
        }
        $this->getJson('/api/v1/contacts?q=nobody-here', ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('meta.total', 0);

        // Rep from another team sees none.
        $other = User::factory()->create(['email' => 'other.hub2@primepower.ph', 'role' => 'sales_rep']);
        $ot = auth('api')->login($other);
        $this->getJson('/api/v1/contacts', ['Authorization' => "Bearer $ot"])->assertOk()
            ->assertJsonPath('data', []);
    }
}
