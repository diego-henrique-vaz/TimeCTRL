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

$clock = at('2026-11-23 15:00');
$data = [
    'tasks' => [
        ['id' => 47, 'title' => 'Relatório de física', 'due_at' => '2026-11-23 16:00', 'minutes' => 30, 'priority' => 3, 'done' => 0],
        ['id' => 15, 'title' => 'Apresentação de história', 'due_at' => '2026-11-25 17:00', 'minutes' => 60, 'priority' => 2, 'done' => 0],
        ['id' => 10, 'title' => 'Tarefa concluída', 'due_at' => '2026-11-23 14:00', 'minutes' => 15, 'priority' => 3, 'done' => 1],
    ],
    'windows' => [['kind' => 'free', 'title' => 'Intervalo privado', 'start_at' => '2026-11-23 15:00', 'end_at' => '2026-11-23 16:00']],
    'blocks' => [], 'feedback' => [], 'choice' => ['mode' => 'balance'],
];
[$context, $refs] = aiContext($data, plan($data, $clock), $clock, $clock);
checkAi($refs === ['T1' => 47, 'T2' => 15] && $context['remaining_windows_minutes'] === [60], 'candidatas pendentes e disponibilidade agregada');
checkAi(!str_contains(json_encode($context, JSON_UNESCAPED_UNICODE), 'Intervalo privado') &&
    $context['tasks'][0]['proposed_on_selected_date'] && $context['tasks'][1]['no_slot_before_deadline'], 'contexto omite detalhes de agenda e inclui o risco real');

$response = static fn(array $items, string $finish = 'stop'): array => ['choices' => [[
    'finish_reason' => $finish, 'message' => ['content' => json_encode(['summary' => 'Comece pela entrega mais próxima.', 'items' => $items])],
]]];
$result = ['ref' => 'T1', 'reason' => 'Entrega mais próxima.', 'first_step' => 'Liste as medições do experimento.'];
$config = ['key' => 'teste-local', 'model' => 'openai/gpt-4.1-mini'];
$review = generateAiReview($config, $context, $refs, static function (string $key, array $payload) use ($response, $result, $context): array {
    checkAi($key === 'teste-local' && $payload['provider']['zdr'] && $payload['provider']['require_parameters'], 'requisição usa a configuração e a rota restrita');
    checkAi($payload['response_format']['json_schema']['strict'] && json_decode($payload['messages'][1]['content'], true) === $context,
        'requisição usa resposta estruturada e carrega apenas o contexto escolhido');
    return $response([$result]);
});
checkAi($review['items'] === [['id' => 47, 'reason' => $result['reason'], 'step' => $result['first_step']]], 'resposta simulada associa a tarefa e o primeiro passo');

foreach ([
    'referência inventada' => $response([array_replace($result, ['ref' => 'T7'])]),
    'referência repetida' => $response([$result, $result]),
    'resposta incompleta' => $response([$result], 'length'),
    'resposta fora do contrato' => $response(['tarefa']),
] as $label => $candidate) {
    $rejected = false;
    try {
        generateAiReview($config, $context, $refs, static fn(): array => $candidate);
    } catch (DomainException) {
        $rejected = true;
    }
    checkAi($rejected, "$label é recusada");
}
