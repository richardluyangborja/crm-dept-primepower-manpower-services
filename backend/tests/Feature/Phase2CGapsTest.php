<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Opportunity;
use App\Models\Survey;
use App\Models\SurveyTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Phase 2C gaps: CSV import, survey→followup hook, effective dates, duration. */
class Phase2CGapsTest extends TestCase
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

    public function test_import_template_and_mixed_csv(): void
    {
        $rep = $this->rep('rep.csv@primepower.ph');
        $h = $this->auth($rep);

        $tpl = $this->get('/api/v1/leads/import-template', $h)->assertOk();
        $this->assertStringContainsString('company_name,contact_name', $tpl->getContent());

        $csv = "company_name,contact_name,contact_email,contact_phone,source,notes\n"
            . "Good Corp,Ana Santos,ana@goodcorp.ph,+639171234567,referral,Hot lead\n"
            . ",No Company,,0917-bad-phone,website,\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);
        $res = $this->post('/api/v1/leads/import', ['file' => $file], $h)->assertOk()->json('data');
        $this->assertSame(1, $res['imported']);
        $this->assertCount(1, $res['failed']);
        $this->assertSame(3, $res['failed'][0]['row']);
        $this->assertDatabaseHas('leads', ['company_name' => 'Good Corp', 'owner_id' => $rep->id]);
    }

    public function test_import_rejects_bad_header(): void
    {
        $rep = $this->rep('rep.csv2@primepower.ph');
        $file = UploadedFile::fake()->createWithContent('bad.csv', "foo,bar\n1,2\n");
        $res = $this->post('/api/v1/leads/import', ['file' => $file], $this->auth($rep))->assertOk()->json('data');
        $this->assertSame(0, $res['imported']);
        $this->assertStringContainsString('Header must be', $res['failed'][0]['errors'][0]);
    }

    public function test_expire_overdue_spawns_followup_once(): void
    {
        $rep = $this->rep('rep.exp@primepower.ph');
        $client = Client::create(['owner_id' => $rep->id, 'name' => 'Expire Client', 'status' => 'active']);
        $tpl = SurveyTemplate::create(['name' => 'T', 'type' => 'nps', 'questions' => [['q' => 'Q?']]]);
        $survey = Survey::create([
            'template_id' => $tpl->id, 'client_id' => $client->id, 'sent_by' => $rep->id,
            'token' => 'expire-hook-token-001', 'status' => 'sent', 'due_at' => now()->subDay(),
        ]);

        $n = app(\App\Services\SurveyService::class)->expireOverdue();
        $this->assertSame(1, $n);
        $this->assertSame('expired', $survey->refresh()->status);
        $this->assertDatabaseHas('followups', ['client_id' => $client->id, 'owner_id' => $rep->id]);

        // Second run: already expired → no duplicate follow-up.
        $this->assertSame(0, app(\App\Services\SurveyService::class)->expireOverdue());
        $this->assertSame(1, \App\Models\Followup::where('client_id', $client->id)->count());
    }

    public function test_win_accepts_effective_date(): void
    {
        $rep = $this->rep('rep.eff@primepower.ph');
        $company = \App\Models\Company::create(['owner_id' => $rep->id, 'name' => 'Eff Co']);
        $client = Client::create(['owner_id' => $rep->id, 'company_id' => $company->id, 'name' => 'Eff Client', 'status' => 'active']);
        $opp = Opportunity::create([
            'client_id' => $client->id, 'company_id' => $company->id, 'owner_id' => $rep->id, 'title' => 'Eff deal',
            'stage' => 'negotiation', 'value_centavos' => 100000, 'probability' => 80,
        ]);
        $date = now()->subDays(5)->toDateString();
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/move", [
            'stage' => 'contract', 'headcount' => 10, 'rate_per_head_centavos' => 100000,
            'contract_months' => 12, 'start_date' => now()->toDateString(),
        ], $this->auth($rep))->assertOk();
        $this->postJson("/api/v1/opportunities/{$opp->opaqueId()}/win", ['effective_date' => $date], $this->auth($rep))
            ->assertOk();
        $this->assertSame($date, $opp->refresh()->won_at->toDateString());
    }

    public function test_activity_duration_roundtrip(): void
    {
        $rep = $this->rep('rep.dur@primepower.ph');
        $client = Client::create(['owner_id' => $rep->id, 'name' => 'Dur Client', 'status' => 'active']);
        $id = $this->postJson('/api/v1/activities', [
            'client_id' => $client->id, 'type' => 'call', 'subject' => 'Long call',
            'duration_minutes' => 45,
        ], $this->auth($rep))->assertCreated()->json('data.id');

        $row = $this->getJson("/api/v1/activities/$id", $this->auth($rep))->assertOk()->json('data');
        $this->assertSame(45, $row['duration_minutes']);
    }
}
