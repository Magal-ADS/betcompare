# Novas fontes: verificação de 05/10/2026

Os seis sites solicitados foram verificados. O Chute13.club substitui a fonte Chute13 antiga; os outros cinco foram adicionados como concorrentes independentes. A verificação usou páginas e requisições públicas, sem autenticação ou automação de navegador. Os números abaixo são uma fotografia da coleta real feita nesta data; jogos e odds mudam com o tempo.

| Fonte | Resultado da coleta real | Mercados encontrados | Observação |
| --- | ---: | ---: | --- |
| M16 Sports Bet | 124 jogos | 33 | Link mobile informado usa HTML diferente; a página desktop pública do mesmo site foi usada. |
| Chute13.club | 140 jogos | 1 (1X2) | A página `/web` carrega jogos por requisições públicas JSON. |
| Tropa PB | 141 jogos | 33 | A página inicial redireciona para a lista desktop pública. |
| Esportes JL | 153 jogos | 31 | Usada a página desktop pública equivalente ao link mobile. |
| Palpite Certo | 122 jogos | 31 | Usada a página desktop pública equivalente ao link mobile. |
| Team Sport | 156 jogos | 31 | Usada a página desktop pública equivalente ao link mobile. |

Nos cinco sites com a mesma plataforma de HTML, foram identificados cartões públicos `.cardItem` e odds `.outcomesMain .odd`. Cada um mantém seu próprio coletor e usa o parser compartilhado enquanto a estrutura permanecer compatível. O Chute13.club usa o endpoint público `/web/leagues`, com a sessão anônima e o token CSRF fornecidos pela própria página `/web`; os dados 1X2 já vêm na listagem. A coleta foi executada de ponta a ponta em cada coletor, incluindo as páginas e os detalhes disponíveis.

## Erros e limitações observados

- **Chute13.club — mercados adicionais indisponíveis:** as rotas públicas `/web/quotations/match/valid-groups/{id}` e `/web/quotations/match/{id}` responderam HTTP 500 para dois jogos distintos. Por isso, este coletor registra somente as odds 1X2 presentes na listagem. Não foi encontrado um modo confiável de obter os demais mercados aprovados desse site nesta verificação.
- **Testes locais — permissão de escrita:** o arquivo `storage/logs/laravel.log` e o cache de resultados do PHPUnit pertencem a `root`, impedindo que o usuário local `magal` escreva neles. Os testes afetados passaram ao usar `LOG_CHANNEL=stderr` e `--do-not-cache-result`; isso não é erro de coleta das fontes.
- **Tempo e volume:** a coleta integrada de dez fontes levou 8 minutos e 23 segundos no ambiente local, abaixo do limite do worker de 15 minutos. A execução gravou 160.040 odds; esse volume aumenta o custo de armazenamento e deve ser acompanhado após o deploy.

## Validação integrada no PostgreSQL local

Após iniciar os serviços locais `db` e `app`, a execução 4 terminou com estado `completed` em 502,5 segundos. As fontes novas gravaram 109 jogos da M16, 128 do Chute13.club, 127 da Tropa PB, 139 da Esportes JL, 110 da Palpite Certo e 142 da Team Sport. As demais fontes também foram processadas; o Chute13 antigo retornou zero jogos, sem erro. A consulta do painel encontrou 217 jogos normalizados da semana e levou 0,65 segundo no ambiente local.

A suíte completa passou com 48 testes e 255 asserções. A implantação em produção ainda exige a mesma versão da aplicação e do worker; os resultados locais não garantem o mesmo tempo de resposta dos sites ou do banco em produção.

## Correção de 07/10/2026

O painel de produção mostrava somente as quatro casas antigas porque o aplicativo havia sido recriado com a imagem nova, mas o serviço separado `oddradar-worker` ainda executava uma imagem de 13 dias antes. Na correção, o coletor JSON de `https://chute13.club/web` passou a usar o identificador existente `chute13`, substituindo o coletor HTML de `chute13.net`. Assim, a próxima coleta atualiza a casa já cadastrada, preserva seu histórico e evita uma coluna duplicada. A validação integrada acima retrata a versão anterior da implementação, que ainda tinha dez coletores; a versão corrigida tem nove. A suíte completa da versão corrigida passou com 48 testes e 251 asserções.

## Verificação em produção de 07/10/2026

A aplicação estava atualizada, mas o worker ainda executava a imagem antiga. Após atualizar o worker para a mesma versão da aplicação, a coleta 8 processou Firebets (215 jogos), Chute13.club (420), A2Bets (184), GB Gold Bet (152), M16 (169), Tropa PB (213) e Esportes JL (225), todos sem erro de fonte. Palpite Certo começou, mas não concluiu; Team Sport não foi alcançado. O job atingiu o limite de 15 minutos durante a gravação das odds, entrou em `failed_jobs` e deixou a execução 8 com status `running`. Esse registro foi corrigido manualmente para `failed`.

O tempo integrado local de 8 minutos não representou o custo de produção. O limite do job e do worker foi ampliado para 30 minutos, o prazo de nova tentativa da fila para 31 minutos e o bloqueio de coletas simultâneas para 35 minutos. O tratamento de falha do job agora encerra execuções que ficariam com status `running`.

Após respeitar o intervalo entre coletas, a execução 9 terminou com status `completed` em 16 minutos e 27 segundos. As nove casas concluíram sem erros: Firebets (208 jogos), Chute13.club (412), A2Bets (175), GB Gold Bet (145), M16 (162), Tropa PB (207), Esportes JL (219), Palpite Certo (154) e Team Sport (224). Foram gravadas 251.216 odds. O painel encontrou 601 jogos para comparação e respondeu à consulta em 1,2 segundo. Há nove casas cadastradas, sem duplicata do Chute13 antigo. Uma casa pode aparecer com traço em um jogo específico quando não oferece odds para aquele jogo; isso não indica falha geral da fonte.

## Erro 500 do painel após a coleta

Após a execução 9, o painel passou a responder HTTP 500 para uma página filtrada. O log de produção registrou esgotamento do limite de memória do PHP, configurado em 128 MB. A montagem de uma página com 15 jogos chegou a cerca de 178 MB e gerou aproximadamente 32 MB de HTML, pois cada jogo inclui todas as análises e as odds das nove casas.

A correção limita o painel a cinco jogos por página, carrega somente os campos de odds usados na comparação e eleva o limite de memória do contêiner para 256 MB. A coleta e os dados gravados não precisaram ser refeitos.
