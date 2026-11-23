<?php
declare(strict_types=1);
require __DIR__ . '/../app/store.php';
require __DIR__ . '/../app/planner.php';
require __DIR__ . '/../app/actions.php';

function verify(bool $success, string $description): void
{
    if (!$success) {
        throw new RuntimeException($description);
    }
    echo "OK $description\n";
}

function change(PDO $db, int $uid, string $action, array $fields = []): string
{
    $_POST = $fields;
    return runAction($db, $uid, $action, '2026-11-19', at('2026-11-19 18:00'));
}

function rejects(callable $callback, string $message): bool
{
    try {
        $callback();
        return false;
    } catch (DomainException $error) {
        return str_contains($error->getMessage(), $message);
    }
}

if (!getenv('TIMECTRL_DB') && !getenv('TIMECTRL_CONFIG')) {
    throw new RuntimeException('Informe TIMECTRL_DB ou TIMECTRL_CONFIG apontando para um banco de teste isolado.');
}
$db = database();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

$create = static function () use ($db): int {
    execute($db, 'INSERT INTO users (name,email,password_hash) VALUES (?,?,?)',
        ['Conta fictícia', bin2hex(random_bytes(8)) . '@example.test', password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT)]);
    return (int) $db->lastInsertId();
};
$uid = $create();
$other = $create();

$task = ['title'=>'Relatório','due_at'=>'2026-11-20T09:00','minutes'=>'90','priority'=>'3'];
change($db, $uid, 'save-task', $task);
$saved = userData(database(), $uid, '2026-11-19')['tasks'][0];
$id = (string) $saved['id'];
verify($saved['title'] === 'Relatório', "$driver grava a tarefa e uma nova conexão encontra o dado");
verify(userData($db, $other, '2026-11-19')['tasks'] === [], 'consulta separa dados por conta');
verify(rejects(fn() => change($db, $other, 'done-task', ['id'=>$id]), 'não está disponível'), 'alteração de registro de outra conta é recusada');
verify(rejects(fn() => change($db, $uid, 'save-task', array_replace($task, ['minutes'=>'0'])), 'entre 1 e 720'), 'duração zero é recusada');

$free = ['kind'=>'free','title'=>'Noite','start_at'=>'2026-11-19T20:00','end_at'=>'2026-11-19T21:30'];
change($db, $uid, 'add-window', $free);
change($db, $uid, 'suggestion', ['kind'=>'task','item'=>$id,'start_at'=>$free['start_at'],'decision'=>'accept']);
verify(count(userData($db, $uid, '2026-11-19')['blocks']) === 1, 'aceite persiste um bloco real');
verify(rejects(fn() => change($db, $uid, 'suggestion', ['kind'=>'rest','item'=>'0','start_at'=>$free['start_at'],'decision'=>'accept']),
    'conflito'), 'blocos confirmados não podem se sobrepor');
change($db, $uid, 'add-window', array_replace($free, ['kind'=>'busy','title'=>'Aula extraordinária']));
$result = plan(userData($db,$uid,'2026-11-19'), at('2026-11-19 18:00'));
verify(count($result['conflicts']) === 1 && count($result['withoutTime']) === 1, 'compromisso superposto mantém o conflito visível e indica falta de tempo');
change($db, $uid, 'done-task', ['id'=>$id]);
$now = userData($db,$uid,'2026-11-19');
verify((bool) $now['tasks'][0]['done'] && $now['blocks'] === [], 'concluir marca tarefa e libera o horário');

change($db, $uid, 'save-choice', ['mode'=>'leisure','routine'=>'usual']);
change($db, $uid, 'hide-choice');
verify((bool) userData($db,$uid,'2026-11-19')['choice']['prompt_hidden'] &&
    userData($db,$uid,'2026-11-20')['choice']['mode'] === 'balance', 'preferência e dispensa são independentes por data');
foreach (INTERESTS as $category => $title) {
    change($db, $uid, 'save-interest', ['title'=>$title, 'category'=>$category,'minutes'=>'20','progress'=>'want','session_note'=>'']);
}
verify(count(userData($db,$uid,'2026-11-19')['interests']) === 5, 'as cinco categorias são persistidas');

$weekly = ['title'=>'Trabalho habitual','kind'=>'work','weekdays'=>['1','2','3','4','5'],
    'first_day'=>'2026-11-19','start_time'=>'07:30','end_time'=>'14:30'];
change($db, $uid, 'save-rule', $weekly);
$ruleId = (string) userData(database(), $uid, '2026-11-19')['rules'][0]['id'];
$exception = ['rule_id'=>$ruleId,'occurrence_day'=>'2026-11-19','change_kind'=>'change','start_time'=>'07:30','end_time'=>'12:00'];
verify(rejects(fn() => change($db, $uid, 'save-exception', array_replace($exception, ['occurrence_day'=>'2026-11-20'])), 'data da agenda mudou'),
    'uma data divergente não altera a rotina do dia aberto');
verify(rejects(fn() => change($db, $other, 'save-exception', $exception), 'não está disponível'), 'ajustes de rotina respeitam a conta');
verify(rejects(fn() => change($db, $uid, 'save-rule', array_replace($weekly, ['weekdays'=>[]])), 'pelo menos um'), 'rotina exige ao menos um dia');
change($db, $uid, 'save-exception', $exception);
$today = userData($db, $uid, '2026-11-19');
verify($today['occurrences'][0]['end_at'] === '2026-11-19 12:00' && !array_filter($today['windows'], fn($w) => !empty($w['released'])),
    'mudança de horário só libera tempo quando a opção é marcada');
change($db, $uid, 'save-exception', array_replace($exception, ['change_kind'=>'skip', 'release_time'=>'1']));
$today = userData($db, $uid, '2026-11-19');
$next = userData($db, $uid, '2026-11-20');
verify($today['occurrences'][0]['skipped'] && intervalFits(freeRanges($today), '2026-11-19 07:30', '2026-11-19 14:30')
    && $next['occurrences'][0]['end_at'] === '2026-11-20 14:30', 'cancelar só uma data pode disponibilizar todo o turno');
change($db, $uid, 'save-exception', array_replace($exception, ['change_kind'=>'reset']));
$overnight = ['title'=>'Turno noturno','kind'=>'work','weekdays'=>['4'],'first_day'=>'2026-11-19','start_time'=>'22:00','end_time'=>'06:00'];
change($db, $uid, 'save-rule', $overnight);
verify(count(array_filter(userData($db, $uid, '2026-11-20')['windows'], fn($w) => $w['title'] === 'Turno noturno'
    && $w['start_at'] === '2026-11-19 22:00' && $w['end_at'] === '2026-11-20 06:00')) === 1, 'turno noturno consta na agenda do dia seguinte');
change($db, $uid, 'save-exception', $exception);
change($db, $uid, 'delete-rule', ['id'=>$ruleId]);
verify(!one($db, 'SELECT rule_id FROM window_exceptions WHERE user_id = ? AND rule_id = ?', [$uid, $ruleId]), 'remover rotina elimina seus ajustes');
$manual = one($db, "SELECT id FROM windows WHERE user_id = ? AND kind = 'free'", [$uid]);
$edit = array_replace($free, ['id'=>(string) $manual['id'], 'title'=>'Fim da noite', 'end_at'=>'2026-11-19T22:00']);
change($db, $uid, 'edit-window', $edit);
verify(owned($db, 'windows', $uid, (int) $manual['id'])['end_at'] === '2026-11-19 22:00' &&
    rejects(fn() => change($db, $other, 'edit-window', $edit), 'não está disponível'), 'intervalo avulso pode ser editado pela conta proprietária');
