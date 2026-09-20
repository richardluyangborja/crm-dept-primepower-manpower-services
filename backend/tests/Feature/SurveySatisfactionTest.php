<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\SurveyTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 5 contract (specs/06): templates, send, public respond, edit window, analytics. */
class SurveySatisfactionTest extends TestCase
{
    use RefreshDatabase;

    protected function mgr(): User
    {
        $team = \App\Models\Team::create(['name' => 'Survey Manila']);
        return User::factory()->create(['email' => 'mgr.srv@primepower.ph', 'role' => 'manager', 'team_id' => $team->id]);
    }

    protected function rep(): User
    {
        return User::factory()->create(['email' => 'rep.srv@primepower.ph', 'role' => 'sales_rep']);
    }

    protected function clientFor(User $owner): Client
    {
        return Client::create(['owner_id' => $owner->id, 'name' => 'Survey Client', 'status' => 'active']);
    }

    public function test_template_crud_requires_manager(): void
    {
        $rep = $this->rep();
        $this->postJson('/api/v1/survey-templates', ['name' => 'Nope', 'type' => 'nps', 'questions' => [['q' => 'Q?']]], ['Authorization' => 'Bearer '.auth('api')->login($rep)])
            ->assertForbidden();

        $mgr = $this->mgr();
        $t = auth('api')->login($mgr);
        $id = $this->postJson('/api/v1/survey-templates', ['name' => 'Q NPS', 'type' => 'nps', 'questions' => [['q' => 'How likely?', 'scale' => 10]]], ['Authorization' => "Bearer $t"])
            ->assertCreated()->assertJsonPath('data.name', 'Q NPS')->json('data.id');

        $this->getJson("/api/v1/survey-templates/$id", ['Authorization' => "Bearer $t"])->assertOk();
        $this->putJson("/api/v1/survey-templates/$id", ['name' => 'Q NPS v2'], ['Authorization' => "Bearer $t"])->assertOk()->assertJsonPath('data.name', 'Q NPS v2');
    }

    public function test_send_creates_token_and_public_respond_with_edit_window(): void
    {
        $mgr = $this->mgr();
        $t = auth('api')->login($mgr);
        $tpl = SurveyTemplate::create(['name' => 'TPL NPS', 'type' => 'nps', 'questions' => [['q' => 'Likelihood?', 'scale' => 10]]]);
        $cid = $this->clientFor($mgr)->id;

        $surveyId = $this->postJson('/api/v1/surveys', ['template_id' => $tpl->id, 'client_id' => $cid], ['Authorization' => "Bearer $t"])
            ->assertCreated()->assertJsonPath('data.status', 'sent')->json('data.id');
        $token = \App\Models\Survey::find($surveyId)->token;
        $this->assertNotEmpty($token);

        // Public fetch — no auth
        $this->getJson("/api/v1/s/$token")->assertOk()->assertJsonPath('data.survey.template.type', 'nps');

        // Respond
        $this->postJson("/api/v1/s/$token/respond", ['score' => 9, 'comment' => 'Mabilis ang deployment, salamat!'])->assertCreated();
        // Duplicate respond blocked
        $this->postJson("/api/v1/s/$token/respond", ['score' => 5])->assertStatus(409);

        // Edit within 24h OK, with new score
        $this->putJson("/api/v1/s/$token/respond", ['score' => 8, 'comment' => 'Updated'])->assertOk()->assertJsonPath('data.score', 8);

        // Simulate past edit window
        $resp = \App\Models\SurveyResponse::where('survey_id', $surveyId)->first();
        $resp->update(['responded_at' => now()->subDays(2)]);
        $this->putJson("/api/v1/s/$token/respond", ['score' => 6])->assertStatus(410);
    }

    public function test_analytics_returns_nps_and_low_scores(): void
    {
        $mgr = $this->mgr();
        $t = auth('api')->login($mgr);
        $tpl = SurveyTemplate::create(['name' => 'TPL2 NPS', 'type' => 'nps', 'questions' => [['q' => 'Q?', 'scale' => 10]]]);
        $cid = $this->clientFor($mgr)->id;
        foreach ([9, 6, 4] as $score) {
            $s = \App\Models\Survey::create(['template_id' => $tpl->id, 'client_id' => $cid, 'sent_by' => $mgr->id, 'token' => \Illuminate\Support\Str::random(32), 'status' => 'responded', 'due_at' => now()->addWeek()]);
            $s->responses()->create(['score' => $score, 'comment' => $score < 7 ? 'Low' : 'High', 'responded_at' => now()]);
        }
        $data = $this->getJson('/api/v1/surveys-analytics', ['Authorization' => "Bearer $t"])->assertOk()->json('data');
        $this->assertArrayHasKey('nps', $data);
        $this->assertGreaterThanOrEqual(2, count(array_filter($data['low_scores'], fn ($r) => $r['score'] < 7))); // 6 and 4 both low
        $this->assertNotEmpty($data['trend']);
    }

    public function test_rep_can_check_survey_of_own_client(): void
    {
        $rep = $this->rep();
        $rt = auth('api')->login($rep);
        $mt = auth('api')->login($this->mgr());
        $tpl = SurveyTemplate::create(['name' => 'TPL3 NPS', 'type' => 'nps', 'questions' => [['q' => 'Q?']]]);
        $cid = $this->clientFor($rep)->id;
        $sid = $this->postJson('/api/v1/surveys', ['template_id' => $tpl->id, 'client_id' => $cid], ['Authorization' => "Bearer $mt"])->assertCreated()->json('data.id');
        // rep owns client, so can view
        $this->getJson("/api/v1/surveys/$sid", ['Authorization' => "Bearer $rt"])->assertOk();
    }
}
