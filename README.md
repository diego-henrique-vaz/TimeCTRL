# TimeCTRL

Agenda web para estudantes que trabalham. O sistema cruza prazos, tempo livre e interesses informados pelo usuário para sugerir o próximo passo.

**Integrantes:** Diego Henrique Pereira Gaiguerra Vaz, Bruno Lealdine Rodrigues de Araujo e Miguel França Vita.

**Disciplina:** Tópicos Especiais em Informática · Prof. David Silva.

## Abrir no Windows

Dê dois cliques em `Abrir_TimeCTRL.cmd`. O arquivo inicia o PHP e abre [http://127.0.0.1:8082](http://127.0.0.1:8082) no navegador padrão quando a página estiver pronta. A primeira abertura inicializa o banco local. Mantenha a janela do servidor aberta durante o uso. Se a aba não abrir, digite o endereço no navegador. Use `TIMECTRL_PORT` para trocar a porta caso a 8082 já esteja ocupada.

Na pasta que contém este README, abra o PowerShell e execute:

```powershell
$php = 'C:\xampp\php\php.exe'
& $php -S 127.0.0.1:8080 -t public
```

Abra [http://127.0.0.1:8080](http://127.0.0.1:8080) e crie uma conta. Mantenha o terminal aberto. Se o PHP estiver no PATH, use `php` no lugar do caminho do XAMPP. São necessários PHP 8.2+, `mbstring` e `pdo_sqlite` ou `pdo_mysql`. No modo padrão, o banco SQLite é criado em `var/timectrl.sqlite`.

### MariaDB no XAMPP

Inicie MySQL no painel do XAMPP. Crie um banco `timectrl`, um usuário com acesso local e uma senha própria. Importe `database/schema.sql` nesse banco pelo phpMyAdmin ou pelo cliente MariaDB. Copie `config.example.php` para `config.local.php` e ajuste host, porta, banco, usuário e senha. Depois inicie o servidor PHP com o comando da seção anterior. O aplicativo usa MariaDB quando essa configuração está presente.

## Fluxo de uso

1. Cadastre tarefas com prazo, duração e prioridade.
2. Informe seus horários livres e compromissos na agenda.
3. Em **Meu dia**, escolha entre tarefas, lazer, descanso ou equilíbrio. O painel mostra o tempo que sobra depois dos blocos confirmados. Em equilíbrio, sugere lazer e, quando há tarefas já marcadas, oferece também uma pausa se houver espaço após os encaixes. Se o lazer coincidir com um encaixe de tarefa ainda não aceito, o painel avisa antes de você decidir. No foco descanso, a pausa usa a duração preferida ou se ajusta a um intervalo menor, a partir de cinco minutos. Os alertas de prazos continuam disponíveis.
4. Em **Interesses**, salve jogos, filmes, séries, animes e livros. A duração é o tempo estimado da próxima sessão. O campo de detalhe pode conter algo como “ler um capítulo”.
5. Em **Hábitos**, informe as atividades que já fez e configure metas opcionais. Ao concluir uma tarefa ou marcar uma pausa ou sessão de lazer como feita na agenda, confira a duração e, se necessário, ajuste a data. A atividade vai para o histórico com seu título. Leitura e jogos entram nas metas semanais, e as sugestões evitam repetir a pausa ou o mesmo interesse já registrado naquele dia. O tempo disponível no dia determina o que cabe. Um limite semanal de jogo igual a zero desativa esse limite.

### Rotina semanal e exceções

Em **Agenda → Rotina semanal**, escolha os dias, os horários e a data inicial. Cadastre turnos de trabalho, compromissos fixos ou janelas livres. As sugestões usam os próximos 90 dias de rotina a partir da data consultada. Para consultar outra data, use as setas ou o seletor da agenda.

Para uma janela de disponibilidade dentro do expediente, escolha **Livre durante o trabalho** ao cadastrar ou editar o intervalo. O mesmo tipo pode entrar na rotina semanal. Ele libera aquele trecho do turno e respeita outros compromissos e blocos aceitos. Se a oportunidade surgir na hora, use **Estou livre agora** em **Meu dia** e informe quantos minutos você tem.

Ao abrir um horário recorrente em **Intervalos cadastrados**, use **Salvar só este dia** para mudar os horários ou **Cancelar só este dia** para pular o compromisso nessa data. Marque **Marcar o tempo liberado como livre** se quiser usar o período retirado para encaixar tarefas ou lazer. Outros compromissos continuam descontando tempo. **Restaurar horário habitual** desfaz o ajuste. Um bloco de tarefa pode ser concluído diretamente pela agenda e devolve o horário reservado ao tempo disponível.

As tabelas adicionais são criadas automaticamente no primeiro acesso, inclusive para bancos existentes. Se a conta MariaDB do aplicativo tiver acesso apenas a dados, importe `database/agenda-recorrente.sql` e `database/atividades-concluidas.sql` como administrador antes de abrir a agenda.

### Plano com IA

Em **Meu dia → Plano com IA**, use **Gerar plano com IA** para ver uma sequência de tarefas, pausas e lazer na data selecionada. Os blocos confirmados aparecem com a marca **Na agenda**. Clique em **Agendar tarefa**, **Agendar descanso** ou **Agendar lazer** para reservar um horário sugerido. Depois de salvar, gere o plano novamente para atualizar a sequência.

**IA local gratuita.** O modo padrão roda no PHP. Uma busca compara ordens de encaixe de até seis tarefas aptas para a data e prioriza as entregas conforme prazo e prioridade. Com os horários de tarefa definidos, sugere pausas de até 15 minutos entre eles, conforme a disponibilidade. Se houver um interesse que caiba, ele aparece como opção de lazer. Os focos **Descanso** e **Lazer** preservam os compromissos e deixam as novas tarefas para outro plano. Para escolher este modo quando já houver uma chave externa, defina `'provider' => 'local'` em `ai.local.php`.

**OpenRouter.** A opção por API continua disponível com `'provider' => 'openrouter'`.

1. Crie uma chave em [OpenRouter → API Keys](https://openrouter.ai/settings/keys). O modelo padrão `openai/gpt-4.1-mini` é pago por uso. Os custos do modelo e os limites da chave são gerenciados no OpenRouter.
2. Copie `ai.example.php` para `ai.local.php` na raiz do projeto, defina `'provider' => 'openrouter'` e preencha `key` nesse arquivo. Ele fica fora da pasta `public/` e é ignorado pelo Git. Em um servidor, também é possível fornecer `TIMECTRL_AI_PROVIDER=openrouter` e `TIMECTRL_OPENROUTER_KEY` pelo ambiente do processo PHP.
3. Abra **Meu dia**, cadastre tarefas com dados fictícios e gere o plano. A integração requer internet e a extensão PHP `curl`. Para trocar o modelo, use `model` no arquivo local ou `TIMECTRL_OPENROUTER_MODEL` no ambiente. Escolha um modelo compatível com `json_schema` e disponível em uma rota com retenção zero.

No modo OpenRouter, o aplicativo só consulta a API quando você solicita a geração. O modelo recebe o dia, o foco, a duração das janelas livres, os prazos, as prioridades e a duração de até seis tarefas, identificadas por referências temporárias. O TimeCTRL valida a ordenação e calcula os horários localmente. Os pedidos solicitam rotas ZDR conforme a política da conta e exigem resposta estruturada. Sem um modelo disponível, a agenda e suas sugestões locais continuam funcionando. Execute `php tests/ai.php` para verificar a busca local por horários, as pausas e a preservação de blocos confirmados e simular a integração com o OpenRouter.

[Respostas estruturadas do OpenRouter](https://openrouter.ai/docs/guides/features/structured-outputs) · [Retenção zero](https://openrouter.ai/docs/guides/features/zdr)

As recomendações são calculadas em PHP. O banco guarda contas, dados acadêmicos e preferências. Os avisos aparecem no painel ao consultar ou atualizar o dia. O calendário local cobre os feriados nacionais brasileiros de 2026 classificados no art. 1º da Portaria MGI nº 11.460/2025. Pontos facultativos e feriados locais ficam fora dessa cobertura. A data do feriado não muda a agenda automaticamente.

[Portaria MGI nº 11.460/2025](https://legis.sigepe.gov.br/sigepe-bgp-ws-legis/legis-service/download/?id=0026440285-ALPDF%2F2025)

## Repetir a demonstração

Em um terminal da pasta do projeto, execute `php scripts/demo.php tarefas` para gerar uma conta fictícia. Use o e-mail e a senha local exibidos no terminal. No PowerShell, defina `$env:TIMECTRL_NOW = '2026-11-19 18:00'` antes de iniciar o servidor PHP. O painel sugere um questionário de 30 minutos agora e um relatório de 90 minutos às 20h. Aceite o relatório e cadastre um compromisso das 20h às 21h30. Confira o aviso de conflito.

Para repetir a leitura no feriado, pare o servidor, execute `php scripts/demo.php feriado` e inicie o servidor com `$env:TIMECTRL_NOW = '2026-11-20 18:00'`. Entre com a nova conta de teste e escolha **Lazer** e **Vou trabalhar no feriado** no painel. A janela disponível será de 25 minutos, com sugestão de uma leitura de 20 minutos. Para voltar ao relógio normal, pare o servidor, execute `Remove-Item Env:TIMECTRL_NOW` e inicie novamente.

## Testes

- `php tests/planner.php` verifica encaixe, prioridade, conflitos, feriados, lazer e descanso.
- `php tests/storage.php` verifica as ações com persistência. Defina `TIMECTRL_DB` para um SQLite de teste ou `TIMECTRL_CONFIG` para um banco MariaDB isolado com o esquema importado. O teste cria contas fictícias.
- `node tests/http.cjs` confere os formulários, login e persistência após reinício do servidor. Sem configuração MariaDB, o teste cria um SQLite descartável.
- `node tests/browser.cjs` executa o ensaio no Chromium e salva capturas em `evidencias/`. Requer Node.js, Playwright e o navegador obtido com `npx playwright install chromium`. Para usar um executável de Chromium já instalado, indique seu caminho com `TIMECTRL_BROWSER`. Se necessário, indique o PHP com `TIMECTRL_PHP`. Execute com um banco isolado.

Veja os [resultados dos testes](docs/resultados-dos-testes.md) e a [atividade comercial da apostila](docs/exercicio-orcamento.md).

Para a publicação, mantenha `config.local.php`, a pasta `var/` e credenciais reais fora do repositório.
