<?php
declare(strict_types=1);

function h(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fields(string $action): void
{
    echo '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
    echo '<input type="hidden" name="action" value="' . h($action) . '">';
}

function stamp(string $value): string
{
    return at($value)->format('d/m/Y \à\s H:i');
}

function inputStamp(string $value): string
{
    return str_replace(' ', 'T', $value);
}

function agendaDate(string $day): string
{
    $date = at($day);
    $names = [1 => 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'];
    return $names[(int) $date->format('N')] . ', ' . $date->format('d/m/Y');
}

function choices(array $options, string|int $value): void
{
    foreach ($options as $key => $label) {
        echo '<option value="' . h($key) . '"' . ((string) $key === (string) $value ? ' selected' : '') . '>' . h($label) . '</option>';
    }
}

function weeklyFields(array $rule): void
{
    ?>
    <div class="form-grid">
        <label>Tipo<select name="kind"><?php choices(WINDOW_TYPES, $rule['kind']); ?></select></label>
        <label>Nome<input name="title" maxlength="120" value="<?= h($rule['title']) ?>" placeholder="Ex.: Trabalho"></label>
        <label>A partir de<input type="date" name="first_day" required value="<?= h($rule['first_day']) ?>"></label>
        <label>Início<input type="time" name="start_time" required value="<?= h($rule['start_time']) ?>"></label>
        <label>Fim<input type="time" name="end_time" required value="<?= h($rule['end_time']) ?>"></label>
    </div>
    <fieldset class="weekdays">
        <legend>Dias da semana</legend>
        <?php foreach (WEEKDAYS as $number => $name): ?>
            <label><input type="checkbox" name="weekdays[]" value="<?= $number ?>" <?= ($rule['weekdays'] & (1 << ($number - 1))) ? 'checked' : '' ?>><?= h($name) ?></label>
        <?php endforeach; ?>
    </fieldset>
    <?php
}

function proposalForm(array $item, string $view, array $dayArg): void
{
    ?>
    <form class="actions" method="post" action="<?= h(url($view, $dayArg)) ?>">
        <?php fields('suggestion'); ?>
        <input type="hidden" name="kind" value="<?= h($item['kind']) ?>">
        <input type="hidden" name="item" value="<?= h($item['id']) ?>">
        <input type="hidden" name="start_at" value="<?= h($item['start']) ?>">
        <?php if ($item['kind'] === 'rest'): ?><input type="hidden" name="rest_minutes" value="<?= h($item['minutes']) ?>"><?php endif; ?>
        <button name="decision" value="accept">Aceitar horário</button>
        <button class="subtle" name="decision" value="later">Adiar</button>
        <button class="quiet" name="decision" value="dismiss">Dispensar</button>
    </form>
    <?php
}

function completionForm(string $action, int $id, int $minutes, string $today, string $view, array $dayArg): void
{
    ?>
    <form class="stack completion-form" method="post" action="<?= h(url($view, $dayArg)) ?>">
        <?php fields($action); ?><input type="hidden" name="id" value="<?= h($id) ?>">
        <label>Tempo gasto (min)<input type="number" name="minutes" min="1" max="1440" required value="<?= h($minutes) ?>"></label>
        <details><summary>Registrar em outra data</summary>
            <label>Data da atividade<input type="date" name="log_day" max="<?= h($today) ?>" value="<?= h($today) ?>"></label>
        </details>
        <button class="subtle"><?= $action === 'done-task' ? 'Concluir tarefa' : 'Marcar como feito' ?></button>
    </form>
    <?php
}

$nav = ['today' => 'Meu dia', 'tasks' => 'Tarefas', 'agenda' => 'Agenda', 'interests' => 'Interesses', 'habits' => 'Hábitos'];
$headings = $nav + ['login' => 'Acesse sua agenda', 'signup' => 'Crie sua conta'];
$dayArg = $day === $today ? [] : ['date' => $day];
$startDefault = inputStamp($day === $today ? timeKey($clock) : $day . ' 18:00');
$endDefault = inputStamp(addMinutes(str_replace('T',' ', $startDefault), 30));
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= h($headings[$view]) ?> · TimeCTRL</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="<?= $account ? 'app-shell' : 'auth-shell' ?>">
<?php if (!$account): ?>
<main class="auth-card">
    <div class="brand"><span class="mark" aria-hidden="true">T</span>TimeCTRL</div>
    <h1><?= h($headings[$view]) ?></h1>
    <?php if ($error): ?><p class="flash warning" role="alert"><?= h($error) ?></p><?php endif; ?>
    <form method="post" action="<?= h(url($view)) ?>" class="stack">
        <?php fields($view === 'signup' ? 'signup' : 'login'); ?>
        <?php if ($view === 'signup'): ?><label>Seu nome<input name="name" required maxlength="80" autocomplete="name" value="<?= h(field('name')) ?>"></label><?php endif; ?>
        <label>E-mail<input type="email" name="email" required maxlength="190" autocomplete="username" value="<?= h(field('email')) ?>"></label>
        <label>Senha<input type="password" name="password" required <?= $view === 'signup' ? 'minlength="8" autocomplete="new-password"' : 'autocomplete="current-password"' ?>></label>
        <button><?= $view === 'signup' ? 'Criar conta' : 'Entrar' ?></button>
    </form>
    <p><a href="<?= h(url($view === 'signup' ? 'login' : 'signup')) ?>"><?= $view === 'signup' ? 'Já tenho uma conta' : 'Criar uma conta' ?></a></p>
</main>
<?php else: ?>
<aside class="sidebar">
    <a href="<?= h(url()) ?>" class="brand"><span class="mark" aria-hidden="true">T</span>TimeCTRL</a>
    <nav aria-label="Principal">
        <?php foreach ($nav as $route => $name): ?>
            <a <?= $route === $view ? 'aria-current="page"' : '' ?> href="<?= h(url($route)) ?>"><?= h($name) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="account" method="post" action="<?= h(url()) ?>">
        <?php fields('logout'); ?><span><?= h($account['name']) ?></span><button class="quiet">Sair</button>
    </form>
</aside>
<main class="workspace">
    <header class="page-header">
        <h1><?= h($headings[$view]) ?></h1>
        <?php if (in_array($view, ['today', 'agenda'], true)): ?>
        <div class="actions" aria-label="Navegação por data">
            <a class="subtle link-button" href="<?= h(url($view)) ?>" <?= $day === $today ? 'aria-current="date"' : '' ?>>Hoje</a>
            <?php if ($day > $today): ?>
                <a class="subtle link-button" href="<?= h(url($view, ['date' => at($day)->modify('-1 day')->format('Y-m-d')])) ?>" aria-label="Dia anterior">‹</a>
            <?php endif; ?>
            <form method="get" action="/" class="date-picker">
                <input type="hidden" name="view" value="<?= h($view) ?>">
                <label><span class="sr">Data da agenda</span><input id="agenda-date" name="date" type="date" min="<?= h($today) ?>" value="<?= h($day) ?>"></label>
                <button class="subtle">Ver dia</button>
            </form>
            <a class="subtle link-button" href="<?= h(url($view, ['date' => at($day)->modify('+1 day')->format('Y-m-d')])) ?>" aria-label="Próximo dia">›</a>
        </div>
        <?php endif; ?>
    </header>
    <?php if (in_array($view, ['today', 'agenda'], true)): ?>
        <p class="view-date<?= $day === $today ? '' : ' view-date-future' ?>" data-view-date>
            <strong><?= $day === $today ? 'Hoje' : 'Data selecionada' ?></strong> · <?= h(agendaDate($day)) ?>
        </p>
    <?php endif; ?>
    <?php if ($notice): ?><p class="flash" role="status"><?= h($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="flash warning" role="alert"><?= h($error) ?></p><?php endif; ?>

    <?php if ($view === 'today'): ?>
        <?php if ($holiday): ?>
            <section class="holiday" aria-label="Feriado nacional">
                <strong><?= h($holiday) ?></strong><span>Feriado nacional · <?= h(at($day)->format('d/m/Y')) ?></span>
                <p><?= $data['choice']['routine'] === 'works' ? 'Sua agenda de trabalho foi mantida.' : 'Se a rotina mudar, ajuste os intervalos na agenda.' ?></p>
            </section>
        <?php endif; ?>
        <div class="today-grid">
            <section class="panel lead">
                <h2><?= $day === $today ? 'O que fazer agora?' : 'Planejamento para ' . h(at($day)->format('d/m')) ?></h2>
                <?php if ($summary['current'] && $day === $today): ?>
                    <p class="metric" data-free-minutes><?= duration(...$summary['current']) ?><span> min livres agora</span></p>
                    <p>Até <?= h(at($summary['current'][1])->format('H:i')) ?>.</p>
                <?php elseif ($dayRanges): ?>
                    <p>Próximo horário livre <?= $day === $today ? 'de hoje' : 'neste dia' ?>:</p>
                    <p class="info-line" data-next-free><?= h(substr($dayRanges[0][0], 11)) ?>–<?= h(substr($dayRanges[0][1], 11)) ?> · <?= duration(...$dayRanges[0]) ?> min</p>
                <?php else: ?>
                    <p>Nenhum intervalo livre restante nesta data. <a href="<?= h(url('agenda', $dayArg)) ?>">Ver agenda</a></p>
                <?php endif; ?>
                <?php if ($suggestion): ?>
                    <div class="recommendation" data-recommendation="<?= h($suggestion['kind']) ?>">
                        <h3><?= h($suggestion['title']) ?></h3>
                        <p><?= h($suggestion['reason']) ?></p>
                        <p class="time"><?= h(at($suggestion['start'])->format('H:i')) ?>–<?= h(at($suggestion['end'])->format('H:i')) ?></p>
                        <?php proposalForm($suggestion, $view, $dayArg); ?>
                    </div>
                <?php elseif ($dayRanges && ($data['choice']['mode'] !== 'balance' || (!$leisureOption && !$restOption))): ?>
                    <p class="recommendation">Nenhuma sugestão se encaixa no tempo disponível para esse foco.</p>
                <?php endif; ?>
            </section>
            <section class="panel">
                <details <?= !$data['choice']['prompt_hidden'] ? 'open' : '' ?>>
                    <summary>Foco do dia</summary>
                    <form class="stack" method="post" action="<?= h(url($view, $dayArg)) ?>">
                        <?php fields('save-choice'); ?>
                        <label>Como quer usar seu tempo livre?
                            <select name="mode"><?php choices(MODES, $data['choice']['mode']); ?></select>
                        </label>
                        <?php if ($holiday): ?><label>Sua rotina mudou?
                            <select name="routine"><?php choices(ROUTINES, $data['choice']['routine']); ?></select>
                        </label><?php else: ?><input type="hidden" name="routine" value="usual"><?php endif; ?>
                        <button>Salvar foco</button>
                    </form>
                    <form method="post" action="<?= h(url($view, $dayArg)) ?>">
                        <?php fields('hide-choice'); ?><button class="quiet">Dispensar pergunta</button>
                    </form>
                </details>
                <?php if ($day === $today): ?>
                    <details class="entry">
                        <summary>Estou livre agora</summary>
                        <form method="post" class="stack" action="<?= h(url($view)) ?>">
                            <?php fields('free-now'); ?>
                            <label>Quanto tempo você tem? (min)<input type="number" name="minutes" min="5" max="240" required value="30"></label>
                            <p class="muted">Abre uma janela a partir de agora, inclusive durante o trabalho. Compromissos e blocos já aceitos continuam ocupando tempo.</p>
                            <button>Adicionar tempo livre</button>
                        </form>
                    </details>
                <?php endif; ?>
            </section>
        </div>
        <?php if ($scheduledTasks && $freeMinutes): ?>
            <section class="panel" data-remaining-time>
                <h2>Tempo livre depois dos blocos marcados</h2>
                <p><?= h($scheduledTasks) ?> <?= $scheduledTasks === 1 ? 'tarefa já marcada' : 'tarefas já marcadas' ?> · <?= h($freeMinutes) ?> min livres <?= $day === $today ? 'hoje' : 'neste dia' ?>.</p>
                <?php if ($data['choice']['mode'] === 'balance' && ($leisureOption || $restOption || $suggestion)): ?>
                    <p class="muted">Quer encaixar algum lazer ou uma pausa nesse tempo?</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php if ($leisureOption || $restOption): ?><div class="<?= $leisureOption && $restOption ? 'two-columns' : '' ?>"><?php endif; ?>
        <?php if ($leisureOption): ?>
            <section class="panel" data-leisure-option>
                <h2>Sugestão de lazer</h2>
                <div class="recommendation">
                    <h3><?= h($leisureOption['title']) ?></h3>
                    <p><?= h($leisureOption['reason']) ?></p>
                    <p><?= $leisureAlternative ? 'Este horário coincide com um encaixe sugerido para tarefa. Confira os prazos antes de reservar.'
                        : 'Este horário cabe junto com os encaixes sugeridos para as tarefas.' ?></p>
                    <p class="time"><?= h(stamp($leisureOption['start'])) ?>–<?= h(substr($leisureOption['end'], 11)) ?></p>
                    <?php proposalForm($leisureOption, $view, $dayArg); ?>
                </div>
            </section>
        <?php endif; ?>
        <?php if ($restOption): ?>
            <section class="panel" data-rest-option>
                <h2>Sugestão de descanso</h2>
                <div class="recommendation">
                    <h3>Pausa de <?= h($restOption['minutes']) ?> min</h3>
                    <p><?= h($restOption['reason']) ?></p>
                    <p class="time"><?= h(stamp($restOption['start'])) ?>–<?= h(substr($restOption['end'], 11)) ?></p>
                    <?php proposalForm($restOption, $view, $dayArg); ?>
                </div>
            </section>
        <?php endif; ?>
        <?php if ($leisureOption || $restOption): ?></div><?php endif; ?>
        <?php if ($dayRanges && !$data['interests'] && in_array($data['choice']['mode'], ['balance', 'leisure'], true)): ?>
            <p class="panel"><a href="<?= h(url('interests')) ?>">Cadastre um interesse</a> para receber sugestões de lazer.</p>
        <?php endif; ?>
        <section class="panel lists">
            <h2>Prazos e conflitos</h2>
            <?php if (!$summary['late'] && !$summary['withoutTime'] && !$summary['beyondHorizon'] && !$summary['conflicts'] && !$summary['near']): ?>
                <p class="muted">Nenhum prazo próximo ou conflito na agenda.</p>
            <?php endif; ?>
            <?php foreach ($summary['late'] as $task): ?>
                <p class="alert-line" data-alert="late"><strong>Prazo vencido.</strong> <?= h($task['title']) ?> · <?= h(stamp($task['due_at'])) ?></p>
            <?php endforeach; ?>
            <?php foreach ($summary['withoutTime'] as $task): ?>
                <p class="alert-line" data-alert="no-slot"><strong>Sem horário antes do prazo.</strong> <?= h($task['title']) ?> precisa de <?= h($task['minutes']) ?> min até <?= h(stamp($task['due_at'])) ?>.</p>
            <?php endforeach; ?>
            <?php foreach ($summary['conflicts'] as $block): ?>
                <p class="alert-line" data-alert="conflict"><strong>Conflito na agenda.</strong> <?= h($block['title']) ?> · <a href="<?= h(url('agenda', ['date' => max($today, substr($block['start_at'], 0, 10))])) ?>">Ajustar bloco</a></p>
            <?php endforeach; ?>
            <?php if ($summary['beyondHorizon']): ?>
                <p class="info-line">Sem encaixe nos próximos 90 dias para <?= h(implode(', ', array_column($summary['beyondHorizon'], 'title'))) ?>. O prazo dessas tarefas vai além desse período.</p>
            <?php endif; ?>
            <?php $without = array_column($summary['withoutTime'], 'id'); ?>
            <?php foreach ($summary['near'] as $task): if (in_array($task['id'], $without, true)) continue; ?>
                <p class="info-line" data-alert="near"><strong>Entrega próxima.</strong> <?= h($task['title']) ?> · <?= h(stamp($task['due_at'])) ?></p>
            <?php endforeach; ?>
        </section>
        <section class="panel lists">
            <h2>Encaixes sugeridos</h2>
            <?php $suggested = false; foreach ($summary['forecasts'] as $item):
                if ($suggestion && $item['key'] === $suggestion['key']) continue;
                $suggested = true;
                $existing = array_column(array_filter($data['blocks'], fn($b) => $b['kind'] === 'task' && $b['end_at'] > timeKey($clock)), null, 'item_id')[$item['id']] ?? null;
                ?>
                <article class="entry" data-forecast>
                    <div><h3><?= h($item['title']) ?></h3><p><?= h(stamp($item['start'])) ?>–<?= h(at($item['end'])->format('H:i')) ?></p></div>
                    <?php if ($existing): ?><a href="<?= h(url('agenda', ['date' => max($today, substr($existing['start_at'], 0, 10))])) ?>">Ajustar bloco existente</a><?php else: ?><?php proposalForm($item, $view, $dayArg); ?><?php endif; ?>
                </article>
            <?php endforeach; if (!$suggested): ?><p class="muted">Não há outros encaixes para mostrar.</p><?php endif; ?>
        </section>

        <section class="panel" id="plano-ia">
            <h2>Plano com IA</h2>
            <?php if (!$aiConfig): ?>
                <p>Confira a configuração de IA no README do projeto.</p>
            <?php elseif (!array_filter($data['tasks'], static fn(array $task): bool => !$task['done'])): ?>
                <p>Cadastre uma tarefa pendente para gerar o plano.</p>
            <?php else: ?>
                <form class="stack" method="post" action="<?= h(url('today', $dayArg)) ?>#plano-ia">
                    <?php fields('review-ai'); ?>
                    <p>Sugere o que priorizar e um primeiro passo para cada tarefa escolhida. Os horários vêm da agenda.</p>
                    <?php if (($aiConfig['provider'] ?? '') === 'local'): ?>
                        <p class="muted">IA local gratuita. O classificador identifica o tipo de tarefa a partir do título e usa o planejamento do dia.</p>
                    <?php else: ?>
                        <p class="muted">Ao gerar, o OpenRouter recebe títulos, prazos, duração e prioridade de até seis tarefas, o foco do dia e a duração das janelas livres.</p>
                    <?php endif; ?>
                    <button>Gerar plano com IA</button>
                </form>
                <?php if ($aiReview): ?>
                    <?php $byId = array_column($data['tasks'], null, 'id');
                    $localSlots = array_column($summary['forecasts'], null, 'id'); ?>
                    <div class="recommendation" data-ai-review>
                        <p><?= h($aiReview['summary']) ?></p>
                        <ol class="ai-steps">
                            <?php foreach ($aiReview['items'] as $item): ?>
                                <?php $task = $byId[$item['id']] ?? null; if (!$task || $task['done']) continue; ?>
                                <li><strong><?= h($task['title']) ?></strong>
                                    <?php if (isset($item['category'])): ?><span class="tag"><?= h($item['category']) ?></span><?php endif; ?>
                                    <p><?= h($item['reason']) ?></p>
                                    <p><strong>Primeiro passo</strong> · <?= h($item['step']) ?></p>
                                    <?php if (isset($localSlots[$item['id']])): ?>
                                        <p class="muted">Encaixe da agenda · <?= h(stamp($localSlots[$item['id']]['start'])) ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

    <?php elseif ($view === 'tasks'): ?>
        <section class="panel">
            <h2>Nova tarefa</h2>
            <form method="post" class="form-grid" action="<?= h(url($view, $dayArg)) ?>">
                <?php fields('save-task'); ?>
                <label>Título<input name="title" maxlength="120" required placeholder="Ex.: Entregar o relatório"></label>
                <label>Prazo<input type="datetime-local" name="due_at" required value="<?= h(inputStamp($day . ' 23:59')) ?>"></label>
                <label>Duração (min)<input type="number" name="minutes" min="1" max="720" required value="30"></label>
                <label>Prioridade<select name="priority"><?php choices(PRIORITIES, 2); ?></select></label>
                <button>Cadastrar tarefa</button>
            </form>
        </section>
        <section class="panel lists">
            <div class="section-heading"><h2>Pendentes</h2><div class="actions">
                <a class="subtle link-button" href="<?= h(url('tasks', $dayArg + ['sort' => 'due'])) ?>">Por prazo</a>
                <a class="subtle link-button" href="<?= h(url('tasks', $dayArg + ['sort' => 'priority'])) ?>">Por prioridade</a>
            </div></div>
            <?php $pending = orderedTasks(array_values(array_filter($data['tasks'], fn($t) => !$t['done'])), ($_GET['sort'] ?? '') === 'priority'); ?>
            <?php if (!$pending): ?><p class="muted">Nenhuma tarefa pendente.</p><?php endif; ?>
            <?php foreach ($pending as $task): ?>
                <details class="entry task" data-task="<?= h($task['id']) ?>">
                    <summary><strong><?= h($task['title']) ?></strong><span class="tag priority-<?= h($task['priority']) ?>"><?= h(PRIORITIES[$task['priority']]) ?></span>
                        <span><?= h(stamp($task['due_at'])) ?> · <?= h($task['minutes']) ?> min</span></summary>
                    <div class="stack inset">
                        <form method="post" class="form-grid" action="<?= h(url($view, $dayArg)) ?>">
                            <?php fields('save-task'); ?><input type="hidden" name="id" value="<?= h($task['id']) ?>">
                            <label>Título<input name="title" required maxlength="120" value="<?= h($task['title']) ?>"></label>
                            <label>Prazo<input type="datetime-local" name="due_at" required value="<?= h(inputStamp($task['due_at'])) ?>"></label>
                            <label>Duração (min)<input type="number" name="minutes" min="1" max="720" required value="<?= h($task['minutes']) ?>"></label>
                            <label>Prioridade<select name="priority"><?php choices(PRIORITIES, $task['priority']); ?></select></label><button>Salvar edição</button>
                        </form>
                        <?php completionForm('done-task', (int) $task['id'], (int) $task['minutes'], $today, $view, $dayArg); ?>
                    </div>
                </details>
            <?php endforeach; ?>
            <?php $done = array_filter($data['tasks'], fn($t) => $t['done']); if ($done): ?>
                <details class="archive"><summary>Concluídas (<?= count($done) ?>)</summary>
                    <ul><?php foreach ($done as $task): ?><li><?= h($task['title']) ?></li><?php endforeach; ?></ul>
                </details>
            <?php endif; ?>
        </section>

    <?php elseif ($view === 'agenda'): ?>
        <section class="panel" id="rotina">
            <h2>Rotina semanal</h2>
            <details class="entry">
                <summary>Adicionar horário recorrente</summary>
                <form method="post" class="stack inset" action="<?= h(url($view, $dayArg)) ?>">
                    <?php fields('save-rule'); weeklyFields(['kind' => 'work', 'title' => '', 'weekdays' => 31,
                        'first_day' => $day, 'start_time' => '07:30', 'end_time' => '14:30']); ?>
                    <p class="muted">Se o fim for mais cedo que o início, o intervalo termina no dia seguinte.</p>
                    <button>Salvar rotina semanal</button>
                </form>
            </details>
            <?php foreach ($data['rules'] as $rule): ?>
                <details class="entry" data-rule="<?= h($rule['id']) ?>">
                    <summary><strong><?= h($rule['title']) ?></strong><span><?= h(implode(' · ', array_filter(WEEKDAYS,
                        fn($number) => $rule['weekdays'] & (1 << ($number - 1)), ARRAY_FILTER_USE_KEY))) ?> · <?= h($rule['start_time']) ?>–<?= h($rule['end_time']) ?></span></summary>
                    <div class="stack inset">
                        <form method="post" class="stack" action="<?= h(url($view, $dayArg)) ?>">
                            <?php fields('save-rule'); ?><input type="hidden" name="id" value="<?= h($rule['id']) ?>">
                            <?php weeklyFields($rule); ?><button>Salvar rotina semanal</button>
                        </form>
                        <details><summary>Excluir rotina</summary>
                            <p class="muted">Remove os horários recorrentes e os ajustes de cada dia desta rotina.</p>
                            <form method="post" action="<?= h(url($view, $dayArg)) ?>">
                                <?php fields('delete-rule'); ?><input type="hidden" name="id" value="<?= h($rule['id']) ?>"><button class="quiet">Excluir rotina semanal</button>
                            </form>
                        </details>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>
        <section class="panel">
            <h2>Novo intervalo</h2>
            <form method="post" class="form-grid" action="<?= h(url($view, $dayArg)) ?>">
                <?php fields('add-window'); ?>
                <label>Tipo<select name="kind"><?php choices(WINDOW_TYPES, 'free'); ?></select></label>
                <label>Nome<input name="title" maxlength="120" placeholder="Obrigatório para compromissos"></label>
                <label>Início<input type="datetime-local" name="start_at" required value="<?= h($startDefault) ?>"></label>
                <label>Fim<input type="datetime-local" name="end_at" required value="<?= h($endDefault) ?>"></label>
                <button>Adicionar à agenda</button>
            </form>
            <p class="muted window-help">Use <strong>Livre durante o trabalho</strong> para uma pausa ou janela que cai dentro do expediente.</p>
        </section>
        <div class="two-columns">
            <section class="panel lists">
                <h2>Intervalos cadastrados</h2>
                <?php $until = at($day)->modify('+1 day')->format('Y-m-d') . ' 00:00';
                $from = $day . ' 00:00';
                $events = array_merge($data['occurrences'] ?? [], array_filter($data['windows'],
                    fn($w) => ($w['occurrence_day'] ?? null) !== $day && overlaps($w['start_at'], $w['end_at'], $from, $until)));
                usort($events, fn($a,$b) => $a['start_at'] <=> $b['start_at']); ?>
                <?php if (!$events): ?><p class="muted">Sem intervalos nesta data.</p><?php endif; ?>
                <?php foreach ($events as $event): ?>
                    <?php $exception = $event['exception'] ?? null;
                    $eventTime = stamp($event['start_at']) . '–' . (substr($event['end_at'], 0, 10) === substr($event['start_at'], 0, 10)
                        ? substr($event['end_at'], 11) : stamp($event['end_at'])); ?>
                    <details class="entry" <?= isset($event['rule_id']) ? 'data-occurrence' : '' ?>>
                        <summary><strong><?= h($event['title']) ?></strong><span><?= h($eventTime) ?></span>
                            <span class="tag"><?= h(WINDOW_TYPES[$event['kind']]) ?></span>
                            <?php if (isset($event['rule_id'])): ?><span class="tag"><?= $exception ? ($event['skipped'] ? 'Cancelado neste dia' : 'Ajustado neste dia') : 'Semanal' ?></span><?php endif; ?>
                        </summary>
                        <div class="stack inset">
                        <?php if (isset($event['rule_id']) && $event['occurrence_day'] === $day): ?>
                            <form method="post" class="stack" action="<?= h(url($view, ['date' => $day])) ?>">
                                <?php fields('save-exception'); ?><input type="hidden" name="rule_id" value="<?= h($event['rule_id']) ?>">
                                <input type="hidden" name="occurrence_day" value="<?= h($day) ?>">
                                <strong>Ajustar só em <?= h(at($day)->format('d/m/Y')) ?></strong>
                                <p class="muted">Para mudar outra data, <a href="#agenda-date">escolha o dia na agenda</a>.</p>
                                <div class="form-grid">
                                    <label>Início neste dia<input type="time" name="start_time" required value="<?= h(substr($event['start_at'], 11)) ?>"></label>
                                    <label>Fim neste dia<input type="time" name="end_time" required value="<?= h(substr($event['end_at'], 11)) ?>"></label>
                                </div>
                                <?php if (!isFreeWindow($event['kind'])): ?>
                                    <label class="check-option"><input type="checkbox" name="release_time" value="1" <?= !empty($exception['release_time']) ? 'checked' : '' ?>>Marcar o tempo liberado como livre</label>
                                <?php endif; ?>
                                <div class="actions">
                                    <button name="change_kind" value="change">Salvar só este dia</button>
                                    <?php if (!$event['skipped']): ?><button class="quiet" name="change_kind" value="skip" formnovalidate>Cancelar só este dia</button><?php endif; ?>
                                    <?php if ($exception): ?><button class="subtle" name="change_kind" value="reset" formnovalidate>Restaurar horário habitual</button><?php endif; ?>
                                </div>
                            </form>
                        <?php elseif (isset($event['id'])): ?>
                            <form method="post" class="stack" action="<?= h(url($view, $dayArg)) ?>">
                                <?php fields('edit-window'); ?><input type="hidden" name="id" value="<?= h($event['id']) ?>">
                                <label>Nome<input name="title" maxlength="120" value="<?= h($event['title']) ?>"></label>
                                <label>Tipo<select name="kind"><?php choices(WINDOW_TYPES, $event['kind']); ?></select></label>
                                <label>Início<input type="datetime-local" name="start_at" required value="<?= h(inputStamp($event['start_at'])) ?>"></label>
                                <label>Fim<input type="datetime-local" name="end_at" required value="<?= h(inputStamp($event['end_at'])) ?>"></label>
                                <button>Salvar intervalo</button>
                            </form>
                            <form method="post" action="<?= h(url($view, $dayArg)) ?>">
                                <?php fields('remove-window'); ?><input type="hidden" name="id" value="<?= h($event['id']) ?>"><button class="quiet">Remover intervalo</button>
                            </form>
                        <?php elseif (!empty($event['released'])): ?>
                            <p class="muted">Este intervalo livre vem de um ajuste na sua rotina.</p>
                        <?php elseif ($event['occurrence_day'] >= $today): ?>
                            <a href="<?= h(url('agenda', ['date' => $event['occurrence_day']])) ?>">Ajustar na data de início</a>
                        <?php else: ?>
                            <p class="muted">Este intervalo começou no dia anterior.</p>
                        <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </section>
            <section class="panel lists">
                <h2>Blocos aceitos</h2>
                <?php $blocks = array_values(array_filter($data['blocks'], fn($b) => overlaps($b['start_at'], $b['end_at'], $from, $until)));
                usort($blocks, fn($a,$b) => $a['start_at'] <=> $b['start_at']); ?>
                <?php if (!$blocks): ?><p class="muted">Nenhum bloco aceito nesta data.</p><?php endif; ?>
                <?php foreach ($blocks as $block): ?>
                    <div class="scheduled-entry">
                    <details class="entry" data-block="<?= h($block['id']) ?>">
                        <summary><strong><?= h($block['title']) ?></strong><span><?= h(stamp($block['start_at'])) ?>–<?= h(at($block['end_at'])->format('H:i')) ?></span>
                            <?php if (conflict($block, $data['windows'])): ?><span class="tag conflict">Conflito</span><?php endif; ?></summary>
                        <div class="stack inset">
                            <form method="post" class="stack" action="<?= h(url($view, $dayArg)) ?>">
                                <?php fields('move-block'); ?><input type="hidden" name="id" value="<?= h($block['id']) ?>">
                                <label>Novo início<input type="datetime-local" name="start_at" required value="<?= h(inputStamp($block['start_at'])) ?>"></label><button>Reagendar bloco</button>
                            </form>
                            <form method="post" action="<?= h(url($view, $dayArg)) ?>">
                                <?php fields('remove-block'); ?><input type="hidden" name="id" value="<?= h($block['id']) ?>"><button class="quiet">Liberar bloco</button>
                            </form>
                        </div>
                    </details>
                    <?php $isTask = $block['kind'] === 'task';
                    completionForm($isTask ? 'done-task' : 'done-block', (int) $block[$isTask ? 'item_id' : 'id'],
                        duration($block['start_at'], $block['end_at']), $today, $view, $dayArg); ?>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>
        <section class="panel lists">
            <h2>Tempo disponível</h2>
            <?php foreach ($dayRanges as [$a, $b]): ?>
                <p class="info-line"><?= h(stamp($a)) ?>–<?= h(substr($b, 11)) ?> · <?= duration($a, $b) ?> min</p>
            <?php endforeach; if (!$dayRanges): ?><p class="muted">Nenhum intervalo livre restante nesta data.</p><?php endif; ?>
        </section>

    <?php elseif ($view === 'interests'): ?>
        <section class="panel">
            <h2>Novo interesse</h2>
            <form method="post" class="form-grid" action="<?= h(url($view, $dayArg)) ?>">
                <?php fields('save-interest'); ?>
                <label>Título<input name="title" required maxlength="120"></label>
                <label>Categoria<select name="category"><?php choices(INTERESTS, 'book'); ?></select></label>
                <label>Tempo da próxima sessão (min)<input type="number" name="minutes" min="1" max="720" required value="20"></label>
                <label>Status<select name="progress"><?php choices(PROGRESS, 'want'); ?></select></label>
                <label>Detalhe da próxima sessão<input name="session_note" maxlength="100" placeholder="Ex.: ler 1 capítulo"></label>
                <button>Salvar interesse</button>
            </form>
        </section>
        <section class="panel lists">
            <h2>Minha lista</h2>
            <?php if (!$data['interests']): ?><p class="muted">Sua lista ainda está vazia.</p><?php endif; ?>
            <?php foreach ($data['interests'] as $item): ?>
                <details class="entry" data-interest="<?= h($item['category']) ?>">
                    <summary><strong><?= h($item['title']) ?></strong><span class="tag"><?= h(INTERESTS[$item['category']]) ?></span>
                        <span><?= h($item['minutes']) ?> min · <?= h(PROGRESS[$item['progress']]) ?></span></summary>
                    <form method="post" class="form-grid inset" action="<?= h(url($view, $dayArg)) ?>">
                        <?php fields('save-interest'); ?><input type="hidden" name="id" value="<?= h($item['id']) ?>">
                        <label>Título<input name="title" required maxlength="120" value="<?= h($item['title']) ?>"></label>
                        <label>Categoria<select name="category"><?php choices(INTERESTS, $item['category']); ?></select></label>
                        <label>Tempo (min)<input type="number" name="minutes" min="1" max="720" required value="<?= h($item['minutes']) ?>"></label>
                        <label>Status<select name="progress"><?php choices(PROGRESS, $item['progress']); ?></select></label>
                        <label>Detalhe da próxima sessão<input name="session_note" maxlength="100" value="<?= h($item['session_note']) ?>"></label>
                        <button>Salvar edição</button>
                    </form>
                </details>
            <?php endforeach; ?>
        </section>

    <?php elseif ($view === 'habits'): ?>
        <div class="two-columns">
            <section class="panel">
                <h2>Registrar atividade</h2>
                <form method="post" class="stack" action="<?= h(url($view, $dayArg)) ?>">
                    <?php fields('add-activity'); ?>
                    <label>Atividade<select name="kind"><?php choices(ACTIVITIES, 'study'); ?></select></label>
                    <label>Data<input type="date" name="log_day" required value="<?= h($today) ?>" max="<?= h($today) ?>"></label>
                    <label>Duração (min)<input type="number" name="minutes" min="1" max="1440" required value="25"></label>
                    <button>Registrar atividade</button>
                </form>
            </section>
            <section class="panel">
                <h2>Minhas preferências</h2>
                <p class="muted">Registre o que fez em cada dia. As sugestões dependem do tempo livre daquela data. Use zero para desativar metas de frequência ou o limite de jogo.</p>
                <form method="post" class="stack" action="<?= h(url($view, $dayArg)) ?>">
                    <?php fields('save-goals'); ?>
                    <?php foreach ([
                        'reading_sessions' => ['Leituras por semana', 0, 30],
                        'busy_days' => ['Dias seguidos com trabalho, estudo ou tarefas para sugerir pausa', 0, 30],
                        'rest_minutes' => ['Duração preferida da pausa (min)', MIN_REST_MINUTES, 180],
                        'gaming_minutes' => ['Limite semanal de jogo (min, 0 = sem limite)', 0, 10080],
                    ] as $key => [$label, $min, $max]): ?>
                        <label><?= h($label) ?><input type="number" name="<?= h($key) ?>" required min="<?= h($min) ?>" max="<?= h($max) ?>" value="<?= h($data['goals'][$key]) ?>"></label>
                    <?php endforeach; ?><button>Salvar preferências</button>
                </form>
            </section>
        </div>
        <section class="panel lists">
            <h2>Atividades registradas</h2>
            <?php if (!$data['activities']): ?><p class="muted">Você pode registrar aqui ou marcar atividades como feitas na agenda.</p><?php endif; ?>
            <?php foreach (array_reverse($data['activities']) as $entry): ?>
                <article class="entry" data-activity="<?= h($entry['kind']) ?>"><div>
                    <h3><?= h($entry['title'] ?? ACTIVITIES[$entry['kind']]) ?></h3>
                    <p><?= $entry['title'] ? h(ACTIVITIES[$entry['kind']]) . ' · ' : '' ?><?= h(at($entry['log_day'])->format('d/m/Y')) ?> · <?= h($entry['minutes']) ?> min</p></div>
                    <form method="post" action="<?= h(url($view, $dayArg)) ?>"><?php fields('remove-activity'); ?>
                        <input type="hidden" name="id" value="<?= h($entry['id']) ?>"><button class="quiet">Remover</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<?php endif; ?>
</body>
</html>
