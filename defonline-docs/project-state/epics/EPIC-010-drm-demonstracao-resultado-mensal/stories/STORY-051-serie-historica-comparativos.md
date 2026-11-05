---
story_id: STORY-051
slug: serie-historica-comparativos
title: "Série histórica e comparativos — o que mudou em relação ao mês passado e à média"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: programador (claude-sonnet-5-5)
created_at: 2026-08-04
updated_at: 2026-11-05
estimated_session_size: L
checkpoint: CP2
---

# STORY-051 — Série histórica e comparativos

## Contexto

Um mês isolado diz pouco. "Sua margem foi 12%" não é acionável; "sua margem caiu de 18% para 12% porque o custo das mercadorias subiu 9 pontos" é. **É esta estória que transforma o DRM de planilha em produto** — e é o que justifica uma assinatura anual em vez de uma consulta avulsa.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: spec da Tela 3 (STORY-046); `epics/EPIC-010-*/design/estrutura-drm.md` §5 e §6; `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` §K.6 e §K.8; **`decisions/adr/ADR-007-modelo-dados-drm-versionamento-retificacao.md`** (§*Comparativos e faróis não são persistidos* e §*Para o time — impacto por estória*)

## O quê

Implementar a visão de série histórica das competências lançadas e os comparativos por linha da demonstração, incluindo os alertas que dependem de histórico.

## Por quê

É a diferença entre um usuário que lança uma vez e um que lança doze. Ataca diretamente a métrica primária do épico.

## Critérios de aceite

- [ ] **CA-1 (Lista de competências):** todas as competências da Empresa Analisada, ordenadas, com estados distintos e visíveis: lançada, retificada, **não lançada** e rascunho em aberto.
- [ ] **CA-2 (Ausência ≠ zero):** mês sem lançamento nunca é tratado como zero. Aparece como lacuna, com ação para lançá-lo. Regra dura — tratá-lo como zero produz quedas fantasma na série. **Mecânica do `ADR-007`:** ausência de lançamento é **ausência de linha** (não existe placeholder zerado nem estado `nao_lancado` no banco); a série gera a espinha de meses na leitura e faz `LEFT JOIN` com as versões vigentes; sem correspondente ⇒ `indisponivel:sem_lancamento`. **`COALESCE(…, 0)` é proibido em qualquer query de série** — é a forma exata como quedas fantasma entram num produto. Lançamento com `deleted_at` conta como ausente, nunca como zero.
- [ ] **CA-3 (Comparativos):** para cada linha de nível 1 **do modo vigente** (`PDR-004`, decisão B), os quatro comparativos de `design/estrutura-drm.md` §6 — variação vs. mês anterior, variação vs. média móvel de até 12 meses, acumulado no ano e participação média sobre a receita.
- [ ] **CA-4 (Indisponibilidade dos comparativos):** cada comparativo respeita suas condições de indisponibilidade (sem mês anterior; menos de 3 competências; janela inteira sem faturamento) com motivo semântico, nunca zero disfarçado.
- [ ] **CA-5 (Alerta A4 — rubrica atípica):** a partir do 4º lançamento, campo que exceda 3× sua própria média móvel é sinalizado no lançamento e marcado na série. Cobre férias e 13º (Anexo K §K.8). **A marcação precisa ser legível como explicação** — o usuário deve entender que a queda de dezembro tem causa conhecida, não que o negócio piorou.
- [ ] **CA-6 (Alerta A6 — salto de competência):** lacuna entre o último lançamento e o atual é detectada e oferece lançar o mês faltante.
- [ ] **CA-7 (Meses atípicos entram nos cálculos):** meses marcados como atípicos **não** são excluídos dos comparativos — são sinalizados. Omitir dado real é pior do que explicá-lo.
- [ ] **CA-8 (Granularidades mistas):** meses lançados em modo agregado e detalhado convivem na mesma série. Comparativo de item detalhado é indisponível nos meses agregados, com motivo `indisponivel:grupo_agregado`. Nunca inferir o item a partir do total. O modo de cada grupo está **carimbado no snapshot de cada mês** (`ADR-007`), não só na preferência atual da empresa — leia de lá. Como a estrutura de linhas de nível 1 é idêntica nos dois modos, **todo comparativo de nível 1 está sempre disponível**; só o item detalhado fica indisponível.
- [ ] **CA-9 (Retificação):** mês retificado é exibido como tal, e os comparativos que o usam como base refletem os valores retificados.
- [ ] **CA-10 (Consistência com o snapshot):** os comparativos derivam dos snapshots persistidos, não de recálculo do motor sobre lançamentos antigos.
- [ ] **CA-11 (Desempenho):** a série carrega em ≤ 2s p95 com 24 competências.
- [ ] **CA-12 (Mobile-first):** utilizável em 360×800 sem scroll horizontal.
- [ ] **CA-13 (Testes):** unitários de cada comparativo, incluindo todas as condições de indisponibilidade e granularidade mista; feature cobrindo série vazia, 1, 3, 4 e 13 competências, série com lacuna, série com mês retificado; E2E percorrendo dois lançamentos consecutivos e conferindo o comparativo.
- [ ] **CA-14 (Cobertura):** ≥ 80%; ≥ 98% no cálculo dos comparativos.

## Fora de escopo

- Gráficos, salvo se o spec da STORY-046 os justificar.
- Projeção, tendência ou previsão de meses futuros.
- Comparação com outras empresas ou benchmark de mercado.
- Exportação da série (STORY-055).

## Sobre rubricas sazonais (decisão D)

`[DECIDIR-D]` foi respondida em 04/08/2026: a EBC quer **provisão contábil completa**. O `PDR-004` registra a decisão e explica por que ela não cabe como parâmetro — provisão exige um saldo acumulado entre meses, que é conta patrimonial, e o DRM é resultado em caixa puro por desenho. O caminho está sob spike na `STORY-058`.

**Esta estória entrega o tratamento vigente**, que responde à dor concreta sem nenhuma conta patrimonial: alerta A4 detecta a rubrica atípica, a série marca o mês, o comparativo explica. Se o spike aprovar a provisão dentro do épico, ela vem como estória adicional — não altera o que está aqui.

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`.

## Dependências

- **Bloqueada por:** STORY-046, STORY-048, STORY-049, STORY-050.
- **Bloqueia:** STORY-054 (a leitura em prosa usa os comparativos), STORY-055.

## Decisões já tomadas (não as reabra)

- `design/estrutura-drm.md` §5 e §6 — quais comparativos e suas condições de indisponibilidade.
- Ausência ≠ zero; meses atípicos entram nos cálculos.
- **ADR-007** (decisão da STORY-045). Em particular: **comparativos não são persistidos** — são derivados na leitura sobre as versões vigentes (CA-10 sai daí, e é o que faz o CA-5 da STORY-050 ser impossível de violar). A marca de mês atípico (A4) também é derivada, não coluna. Mês retificado é `versao > 1`. Se o CA-11 (2s p95) apertar com volume real, a saída registrada na ADR é cache de leitura invalidável por `(empresa, competência)` — reversível e local, nunca persistir comparativo.

## Definição de Pronto

- [ ] CA-1 a CA-14 passam.
- [ ] **Checkpoint 2 demonstrado em homologação:** lançar dois meses, retificar o primeiro, ver o comparativo do segundo se ajustar.
- [ ] Pipeline verde; `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Plano (2026-11-05, antes de codar)

Lido: estória, spec `design/STORY-046-serie-historica` e `STORY-046-demonstracao-mes` (divisão de trabalho), `estrutura-drm` §4–§6, Anexo K §K.6/K.8, ADR-007, PDR-004, ADR-011 (`proposed`), notas das STORY-047 a 050.

Entendimento: a série é leitura sobre as versões **vigentes**; ausência de lançamento é ausência de linha (a espinha de meses é gerada na leitura); comparativos nunca são persistidos; A4 é derivado (do payload dos vigentes), não coluna. Provisão (ADR-011) não é construída: vale o tratamento interino do PDR-004 (A4 + marca + explicação).

Plano: (1) domínio puro `App\Domain\Drm\Serie` (`MesDaSerie`, `SerieDrm`, `Comparativo`, `Atipicidade`) + regras `RubricaAtipica` (A4) e `SaltoDeCompetencia` (A6); (2) `HistoricoDrm` lê os vigentes numa consulta; (3) Livewire `Drm\Serie` + `SerieVisao` (rota `drm.serie`); (4) comparativos por linha de nível 1 na Demonstração; (5) A4/A6 no lançamento; (6) ligar "Ver todos os meses" e "Corrigir" a partir da série; testes unitários/feature/Dusk.

### Decisões tomadas

- **Domínio** `App\Domain\Drm\Serie\SerieDrm`: recebe só meses LANÇADOS (`MesDaSerie`, lidos do snapshot vigente) e devolve os quatro comparativos de uma linha. Nenhum método devolve zero para mês ausente. Aritmética inteira (centavos; percentual em 4 casas via `Percentual::deCentavos`); a variação contra a média é `(atual·n − soma)/|soma|`, sem dividir a média antes. `Percentual` ganhou `emUnidades/deUnidades/media` (média truncada em direção a zero).
- **Janela dos 12 meses = 12 meses de calendário** anteriores ao mês, usando só os que existem (a spec diz "competências anteriores disponíveis, até 12"; o texto "a partir do 4º mês lançado" fica igual para série densa). Ver "Dúvidas" abaixo.
- **Motivos novos** além dos três do `estrutura-drm` §4: `primeiro_lancamento` (não há nada antes), `sem_lancamento` (mês-base ausente, como no ADR-007), `historico_insuficiente` (<3 na janela), `base_zero` (divisão por zero: o `estrutura-drm` não cobre o caso, e "0%" seria mentira), `grupo_agregado` (item cujo mês, ou algum mês da conta, não o informou) e `sem_faturamento`. Todos têm frase em `config/drm/serie.php`.
- **Participação média** = média dos percentuais do snapshot dos meses da janela **incluindo o mês** (12 meses terminando nele); mês sem faturamento sai da média (não entra como 0%); só fica indisponível (`sem_faturamento`) se a janela inteira não tem percentual. **Acumulado no ano** soma só os meses lançados do ano-calendário até o mês e informa quantos entraram ("3 meses lançados em 2026"), para a lacuna não ficar invisível; é `grupo_agregado` se algum desses meses não tem a linha.
- **Comparativos do mês inteiro na série** = a última linha de nível 1 (resultado final) contra o mês anterior e contra a média; **comparativos por linha de nível 1** ficam na Demonstração do Mês, numa seção "Comparado aos outros meses" com um `<details>` por linha (divisão do spec; a seção vem abaixo da tabela/cards, em vez de dentro deles, para não duplicar marcação em tabela e cards). O motor/snapshot não foi tocado; o código `L1..L11` não aparece na tela (nível 1 e `codigoFinal/Ancora` vêm da ordem do snapshot).
- **A4 (`Atipicidade`)**: campo (qualquer D01..D42 coletado) com valor > 3× a média dos meses lançados na janela de 12 meses em que o campo foi coletado, com **pelo menos 3 desses meses** ("a partir do 4º lançamento"); mês sem lançamento ou com o campo suprimido (grupo agregado) fica fora da média, não conta zero. **Média zero com valor positivo conta como atípico**: é a assinatura do 13º (zero nos outros 11 meses); sem isso A4 não pegaria o caso para o qual existe. A mesma função alimenta o alerta no lançamento e a marca da série, então as duas telas não discordam. Uma `Ocorrencia` por campo (interface nova `RegraComVariasOcorrencias`, `RegraDeAlerta` intacta); o aceite continua por código. A demonstração deduplica a ressalva A4 (mesma frase por campo).
- **Marca de mês atípico na série**: `pill` neutro `Mês atípico` + frase `Outubro teve décimo-terceiro salário — por isso o resultado do mês caiu.` no fluxo de leitura + `<x-help>` com a explicação do regime de caixa; variante "subiu" se só campos de receita (D01, D41) dispararam. O mês entra nos cálculos (CA-7, testado). Atenção: `<x-help>` tem `<div>`; dentro de `<p>` o parser fecha o `<p>` no meio do componente Alpine e o painel fica sempre visível (achado pelo Dusk) — usei `<div>`.
- **A6 (`SaltoDeCompetencia`)**: último lançamento ANTERIOR à competência atual; se há meses entre os dois, alerta com contagem e lista, e um link `Lançar o mês que falta` para o primeiro mês em falta (`drm.lancar?competencia=`). Não se aplica na retificação (o mês já existe). Lançar um mês antes do último lançado não é salto.
- **Série (`Drm\Serie`, rota `GET /empresas/{empresa}/drm`, `drm.serie`)**: espinha do primeiro lançamento (ou rascunho) até o último mês fechado, do mais recente ao mais antigo, agrupada por ano; mobile `<ul>` de cartões (`lg:hidden`) e desktop `<table>` por ano (`hidden lg:table`), com a ausência atravessando as quatro colunas numéricas (`colspan`). O CTA do topo é "Lançar {mês}" para o mês fechado mais recente SEM lançamento (some quando tudo está lançado). Rascunho só de `origem = inicial` e ativo (o de retificação é de um mês que já está lançado).
- **"Ver todos os meses"** ligado na página da empresa (`empresa-show-ver-meses`) e na Demonstração (`drm-demonstracao-ver-serie`); "Corrigir" da série vai a `drm.corrigir`. Não mexi na navegação lateral do shell (`app-nav`, arquivo compartilhado com a outra pista).
- **Textos** em `config/drm/serie.php` (microcopy provisória do Designer, como `tela.php`); `Formato` agrupa moeda/percentual/variação (e `DemonstracaoVisao` passou a usá-lo). `ConteudoDrm::nomesDosBlocos()` saiu do componente de lançamento (a série diz "você parou em {bloco}").
- **STORY-050 CA-5 fechado na leitura real**: `SerieTest` "retificar um mês muda o comparativo do mês seguinte" — setembro 1.000 → 500 muda o de outubro de +50,0% para +200,0% na série **e** na Demonstração, com o snapshot de outubro idêntico byte a byte (id, versão, hash e `demonstracao`). O Dusk faz o mesmo pelo browser (lançar dois meses, retificar o primeiro a partir da série, ver o segundo se ajustar: o Checkpoint 2 da DoD).
- **Teste antigo alterado**: `DemonstracaoTest` "sem alertas aceitos, o bloco não existe" afirmava `assertDontSeeHtml('<details')`; a Demonstração agora tem `<details>` nos comparativos, então a asserção passou a `dusk="drm-ressalvas"` (a intenção — "o bloco de ressalvas não existe" — é a mesma).

### Descobertas

- `<x-help>` dentro de `<p>` quebra o componente (ver acima).
- `estrutura-drm` §5 não cobre base zero nem meses agregados no acumulado; registrei `base_zero` e `grupo_agregado` em vez de inventar um número.
- Nomes de função global do Pest colidem entre arquivos (`snapshot()` já existia no motor): helpers desta estória têm prefixo próprio; `tests/Support/drm_serie.php` (carregado em `Pest.php`) tem `serieMes/serieLancamento`.
- O `A6` da K.6 diz "lacuna entre o último lançamento e o atual"; interpretei "último" como o mais recente anterior à competência em edição.

### Dúvidas para o PO (não bloqueiam)

1. **Janela da média**: 12 meses de calendário (adotado) ou as últimas 12 competências lançadas, mesmo com lacunas? Muda só séries com buracos.
2. **A4 com média zero** conta como atípico (necessário para o 13º). Efeito colateral: a primeira vez que qualquer rubrica antes zerada aparece, a partir do 4º lançamento, pede aceite. Se for ruído, o ajuste é uma linha em `Atipicidade`.
3. **A4 em qualquer campo** (inclui D01/D41, com frase "subiu"). O Anexo K diz "qualquer campo"; o spec de tela só imagina despesa.
4. A **navegação lateral** do shell não ganhou o item "Meses" (spec §2 lista como entrada); fica para quando a outra pista liberar `app-nav`.

### Bloqueios encontrados

Nenhum.

### IDRs criados

Nenhum. Decisões locais (janela, motivos, A4). ADR-011 segue `proposed`; nada de provisão foi construído.

### Cobertura final

- Suíte `All` com `php -d memory_limit=-1 ... --coverage --min=80`: **2387 passed** (46.120 assertions); cobertura total **98,6%**. `Domain/Drm/Serie/*`, `Alertas/RubricaAtipica`, `Alertas/SaltoDeCompetencia` e `Percentual`: 100%. `Support/Drm/SerieVisao` 99,1%, `ComparativosVisao` 97,5%, `Livewire/Drm/Serie` 100%.
- Dusk completo: **75 passed** (610 assertions), incluindo `DrmSerieBrowserTest` (6: dois lançamentos consecutivos e o comparativo; retificar o primeiro a partir da série ajusta o segundo; 360x800 sem scroll horizontal com lacuna, rascunho, retificado e atípico; tabela no desktop; série vazia; 404 cross-tenant).
- Pint e PHPStan limpos.
- **Não verificado:** estados de loading/erro/degradado do spec (§4.2, §4.10, §4.11; tela renderizada no servidor); item "Meses" na navegação lateral; teclado ponta a ponta e axe; Checkpoint 2 em homologação (deploy fora da pista; o equivalente roda no Dusk local).
