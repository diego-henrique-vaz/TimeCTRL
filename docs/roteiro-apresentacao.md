# Roteiro do TimeCTRL

Ensaio de aproximadamente 6 minutos. Apresentação de Tópicos Especiais em Informática, professor David Silva.

| Tempo | Pessoa | Tela e fala |
| --- | --- | --- |
| 0:00–1:10 | Diego Henrique Pereira Gaig | **Slides 1 e 2.** “Quem estuda e trabalha costuma ter períodos curtos livres. Uma lista mostra o que está pendente, mas ainda precisamos decidir o que cabe antes do próximo compromisso. O TimeCTRL cruza prazo, duração e agenda. Se for uma hora de lazer, também sugere algo que a pessoa já queria fazer.” |
| 1:10–2:05 | Bruno Lealdine Rodrigues de Araujo | **Slide 3 e tela Tarefas.** “Temos um questionário de 30 minutos que vence hoje e um relatório de 90 minutos que vence amanhã. O painel prioriza os prazos e usa prioridade no desempate. Com 40 minutos agora, o questionário cabe. O relatório fica para as 20h.” Abra **Meu dia** e aceite o horário do relatório. |
| 2:05–3:00 | Bruno | **Slide 4 e tela Agenda.** Cadastre **Aula extraordinária**, tipo **Compromisso**, em 19/11/2026, das 20h às 21h30. Mostre o bloco marcado como **Conflito**. Volte ao painel e mostre o alerta de que restou tempo insuficiente para o relatório. “Assim o usuário sabe qual tarefa precisa de um novo horário e pode ajustá-lo pela agenda.” |
| 3:00–4:00 | Miguel França Vita | **Slide 5 e painel do feriado.** Escolha **Lazer** e **Vou trabalhar no feriado**. “O turno fica registrado e ainda restam 25 minutos livres. Das cinco categorias, a sessão cadastrada que cabe é ler um capítulo de *O Hobbit*, estimado em 20 minutos. A pessoa pode aceitar, adiar ou dispensar.” |
| 4:00–5:05 | Miguel | **Slide 6.** “As telas enviam formulários para o PHP, que valida os dados, calcula as janelas e usa o banco para guardar as informações da conta. Usamos PHP por ser a tecnologia trabalhada nas práticas e MariaDB para armazenamento relacional. Para demonstrar localmente, também há suporte a SQLite. Aplicamos modelagem, CRUD, sessões e consultas parametrizadas.” |
| 5:05–6:00 | Diego | **Slide 7.** “Testamos o fluxo no navegador, no tamanho de celular e com dois bancos. Uma dificuldade foi manter o conflito visível quando muda a agenda. O Codex ajudou a corrigir e testar o encaminhamento para o reagendamento. Um modelo possível é oferecer as funções atuais gratuitamente e uma assinatura futura para sincronização de calendários.” |

## Preparar a demonstração

1. No PowerShell, abra a pasta do projeto. Execute `& 'C:\xampp\php\php.exe' scripts/demo.php tarefas` e guarde os dados temporários no terminal. Defina `$env:TIMECTRL_NOW = '2026-11-19 18:00'` e rode `& 'C:\xampp\php\php.exe' -S 127.0.0.1:8083 -t public`. Entre na conta fictícia em `http://127.0.0.1:8083`.
2. Em outro PowerShell, dentro da mesma pasta, execute `& 'C:\xampp\php\php.exe' scripts/demo.php feriado`. Defina `$env:TIMECTRL_NOW = '2026-11-20 18:00'` e rode `& 'C:\xampp\php\php.exe' -S 127.0.0.1:8084 -t public`. Abra esse endereço em uma janela privada e use a segunda conta, mantendo os dois logins independentes.
3. Prepare as telas da primeira conta antes de aceitar o relatório e da segunda antes de escolher **Lazer**. Se o aplicativo ficar indisponível, use as três capturas dos slides para narrar o fluxo.
4. Depois do ensaio, pare apenas os dois servidores de demonstração com `Ctrl+C`. Em cada terminal, execute `Remove-Item Env:TIMECTRL_NOW` para voltar ao relógio normal.

## Perguntas do professor

- **Como o painel escolhe a tarefa?** Filtra pendências que cabem na janela livre e terminam até o prazo. A ordem usa prazo e depois prioridade.
- **O feriado libera automaticamente o dia?** O calendário exibe a data e abre a escolha da rotina. Os compromissos cadastrados continuam ocupando suas janelas.
- **Como funciona a IA?** O grupo usou ChatGPT com Codex para desenvolver e testar. As recomendações do aplicativo vêm de regras em PHP usando os dados inseridos pelo usuário.
- **O que acontece quando não há um horário?** O painel mostra a duração e o prazo da tarefa junto ao alerta. É possível alterar a disponibilidade ou ajustar um bloco existente.
- **Por que poderia ser cobrado como SaaS?** O acesso seria por conta na web. Uma assinatura futura poderia oferecer sincronização de calendários. No MVP o fluxo roda localmente.

## Exercício comercial da pequena loja — até 2 minutos

- **0:00–0:35 · Diego.** “Começamos perguntando como a loja registra entradas, vendas e perdas, qual erro custa mais e quantas pessoas ou unidades usarão o sistema. Assumimos uma unidade, um responsável e até 200 produtos. O cliente ainda precisa confirmar esses dados.”
- **0:35–1:10 · Bruno.** “Propusemos login, cadastro de produtos, registro de entradas e saídas e consulta com alertas. Um teste de aceite é dar entrada em 10 unidades, dar saída em 3 e confirmar o saldo 7. Emissão fiscal, filiais, loja online e WhatsApp ficaram para um orçamento posterior.”
- **1:10–1:55 · Miguel.** “Estimamos 48 horas a R$ 75, que dão R$ 3.600 de mão de obra. Despesas, reserva, provisão de taxas e lucro levam a R$ 5.400. O prazo é de 15 dias úteis com início após aprovação, entrada e dados. Para loja virtual e WhatsApp, levantamos os requisitos e enviamos um preço e prazo para a segunda etapa.”

As perguntas completas, o cálculo e a proposta estão em [exercicio-orcamento.md](exercicio-orcamento.md).
