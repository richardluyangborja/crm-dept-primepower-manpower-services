<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Base auth contract — agents must keep these green. */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_ok(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'no@primepower.ph', 'password' => 'wrongpass1'])
            ->assertUnauthorized();
    }

    public function test_login_issues_jwt_and_me_works(): void
    {
        $user = User::factory()->create(['email' => 'rep.test@primepower.ph']);
        $res = $this->postJson('/api/v1/auth/login', ['email' => 'rep.test@primepower.ph', 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'user']]);
        $token = $res->json('data.access_token');
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])->assertOk();
    }

    public function test_reports_require_manager_role(): void
    {
        // All stubs retired after Step 7 — reports are real and manager+.
        $user = User::factory()->create(['email' => 'rep.stub@primepower.ph', 'role' => 'sales_rep']);
        $token = auth('api')->login($user);
        $this->getJson('/api/v1/reports/weekly', ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
    }
}
