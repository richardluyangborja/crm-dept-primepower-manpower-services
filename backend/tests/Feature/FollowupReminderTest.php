<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 3 contract (specs/08): queue lifecycle + scheduler escalation. */
class FollowupReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setupTeam(): array
    {
        $team = Team::create(['name' => 'Manila']);
        $mgr = User::factory()->create(['email' => 'mgr.fup@primepower.ph', 'role' => 'manager', 'team_id' => $team->id]);
        $rep = User::factory()->create(['email' => 'rep.fup@primepower.ph', 'role' => 'sales_rep', 'team_id' => $team->id]);

        return [$mgr, $rep];
    }

    protected function clientFor(User $owner): Client
    {
        return Client::create(['owner_id' => $owner->id, 'name' => 'Reminder Client', 'status' => 'active']);
    }

    public function test_create_rejects_past_due_and_done_flow(): void
    {
        [$mgr, $rep] = $this->setupTeam();
        $t = auth('api')->login($rep);
        $cid = $this->clientFor($rep)->id;

        // Yesterday is rejected; today is allowed (due-today, overhaul Phase 5).
        $this->postJson('/api/v1/followups', [
            'client_id' => $cid, 'title' => 'Past one', 'due_at' => now()->subDay()->toIso8601String(),
        ], ['Authorization' => "Bearer $t"])->assertStatus(422);
        $this->postJson('/api/v1/followups', [
            'client_id' => $cid, 'title' => 'Due today', 'due_at' => now()->addHours(2)->toIso8601String(),
        ], ['Authorization' => "Bearer $t"])->assertCreated();

        $id = $this->postJson('/api/v1/followups', [
            'client_id' => $cid, 'title' => 'Call back', 'due_at' => now()->addDay()->toIso8601String(), 'priority' => 'high',
        ], ['Authorization' => "Bearer $t"])->assertCreated()->assertJsonPath('data.status', 'open')->json('data.id');

        $this->postJson("/api/v1/followups/$id/done", [], ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.status', 'done');
    }

    public function test_snooze_and_manual_escalate(): void
    {
        [$mgr, $rep] = $this->setupTeam();
        $t = auth('api')->login($rep);
        $id = $this->postJson('/api/v1/followups', [
            'client_id' => $this->clientFor($rep)->id, 'title' => 'Snooze me', 'due_at' => now()->addHours(2)->toIso8601String(),
        ], ['Authorization' => "Bearer $t"])->assertCreated()->json('data.id');

        $until = now()->addDays(3)->toIso8601String();
        $this->postJson("/api/v1/followups/$id/snooze", ['snoozed_until' => $until], ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.status', 'snoozed');

        $this->postJson("/api/v1/followups/$id/escalate", [], ['Authorization' => "Bearer $t"])
            ->assertOk()->assertJsonPath('data.status', 'escalated')
            ->assertJsonPath('data.escalated_to', $mgr->id);
    }

    public function test_dispatch_flips_overdue_and_auto_escalates_at_72h(): void
    {
        [$mgr, $rep] = $this->setupTeam();
        $cid = $this->clientFor($rep)->id;

        $old = \App\Models\Followup::create([
            'owner_id' => $rep->id, 'client_id' => $cid, 'title' => 'Ancient overdue',
            'due_at' => now()->subHours(80), 'status' => 'open',
        ]);
        $recent = \App\Models\Followup::create([
            'owner_id' => $rep->id, 'client_id' => $cid, 'title' => 'Just overdue',
            'due_at' => now()->subHours(2), 'status' => 'open',
        ]);

        $this->artisan('reminders:dispatch')->assertSuccessful();

        $this->assertSame('escalated', $old->refresh()->status);
        $this->assertSame($mgr->id, $old->refresh()->escalated_to);
        $this->assertSame('overdue', $recent->refresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $mgr->id, 'type' => 'escalation']);
        $this->assertDatabaseHas('notifications', ['user_id' => $rep->id, 'type' => 'overdue']);
    }

    public function test_reps_only_see_own_queue_and_notifications_are_personal(): void
    {
        [$mgr, $rep] = $this->setupTeam();
        $other = User::factory()->create(['email' => 'rep.other@primepower.ph', 'role' => 'sales_rep']);
        \App\Models\Followup::create([
            'owner_id' => $rep->id, 'client_id' => $this->clientFor($rep)->id,
            'title' => 'Mine', 'due_at' => now()->addDay(),
        ]);
        $list = $this->getJson('/api/v1/followups', ['Authorization' => 'Bearer '.auth('api')->login($other)])->assertOk();
        $this->assertCount(0, $list->json('data'));

        $note = \App\Models\Notification::create(['user_id' => $rep->id, 'type' => 'info', 'title' => 'Hi']);
        $this->postJson("/api/v1/notifications/{$note->opaqueId()}/read", [], ['Authorization' => 'Bearer '.auth('api')->login($other)])
            ->assertForbidden();
        $this->postJson("/api/v1/notifications/{$note->opaqueId()}/read", [], ['Authorization' => 'Bearer '.auth('api')->login($rep)])
            ->assertOk()->assertJsonPath('data.read_at', fn ($v) => $v !== null);
    }
}
