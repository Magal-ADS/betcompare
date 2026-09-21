# OddRadar — Operação local atual

**Atualizado em:** 21/09/2026

Este documento reúne o fluxo operacional do ambiente local, o funcionamento atual da coleta e os procedimentos seguros de recuperação e teste. Ele complementa [IMPLEMENTACAO_MVP.md](../implementacao/IMPLEMENTACAO_MVP.md), [IMPLEMENTACAO_ANALISES_E_FILTROS.md](../implementacao/IMPLEMENTACAO_ANALISES_E_FILTROS.md) e [IMPLEMENTACAO_PROTECAO_BLOQUEIOS_FONTES.md](../implementacao/IMPLEMENTACAO_PROTECAO_BLOQUEIOS_FONTES.md).

## 1. Serviços locais

O `docker-compose.yml` mantém quatro serviços:

| Serviço | Responsabilidade |
| --- | --- |
| `app` | Aplicação Laravel disponível em `http://localhost:8010`. Usa o PostgreSQL local. |
| `worker` | Executa os jobs de coleta em segundo plano. Usa a fila e o PostgreSQL locais. |
| `db` | PostgreSQL que armazena usuários, execuções, eventos e odds locais. |
| `test` | Ambiente isolado de testes, com SQLite em memória. Não usa o PostgreSQL local. |

Para iniciar e preparar o ambiente:

```bash
docker compose up --build -d
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan db:seed --no-interaction
```

## 2. Acesso ao sistema

O painel exige autenticação. O super administrador inicial é definido pelas variáveis abaixo, mantidas somente no ambiente:

```dotenv
ODDRADAR_SUPER_ADMIN_NAME=...
ODDRADAR_SUPER_ADMIN_EMAIL=...
ODDRADAR_SUPER_ADMIN_PASSWORD=...
```

O seeder usa `firstOrCreate`: ele cria a conta quando o e-mail ainda não existe, mas não redefine a senha de uma conta existente.

Quando a tabela `users` estiver vazia, restaure o administrador com:

```bash
docker compose exec -T app php artisan db:seed --no-interaction
```

Se a conta existir com uma senha diferente da configurada, confirme primeiro o ambiente e o e-mail alvo. No ambiente local, a senha pode ser sincronizada com a configuração usando o cast `hashed` do modelo:

```bash
docker compose exec -T app php artisan tinker --execute '$email = config("oddradar.super_admin.email"); $password = config("oddradar.super_admin.password"); throw_unless(is_string($email) && $email !== "" && is_string($password) && $password !== "", RuntimeException::class, "Credenciais locais não configuradas."); $user = App\Models\User::query()->where("email", $email)->firstOrFail(); $user->password = $password; $user->save();'
```

Nunca registre a senha real em documentação, código ou Git.

## 3. Atualização das odds

O fluxo atual é assíncrono:

1. O operador clica em **“Atualizar odds agora”**.
2. `RefreshOddsController` verifica o cooldown de 30 minutos e o marcador de coleta pendente.
3. O sistema agenda um único job `CollectOdds` e redireciona imediatamente para o dashboard.
4. O painel informa que a coleta começou em segundo plano e pode levar até 15 minutos.
5. O serviço `worker` consulta as fontes e persiste cada resultado separadamente.
6. O operador pode continuar usando ou fechar o painel. Ao recarregar a página após alguns minutos, o dashboard mostra a última execução concluída.

O job possui uma tentativa, timeout de 900 segundos e unicidade por 1.200 segundos. A fila usa `retry_after` de 960 segundos, evitando que uma coleta lenta seja executada em duplicidade.

Se outra coleta estiver pendente, o sistema não agenda uma segunda. Depois de uma execução concluída, novas atualizações manuais respeitam o cooldown configurado em `oddradar.collection_cooldown_minutes`.

## 4. Status e limites das fontes

Cada fonte é isolada. Uma falha em A2Bets, por exemplo, não impede Firebets, Chute13 ou GB Gold Bet de concluir.

Os cartões representam o resultado da última coleta concluída:

- `Disponível`: a fonte concluiu e retornou eventos;
- `Sem eventos`: a consulta concluiu sem eventos disponíveis;
- `Limitada`: a fonte respondeu HTTP 429 ou Cloudflare 1015;
- `Em espera`: o OddRadar adiou a consulta para respeitar um bloqueio ainda ativo;
- `Indisponível`: ocorreu outra falha na fonte.

Um cartão não muda automaticamente quando o horário de nova tentativa chega. Ele só é atualizado depois que outra coleta consulta a fonte e termina com sucesso ou registra um novo estado.

Bloqueios HTTP 429/Cloudflare 1015 usam espera progressiva de 1, 3, 6 e 12 horas. Um `Retry-After` maior prevalece, limitado por padrão a 24 horas. O sistema não troca IP, não usa proxy e não tenta contornar controles das casas.

A concorrência atual das páginas de detalhes é de duas requisições simultâneas por fonte:

```dotenv
ODDRADAR_COLLECTION_DETAIL_CONCURRENCY=2
ODDRADAR_COLLECTION_COOLDOWN_MINUTES=30
ODDRADAR_SOURCE_RATE_LIMIT_MAX_MINUTES=1440
```

## 5. Recuperação de uma base local vazia

As migrations recriam a estrutura e o seeder recria somente o super administrador. As fontes são recriadas por `CollectOddsAction` durante a próxima coleta.

```bash
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan db:seed --no-interaction
```

Depois disso:

1. acesse `http://localhost:8010/login`;
2. entre com as credenciais configuradas no ambiente;
3. clique em **“Atualizar odds agora”**;
4. aguarde até 15 minutos e recarregue o dashboard.

Histórico, eventos e odds removidos não são reconstruídos pelo seeder; uma nova coleta repopula somente os dados novamente disponíveis nas fontes.

## 6. Execução segura dos testes

Execute a suíte exclusivamente no serviço `test`:

```bash
docker compose run --rm --no-deps test php artisan test --compact
```

Para um arquivo específico:

```bash
docker compose run --rm --no-deps test php artisan test --compact tests/Feature/RefreshOddsTest.php
```

Não execute testes de banco no serviço `app`:

```text
docker compose exec app php artisan test ...  ← não usar
```

O serviço `app` aponta para o PostgreSQL local. Testes que usam `RefreshDatabase` ou `LazilyRefreshDatabase` podem recriar as tabelas da conexão ativa e apagar usuários, histórico e odds. O serviço `test` força SQLite em memória e existe para impedir esse impacto.

## 7. Diagnóstico rápido

Estado dos contêineres:

```bash
docker compose ps
```

Logs recentes do worker:

```bash
docker compose logs --tail=200 worker
```

Jobs que falharam:

```bash
docker compose exec -T app php artisan queue:failed
```

Reinício do worker após alterações de código ou configuração:

```bash
docker compose restart worker
```

## 8. Registro operacional de 21/09/2026

- A mensagem de confirmação da atualização passou a informar explicitamente que a coleta pode levar até 15 minutos.
- O teste de feature de atualização foi ajustado para proteger esse texto apresentado ao operador.
- Durante a validação, um teste de banco foi executado incorretamente no serviço `app`, recriando as tabelas do PostgreSQL local enquanto uma coleta estava em andamento.
- O job ativo falhou porque seus registros relacionados deixaram de existir.
- O super administrador foi restaurado com o seeder e sua senha foi validada contra a configuração local.
- O histórico e as odds locais anteriores precisam ser repopulados por uma nova coleta.
- A regra operacional estabelecida é executar testes somente no serviço isolado `test`.
