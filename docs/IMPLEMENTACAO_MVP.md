# OddRadar — Implementação atual do MVP

**Atualizado em:** 26/08/2026  
**Status:** MVP funcional em ambiente local

Este documento descreve o que está efetivamente implementado. Para o objetivo, limites e decisões de produto, consulte também [CONTEXTO_DO_PROJETO.md](CONTEXTO_DO_PROJETO.md) e [LEVANTAMENTO_DE_REQUISITOS.md](LEVANTAMENTO_DE_REQUISITOS.md).

## 1. Entrega atual

O MVP implementa o fluxo abaixo para futebol pré-jogo no mercado de resultado final 1X2:

```text
Atualização manual
  ↓
Coleta pública das quatro fontes
  ↓
Normalização e matching determinístico
  ↓
Persistência de eventos e snapshots de odds
  ↓
Comparação da Firebets com a melhor referência concorrente
  ↓
Dashboard
```

Fontes configuradas:

| Papel | Fonte | Identificador interno |
| --- | --- | --- |
| Principal | Firebets | `firebets` |
| Concorrente | Chute13 | `chute13` |
| Concorrente | A2Bets | `a2bets` |
| Concorrente | GB Gold Bet | `gbgoldbet` |

O sistema continua sendo uma ferramenta interna de monitoramento. Ele não executa apostas, não recomenda apostas e não prevê resultados.

## 2. Componentes principais

| Componente | Responsabilidade |
| --- | --- |
| `app/Collectors/*Collector.php` | Obtém e transforma os dados públicos de uma fonte no contrato comum. |
| `PublicHtmlOddsCollector` | Parser comum para a estrutura HTML pública observada nas quatro fontes. Cada fonte ainda possui seu próprio coletor e pode substituir essa lógica se seu HTML mudar. |
| `OddsCollectorRegistry` | Define a lista e a ordem de execução das quatro fontes; Firebets é executada primeiro. |
| `CollectOddsAction` | Cria uma execução de coleta, isola falhas por fonte e persiste resultados disponíveis. |
| `EventNormalizer` | Normaliza nomes de equipes, removendo acentos, caracteres especiais e sufixos redundantes comuns. |
| `EventMatcher` | Associa eventos somente quando mandante, visitante, mercado, data e horário normalizados coincidem. |
| `OddsComparisonService` | Mantém as odds individuais e calcula a maior odd concorrente e a diferença da Firebets. |
| `DashboardController` / `RefreshOddsController` | Exibem o painel e recebem o pedido manual de atualização. |

## 3. Coleta responsável

Os coletores fazem apenas `GET` para páginas públicas configuradas, com timeout de 10 segundos, timeout de conexão de 3 segundos e tentativas apenas para erro de conexão ou erro HTTP de servidor.

Não há uso de login, CAPTCHA, endpoint privado, contorno de anti-bot, WebSocket ou automação de navegador. O método atual foi validado pelas provas técnicas registradas em:

- [PROVA_TECNICA_FIREBETS.md](PROVA_TECNICA_FIREBETS.md)
- [PROVAS_TECNICAS_CONCORRENTES.md](PROVAS_TECNICAS_CONCORRENTES.md)

Se uma fonte alterar o HTML ou deixar de disponibilizar os dados publicamente, a correção deve ocorrer no coletor daquela fonte. Não tentar burlar proteções.

## 4. Contrato interno de coleta

Cada coletor retorna objetos `CollectedOddsEvent` com:

```text
fonte
mercado (1x2)
mandante e visitante
data e horário exibidos pela fonte, quando disponíveis
odd do mandante, empate e visitante
horário da coleta
```

O comparador e o dashboard não conhecem HTML, URLs ou seletores CSS das casas.

## 5. Persistência

As migrations do MVP criam as tabelas abaixo.

| Tabela | Conteúdo |
| --- | --- |
| `bookmakers` | Fontes configuradas, nome, URL e indicação de fonte principal. |
| `collection_runs` | Uma atualização manual completa e seu status final: `completed`, `partial` ou `failed`. |
| `collection_source_results` | Resultado individual de cada fonte: status, horário, quantidade de eventos e erro resumido quando aplicável. |
| `events` | Evento padronizado, com equipes originais da primeira ocorrência e chaves normalizadas. |
| `source_events` | Representação de um evento tal como foi recebido por uma fonte; pode apontar para um evento padronizado. |
| `odds` | Snapshot 1X2 vinculado a um evento de origem e a uma execução de coleta. |

O MVP armazena dados estruturados e mensagens resumidas de erro. Não armazena HTML completo nem payloads extensos por padrão.

## 6. Matching de eventos

O matching atual é deliberadamente conservador e determinístico:

1. Converte nomes para minúsculas e remove acentos e caracteres especiais.
2. Remove sufixos/prefixos redundantes comuns, como siglas estaduais e designadores de clube.
3. Compara mandante e visitante preservando a ordem.
4. Exige que mercado, data e horário normalizados coincidam.

Quando não há correspondência segura, o evento continua no banco e aparece no painel como **“Ainda não comparado”**. O sistema não tenta adivinhar equivalências e não usa IA.

## 7. Regra de comparação

Para cada seleção do 1X2, o painel mostra:

- odd individual de Firebets, Chute13, A2Bets e GB Gold Bet, quando disponível;
- a maior odd concorrente válida;
- a casa que oferece essa referência;
- diferença percentual da Firebets para a referência;
- estado textual: `↑ Acima`, `↓ Abaixo` ou `= Igual`.

Fórmula aplicada:

```text
((odd_firebets - melhor_odd_concorrente) / melhor_odd_concorrente) × 100
```

Uma diferença negativa informa apenas que a odd Firebets é menor que a maior odd concorrente. Não é recomendação de aposta, avaliação de probabilidade ou previsão esportiva.

Dados ausentes nunca são tratados como odd zero.

## 8. Atualização manual e disponibilidade parcial

No dashboard, o botão **“Atualizar odds agora”** envia `POST /atualizar-odds`.

Para cada fonte:

1. o sistema executa o coletor isolado;
2. persiste os eventos e odds se a coleta funcionar;
3. registra erro se ela falhar;
4. segue para as fontes restantes.

Assim, uma falha de A2Bets, por exemplo, não impede a exibição de Firebets, Chute13 e GB Gold Bet. O dashboard mostra o status individual de cada fonte.

## 9. Horários

Os dados são armazenados e processados em UTC para evitar ambiguidade técnica. A apresentação do dashboard converte a última atualização para `America/Sao_Paulo`.

O fuso visível pode ser configurado com:

```dotenv
ODDRADAR_DISPLAY_TIMEZONE=America/Sao_Paulo
```

Depois de alterar configuração em ambiente já em execução, execute `php artisan config:clear` dentro do contêiner `app`.

## 10. Rotas do MVP

| Método | Rota | Finalidade |
| --- | --- | --- |
| `GET` | `/` | Dashboard de comparação. |
| `POST` | `/atualizar-odds` | Dispara uma nova coleta manual. |

## 11. Configuração de fontes

As URLs ficam em `config/services.php`, alimentadas por variáveis de ambiente documentadas em `.env.example`:

```dotenv
FIREBETS_GAMES_URL=...
CHUTE13_GAMES_URL=...
A2BETS_GAMES_URL=...
GBGOLDBET_GAMES_URL=...
```

Não colocar credenciais no repositório. O MVP não usa credenciais de fontes externas.

## 12. Executar localmente

O ambiente local usa Docker, Laravel e MySQL. A aplicação fica disponível em:

```text
http://localhost:8010
```

Comandos usuais:

```bash
docker compose up -d
docker compose exec -T app php artisan migrate
npm install --ignore-scripts
npm run build
```

Para desenvolvimento de frontend com atualização automática, executar `npm run dev` em outro terminal.

## 13. Testes e validação

Os testes usam dados HTML simulados e não fazem requisições reais às fontes. Coberturas atuais incluem:

- parsing do coletor Firebets e falha HTTP;
- parsing de Chute13, A2Bets e GB Gold Bet;
- coleta parcial com uma fonte indisponível;
- normalização/matching e evento não comparado;
- melhor referência concorrente e diferença percentual;
- renderização do dashboard com uma fonte indisponível;
- conversão do horário exibido para São Paulo.

Executar:

```bash
docker compose exec -T app php artisan test
docker compose exec -T app vendor/bin/pint
```

## 14. Limitações e próximos cuidados

- O dashboard ainda não tem mecanismo de autenticação. Antes de publicar fora de ambiente controlado, definir uma proteção simples de acesso para o único operador.
- O botão de atualização executa a coleta de forma síncrona. Scheduler, filas e alertas são evoluções posteriores, não requisitos desta entrega.
- A estabilidade do HTML das fontes deve ser monitorada; mudança de markup exige ajuste no coletor correspondente.
- Não foram adicionados outros esportes, outros mercados, odds ao vivo, filtros avançados, exportação, API pública, IA, surebets ou execução de apostas.
