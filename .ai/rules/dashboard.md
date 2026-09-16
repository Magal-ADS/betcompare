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

## Dashboard usa semana e funil de jogos
O dashboard considera apenas a semana corrente e não oferece filtro de dia, mês ou ano. Preserve o funil Região → País → Campeonato → Jogo, a opção Todos, a busca normalizada e as ordenações de maior/menor diferença.

## Respeitar cooldown entre coletas manuais
A atualização manual deve respeitar oddradar.collection_cooldown_minutes (30 minutos por padrão), além do lock contra concorrência. Mostre uma mensagem clara ao usuário e não inicie requisições externas durante o cooldown.
