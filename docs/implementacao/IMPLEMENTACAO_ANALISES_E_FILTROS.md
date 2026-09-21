# OddRadar — Implementação de análises e filtros semanais

**Data da implementação:** 15/09/2026
**Status:** implementado e coberto por testes automatizados

Este documento registra a expansão aprovada após a reunião com o cliente. Ele complementa [CONTEXTO_DO_PROJETO.md](../produto/CONTEXTO_DO_PROJETO.md), [LEVANTAMENTO_DE_REQUISITOS.md](../produto/LEVANTAMENTO_DE_REQUISITOS.md) e [IMPLEMENTACAO_MVP.md](IMPLEMENTACAO_MVP.md).

## 1. Resultado entregue

O OddRadar deixou de comparar somente o resultado final 1X2 e passou a trabalhar com uma estrutura genérica de mercados e seleções. A coleta continua limitada a futebol pré-jogo, às quatro casas configuradas e aos mercados aprovados neste documento.

O dashboard agora:

- considera somente partidas da semana corrente, de segunda-feira a domingo, no fuso de exibição configurado;
- permite filtrar em funil por região, país, campeonato e jogo;
- mantém a opção de visualizar todos os jogos quando nenhum filtro é aplicado;
- mantém as ordenações por maior e menor diferença;
- pesquisa nomes de times e confrontos completos sem depender de maiúsculas, acentos ou do separador `x`;
- mostra um seletor compacto de análise junto ao cabeçalho de cada jogo;
- exibe somente um mercado por vez para preservar o layout e reduzir poluição visual;
- mostra a diferença percentual individual da Firebets para cada concorrente e mantém a melhor referência concorrente.

## 2. Mercados permitidos

`MarketCatalog` é a lista fechada de mercados aceitos. Um título recebido de uma casa que não esteja nessa lista é descartado antes da persistência.

| Chave interna | Nome exibido |
| --- | --- |
| `match_winner` | Vencedor do Encontro |
| `total_goals` | Total de Gols no Jogo |
| `both_teams_score` | Ambas as equipes marcam |
| `double_chance` | Chance Dupla |
| `draw_no_bet` | Empate não tem aposta |
| `first_half_winner` | Vencedor do 1º tempo |
| `first_half_double_chance` | 1º Tempo - chance dupla |
| `first_half_both_teams_score` | 1º Tempo - ambas marcam |
| `second_half_both_teams_score` | 2º Tempo - ambas marcam |
| `half_with_most_goals` | Tempo do Jogo com Mais Gols |
| `home_score_both_halves` | Casa para marcar em ambos os tempos |
| `away_score_both_halves` | Fora para marcar em ambos os tempos |
| `away_total_goals` | Fora - Total de gols no jogo |
| `home_total_goals` | Casa - Total de gols no jogo |
| `correct_score` | Resultado exato |
| `team_to_score` | Qual equipe vai marcar |
| `total_corners` | Total de escanteios |
| `corners_1x2` | Escanteios 1x2 |
| `first_half_corners_1x2` | 1º tempo - escanteios 1x2 |
| `home_refund_bet` | Casa devolve aposta |
| `away_refund_bet` | Fora devolve aposta |
| `total_and_both_teams_score` | Total e ambas as equipes para marcar |
| `first_half_correct_score` | 1º Tempo - resultado exato |
| `second_half_total_goals` | Total de Gols no 2º Tempo |
| `home_win_both_halves` | Casa para vencer ambos os tempos |
| `away_win_both_halves` | Fora para vencer ambos os tempos |
| `winner_and_total_goals` | Vencedor do Encontro e Total de Gols |
| `both_teams_score_by_half` | Ambas marcam no 1º e 2º tempo |
| `winner_and_both_teams_score` | Vencedor do Encontro e Ambas Marcam |
| `first_half_first_goal` | 1º Tempo - 1º gol |
| `anytime_scorer` | Marca um Gol em Qualquer Momento |
| `first_half_total_goals` | Total de Gols no 1º Tempo |
| `first_scorer` | Jogador que Marca o 1º Gol |

No mercado de total de gols são preservadas todas as linhas disponíveis na partida, incluindo as linhas solicitadas de mais de 1,5, mais de 2,5, menos de 1,5 e menos de 2,5. A disponibilidade depende de cada jogo e de cada casa.

## 3. Equivalências confirmadas na fonte

A inspeção passiva das páginas públicas em 15/09/2026 confirmou a estrutura compartilhada atualmente pelas quatro fontes. Algumas anotações da reunião usavam nomes resumidos; foram vinculadas aos títulos públicos abaixo:

| Anotação | Título público utilizado |
| --- | --- |
| Vencedor não tem aposta | Empate não tem aposta |
| 1 tempo escanteio | 1ª/1º tempo - escanteios 1x2 |
| 1 tempo e primeiro gol | 1º Tempo - 1º gol |
| Que jogador marcou o primeiro jogo | Que Jogador Marca o 1º Gol? |
| Resultado exato | Resultado exato, cujo título pode trazer o placar atual entre colchetes |

Os mercados de jogadores e escanteios não aparecem em todas as partidas. Ausência de um mercado ou seleção é armazenada como ausência; nunca é convertida em odd zero.

## 4. Coleta semanal

Para cada fonte, o coletor:

1. acessa a página pública configurada;
2. identifica até sete links públicos no grupo “Jogos do Dia”;
3. lê país, código da bandeira, campeonato, equipes, data, horário e 1X2 dos cartões;
4. descarta grupos promocionais de estatísticas de jogadores que usam o mesmo HTML dos cartões de partidas;
5. acessa a página pública de detalhes de cada jogo;
6. retém somente os mercados presentes na lista fechada;
7. normaliza e persiste as seleções disponíveis.

Os cabeçalhos promocionais `Chutes ao Gol` e `Defesas de Goleiro` são descartados antes da leitura dos cartões. Essa validação é feita pelo título do grupo, e não pela presença de nomes entre parênteses, para não excluir equipes ou campeonatos legítimos.

As páginas de detalhes são consultadas concorrentemente, com limite padrão atual de duas requisições por fonte. O limite pode ser ajustado por ambiente:

```dotenv
ODDRADAR_COLLECTION_DETAIL_CONCURRENCY=2
ODDRADAR_COLLECTION_COOLDOWN_MINUTES=30
```

Continuam proibidos login em fontes, CAPTCHA, contorno de anti-bot, endpoint privado e automação destinada a superar controles de acesso. Uma falha de detalhes preserva o 1X2 já obtido na listagem e é registrada no log; uma falha da fonte permanece isolada das demais casas.

Atualizações manuais respeitam um intervalo mínimo configurável de 30 minutos. Durante esse período, o botão não inicia novas requisições e informa ao usuário que deve aguardar. Se uma das páginas da semana responder HTTP 429/Cloudflare 1015, as demais páginas já obtidas continuam sendo processadas; não há retry automático nem tentativa de contornar o limite da fonte. O detalhe técnico permanece no histórico e no log, enquanto o dashboard exibe uma mensagem operacional legível.

### 4.1. Diferença entre o cooldown interno e o bloqueio da fonte

Existem dois controles independentes:

- **Cooldown interno do OddRadar:** dura 30 minutos por padrão e impede que o botão inicie coletas muito próximas. Esse prazo é controlado pelo sistema e serve para reduzir a quantidade de acessos às fontes.
- **Bloqueio externo da casa:** é aplicado pela própria fonte quando ela responde HTTP 429 ou Cloudflare 1015. A duração não é informada nem controlada pelo OddRadar e pode ultrapassar 30 minutos, inclusive permanecer por horas.

O fim do cooldown interno somente libera uma nova tentativa; ele não garante que a fonte externa já tenha retirado o bloqueio. Repetir atualizações não acelera a liberação e pode prolongar a restrição aplicada ao IP do servidor.

Os cartões de “Status das fontes” representam o resultado da última coleta concluída. Portanto, uma fonte permanece marcada como “Falhou — Limite temporário da fonte atingido” até que uma coleta posterior dessa mesma fonte termine com sucesso. O aviso de cooldown exibido acima do painel se refere ao clique atual e não substitui o resultado histórico mostrado nos cartões.

O clique em “Atualizar odds agora” apenas agenda uma tarefa única na fila e devolve o controle do navegador imediatamente. O painel informa que a coleta pode levar até 15 minutos. Um worker executa a coleta em segundo plano. A combinação de marcador atômico no cache e job único impede cliques repetidos de enfileirarem coletas simultâneas. O job possui uma tentativa, timeout de 900 segundos e `retry_after` de 960 segundos; assim, uma coleta lenta não é duplicada pelo worker e uma falha externa não dispara novas tentativas contra a fonte. O procedimento operacional completo está em [OPERACAO_LOCAL.md](../operacao/OPERACAO_LOCAL.md).

## 5. Dados e migrations

Foram acrescentados aos eventos normalizados e de origem:

- início completo em `starts_at`;
- região em `region`;
- país e código público da bandeira em `country` e `country_code`;
- campeonato em `competition`.

A tabela `market_odds` guarda uma linha por fonte, execução, jogo, mercado e seleção. Ela substitui o formato rígido de três colunas para os novos dados. A tabela `odds` foi mantida para compatibilidade histórica, e uma migration converte seus snapshots 1X2 existentes para `market_odds`.

Migrations desta entrega:

```text
add_location_and_start_time_to_events_tables
create_market_odds_table
backfill_market_odds_from_legacy_odds
```

## 6. Região, país e campeonato

As regiões aprovadas no filtro são `América`, `Ásia` e `Europa`. A região é derivada do código público da bandeira informado na listagem da casa. Competições internacionais sem um país único são classificadas somente quando o próprio nome identifica de forma segura uma dessas regiões; caso contrário, continuam acessíveis em “Todos”, sem classificação inventada.

Os filtros são opcionais e encadeados na interface:

```text
Região → País → Campeonato → Jogo
```

Ao selecionar um campeonato, o seletor de jogos mantém somente as partidas daquele campeonato disponíveis na semana atual.

## 7. Busca corrigida

A busca anterior consultava os nomes originais com `LIKE`, comportamento sensível a maiúsculas no PostgreSQL. Agora cada termo é normalizado com a mesma regra dos eventos:

- conversão para minúsculas;
- remoção de acentos e caracteres especiais;
- remoção dos separadores `x` e `vs` da consulta;
- exigência de que todos os termos apareçam no mandante ou visitante normalizado.

Assim, uma busca como `sao paulo x boca` encontra `São Paulo FC x Boca Juniors` mesmo quando o jogo está em outra página da paginação.

## 8. Percentuais

Para cada seleção, a melhor referência continua sendo a maior odd concorrente válida.

```text
diferença para a melhor = ((odd Firebets - melhor odd concorrente) / melhor odd concorrente) × 100
```

Além dela, cada coluna concorrente mostra sua comparação individual:

```text
diferença individual = ((odd Firebets - odd da casa) / odd da casa) × 100
```

O sinal e o texto indicam se a Firebets está acima, abaixo ou igual. Dados ausentes não geram percentual.

## 9. Layout

O cartão de jogo atual foi preservado. O seletor “Análise” fica no cabeçalho, junto às informações da partida, e contém somente mercados realmente disponíveis naquele jogo. Ao trocar a análise, JavaScript alterna a tabela localmente, sem recarregar a página e sem renderizar vários blocos abertos ao mesmo tempo.

No celular, a tabela mantém rolagem horizontal orientada. Os percentuais individuais usam texto e seta além de cor, preservando a compreensão visual dos estados.

A paginação é executada no banco antes do carregamento das odds. Cada requisição do dashboard carrega somente os 15 jogos da página atual. As ordenações de maior e menor diferença usam um agregado SQL baseado na melhor odd concorrente, preservando a ordem global sem carregar a semana inteira na memória.

## 10. Implantação e revisão

Depois de publicar o código:

```bash
php artisan migrate --force
npm run build
php artisan config:clear
```

Também é obrigatório manter um worker de fila ativo:

```bash
php artisan queue:work --sleep=2 --tries=1 --timeout=900
```

No ambiente Docker local, o serviço `worker` do `docker-compose.yml` executa esse processo. Em seguida, execute “Atualizar odds agora” para popular a semana e os novos mercados; o painel pode continuar sendo usado durante a coleta.

Validações automatizadas realizadas durante a implementação:

```bash
docker compose run --rm --no-deps test php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
```

A suíte cobre a lista fechada de mercados, descarte de mercado não aprovado, parsing de localização e detalhes, persistência genérica, comparação, percentual por casa, semana corrente, funil e regressão da busca normalizada.

Em 15/09/2026, a validação local com dados públicos reais confirmou os 33 mercados. Após o cooldown, a execução 5 persistiu 49.828 odds em 458 registros de evento por fonte: 218 eventos da Firebets e 240 da Chute13, correspondendo a 260 jogos normalizados na semana. O dashboard autenticado respondeu com sucesso usando PostgreSQL, inclusive com comparações disponíveis e ordenação por diferença. Após as correções de grupos promocionais e processamento em fila, a suíte completa terminou com 32 testes e 156 assertions aprovados. Os testes de proteção contra 429 cobrem o cooldown manual, a retomada após o intervalo, a preservação de páginas semanais disponíveis e a mensagem amigável no dashboard.

Durante essa validação, foi corrigido um erro de tipo na classificação de competições internacionais: o resolvedor mantinha a descrição como texto simples antes de tentar usar a API fluente de strings. Um teste unitário cobre agora a classificação de `Copa Libertadores` com o código internacional `INT`.

A rodada real permaneceu parcial: Firebets e Chute13 concluíram; A2Bets e GB Gold Bet responderam HTTP 429/Cloudflare 1015. O sistema isolou essas falhas conforme projetado e não tentou contornar o controle de acesso.

Após a identificação visual de cartões de jogadores misturados às partidas, foram removidos do banco local 27 eventos de origem inválidos, 27 eventos normalizados órfãos e suas 202 odds dependentes. A regressão automatizada inclui exemplos de `Chutes ao Gol` e `Defesas de Goleiro` para impedir que esses grupos voltem ao dashboard.

## 11. Limitações operacionais

- As casas podem alterar HTML, nomes e disponibilidade sem aviso; o coletor correspondente precisará ser ajustado quando isso ocorrer.
- Uma atualização consulta mais páginas do que o MVP 1X2 e, portanto, pode demorar mais, mas é processada em segundo plano sem manter a requisição do navegador aberta.
- O intervalo interno entre atualizações não define nem encerra o bloqueio HTTP 429/Cloudflare 1015 de uma casa; a liberação depende exclusivamente da fonte externa.
- O sistema exibe somente dados públicos disponíveis no momento da coleta.
- África, Oceania e localizações sem classificação segura não receberam filtros adicionais, pois não foram solicitadas; seus jogos continuam visíveis na opção “Todos” quando coletados.
- Mercados ao vivo, esportes diferentes de futebol e mercados fora da lista deste documento permanecem fora do escopo.
