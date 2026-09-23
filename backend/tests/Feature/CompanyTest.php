<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use App\Services\BackfillCompanies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 1 company root (specs/03): CRUD, scoping, lookup, backfill. */
class CompanyTest extends TestCase
{
    use RefreshDatabase;

    protected function org(): array
    {
        $team = Team::create(['name' => 'Company']);
        $mk = fn ($email, $role) => User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $team->id]);
        return ['mgr' => $mk('mgr.co@primepower.ph', 'manager'), 'rep' => $mk('rep.co@primepower.ph', 'sales_rep'), 'team' => $team];
    }

    public function test_crud_and_scoping(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['rep']);
        $id = $this->postJson('/api/v1/companies', [
            'name' => 'Acme Manufacturing', 'industry' => 'Manufacturing',
            'address_city' => 'Calamba', 'address_province' => 'Laguna',
        ], ['Authorization' => "Bearer $t"])->assertCreated()->json('data.id');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{8,}$/', $id);

        $row = $this->getJson("/api/v1/companies/$id", ['Authorization' => "Bearer $t"])->assertOk()->json('data');
        $this->assertSame('Acme Manufacturing', $row['name']);
        $this->assertSame('Calamba', $row['address_city']);

        // Rep from another team sees none; garbage hash 404s.
        $other = User::factory()->create(['email' => 'other.co@primepower.ph', 'role' => 'sales_rep']);
        $ot = auth('api')->login($other);
        $this->getJson('/api/v1/companies', ['Authorization' => "Bearer $ot"])->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/companies/zzzz', ['Authorization' => "Bearer $t"])->assertNotFound();
    }

    public function test_lookup_matches_name_and_phone(): void
    {
        $o = $this->org();
        $t = auth('api')->login($o['rep']);
        Company::create(['owner_id' => $o['rep']->id, 'name' => 'Acme Manufacturing', 'contact_phone' => '+639171234567']);

        $hit = $this->getJson('/api/v1/companies/lookup?name=acme', ['Authorization' => "Bearer $t"])
            ->assertOk()->json('data.0');
        $this->assertSame('Acme Manufacturing', $hit['name']);
        $this->assertArrayHasKey('open_leads_count', $hit);

        $hit2 = $this->getJson('/api/v1/companies/lookup?phone=639171234567', ['Authorization' => "Bearer $t"])
            ->assertOk()->json('data.0');
        $this->assertSame('Acme Manufacturing', $hit2['name']);

        $this->getJson('/api/v1/companies/lookup', ['Authorization' => "Bearer $t"])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_backfill_links_existing_rows(): void
    {
        $o = $this->org();
        $client = Client::create(['owner_id' => $o['rep']->id, 'name' => 'Backfill Co', 'status' => 'active', 'industry' => 'Logistics']);
        $lead = Lead::create(['owner_id' => $o['rep']->id, 'company_name' => 'Backfill Co', 'contact_name' => 'Pat', 'status' => 'converted', 'converted_client_id' => $client->id]);
        $client->update(['created_from_lead_id' => $lead->id]);
        $opp = Opportunity::create(['client_id' => $client->id, 'owner_id' => $o['rep']->id, 'title' => 'Backfill deal', 'stage' => 'won', 'value_centavos' => 1000, 'probability' => 100]);
        $fup = Followup::create(['owner_id' => $o['rep']->id, 'client_id' => $client->id, 'title' => 'Backfill fup', 'due_at' => now()->addDay()]);

        $stats = BackfillCompanies::run();
        $this->assertSame(1, $stats['companies_created']);

        $company = Company::where('name', 'Backfill Co')->firstOrFail();
        $this->assertSame('Logistics', $company->industry);
        $this->assertSame($company->id, $client->refresh()->company_id);
        $this->assertSame($company->id, $lead->refresh()->company_id);
        $this->assertSame($company->id, $opp->refresh()->company_id);
        $this->assertSame($company->id, $fup->refresh()->company_id);

        // Idempotent re-run creates nothing new.
        $again = BackfillCompanies::run();
        $this->assertSame(0, $again['companies_created']);
    }
}
