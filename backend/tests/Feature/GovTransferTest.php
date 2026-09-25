<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ownership governance (specs/02): transfer moves open records, guards hold. */
class GovTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function org(): array
    {
        $manila = Team::create(['name' => 'Manila']);
        $cebu = Team::create(['name' => 'Cebu']);
        $mk = fn ($email, $role, $team) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $team->id]);
        return [
            'admin' => $mk('admin.gov@primepower.ph', 'admin', $manila),
            'mgr' => $mk('mgr.gov@primepower.ph', 'manager', $manila),
            'rep' => $mk('rep.gov@primepower.ph', 'sales_rep', $manila),
            'mate' => $mk('mate.gov@primepower.ph', 'sales_rep', $manila),
            'other' => $mk('other.gov@primepower.ph', 'sales_rep', $cebu),
        ];
    }

    protected function companyFor(User $owner, string $name = 'Gov Co'): Company
    {
        $co = Company::create(['owner_id' => $owner->id, 'name' => $name]);
        $lead = Lead::create(['owner_id' => $owner->id, 'company_id' => $co->id, 'company_name' => $name, 'contact_name' => 'Gov Person', 'status' => 'qualified']);
        $client = Client::create(['owner_id' => $owner->id, 'company_id' => $co->id, 'name' => $name, 'status' => 'active']);
        Opportunity::create(['company_id' => $co->id, 'client_id' => $client->id, 'owner_id' => $owner->id, 'title' => 'Gov deal', 'stage' => 'negotiation', 'value_centavos' => 100000, 'probability' => 80]);
        $done = Followup::create(['owner_id' => $owner->id, 'company_id' => $co->id, 'title' => 'Old', 'due_at' => now()->subDay(), 'status' => 'done']);
        Followup::create(['owner_id' => $owner->id, 'company_id' => $co->id, 'title' => 'Open task', 'due_at' => now()->addDay(), 'status' => 'open']);
        // Closed history keeps original attribution.
        $lead->audit('created', $owner->id, []);

        return $co;
    }

    public function test_owner_transfers_company_with_open_records(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['rep']);
        $co = $this->companyFor($o['rep']);

        $res = $this->postJson("/api/v1/companies/{$co->opaqueId()}/transfer", [
            'to_user_id' => $o['mate']->id, 'reason' => 'Workload rebalance',
        ], ['Authorization' => "Bearer $t"])->assertOk()->json('data');

        $this->assertSame($o['mate']->id, Company::find($co->id)->owner_id);
        $this->assertSame($o['mate']->id, Lead::where('company_id', $co->id)->first()->owner_id);
        $this->assertSame($o['mate']->id, Opportunity::where('company_id', $co->id)->first()->owner_id);
        $this->assertSame($o['mate']->id, Client::where('company_id', $co->id)->first()->owner_id);
        $this->assertSame($o['mate']->id, Followup::where('company_id', $co->id)->where('status', 'open')->first()->owner_id);
        // Done follow-up stays with the original owner.
        $this->assertSame($o['rep']->id, Followup::where('company_id', $co->id)->where('status', 'done')->first()->owner_id);
        // Audit trail recorded.
        $this->assertDatabaseHas('audit_logs', ['entity' => 'companies', 'entity_id' => $co->id, 'action' => 'transferred']);
    }

    public function test_transfer_guards(): void
    {
        $o = $this->org();
        $co = $this->companyFor($o['rep']);
        $url = "/api/v1/companies/{$co->opaqueId()}/transfer";

        // Stranger rep: 403. Cross-team manager: 403. Same-team manager: ok.
        $this->postJson($url, ['to_user_id' => $o['mate']->id], ['Authorization' => 'Bearer '.auth('api')->login($o['other'])])->assertForbidden();
        $this->postJson($url, ['to_user_id' => $o['mate']->id], ['Authorization' => 'Bearer '.auth('api')->login($o['mgr'])])->assertOk();

        // Target must be active sales/manager; self-transfer rejected.
        $co2 = $this->companyFor($o['mate'], 'Gov Co 2');
        $url2 = "/api/v1/companies/{$co2->opaqueId()}/transfer";
        $mt = auth('api')->login($o['mate']);
        $this->postJson($url2, ['to_user_id' => $o['admin']->id], ['Authorization' => "Bearer $mt"])->assertStatus(422);
        $this->postJson($url2, ['to_user_id' => $o['mate']->id], ['Authorization' => "Bearer $mt"])->assertStatus(422);
    }

    public function test_owner_name_on_detail_pages(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['rep']);
        $co = $this->companyFor($o['rep']);
        $lead = Lead::where('company_id', $co->id)->first();
        $client = Client::where('company_id', $co->id)->first();

        $this->getJson("/api/v1/leads/{$lead->opaqueId()}", ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.owner_name', $o['rep']->name);
        $this->getJson("/api/v1/clients/{$client->opaqueId()}", ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.owner_name', $o['rep']->name);
    }
}
