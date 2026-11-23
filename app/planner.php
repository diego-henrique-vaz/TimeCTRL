<?php
declare(strict_types=1);

const INTERESTS = ['book' => 'Livro', 'game' => 'Jogo', 'movie' => 'Filme', 'series' => 'Série', 'anime' => 'Anime'];
const PROGRESS = ['want' => 'Quero começar', 'ongoing' => 'Em andamento', 'done' => 'Concluído'];
const MODES = ['task' => 'Tarefas', 'leisure' => 'Lazer', 'rest' => 'Descanso', 'balance' => 'Equilíbrio'];
const ACTIVITIES = ['work' => 'Trabalho', 'study' => 'Estudo', 'book' => 'Leitura', 'game' => 'Jogo', 'rest' => 'Descanso', 'other' => 'Outro lazer'];
const PRIORITIES = [3 => 'Alta', 2 => 'Normal', 1 => 'Baixa'];
const ROUTINES = ['usual' => 'Manter a agenda cadastrada', 'works' => 'Vou trabalhar no feriado', 'changed' => 'Minha rotina mudou'];

function at(string $value): DateTimeImmutable
{
    return new DateTimeImmutable($value, new DateTimeZone('America/Sao_Paulo'));
}

function timeKey(DateTimeImmutable $value): string
{
    return $value->format('Y-m-d H:i');
}

function addMinutes(string $start, int $minutes): string
{
    return timeKey(at($start)->modify("+$minutes minutes"));
}

function duration(string $start, string $end): int
{
    return (int) ((at($end)->getTimestamp() - at($start)->getTimestamp()) / 60);
}

function overlaps(string $a, string $b, string $c, string $d): bool
{
    return $a < $d && $c < $b;
}

function subtract(array $ranges, string $start, string $end): array
{
    $remaining = [];
    foreach ($ranges as [$a, $b]) {
        if (!overlaps($a, $b, $start, $end)) {
            $remaining[] = [$a, $b];
            continue;
        }
        if ($a < $start) {
            $remaining[] = [$a, $start];
        }
        if ($end < $b) {
            $remaining[] = [$end, $b];
        }
    }
    return $remaining;
}

function freeRanges(array $data, ?int $ignoreBlock = null): array
{
    $ranges = [];
    $busy = [];
    foreach ($data['windows'] as $window) {
        $interval = [$window['start_at'], $window['end_at']];
        if ($window['kind'] === 'free') {
            $ranges[] = $interval;
        } else {
            $busy[] = $interval;
        }
    }
    sort($ranges);
    $merged = [];
    foreach ($ranges as [$start, $end]) {
        $last = array_key_last($merged);
        if ($last !== null && $start <= $merged[$last][1]) {
            $merged[$last][1] = max($end, $merged[$last][1]);
        } else {
            $merged[] = [$start, $end];
        }
    }
    foreach ($data['blocks'] as $block) {
        if ((int) $block['id'] !== $ignoreBlock) {
            $busy[] = [$block['start_at'], $block['end_at']];
        }
    }
    foreach ($busy as [$start, $end]) {
        $merged = subtract($merged, $start, $end);
    }
    return $merged;
}

function conflict(array $block, array $windows): bool
{
    return !intervalFits(freeRanges(['windows' => $windows, 'blocks' => []]), $block['start_at'], $block['end_at']);
}

function intervalFits(array $ranges, string $start, string $end): bool
{
    foreach ($ranges as [$a, $b]) {
        if ($a <= $start && $end <= $b) {
            return true;
        }
    }
    return false;
}

function orderedTasks(array $tasks, bool $byPriority = false): array
{
    usort($tasks, static fn(array $a, array $b): int =>
        $byPriority ? ([-$a['priority'], $a['due_at'], $a['id']] <=> [-$b['priority'], $b['due_at'], $b['id']])
            : ([$a['due_at'], -$a['priority'], $a['id']] <=> [$b['due_at'], -$b['priority'], $b['id']]));
    return $tasks;
}

function allowedSuggestion(array $data, string $key, string $now): bool
{
    $record = $data['feedback'][$key] ?? null;
    return $record === null || ($record['decision'] === 'later' && $record['until_at'] <= $now);
}

function plan(array $data, DateTimeImmutable $clock): array
{
    $now = timeKey($clock);
    $ranges = freeRanges($data);
    $current = null;
    foreach ($ranges as [$a, $b]) {
        if ($a <= $now && $now < $b) {
            $current = [$now, $b];
            break;
        }
    }
    $tasks = orderedTasks(array_values(array_filter($data['tasks'], fn(array $t): bool => !$t['done'])));
    $taskNow = null;
    $forecasts = $late = $withoutTime = $near = $conflicts = [];
    $validTasks = $conflictingTasks = [];
    foreach ($data['blocks'] as $block) {
        if ($block['end_at'] <= $now) {
            continue;
        }
        if (conflict($block, $data['windows'])) {
            $conflicts[] = $block;
            if ($block['kind'] === 'task') {
                $conflictingTasks[(int) $block['item_id']] = true;
            }
        } elseif ($block['kind'] === 'task') {
            $validTasks[(int) $block['item_id']] = true;
        }
    }
    $projected = $ranges;
    $tomorrow = timeKey($clock->modify('+24 hours'));
    foreach ($tasks as $task) {
        $id = (int) $task['id'];
        $key = 'task:' . $id;
        if ($task['due_at'] <= $now) {
            $late[] = $task;
            continue;
        }
        if ($task['due_at'] <= $tomorrow) {
            $near[] = $task;
        }
        if (isset($validTasks[$id])) {
            continue;
        }
        $endNow = addMinutes($now, (int) $task['minutes']);
        if ($current && !$taskNow && !isset($conflictingTasks[$id]) && $endNow <= min($current[1], $task['due_at']) && allowedSuggestion($data, $key, $now)) {
            $taskNow = ['key' => $key, 'kind' => 'task', 'id' => $id, 'title' => $task['title'],
                'start' => $now, 'end' => $endNow, 'reason' => 'Cabe no tempo livre atual e está entre as entregas com prazo mais próximo.'];
        }
        $slot = null;
        foreach ($projected as [$a, $b]) {
            $start = max($now, $a);
            $end = addMinutes($start, (int) $task['minutes']);
            if ($end <= min($task['due_at'], $b)) {
                $slot = [$start, $end];
                break;
            }
        }
        if ($slot === null) {
            $withoutTime[] = $task;
        } else {
            $projected = subtract($projected, ...$slot);
            if (allowedSuggestion($data, $key, $now)) {
                $forecasts[] = ['key' => $key, 'kind' => 'task', 'id' => $id, 'title' => $task['title'], 'start' => $slot[0],
                    'end' => $slot[1], 'reason' => 'Este intervalo comporta a tarefa e termina antes do prazo.'];
            }
        }
    }
    return compact('ranges', 'current', 'taskNow', 'forecasts', 'late', 'withoutTime', 'near', 'conflicts');
}

function leisureOptions(array $data, DateTimeImmutable $clock, ?array $current): array
{
    if (!$current) {
        return [null, null];
    }
    [$start, $end] = $current;
    $week = $clock->modify('monday this week')->format('Y-m-d');
    $today = $clock->format('Y-m-d');
    $reading = $gaming = 0;
    $busyDays = [];
    foreach ($data['activities'] as $entry) {
        if ($entry['log_day'] > $today) {
            continue;
        }
        if ($entry['log_day'] >= $week) {
            $reading += (int) ($entry['kind'] === 'book');
            $gaming += $entry['kind'] === 'game' ? (int) $entry['minutes'] : 0;
        }
        if (in_array($entry['kind'], ['work', 'study'], true)) {
            $busyDays[$entry['log_day']] = true;
        }
    }
    $goals = $data['goals'];
    $readingDue = $reading < (int) $goals['reading_sessions'];
    $cap = (int) $goals['gaming_minutes'];
    $items = $data['interests'];
    usort($items, static fn(array $a, array $b): int =>
        [!($readingDue && $a['category'] === 'book'), $a['progress'] !== 'ongoing', $a['id']]
            <=> [!($readingDue && $b['category'] === 'book'), $b['progress'] !== 'ongoing', $b['id']]);
    $leisure = null;
    foreach ($items as $item) {
        $key = 'interest:' . $item['id'];
        $finish = addMinutes($start, (int) $item['minutes']);
        if ($item['progress'] === 'done' || $finish > $end || !allowedSuggestion($data, $key, $start)
            || ($item['category'] === 'game' && $cap && $gaming + (int) $item['minutes'] > $cap)) {
            continue;
        }
        $reason = 'Você salvou este interesse, e a sessão cabe no horário livre.';
        if ($readingDue && $item['category'] === 'book') {
            $reason .= ' Ela também ajuda a cumprir sua meta de leitura da semana.';
        }
        if ($item['session_note'] !== '') {
            $reason .= ' Sessão: ' . $item['session_note'] . '.';
        }
        $leisure = ['key' => $key, 'kind' => 'interest', 'id' => (int) $item['id'], 'title' => $item['title'],
            'start' => $start, 'end' => $finish, 'reason' => $reason];
        break;
    }
    $finish = addMinutes($start, (int) $goals['rest_minutes']);
    $rest = null;
    if ($finish <= $end && $goals['rest_minutes'] > 0 && allowedSuggestion($data, 'rest:0', $start)) {
        $streak = 0;
        $day = isset($busyDays[$today]) ? $clock : $clock->modify('-1 day');
        while (isset($busyDays[$day->format('Y-m-d')])) {
            $streak++;
            $day = $day->modify('-1 day');
        }
        $rest = ['key' => 'rest:0', 'kind' => 'rest', 'id' => 0, 'title' => 'Reservar uma pausa',
            'start' => $start, 'end' => $finish, 'reason' => 'Há um horário livre suficiente para a pausa que você configurou.',
            'goalReached' => $goals['busy_days'] > 0 && $streak >= $goals['busy_days']];
        if ($rest['goalReached']) {
            $rest['reason'] = "Você registrou $streak dias seguidos com trabalho ou estudo. Há tempo livre para uma pausa.";
        }
    }
    return [$leisure, $rest];
}

function recommendation(array $data, array $plan, DateTimeImmutable $clock): ?array
{
    [$leisure, $rest] = leisureOptions($data, $clock, $plan['current']);
    return match ($data['choice']['mode']) {
        'task' => $plan['taskNow'],
        'leisure' => $leisure,
        'rest' => $rest,
        default => $plan['taskNow'] ?? (($rest['goalReached'] ?? false) ? $rest : $leisure),
    };
}
