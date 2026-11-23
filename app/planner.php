<?php
declare(strict_types=1);

const INTERESTS = ['book' => 'Livro', 'game' => 'Jogo', 'movie' => 'Filme', 'series' => 'Série', 'anime' => 'Anime'];
const PROGRESS = ['want' => 'Quero começar', 'ongoing' => 'Em andamento', 'done' => 'Concluído'];
const MODES = ['task' => 'Tarefas', 'leisure' => 'Lazer', 'rest' => 'Descanso', 'balance' => 'Equilíbrio'];
const ACTIVITIES = ['work' => 'Trabalho', 'study' => 'Estudo', 'book' => 'Leitura', 'game' => 'Jogo', 'rest' => 'Descanso', 'other' => 'Outro lazer'];
const PRIORITIES = [3 => 'Alta', 2 => 'Normal', 1 => 'Baixa'];
const ROUTINES = ['usual' => 'Manter a agenda cadastrada', 'works' => 'Vou trabalhar no feriado', 'changed' => 'Minha rotina mudou'];
const WINDOW_TYPES = ['free' => 'Livre', 'busy' => 'Compromisso', 'work' => 'Trabalho'];
const WEEKDAYS = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
const MIN_REST_MINUTES = 5;

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

function repeatsOn(array $rule, DateTimeImmutable $date): bool
{
    return $date->format('Y-m-d') >= $rule['first_day'] && ($rule['weekdays'] & (1 << ((int) $date->format('N') - 1))) !== 0;
}

function shiftInterval(string $day, string $start, string $end): array
{
    $endDay = $end < $start ? at($day)->modify('+1 day')->format('Y-m-d') : $day;
    return ["$day $start", "$endDay $end"];
}

function recurringAgenda(array $rules, array $exceptions, array $blocks, string $day): array
{
    $focus = at($day);
    $last = $focus->modify('+89 days');
    $dates = [];
    for ($date = $focus->modify('-1 day'); $date <= $last; $date = $date->modify('+1 day')) {
        $dates[$date->format('Y-m-d')] = $date;
    }
    foreach ($blocks as $block) {
        if ($block['end_at'] <= $last->format('Y-m-d') . ' 00:00') {
            continue;
        }
        $date = at(substr($block['start_at'], 0, 10));
        foreach ([$date->modify('-1 day'), $date, at(substr($block['end_at'], 0, 10))] as $needed) {
            $dates[$needed->format('Y-m-d')] = $needed;
        }
    }
    $changes = [];
    foreach ($exceptions as $exception) {
        $changes[$exception['occurrence_day']][$exception['rule_id']] = $exception;
    }

    $recurring_windows = $occurrences = [];
    foreach ($dates as $key => $date) {
        foreach ($rules as $rule) {
            if (!repeatsOn($rule, $date)) {
                continue;
            }
            $exception = $changes[$key][$rule['id']] ?? null;
            $original = shiftInterval($key, $rule['start_time'], $rule['end_time']);
            $skip = ($exception['change_kind'] ?? '') === 'skip';
            $interval = $exception && !$skip ? shiftInterval($key, $exception['start_time'], $exception['end_time']) : $original;
            $window = ['title' => $rule['title'], 'kind' => $rule['kind'], 'start_at' => $interval[0], 'end_at' => $interval[1],
                'rule_id' => $rule['id'], 'occurrence_day' => $key, 'exception' => $exception, 'skipped' => $skip];
            if (!$skip) {
                $recurring_windows[] = $window;
            }
            if ($key === $day) {
                $occurrences[] = $window;
            }
            if ($exception && $exception['release_time'] && $rule['kind'] !== 'free') {
                foreach ($skip ? [$original] : subtract([$original], ...$interval) as [$start, $end]) {
                    $recurring_windows[] = ['title' => 'Tempo liberado · ' . $rule['title'], 'kind' => 'free', 'start_at' => $start,
                        'end_at' => $end, 'released' => true];
                }
            }
        }
    }
    return compact('recurring_windows', 'occurrences') + ['routine_horizon' => $last->modify('+1 day')->format('Y-m-d') . ' 00:00'];
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

function rangesOnDay(array $ranges, string $day, DateTimeImmutable $clock): array
{
    $from = max($day . ' 00:00', timeKey($clock));
    $until = timeKey(at($day)->modify('+1 day'));
    $available = [];
    foreach ($ranges as [$start, $end]) {
        if (overlaps($start, $end, $from, $until)) {
            $available[] = [max($start, $from), min($end, $until)];
        }
    }
    return $available;
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
    $forecasts = $late = $withoutTime = $beyondHorizon = $near = $conflicts = [];
    $validTasks = $conflictingTasks = [];
    $unreserved = freeRanges(['windows' => $data['windows'], 'blocks' => []]);
    foreach ($data['blocks'] as $block) {
        if ($block['end_at'] <= $now) {
            continue;
        }
        if (!intervalFits($unreserved, $block['start_at'], $block['end_at'])) {
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
            if (isset($data['routine_horizon']) && $task['due_at'] > $data['routine_horizon']) {
                $beyondHorizon[] = $task;
            } else {
                $withoutTime[] = $task;
            }
        } else {
            $projected = subtract($projected, ...$slot);
            if (allowedSuggestion($data, $key, $now)) {
                $forecasts[] = ['key' => $key, 'kind' => 'task', 'id' => $id, 'title' => $task['title'], 'start' => $slot[0],
                    'end' => $slot[1], 'reason' => 'Este intervalo comporta a tarefa e termina antes do prazo.'];
            }
        }
    }
    return compact('ranges', 'current', 'taskNow', 'forecasts', 'late', 'withoutTime', 'beyondHorizon', 'near', 'conflicts')
        + ['unallocated' => $projected];
}

function leisureOptions(array $data, DateTimeImmutable $clock, ?array $current): array
{
    if (!$current) {
        return [null, null];
    }
    [$start, $end] = $current;
    $date = at($start);
    $week = $date->modify('monday this week')->format('Y-m-d');
    $day = $date->format('Y-m-d');
    $now = timeKey($clock);
    $reading = $gaming = 0;
    $busyDays = [];
    foreach ($data['activities'] as $entry) {
        if ($entry['log_day'] > $day) {
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
    $reserved = [];
    $dayEnd = timeKey($date->setTime(0, 0)->modify('+1 day'));
    foreach ($data['blocks'] as $block) {
        if (overlaps($block['start_at'], $block['end_at'], $day . ' 00:00', $dayEnd)) {
            $reserved[$block['kind'] . ':' . $block['item_id']] = true;
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
        if ($item['progress'] === 'done' || $finish > $end || isset($reserved[$key]) || !allowedSuggestion($data, $key, $now)
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
    $restMinutes = min((int) $goals['rest_minutes'], duration($start, $end));
    $rest = null;
    if ($restMinutes >= MIN_REST_MINUTES && !isset($reserved['rest:0']) && allowedSuggestion($data, 'rest:0', $now)) {
        $streak = 0;
        $activityDate = isset($busyDays[$day]) ? $date : $date->modify('-1 day');
        while (isset($busyDays[$activityDate->format('Y-m-d')])) {
            $streak++;
            $activityDate = $activityDate->modify('-1 day');
        }
        $rest = ['key' => 'rest:0', 'kind' => 'rest', 'id' => 0, 'title' => 'Reservar uma pausa',
            'start' => $start, 'end' => addMinutes($start, $restMinutes), 'minutes' => $restMinutes,
            'reason' => $restMinutes < (int) $goals['rest_minutes']
                ? "Uma pausa de $restMinutes min cabe neste intervalo livre." : "Há tempo para uma pausa de $restMinutes min.",
            'goalReached' => $goals['busy_days'] > 0 && $streak >= $goals['busy_days']];
        if ($rest['goalReached']) {
            $rest['reason'] = "Você registrou $streak dias seguidos com trabalho ou estudo. Uma pausa de $restMinutes min cabe neste intervalo livre.";
        }
    }
    return [$leisure, $rest];
}

function upcomingLeisure(array $data, DateTimeImmutable $clock, array $ranges): array
{
    $leisure = $rest = null;
    foreach ($ranges as $range) {
        [$interestInSlot, $restInSlot] = leisureOptions($data, $clock, $range);
        $leisure ??= $interestInSlot;
        $rest ??= $restInSlot;
        if ($leisure && $rest) {
            break;
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
