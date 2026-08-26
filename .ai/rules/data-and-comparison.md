---
paths:
  - 'app/Models/**'
  - 'database/migrations/**'
  - 'app/Services/Normalization/**'
  - 'app/Services/Comparison/**'
---

# Dados, Normalização e Comparação

- Modele fonte, evento de origem, evento padronizado, mercado, odd e execução de coleta de forma desacoplada, conforme os documentos em `docs/`.
- O formato normalizado deve separar mandante, visitante, mercado, odds 1X2, fonte e horário de coleta.
- O matching do MVP é determinístico: lowercase, remoção de acentos/caracteres especiais e dados redundantes, comparação ordenada de mandante/visitante e data/horário quando disponíveis.
- Não associe eventos com confiança insuficiente. Eventos sem correspondência devem continuar disponíveis como não comparados.
- Ao comparar, ignore fontes sem odd válida; ausência de dado nunca equivale a odd zero.
- A referência concorrente inicial é a maior odd válida por seleção. Ao exibir diferença percentual, use: `((odd_firebets - melhor_odd_concorrente) / melhor_odd_concorrente) × 100`.
- Não introduza IA para normalização, matching ou predição no MVP.
