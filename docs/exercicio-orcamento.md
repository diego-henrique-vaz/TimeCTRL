# Do pedido ao orçamento — pequena loja

Atividade da página 5 da apostila *Do problema do cliente ao orçamento de um sistema*. Caso simulado para a disciplina de Tópicos Especiais em Informática, professor David Silva.

**Grupo:** Diego Henrique Pereira Gaig, Bruno Lealdine Rodrigues de Araujo e Miguel França Vita.

## A. Perguntas ao cliente

| Pergunta | Por que perguntar |
| --- | --- |
| **1. Como vocês registram entradas, vendas e perdas hoje?** | Prioritária. Permite entender o processo e descobrir em quais pontos o estoque registrado diverge da contagem real. |
| **2. Qual erro mais prejudica a loja e qual resultado faria a primeira versão valer a pena?** | Prioritária. Mostra se o problema central é estoque, caixa, vendas ou outra necessidade. Define o que entregar primeiro. |
| **3. Quantas pessoas e unidades usarão o sistema?** | Prioritária. Uma loja com um responsável permite um fluxo simples. Acesso simultâneo, filiais e permissões aumentam o esforço. |
| 4. Quantos produtos vocês têm e os cadastros já estão em alguma planilha? | Mostra se o cadastro inicial é viável à mão e se vale orçar importação. |
| 5. Já usam sistema de venda ou emissão fiscal que a nova ferramenta precisaria consultar? | Identifica integrações, fornecedores e dependências de prazo. |
| 6. Existe uma data importante para a implantação e uma faixa de investimento? | Ajuda a escolher a primeira entrega e os marcos de pagamento. |
| 7. Quem vai aprovar os critérios e validar a primeira versão? | Define quem testa, decide mudanças e registra o aceite. |

**Hipóteses do grupo.** A loja tem uma unidade, um responsável e até 200 produtos. As movimentações são anotadas manualmente e o maior problema é saber quanto ainda há de cada produto. A primeira versão será web e o responsável fará o cadastro inicial e os lançamentos manuais. Essas condições precisam de validação com o cliente.

**Confirmação com o cliente.** Hoje vocês anotam as movimentações manualmente. O saldo acaba divergindo da contagem. A primeira entrega permitirá registrar entradas e saídas e conferir o saldo e o histórico de uma unidade. Esse recorte atende à necessidade inicial?

## B. Primeira versão

A pequena loja precisa descobrir o saldo atual de cada produto e identificar como esse saldo mudou ao longo do tempo.

| Funcionalidade | Como conferir |
| --- | --- |
| Acesso do responsável | O responsável entra com sua conta e pode sair. Ao sair, a sessão deixa de abrir as telas internas. |
| Cadastro de produtos | Um produto salvo com código único, nome e estoque mínimo aparece na lista. O mesmo código é recusado em outro cadastro. |
| Entradas e saídas | Após registrar entrada de 10 e saída de 3, o saldo passa a 7. Uma saída de 8 é recusada e o saldo segue em 7. |
| Histórico e estoque baixo | O produto mostra as duas movimentações com tipo, quantidade e data. Se o estoque mínimo for 8, o painel sinaliza o saldo de 7. |

Ficam fora a loja virtual, o WhatsApp, a emissão fiscal, a importação automática e o atendimento a filiais. O cliente fornece a lista inicial de produtos, códigos, unidades de medida, quantidades de abertura, mínimos de estoque e o contato do responsável pelo aceite. A proposta considera que o responsável faz os cadastros com orientação.

## C. Horas, preço e proposta

| Etapa | Esforço |
| --- | ---: |
| Conversa, levantamento e escopo | 4 h |
| Modelagem, telas e fluxo | 6 h |
| Desenvolvimento | 24 h |
| Testes e ajustes | 8 h |
| Instalação e orientação | 4 h |
| Acompanhamento e aceite | 2 h |
| **Total** | **48 h** |

O custo por hora fictício é `(R$ 4.800 de remuneração + R$ 1.200 de despesas operacionais) ÷ 80 horas faturáveis = R$ 75/h`. As horas faturáveis representam o tempo disponível para projetos pagos. R$ 240 de despesas específicas são a previsão para deslocamento e material da sessão de implantação, separados das despesas mensais.

| Componente | Valor fictício |
| --- | ---: |
| Mão de obra, 48 h × R$ 75 | R$ 3.600 |
| Despesas específicas | R$ 240 |
| Reserva para imprevistos do escopo | R$ 360 |
| Provisão didática de tributos e taxas | R$ 300 |
| Lucro planejado | R$ 900 |
| **Preço fechado** | **R$ 5.400** |

| Condição | Proposta |
| --- | --- |
| Cliente e entrega | Pequena loja, com responsável a indicar. Implantação do sistema web para uma unidade e um usuário, com acesso de uso, instalação e orientação. O código-fonte pode ser negociado à parte. |
| Escopo | Acesso, produtos, movimentações, histórico e alertas da seção B. Loja virtual, WhatsApp, emissão fiscal, importação e filiais ficam fora desta implantação. |
| Pagamento | 40% na contratação (R$ 2.160), 30% na demonstração (R$ 1.620) e 30% no aceite (R$ 1.620). |
| Prazo | Até 15 dias úteis a partir da aprovação por escrito, do pagamento inicial e do recebimento dos dados combinados. |
| Recorrência | Hospedagem estimada em R$ 45 por mês, paga pelo cliente ao fornecedor. O valor será confirmado antes da contratação. |
| Aceite | O responsável testa os quatro critérios da seção B com dados de exemplo e registra o aceite por escrito. |
| Suporte e mudanças | Por 30 dias após o aceite, atendimento por e-mail em dias úteis para dúvidas de uso e correções do escopo. Novas funções recebem orçamento e prazo próprios para aprovação. |
| Validade | 10 dias corridos a partir do envio da proposta. |

## D. Resposta ao pedido extra

> Podemos estimar uma segunda etapa para a loja virtual e a integração com WhatsApp. Em uma conversa curta, vamos definir os canais, o catálogo e os fluxos de atendimento. Depois enviamos prazo e preço da ampliação para aprovação, mantendo a implantação já proposta em R$ 5.400.

**Fechamento individual**

- **Diego:** A necessidade de integrar um sistema de venda já em uso é a informação que mais altera meu orçamento, porque muda o desenvolvimento e os testes de validação de saldo.
- **Bruno:** A quantidade de unidades é a informação principal para mim, porque cada filial pode exigir estoque separado, permissões e transferências.
- **Miguel:** O estado da planilha de produtos mudaria mais a minha estimativa, porque limpeza, duplicatas e conferência de saldos podem exigir uma etapa extra.

Os fechamentos individuais estão apresentados como falas sugeridas para o ensaio.
