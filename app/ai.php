<?php
declare(strict_types=1);

require_once __DIR__ . '/ai_local.php';

const AI_MAX_TASKS = 6;
const AI_PLAN_VERSION = 2;

function aiConfiguration(): ?array
{
    $file = dirname(__DIR__) . '/ai.local.php';
    try {
        $settings = is_file($file) ? require $file : [];
        $key = trim(getenv('TIMECTRL_OPENROUTER_KEY') ?: ($settings['key'] ?? ''));
        $provider = trim(getenv('TIMECTRL_AI_PROVIDER') ?: ($settings['provider'] ?? ($key !== '' ? 'openrouter' : 'local')));
        $model = trim(getenv('TIMECTRL_OPENROUTER_MODEL') ?: ($settings['model'] ?? 'openai/gpt-4.1-mini'));
    } catch (Throwable) {
        error_log('TimeCTRL IA: configuração local inválida');
        return null;
    }
    if ($provider === 'local') {
        return ['provider' => 'local'];
    }
    if ($provider !== 'openrouter' || $key === '' || preg_match('/\s/', $key) || !preg_match('~^[\w.-]+/[\w.:-]+$~D', $model) || !extension_loaded('curl')) {
        return null;
    }
    return ['provider' => 'openrouter', 'key' => $key, 'model' => $model];
}

function generateAiReview(
    array $config, array $data, array $plan, DateTimeImmutable $focus, DateTimeImmutable $clock,
    ?callable $completion = null
): array
{
    $day = $focus->format('Y-m-d');
    $now = timeKey($clock);
    $start = max($day . ' 00:00', $now);
    $end = timeKey(at($day)->modify('+1 day'));
    $ranges = rangesOnDay($plan['ranges'], $day, $clock);
    $conflicts = array_fill_keys(array_column($plan['conflicts'], 'id'), true);
    $fixed = $reservedTasks = [];
    foreach ($data['blocks'] as $block) {
        if ($block['end_at'] <= $now) {
            continue;
        }
        if ($block['kind'] === 'task') {
            $reservedTasks[(int) $block['item_id']] = true;
        }
        if (overlaps($block['start_at'], $block['end_at'], $start, $end) && !isset($conflicts[$block['id']])) {
            $fixed[] = ['kind' => $block['kind'], 'id' => (int) $block['item_id'], 'title' => $block['title'],
                'start' => $block['start_at'], 'end' => $block['end_at'], 'scheduled' => true,
                'minutes' => duration($block['start_at'], $block['end_at'])];
        }
    }
    $tasks = $metadata = [];
    $unplaced = 0;
    $mode = $data['choice']['mode'];
    foreach (orderedTasks($data['tasks']) as $task) {
        $id = (int) $task['id'];
        if (in_array($mode, ['rest', 'leisure'], true) || $task['done'] || isset($reservedTasks[$id])
            || !allowedSuggestion($data, "task:$id", $now)) {
            continue;
        }
        $unplaced++;
        $ref = 'T' . (count($tasks) + 1);
        $candidate = ['ref' => $ref, 'deadline' => $task['due_at'],
            'minutes' => (int) $task['minutes'], 'priority' => PRIORITIES[$task['priority']]];
        if (count($tasks) < AI_MAX_TASKS && aiTaskPlacement($candidate, $ranges) !== null) {
            $tasks[$ref] = $candidate;
            $metadata[$ref] = ['kind' => 'task', 'id' => $id, 'title' => $task['title'], 'scheduled' => false];
        }
    }
    if (($config['provider'] ?? '') === 'openrouter' && count($tasks) > 1) {
        $context = ['date' => $day, 'focus' => MODES[$mode], 'tasks' => array_values($tasks),
            'remaining_windows_minutes' => array_map(static fn(array $slot): int => duration(...$slot), $ranges)];
        $order = openRouterTaskOrder($config, $context, array_keys($tasks), $completion ?? openRouterCompletion(...));
        $tasks = array_replace(array_fill_keys($order, []), $tasks);
    }
    $proposed = searchAiTaskSlots($tasks, $ranges);
    foreach ($proposed as &$item) {
        $item += $metadata[$item['ref']];
    }
    unset($item);
    return ['items' => aiTimeline($data, [...$fixed, ...$proposed], $ranges, $clock), 'unplaced' => $unplaced - count($proposed)];
}

function openRouterCompletion(string $key, array $payload): array
{
    $curl = curl_init('https://openrouter.ai/api/v1/chat/completions');
    $body = '';
    curl_setopt_array($curl, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 18,
        CURLOPT_WRITEFUNCTION => static function ($curl, string $part) use (&$body): int {
            if (strlen($body) + strlen($part) > 65536) {
                return 0;
            }
            $body .= $part;
            return strlen($part);
        },
    ]);
    $received = curl_exec($curl);
    $http = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $transportError = curl_errno($curl);
    curl_close($curl);

    if (!$received || $http !== 200) {
        error_log("TimeCTRL IA: HTTP $http; cURL $transportError");
        throw new DomainException(match ($http) {
            401, 403 => 'O OpenRouter recusou o acesso. Confira a chave e as permissões dela.',
            402 => 'O OpenRouter não tem saldo disponível para essa geração.',
            429 => 'O OpenRouter atingiu o limite de solicitações. Tente mais tarde.',
            default => 'A IA está indisponível no momento. Os encaixes da agenda seguem disponíveis.',
        });
    }
    try {
        $result = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        $result = null;
    }
    if (!is_array($result)) {
        throw new DomainException('A IA enviou uma resposta inválida. Tente gerar o plano novamente.');
    }
    return $result;
}

function openRouterTaskOrder(array $config, array $context, array $refs, callable $completion): array
{
    $response = $completion($config['key'], [
        'model' => $config['model'],
        'max_tokens' => 250,
        'temperature' => 0.2,
        'provider' => ['require_parameters' => true, 'data_collection' => 'deny', 'zdr' => true],
        'messages' => [
            ['role' => 'system', 'content' => 'Ordene todas as referências das tarefas por urgência, prioridade e duração, considerando os minutos livres. Devolva todas as referências fornecidas, cada uma uma única vez. A agenda do TimeCTRL calcula a sequência de horários e pausas.'],
            ['role' => 'user', 'content' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        ],
        'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'timectrl_task_order', 'strict' => true,
            'schema' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['order'],
                'properties' => [
                    'order' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $refs]],
                ],
            ],
        ]],
    ]);

    $invalid = 'A IA não conseguiu montar um plano válido com estas tarefas. Tente novamente.';
    $choice = $response['choices'][0] ?? [];
    $content = $choice['message']['content'] ?? null;
    if (!is_string($content) || ($choice['finish_reason'] ?? '') !== 'stop' || !empty($choice['message']['refusal'])) {
        throw new DomainException($invalid);
    }
    try {
        $parsed = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new DomainException($invalid);
    }
    $order = $parsed['order'] ?? null;
    if (!is_array($order) || !array_is_list($order) || count($order) !== count($refs)) {
        throw new DomainException($invalid);
    }
    $remaining = array_fill_keys($refs, true);
    foreach ($order as $ref) {
        if (!is_string($ref) || !isset($remaining[$ref])) {
            throw new DomainException($invalid);
        }
        unset($remaining[$ref]);
    }
    return $order;
}
