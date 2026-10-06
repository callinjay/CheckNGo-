<?php
/**
 * services/ReadinessService.php
 *
 * Readiness calculation:
 *   weight(critical) = 5, weight(important) = 3, weight(optional) = 1
 *   Readiness % = (sum of weights for CHECKED items / sum of weights for ALL items) * 100
 *
 * This is a simple, transparent, and fully documented formula — no hidden
 * heuristics. It is computed the same way everywhere in the system
 * (dashboard, quick check, history) by always calling this class.
 */

declare(strict_types=1);

class ReadinessService
{
    private const WEIGHTS = [
        'critical'  => 5,
        'important' => 3,
        'optional'  => 1,
    ];

    /**
     * @param array<int, array{priority:string, is_checked:bool|int}> $items
     * @return array{
     *   percent: float,
     *   critical_total:int, critical_checked:int,
     *   important_total:int, important_checked:int,
     *   optional_total:int, optional_checked:int,
     *   has_unchecked_critical: bool,
     *   status_message: string
     * }
     */
    public static function calculate(array $items): array
    {
        if (empty($items)) {
            return [
                'percent' => 0.0,
                'critical_total' => 0, 'critical_checked' => 0,
                'important_total' => 0, 'important_checked' => 0,
                'optional_total' => 0, 'optional_checked' => 0,
                'has_unchecked_critical' => false,
                'status_message' => 'No checklist items yet. Add belongings to get started.',
            ];
        }

        $totalWeight = 0;
        $earnedWeight = 0;
        $counts = [
            'critical'  => ['total' => 0, 'checked' => 0],
            'important' => ['total' => 0, 'checked' => 0],
            'optional'  => ['total' => 0, 'checked' => 0],
        ];

        foreach ($items as $item) {
            $priority = $item['priority'] ?? 'important';
            $weight = self::WEIGHTS[$priority] ?? self::WEIGHTS['important'];
            $checked = (bool) ($item['is_checked'] ?? false);

            $totalWeight += $weight;
            $counts[$priority]['total']++;

            if ($checked) {
                $earnedWeight += $weight;
                $counts[$priority]['checked']++;
            }
        }

        $percent = $totalWeight > 0 ? round(($earnedWeight / $totalWeight) * 100, 2) : 0.0;
        $hasUncheckedCritical = $counts['critical']['checked'] < $counts['critical']['total'];

        if ($hasUncheckedCritical) {
            $statusMessage = 'Readiness requires attention — critical items are still unchecked.';
        } elseif ($percent >= 100.0) {
            $statusMessage = "You're all set!";
        } else {
            $statusMessage = 'Almost ready — some non-critical items are still unchecked.';
        }

        return [
            'percent' => $percent,
            'critical_total'  => $counts['critical']['total'],
            'critical_checked'=> $counts['critical']['checked'],
            'important_total' => $counts['important']['total'],
            'important_checked'=> $counts['important']['checked'],
            'optional_total'  => $counts['optional']['total'],
            'optional_checked'=> $counts['optional']['checked'],
            'has_unchecked_critical' => $hasUncheckedCritical,
            'status_message' => $statusMessage,
        ];
    }
}
