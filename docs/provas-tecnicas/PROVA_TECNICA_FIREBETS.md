# OddRadar — Prova Técnica de Coleta: Firebets

**Data da verificação:** 26/08/2026
**Status:** concluída para o objetivo mínimo da POC
**Escopo:** inspeção passiva de conteúdo público que fundamentou o coletor implementado posteriormente.

## Objetivo

Validar se é possível obter, de uma fonte real, os campos mínimos do MVP:

- evento;
- mandante;
- visitante;
- horário, quando disponível;
- mercado 1X2;
- odds de mandante, empate e visitante.

## Resultado

O objetivo mínimo foi atendido pela Firebets usando uma requisição HTTP pública comum. A página inicial redireciona para uma página de jogos de futebol e já entrega no HTML os cartões de eventos e as três odds principais.

URL pública observada após o redirecionamento:

```text
https://firebets.net.br/sistema_v2/usuarios/simulador/desktop/jogos.aspx?idesporte=102&idcampeonato=574926
```

## Evidências técnicas

| Campo | Localização observada no HTML | Resultado |
| --- | --- | --- |
| Esporte | parâmetro `idesporte=102` e menu/página de futebol | disponível |
| Evento | cartão `.cardItem` | disponível |
| Mandante | primeiro elemento `.teams .team .nameTeam span` | disponível |
| Visitante | segundo elemento `.teams .team .nameTeam span` | disponível |
| Data | `.dateAndHour .date` | disponível |
| Horário | `.dateAndHour .hour` | disponível |
| Mercado | cabeçalho com `Casa`, `Empate`, `Fora` | disponível como 1X2 |
| Odds 1X2 | links `.outcomesMain .odd`, nessa ordem | disponível |

Exemplo observado na página pública:

```text
Evento: FC Bulle x BSC Young Boys U21
Data/hora: 26/ago 14:30
Mercado: 1X2
Mandante: 1,64
Empate: 3,66
Visitante: 3,46
```

O cabeçalho da página informa explicitamente a ordem `Casa`, `Empate`, `Fora`; portanto, as três odds apresentadas em cada cartão podem ser mapeadas para mandante, empate e visitante nessa ordem. A conversão da vírgula decimal para valor numérico deve ocorrer somente na camada de normalização futura.

## Método identificado

- A página usa ASP.NET Web Forms e contém campos como `__VIEWSTATE` para interações/postbacks.
- Para a listagem pública de eventos e o mercado principal 1X2 observado nesta POC, um `GET` público retornou os dados diretamente no HTML.
- Há JavaScript e recursos ASP.NET na página, mas não foi necessário executar JavaScript, usar WebSocket, chamar endpoint interno ou automatizar navegador para atingir o objetivo mínimo.
- A resposta pública criou uma sessão HTTP padrão. Nenhuma autenticação foi usada e nenhum mecanismo de proteção foi contornado.

## Decisão técnica preliminar

Para a primeira versão do futuro `FirebetsCollector`, a hipótese preferencial é **HTTP GET + parser de HTML**, mantida isolada em seu coletor. Essa decisão ainda deve ser validada durante a implementação quanto a estabilidade, cobertura de campeonatos e alterações no markup.

Não adicionar Playwright/Puppeteer para a Firebets neste momento: não há evidência técnica de necessidade. A eventual adoção de serviço auxiliar continua condicionada à prova técnica de uma fonte que realmente o exija.

## Limitações e próximos passos técnicos

- A POC comprovou o formato em uma página pública e em um instante específico; ela não prova estabilidade permanente do markup nem cobre todos os campeonatos.
- Não armazenar HTML integral ou payloads extensos no MVP. O resultado futuro da coleta deve registrar somente fonte, evento, mercado, odds, horário, status e erro quando aplicável.
- Antes de implementar o dashboard completo, executar a mesma prova técnica para Chute13, A2Bets e GB Gold Bet.
- A implementação futura deve respeitar limites razoáveis de acesso e registrar falha isolada por fonte, sem impedir a visualização das fontes disponíveis.

## Revisão de 15/09/2026 — mercados e semana

Uma nova inspeção passiva confirmou que o menu público “Jogos do Dia” expõe links das datas disponíveis na semana e que cada cartão aponta para uma página pública de detalhes em `Apostas.aspx`. Nessa página, os mercados aparecem em `.eventdetail-market`, com o título no cabeçalho e cada seleção/odd em `.eventdetail-optionItem`.

Também foram confirmados nos eventos que os oferecem os mercados solicitados de gols, ambas marcam, chances duplas, tempos, placares exatos, escanteios e jogadores. A relação fechada efetivamente coletada está em [IMPLEMENTACAO_ANALISES_E_FILTROS.md](../implementacao/IMPLEMENTACAO_ANALISES_E_FILTROS.md); mercados presentes na página, mas ausentes dessa relação, são descartados.
