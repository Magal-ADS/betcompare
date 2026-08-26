# OddRadar — Provas Técnicas de Coleta: Concorrentes

**Data das verificações:** 26/08/2026
**Status:** concluídas para o objetivo mínimo da POC
**Escopo:** inspeção passiva de conteúdo público; nenhum coletor foi implementado nesta etapa.

## Objetivo

Repetir a prova técnica da Firebets para Chute13, A2Bets e GB Gold Bet, validando a obtenção pública de evento, participantes, data/hora quando disponível e odds 1X2.

## Resultado resumido

| Fonte | Página pública acessível | Evento e participantes | Data/hora | Odds 1X2 | Método inicial indicado |
| --- | --- | --- | --- | --- | --- |
| Chute13 | Sim | Sim | Sim | Sim | HTTP GET + parser de HTML |
| A2Bets | Sim | Sim | Sim | Sim | HTTP GET + parser de HTML |
| GB Gold Bet | Sim | Sim | Sim | Sim | HTTP GET + parser de HTML |

Nas três fontes, a página inicial redirecionou para uma página pública de jogos de futebol. Os cartões retornados no HTML já contêm mandante, visitante, data, hora e três odds sob o cabeçalho `Casa`, `Empate`, `Fora`.

## Exemplo comparável observado

Evento público observado nas três fontes: **CR Vasco da Gama RJ x EC Vitória BA**, em **26/ago às 21:30**.

| Fonte | Casa | Empate | Fora |
| --- | ---: | ---: | ---: |
| Chute13 | 1,55 | 3,37 | 5,10 |
| A2Bets | 1,55 | 3,36 | 5,10 |
| GB Gold Bet | 1,52 | 3,30 | 5,00 |

O exemplo é apenas evidência de estrutura da POC; odds e disponibilidade variam ao longo do tempo e não devem ser tratadas como valores permanentes.

## Estrutura técnica observada

As três páginas apresentam, no HTML público:

- cartões de evento em `.cardItem`;
- data e hora em `.dateAndHour .date` e `.dateAndHour .hour`;
- mandante e visitante em elementos `.teams .team .nameTeam span`, nessa ordem;
- três odds em `.outcomesMain .odd`, na ordem definida pelo cabeçalho `Casa`, `Empate`, `Fora`.

As fontes usam ASP.NET Web Forms e possuem campos como `__VIEWSTATE` para interações da página. Esses campos não foram necessários para obter a listagem observada. Nenhuma autenticação, endpoint privado, WebSocket, execução de JavaScript ou navegador automatizado foi usado na POC.

## Decisão técnica preliminar

O método inicial para todas as quatro fontes é **HTTP GET + parser de HTML**, em um coletor isolado para cada casa. Embora a estrutura observada seja semelhante, cada fonte continuará tendo seu próprio coletor; não se deve acoplar uma fonte à outra nem pressupor que essa semelhança será permanente.

Não há evidência atual que justifique adicionar Node.js/Python com Playwright/Puppeteer ao MVP. Essa alternativa permanece disponível apenas se uma mudança futura em alguma fonte tornar a automação de navegador tecnicamente necessária e adequada.

## Limitações e próximos passos

- A verificação retrata o conteúdo público acessível em 26/08/2026 e não garante estabilidade futura de markup, URLs, campeonatos ou odds.
- Nenhum HTML ou payload completo foi armazenado; foram registrados apenas os campos necessários para a avaliação técnica.
- O `FirebetsCollector` isolado, seu contrato de dados normalizados e testes já foram criados. Os demais coletores devem seguir esse contrato, mantendo suas regras de parsing independentes.
- Falhas futuras de uma fonte devem ser registradas por fonte e não podem interromper a coleta ou a exibição das demais.
