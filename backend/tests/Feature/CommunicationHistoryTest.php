<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Step 4 contract (specs/07): loggers, timeline filters, templates, follow-up hook. */
class CommunicationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function rep(string $email): User
    {
        return User::factory()->create(['email' => $email, 'role' => 'sales_rep']);
    }

    protected function clientFor(User $owner): Client
    {
        return Client::create(['owner_id' => $owner->id, 'name' => 'Comms Client', 'status' => 'active']);
    }

    public function test_log_call_updates_client_last_contacted(): void
    {
        Storage::fake('local');
        $rep = $this->rep('rep.call@primepower.ph');
        $t = auth('api')->login($rep);
        $cid = $this->clientFor($rep)->id;

        $res = $this->postJson('/api/v1/activities', [
            'client_id' => $cid, 'type' => 'call',
            'subject' => 'Discovery call', 'body' => 'Needs 40 crew.',
            'outcome' => 'connected',
        ], ['Authorization' => "Bearer $t"])->assertCreated()
            ->assertJsonPath('data.type', 'call');

        $this->assertNotNull(Client::find($cid)->last_contacted_at);
        $this->assertSame('Logged — timeline updated.', $res->json('message'));
    }

    public function test_email_requires_subject_and_future_occurred_rejected(): void
    {
        $rep = $this->rep('rep.mail@primepower.ph');
        $t = auth('api')->login($rep);
        $cid = $this->clientFor($rep)->id;

        $this->postJson('/api/v1/activities', [
            'client_id' => $cid, 'type' => 'email', 'body' => 'No subject',
        ], ['Authorization' => "Bearer $t"])->assertStatus(422);

        $this->postJson('/api/v1/activities', [
            'client_id' => $cid, 'type' => 'note', 'body' => 'From the future',
            'occurred_at' => now()->addDay()->toIso8601String(),
        ], ['Authorization' => "Bearer $t"])->assertStatus(422);
    }

    public function test_timeline_filters_and_search(): void
    {
        $rep = $this->rep('rep.tl@primepower.ph');
        $t = auth('api')->login($rep);
        $cid = $this->clientFor($rep)->id;
        foreach ([
            ['type' => 'call', 'subject' => 'Morning call', 'body' => 'quotation discussed'],
            ['type' => 'site_visit', 'subject' => 'Plant visit', 'body' => 'shifting checked'],
        ] as $a) {
            $this->postJson('/api/v1/activities', ['client_id' => $cid] + $a, ['Authorization' => "Bearer $t"])->assertCreated();
        }

        $this->getJson('/api/v1/activities?type=call', ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/activities?q=quotation', ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_templates_listed_and_followup_hook_creates_reminder(): void
    {
        $rep = $this->rep('rep.hook@primepower.ph');
        $t = auth('api')->login($rep);
        $cid = $this->clientFor($rep)->id;

        $tpls = $this->getJson('/api/v1/message-templates', ['Authorization' => "Bearer $t"])
            ->assertOk()->json('data');
        $this->assertNotEmpty($tpls);

        $res = $this->postJson('/api/v1/activities', [
            'client_id' => $cid, 'type' => 'meeting', 'subject' => 'Intro meeting',
            'create_followup' => true, 'followup_title' => 'Send quotation',
            'followup_due_at' => now()->addDays(2)->toIso8601String(),
        ], ['Authorization' => "Bearer $t"])->assertCreated();
        $this->assertNotNull($res->json('meta.followup_id'));
        $this->assertDatabaseHas('followups', ['id' => $res->json('meta.followup_id'), 'title' => 'Send quotation']);
    }

    public function test_file_attachment_stored_and_listed_without_path(): void
    {
        Storage::fake('local');
        $rep = $this->rep('rep.file@primepower.ph');
        $t = auth('api')->login($rep);
        $cid = $this->clientFor($rep)->id;

        $res = $this->post('/api/v1/activities', [
            'client_id' => (string) $cid, 'type' => 'email', 'subject' => 'Quotation sent',
            'attachments' => [UploadedFile::fake()->create('quote.pdf', 100, 'application/pdf')],
        ], ['Authorization' => "Bearer $t"])->assertCreated();
        $files = $res->json('data.attachments');
        $this->assertSame('quote.pdf', $files[0]['name']);
        $this->assertArrayNotHasKey('path', $files[0]);
    }
}
