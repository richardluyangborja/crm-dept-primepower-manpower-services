<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Overhaul Phase 2: company-owner assignment — the company owner owns its pipeline. */
class CompanyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function mk(string $email, string $role, ?int $teamId = null): User
    {
        return User::factory()->create(['email' => $email, 'role' => $role, 'team_id' => $teamId]);
    }

    protected function companyFor(User $owner, string $name = 'Acme Foods'): Company
    {
        return Company::create(['owner_id' => $owner->id, 'name' => $name.' '.uniqid()]);
    }

    protected function leadPayload(array $extra = []): array
    {
        return ['contact_name' => 'HR Person', 'contact_phone' => '+639171234567'] + $extra;
    }

    public function test_admin_assigns_new_lead_and_company_to_rep(): void
    {
        $admin = $this->mk('admin.co@primepower.ph', 'admin');
        $rep = $this->mk('rep.co@primepower.ph', 'sales_rep');
        $t = auth('api')->login($admin);

        $this->postJson('/api/v1/leads', $this->leadPayload([
            'company' => ['name' => 'Assigned Co '.uniqid()],
            'owner_id' => $rep->id,
        ]), ['Authorization' => "Bearer $t"])->assertCreated();

        $lead = Lead::latest('id')->first();
        $this->assertSame($rep->id, $lead->owner_id);
        $this->assertSame($rep->id, $lead->company->owner_id);
    }

    public function test_lead_on_existing_company_defaults_to_company_owner(): void
    {
        $team = Team::create(['name' => 'T0']);
        $manager = $this->mk('mgr.co@primepower.ph', 'manager', $team->id);
        $rep = $this->mk('rep.co2@primepower.ph', 'sales_rep', $team->id);
        $co = $this->companyFor($rep);
        $t = auth('api')->login($manager);

        $this->postJson('/api/v1/leads', $this->leadPayload(['company_id' => $co->opaqueId()]),
            ['Authorization' => "Bearer $t"])->assertCreated();

        $this->assertSame($rep->id, Lead::latest('id')->first()->owner_id);
    }

    public function test_explicit_assignment_moves_existing_company_with_audit(): void
    {
        $admin = $this->mk('admin.co3@primepower.ph', 'admin');
        $repA = $this->mk('rep.coa@primepower.ph', 'sales_rep');
        $repB = $this->mk('rep.cob@primepower.ph', 'sales_rep');
        $co = $this->companyFor($repA);
        $t = auth('api')->login($admin);

        $this->postJson('/api/v1/leads', $this->leadPayload(['company_id' => $co->opaqueId(), 'owner_id' => $repB->id]),
            ['Authorization' => "Bearer $t"])->assertCreated();

        $this->assertSame($repB->id, $co->refresh()->owner_id);
        $this->assertSame($repB->id, Lead::latest('id')->first()->owner_id);
        $this->assertDatabaseHas('audit_logs', ['entity' => 'companies', 'entity_id' => $co->id, 'action' => 'owner_assigned']);
    }

    public function test_deal_defaults_to_company_owner_and_rep_sees_company_deals(): void
    {
        $repA = $this->mk('rep.deala@primepower.ph', 'sales_rep');
        $repB = $this->mk('rep.dealb@primepower.ph', 'sales_rep');
        $co = $this->companyFor($repB);
        // Legacy-style mismatch: row owned by A, company owned by B.
        $opp = Opportunity::create([
            'owner_id' => $repA->id, 'company_id' => $co->id, 'title' => 'Mismatch deal',
            'stage' => 'new', 'probability' => 10, 'value_centavos' => 100000,
        ]);

        $tB = auth('api')->login($repB);
        $list = $this->getJson('/api/v1/opportunities', ['Authorization' => "Bearer $tB"])->assertOk()->json('data');
        $this->assertContains($opp->opaqueId(), array_column($list, 'id'));
        $this->getJson("/api/v1/opportunities/{$opp->opaqueId()}", ['Authorization' => "Bearer $tB"])->assertOk();

        // A stranger rep sees nothing.
        $repC = $this->mk('rep.dealc@primepower.ph', 'sales_rep');
        $tC = auth('api')->login($repC);
        $listC = $this->getJson('/api/v1/opportunities', ['Authorization' => "Bearer $tC"])->assertOk()->json('data');
        $this->assertNotContains($opp->opaqueId(), array_column($listC, 'id'));
        $this->getJson("/api/v1/opportunities/{$opp->opaqueId()}", ['Authorization' => "Bearer $tC"])->assertForbidden();
    }

    public function test_new_deal_inherits_company_owner(): void
    {
        $team = Team::create(['name' => 'TDeal']);
        $manager = $this->mk('mgr.deal@primepower.ph', 'manager', $team->id);
        $rep = $this->mk('rep.deald@primepower.ph', 'sales_rep', $team->id);
        $co = $this->companyFor($rep);
        $t = auth('api')->login($manager);

        $this->postJson('/api/v1/opportunities', [
            'company_id' => $co->opaqueId(), 'title' => 'Inherited deal', 'value_centavos' => 500000,
        ], ['Authorization' => "Bearer $t"])->assertCreated();

        $this->assertSame($rep->id, Opportunity::latest('id')->first()->owner_id);
    }

    public function test_manager_sees_team_company_rows(): void
    {
        $team = Team::create(['name' => 'T1']);
        $mgr = $this->mk('mgr.team@primepower.ph', 'manager', $team->id);
        $rep = $this->mk('rep.team@primepower.ph', 'sales_rep', $team->id);
        $co = $this->companyFor($rep);
        $lead = Lead::create(['owner_id' => $rep->id, 'company_id' => $co->id, 'company_name' => $co->name,
            'contact_name' => 'C', 'status' => 'new']);
        $t = auth('api')->login($mgr);

        $list = $this->getJson('/api/v1/leads', ['Authorization' => "Bearer $t"])->assertOk()->json('data');
        $this->assertContains($lead->opaqueId(), array_column($list, 'id'));
    }

    public function test_owner_must_be_an_active_sales_role(): void
    {
        $admin = $this->mk('admin.guard@primepower.ph', 'admin');
        $otherAdmin = $this->mk('admin.guard2@primepower.ph', 'admin');
        $off = $this->mk('rep.off@primepower.ph', 'sales_rep');
        $off->update(['is_active' => false]);
        $t = auth('api')->login($admin);

        foreach ([$otherAdmin->id, $off->id] as $bad) {
            $this->postJson('/api/v1/leads', $this->leadPayload([
                'company' => ['name' => 'Guard Co '.uniqid()], 'owner_id' => $bad,
            ]), ['Authorization' => "Bearer $t"])->assertStatus(422);
        }
    }
}
