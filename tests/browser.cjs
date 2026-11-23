const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const { request } = require('node:http');
const { randomUUID } = require('node:crypto');
const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');
const { setTimeout: delay } = require('node:timers/promises');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const tempParent = path.join(root, 'var');
fs.mkdirSync(tempParent, { recursive: true });
const data = fs.mkdtempSync(path.join(tempParent, 'test-'));
const sessions = path.join(data, 'sessions');
const screenshots = path.join(root, 'evidencias');
fs.mkdirSync(sessions);
fs.mkdirSync(screenshots, { recursive: true });
const php = process.env.TIMECTRL_PHP ?? 'C:/xampp/php/php.exe';
const executable = process.env.TIMECTRL_BROWSER;
const processes = [];

function availablePort() {
  return new Promise((resolve, reject) => {
    const socket = net.createServer();
    socket.on('error', reject);
    socket.listen(0, '127.0.0.1', () => {
      const port = socket.address().port;
      socket.close(() => resolve(port));
    });
  });
}

function get(address) {
  return new Promise((resolve, reject) => {
    const req = request(address, { timeout: 1300 }, res => {
      let body = '';
      res.setEncoding('utf8');
      res.on('data', chunk => { body += chunk; });
      res.on('end', () => resolve({ status: res.statusCode, body }));
    });
    req.on('timeout', () => req.destroy());
    req.on('error', reject);
    req.end();
  });
}

async function waitAt(url, check) {
  for (let i = 0; i < 35; i++) {
    try {
      const response = await get(url);
      if (check(response)) return;
    } catch {}
    await delay(260);
  }
  throw Error('Tempo limite ao iniciar ' + url);
}

function start(exe, args, env = process.env) {
  const proc = spawn(exe, args, { env, cwd: root, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
  processes.push(proc);
  proc.on('error', error => console.error(error.message));
  let log = '';
  proc.stderr.on('data', chunk => { log = (log + chunk).slice(-3000); });
  proc.logText = () => log;
  return proc;
}

async function serve(clock) {
  const port = await availablePort();
  const url = 'http://127.0.0.1:' + port;
  const proc = start(php, ['-d', 'session.save_path=' + sessions, '-S', '127.0.0.1:' + port, '-t', 'public'], {
    ...process.env, TIMECTRL_DB: path.join(data, 'test.sqlite'), TIMECTRL_NOW: clock,
  });
  try {
    await waitAt(url, res => res.status === 200 && res.body.includes('Acesse sua agenda'));
  } catch (error) {
    throw Error(error.message + '\n' + proc.logText());
  }
  return url;
}

function at(base, view) { return base + '/?view=' + view; }
function form(page, name) { return page.locator('form').filter({has: page.locator('input[name="action"][value="' + name + '"]')}).first(); }

async function register(page, base, name) {
  await page.goto(at(base, 'signup'));
  await page.getByLabel('Seu nome').fill(name);
  await page.getByLabel('E-mail').fill(randomUUID() + '@example.test');
  await page.getByLabel('Senha').fill(randomUUID());
  await page.getByRole('button', {name:'Criar conta'}).click();
  await page.getByRole('heading', {name:'Meu dia'}).waitFor();
}

async function windowForm(page, base, start, end, kind = 'free', title = '') {
  await page.goto(at(base, 'agenda'));
  const entry = form(page, 'add-window');
  await entry.locator('[name=kind]').selectOption(kind);
  await entry.locator('[name=title]').fill(title);
  await entry.locator('[name=start_at]').fill(start);
  await entry.locator('[name=end_at]').fill(end);
  await entry.getByRole('button', {name:'Adicionar à agenda'}).click();
  await page.getByRole('status').getByText('Intervalo cadastrado.').waitFor();
}

async function taskForm(page, base, title, due, minutes, priority) {
  await page.goto(at(base, 'tasks'));
  const entry = form(page, 'save-task');
  await entry.locator('[name=title]').fill(title);
  await entry.locator('[name=due_at]').fill(due);
  await entry.locator('[name=minutes]').fill(String(minutes));
  await entry.locator('[name=priority]').selectOption(String(priority));
  await entry.getByRole('button', {name:'Cadastrar tarefa'}).click();
  await page.getByRole('status').getByText('Tarefa cadastrada.').waitFor();
}

async function interestForm(page, base, title, kind, mins, note = '') {
  await page.goto(at(base, 'interests'));
  const entry = form(page, 'save-interest');
  await entry.locator('[name=title]').fill(title);
  await entry.locator('[name=category]').selectOption(kind);
  await entry.locator('[name=minutes]').fill(String(mins));
  await entry.locator('[name=session_note]').fill(note);
  await entry.getByRole('button', {name:'Salvar interesse'}).click();
  await page.getByRole('status').getByText('Alteração salva.').waitFor();
}

async function main() {
  const normal = await serve('2026-11-19 18:00');
  const holiday = await serve('2026-11-20 18:00');
  let chrome;
  let browser;
  try {
    if (executable) {
      const port = await availablePort();
      chrome = start(executable, ['--headless=new', '--no-sandbox', '--disable-gpu', '--disable-breakpad', '--no-first-run', '--disable-default-apps',
        '--disable-background-networking', '--remote-debugging-port=' + port,
        '--user-data-dir=' + path.join(data, 'browser'), 'about:blank']);
      const cdp = 'http://127.0.0.1:' + port;
      await waitAt(cdp + '/json/version', res => res.status === 200 && res.body.includes('webSocketDebuggerUrl'));
      browser = await chromium.connectOverCDP(cdp, {timeout:12000});
    } else {
      browser = await chromium.launch({headless:true, timeout:15000, args:['--disable-gpu']});
    }
    console.log('Navegador de teste iniciado.');
    const context = await browser.newContext({ viewport: { width: 1280, height: 895 } });
    const page = await context.newPage();
    page.setDefaultTimeout(8500);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const style = page.waitForResponse(r => r.url().endsWith('/assets/app.css') && r.ok(), {timeout:10000}).catch(() => null);
    await page.goto(normal);
    assert(await style, 'CSS deve carregar');
    await register(page, normal, 'Conta de demonstração');
    await windowForm(page, normal, '2026-11-19T18:00', '2026-11-19T18:40', 'free', 'Depois do trabalho');
    await windowForm(page, normal, '2026-11-19T20:00', '2026-11-19T21:30', 'free', 'Bloco da noite');
    await taskForm(page, normal, 'Questionário de redes', '2026-11-19T23:59', 30, 2);
    await taskForm(page, normal, 'Relatório de laboratório', '2026-11-20T09:00', 90, 3);
    await page.getByRole('link', {name:'Por prioridade'}).click();
    assert.match(await page.locator('[data-task]').first().innerText(), /Relatório/);
    await page.getByRole('link', {name:'Por prazo'}).click();
    assert.match(await page.locator('[data-task]').first().innerText(), /Questionário/);
    await page.screenshot({path:path.join(screenshots, '01-tarefas.png'), fullPage:true});
    const questionnaire = page.locator('[data-task]').filter({hasText:'Questionário'});
    const taskId = await questionnaire.getAttribute('data-task');
    await questionnaire.locator('summary').click();
    await questionnaire.locator('[name=title]').fill('Questionário de redes revisado');
    await questionnaire.getByRole('button', {name:'Salvar edição'}).click();
    await page.getByRole('status').getByText('Alteração salva.').waitFor();

    await page.goto(normal);
    assert.match(await page.locator('[data-free-minutes]').innerText(), /40 min/);
    assert.match(await page.locator('[data-recommendation]').innerText(), /Questionário de redes revisado/);
    const proposed = page.locator('[data-forecast]').filter({hasText:'Relatório de laboratório'});
    assert.match(await proposed.innerText(), /20:00/);
    await proposed.getByRole('button', {name:'Aceitar horário'}).click();
    await page.getByRole('status').getByText('Sugestão adicionada à agenda.').waitFor();

    const csrf = await page.locator('input[name=csrf]').first().inputValue();
    const conflicting = await page.request.post(at(normal, 'today'), {form:{
      action:'suggestion', csrf, kind:'task', item:taskId, decision:'accept', start_at:'2026-11-19 20:00',
    }});
    assert.equal(conflicting.status(), 422);
    await windowForm(page, normal, '2026-11-19T20:00', '2026-11-19T21:30', 'busy', 'Aula extraordinária');
    assert.match(await page.locator('[data-block]').innerText(), /Conflito/);
    await page.screenshot({path:path.join(screenshots, '02-agenda.png'), fullPage:true});
    await page.goto(normal);
    assert.match(await page.locator('[data-alert=no-slot]').innerText(), /Relatório de laboratório precisa de 90 min/);
    assert.equal(await page.locator('[data-alert=conflict]').count(), 1);
    await page.reload();
    assert.equal(await page.locator('[data-alert=conflict]').count(), 1);
    assert.equal(await page.locator('[data-alert=no-slot]').count(), 1);
    await windowForm(page, normal, '2026-11-19T21:30', '2026-11-19T23:00', 'free', 'Novo horário da noite');
    const block = page.locator('[data-block]').filter({hasText:'Relatório de laboratório'});
    await block.locator('summary').click();
    await block.locator('[name=start_at]').fill('2026-11-19T21:30');
    await block.getByRole('button', {name:'Reagendar bloco'}).click();
    await page.getByRole('status').waitFor();
    assert.match(await block.innerText(), /21:30/);
    assert.doesNotMatch(await block.innerText(), /Conflito/);

    const second = await browser.newContext({ viewport: { width: 1280, height: 895 } });
    const other = await second.newPage();
    other.setDefaultTimeout(8500);
    await register(other, normal, 'Outra conta');
    assert.doesNotMatch(await other.locator('main').innerText(), /Relatório de laboratório/);
    const foreign = await other.request.post(at(normal, 'tasks'), {form:{
      action:'done-task', id:taskId, csrf:await other.locator('input[name=csrf]').first().inputValue(),
    }});
    assert.equal(foreign.status(), 422);
    const tokenless = await other.request.post(at(normal, 'tasks'), { form: {action:'done-task', id:taskId} });
    assert.equal(tokenless.status(), 403);
    await second.close();

    await page.goto(at(normal,'tasks'));
    const task = page.locator('[data-task="'+taskId+'"]');
    await task.locator('summary').click();
    await task.getByRole('button', {name:'Concluir tarefa'}).click();
    await page.getByRole('status').getByText('Tarefa concluída.').waitFor();
    assert.equal(await page.locator('[data-task="'+taskId+'"]').count(), 0);
    console.log('OK navegador: tarefas, encaixe, conflito, reagendamento, rejeição de sobreposição, isolamento e avisos sem duplicatas');
    await context.close();

    const otherDay = await browser.newContext({ viewport: { width: 1280, height: 895 } });
    const leisure = await otherDay.newPage();
    leisure.setDefaultTimeout(8500);
    await register(leisure, holiday, 'Conta de lazer');
    await windowForm(leisure, holiday, '2026-11-20T18:00', '2026-11-20T19:00', 'free', 'Tempo para mim');
    await windowForm(leisure, holiday, '2026-11-20T18:25', '2026-11-20T19:00', 'work', 'Turno de trabalho no feriado');
    for (const [title, category, mins, note] of [
      ['O Hobbit','book',20,'Ler um capítulo'],['Jogo de aventura','game',40],['Filme salvo','movie',100],
      ['Série salva','series',45],['Anime salvo','anime',27],
    ]) await interestForm(leisure, holiday, title, category, mins, note);
    assert.equal(await leisure.locator('[data-interest]').count(), 5);
    await leisure.goto(holiday);
    assert.match(await leisure.locator('[aria-label="Feriado nacional"]').innerText(), /Consciência Negra/);
    const focus = form(leisure, 'save-choice');
    await focus.locator('[name=mode]').selectOption('leisure');
    await focus.locator('[name=routine]').selectOption('works');
    await focus.getByRole('button', {name:'Salvar foco'}).click();
    await leisure.getByRole('status').getByText('Preferência do dia atualizada.').waitFor();
    assert.match(await leisure.locator('[data-free-minutes]').innerText(), /25 min/);
    assert.match(await leisure.locator('[data-recommendation=interest]').innerText(), /O Hobbit/);
    assert.match(await leisure.locator('[data-recommendation=interest]').innerText(), /Ler um capítulo/);
    await leisure.screenshot({path:path.join(screenshots, '03-leitura-feriado.png'), fullPage:true});
    await leisure.locator('[data-recommendation]').getByRole('button', {name:'Adiar'}).click();
    await leisure.getByRole('status').getByText('Sugestão adiada por uma hora.').waitFor();
    assert.equal(await leisure.locator('[data-recommendation]').count(), 0);
    const dayChoice = form(leisure, 'save-choice');
    await dayChoice.locator('[name=mode]').selectOption('rest');
    await dayChoice.getByRole('button', {name:'Salvar foco'}).click();
    await leisure.locator('[data-recommendation=rest]').getByRole('button', {name:'Dispensar'}).click();
    await leisure.getByRole('status').getByText('Sugestão dispensada para este dia.').waitFor();
    assert.equal(await leisure.locator('[data-recommendation]').count(), 0);
    await leisure.goto(at(holiday,'today') + '&date=2026-11-21');
    assert.equal(await form(leisure,'save-choice').locator('[name=mode]').inputValue(), 'balance');
    assert.equal(await leisure.locator('[aria-label="Feriado nacional"]').count(), 0);
    assert.deepEqual(errors, []);
    console.log('OK navegador: cinco categorias, leitura de 20 min, feriado com turno mantido, adiar, dispensar e preferências por dia');
    await otherDay.close();

    const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 1 });
    const phone = await mobile.newPage();
    phone.setDefaultTimeout(8500);
    await register(phone, normal, 'Ensaio de hábitos');
    await windowForm(phone, normal, '2026-11-19T18:00', '2026-11-19T19:00');
    await phone.goto(at(normal, 'habits'));
    const goals = form(phone, 'save-goals');
    await goals.locator('[name=busy_days]').fill('2');
    await goals.locator('[name=rest_minutes]').fill('15');
    await goals.locator('button').click();
    await phone.getByRole('status').getByText('Alteração salva.').waitFor();
    const log = form(phone, 'add-activity');
    for (const [kind, date] of [['work', '2026-11-18'], ['study', '2026-11-19']]) {
      await log.locator('[name=kind]').selectOption(kind);
      await log.locator('[name=log_day]').fill(date);
      await log.locator('[name=minutes]').fill('60');
      await log.locator('button').click();
      await phone.getByRole('status').getByText('Alteração salva.').waitFor();
    }
    await phone.goto(normal);
    const pause = phone.locator('[data-recommendation=rest]');
    assert.match(await pause.innerText(), /2 dias seguidos/);
    assert.match(await pause.innerText(), /18:00–18:15/);
    await pause.getByRole('button', {name:'Aceitar horário'}).click();
    await phone.getByRole('status').getByText('Sugestão adicionada à agenda.').waitFor();
    await phone.goto(at(normal, 'agenda'));
    assert.match(await phone.locator('[data-block]').innerText(), /Pausa/);
    for (const view of ['today', 'tasks', 'agenda', 'interests', 'habits']) {
      await phone.goto(at(normal, view));
      assert(await phone.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'rolagem horizontal em ' + view);
    }
    await mobile.close();
    console.log('OK navegador móvel: cinco telas sem rolagem horizontal, metas editadas, hábitos cadastrados e pausa aceita');
  } catch (error) {
    if (chrome) console.error(chrome.logText().slice(-1200));
    throw error;
  } finally {
    if (browser) await Promise.race([browser.close(), delay(2000)]);
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; }).finally(() => {
  for (const proc of processes.reverse()) {
    if (proc.exitCode === null && !proc.killed) proc.kill();
  }
});
