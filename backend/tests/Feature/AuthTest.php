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

    public function test_module_stubs_return_501_with_owner(): void
    {
        $user = User::factory()->create(['email' => 'rep.stub@primepower.ph']);
        $token = auth('api')->login($user);
        // Reports land in Step 7 — still a stub with its owner note.
        $res = $this->getJson('/api/v1/reports/weekly', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(501);
        $this->assertStringContainsString('Step 7', $res->getContent());
    }
}
