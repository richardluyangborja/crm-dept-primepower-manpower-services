<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Opaque public IDs (specs/14): no bare-integer IDs leak; tampered hashes 404; authz unchanged. */
class OpaqueIdTest extends TestCase
{
    use RefreshDatabase;

    /** Numeric IDs that are intentionally NOT opaque (internal entities, out of scope). */
    protected const NUMERIC_OK = ['owner_id', 'sent_by', 'escalated_to', 'template_id', 'user_id', 'team_id'];

    protected function scan(mixed $node, string $path = ''): array
    {
        $bad = [];
        if (is_array($node)) {
            foreach ($node as $k => $v) {
                $p = $path === '' ? (string) $k : "$path.$k";
                if ((is_string($k) && ($k === 'id' || str_ends_with($k, '_id'))) && ! in_array($k, self::NUMERIC_OK, true)) {
                    if (! is_string($v) && $v !== null) {
                        $bad[] = "$p=" . var_export($v, true);
                    } elseif (is_string($v) && ! preg_match('/^[A-Za-z0-9]{8,}$/', $v)) {
                        $bad[] = "$p=$v";
                    }
                } else {
                    array_push($bad, ...$this->scan($v, $p));
                }
            }
        }

        return $bad;
    }

    public function test_no_bare_integer_ids_in_scope(): void
    {
        $team = Team::create(['name' => 'Opaque']);
        $rep = User::factory()->create(['email' => 'rep.opaque@primepower.ph', 'role' => 'sales_rep', 'team_id' => $team->id]);
        $t = auth('api')->login($rep);
        $h = ['Authorization' => "Bearer $t"];

        $lead = Lead::create(['owner_id' => $rep->id, 'company_name' => 'Opaque Co', 'contact_name' => 'Op Person']);
        $client = Client::create(['owner_id' => $rep->id, 'name' => 'Opaque Client', 'status' => 'active', 'created_from_lead_id' => $lead->id]);
        Contact::create(['client_id' => $client->id, 'full_name' => 'Op Contact']);
        $opp = Opportunity::create(['client_id' => $client->id, 'owner_id' => $rep->id, 'title' => 'Op Deal', 'stage' => 'contacted', 'value_centavos' => 1000, 'probability' => 10]);
        Followup::create(['owner_id' => $rep->id, 'client_id' => $client->id, 'title' => 'Op Fup', 'due_at' => now()->addDay()]);
        Activity::create(['owner_id' => $rep->id, 'client_id' => $client->id, 'type' => 'note', 'occurred_at' => now()]);
        Notification::create(['user_id' => $rep->id, 'type' => 'info', 'title' => 'Op Note']);

        foreach (['leads', 'clients', 'opportunities', 'contacts', 'followups', 'activities', 'notifications', 'job-orders', 'invoices', 'surveys', 'staffing', 'finance/summary', 'dashboard/summary', 'surveys-analytics'] as $ep) {
            $res = $this->getJson("/api/v1/$ep", $h)->assertOk()->json();
            $this->assertSame([], $this->scan($res), "bare IDs in $ep");
        }
        foreach (["leads/{$lead->opaqueId()}", "clients/{$client->opaqueId()}", "opportunities/{$opp->opaqueId()}"] as $ep) {
            $res = $this->getJson("/api/v1/$ep", $h)->assertOk()->json();
            $this->assertSame([], $this->scan($res), "bare IDs in $ep");
        }

        $mgr = User::factory()->create(['email' => 'mgr.opaque@primepower.ph', 'role' => 'manager', 'team_id' => $team->id]);
        $mh = ['Authorization' => 'Bearer '.auth('api')->login($mgr)];
        $res = $this->getJson('/api/v1/bi/client-breakdown', $mh)->assertOk()->json();
        $this->assertSame([], $this->scan($res), 'bare IDs in bi/client-breakdown');
    }

    public function test_tampered_hash_is_404_and_authz_still_403(): void
    {
        $team = Team::create(['name' => 'Opaque2']);
        $rep = User::factory()->create(['email' => 'rep.opaque2@primepower.ph', 'role' => 'sales_rep', 'team_id' => $team->id]);
        $other = User::factory()->create(['email' => 'other.opaque2@primepower.ph', 'role' => 'sales_rep']);
        $client = Client::create(['owner_id' => $rep->id, 'name' => 'Opaque Client 2', 'status' => 'active']);

        $t = auth('api')->login($rep);
        // Integer IDs no longer resolve.
        $this->getJson("/api/v1/clients/{$client->id}", ['Authorization' => "Bearer $t"])->assertNotFound();
        // Garbage hashes → 404, not 500 — including inputs that crash GMP-backed decoders.
        foreach (['notahash!!', 'zzzz', 'zzzzzzzz', '!!!!!!!!', str_repeat('A', 65)] as $bad) {
            $this->getJson("/api/v1/clients/$bad", ['Authorization' => "Bearer $t"])->assertNotFound();
        }
        // Someone else's record decodes fine but policy still denies → 403, not 404.
        $ot = auth('api')->login($other);
        $this->getJson("/api/v1/clients/{$client->opaqueId()}", ['Authorization' => "Bearer $ot"])->assertForbidden();
    }
}
