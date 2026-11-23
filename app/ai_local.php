<?php
declare(strict_types=1);

function aiTaskPlacement(array $task, array $ranges): ?array
{
    foreach ($ranges as [$start, $end]) {
        $latestEnd = min($end, $task['deadline']);
        $finish = addMinutes($start, $task['minutes']);
        if ($finish <= $latestEnd) {
            return $task + ['start' => $start, 'end' => $finish, 'latest_end' => $latestEnd];
        }
    }
    return null;
}

function searchAiTaskSlots(array $tasks, array $ranges): array
{
    // O peso de uma entrega supera a soma dos pesos das entregas que vêm depois dela.
    $weights = [];
    foreach (array_keys($tasks) as $index => $ref) {
        $weights[$ref] = 1 << (count($tasks) - $index - 1);
    }
    $best = [];
    $bestScore = 0;
    $fullScore = array_sum($weights);
    $search = static function (array $remaining, array $free, array $chosen, int $score) use (&$search, &$best, &$bestScore, $weights, $fullScore): void {
        if ($score > $bestScore) {
            $best = $chosen;
            $bestScore = $score;
        }
        foreach ($remaining as $ref => $task) {
            if ($bestScore === $fullScore) {
                return;
            }
            $slot = aiTaskPlacement($task, $free);
            if ($slot === null) {
                continue;
            }
            $next = $remaining;
            unset($next[$ref]);
            $search($next, subtract($free, $slot['start'], $slot['end']), [...$chosen, $slot], $score + $weights[$ref]);
        }
    };
    $search($tasks, $ranges, [], 0);
    return $best;
}

function aiShiftTasksForPause(array $timeline, int $next, string $resume): ?array
{
    for ($i = $next; isset($timeline[$i]) && $timeline[$i]['start'] < $resume; $i++) {
        $item = $timeline[$i];
        if ($item['scheduled']) {
            return null;
        }
        $finish = addMinutes($resume, $item['minutes']);
        if ($finish > $item['latest_end']) {
            return null;
        }
        $timeline[$i]['start'] = $resume;
        $timeline[$i]['end'] = $resume = $finish;
    }
    return $timeline;
}

function aiPauseSlot(array &$timeline, int $index, array $ranges, int $preferred): ?array
{
    $next = $timeline[$index + 1] ?? null;
    $nextStart = $next['start'] ?? null;
    $previousEnd = $timeline[$index]['end'] ?? '';
    $best = null;
    $bestMinutes = MIN_REST_MINUTES - 1;
    foreach ($ranges as [$from, $until]) {
        $start = max($from, $previousEnd);
        if ($start >= $until || ($nextStart !== null && $start > $nextStart)) {
            continue;
        }
        $limit = $nextStart !== null && $next['scheduled'] ? min($until, $nextStart) : $until;
        for ($minutes = min($preferred, duration($start, $limit)); $minutes > $bestMinutes; $minutes--) {
            $end = addMinutes($start, $minutes);
            $adjusted = aiShiftTasksForPause($timeline, $index + 1, $end);
            if ($adjusted === null) {
                continue;
            }
            $best = [[$start, $end], $adjusted];
            $bestMinutes = $minutes;
            break;
        }
        if ($bestMinutes === $preferred) {
            break;
        }
    }
    if ($best === null) {
        return null;
    }
    [$slot, $timeline] = $best;
    return $slot;
}

function aiPauseItem(array &$data, DateTimeImmutable $clock, array $slot): array
{
    [$start, $end] = $slot;
    $alternative = null;
    if (in_array($data['choice']['mode'], ['balance', 'leisure'], true)) {
        [$alternative] = leisureOptions($data, $clock, $slot);
        if ($alternative) {
            $data['blocks'][] = ['kind' => 'interest', 'item_id' => $alternative['id'],
                'start_at' => $alternative['start'], 'end_at' => $alternative['end']];
        }
    }
    return ['id' => 0, 'kind' => 'rest', 'title' => 'Pausa', 'start' => $start, 'end' => $end,
        'minutes' => duration($start, $end), 'scheduled' => false, 'alternative' => $alternative];
}

function aiTimeline(array $data, array $timeline, array $ranges, DateTimeImmutable $clock): array
{
    usort($timeline, static fn(array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
    if (!allowedSuggestion($data, 'rest:0', timeKey($clock))) {
        return $timeline;
    }
    $dayMode = $data['choice']['mode'];
    $preferred = min(15, (int) $data['goals']['rest_minutes']);
    $result = [];
    $hasPause = false;
    foreach (array_keys($timeline) as $index) {
        $item = $timeline[$index];
        $result[] = $item;
        $next = $timeline[$index + 1] ?? null;
        $hasPause = $hasPause || $item['kind'] !== 'task';
        if ($item['kind'] === 'task' && ($next['kind'] ?? null) === 'task') {
            $slot = aiPauseSlot($timeline, $index, $ranges, $preferred);
            if ($slot !== null) {
                $result[] = aiPauseItem($data, $clock, $slot);
                $hasPause = true;
            }
        }
    }
    if (!$hasPause && $dayMode !== 'task') {
        $trailingMinutes = $dayMode === 'rest' ? (int) $data['goals']['rest_minutes'] : $preferred;
        if (in_array($dayMode, ['rest', 'leisure'], true) && $timeline) {
            $leading = aiPauseSlot($timeline, -1, $ranges, $trailingMinutes);
            if ($leading !== null) {
                return [aiPauseItem($data, $clock, $leading), ...$result];
            }
        }
        $slot = aiPauseSlot($timeline, count($timeline) - 1, $ranges, $trailingMinutes);
        if ($slot !== null) {
            $result[] = aiPauseItem($data, $clock, $slot);
        }
    }
    return $result;
}
