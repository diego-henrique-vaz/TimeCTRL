<?php
declare(strict_types=1);
require __DIR__ . '/../app/calendar.php';
require __DIR__ . '/../app/planner.php';

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
    echo "OK $message\n";
}

$day = '2026-11-19 ';
$clock = at($day . '18:00');
$a = ['id'=>1,'title'=>'Questionário','minutes'=>30,'due_at'=>$day . '23:59','priority'=>2,'done'=>0];
$b = ['id'=>2,'title'=>'Relatório','minutes'=>90,'due_at'=>'2026-11-20 09:00','priority'=>3,'done'=>0];
$c = ['id'=>3,'title'=>'Empate com prioridade','minutes'=>20,'due_at'=>$day . '23:59','priority'=>3,'done'=>0];
$data = [
    'tasks'=>[$a,$b],
    'windows'=>[
        ['kind'=>'free','start_at'=>$day . '18:00','end_at'=>$day . '18:40'],
        ['kind'=>'free','start_at'=>$day . '20:00','end_at'=>$day . '21:30'],
    ],
    'blocks'=>[], 'activities'=>[], 'interests'=>[], 'feedback'=>[],
    'goals'=>['reading_sessions'=>2,'busy_days'=>2,'rest_minutes'=>20,'gaming_minutes'=>0],
    'choice'=>['mode'=>'balance','routine'=>'usual'],
];
$result = plan($data, $clock);
check($result['taskNow']['id'] === 1 && $result['forecasts'][1]['start'] === $day . '20:00', 'prazo e duração produzem o encaixe do cenário principal');
check(orderedTasks([$a,$b,$c])[0]['id'] === 3, 'prioridade resolve empate de prazo');
check(orderedTasks([$a,$b], true)[0]['id'] === 2, 'ordenação da lista por prioridade');
check(orderedTasks([$a,array_replace($a, ['id'=>4])])[0]['id'] === 1, 'empate de prazo e prioridade mantém uma ordem estável');
check(freeRanges(['windows'=>[$data['windows'][0], ['kind'=>'free','start_at'=>$day.'18:30','end_at'=>$day.'19:00'],
    ['kind'=>'work','start_at'=>$day.'18:10','end_at'=>$day.'18:20']], 'blocks'=>[]])
    === [[$day.'18:00',$day.'18:10'],[$day.'18:20',$day.'19:00']], 'janelas sobrepostas são unidas e trabalho desconta tempo');
$data['blocks'][] = ['id'=>1,'kind'=>'task','item_id'=>2,'title'=>'Relatório','start_at'=>$day.'20:00','end_at'=>$day.'21:30'];
$data['windows'][] = ['kind'=>'busy','start_at'=>$day.'20:00','end_at'=>$day.'21:30'];
$result = plan($data, $clock);
check(count($result['conflicts']) === 1 && count($result['withoutTime']) === 1 && $result['withoutTime'][0]['id'] === 2,
    'compromisso invalida o bloco e gera risco, mesmo fora da recomendação');
$needsRebooking = $data;
$needsRebooking['tasks'] = [$b];
$needsRebooking['windows'][] = ['kind'=>'free','start_at'=>$day.'18:00','end_at'=>$day.'19:30'];
$result = plan($needsRebooking, $clock);
check($result['taskNow'] === null && $result['forecasts'][0]['id'] === 2, 'novo encaixe com conflito fica disponível para reagendar o bloco existente');
check(count(plan($data, at($day.'18:30'))['withoutTime']) === 2, 'janela curta não recebe tarefa maior');
check(count(plan($data, at('2026-11-20 10:00'))['late']) === 2, 'prazos vencidos são distintos de tarefas apenas sem horário');
check(isset(HOLIDAYS['2026-11-20']) && !isset(HOLIDAYS['2026-11-19']) && !isset(HOLIDAYS['2026-06-04']) && !isset(HOLIDAYS['2027-11-20']), 'feriado, dia normal, ponto facultativo e ano sem cobertura');

$data['choice']['mode'] = 'leisure';
$data['interests'] = [
    ['id'=>1,'category'=>'game','title'=>'Jogo','minutes'=>20,'progress'=>'want','session_note'=>''],
    ['id'=>2,'category'=>'book','title'=>'Livro','minutes'=>20,'progress'=>'ongoing','session_note'=>'1 capítulo'],
    ['id'=>3,'category'=>'anime','title'=>'Anime','minutes'=>10,'progress'=>'done','session_note'=>''],
];
check(recommendation($data, plan($data,$clock), $clock)['title'] === 'Livro', 'meta de leitura e duração favorecem o livro');
$data['feedback']['interest:2'] = ['decision'=>'dismiss','until_at'=>''];
check(recommendation($data, plan($data,$clock), $clock)['title'] === 'Jogo', 'interesse dispensado fica fora do painel do dia');
$data['goals']['gaming_minutes'] = 40;
$data['activities'][] = ['log_day'=>'2026-11-17','kind'=>'game','minutes'=>30];
check(recommendation($data, plan($data,$clock), $clock) === null, 'limite configurado bloqueia apenas a sugestão de jogo');
$data['feedback']['interest:2'] = ['decision'=>'later','until_at'=>$day.'18:05'];
$after = at($day.'18:05');
check(recommendation($data, plan($data,$after), $after)['title'] === 'Livro', 'sugestão adiada volta depois do horário');
foreach (['2026-11-18','2026-11-19'] as $date) {
    $data['activities'][] = ['log_day'=>$date,'kind'=>'study','minutes'=>60];
}
$data['tasks'] = $data['blocks'] = [];
$data['choice']['mode'] = 'balance';
check(recommendation($data, plan($data,$clock), $clock)['kind'] === 'rest', 'pausa opcional após sequência registrada');
