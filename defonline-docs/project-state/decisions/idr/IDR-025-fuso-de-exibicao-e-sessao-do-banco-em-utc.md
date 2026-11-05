---
idr_id: IDR-025
slug: fuso-de-exibicao-e-sessao-do-banco-em-utc
title: Datas para pessoas em horário de Brasília (ponto único) e sessão do banco fixa em UTC
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5
related_story: null
related_adrs: ["ADR-010"]
related_idrs: ["IDR-018"]
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-025 — Fuso de exibição e sessão do banco em UTC

## Contexto

`app.timezone` é UTC. Telas, PDF, planilha e e-mails formatavam o atributo direto (ou com `setTimezone` espalhado, às vezes com `config('app.timezone')`, ou seja, UTC): depois das 21h em Brasília o usuário via o dia seguinte (e o rodapé via o ano seguinte na virada). O `DrmRetificacaoBrowserTest` falhava depois das 21:00 UTC. Além disso o Postgres local roda em `America/Sao_Paulo` e vários models usavam `$dateFormat` com offset para não deslocar 3 h (IDR-018).

## Decisão

1. **Ponto único:** `App\Support\DataExibicao` (`data`, `dataHora`, `hora`, `agora`, `hoje`, `ano`, `converter`, `fuso`), no fuso `config('app.display_timezone')` (`APP_DISPLAY_TIMEZONE`, padrão `America/Sao_Paulo`). Armazenamento e `app.timezone` continuam UTC. Dia sem hora (`data_fundacao`, vigência, competência) usa `DataExibicao::dia()`, sem converter. "Hoje" e o mês padrão do DRM (`Competencia::padrao`, `ehFutura`) vêm do dia de Brasília e comparam mês civil, não instantes.
2. **Teste de arquitetura** (`tests/Feature/Support/FusoDeExibicaoTest.php`): falha se `app/` ou `resources/views` tiver `format('d/m/Y…`/`H:i`, `'America/Sao_Paulo'` literal ou `now()->year` fora de `DataExibicao`.
3. **Sessão do banco:** `'timezone' => 'UTC'` na conexão `pgsql` (`SET TIME ZONE 'UTC'` a cada conexão), em todos os ambientes.
4. **Casts com offset mantidos** (`Recomendacao`, `Assinatura`, `Invoice`, `LoteCredito`, `ProviderEvent`, `TrocaDeCartao`): custam nada, e protegem se algum cliente abrir sessão sem o ajuste. Não são mais necessários, e novos models não precisam deles.

## Por quê / impacto em dados existentes

- `timestamp` (sem fuso): o valor é literal; leitura e escrita não dependem da sessão. Só o `DEFAULT now()`/`useCurrent` muda: passa a gravar relógio UTC (antes, no dev, relógio de São Paulo). Em produção não muda nada se o servidor já estava em UTC.
- `timestamptz`: o instante armazenado é absoluto e a leitura devolve o mesmo instante (com offset) em qualquer sessão; reinterpretar a sessão não altera dados já gravados. Muda só a gravação de string **sem offset**, que passa a ser lida como UTC (era o bug local de 3 h; models com offset já gravavam certo).
- Consequência prática: no banco local, linhas antigas gravadas sem offset em `timestamptz` por models sem cast ficam 3 h deslocadas (só dev; recriar com `migrate:fresh`). Nenhuma query do código usa `date_trunc`, `::date` ou `CURRENT_DATE` sobre instantes (verificado por busca), então não há agrupamento por dia dependente da sessão.
- **Produção:** o Postgres é o do host ZalkVPS e seu `timezone` não é legível pelo repositório. Como o instante em `timestamptz` não muda e a sessão agora é fixa, o resultado independe dele. Se o host não estiver em UTC, `timestamp` com default de banco criados antes saíram no relógio do host: conferir com `SHOW timezone;` e, se divergir, tratar à parte (pedido ao admin, sem migração automática aqui).
- `Cupons::contasAlvo` ("cadastradas até a data"): a data do admin é um dia local; vale até 23:59:59 de Brasília (`DataExibicao::inicioDoDiaSeguinte`, limite exclusivo em UTC). Antes comparava o dia contra `created_at` em UTC e errava a borda por 3 h.

## Alternativas

- Mudar `app.timezone` para Brasília: quebra a premissa de armazenar UTC e afeta jobs, cache e provedores.
- Só `setTimezone` em cada view: foi o estado anterior; regride sem o teste de arquitetura.
- Remover os casts com offset: sem ganho; mantidos como defesa.

## Para outros agentes

Mostre datas com `DataExibicao`; "hoje" para regra de negócio de dia civil, `DataExibicao::hoje()`. Em teste, congele o relógio em `2026-11-06 01:30:00` UTC (22:30 em Brasília).
