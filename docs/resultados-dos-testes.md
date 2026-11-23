# Testes do TimeCTRL

| Verificação | Resultado |
| --- | --- |
| Sintaxe PHP | Todos os arquivos PHP passaram em `php -l`. |
| Regras do planejador | 15 verificações passaram, incluindo desempate, prazos vencidos, tempo insuficiente, conflitos, descanso, leitura e limite de jogo. |
| Persistência | 10 verificações passaram em SQLite e 10 em MariaDB 10.4.32. Foram verificados isolamento por conta, validação de duração, aceite de blocos, sobreposição, conclusão e preferências por dia. |
| Formulários e HTTP | Passaram com MariaDB e SQLite. O teste confirmou persistência após reiniciar o servidor e recusou senha incorreta, data inexistente e intervalo invertido. |
| Chromium | Passaram os fluxos de tarefas, agenda, reagendamento, isolamento de contas e lazer no feriado. |
| Chromium em largura de celular | As cinco telas autenticadas abriram a 390 px sem rolagem horizontal. Dois registros de trabalho ou estudo em dias consecutivos geraram uma pausa sugerida de 15 minutos. O aceite gravou o bloco na agenda. |
| Inicialização local no Windows | `Abrir_TimeCTRL.cmd` iniciou o servidor PHP na porta 8082. A página de login e a folha de estilos responderam e o banco SQLite local foi inicializado. |

## Cenários da demonstração

Em 19/11/2026, o sistema sugeriu um questionário de 30 minutos para uma janela de 40 minutos e um relatório de 90 minutos para as 20h. Uma aula extra ocupou o horário do relatório. O painel exibiu um aviso de conflito e um alerta de tempo insuficiente. Ao cadastrar outra janela válida, foi possível reagendar o relatório e resolver o conflito.

Em 20/11/2026, após a seleção de **Lazer** e **Vou trabalhar no feriado**, o turno cadastrado foi preservado e restaram 25 minutos livres. Com interesses das cinco categorias, o sistema recomendou ler um capítulo de *O Hobbit* em 20 minutos. O teste também conferiu adiar uma sugestão, dispensá-la e abrir outro dia com as preferências padrão.

As datas foram controladas pela variável `TIMECTRL_NOW`. As três capturas em [`evidencias/`](../evidencias/) mostram o aplicativo real com contas fictícias.

## Uso de IA no desenvolvimento

ChatGPT com Codex auxiliou na implementação em PHP, na escrita dos testes e na correção do fluxo de conflito. Um exemplo foi ajustar a recomendação para que uma tarefa com bloco em conflito seja encaminhada ao reagendamento. A regra foi verificada em um teste do planejador e o reagendamento foi exercitado no navegador. O aplicativo calcula suas sugestões a partir de prazos, agenda e preferências, usando regras em PHP.
