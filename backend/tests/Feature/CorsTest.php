<?php

namespace Tests\Feature;

use Tests\TestCase;

/** CORS contract (specs/01 + 14): SPA origin allowlisted, JWT bearer, no cookies. Hermetic — no web server needed. */
class CorsTest extends TestCase
{
    public function test_preflight_returns_allow_origin_for_frontend(): void
    {
        $res = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization, Content-Type',
        ]);
        $res->assertStatus(204);
        $this->assertSame('http://localhost:5173', $res->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_actual_request_carries_allow_origin(): void
    {
        $res = $this->call('GET', '/api/v1/health', [], [], [], ['HTTP_ORIGIN' => 'http://localhost:5173']);
        $res->assertOk();
        $this->assertSame('http://localhost:5173', $res->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_disallowed_origin_is_not_echoed_back(): void
    {
        // Single-origin allowlist: Fruitcake sets ACAO to the configured origin
        // on actual responses; browsers still block evil.example since it mismatches.
        $res = $this->call('GET', '/api/v1/health', [], [], [], ['HTTP_ORIGIN' => 'http://evil.example']);
        $res->assertOk();
        $this->assertNotSame('http://evil.example', $res->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_each_allowlisted_origin_is_echoed(): void
    {
        // Guards the localhost-vs-127.0.0.1 dev gotcha: every origin in
        // FRONTEND_URL must preflight cleanly.
        config()->set('cors.allowed_origins', ['http://localhost:5173', 'http://127.0.0.1:5173']);
        foreach (['http://localhost:5173', 'http://127.0.0.1:5173'] as $origin) {
            $res = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], [
                'HTTP_ORIGIN' => $origin,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            ]);
            $res->assertStatus(204);
            $this->assertSame($origin, $res->headers->get('Access-Control-Allow-Origin'));
        }
    }
}
