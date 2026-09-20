<?php

namespace App\Services\Insights;

/** v1 EN/TL keyword sentiment for survey comments (specs/15). v2: sklearn TF-IDF. */
class SentimentAnalyzer
{
    protected const POSITIVE = ['salamat', 'mabilis', 'magaling', 'mahusay', 'maayos', 'ok', 'great', 'excellent', 'good', 'thank', 'satisfied', 'happy'];
    protected const NEGATIVE = ['mabagal', 'delay', 'delayed', 'pangit', 'poor', 'bad', 'late', 'kulang', 'reklamo', 'complaint', 'disappointed', 'hindi'];

    public static function analyze(?string $text): array
    {
        if (! $text || trim($text) === '') {
            return ['label' => 'neutral', 'score' => 0.5, 'hits' => []];
        }
        $lower = mb_strtolower($text);
        $pos = array_values(array_filter(self::POSITIVE, fn ($w) => str_contains($lower, $w)));
        $neg = array_values(array_filter(self::NEGATIVE, fn ($w) => str_contains($lower, $w)));

        if (count($pos) > count($neg)) {
            return ['label' => 'positive', 'score' => 0.8, 'hits' => $pos];
        }
        if (count($neg) > count($pos)) {
            return ['label' => 'negative', 'score' => 0.8, 'hits' => $neg];
        }

        return ['label' => 'neutral', 'score' => 0.5, 'hits' => []];
    }
}
