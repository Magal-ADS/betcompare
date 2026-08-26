---
paths:
  - 'app/Http/**'
  - 'resources/views/**'
  - 'routes/**'
---

# Dashboard

- A interface deve priorizar a leitura rápida de evento, mercado, odds da Firebets, odds das concorrentes, melhor referência, diferença e horário de atualização.
- Use Blade como padrão enquanto for suficiente; JavaScript somente para uma necessidade concreta de interação.
- O dashboard não é área pública nem produto SaaS. Não adicione fluxos de cadastro, planos, equipes ou permissões complexas.
- Estados de Firebets acima, abaixo ou igual à referência devem ser distinguíveis por texto e/ou ícone, não apenas por cor.
- Dados ausentes e falhas de fonte devem ser compreensíveis e não devem aparentar uma cotação válida.
