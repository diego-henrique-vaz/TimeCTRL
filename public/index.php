<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
require __DIR__ . '/../app/store.php';
require __DIR__ . '/../app/calendar.php';
require __DIR__ . '/../app/planner.php';
require __DIR__ . '/../app/actions.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
session_name('TimeCTRL');
session_start(['use_strict_mode' => true, 'use_only_cookies' => true, 'cookie_httponly' => true,
    'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

$clock = at(getenv('TIMECTRL_NOW') ?: 'now');
$today = $clock->format('Y-m-d');
$day = is_string($_GET['date'] ?? null) && dateValid($_GET['date']) && $_GET['date'] >= $today ? $_GET['date'] : $today;
$focus = $day === $today ? $clock : at($day . ' 00:00');
$view = is_string($_GET['view'] ?? null) ? $_GET['view'] : 'today';
if (!in_array($view, ['today','tasks','agenda','interests','habits','signup','login'], true)) {
    http_response_code(404);
    $view = 'today';
}

function url(string $view = 'today', array $args = []): string
{
    return '/?' . http_build_query(['view' => $view] + $args);
}

function redirect(string $view = 'today', array $args = []): never
{
    header('Location: ' . url($view, $args), true, 303);
    exit;
}

$error = '';
try {
    $db = database();
    $account = isset($_SESSION['uid']) ? one($db, 'SELECT id, name FROM users WHERE id = ?', [$_SESSION['uid']]) : null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'], field('csrf'))) {
            http_response_code(403);
            throw new DomainException('O formulário expirou. Atualize a página e tente novamente.');
        }
        $action = field('action');
        if (!$account) {
            if (!in_array($action, ['login','signup'], true)) {
                redirect('login');
            }
            authenticate($db, $action);
            redirect();
        }
        if ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            redirect('login');
        }
        $_SESSION['notice'] = runAction($db, (int) $account['id'], $action, $day, $clock);
        redirect($view, $day === $today ? [] : ['date' => $day]);
    }
} catch (DomainException $failure) {
    if (http_response_code() < 400) {
        http_response_code(422);
    }
    $error = $failure->getMessage();
} catch (Throwable $failure) {
    error_log('TimeCTRL: ' . get_class($failure) . ' (' . $failure->getCode() . ')');
    http_response_code(500);
    $error = 'O sistema está indisponível. Confira a configuração local e tente novamente.';
}

$account ??= null;
if (!$account && !in_array($view, ['signup', 'login'], true)) {
    $view = 'login';
} elseif ($account && in_array($view, ['signup','login'], true)) {
    $view = 'today';
}
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);
$data = $account ? userData($db, (int) $account['id'], $day) : null;
$summary = $data ? plan($data, $focus) : null;
$suggestion = $data && $day === $today ? recommendation($data, $summary, $focus) : null;
$holiday = HOLIDAYS[$day] ?? null;
require __DIR__ . '/../views/page.php';
