const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const { randomUUID } = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { setTimeout: delay } = require('node:timers/promises');

const root = path.resolve(__dirname, '..');
fs.mkdirSync(path.join(root,'var'), {recursive:true});
const dir = fs.mkdtempSync(path.join(root,'var','http-'));
const sessions = path.join(dir,'sessions');
fs.mkdirSync(sessions);
const php = process.env.TIMECTRL_PHP ?? 'C:/xampp/php/php.exe';
const port = Number(process.env.TIMECTRL_HTTP_PORT ?? 8816);
const base = 'http://127.0.0.1:' + port;
const tag = () => randomUUID();

async function serve(clock, run) {
  const server = spawn(php, ['-d','session.save_path=' + sessions,'-S','127.0.0.1:' + port,'-t','public'], {
    cwd:root, windowsHide:true, stdio:['ignore','ignore','pipe'],
    env:{...process.env,TIMECTRL_DB:path.join(dir,'test.sqlite'),TIMECTRL_NOW:clock},
  });
  let log = '';
  server.stderr.on('data', bytes => { log = (log + bytes).slice(-1600); });
  const exit = new Promise(resolve => {server.on('exit',resolve); server.on('error',resolve);});
  try {
    let ok = false;
    for (let i=0; i<40 && !ok; i++) {
      try { ok = (await fetch(base,{signal:AbortSignal.timeout(1200)})).ok; } catch {}
      if (!ok) await delay(250);
    }
    assert(ok, log);
    return await run();
  } finally {
    server.kill();
    await Promise.race([exit,delay(2000)]);
  }
}

function client() {
  let cookie = '', token = '';
  async function call(route, action, fields={}, expected=200, csrf=true) {
    const headers = cookie ? {Cookie:cookie} : {};
    const opts = {headers,redirect:'manual',signal:AbortSignal.timeout(6000)};
    if (action) {
      opts.method='POST';
      opts.body=new URLSearchParams({...fields,action,...(csrf?{csrf:token}:{})});
    }
    const res=await fetch(base+route,opts);
    const next=res.headers.get('set-cookie');
    if (next) cookie=next.split(';')[0];
    const html=await res.text();
    const match=/name="csrf" value="([0-9a-f]+)"/.exec(html);
    if (match) token=match[1];
    assert.equal(res.status,expected,route+' '+action+' '+html.replace(/<[^>]+>/g,' ').trim().slice(-600));
    if (res.status===303) return call(res.headers.get('location'));
    return html;
  }
  return call;
}
const view = p => '/?view='+p;
const account = async (http, name, email, pass) => {
  await http(view('signup'));
  const body = await http(view('signup'),'signup',{name,email,password:pass},303);
  assert.match(body, /O que fazer agora/);
};
const a = client(), b = client();
const email = tag()+'@example.test', password = tag();
const nov19 = '2026-11-19T', nov20 = '2026-11-20T';
async function main() {
  await serve('2026-11-19 18:00', async () => {
    await account(a,'Conta principal',email,password);
    await a(view('agenda'),'add-window',{kind:'free',title:'Intervalo invertido',start_at:nov19+'19:00',end_at:nov19+'18:00'},422);
    await a(view('tasks'),'save-task',{title:'Data inexistente',due_at:'2026-02-30T23:59',minutes:30,priority:2},422);
    for (const [title, start_at, end_at] of [['Após o trabalho',nov19+'18:00',nov19+'18:40'],['Noite livre',nov19+'20:00',nov19+'21:30']])
      await a(view('agenda'),'add-window',{kind:'free',title,start_at,end_at},303);
    await a(view('tasks'),'save-task',{title:'Questionário',due_at:nov19+'23:59',minutes:30,priority:2},303);
    const taskList=await a(view('tasks'),'save-task',{title:'Relatório',due_at:nov20+'09:00',minutes:90,priority:3},303);
    const id=Number(/data-task="(\d+)"/.exec(taskList)[1]);
    assert.doesNotMatch(taskList, /Data inexistente/);
    assert.doesNotMatch(await a(view('agenda')), /Intervalo invertido/);
    const home=await a(view('today'));
    assert.match(home,/data-free-minutes>40/);
    assert.match(home,/data-recommendation="task">\s*<h3>Questionário/);
    assert.match(home,/Relatório<\/h3><p>[^<]*20:00/);
    const proposal=/name="item" value="(\d+)">\s*<input type="hidden" name="start_at" value="2026-11-19 20:00"/.exec(home);
    assert(proposal);
    await a(view('today'),'suggestion',{kind:'task',item:proposal[1],decision:'accept',start_at:'2026-11-19 20:00'},303);
    await a(view('today'),'suggestion',{kind:'task',item:id,decision:'accept',start_at:'2026-11-19 20:00'},422);
    await a(view('agenda'),'add-window',{kind:'busy',title:'Aula extra',start_at:nov19+'20:00',end_at:nov19+'21:30'},303);
    const conflicted=await a(view('today'));
    assert.match(conflicted,/Sem horário antes do prazo.*Relatório precisa de 90 min/);
    assert.equal((conflicted.match(/data-alert="conflict"/g)??[]).length,1);
    assert.equal(((await a(view('today'))).match(/data-alert="conflict"/g)??[]).length,1);
    await account(b,'Segunda conta',tag()+'@example.test',tag());
    assert.doesNotMatch(await b(view('tasks')), /Questionário/);
    await b(view('tasks'),'done-task',{id},422);
    await b(view('tasks'),'done-task',{id},403,false);
    await a(view('tasks'),'done-task',{id},303);
    assert.doesNotMatch(await a(view('tasks')),new RegExp('data-task="'+id+'"'));
    await a(view('today'),'save-choice',{mode:'rest',routine:'usual'},303);
    console.log('OK HTTP: tarefas, horários, encaixe, conflito, reabertura, validação e isolamento de contas.');
  });
  await serve('2026-11-20 18:00', async () => {
    const first=await a(view('today'));
    assert.match(first,/Consciência Negra/);
    assert.match(first,/option value="balance" selected/);
    assert.match(await a(view('tasks')), /Relatório/);
    await a(view('today'),'logout',{},303);
    await a(view('login'));
    await a(view('login'),'login',{email,password:tag()},422);
    await a(view('login'),'login',{email,password},303);
    await a(view('agenda'),'add-window',{kind:'free',title:'Fim de tarde',start_at:nov20+'18:00',end_at:nov20+'19:00'},303);
    await a(view('agenda'),'add-window',{kind:'work',title:'Turno',start_at:nov20+'18:25',end_at:nov20+'19:00'},303);
    for(const [category, title, minutes] of [['book','O Hobbit',20],['game','Jogo salvo',40],['movie','Filme salvo',90],['series','Série salva',45],['anime','Anime salvo',27]]){
      await a(view('interests'),'save-interest',{category,title,minutes,progress:'want',session_note:category==='book'?'Ler um capítulo':''},303);
    }
    const reading=await a(view('today'),'save-choice',{mode:'leisure',routine:'works'},303);
    assert.match(reading,/data-free-minutes>25/);
    assert.match(reading,/data-recommendation="interest">\s*<h3>O Hobbit/);
    assert.match(reading,/Sua agenda de trabalho foi mantida/);
    const item=/name="kind" value="interest">\s*<input type="hidden" name="item" value="(\d+)"/.exec(reading)[1];
    assert.doesNotMatch(await a(view('today'),'suggestion',{kind:'interest',item,decision:'dismiss'},303),/data-recommendation="interest"/);
    console.log('OK HTTP: persistência após reinício, login, virada do dia, feriado, cinco tipos de interesse e sugestão de leitura.');
  });
}
main().catch(error=>{console.error(error.message);process.exitCode=1;});
