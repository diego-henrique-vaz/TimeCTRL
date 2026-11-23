<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
require __DIR__ . '/../app/store.php';
require __DIR__ . '/../app/planner.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$mode = $argv[1] ?? 'tarefas';
if (!in_array($mode, ['tarefas', 'feriado'], true)) {
    fwrite(STDERR, "Use demo.php tarefas ou demo.php feriado.\n");
    exit(1);
}
$clock = at(($mode === 'feriado' ? '2026-11-20' : '2026-11-19') . ' 18:00');
$day = $clock->format('Y-m-d');
$email = 'demo-' . bin2hex(random_bytes(4)) . '@example.test';
$password = bin2hex(random_bytes(9));
$db = database();
execute($db, 'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)', ['Conta de demonstração', $email, password_hash($password, PASSWORD_DEFAULT)]);
$uid = (int) $db->lastInsertId();
transaction($db, $uid, function () use ($db, $uid, $mode, $day, $clock) {
    $window = static function (string $title, string $kind, string $from, string $to) use ($db, $uid, $day) {
        execute($db, 'INSERT INTO windows (user_id,title,kind,start_at,end_at) VALUES (?,?,?,?,?)', [$uid,$title,$kind,"$day $from","$day $to"]);
    };
    if ($mode === 'tarefas') {
        $window('Depois do trabalho', 'free', '18:00', '18:40');
        $window('Bloco da noite', 'free', '20:00', '21:30');
        foreach ([
            ['Questionário de redes', "$day 23:59", 30, 2],
            ['Relatório de laboratório', $clock->modify('+1 day')->format('Y-m-d') . ' 09:00', 90, 3],
        ] as $task) {
            execute($db, 'INSERT INTO tasks (user_id,title,due_at,minutes,priority) VALUES (?,?,?,?,?)', [$uid, ...$task]);
        }
        return;
    }
    $window('Tempo para mim', 'free', '18:00', '19:00');
    $window('Turno de trabalho no feriado', 'work', '18:25', '19:00');
    foreach ([
        ['O Hobbit', 'book', 20, 'Ler um capítulo'],
        ['Jogo de aventura', 'game', 40, ''],
        ['Filme salvo', 'movie', 100, ''],
        ['Série salva', 'series', 45, ''],
        ['Anime salvo', 'anime', 27, ''],
    ] as [$title, $category, $minutes, $note]) {
        execute($db, 'INSERT INTO interests (user_id,title,category,minutes,progress,session_note) VALUES (?,?,?,?,?,?)',
            [$uid,$title,$category,$minutes,'want',$note]);
    }
});
echo "Conta fictícia criada.\nE-mail: $email\nSenha local: $password\n";
echo "Data de demonstração (TIMECTRL_NOW): " . timeKey($clock) . "\n";
