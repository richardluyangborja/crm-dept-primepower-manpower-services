<?php

namespace App\Services\Mocks;

use App\Services\Contracts\AiServiceInterface;

/**
 * Deterministic AI fixtures (specs/15, AI_MODE=mock).
 * Agent G replaces internals with rule services first, real models in v2 — same shape.
 */
class MockAiService implements AiServiceInterface
{
    public function clientRisk(int $clientId): array
    {
        return ['level' => 'low', 'drivers' => ['Seeded baseline — connect Insights services in Agent G stream'], 'confidence' => 40, 'mock' => true, 'ai_preview' => true];
    }

    public function winProbability(int $opportunityId): array
    {
        return ['probability' => 50, 'drivers' => ['Stage default — Agent G stream computes live value'], 'mock' => true, 'ai_preview' => true];
    }

    public function sentiment(string $text): array
    {
        $lower = mb_strtolower($text);
        foreach (['salamat', 'mabilis', 'magaling', 'great', 'excellent'] as $w) {
            if (str_contains($lower, $w)) {
                return ['label' => 'positive', 'score' => 0.8, 'mock' => true];
            }
        }
        foreach (['mabagal', 'delay', 'pangit', 'poor', 'bad'] as $w) {
            if (str_contains($lower, $w)) {
                return ['label' => 'negative', 'score' => 0.8, 'mock' => true];
            }
        }

        return ['label' => 'neutral', 'score' => 0.5, 'mock' => true];
    }
}
