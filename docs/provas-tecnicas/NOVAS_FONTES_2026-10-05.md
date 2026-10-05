# Novas fontes: verificação de 05/10/2026

As seis fontes solicitadas foram adicionadas como concorrentes independentes. A verificação usou páginas e requisições públicas, sem autenticação ou automação de navegador. Os números abaixo são uma fotografia da coleta real feita nesta data; jogos e odds mudam com o tempo.

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
