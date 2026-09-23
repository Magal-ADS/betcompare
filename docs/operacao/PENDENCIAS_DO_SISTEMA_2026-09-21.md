# OddRadar — Pendências do sistema

**Data do levantamento:** 21/09/2026
**Escopo:** situação do desenvolvimento, publicação e operação do OddRadar nesta data.

## 1. Resumo executivo

O MVP e a expansão aprovada de análises e filtros estão funcionalmente implementados. Não foi identificada uma grande funcionalidade obrigatória ausente no escopo contratado.

As pendências prioritárias estão concentradas na publicação da versão atual, na validação final em produção e na proteção operacional dos dados. Recursos como atualização automática, gráficos e exportações continuam classificados como pós-MVP e não impedem a conclusão da entrega atual.

## 2. Pendências prioritárias para a entrega

### 2.1. Mesclar e publicar a versão atual

**Status:** pendente.

Os commits mais recentes estão na branch `feature/analises-filtros-protecao-fontes`. É necessário mesclar essa branch na branch usada pelo ambiente de produção e confirmar o deploy no Dokploy.

Depois da publicação:

1. executar as migrations pendentes com `php artisan migrate --force`;
2. atualizar o cache de configuração;
3. reiniciar o worker da fila;
4. confirmar que aplicação e worker estão usando a mesma versão do código.

### 2.2. Executar teste final em produção

**Status:** pendente após o deploy.

Realizar um teste de aceite com o fluxo usado pelo cliente:

- autenticar no painel;
- solicitar uma atualização manual;
- confirmar a mensagem de que a coleta pode levar até 15 minutos;
- acompanhar a execução no worker e no histórico;
- confirmar os cartões das quatro fontes;
- validar mercados, percentuais, busca, filtros e ordenações;
- conferir a visualização responsiva e a instalação da PWA em um dispositivo compatível.

Uma fonte temporariamente limitada ou sem eventos não invalida o teste das demais, desde que o estado seja exibido corretamente e o dashboard continue funcionando.

### 2.3. Configurar e testar backup do PostgreSQL

**Status:** pendente e prioritário.

Configurar backup recorrente do banco de produção em armazenamento externo à VPS. A configuração somente será considerada concluída depois de:

- gerar um backup manual;
- confirmar que o arquivo foi armazenado fora da VPS;
- restaurar esse backup em um banco temporário;
- validar usuários, execuções, eventos e odds restaurados;
- definir retenção e uma rotina periódica de verificação.

Um backup sem teste de restauração não deve ser considerado suficiente.

### 2.4. Adicionar monitoramento operacional básico

**Status:** pendente; recomendado logo após a entrega.

Criar alertas ou verificações para:

- fonte com estado `failed`, `rate_limited` ou `empty`;
- queda anormal na quantidade de eventos coletados;
- ausência de coleta concluída dentro da janela esperada;
- worker parado ou jobs acumulados;
- mudança de HTML que faça um coletor deixar de reconhecer eventos.

O monitoramento deve distinguir uma fonte realmente sem jogos de uma quebra de coleta.

## 3. Situação das fontes em 21/09/2026

A última coleta local terminou em aproximadamente 2 minutos e 14 segundos com o estado geral `completed`:

| Fonte | Estado | Eventos |
| --- | --- | ---: |
| Firebets | Concluída | 118 |
| Chute13 | Concluída | 126 |
| A2Bets | Concluída | 111 |
| GB Gold Bet | Sem eventos | 0 |

A execução armazenou 53.268 odds para 146 jogos normalizados. A página pública da GB Gold Bet respondeu normalmente, mas informou zero jogos disponíveis. Esse resultado não comprovou falha do coletor; a fonte deve ser verificada novamente quando voltar a publicar eventos.

Existe um registro antigo em `failed_jobs`, causado pelo incidente local de recriação do banco durante uma coleta. A coleta posterior foi concluída normalmente. O registro pode ser removido após a preservação deste histórico, mas não afeta o funcionamento atual.

## 4. Pendências operacionais secundárias

- disponibilizar troca ou recuperação da senha administrativa pela interface, caso isso seja incluído no escopo; atualmente a recuperação depende de acesso ao servidor;
- confirmar atualizações de segurança do Ubuntu e Docker em uma janela de manutenção;
- avaliar usuário administrativo sem privilégios permanentes de `root` e proteção adicional contra tentativas de acesso SSH;
- limpar o job antigo em `failed_jobs` depois da conferência operacional;
- revisar o `README.md`, que ainda usa o nome antigo `BetCompare`;
- atualizar no levantamento de requisitos as decisões que já foram resolvidas durante a implementação;
- manter os documentos locais de servidor, incidentes e backup organizados sem versionar segredos.

## 5. Recursos pós-MVP que não bloqueiam a entrega

Os itens abaixo permanecem como possíveis evoluções e exigem confirmação de escopo e orçamento:

- atualização automática pelo Laravel Scheduler;
- alertas configuráveis por diferença percentual;
- gráficos e visão histórica por evento ou seleção;
- exportação CSV, Excel ou PDF;
- correção manual de matching e dicionário de aliases;
- novos esportes, fontes ou mercados;
- eventos e odds ao vivo;
- aplicativo mobile nativo e API pública.

A coleta atual é manual e assíncrona. O operador aciona o botão, o worker processa as fontes em segundo plano e o painel informa que o processo pode levar até 15 minutos. Esse comportamento atende ao MVP documentado.

## 6. Validações concluídas neste levantamento

Em 21/09/2026 foram confirmados:

- suíte completa: 36 testes aprovados e 180 assertions;
- build dos assets de produção concluído;
- migrations locais aplicadas;
- aplicação, PostgreSQL e worker locais ativos;
- última coleta local concluída;
- nenhuma vulnerabilidade conhecida encontrada pelo `composer audit`;
- nenhum agendamento automático configurado no Laravel.

## 7. Ordem recomendada de execução

1. Mesclar e publicar a branch atual.
2. Executar o teste final de aceite em produção.
3. Configurar e testar a restauração do backup.
4. Adicionar monitoramento das fontes e do worker.
5. Resolver as pendências secundárias aprovadas.
6. Orçar separadamente qualquer recurso pós-MVP.

Após os quatro primeiros itens, o ambiente estará em uma condição mais segura para encerramento da entrega e início da manutenção recorrente.
