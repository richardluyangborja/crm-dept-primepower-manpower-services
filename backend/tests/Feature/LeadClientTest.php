<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 1 contract (specs/04): capture → qualify → convert, PH validation, scoping. */
class LeadClientTest extends TestCase
{
    use RefreshDatabase;

    protected function token(User $user): string
    {
        return auth('api')->login($user);
    }

    protected function rep(array $over = []): User
    {
        return User::factory()->create(['role' => 'sales_rep'] + $over);
    }

    public function test_rep_captures_lead_with_score_and_duplicate_warning(): void
    {
        $rep = $this->rep(['email' => 'rep.cap@primepower.ph']);
        $t = $this->token($rep);
        $payload = [
            'company' => ['name' => 'BGC Tech Solutions Inc.', 'industry' => 'BPO', 'address_city' => 'Taguig'],
            'contact_name' => 'Paolo Gutierrez',
            'contact_email' => 'hrd@bgctech.ph', 'contact_phone' => '+639171111111',
            'source' => 'referral',
        ];
        $first = $this->postJson('/api/v1/leads', $payload, ['Authorization' => "Bearer $t"])
            ->assertCreated()->assertJsonPath('data.score', 45);
        $this->assertNull($first->json('meta.duplicate_warning'));

        // Same phone under a different company → still created, warns with existing id.
        $second = $this->postJson('/api/v1/leads', [
            'company' => ['name' => 'BGC Tech Duplicate'],
            'contact_name' => 'Paolo Gutierrez',
            'contact_email' => 'hrd@bgctech.ph', 'contact_phone' => '+639171111111',
            'source' => 'referral',
        ], ['Authorization' => "Bearer $t"])
            ->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('meta.duplicate_warning.id'));
    }

    public function test_ph_phone_validated(): void
    {
        $rep = $this->rep(['email' => 'rep.phone@primepower.ph']);
        $this->postJson('/api/v1/leads', [
            'company' => ['name' => 'X'], 'contact_name' => 'Y', 'contact_phone' => '09171234567',
        ], ['Authorization' => 'Bearer '.$this->token($rep)])
            ->assertStatus(422)->assertJsonValidationErrors('contact_phone');
    }

    public function test_reps_only_see_own_leads(): void
    {
        $a = $this->rep(['email' => 'rep.a@primepower.ph']);
        $b = $this->rep(['email' => 'rep.b@primepower.ph']);
        $this->postJson('/api/v1/leads', ['company' => ['name' => 'A Corp'], 'contact_name' => 'A'], ['Authorization' => 'Bearer '.$this->token($a)])->assertCreated();
        $list = $this->getJson('/api/v1/leads', ['Authorization' => 'Bearer '.$this->token($b)])->assertOk();
        $this->assertCount(0, $list->json('data'));
    }

    public function test_convert_creates_client_contact_and_is_idempotent(): void
    {
        $rep = $this->rep(['email' => 'rep.conv@primepower.ph']);
        $t = $this->token($rep);
        $leadId = $this->postJson('/api/v1/leads', [
            'company' => ['name' => 'Davao Prime Hotel', 'industry' => 'Hospitality', 'address_city' => 'Davao'],
            'contact_name' => 'Mark Villanueva', 'contact_position' => 'HR Manager',
            'contact_email' => 'admin@davaoprime.ph', 'contact_phone' => '+639175555555',
        ], ['Authorization' => "Bearer $t"])->assertCreated()->json('data.id');

        $res = $this->postJson("/api/v1/leads/$leadId/convert", [
            'create_opportunity' => true, 'opportunity_title' => '80 guards — Davao Prime',
            'opportunity_value_centavos' => 240000000,
        ], ['Authorization' => "Bearer $t"])->assertCreated();
        $clientId = $res->json('data.client_id');
        $this->assertNotNull($res->json('data.opportunity_id'));

        // Client 360 header includes primary contact from the lead.
        $client = $this->getJson("/api/v1/clients/$clientId", ['Authorization' => "Bearer $t"])->assertOk()->json('data');
        $this->assertSame('Davao Prime Hotel', $client['name']);
        $this->assertSame('Mark Villanueva', $client['contacts'][0]['full_name']);

        // Second convert → 409.
        $this->postJson("/api/v1/leads/$leadId/convert", [], ['Authorization' => "Bearer $t"])->assertStatus(409);
    }

    public function test_one_active_lead_per_company(): void
    {
        $rep = $this->rep(['email' => 'rep.once@primepower.ph']);
        $t = $this->token($rep);
        $payload = ['company' => ['name' => 'Once Co'], 'contact_name' => 'Solo'];
        $this->postJson('/api/v1/leads', $payload, ['Authorization' => "Bearer $t"])->assertCreated();
        // Second lead for the same company → 409 pointing at the open one.
        $companyId = \App\Models\Company::where('name', 'Once Co')->firstOrFail()->opaqueId();
        $res = $this->postJson('/api/v1/leads', ['company_id' => $companyId, 'contact_name' => 'Dupe'], ['Authorization' => "Bearer $t"])
            ->assertStatus(409);
        $this->assertNotEmpty($res->json('meta.existing_lead_id'));
    }

    public function test_unqualified_requires_reason_and_delete_is_admin_only(): void
    {        $rep = $this->rep(['email' => 'rep.unq@primepower.ph']);
        $t = $this->token($rep);
        $leadId = $this->postJson('/api/v1/leads', ['company' => ['name' => 'Z Corp'], 'contact_name' => 'Z'], ['Authorization' => "Bearer $t"])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/leads/$leadId", ['status' => 'unqualified'], ['Authorization' => "Bearer $t"])->assertStatus(422);
        $this->putJson("/api/v1/leads/$leadId", ['status' => 'unqualified', 'unqualified_reason' => 'No budget'], ['Authorization' => "Bearer $t"])->assertOk();
        $this->deleteJson("/api/v1/leads/$leadId", [], ['Authorization' => "Bearer $t"])->assertForbidden();
    }
}
