# OddRadar — Levantamento de Requisitos

## 1. Propósito deste documento

Este documento converte o contexto do projeto em requisitos verificáveis. Ele complementa o [contexto do projeto](CONTEXTO_DO_PROJETO.md) e deve orientar planejamento, estimativas e futuras implementações.

**Regra de escopo:** itens classificados como **MVP** podem ser planejados para a primeira entrega. Itens classificados como **avançados** não devem ser implementados sem aprovação explícita e nova estimativa.

## 2. Perfis envolvidos

| Perfil | Responsabilidade no sistema |
| --- | --- |
| Proprietário/operador da Firebets | Único usuário do MVP; consulta o painel e solicita atualização dos dados. |
| Administrador técnico/desenvolvedor | Mantém infraestrutura, coletores e correções; não é um perfil de produto a ser implementado inicialmente. |

Não há requisito de cadastro público, múltiplos usuários, equipes, níveis de acesso ou multi-tenancy no MVP.

## 3. Requisitos funcionais — MVP

### 3.1 Gestão de fontes

| ID | Requisito | Prioridade | Critério de aceite |
| --- | --- | --- | --- |
| RF-01 | O sistema deve reconhecer Firebets como fonte principal de comparação. | Obrigatório | As odds da Firebets aparecem identificadas no painel para cada evento comparado. |
| RF-02 | O sistema deve suportar inicialmente Chute13, A2Bets e GB Gold Bet como fontes concorrentes. | Obrigatório | Cada fonte pode fornecer odds sem alterar a estrutura de comparação. |
| RF-03 | Cada fonte deve possuir uma implementação de coleta isolada das demais. | Obrigatório | Uma alteração em um coletor não exige alteração do comparador ou dos demais coletores. |
| RF-04 | O sistema deve permitir que uma fonte esteja temporariamente sem dados sem impedir a comparação das outras. | Obrigatório | O painel indica indisponibilidade/ausência de dados da fonte, sem falhar por completo. |

### 3.2 Coleta e padronização

| ID | Requisito | Prioridade | Critério de aceite |
| --- | --- | --- | --- |
| RF-05 | Coletar somente eventos e odds publicamente acessíveis nas fontes configuradas. | Obrigatório | A coleta não exige autenticação, pagamento ou contorno de proteção. |
| RF-06 | Coletar futebol no mercado pré-jogo de resultado final 1X2. | Obrigatório | Para cada evento, podem ser armazenadas odds de mandante, empate e visitante. |
| RF-07 | Registrar o resultado da coleta por fonte, incluindo data/hora e eventual falha. | Obrigatório | É possível identificar quando a fonte foi atualizada e se a última coleta falhou. |
| RF-08 | Transformar dados de cada fonte em um formato interno comum. | Obrigatório | O comparador recebe evento, participantes, mercado e odds sem depender do formato original. |
| RF-09 | Preservar dados brutos ou metadados suficientes para diagnosticar divergências de coleta. | Recomendado | Uma odd persistida pode ser relacionada à fonte e ao horário em que foi obtida. |

Formato conceitual mínimo normalizado:

```json
{
  "source": "firebets",
  "sport": "football",
  "event": "Flamengo x Palmeiras",
  "home_team": "Flamengo",
  "away_team": "Palmeiras",
  "event_starts_at": "data/hora quando disponível",
  "market": "1x2",
  "home_odd": 1.85,
  "draw_odd": 3.40,
  "away_odd": 4.20,
  "collected_at": "data/hora da coleta"
}
```

### 3.3 Identificação de eventos equivalentes

| ID | Requisito | Prioridade | Critério de aceite |
| --- | --- | --- | --- |
| RF-10 | Normalizar os nomes de participantes antes de comparar eventos. | Obrigatório | Diferenças de maiúsculas/minúsculas, acentos e caracteres especiais não impedem o cruzamento. |
| RF-11 | Comparar mandante e visitante, preservando a ordem dos participantes. | Obrigatório | Um evento com times invertidos não é automaticamente tratado como o mesmo mercado/seleção. |
| RF-12 | Usar data e horário do evento como apoio ao matching, quando disponíveis. | Obrigatório | Jogos com nomes semelhantes em horários distintos podem ser diferenciados. |
| RF-13 | Permitir que eventos sem correspondência permaneçam visíveis como não comparados. | Obrigatório | O sistema não inventa uma equivalência quando a confiança do matching é insuficiente. |
| RF-14 | Possibilitar correção manual de equivalências no futuro. | Futuro próximo | A modelagem não impede vincular manualmente duas representações do mesmo evento. |

### 3.4 Comparação

| ID | Requisito | Prioridade | Critério de aceite |
| --- | --- | --- | --- |
| RF-15 | Comparar as três seleções do mercado 1X2 da Firebets contra cada concorrente disponível. | Obrigatório | Mandante, empate e visitante são exibidos lado a lado por evento. |
| RF-16 | Identificar a maior odd concorrente por seleção. | Obrigatório | Para cada linha, o painel indica qual concorrente possui a melhor odd disponível. |
| RF-17 | Calcular a diferença absoluta e/ou percentual entre a odd Firebets e a melhor odd concorrente. | Obrigatório | A fórmula usada é consistente e pode ser explicada na interface/documentação. |
| RF-18 | Indicar se a Firebets está acima, abaixo ou igual à referência concorrente. | Obrigatório | O estado é visualmente distinguível e não depende apenas de cor. |
| RF-19 | Desconsiderar concorrentes sem odd válida da referência daquele cálculo. | Obrigatório | Dados ausentes não são interpretados como odd zero nem distorcem o resultado. |

**Definição sugerida de diferença percentual:**

```text
((odd_firebets - melhor_odd_concorrente) / melhor_odd_concorrente) × 100
```

O sinal positivo significa que a Firebets paga mais; o negativo significa que paga menos. A definição final deve ser apresentada no painel para evitar ambiguidade.

### 3.5 Dashboard e atualização

| ID | Requisito | Prioridade | Critério de aceite |
| --- | --- | --- | --- |
| RF-20 | Exibir uma lista clara de eventos comparados. | Obrigatório | O usuário identifica jogo, data/hora quando disponível e mercado. |
| RF-21 | Exibir uma tabela de odds por evento com Firebets e fontes concorrentes. | Obrigatório | As três seleções são legíveis e comparáveis numa única visualização. |
| RF-22 | Destacar oportunidades em que Firebets está acima ou abaixo da referência. | Obrigatório | O destaque funciona com texto/ícone além de cor. |
| RF-23 | Exibir a última atualização geral e, quando relevante, por fonte. | Obrigatório | O usuário sabe a atualidade dos dados antes de agir sobre eles. |
| RF-24 | Disponibilizar atualização manual acionada pelo operador. | Obrigatório | O usuário pode solicitar nova coleta e recebe estado de processamento/sucesso/falha. |
| RF-25 | Permitir filtrar ou organizar eventos para facilitar consulta. | Recomendado | Ao menos uma forma de ordenar/filtrar por data, time ou situação de comparação é definida antes da tela. |

## 4. Requisitos não funcionais — MVP

| ID | Requisito | Critério/objetivo |
| --- | --- | --- |
| RNF-01 | Simplicidade operacional | Um único ambiente Laravel + banco relacional deve ser suficiente no início. |
| RNF-02 | Baixo custo | Não depender de APIs comerciais/pagas para obter odds. |
| RNF-03 | Manutenibilidade | Coletores, normalização, comparação e apresentação devem permanecer desacoplados. |
| RNF-04 | Confiabilidade | Falhas isoladas de fonte devem ser registradas e não derrubar o painel. |
| RNF-05 | Rastreabilidade | Odds e comparações devem ter fonte e horário de coleta identificáveis. |
| RNF-06 | Segurança | Não expor o painel como produto público; definir um mecanismo simples de acesso antes da entrega operacional. |
| RNF-07 | Coleta responsável | Não burlar CAPTCHA, autenticação, anti-bot ou controles de acesso. |
| RNF-08 | Usabilidade | O operador deve identificar divergências relevantes rapidamente, sem navegação complexa. |
| RNF-09 | Evolutividade | Scheduler/Jobs e coletores novos devem poder ser adicionados sem reescrever o MVP. |

## 5. Requisitos avançados ou pós-MVP

Os itens abaixo são possibilidades futuras. Eles não fazem parte da primeira implementação nem devem ser incluídos automaticamente.

### 5.1 Evoluções de operação

- atualização automática via Laravel Scheduler e Jobs;
- filas, tentativas e alertas de falha por coletor;
- histórico de odds e gráficos de variação;
- alertas por diferença percentual configurável;
- auditoria detalhada de coletas e dados brutos;
- correção manual de matching e dicionário de aliases dos times;
- health check e observabilidade dos coletores.

### 5.2 Evoluções de interface e análise

- filtros avançados por liga, data, fonte e faixa de diferença;
- busca por equipe/evento;
- ordenação por maior divergência;
- exportação CSV/Excel/PDF;
- visão histórica por evento ou seleção;
- configuração visual de limites para destaque.

### 5.3 Ampliações de cobertura

- outros esportes;
- mercados adicionais (handicap, total de gols, ambas marcam etc.);
- eventos ao vivo;
- novas casas/fontes;
- automação de navegador por serviço auxiliar, se confirmada necessária na prova técnica.

### 5.4 Itens explicitamente excluídos até nova decisão comercial

- realizar ou intermediar apostas;
- arbitragem, surebets, recomendações de aposta ou previsão de resultados;
- IA para matching, precificação ou predição;
- integrações financeiras, pagamentos e cobranças;
- SaaS público, multi-tenancy, planos e assinaturas;
- aplicativo mobile e API pública.

## 6. Entidades de dados esperadas

| Entidade | Responsabilidade conceitual |
| --- | --- |
| Fonte/Bookmaker | Identifica Firebets ou um concorrente, sua URL e estado operacional. |
| Esporte | Classificação do evento; no MVP, futebol. |
| Evento padronizado | Representa o jogo normalizado: mandante, visitante, início e esporte. |
| Evento de origem | Representação do evento como foi recebido por determinada fonte. |
| Mercado | Tipo de mercado; no MVP, `1x2`. |
| Odd | Cotação de uma seleção, vinculada à fonte, evento, mercado e coleta. |
| Execução de coleta | Registro de início, fim, status, erro e quantidade coletada por fonte. |
| Comparação | Resultado derivado entre a Firebets e as referências concorrentes. |

## 7. Fluxo operacional esperado

```text
Operador solicita atualização
  ↓
Sistema dispara coleta para cada fonte
  ↓
Cada coletor retorna dados brutos/normalizados ou registra falha
  ↓
Sistema normaliza e relaciona eventos equivalentes
  ↓
Odds são persistidas com fonte e horário
  ↓
Comparações são calculadas
  ↓
Dashboard apresenta resultado e última atualização
```

No futuro, o mesmo fluxo poderá ser disparado por agendamento; a origem do disparo não deve alterar a lógica principal.

## 8. Decisões pendentes antes da implementação

Estas perguntas devem ser resolvidas durante a prova técnica e o detalhamento da primeira entrega. Elas não autorizam implementação antecipada.

| Tema | Decisão necessária |
| --- | --- |
| Acesso ao painel | Qual mecanismo mínimo de autenticação será usado pelo único operador? |
| Cobertura inicial | Quais campeonatos/eventos de futebol devem ser priorizados, se houver recorte? |
| Atualização | A primeira entrega terá apenas botão manual, agendamento, ou ambos? Qual intervalo é aceitável? |
| Referência da comparação | A referência será sempre a maior odd concorrente ou também cada concorrente individualmente? |
| Diferença relevante | Qual percentual/valor deve receber maior destaque visual? |
| Matching | Qual tolerância de horário e quais aliases iniciais de equipes serão aceitos? |
| Histórico | Guardar todas as coletas desde o MVP ou apenas o estado mais recente? |
| Fontes | Cada fonte permite coleta pública estável e adequada? Qual método técnico será adotado em cada uma? |
| Infraestrutura | Onde o sistema será hospedado e quais limites de execução/agendamento existem? |

## 9. Critério de conclusão do MVP

O MVP estará funcional quando o único operador puder abrir um painel privado, acionar uma atualização, ver o estado das quatro fontes e comparar as odds 1X2 de futebol da Firebets com as concorrentes para eventos reconhecidos como equivalentes, incluindo horários de atualização e indicação clara das diferenças.

Qualquer recurso adicional deve ser classificado neste documento antes de ser desenvolvido.
