<?php
declare(strict_types=1);

function database(): PDO
{
    $root = dirname(__DIR__);
    $file = getenv('TIMECTRL_CONFIG') ?: $root . '/config.local.php';
    $config = is_file($file) ? (require $file)['database'] : [];
    $sqliteFile = getenv('TIMECTRL_DB') ?: $root . '/var/timectrl.sqlite';
    $dsn = $config['dsn'] ?? 'sqlite:' . $sqliteFile;
    if (str_starts_with($dsn, 'sqlite:') && $dsn !== 'sqlite::memory:') {
        $parent = dirname(substr($dsn, strlen('sqlite:')));
        if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
            throw new RuntimeException('A pasta do banco local não pôde ser criada.');
        }
    }
    $db = new PDO($dsn, $config['user'] ?? null, $config['password'] ?? null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    if ($sqlite) {
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA busy_timeout = 5000');
        if (!one($db, "SELECT name FROM sqlite_master WHERE name = 'feedback'")) {
            $schema = file_get_contents($root . '/database/schema.sql');
            $db->exec(str_replace(' AUTO_INCREMENT', '', $schema));
        }
    }
    $tables = $sqlite ? 'sqlite_master' : 'information_schema.tables';
    $filter = $sqlite ? 'name' : 'table_name';
    $schemaFilter = $sqlite ? '' : ' AND table_schema = DATABASE()';
    $installed = execute($db, "SELECT $filter FROM $tables WHERE $filter IN ('recurring_windows', 'window_exceptions', 'activity_details')$schemaFilter")
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach (['agenda-recorrente.sql' => ['recurring_windows', 'window_exceptions'], 'atividades-concluidas.sql' => ['activity_details']] as $migration => $required) {
        if (array_diff($required, $installed)) {
            $sql = file_get_contents($root . '/database/' . $migration);
            foreach (explode(';', $sqlite ? str_replace(' AUTO_INCREMENT', '', $sql) : $sql) as $statement) {
                if (trim($statement) !== '') {
                    $db->exec($statement);
                }
            }
        }
    }
    return $db;
}

function execute(PDO $db, string $sql, array $params = []): PDOStatement
{
    $query = $db->prepare($sql);
    $query->execute($params);
    return $query;
}

function one(PDO $db, string $sql, array $params = []): ?array
{
    return execute($db, $sql, $params)->fetch() ?: null;
}

function owned(PDO $db, string $table, int $uid, int $id): array
{
    return one($db, "SELECT * FROM $table WHERE user_id = ? AND id = ?", [$uid, $id])
        ?? throw new DomainException('Este registro não está disponível.');
}

function transaction(PDO $db, int $uid, callable $process): void
{
    $db->beginTransaction();
    try {
        // A escrita serializa alterações simultâneas na agenda da mesma conta.
        execute($db, 'UPDATE users SET revision = revision + 1 WHERE id = ?', [$uid]);
        $process();
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function userData(PDO $db, int $uid, string $day): array
{
    $data = [];
    foreach (['tasks', 'windows', 'interests', 'blocks'] as $table) {
        $data[$table] = execute($db, "SELECT * FROM $table WHERE user_id = ? ORDER BY id", [$uid])->fetchAll();
    }
    $data['activities'] = execute($db, 'SELECT a.*, d.title, d.interest_id FROM activities a
        LEFT JOIN activity_details d ON d.activity_id = a.id WHERE a.user_id = ? ORDER BY a.log_day, a.id', [$uid])->fetchAll();
    $data['rules'] = execute($db, 'SELECT * FROM recurring_windows WHERE user_id = ? ORDER BY id', [$uid])->fetchAll();
    if ($data['rules']) {
        $exceptions = execute($db, 'SELECT * FROM window_exceptions WHERE user_id = ? AND occurrence_day >= ?',
            [$uid, at($day)->modify('-1 day')->format('Y-m-d')])->fetchAll();
        $data += recurringAgenda($data['rules'], $exceptions, $data['blocks'], $day);
        $data['windows'] = array_merge($data['windows'], $data['recurring_windows']);
    }
    $data['choice'] = one($db, 'SELECT * FROM day_choices WHERE user_id = ? AND plan_day = ?', [$uid, $day])
        ?? ['mode' => 'balance', 'routine' => 'usual', 'prompt_hidden' => 0];
    $data['goals'] = one($db, 'SELECT * FROM goals WHERE user_id = ?', [$uid]) ?? [
        'reading_sessions' => 0, 'busy_days' => 0, 'rest_minutes' => 25, 'gaming_minutes' => 0,
    ];
    $data['feedback'] = array_column(execute($db, 'SELECT * FROM feedback WHERE user_id = ? AND plan_day = ?', [$uid, $day])->fetchAll(), null, 'item_key');
    return $data;
}
