# OddRadar — Proteção contra bloqueios temporários das fontes

**Data:** 15/09/2026

**Branch:** `feature/analises-filtros-protecao-fontes`

**Objetivo:** reduzir novas ocorrências de HTTP 429/Cloudflare 1015 sem contornar os controles das casas.

## 1. Limites respeitados

O sistema diferencia dois controles independentes:

- o cooldown manual do OddRadar, de 30 minutos por padrão, limita os cliques no botão de atualização;
- o bloqueio externo HTTP 429/Cloudflare 1015 pertence à casa e pode durar mais tempo, inclusive horas.

Esta implementação não troca IP, não usa proxy, não altera identidade da requisição e não tenta superar CAPTCHA, Cloudflare ou qualquer outro controle de acesso.

## 2. Espera progressiva por fonte

Cada casa possui seu próprio estado. Quando uma fonte devolve HTTP 429 ou Cloudflare 1015, somente ela entra em espera; as demais continuam sendo coletadas normalmente.

As tentativas consecutivas usam os seguintes intervalos:

| Falha consecutiva | Espera mínima |
| --- | --- |
| 1ª | 1 hora |
| 2ª | 3 horas |
| 3ª | 6 horas |
| 4ª em diante | 12 horas |

Se a resposta trouxer o cabeçalho HTTP `Retry-After` com um prazo maior, esse prazo prevalece. O limite máximo aceito pelo sistema é de 24 horas por padrão, configurável por `ODDRADAR_SOURCE_RATE_LIMIT_MAX_MINUTES`.

Uma coleta executada antes do horário permitido não acessa a fonte. Ela registra o estado `deferred` e preserva o mesmo horário de próxima tentativa. Uma coleta bem-sucedida encerra a sequência de falhas daquela casa.

## 3. Estados registrados

`collection_source_results` passou a armazenar:

- `http_status`: código HTTP que causou a falha, quando disponível;
- `retry_at`: data e horário da próxima tentativa permitida.

Na aplicação da migration, falhas históricas identificadas como HTTP 429/Cloudflare 1015 são classificadas como `rate_limited`, recebem `http_status = 429` e uma primeira espera de uma hora a partir do último registro. Isso faz o painel refletir imediatamente o estado correto sem exigir uma nova chamada à fonte.

Os estados relevantes são:

- `rate_limited`: a fonte foi consultada e respondeu com bloqueio temporário;
- `deferred`: a fonte não foi consultada porque ainda estava no período de espera;
- `failed`: ocorreu outra falha que não representa limite HTTP;
- `completed` ou `empty`: a consulta foi concluída, com ou sem eventos.

O histórico mantém esses estados para auditoria. Quando todas as fontes de uma execução estiverem adiadas, a própria execução recebe o estado `deferred`.

## 4. Exibição no painel

O cartão da fonte informa:

- `Limitada`, quando a última tentativa recebeu HTTP 429/Cloudflare 1015;
- `Em espera`, quando a consulta foi adiada localmente;
- o horário exato após o qual uma nova tentativa poderá ocorrer;
- a data do último dado válido, quando existir.

Odds antigas não são misturadas silenciosamente à coleta atual. O último horário válido é mostrado apenas como referência operacional; as comparações continuam identificando os dados pertencentes à execução selecionada pelo painel.

## 5. Redução de carga

A concorrência padrão das páginas de detalhes foi reduzida de quatro para duas requisições simultâneas:

```dotenv
ODDRADAR_COLLECTION_DETAIL_CONCURRENCY=2
ODDRADAR_SOURCE_RATE_LIMIT_MAX_MINUTES=1440
```

O cliente HTTP mantém timeout explícito e não repete respostas 429. As únicas repetições imediatas existentes continuam limitadas a falhas de conexão ou erros 5xx, conforme a política já adotada pelo coletor.

## 6. Banco de dados e implantação

Migration adicionada:

```text
add_rate_limit_metadata_to_collection_source_results_table
```

Comandos de implantação:

```bash
php artisan migrate --force
php artisan config:clear
php artisan queue:restart
```

No ambiente Docker local, o worker deve ser recriado ou reiniciado depois da atualização para carregar o código novo.

## 7. Cobertura automatizada

Os testes verificam:

- reconhecimento de HTTP 429/Cloudflare 1015;
- respeito a um `Retry-After` maior que o intervalo interno;
- ausência de nova requisição durante o período de espera;
- progressão de uma para três horas após bloqueios consecutivos;
- persistência de `http_status` e `retry_at`;
- exibição da próxima tentativa e do último dado válido no dashboard;
- exibição do estado limitado no histórico de coletas.

Validação final executada em 15/09/2026:

```text
Laravel Pint: aprovado
Build Vite: aprovado
Suíte completa: 36 testes e 180 assertions aprovados
```

## 8. Limitação conhecida

Esta proteção reduz pressão sobre as fontes, mas não garante a remoção de um bloqueio já aplicado. A duração e a liberação continuam sob controle exclusivo da casa. Caso os bloqueios persistam, a solução apropriada é solicitar API, autorização formal ou liberação do IP diretamente à fonte.
