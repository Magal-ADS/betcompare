# OddRadar — Contexto do Projeto

> Documento canônico de produto e escopo. Leia este arquivo antes de propor ou implementar funcionalidades.

## Visão geral

**OddRadar** é uma ferramenta web interna, personalizada e de uso exclusivo da casa de apostas **Firebets**. O sistema monitora as odds públicas da Firebets e de concorrentes selecionados, reunindo a comparação em um único painel.

**Slogan/conceito visual:** _“Monitore suas odds. Compare a concorrência.”_

O produto existe para eliminar a comparação manual, hoje feita pelo proprietário ao abrir vários sites, localizar os mesmos jogos e conferir as odds. Ele não é uma casa de apostas, não executa apostas e não disponibiliza apostas a usuários.

Site da casa principal: <https://firebets.net.br>

## Problema e objetivo

O proprietário/operador da Firebets precisa identificar rapidamente se as cotações oferecidas pela casa estão acima ou abaixo das principais concorrentes para os mesmos eventos.

O objetivo do OddRadar é automatizar o fluxo:

```text
COLETAR → NORMALIZAR → COMPARAR → EXIBIR
```

Toda decisão deve priorizar simplicidade, baixo custo, manutenção fácil, confiabilidade, velocidade de desenvolvimento e clareza das informações. Não transformar este projeto em um SaaS ou plataforma de apostas sem uma decisão explícita posterior.

## Fontes no escopo inicial

| Papel | Fonte |
| --- | --- |
| Casa principal | Firebets — <https://firebets.net.br> |
| Concorrente | Chute13 — <https://chute13.net> |
| Concorrente | A2Bets — <https://a2bets.com> |
| Concorrente | GB Gold Bet — <https://gbgoldbet.com> |

Essas são as únicas fontes previstas para o MVP. A modelagem deve tornar possível incluir novas fontes no futuro, sem exigir alterações drásticas no sistema.

## Usuário e modelo de uso

Inicialmente haverá somente **um usuário**: o proprietário/operador da Firebets. Esta é uma aplicação web privada para uso interno de um cliente, e não um produto comercial multiusuário.

Fora de escopo inicial:

- múltiplos usuários, equipes, papéis, permissões ou multi-tenancy;
- planos, cobrança, assinatura, clientes ou afiliados;
- API pública;
- aplicativo mobile nativo. A aplicação web responsiva pode ser instalada como PWA, sem criar uma versão nativa separada.

## MVP funcional e expansão aprovada

O MVP original cobria:

- futebol;
- eventos disponíveis publicamente;
- mercado de resultado final **1X2**;
- comparação da Firebets com as fontes configuradas;
- dashboard simples e visual;
- atualização manual por botão; atualização automática é uma evolução posterior;
- indicação da última atualização;
- destaque visual para diferenças relevantes.
- interface responsiva para dispositivos móveis e instalação opcional como PWA.

Em 15/09/2026, o cliente aprovou a expansão para análises adicionais de gols, ambas marcam, chances duplas, tempos da partida, placares exatos, escanteios e jogadores, limitada à lista fechada documentada em [IMPLEMENTACAO_ANALISES_E_FILTROS.md](IMPLEMENTACAO_ANALISES_E_FILTROS.md). A mesma aprovação incluiu jogos da semana, filtros por região/país/campeonato/jogo, correção da busca e percentuais individuais por casa.

Exemplo de comparação esperada:

| Evento: Flamengo x Palmeiras · Mercado: 1X2 | Firebets | Chute13 | A2Bets | GB Gold |
| --- | ---: | ---: | ---: | ---: |
| Flamengo | 1,85 | 1,90 | 1,87 | 1,88 |
| Empate | 3,40 | 3,30 | 3,35 | 3,30 |
| Palmeiras | 4,20 | 4,00 | 4,10 | 4,05 |

O painel deve permitir responder rapidamente:

- Quais jogos estão disponíveis?
- Qual odd a Firebets oferece em cada seleção?
- Quais odds as concorrentes oferecem?
- Qual concorrente apresenta a maior odd?
- Qual a diferença percentual?
- Onde a Firebets está abaixo ou acima da concorrência?
- Quando os dados foram coletados pela última vez?

Continuam fora de escopo:

- outros esportes e mercados não incluídos na lista aprovada;
- apostas ao vivo;
- arbitragem, surebets ou previsões;
- inteligência artificial para matching ou resultados;
- execução de apostas;
- integração financeira, pagamentos, assinaturas ou afiliados.

## Diretriz de coleta

Não usar APIs comerciais ou pagas na solução inicial. A preferência é consumir exclusivamente dados públicos apresentados pelas próprias fontes.

Antes da implementação definitiva de cada fonte, realizar uma **prova técnica** para determinar a forma apropriada de coleta. Investigar, para cada site:

1. HTML já renderizado;
2. endpoints internos; requisições Fetch/XHR e JSON;
3. conteúdo carregado por JavaScript;
4. WebSocket;
5. necessidade de navegador automatizado com Playwright/Puppeteer;
6. limitações, estabilidade e adequação técnica/contratual da coleta.

Não assumir que há API pública, que todas as fontes funcionam de forma igual ou que um scraper complexo é necessário antes dessa avaliação.

O sistema deve operar apenas sobre informações publicamente acessíveis. Não implementar mecanismos para burlar CAPTCHA, autenticação, bloqueios de segurança, anti-bot ou controles de acesso. Caso uma fonte não possa ser coletada de maneira adequada, documentar a limitação e avaliar uma alternativa.

## Arquitetura proposta

A coleta deve ser desacoplada das camadas de normalização, comparação e visualização. Cada fonte terá seu próprio coletor, por exemplo:

```text
collectors/
  FirebetsCollector
  Chute13Collector
  A2BetsCollector
  GBGoldBetCollector
```

Cada coletor converte os dados de origem para um formato interno padronizado. O comparador nunca deve depender do HTML, endpoint ou tecnologia de uma fonte específica.

Exemplo conceitual de dado normalizado:

```json
{
  "event": "Flamengo x Palmeiras",
  "home_team": "Flamengo",
  "away_team": "Palmeiras",
  "market": "1x2",
  "home_odd": 1.85,
  "draw_odd": 3.40,
  "away_odd": 4.20
}
```

Fluxo conceitual completo:

```text
Fonte
  ↓
Coletor específico
  ↓
Dados brutos
  ↓
Normalização
  ↓
Evento e odds padronizados
  ↓
Comparador
  ↓
Banco de dados
  ↓
Dashboard
```

Uma alteração futura em uma fonte deve exigir, idealmente, correção apenas no respectivo coletor.

## Matching e normalização de eventos

O sistema precisa reconhecer jogos equivalentes mesmo com variações nos nomes. Por exemplo, `Flamengo x Cruzeiro` e `Flamengo RJ x Cruzeiro MG` podem se referir ao mesmo evento.

No MVP, adotar uma estratégia simples e determinística baseada em:

- lowercase;
- remoção de caracteres especiais;
- remoção de informações redundantes;
- normalização de nomes de times;
- comparação de mandante e visitante;
- data e horário do evento quando disponíveis.

Não criar matching por IA neste momento.

## Dados e persistência

A modelagem relacional futura deve comportar:

- fontes/bookmakers;
- esportes;
- eventos;
- mercados;
- odds;
- horários de coleta/atualização;
- histórico de comparações, caso seja necessário.

O banco deve aceitar novas fontes sem remodelagens significativas.

## Atualização de dados

Tempo real em segundos não é requisito do MVP. A solução pode começar por atualização manual e/ou intervalos automáticos razoáveis.

A arquitetura deve permitir a evolução para este fluxo, usando Scheduler e Jobs do Laravel quando necessário:

```text
Laravel Scheduler
  ↓
Coleta das fontes
  ↓
Normalização
  ↓
Comparação
  ↓
Banco
  ↓
Dashboard
```

## Stack e infraestrutura

Preferências atuais:

- Laravel e PHP;
- PostgreSQL;
- Blade para a interface enquanto for suficiente;
- JavaScript somente quando necessário;
- Scheduler/Queue do Laravel para tarefas automáticas futuras;
- um serviço auxiliar em Node.js ou Python com Playwright/Puppeteer somente se alguma fonte realmente exigir automação de navegador.

Evitar tecnologias desnecessárias. O ambiente local atual é Dockerizado com Laravel e PostgreSQL; isso não autoriza implementar recursos além do escopo documentado.

## Contexto comercial

Estimativa discutida:

- desenvolvimento: **R$ 3.500**;
- pagamento à vista: **R$ 3.000**;
- manutenção mensal futura: aproximadamente **R$ 250/mês**.

A manutenção prevista cobre hospedagem, correções, manutenção dos coletores, pequenos ajustes e suporte básico. Funcionalidades ou integrações que ampliem significativamente o escopo exigem novo desenvolvimento e novo orçamento.

## Ordem obrigatória para futuras implementações

Antes de adicionar qualquer item, verificar se ele pertence ao MVP. Caso não pertença, não implementar automaticamente.

Prioridades:

1. coleta confiável;
2. normalização;
3. identificação dos mesmos eventos;
4. comparação;
5. dashboard;
6. atualização dos dados.

O MVP descrito neste documento está implementado. Consulte [IMPLEMENTACAO_MVP.md](IMPLEMENTACAO_MVP.md) para o estado técnico atual e suas limitações operacionais.
