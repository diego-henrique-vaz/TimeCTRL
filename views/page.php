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

function choices(array $options, string|int $value): void
{
    foreach ($options as $key => $label) {
        echo '<option value="' . h($key) . '"' . ((string) $key === (string) $value ? ' selected' : '') . '>' . h($label) . '</option>';
    }
}

function proposalForm(array $item, string $view, array $dayArg): void
{
    ?>
    <form class="actions" method="post" action="<?= h(url($view, $dayArg)) ?>">
        <?php fields('suggestion'); ?>
        <input type="hidden" name="kind" value="<?= h($item['kind']) ?>">
        <input type="hidden" name="item" value="<?= h($item['id']) ?>">
        <input type="hidden" name="start_at" value="<?= h($item['start']) ?>">
        <button name="decision" value="accept">Aceitar horário</button>
        <button class="subtle" name="decision" value="later">Adiar</button>
        <button class="quiet" name="decision" value="dismiss">Dispensar</button>
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
            <a <?= $route === $view ? 'aria-current="page"' : '' ?> href="<?= h(url($route, $dayArg)) ?>"><?= h($name) ?></a>
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
        <form method="get" action="/" class="date-picker">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <label><span class="sr">Data da agenda</span><input name="date" type="date" min="<?= h($today) ?>" value="<?= h($day) ?>"></label>
            <button class="subtle">Ver dia</button>
        </form>
        <?php endif; ?>
    </header>
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
                <?php elseif ($day === $today): ?>
                    <p>Você não tem um intervalo livre cadastrado neste momento.</p>
                <?php endif; ?>
                <?php if ($suggestion): ?>
                    <div class="recommendation" data-recommendation="<?= h($suggestion['kind']) ?>">
                        <h3><?= h($suggestion['title']) ?></h3>
                        <p><?= h($suggestion['reason']) ?></p>
                        <p class="time"><?= h(at($suggestion['start'])->format('H:i')) ?>–<?= h(at($suggestion['end'])->format('H:i')) ?></p>
                        <?php proposalForm($suggestion, $view, $dayArg); ?>
                    </div>
                <?php elseif ($summary['current'] && $day === $today): ?>
                    <p class="recommendation">Nenhuma sugestão disponível se encaixa com essa escolha e esse horário.</p>
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
            </section>
        </div>
        <section class="panel lists">
            <h2>Prazos e conflitos</h2>
            <?php if (!$summary['late'] && !$summary['withoutTime'] && !$summary['conflicts'] && !$summary['near']): ?>
                <p class="muted">Nenhum prazo próximo ou conflito na agenda.</p>
            <?php endif; ?>
            <?php foreach ($summary['late'] as $task): ?>
                <p class="alert-line" data-alert="late"><strong>Prazo vencido.</strong> <?= h($task['title']) ?> · <?= h(stamp($task['due_at'])) ?></p>
            <?php endforeach; ?>
            <?php foreach ($summary['withoutTime'] as $task): ?>
                <p class="alert-line" data-alert="no-slot"><strong>Sem horário antes do prazo.</strong> <?= h($task['title']) ?> precisa de <?= h($task['minutes']) ?> min até <?= h(stamp($task['due_at'])) ?>.</p>
            <?php endforeach; ?>
            <?php foreach ($summary['conflicts'] as $block): ?>
                <p class="alert-line" data-alert="conflict"><strong>Conflito na agenda.</strong> <?= h($block['title']) ?> · <a href="<?= h(url('agenda', $dayArg)) ?>">Ajustar bloco</a></p>
            <?php endforeach; ?>
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
                $hasBlock = in_array($item['id'], array_map(fn($b) => (int) $b['item_id'],
                    array_filter($data['blocks'], fn($b) => $b['kind'] === 'task' && $b['end_at'] > timeKey($clock))), true);
                ?>
                <article class="entry" data-forecast>
                    <div><h3><?= h($item['title']) ?></h3><p><?= h(stamp($item['start'])) ?>–<?= h(at($item['end'])->format('H:i')) ?></p></div>
                    <?php if ($hasBlock): ?><a href="<?= h(url('agenda', $dayArg)) ?>">Ajustar bloco existente</a><?php else: ?><?php proposalForm($item, $view, $dayArg); ?><?php endif; ?>
                </article>
            <?php endforeach; if (!$suggested): ?><p class="muted">Não há outros encaixes para mostrar.</p><?php endif; ?>
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
                        <form method="post" action="<?= h(url($view, $dayArg)) ?>">
                            <?php fields('done-task'); ?><input type="hidden" name="id" value="<?= h($task['id']) ?>"><button class="subtle">Concluir tarefa</button>
                        </form>
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
        <section class="panel">
            <h2>Novo intervalo</h2>
            <form method="post" class="form-grid" action="<?= h(url($view, $dayArg)) ?>">
                <?php fields('add-window'); ?>
                <label>Tipo<select name="kind"><?php choices(['free' => 'Livre', 'busy' => 'Compromisso', 'work' => 'Trabalho'], 'free'); ?></select></label>
                <label>Nome<input name="title" maxlength="120" placeholder="Obrigatório para compromissos"></label>
                <label>Início<input type="datetime-local" name="start_at" required value="<?= h($startDefault) ?>"></label>
                <label>Fim<input type="datetime-local" name="end_at" required value="<?= h($endDefault) ?>"></label>
                <button>Adicionar à agenda</button>
            </form>
        </section>
        <div class="two-columns">
            <section class="panel lists">
                <h2>Intervalos cadastrados</h2>
                <?php $until = at($day)->modify('+1 day')->format('Y-m-d') . ' 00:00';
                $from = $day . ' 00:00';
                $events = array_values(array_filter($data['windows'], fn($w) => overlaps($w['start_at'], $w['end_at'], $from, $until)));
                usort($events, fn($a,$b) => $a['start_at'] <=> $b['start_at']); ?>
                <?php if (!$events): ?><p class="muted">Sem intervalos nesta data.</p><?php endif; ?>
                <?php foreach ($events as $event): ?>
                    <article class="entry">
                        <div><h3><?= h($event['title']) ?></h3><p><?= h(stamp($event['start_at'])) ?>–<?= h(at($event['end_at'])->format('H:i')) ?></p>
                            <small class="tag"><?= h(['free'=>'Livre','busy'=>'Compromisso','work'=>'Trabalho'][$event['kind']]) ?></small></div>
                        <form method="post" action="<?= h(url($view, $dayArg)) ?>">
                            <?php fields('remove-window'); ?><input type="hidden" name="id" value="<?= h($event['id']) ?>"><button class="quiet">Remover</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </section>
            <section class="panel lists">
                <h2>Blocos aceitos</h2>
                <?php $blocks = array_values(array_filter($data['blocks'], fn($b) => overlaps($b['start_at'], $b['end_at'], $from, $until)));
                usort($blocks, fn($a,$b) => $a['start_at'] <=> $b['start_at']); ?>
                <?php if (!$blocks): ?><p class="muted">Nenhum bloco aceito nesta data.</p><?php endif; ?>
                <?php foreach ($blocks as $block): ?>
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
                <?php endforeach; ?>
            </section>
        </div>
        <section class="panel lists">
            <h2>Tempo disponível</h2>
            <?php $visible = false;
            foreach ($summary['ranges'] as [$a, $b]): if (!overlaps($a, $b, $from, $until) || $b <= timeKey($focus)) continue; $visible = true; ?>
                <p class="info-line"><?= h(stamp(max($a, $from, timeKey($focus)))) ?>–<?= h(at(min($b, $until))->format('H:i')) ?> · <?= duration(max($a, $from, timeKey($focus)), min($b, $until)) ?> min</p>
            <?php endforeach; if (!$visible): ?><p class="muted">Nenhum intervalo livre restante nesta data.</p><?php endif; ?>
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
                <p class="muted">Use zero para desligar as metas de leitura, pausa por sequência ou limite de jogo.</p>
                <form method="post" class="stack" action="<?= h(url($view, $dayArg)) ?>">
                    <?php fields('save-goals'); ?>
                    <?php foreach ([
                        'reading_sessions' => ['Leituras por semana', 0, 30],
                        'busy_days' => ['Dias seguidos de trabalho ou estudo para sugerir pausa', 0, 30],
                        'rest_minutes' => ['Duração da pausa (min)', 5, 180],
                        'gaming_minutes' => ['Limite semanal de jogo (min)', 0, 10080],
                    ] as $key => [$label, $min, $max]): ?>
                        <label><?= h($label) ?><input type="number" name="<?= h($key) ?>" required min="<?= h($min) ?>" max="<?= h($max) ?>" value="<?= h($data['goals'][$key]) ?>"></label>
                    <?php endforeach; ?><button>Salvar preferências</button>
                </form>
            </section>
        </div>
        <section class="panel lists">
            <h2>Atividades registradas</h2>
            <?php if (!$data['activities']): ?><p class="muted">Nenhuma atividade registrada. O painel só usa os dados que você informar.</p><?php endif; ?>
            <?php foreach (array_reverse($data['activities']) as $entry): ?>
                <article class="entry"><div><h3><?= h(ACTIVITIES[$entry['kind']]) ?></h3>
                    <p><?= h(at($entry['log_day'])->format('d/m/Y')) ?> · <?= h($entry['minutes']) ?> min</p></div>
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
