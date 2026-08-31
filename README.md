# BetCompare

Aplicação Laravel com Laravel Boost e ambiente de desenvolvimento em Docker.

## Iniciar

```bash
docker compose up --build -d
docker compose exec app php artisan migrate
```

A aplicação ficará disponível em http://localhost:8010.

## Comandos úteis

```bash
docker compose run --rm --no-deps test php artisan test --compact
docker compose exec app composer install
docker compose down
```

O Laravel Boost está configurado em `boost.json` e `.mcp.json`. Para regenerar as integrações de agentes, execute:

```bash
docker compose exec app php artisan boost:install --guidelines --skills --mcp
```
# betcompare
