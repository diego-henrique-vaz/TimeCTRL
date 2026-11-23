<?php
declare(strict_types=1);

require __DIR__ . '/../app/planner.php';
require __DIR__ . '/../app/ai.php';

function checkAi(bool $passed, string $message): void
{
    if (!$passed) {
        throw new RuntimeException($message);
    }
    echo "OK $message\n";
}

function sequence(array $review): array
{
    return array_map(static fn(array $item): array =>
        [$item['kind'], substr($item['start'], 11), substr($item['end'], 11), $item['scheduled']], $review['items']);
}

$clock = at('2026-11-23 15:00');
$data = [
    'tasks' => [
        ['id' => 47, 'title' => 'Relatório de física', 'due_at' => '2026-11-23 16:15', 'minutes' => 60, 'priority' => 3, 'done' => 0],
        ['id' => 15, 'title' => 'Apresentação de história', 'due_at' => '2026-11-24 17:00', 'minutes' => 45, 'priority' => 2, 'done' => 0],
        ['id' => 10, 'title' => 'Tarefa concluída', 'due_at' => '2026-11-23 15:30', 'minutes' => 15, 'priority' => 3, 'done' => 1],
    ],
    'windows' => [['kind' => 'free', 'title' => 'Intervalo privado', 'start_at' => '2026-11-23 15:00', 'end_at' => '2026-11-23 17:00']],
    'interests' => [
        ['id' => 7, 'category' => 'book', 'title' => 'O Hobbit', 'minutes' => 15, 'progress' => 'ongoing', 'session_note' => 'Ler um capítulo'],
        ['id' => 8, 'category' => 'game', 'title' => 'Stardew Valley', 'minutes' => 30, 'progress' => 'want', 'session_note' => ''],
    ],
    'goals' => ['reading_sessions' => 1, 'busy_days' => 0, 'rest_minutes' => 25, 'gaming_minutes' => 0],
    'activities' => [], 'blocks' => [], 'feedback' => [], 'choice' => ['mode' => 'balance'],
];
$unsupported = static fn() => throw new RuntimeException('O modo local não deve chamar o transporte HTTP.');
$local = static fn(array $data): array => generateAiReview(['provider' => 'local'], $data, plan($data, $clock), $clock, $clock, $unsupported);
$expected = [['task', '15:00', '16:00', false], ['rest', '16:00', '16:15', false], ['task', '16:15', '17:00', false]];
$review = $local($data);
checkAi(sequence($review) === $expected, 'encaixa 60 min de tarefa, 15 min de pausa e 45 min de tarefa');
checkAi($review['items'][1]['alternative']['title'] === 'O Hobbit', 'propõe somente o interesse que cabe na pausa');

$fixed = $data;
$fixed['blocks'][] = ['id' => 9, 'kind' => 'task', 'item_id' => 15, 'title' => 'Apresentação de história',
    'start_at' => '2026-11-23 16:15', 'end_at' => '2026-11-23 17:00'];
$expected[2][3] = true;
checkAi(sequence($local($fixed)) === $expected, 'mantém os horários da tarefa que já está na agenda');

$interrupted = $fixed;
$interrupted['windows'][] = ['kind' => 'busy', 'title' => 'Reunião', 'start_at' => '2026-11-23 16:07', 'end_at' => '2026-11-23 16:15'];
$expected[1][2] = '16:07';
$shortened = $local($interrupted);
checkAi(sequence($shortened) === $expected && $shortened['items'][1]['alternative'] === null,
    'encurta a pausa quando há reunião e deixa o livro de fora se a leitura não cabe');

$rest = $fixed;
$rest['choice']['mode'] = 'rest';
$restPlan = $local($rest);
$expectedRest = [['rest', '15:00', '15:25', false], ['task', '16:15', '17:00', true]];
checkAi(sequence($restPlan) === $expectedRest, 'foco descanso preserva o que já está agendado e não propõe novas tarefas');
$rest['windows'][0]['start_at'] = '2026-11-23 16:15';
$rest['windows'][0]['end_at'] = '2026-11-23 17:30';
$expectedRest = [['task', '16:15', '17:00', true], ['rest', '17:00', '17:25', false]];
checkAi(sequence($local($rest)) === $expectedRest, 'foco descanso reserva a duração preferida quando cabe');

$full = $data;
$full['windows'][0]['end_at'] = '2026-11-23 16:45';
$expectedFull = [['task', '15:00', '16:00', false], ['task', '16:00', '16:45', false]];
checkAi(sequence($local($full)) === $expectedFull, 'mantém as entregas que cabem quando não há espaço para pausa');

$deadline = $data;
$deadline['tasks'][0]['due_at'] = '2026-11-23 15:55';
$review = $local($deadline);
checkAi($review['items'][0]['id'] === 15 && $review['unplaced'] === 1, 'tarefa sem tempo até o prazo fica fora da sequência');
$future = $fixed;
$future['blocks'][0]['start_at'] = '2026-11-24 16:00';
$future['blocks'][0]['end_at'] = '2026-11-24 16:45';
checkAi(!in_array(15, array_column($local($future)['items'], 'id'), true), 'horário já reservado para amanhã não vira uma sugestão hoje');

$search = $data;
$search['tasks'][0]['due_at'] = '2026-11-23 18:00';
$search['tasks'][1]['due_at'] = '2026-11-23 17:15';
$search['windows'][0]['end_at'] = '2026-11-23 16:00';
$search['windows'][] = ['kind' => 'free', 'title' => '', 'start_at' => '2026-11-23 16:30', 'end_at' => '2026-11-23 17:15'];
$searchPlan = $local($search);
checkAi($searchPlan['unplaced'] === 0 && $searchPlan['items'][0]['id'] === 47 && end($searchPlan['items'])['id'] === 15,
    'procura uma combinação que acomoda uma tarefa curta e outra com janela mais restrita');

$response = static fn(array $order, string $finish = 'stop'): array => ['choices' => [[
    'finish_reason' => $finish, 'message' => ['content' => json_encode(['order' => $order])],
]]];
$config = ['provider' => 'openrouter', 'key' => 'teste-local', 'model' => 'openai/gpt-4.1-mini'];
$remote = static fn(callable $completion): array => generateAiReview($config, $data, plan($data, $clock), $clock, $clock, $completion);
$review = $remote(static function (string $key, array $payload) use ($response): array {
    checkAi($key === 'teste-local' && $payload['provider']['zdr'] && $payload['response_format']['json_schema']['strict'],
        'requisição usa rota restrita e resposta estruturada');
    $context = json_decode($payload['messages'][1]['content'], true);
    checkAi($context['remaining_windows_minutes'] === [120] && array_column($context['tasks'], 'ref') === ['T1', 'T2']
        && array_keys($context['tasks'][0]) === ['ref', 'deadline', 'minutes', 'priority'],
        'API recebe dados de planejamento, sem títulos de tarefas ou compromissos');
    return $response(['T1', 'T2']);
});
checkAi(sequence($review) === sequence($local($data)), 'ordem do OpenRouter passa pelo mesmo cálculo de horários');

foreach ([
    'referência inventada' => $response(['T1', 'T7']),
    'referência repetida' => $response(['T1', 'T1']),
    'resposta incompleta' => $response(['T1', 'T2'], 'length'),
    'referência faltando' => $response(['T1']),
    'tipo errado' => $response(['T1', ['T2']]),
] as $label => $candidate) {
    $rejected = false;
    try {
        $remote(static fn(): array => $candidate);
    } catch (DomainException) {
        $rejected = true;
    }
    checkAi($rejected, "$label é recusada");
}
