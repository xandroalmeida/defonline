---
epic_id: EPIC-002
type: validation-report
validated_at: 2026-05-25
validated_by: validador (agente Claude — sessão STORY-037)
verdict: approved_with_pending
checklist_source: epics/EPIC-002-diagnostico-industria/validation/checklist.md
commit_validado: abcf09bd91371af1483541c74da0db51a83a86b4
branch: main
ambiente: localhost:8090 (dev, defonline-web + defonline-db) — ver Limitações / F-NB-1
---

# Relatório de Validação — EPIC-002 (Diagnóstico Indústria)

## TL;DR

> **Veredito**: **APPROVED com pendências** (`approved_with_pending`).
> **Contagem**: 51 itens — 39 `pass`, 4 `pass com ressalva`, **8 `fail` (0 bloqueantes, 8 não-bloqueantes)**, 0 `n/a`. (Itens com ressalva contam como pass.)
> **Próximo passo recomendado**: promover EPIC-002 para `done_under_review` (feito como output desta validação) e o PO fechar os 8 itens de **handoff/release/comunicação** (Blocos H/I) + reconciliar a doc da STORY-035 antes de declarar a STORY-037 `done`. **Beta segue bloqueado** até a STORY-036 (validação externa NRF §9.3) voltar `approved`/`approved_with_pending` — por decisão do próprio PO.

---

## Resumo executivo

O EPIC-002 entrega o diagnóstico financeiro do setor Indústria: quiz de 23 campos, motor de 14 indicadores com faróis, matriz de recomendações DEZ/2025, resumo executivo determinístico, validações cruzadas DRE × Balanço, tooltips, glossário e eventos analíticos. **Sob a ótica de qualidade técnica do produto, o épico passa com folga**: cobertura do motor 99.6% (≥98%) e geral 97.3% (≥80%), 787 testes Pest + 17 Dusk verdes, Pint/Larastan limpos, p95 do relatório em 20 ms (limite 3 s), determinismo de hash confirmado em execução ao vivo, isolamento multi-tenant retornando 404 (IDR-009), eventos com payload ADR-004 sem PII e `request_id` UUID v7. Blocos A, B, D, E, F validados com evidência verificável.

As pendências **não são defeitos de produto** — são **deliverables de fechamento/handoff e itens de processo** ainda não concluídos no momento da validação: o `handoff/README.md` não existe, a tag `v1.0.0` não foi criada, a seção de fechamento da sprint é um stub, a comunicação ao stakeholder não está registrada, o EPIC-003 ainda está `draft` (não `ready`), e o débito da validação externa não foi registrado como item de backlog com janela/responsável. Some-se a isso a doc da STORY-035, que está `done` no `index.json` mas `draft` no próprio arquivo. Tudo é endereçável pelo PO sem mexer em código. A validação externa do motor (Bloco G / STORY-036) permanece **pendente por decisão explícita do PO** (2026-05-26) — é o motivo de o épico fechar como `done_under_review` em vez de `done`, e de o beta seguir bloqueado.

---

## Checklist preenchido

Legenda: ✅ pass · ⚠️ pass com ressalva · ❌ fail · 🚫 n/a

### Bloco A — Métricas de qualidade técnica

| Item | Status | Evidência |
|---|---|---|
| A-1 — Cobertura motor ≥ 98% e geral ≥ 80% | ✅ | Motor **99.6%** (`A-1-coverage.txt`); geral **97.3%** (2498/2567 stmts, `A-1b-clover.xml` / `A-1b-coverage-geral.txt`). |
| A-2 — ≥ 10 casos por fórmula (≥ 140 cenários) | ✅ | 15 arquivos de indicador, 11–17 casos cada, **204 cenários** (`A-2-cobertura-por-formula.txt`). |
| A-3 — p95 relatório ≤ 3 s | ⚠️ | **p95 = 20 ms** em 50 navegações autenticadas (`A-3-p95.txt`/`A-3-p95-raw.txt`). Ressalva: medido em localhost dev, não homolog rc — ver F-NB-1. |
| A-4 — Pint + Larastan zero erro | ✅ | Pint 227 arquivos limpo; PHPStan **"No errors"** (`A-4-lint.txt`). |
| A-5 — Suíte completa verde (Pest + Dusk) | ✅ | **787 Pest** (2424 assertions) + **17 Dusk** (105 assertions), 0 falha (`A-1b-coverage-geral.txt`, `A-5-dusk.txt`). |

### Bloco B — Entregáveis visíveis

| Item | Status | Evidência |
|---|---|---|
| B-1 — Roberto preenche quiz e recebe relatório | ✅ | Caminho browser provado por Dusk (`CadastroLoginSmokeBrowserTest`, `ValidacoesCruzadasBrowserTest` gera diagnóstico). Funil real (rascunho→evento→cálculo) executado via app: `B-1-smoke-funil.txt`. Sem screencast `.mov` — substituído (ver Limitações). |
| B-2 — Tempo ≤ 3 s p95 | ✅ | = A-3 (`A-3-p95.txt`). |
| B-3 — 14 indicadores com semáforo | ✅ | Relatório renderiza todos os indicadores nomeados + faróis verde/amarelo/vermelho (`B-3-relatorio.html`; 15 chaves em `indicadores_calculados`). |
| B-4 — Recomendações da matriz DEZ/2025 (não placeholder) | ✅ | **Zero** ocorrências de "Faixa verde/amarela/vermelha" no relatório (`B-3-relatorio.html`); integração já validada em `report-STORY-032.md` (approved). |
| B-5 — Resumo Executivo no topo | ✅ | Bloco "Resumo executivo" + `data-veredito="saudavel"` presentes (`B-3-relatorio.html`). |
| B-6 — NCG absoluto sem farol | ✅ | Card "Necessidade de Capital de Giro" + mensagem semântica "Folga" presentes (`B-3-relatorio.html`). |
| B-7 — Validações cruzadas DRE × Balanço acionáveis | ✅ | `ValidacoesCruzadasBrowserTest` (Dusk): banner de inconsistência aparece, "Continuar" gera diagnóstico com alerta; R1/R2 com link ao campo (`A-5-dusk.txt`). |
| B-8 — Tooltips por campo no quiz | ✅ | `QuizTooltipsBrowserTest` (Dusk): popover desktop + bottom-sheet mobile (`A-5-dusk.txt`). |
| B-9 — Glossário inline | ✅ | Seção "Glossário" presente no fim do relatório (`B-3-relatorio.html`). |
| B-10 — Eventos `quiz_iniciado` e `diagnostico_concluido` | ✅ | 3 linhas com payload ADR-004 §2.2 (`B-10-eventos-sql.txt`): `quiz_iniciado`{quiz_id, quiz_versao:"2026.1"}; `diagnostico_concluido`{quiz_id, diagnostico_id, duracao_preenchimento_seg, setor, porte}. |

### Bloco C — CAs por estória (026–035)

| Item | Status | Evidência |
|---|---|---|
| C-026 — Spike versionamento/persistência | ✅ | IDR-010 aplicada; `payload_hash` SHA-256; versões carimbadas (D-1/D-3). |
| C-027 — Quiz 23 campos | ⚠️ | Funcionalidade verde (Dusk + suíte). Ressalva: CAs com `- [ ]` não marcados no arquivo da estória (front-matter `done`). |
| C-028 — Motor V1 7 indicadores | ✅ | Cobertura motor 100% nos indicadores (A-1); golden hashes verdes. |
| C-029 — Relatório minimalista | ⚠️ | Rota `/diagnosticos/{id}` renderiza (B-3). Ressalva: CAs não marcados no arquivo. |
| C-030 — Motor V2 14 indicadores | ⚠️ | 14 indicadores no relatório + Ciclo Operacional informativo. Ressalva: CAs não marcados; bump observado é `1.3.0` (≥ 1.1.0 prescrito). |
| C-031 — Resumo Executivo | ✅ | `report-STORY-031.md` = `approved_with_pending`. |
| C-032 — Matriz DEZ/2025 | ✅ | `report-STORY-032.md` = `approved`. |
| C-033 — Tooltips | ✅ | `report-STORY-033.md` = `approved`. |
| C-034 — Validações cruzadas | ✅ | `report-STORY-034.md` = `approved`. |
| C-035 — Eventos analíticos | ⚠️ | Substância verde (B-10, D-5, D-6, D-7). **Ressalva forte**: front-matter `status: draft` no arquivo da estória vs `done` no `index.json`; CAs não marcados; sem `report-STORY-035.md`. Ver F-NB-6. |

### Bloco D — Decisões arquiteturais preservadas

| Item | Status | Evidência |
|---|---|---|
| D-1 — `motor_version` semver + `matrix_version` datada | ✅ | `1.1.0`/`1.3.0` + `dez-2025` no snapshot (query SELECT). |
| D-2 — Snapshot imutável (not null, nunca update) | ⚠️ | Colunas `indicadores_calculados`/`resumo_executivo`/`payload_hash`/versões **NOT NULL**; nenhum caminho de `update` em `diagnosticos`; criação-única via `CalcularDiagnostico`. **Ressalva**: o teste nomeado `Tests\Architectural\SnapshotImutabilidadeTest` citado pelo checklist **não existe** — imutabilidade hoje repousa em schema + convenção, sem teste de regressão dedicado. Ver F-NB-5. |
| D-3 — `payload_hash` SHA-256 determinístico | ✅ | `CalcularDiagnosticoTest` + `GoldenHashesTest` verdes; ao vivo D1 e D2 com hash idêntico (`B-1-smoke-funil.txt`). |
| D-4 — `alertas_aceitos` fora do hash | ✅ | Teste "persiste alertas_aceitos sem alterar payload_hash" verde (CalcularDiagnosticoTest). |
| D-5 — `EventLogger::emit` única porta | ✅ | `EmissaoEventoArchTest` verde (0 infratores). |
| D-6 — Sem PII em `propriedades` | ✅ | Grep CNPJ/CPF/email/telefone em `evento_produto.propriedades`: **0 matches** (`D-6-pii-eventos.txt`). |
| D-7 — `request_id` UUID v7 | ✅ | Todos os eventos com `request_id` v7 válido (`D-7-request-id.txt`). Nota de método: ver Limitações. |
| D-8 — Cross-tenant retorna 404 (IDR-009) | ✅ | 2º usuário acessando diagnóstico alheio → **HTTP 404** (`D-8-cross-tenant.txt`). |

### Bloco E — Compatibilidade retroativa

| Item | Status | Evidência |
|---|---|---|
| E-1 — Diagnósticos legados abrem sem erro | ⚠️ | Legado `1.1.0` abre **200**, sem PHP fatal, indicadores renderizam (`E-1-legado-1.1.0.html`). **Ressalva**: o dev DB só tem `1.1.0` legado (+ `1.3.0` atual); não foi possível cobrir 5 versões distintas da faixa 1.0.0–1.2.x. |
| E-2 — Resumo placeholder legado silencia | ✅ | Diagnóstico `1.1.0` sem `veredito` → bloco Resumo Executivo ausente do HTML (`E-1-legado-1.1.0.html`). |

### Bloco F — Fora de escopo preservado

| Item | Status | Evidência |
|---|---|---|
| F-1 — Só Indústria selecionável | ✅ | `/diagnosticos/novo`: demais setores como "Em breve — Onda 2" (`F-diag-novo.html`). |
| F-2 — Sem PDF | ✅ | Zero "Exportar/Baixar PDF" no relatório (`B-3-relatorio.html`). |
| F-3 — Sem captação | ✅ | Zero "Solicitar análise de captação" (`B-3-relatorio.html`/`F-diag-novo.html`). |
| F-4 — Sem compartilhamento | ✅ | Zero "Compartilhar/link público" (`B-3-relatorio.html`). |
| F-5 — Sem feedback 👍/👎 | ✅ | Ausente do relatório (`B-3-relatorio.html`). |
| F-6 — Edição do quiz pós-cálculo não permitida | ✅ | Relatório read-only; única ocorrência de "Editar" é "Editar perfil" (conta), não o quiz (`B-3-relatorio.html`). |

### Bloco G — Validação externa (STORY-036) — pendente por decisão PO

| Item | Status | Evidência |
|---|---|---|
| G-1 — STORY-036 como pendência no index | ✅ | `index.json`: STORY-036 = `draft`; decisão de adiamento documentada em `STORY-036 §"Decisão do PO em 2026-05-26"`. |
| G-2 — Frase pré-beta no handoff | ❌ (não-bloq.) | Não verificável: `handoff/README.md` não existe (ver H-1). A frase obrigatória não está publicada no artefato exigido. |
| G-3 — Débito da validação externa como item de backlog | ❌ (não-bloq.) | Intenção registrada em STORY-036, mas **sem** item de backlog com janela + responsável definidos. |

### Bloco H — Pacote de handoff (STORY-037 CA-6)

| Item | Status | Evidência |
|---|---|---|
| H-1 — `handoff/README.md` cobre 9 pontos + pré-requisito G-2 | ❌ (não-bloq.) | **Arquivo ausente** — `handoff/` contém apenas `PO-retomada-2026-05-26.md`. Deliverable formal de STORY-037 não produzido. |
| H-2 — Decisão "sem dry-run" + risco no handoff §Riscos | ❌ (não-bloq.) | Decisão registrada em `checklist.md` H-2 e `PO-retomada-2026-05-26.md`, mas a seção §Riscos do handoff README não existe (depende de H-1). |
| H-3 — Tag `v1.0.0` criada | ❌ (não-bloq.) | `git tag` lista apenas rc até `v0.9.1-rc.1`. Tag `v1.0.0` (ou equivalente) não criada. |

### Bloco I — Promoção do épico

| Item | Status | Evidência |
|---|---|---|
| I-1 — EPIC-002 → `done_under_review` | ✅ | Promovido no `index.json` como output desta validação + `validation_report` apontando para este arquivo. |
| I-2 — EPIC-003 → `ready` | ❌ (não-bloq.) | `index.json`: EPIC-003 ainda `draft`. Recomendo ao PO destravar (decisão já tomada em STORY-036), mas não promovi epic alheio ao validado. |
| I-3 — Fechamento da sprint W25 preenchido | ❌ (não-bloq.) | Seção "Fechamento do sprint" existe mas é stub ("A preencher pelo PO"). |
| I-4 — Comunicação ao stakeholder com alerta pré-beta | ❌ (não-bloq.) | Sem registro de comunicação. |

---

## Fails identificados

### Bloqueantes

> Nenhum. O produto não tem defeito que impeça o fechamento técnico.

### Não-bloqueantes

#### F-NB-1 — Validação executada em localhost dev, não em homolog rc
- **Bloco**: Ambiente (afeta A-3, B-1..B-10, D-6..D-8, E).
- **Descrição**: O checklist pede homolog `https://defonline.xandrix.com.br` (responde 200) com fallback para `localhost:8090` registrando esta defasagem. A evidência com escrita em banco (smoke, eventos, p95, cross-tenant) foi coletada no dev (`defonline-web` + `defonline-db`), coerente com a política do projeto de nunca escrever em DB de produção via smoke. Não confirmei que o rc da W25 está deployado em homolog.
- **Sugestão**: PO/tech confirmar deploy do rc em homolog e re-rodar o roteiro de smoke read-only lá antes de abrir o beta.
- **Evidência**: `_env-probe.txt`, `A-3-p95.txt`.

#### F-NB-2 — `handoff/README.md` ausente (H-1, H-2, G-2)
- **Bloco**: H-1/H-2 + G-2.
- **Descrição**: O pacote de handoff é deliverable formal de STORY-037 ("não é opcional se sobrar tempo") e não existe. Sem ele, faltam os 9 pontos do CA-6, a seção §Riscos (decisão sem dry-run) e a frase obrigatória de pré-requisito de validação externa para o beta.
- **Sugestão**: PO/tech redigir `handoff/README.md` com os 9 pontos + §Riscos + frase pré-beta. Bloqueia o `done` da STORY-037 (não o `done_under_review` do épico).
- **Evidência**: `ls handoff/`.

#### F-NB-3 — Tag `v1.0.0` não criada (H-3)
- **Bloco**: H-3 + DoD STORY-037.
- **Descrição**: Não há tag de release pública; só rc.
- **Sugestão**: criar a tag (ou equivalente decidida no Checkpoint 5) ao concluir os deliverables de handoff.
- **Evidência**: `git tag --list 'v*'`.

#### F-NB-4 — Fechamento de sprint, comunicação e destrave do EPIC-003 (I-2, I-3, I-4)
- **Bloco**: I-2/I-3/I-4.
- **Descrição**: Itens de fechamento sob responsabilidade do PO ainda abertos: EPIC-003 segue `draft`; "Fechamento do sprint" é stub; sem registro de comunicação ao stakeholder.
- **Sugestão**: PO executa o fechamento; destravar EPIC-003 para SPRINT-2026-W30 conforme decisão já registrada.

#### F-NB-5 — Teste de imutabilidade do snapshot ausente (D-2)
- **Bloco**: D-2.
- **Descrição**: O checklist referencia `Tests\Architectural\SnapshotImutabilidadeTest` como "existente"; ele não existe. A imutabilidade hoje é garantida por schema NOT NULL + ausência de caminho de update + criação-única — funciona, mas não há teste de regressão que trave uma futura introdução de `update`.
- **Sugestão**: estória pequena criando o teste arquitetural (assert: nenhum `update`/`save` muta colunas de snapshot).
- **Evidência**: busca `SnapshotImutab|Architectural|Imutabilidade` = 0 ocorrências; schema not-null confirmado.

#### F-NB-6 — Doc da STORY-035 fora de sincronia (C-035)
- **Bloco**: C-035.
- **Descrição**: STORY-035 é `done` no `index.json` e no commit, mas o arquivo da estória tem `status: draft` e CAs não marcados; não há `report-STORY-035.md`. Substância 100% verde nesta validação.
- **Sugestão**: PO reconciliar front-matter para `done`, marcar CAs e (opcional) registrar o report individual. Sinal de processo: "done means done" no `done-checklist.md` do Programador não pegou a atualização do arquivo.

#### F-NB-7 — CAs não marcados em STORY-027/029/030 (C-027/029/030)
- **Bloco**: C-027/029/030.
- **Descrição**: estórias `done` no index, mas com CAs em `- [ ]` no arquivo (027/034 mostram que a convenção é marcar `[x]`).
- **Sugestão**: varredura de higiene documental marcando os CAs cumpridos.

#### F-NB-8 — Débito da validação externa sem item de backlog (G-3)
- **Bloco**: G-3.
- **Descrição**: a contratação do validador externo (NRF §9.3) é pré-requisito do beta; está descrita como intenção em STORY-036, mas não há item de backlog com janela + responsável.
- **Sugestão**: PO abrir épico/estória de débito com janela e dono antes do início do beta.

---

## Passes com ressalva

- **A-3** — p95 = 20 ms, muito abaixo do limite, porém medido em localhost dev (não homolog) — ver F-NB-1.
- **C-027 / C-029 / C-030 / C-035** — funcionalidade verde; ressalvas de higiene documental (CAs não marcados; STORY-035 com front-matter `draft`).
- **D-2** — imutabilidade do snapshot garantida por schema + uso, mas sem o teste de regressão nomeado.
- **E-1** — legados abrem sem erro, mas só a versão `1.1.0` legada existe no ambiente (cobertura parcial da faixa 1.0.0–1.2.x).

---

## Recomendação ao PO

### Sobre o épico
**APPROVED com pendências.** O produto cumpre o essencial técnico com folga e foi promovido a `done_under_review` (não `done` puro), exatamente como sua decisão de 2026-05-26 previu. Nenhuma pendência é bloqueante de qualidade de produto; **todas são deliverables de fechamento/handoff/processo**. **O beta permanece bloqueado** até a STORY-036 voltar `approved`/`approved_with_pending` — condição que deve estar explícita no handoff.

### Antes de declarar a STORY-037 `done` (deliverables ainda abertos)
- Redigir `handoff/README.md` (F-NB-2) — 9 pontos + §Riscos + frase pré-beta.
- Criar a tag `v1.0.0`/equivalente (F-NB-3).
- Preencher o fechamento da sprint W25 e comunicar ao stakeholder (F-NB-4).
- Destravar EPIC-003 → `ready` (F-NB-4 / I-2).
- Registrar o débito da validação externa como item de backlog com janela + dono (F-NB-8).

### Estórias/itens de correção sugeridos (decisão final do PO)
- **(doc)** Reconciliar STORY-035 (front-matter + CAs + report) e higiene de CAs em 027/029/030 — F-NB-6/F-NB-7. Tamanho: S.
- **(teste)** Criar `SnapshotImutabilidadeTest` arquitetural — F-NB-5. Tamanho: S.
- **(homolog)** Re-rodar smoke read-only em homolog após confirmar deploy do rc — F-NB-1. Tamanho: S.

### Observações de processo (input para retro)
- STORY-035 `done` no index com arquivo `draft` sugere que a etapa de atualizar o arquivo da estória escapou do `done-checklist`. Vale lembrete no próximo sprint.
- O handoff sendo deliverable formal e ainda ausente no momento da validação indica que o "como entregar o handoff" pode entrar mais cedo no fluxo da estória de fechamento.

---

## Limitações da validação

- **Ambiente**: toda a evidência com escrita em banco foi coletada em `localhost:8090` (dev), não em homolog rc — F-NB-1. Homolog responde 200, mas não confirmei o build servido nem escrevi nele (política de não escrever em prod via smoke).
- **Smoke E2E (B-1)**: o caminho browser→quiz→diagnóstico está provado pela suíte Dusk (17 verdes). Para evidência persistente de eventos/relatório/p95, exercitei o **caminho real da aplicação** (`CalcularDiagnostico` + `EventLogger`, mesmos pontos de emissão do componente `Quiz`) via tinker no dev DB. Não produzi screencast `.mov`; substituí por Dusk + funil textual + HTML do relatório + SQL.
- **request_id (D-7)**: os 3 eventos do meu funil compartilham um único `request_id` por terem rodado num único processo CLI (o `RequestId` gera um v7 sob demanda e o reusa). Em HTTP real cada request teria o seu. Cada valor é um v7 válido — o que o item D-7 exige; a correlação de funil usa `quiz_id`, que está correto.
- **Cobertura geral (A-1)**: o renderizador de tabela do Pest estourou memória sobre o `app/` inteiro; o número (97.3%) veio do relatório clover (`--coverage-clover`), método de menor pico de memória. A cobertura do motor (99.6%) saiu pela config de domínio sem problema.
- **Compat retroativa (E-1)**: só a versão legada `1.1.0` existe no dev DB; não cobri 5 versões distintas.

---

## Apêndice — Arquivos de evidência (`validation/evidence/`)

- `_env-probe.txt` — ambiente, driver de cobertura, versões.
- `A-1-coverage.txt` — cobertura do motor (99.6%).
- `A-1b-coverage-geral.txt` / `A-1b-clover.xml` — cobertura geral (97.3%).
- `A-2-cobertura-por-formula.txt` — cenários por indicador (204).
- `A-3-p95.txt` / `A-3-p95-raw.txt` — p95 (20 ms) em 50 navegações.
- `A-4-lint.txt` — Pint + PHPStan.
- `A-5-dusk.txt` — 17 testes Dusk verdes.
- `B-1-smoke-funil.txt` — funil real (rascunho→evento→D1/D2, hash idêntico).
- `B-3-relatorio.html` — relatório renderizado (base de B-3/B-4/B-5/B-6/B-9/F-2..F-6).
- `B-10-eventos-sql.txt` — eventos com payload ADR-004.
- `D-6-pii-eventos.txt` — 0 PII.
- `D-7-request-id.txt` — request_id v7 válido.
- `D-8-cross-tenant.txt` — 404 cross-tenant.
- `E-1-legado-1.1.0.html` / `E-1-snapshots-legados.txt` — legado renderiza; resumo silencia.
- `F-diag-novo.html` / `F-empresa-nova.html` — fora de escopo preservado.

---

## Histórico

- 2026-05-25 — relatório inicial submetido pelo validador (agente Claude, sessão STORY-037). Commit validado `abcf09b`. Veredito `approved_with_pending`.
