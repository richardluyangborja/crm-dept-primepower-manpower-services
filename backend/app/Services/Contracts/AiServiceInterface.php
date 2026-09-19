<?php

namespace App\Services\Contracts;

interface AiServiceInterface
{
    /** Customer-intelligence predictions. Mock returns deterministic fixtures (AI_MODE=mock). */
    public function clientRisk(int $clientId): array; // ['level'=>high|medium|low,'drivers'=>[…],'confidence'=>int]

    public function winProbability(int $opportunityId): array; // ['probability'=>int,'drivers'=>[…]]

    public function sentiment(string $text): array; // ['label'=>positive|neutral|negative,'score'=>float]
}
