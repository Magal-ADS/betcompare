# OddRadar — Implementação atual do MVP

**Atualizado em:** 21/09/2026

**Status:** MVP funcional com expansão de análises e filtros semanais

Este documento descreve o que está efetivamente implementado. Para o objetivo, limites e decisões de produto, consulte também [CONTEXTO_DO_PROJETO.md](../produto/CONTEXTO_DO_PROJETO.md) e [LEVANTAMENTO_DE_REQUISITOS.md](../produto/LEVANTAMENTO_DE_REQUISITOS.md).

A expansão aprovada após o MVP está detalhada em [IMPLEMENTACAO_ANALISES_E_FILTROS.md](IMPLEMENTACAO_ANALISES_E_FILTROS.md). Em caso de divergência sobre mercados, filtros, semana ou percentuais individuais, esse documento mais recente prevalece.

Para inicialização, autenticação, coleta, recuperação e execução segura dos testes no ambiente local, consulte [OPERACAO_LOCAL.md](../operacao/OPERACAO_LOCAL.md).

## 1. Entrega atual

O sistema implementa o fluxo abaixo para futebol pré-jogo nos mercados aprovados:

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
| `MarketCatalog` | Mantém a lista fechada de mercados autorizados e descarta títulos não aprovados. |
| `OddsComparisonService` | Mantém as odds individuais, calcula a diferença por casa e a maior referência concorrente. |
| `DashboardController` / `RefreshOddsController` | Exibem o painel e recebem o pedido manual de atualização. |
| `AuthenticatedSessionController` | Autentica sessões internas com as contas do Laravel. |
| `UserManagementController` | Permite ao super administrador cadastrar e alterar contas operadoras. |

## 3. Coleta responsável

Os coletores fazem apenas `GET` para páginas públicas configuradas, com timeout de 10 segundos, timeout de conexão de 3 segundos e tentativas apenas para erro de conexão ou erro HTTP de servidor.

Não há uso de login, CAPTCHA, endpoint privado, contorno de anti-bot, WebSocket ou automação de navegador. O método atual foi validado pelas provas técnicas registradas em:

- [PROVA_TECNICA_FIREBETS.md](../provas-tecnicas/PROVA_TECNICA_FIREBETS.md)
- [PROVAS_TECNICAS_CONCORRENTES.md](../provas-tecnicas/PROVAS_TECNICAS_CONCORRENTES.md)

Se uma fonte alterar o HTML ou deixar de disponibilizar os dados publicamente, a correção deve ocorrer no coletor daquela fonte. Não tentar burlar proteções.

## 4. Contrato interno de coleta

Cada coletor retorna objetos `CollectedOddsEvent` com:

```text
fonte
mandante e visitante
data e horário exibidos pela fonte, quando disponíveis
região, país e campeonato, quando identificáveis
mercados aprovados, seleções e odds disponíveis
horário da coleta
```

O comparador e o dashboard não conhecem HTML, URLs ou seletores CSS das casas.

## 5. Persistência

As migrations do MVP criam as tabelas abaixo.

| Tabela | Conteúdo |
| --- | --- |
| `bookmakers` | Fontes configuradas, nome, URL e indicação de fonte principal. |
| `collection_runs` | Uma atualização manual completa e seu status final: `completed`, `partial` ou `failed`. |
| `collection_source_results` | Resultado individual de cada fonte: status (`completed`, `empty` ou `failed`), horário, quantidade de eventos e erro resumido quando aplicável. |
| `events` | Evento padronizado, com equipes originais da primeira ocorrência e chaves normalizadas. |
| `source_events` | Representação de um evento tal como foi recebido por uma fonte; pode apontar para um evento padronizado. |
| `odds` | Snapshot 1X2 legado, mantido para compatibilidade histórica. |
| `market_odds` | Snapshot genérico por mercado e seleção, vinculado ao evento de origem e à execução. |

O MVP armazena dados estruturados e mensagens resumidas de erro. Não armazena HTML completo nem payloads extensos por padrão.

## 6. Matching de eventos

O matching atual é deliberadamente conservador e determinístico:

1. Converte nomes para minúsculas e remove acentos e caracteres especiais.
2. Remove sufixos/prefixos redundantes comuns, como siglas estaduais e designadores de clube.
3. Compara mandante e visitante preservando a ordem.
4. Exige que mercado, data e horário normalizados coincidam.

Quando não há correspondência segura, o evento continua no banco e aparece no painel como **“Ainda não comparado”**. O sistema não tenta adivinhar equivalências e não usa IA.

## 7. Regra de comparação

Para cada seleção de um mercado aprovado, o painel mostra:

- odd individual de Firebets, Chute13, A2Bets e GB Gold Bet, quando disponível;
- a maior odd concorrente válida;
- a casa que oferece essa referência;
- diferença percentual da Firebets para a referência;
- diferença percentual da Firebets para cada casa concorrente;
- estado textual: `↑ Acima`, `↓ Abaixo` ou `= Igual`.

Fórmula aplicada:

```text
((odd_firebets - melhor_odd_concorrente) / melhor_odd_concorrente) × 100
```

Uma diferença negativa informa apenas que a odd Firebets é menor que a maior odd concorrente. Não é recomendação de aposta, avaliação de probabilidade ou previsão esportiva.

Dados ausentes nunca são tratados como odd zero.

## 8. Atualização manual e disponibilidade parcial

No dashboard, o botão **“Atualizar odds agora”** envia `POST /atualizar-odds`.

A requisição agenda um job único e retorna imediatamente ao dashboard. Um worker executa a coleta em segundo plano, com timeout de 15 minutos. A mensagem de confirmação informa esse prazo ao operador, que pode continuar usando ou fechar o painel durante o processamento.

Para cada fonte:

1. o sistema executa o coletor isolado;
2. persiste os eventos e odds se a coleta funcionar;
3. identifica explicitamente uma fonte que respondeu sem eventos;
4. registra erro se ela falhar;
5. segue para as fontes restantes.

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
| `GET` | `/login` | Exibe a tela de acesso interno. |
| `POST` | `/login` | Inicia a sessão autenticada. |
| `POST` | `/logout` | Encerra a sessão atual. |
| `GET` | `/` | Dashboard de comparação. |
| `POST` | `/atualizar-odds` | Dispara uma nova coleta manual. |
| `GET` | `/historico-coletas` | Exibe as execuções de coleta e o estado de cada fonte. |
| `GET` | `/usuarios` | Lista usuários; exclusiva para super administrador. |
| `POST` | `/usuarios` | Cria um usuário operador; exclusiva para super administrador. |
| `PUT` | `/usuarios/{user}` | Atualiza um usuário operador; exclusiva para super administrador. |

## 10.1 Melhorias operacionais implementadas

- Funil por região, país, campeonato e jogo, busca normalizada e ordenação por horário, equipe ou maior/menor diferença.
- Recorte automático da semana corrente; não há filtro de dia, mês ou ano.
- Seletor compacto de análise no cabeçalho de cada jogo, exibindo um mercado por vez.
- Feedback visual no botão durante a atualização manual.
- Histórico paginado de coletas e falhas por fonte.
- Tela de login com sessão segura do Laravel. Todo o painel exige autenticação.
- Um super administrador inicial pode ser criado via variáveis de ambiente; ele pode criar e editar contas operadoras, sem acesso a recursos de SaaS ou permissões complexas.
- Interface responsiva: cabeçalhos, ações e formulários se reorganizam em telas pequenas; as tabelas extensas preservam a leitura com rolagem lateral orientada.
- PWA instalável: manifesto, ícones, `service worker`, tema do aplicativo e botão de instalação nos navegadores que expõem o prompt. O modo offline mostra uma página de ausência de conexão e não guarda dashboard, sessões ou odds em cache.

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

O ambiente local usa Docker, Laravel e PostgreSQL. A aplicação fica disponível em:

```text
http://localhost:8010
```

Comandos usuais:

```bash
docker compose up -d
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan db:seed
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
docker compose run --rm --no-deps test php artisan test --compact
docker compose exec -T app vendor/bin/pint
```

O serviço `test` força SQLite em memória. Assim, a suíte não usa nem altera o banco PostgreSQL local.

Não execute testes de banco com `docker compose exec app php artisan test`: o serviço `app` usa o PostgreSQL local e testes com recriação de banco podem apagar os dados de desenvolvimento. Consulte o procedimento completo em [OPERACAO_LOCAL.md](../operacao/OPERACAO_LOCAL.md).

## 14. Publicação com Dockploy

O `Dockerfile` prepara os assets do Vite durante a imagem e expõe a porta interna `8000`; no Dockploy, publique esse container através de um domínio com HTTPS. HTTPS é necessário em produção para o `service worker` e a instalação como PWA; `localhost` é a exceção aceita pelos navegadores durante o desenvolvimento. Não é necessário expor a porta `8010`, que existe apenas no `docker-compose.yml` local.

Cadastre no Dockploy as variáveis de ambiente de produção, sem versioná-las:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://seu-dominio
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
ODDRADAR_DISPLAY_TIMEZONE=America/Sao_Paulo
ODDRADAR_SUPER_ADMIN_NAME="Administrador OddRadar"
ODDRADAR_SUPER_ADMIN_EMAIL=...
ODDRADAR_SUPER_ADMIN_PASSWORD=...
```

Na primeira publicação, execute uma única vez no terminal/comando pós-deploy do serviço:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
```

O seeder só cria o super administrador caso o e-mail ainda não exista; por isso, reexecutá-lo não redefine a senha de uma conta já criada. Após o primeiro deploy, mantenha as variáveis de administrador como segredos do Dockploy e nunca as coloque no Git.

## 15. Limitações e próximos cuidados

- O botão de atualização agenda a coleta em uma fila e retorna imediatamente. O serviço `worker` é obrigatório; sem ele, as atualizações permanecem pendentes.
- A estabilidade do HTML das fontes deve ser monitorada; mudança de markup exige ajuste no coletor correspondente.
- Não foram adicionados outros esportes, mercados fora do catálogo aprovado, odds ao vivo, exportação, API pública, IA, surebets ou execução de apostas.
- A PWA é uma versão instalável da aplicação web; não há aplicativo nativo Android ou iOS. Em navegadores compatíveis, a instalação é oferecida pelo botão ou pelo menu do navegador; no Safari/iOS, use “Adicionar à Tela de Início”.
