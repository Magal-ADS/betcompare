---
paths:
  - 'app/Collectors/**'
  - 'app/Services/Collection/**'
  - 'app/Jobs/**'
---

# Coletores

- Cada fonte deve ter coletor isolado. Nunca faça o comparador ou dashboard depender do HTML, endpoint ou tecnologia de uma casa específica.
- Antes da implementação definitiva, realize prova técnica da fonte para identificar HTML, XHR/Fetch, endpoint interno, JavaScript, WebSocket ou real necessidade de automação de navegador.
- Não presuma API pública e não presuma que fontes diferentes seguem a mesma estrutura.
- Uma falha em uma fonte deve ser registrada com fonte, horário e causa, sem bloquear a coleta ou exibição das demais fontes.
- Colete apenas futebol e mercado pré-jogo 1X2 no MVP.
- Registre `collected_at` e metadados suficientes para rastrear a origem de cada odd. Preserve dados brutos somente quando necessário para diagnóstico e dentro dos limites de custo.
