<?php
declare(strict_types=1);

function field(string $key): string
{
    return is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
}

function requiredText(string $key, int $limit): string
{
    $value = field($key);
    if ($value === '' || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $limit) {
        throw new DomainException("Preencha o campo com até $limit caracteres.");
    }
    return $value;
}

function number(string $key, int $min, int $max): int
{
    $value = filter_var(field($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    if ($value === false) {
        throw new DomainException("Informe um número inteiro entre $min e $max.");
    }
    return $value;
}

function option(string $key, array $options): string
{
    $value = field($key);
    if (!array_key_exists($value, $options)) {
        throw new DomainException('Selecione uma das opções disponíveis.');
    }
    return $value;
}

function dateValid(string $day): bool
{
    return preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D', $day) === 1
        && checkdate((int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4));
}

function timestamp(string $field): string
{
    $value = str_replace('T', ' ', field($field));
    if (!dateValid(substr($value, 0, 10)) || preg_match('/^.{10} (?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value) !== 1) {
        throw new DomainException('Informe uma data e hora válidas.');
    }
    return $value;
}

function authenticate(PDO $db, string $action): void
{
    $email = strtolower(field('email'));
    $password = field('password');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        throw new DomainException('Informe um e-mail válido.');
    }
    if ($action === 'signup') {
        $name = requiredText('name', 80);
        if (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new DomainException('Use uma senha de 8 a 72 bytes.');
        }
        try {
            execute($db, 'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                throw new DomainException('Este e-mail já está cadastrado.');
            }
            throw $error;
        }
        $uid = (int) $db->lastInsertId();
    } else {
        $account = one($db, 'SELECT * FROM users WHERE email = ?', [$email]);
        if (!$account || strlen($password) > 72 || !password_verify($password, $account['password_hash'])) {
            throw new DomainException('E-mail ou senha incorretos.');
        }
        $uid = (int) $account['id'];
    }
    session_regenerate_id(true);
    $_SESSION = ['uid' => $uid, 'csrf' => bin2hex(random_bytes(32))];
}

function replaceRow(PDO $db, string $table, array $keys, array $values): void
{
    // Chamadas feitas dentro da transação da conta, com nomes de colunas fixos.
    $filter = implode(' AND ', array_map(static fn($column) => "$column = ?", array_keys($keys)));
    execute($db, "DELETE FROM $table WHERE $filter", array_values($keys));
    $all = $keys + $values;
    $columns = implode(', ', array_keys($all));
    $placeholders = implode(', ', array_fill(0, count($all), '?'));
    execute($db, "INSERT INTO $table ($columns) VALUES ($placeholders)", array_values($all));
}

function saveBlock(PDO $db, int $uid, array $data, string $start, string $kind, int $item, string $now, ?int $blockId = null): void
{
    if ($kind === 'task') {
        $task = owned($db, 'tasks', $uid, $item);
        if ($task['done']) {
            throw new DomainException('A tarefa já foi concluída.');
        }
        if ($blockId === null && one($db, "SELECT id FROM blocks WHERE user_id = ? AND kind = 'task' AND item_id = ? AND end_at > ?", [$uid, $item, $now])) {
            throw new DomainException('A tarefa já tem um bloco. Ajuste o horário pela agenda.');
        }
        $title = $task['title'];
        $minutes = (int) $task['minutes'];
    } elseif ($kind === 'interest') {
        $interest = owned($db, 'interests', $uid, $item);
        if ($interest['progress'] === 'done') {
            throw new DomainException('Este interesse já foi concluído.');
        }
        $title = $interest['title'];
        $minutes = (int) $interest['minutes'];
    } else {
        $title = 'Pausa';
        $oldBlock = $blockId === null ? null : owned($db, 'blocks', $uid, $blockId);
        $minutes = $oldBlock ? duration($oldBlock['start_at'], $oldBlock['end_at']) : (int) $data['goals']['rest_minutes'];
    }
    $end = addMinutes($start, $minutes);
    if ($minutes < 1 || $start < $now || !intervalFits(freeRanges($data, $blockId), $start, $end)) {
        throw new DomainException('Esse horário entrou em conflito ou não comporta a duração. Escolha outro bloco livre.');
    }
    if (isset($task) && $end > $task['due_at']) {
        throw new DomainException('O bloco termina depois do prazo da tarefa.');
    }
    if ($blockId === null) {
        execute($db, 'INSERT INTO blocks (user_id, kind, item_id, title, start_at, end_at) VALUES (?, ?, ?, ?, ?, ?)', [$uid, $kind, $item, $title, $start, $end]);
    } else {
        execute($db, 'UPDATE blocks SET title = ?, start_at = ?, end_at = ? WHERE user_id = ? AND id = ?', [$title, $start, $end, $uid, $blockId]);
    }
}

function runAction(PDO $db, int $uid, string $action, string $day, DateTimeImmutable $clock): string
{
    $now = timeKey($clock);
    $message = 'Alteração salva.';
    transaction($db, $uid, function () use ($db, $uid, $action, $day, $clock, $now, &$message) {
        switch ($action) {
            case 'save-task':
                $title = requiredText('title', 120);
                $due = timestamp('due_at');
                $minutes = number('minutes', 1, 720);
                $priority = number('priority', 1, 3);
                if (field('id') === '') {
                    execute($db, 'INSERT INTO tasks (user_id, title, due_at, minutes, priority) VALUES (?, ?, ?, ?, ?)', [$uid, $title, $due, $minutes, $priority]);
                    $message = 'Tarefa cadastrada.';
                    break;
                }
                $id = number('id', 1, PHP_INT_MAX);
                $old = owned($db, 'tasks', $uid, $id);
                $block = one($db, "SELECT * FROM blocks WHERE user_id = ? AND kind = 'task' AND item_id = ? AND end_at > ?", [$uid, $id, $now]);
                if ($block && ((int) $old['minutes'] !== $minutes || $block['end_at'] > $due)) {
                    execute($db, 'DELETE FROM blocks WHERE user_id = ? AND id = ?', [$uid, $block['id']]);
                    $message = 'Tarefa editada. O horário anterior foi liberado porque a duração ou o prazo mudou.';
                } elseif ($block) {
                    execute($db, 'UPDATE blocks SET title = ? WHERE user_id = ? AND id = ?', [$title, $uid, $block['id']]);
                }
                execute($db, 'UPDATE tasks SET title = ?, due_at = ?, minutes = ?, priority = ? WHERE user_id = ? AND id = ?', [$title, $due, $minutes, $priority, $uid, $id]);
                break;
            case 'done-task':
                $id = number('id', 1, PHP_INT_MAX);
                owned($db, 'tasks', $uid, $id);
                execute($db, 'UPDATE tasks SET done = 1 WHERE user_id = ? AND id = ?', [$uid, $id]);
                execute($db, "DELETE FROM blocks WHERE user_id = ? AND kind = 'task' AND item_id = ?", [$uid, $id]);
                $message = 'Tarefa concluída.';
                break;
            case 'add-window':
                $kind = option('kind', ['free' => true, 'busy' => true, 'work' => true]);
                $start = timestamp('start_at');
                $end = timestamp('end_at');
                if ($end <= $start || duration($start, $end) > 1440) {
                    throw new DomainException('O intervalo deve terminar depois do início e durar até 24 horas.');
                }
                $title = $kind === 'free' && field('title') === '' ? 'Horário livre' : requiredText('title', 120);
                execute($db, 'INSERT INTO windows (user_id, title, kind, start_at, end_at) VALUES (?, ?, ?, ?, ?)', [$uid, $title, $kind, $start, $end]);
                $message = 'Intervalo cadastrado.';
                break;
            case 'remove-window':
            case 'remove-block':
            case 'remove-activity':
                $table = ['remove-window' => 'windows', 'remove-block' => 'blocks', 'remove-activity' => 'activities'][$action];
                $id = number('id', 1, PHP_INT_MAX);
                owned($db, $table, $uid, $id);
                execute($db, "DELETE FROM $table WHERE user_id = ? AND id = ?", [$uid, $id]);
                $message = 'Registro removido.';
                break;
            case 'save-interest':
                $title = requiredText('title', 120);
                $category = option('category', INTERESTS);
                $progress = option('progress', PROGRESS);
                $minutes = number('minutes', 1, 720);
                $note = field('session_note');
                if (!mb_check_encoding($note, 'UTF-8') || mb_strlen($note) > 100) {
                    throw new DomainException('A descrição da sessão pode ter até 100 caracteres.');
                }
                if (field('id') === '') {
                    execute($db, 'INSERT INTO interests (user_id, title, category, minutes, progress, session_note) VALUES (?, ?, ?, ?, ?, ?)',
                        [$uid, $title, $category, $minutes, $progress, $note]);
                    break;
                }
                $id = number('id', 1, PHP_INT_MAX);
                $old = owned($db, 'interests', $uid, $id);
                execute($db, 'UPDATE interests SET title = ?, category = ?, minutes = ?, progress = ?, session_note = ? WHERE user_id = ? AND id = ?',
                    [$title, $category, $minutes, $progress, $note, $uid, $id]);
                if ($progress === 'done' || (int) $old['minutes'] !== $minutes) {
                    execute($db, "DELETE FROM blocks WHERE user_id = ? AND kind = 'interest' AND item_id = ? AND end_at > ?", [$uid, $id, $now]);
                } else {
                    execute($db, "UPDATE blocks SET title = ? WHERE user_id = ? AND kind = 'interest' AND item_id = ? AND end_at > ?", [$title, $uid, $id, $now]);
                }
                break;
            case 'add-activity':
                $date = field('log_day');
                if (!dateValid($date) || $date > $clock->format('Y-m-d')) {
                    throw new DomainException('Informe uma data válida, igual ou anterior ao dia atual.');
                }
                execute($db, 'INSERT INTO activities (user_id, kind, log_day, minutes) VALUES (?, ?, ?, ?)',
                    [$uid, option('kind', ACTIVITIES), $date, number('minutes', 1, 1440)]);
                break;
            case 'save-goals':
                replaceRow($db, 'goals', ['user_id' => $uid], [
                    'reading_sessions' => number('reading_sessions', 0, 30),
                    'busy_days' => number('busy_days', 0, 30),
                    'rest_minutes' => number('rest_minutes', 5, 180),
                    'gaming_minutes' => number('gaming_minutes', 0, 10080),
                ]);
                break;
            case 'save-choice':
                replaceRow($db, 'day_choices', ['user_id' => $uid, 'plan_day' => $day], [
                    'mode' => option('mode', MODES), 'routine' => option('routine', ROUTINES),
                ]);
                $message = 'Preferência do dia atualizada.';
                break;
            case 'hide-choice':
                $choice = userData($db, $uid, $day)['choice'];
                replaceRow($db, 'day_choices', ['user_id' => $uid, 'plan_day' => $day], [
                    'mode' => $choice['mode'], 'routine' => $choice['routine'], 'prompt_hidden' => 1,
                ]);
                $message = 'Pergunta dispensada para este dia.';
                break;
            case 'move-block':
                $id = number('id', 1, PHP_INT_MAX);
                $block = owned($db, 'blocks', $uid, $id);
                saveBlock($db, $uid, userData($db, $uid, $day), timestamp('start_at'), $block['kind'], (int) $block['item_id'], $now, $id);
                $message = 'Bloco reagendado.';
                break;
            case 'suggestion':
                $kind = option('kind', ['task' => true, 'interest' => true, 'rest' => true]);
                $item = number('item', $kind === 'rest' ? 0 : 1, $kind === 'rest' ? 0 : PHP_INT_MAX);
                if ($kind !== 'rest') {
                    owned($db, $kind === 'task' ? 'tasks' : 'interests', $uid, $item);
                }
                $decision = option('decision', ['accept' => true, 'later' => true, 'dismiss' => true]);
                if ($decision === 'accept') {
                    $start = max($now, timestamp('start_at'));
                    saveBlock($db, $uid, userData($db, $uid, $day), $start, $kind, $item, $now);
                    $message = 'Sugestão adicionada à agenda.';
                } else {
                    replaceRow($db, 'feedback', ['user_id' => $uid, 'plan_day' => $day, 'item_key' => "$kind:$item"], [
                        'decision' => $decision, 'until_at' => addMinutes($now, 60),
                    ]);
                    $message = $decision === 'later' ? 'Sugestão adiada por uma hora.' : 'Sugestão dispensada para este dia.';
                }
                break;
            default:
                throw new DomainException('Ação indisponível.');
        }
    });
    return $message;
}
