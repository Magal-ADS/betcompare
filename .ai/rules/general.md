---
paths:
  - '**'
---

# Regras Gerais do OddRadar

## Escopo do produto

Considere `docs/CONTEXTO_DO_PROJETO.md` e `docs/LEVANTAMENTO_DE_REQUISITOS.md` como fontes de verdade do produto. Antes de implementar, confirme que o pedido pertence ao MVP ou que o usuário aprovou expressamente a expansão de escopo.

O OddRadar é uma ferramenta interna de comparação de odds para a Firebets. Não implemente apostas, execução de apostas, pagamentos, SaaS, multi-tenancy, IA preditiva, arbitragem, surebets ou integração financeira sem autorização explícita.

## Princípios técnicos

- Priorize simplicidade, baixo custo, manutenção fácil e confiabilidade.
- Use Laravel, PHP, Blade e MySQL enquanto forem suficientes; não introduza tecnologia ou dependência sem necessidade e aprovação.
- Confira convenções nos arquivos irmãos antes de criar ou alterar código.
- Use nomes descritivos, tipagem de parâmetros e retornos, e chaves de enum em TitleCase.
- Sempre use chaves em estruturas de controle PHP.
- Não crie documentação fora de `docs/` a menos que o usuário solicite.

## Segurança e coleta responsável

Trabalhe somente com informações publicamente acessíveis. Nunca implemente contorno de CAPTCHA, autenticação, bloqueio anti-bot ou qualquer controle de acesso. Quando uma fonte não puder ser coletada de forma adequada, registre a limitação em vez de tentar burlá-la.

## Fluxo de trabalho e validação

- Antes de mudanças de código, consulte a documentação versionada do framework/pacote relevante pelo Laravel Boost MCP quando estiver disponível.
- Use comandos Artisan dentro do contêiner `app`; comandos geradores devem usar `--no-interaction` e o UID/GID do host.
- Faça a menor validação automatizada que cubra a mudança. Ao alterar PHP, execute o Pint e os testes afetados.
- Ao concluir uma implementação, informe resultado, validação feita e como revisar. Só faça commit quando o usuário pedir ou autorizar explicitamente.

## Registro de decisões duráveis

Quando uma decisão não óbvia afetar implementações futuras, registre-a em uma regra específica nesta pasta e adicione seu caminho ao índice.
