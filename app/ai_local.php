<?php
declare(strict_types=1);

const LOCAL_AI_EXAMPLES = [
    'estudo' => [
        'revisar capítulos para a prova', 'resolver exercícios de cálculo', 'estudar matéria para avaliação',
        'praticar questões do simulado', 'reler anotações da aula', 'responder questionário de revisão',
        'estudar conteúdos do exame', 'refazer lista de exercícios',
    ],
    'texto' => [
        'escrever relatório do estágio', 'rascunhar introdução do artigo', 'redigir conclusão da pesquisa',
        'revisar texto do trabalho', 'organizar referências bibliográficas', 'produzir resumo escrito',
        'documentar resultados no relatório', 'editar redação e bibliografia',
    ],
    'apresentacao' => [
        'montar slides do seminário', 'preparar apresentação oral', 'ensaiar fala da apresentação',
        'fazer roteiro dos slides', 'treinar explicação para a banca', 'criar apresentação no powerpoint',
        'ajustar slides e roteiro do seminário', 'ensaiar discurso e demonstração',
    ],
    'tecnica' => [
        'programar tela do sistema', 'corrigir erro no código', 'criar consulta SQL no banco de dados',
        'implementar cadastro de usuários', 'rodar testes automatizados', 'configurar projeto no git',
        'desenvolver função da API', 'investigar bug no formulário',
    ],
    'organizacao' => [
        'separar documentos para matrícula', 'preencher formulário de inscrição', 'organizar arquivos da pasta',
        'agendar consulta e confirmar horário', 'separar materiais para a reunião', 'enviar comprovante de pagamento',
        'atualizar planilha de controle', 'conferir documentos e anexos da inscrição',
    ],
];

const LOCAL_AI_STEPS = [
    'estudo' => ['Estudo', 'Escolha um tópico e tente responder uma questão sem consultar o material. Depois confira sua resposta.'],
    'texto' => ['Texto e relatório', 'Abra o documento e escreva três pontos que precisam aparecer. Comece pelo mais fácil de explicar.'],
    'apresentacao' => ['Apresentação', 'Escreva a ideia principal da apresentação e separe três tópicos para os slides ou para a fala.'],
    'tecnica' => ['Implementação técnica', 'Anote o resultado que você espera e teste um caso pequeno antes de mudar o projeto.'],
    'organizacao' => ['Organização', 'Liste os materiais ou informações necessários e reúna o primeiro item que estiver disponível.'],
];

function localAiWords(string $text): array
{
    $text = strtr(mb_strtolower($text, 'UTF-8'), ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e',
        'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
    return preg_split('/[^a-z]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
}

function localAiStatistics(): array
{
    static $stats;
    if ($stats !== null) {
        return $stats;
    }
    $stats = ['words' => [], 'counts' => [], 'totals' => []];
    $commonWords = ['de', 'do', 'da', 'dos', 'das', 'no', 'na', 'e', 'o', 'a', 'para', 'um', 'uma', 'fazer', 'com'];
    foreach (LOCAL_AI_EXAMPLES as $category => $titles) {
        $tokens = array_values(array_diff(localAiWords(implode(' ', $titles)), $commonWords));
        $stats['counts'][$category] = array_count_values($tokens);
        $stats['totals'][$category] = count($tokens);
        $stats['words'] += $stats['counts'][$category];
    }
    return $stats;
}

function classifyLocalAiTask(string $title): ?string
{
    $stats = localAiStatistics();
    $terms = array_intersect_key(array_count_values(localAiWords($title)), $stats['words']);
    if (!$terms) {
        return null;
    }
    $scores = [];
    foreach ($stats['counts'] as $category => $counts) {
        $denominator = $stats['totals'][$category] + count($stats['words']);
        $scores[$category] = 0.0;
        foreach ($terms as $token => $count) {
            $scores[$category] += $count * log((($counts[$token] ?? 0) + 1) / $denominator);
        }
    }
    arsort($scores, SORT_NUMERIC);
    $ranked = array_values($scores);
    return $ranked[0] - $ranked[1] >= 0.2 ? array_key_first($scores) : null;
}

function generateLocalAiReview(array $context, array $taskIds): array
{
    $tasks = $context['tasks'];
    $priority = array_flip(PRIORITIES);
    usort($tasks, static fn(array $a, array $b): int =>
        [!$a['overdue'], $a['deadline'], -$priority[$a['priority']], !$a['proposed_on_selected_date'], $a['minutes'], $a['ref']]
            <=> [!$b['overdue'], $b['deadline'], -$priority[$b['priority']], !$b['proposed_on_selected_date'], $b['minutes'], $b['ref']]);
    $items = [];
    foreach (array_slice($tasks, 0, AI_MAX_RECOMMENDATIONS) as $task) {
        $category = classifyLocalAiTask($task['title']);
        [$label, $step] = LOCAL_AI_STEPS[$category] ?? ['Geral', 'Separe uma parte pequena da tarefa e defina o que precisa ficar pronto primeiro.'];
        $reason = match (true) {
            $task['overdue'] => 'O prazo já passou. Revise o que falta para concluir.',
            $task['no_slot_before_deadline'] => 'A agenda sinalizou falta de tempo antes do prazo.',
            $task['proposed_on_selected_date'] => 'A agenda encontrou um encaixe na data escolhida.',
            default => 'A ordem considera a proximidade do prazo e a prioridade cadastrada.',
        };
        $items[] = ['id' => $taskIds[$task['ref']], 'reason' => $reason, 'step' => $step, 'category' => $label];
    }
    return ['summary' => 'Organizamos as tarefas por prazo e prioridade. Veja um primeiro passo para começar.', 'items' => $items];
}
