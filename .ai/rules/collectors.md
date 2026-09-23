---
paths:
  - 'app/Collectors/**'
  - 'app/Services/Collection/**'
  - 'app/Jobs/**'
---

# Coletores

- Cada fonte deve ter coletor isolado. Nunca faça o comparador ou dashboard depender do HTML, endpoint ou tecnologia de uma casa específica.
- Enquanto a estrutura pública observada for idêntica, os coletores podem reutilizar o parser HTML comum. Uma mudança em uma fonte deve ser tratada no coletor dela, inclusive com override do parsing quando necessário.
- Antes da implementação definitiva, realize prova técnica da fonte para identificar HTML, XHR/Fetch, endpoint interno, JavaScript, WebSocket ou real necessidade de automação de navegador.
- Não presuma API pública e não presuma que fontes diferentes seguem a mesma estrutura.
- Uma falha em uma fonte deve ser registrada com fonte, horário e causa, sem bloquear a coleta ou exibição das demais fontes.
- Colete apenas futebol e mercado pré-jogo 1X2 no MVP.
- Registre `collected_at` e metadados suficientes para rastrear a origem de cada odd. Preserve dados brutos somente quando necessário para diagnóstico e dentro dos limites de custo.

## Coletar somente o catálogo aprovado de mercados
A expansão aprovada em 15/09/2026 substitui a antiga limitação exclusiva a 1X2. Colete futebol pré-jogo somente para as chaves definidas em MarketCatalog; descarte qualquer outro mercado público e preserve ausência de dados como ausência.

## Tratar limite HTTP 429 sem contorno
Ao receber HTTP 429/Cloudflare 1015, não faça retry automático nem contorne o bloqueio. Preserve páginas e odds já coletadas, registre fonte/URL/status e deixe a próxima coleta respeitar o cooldown configurado.

## Discard player-stat promotion groups before event parsing
Public bookmaker pages reuse match-card HTML for promotional player-stat groups. Treat competition headers containing Chutes ao Gol or Defesas de Goleiro as non-events and discard the whole group; do not infer player props from parentheses because legitimate teams and competitions use them.

## Defer each rate-limited bookmaker independently
Persist HTTP 429/Cloudflare 1015 as rate_limited with http_status and retry_at. Before collecting a source, honor its latest active retry_at and record deferred without making an HTTP request; use 1h, 3h, 6h, then 12h backoff and prefer a longer Retry-After, capped by configuration. Never delay healthy sources because another bookmaker is limited.

## Persist match start times in UTC
Public match cards show time in app.display_timezone. Convert the parsed Carbon start time to UTC before Eloquent persists it; timestamp columns omit timezone, so passing a local-time Carbon stores the wrong wall time. Keep source text separately and verify collection through database display.
