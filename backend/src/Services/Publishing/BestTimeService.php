<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Suggests the best upcoming times to post per platform. Heuristic model
 * (preferred weekdays + hours per platform, in the account's timezone).
 * Phase 3 can refine these from real engagement data.
 */
final class BestTimeService
{
    /** platform => ['days' => [0=Sun..6=Sat], 'hours' => [24h]] ranked best-first */
    private const MODEL = [
        'instagram' => ['days' => [3, 2, 4, 1], 'hours' => [11, 13, 19]],
        'facebook'  => ['days' => [3, 4, 2],    'hours' => [9, 13, 15]],
        'linkedin'  => ['days' => [2, 3, 4],    'hours' => [8, 10, 12, 17]],
        'twitter'   => ['days' => [3, 1, 5],    'hours' => [9, 12, 18]],
        'tiktok'    => ['days' => [2, 4, 6],    'hours' => [18, 20, 22]],
        'youtube'   => ['days' => [5, 6, 4],    'hours' => [15, 17, 20]],
        'pinterest' => ['days' => [6, 0, 5],    'hours' => [20, 21, 14]],
    ];

    /** @return array<int,array{datetime:string,label:string,score:int}> */
    public function suggest(string $platform, string $timezone = 'UTC', int $count = 5): array
    {
        $model = self::MODEL[$platform] ?? self::MODEL['instagram'];
        $tz = $this->safeZone($timezone);
        $now = new DateTimeImmutable('now', $tz);

        $out = [];
        // Look across the next 10 days, keep slots that match preferred day+hour.
        for ($d = 0; $d < 10 && count($out) < $count * 2; $d++) {
            $day = $now->modify("+$d days");
            $dow = (int) $day->format('w');
            $dayRank = array_search($dow, $model['days'], true);
            if ($dayRank === false) {
                continue;
            }
            foreach ($model['hours'] as $hourRank => $hour) {
                $slot = $day->setTime($hour, 0);
                if ($slot <= $now) {
                    continue;
                }
                $score = 100 - ($dayRank * 10) - ($hourRank * 5);
                $out[] = [
                    'datetime' => $slot->format('Y-m-d\TH:i'),
                    'label'    => $slot->format('D, M j · g:i A'),
                    'score'    => max(1, $score),
                ];
            }
        }

        usort($out, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['datetime'], $b['datetime']));
        return array_slice($out, 0, $count);
    }

    private function safeZone(string $tz): DateTimeZone
    {
        try {
            return new DateTimeZone($tz);
        } catch (\Throwable) {
            return new DateTimeZone('UTC');
        }
    }
}
