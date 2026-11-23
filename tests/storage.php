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
