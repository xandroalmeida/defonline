---
story_id: STORY-030
slug: motor-completar-14-indicadores
title: Motor — completar os 7 indicadores restantes (14 no total) + ajuste fino do farol Indústria
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: done
owner_agent: claude-programador
approved_by: Alexandro
approved_at: 2026-05-25
closed_at: 2026-05-25
created_at: 2026-05-25
updated_at: 2026-05-25
related_briefing: briefings/STORY-030-abertura.md
parallel_with: STORY-031
estimated_session_size: L
---

# STORY-030 — Motor: completar 14 indicadores

> Estende STORY-028 com os 7 indicadores que faltam para fechar o conjunto canônico da spec §4.5. Não muda arquitetura — adiciona classes seguindo o mesmo padrão.

## Contexto

V1 (STORY-028) entregou 7 essenciais. Esta estória completa o conjunto canônico de 14 da spec §4.5: **Margem EBITDA, Despesas Financeiras / EBITDA, Fontes de Recursos (PC/PL), Giro do Ativo, PME, Inadimplência de Clientes, Ciclo Operacional**. Mais ajuste fino das faixas de farol para Indústria conforme §4.5 — algumas faixas só fazem sentido vistas em conjunto (ex.: ciclo operacional × ciclo financeiro).

## O quê

1. **7 novas classes** em `app/Domain/Motor/Indicadores/`, seguindo a interface `Indicador` da STORY-028.
2. **Fórmulas exatas** conforme §4.5; tratamento de casos extremos conforme catálogo da STORY-026.
3. **Faixas de farol** atualizadas em `config/motor/faroes-industria.php` cobrindo agora 13 indicadores com farol + 1 sem (NCG abs).
4. **Fixtures**: ≥ 10 casos por indicador novo (mais 70 casos no total).
5. **Cobertura ≥ 98%** mantida no pacote `app/Domain/Motor/`.
6. **Bump do `motor_version`** para `"1.1.0"` (minor — novos indicadores, sem mudar comportamento dos 7 existentes).
7. **Migration de dados:** diagnósticos antigos em `motor_version = "1.0.0"` continuam reproduzíveis (snapshot preservado); novos diagnósticos passam a calcular 14.

## Critérios de aceite

- [x] **CA-1:** 7 classes novas, padrão STORY-028.
- [x] **CA-2:** Fórmulas batem com §4.5 (revisão do PO em par com Arquiteto).
- [x] **CA-3:** Faixas de farol revisadas para os 7 novos + ajuste fino dos 7 antigos se a leitura do Anexo F sinalizar inconsistência (registrar em decisão).
- [x] **CA-4:** ≥ 10 casos por indicador novo. Total ≥ 140 casos no pacote motor.
- [x] **CA-5:** Cobertura ≥ 98% mantida.
- [x] **CA-6:** `motor_version` bumpa para `"1.1.0"`. Golden hash da fixture canônica muda (esperado); novo hash registrado.
- [x] **CA-7:** Diagnósticos antigos (`motor_version = 1.0.0`) continuam reproduzíveis em `/diagnosticos/{id}` — snapshot `indicadores_calculados` é a fonte da verdade, não recalcula.
- [x] **CA-8 (latência):** p95 do motor com 14 indicadores ≤ 1s (parte do budget total ≤ 3s).
- [x] **CA-9 (relatório):** STORY-029 já lê dinamicamente os indicadores do snapshot — sem mudança de UI nesta estória? Verificar; se a tabela tem header hardcoded para 7, ajustar.

## Fora de escopo

- Resumo Executivo (STORY-031).
- Matriz DEZ/2025 (STORY-032).
- Recalcular diagnósticos antigos (regra: não fazemos).

## Dependências

- **Bloqueada por:** STORY-028.
- **Bloqueia:** STORY-031 (Resumo lê todos os 14), STORY-032 (matriz precisa dos 14).

## Decisões já tomadas

- Bump de `motor_version` para `1.1.0` (minor).
- Diagnósticos antigos preservados via snapshot.
- Faixas de farol em config externa.

## DoD

CA-1 a CA-9 + pre-push verde + pipeline verde + tag `rc.W25S2.1`. `index.json` atualizado.

## Protocolo do agente

Padrão. Avisar PO em `in_review`.

## Notas do agente

### 2026-05-25 — Abertura pelo Programador

**Documentos lidos:** STORY-030 + briefing-030 + briefing-031 (impacto no bump), epic.md, IDR-010, design/casos-extremos.md (34 entradas, cobre todos os 7 novos), design/idempotencia.md, app/app/Domain/Motor/ (todos os 8 arquivos), config/motor/faroes-industria.php, IndicadorFormatter, GoldenHashesTest + 5 fixtures.

**Estado de partida verificado:**
- `config('motor.version') === '1.0.0'` ✓
- STORY-031 em `status: ready` (não in_progress) → bump esperado: 1.0.0 → 1.1.0
- 182 testes do pacote `Domain/Motor` verdes (baseline)
- Branch: `main` limpo, sem PR pendente

**Decisões interpretativas (confirmadas com PO antes de codar):**
1. **Ciclo Operacional** = PME + PMR (sem PMC), informativo, `farol = 'nenhum'`, mensagem fixa. Anexo D só lista Ciclo Financeiro (#8) — Ciclo Operacional é complementar, sem faixa em Anexo E.
2. **Fixtures novos:** não criar preventivamente; ajustar os 5 atuais se faltar cobertura verde/amarelo/vermelho para algum dos 7 novos.

**Plano em 5 bullets:**
1. Faixas dos 7 novos em `config/motor/faroes-industria.php` (Anexo E literal).
2. Novos códigos de motivo em `MotivosIndisponibilidade` (`pl_nao_positivo`, `ativo_zero`, `ativo_componente_faltante`, `inadimplencia_faltante`, `ebitda_extremo`, `ciclo_operacional_prazo_faltante`).
3. TDD por indicador (10+ casos cada), ordem do briefing.
4. `Motor::indicadores()` reordenado conforme Anexo D + Ciclo Operacional informativo no final.
5. `IndicadorFormatter` atualizado + verificação visual no relatório + golden hashes re-emitidos + bump `motor_version → 1.1.0`.

### 2026-05-25 — Entrega (V2 nasceu + pronto para PR)

**Implementação completa:**

- 7 novas classes em `app/app/Domain/Motor/Indicadores/`: `Inadimplencia`, `Pme`, `MargemEbitda`, `DespesasFinEbitda`, `GiroAtivo`, `FontesRecursos`, `CicloOperacional`.
- `Motor::indicadores()` reordenado conforme Anexo D §4.5 (#1..#14 + Ciclo Operacional informativo no slot final). Chaves: 15 (era 8).
- `MotivosIndisponibilidade` ampliado com 6 novos códigos.
- `config/motor/faroes-industria.php` ampliado com 6 novas faixas (Ciclo Operacional não tem — informativo).
- `config/motor.php` bumpado para `1.1.0`.
- Golden hashes re-emitidos para os 5 fixtures (mudaram porque saída cresceu de 8 → 15 entradas, é o motivo do MINOR bump).
- `IndicadorFormatter` ampliado: `NOMES` com 15 entradas, `ORDEM_ESSENCIAIS` com 13 indicadores com farol (Ciclo Operacional + NCG abs ficam fora — cards informativos separados); `valor()` cobre novos códigos com unidades corretas (`%`, `×`, `dias`).
- View `diagnosticos/show.blade.php`: 13 linhas na tabela essenciais + grid `md:grid-cols-2` com 2 cards informativos (NCG abs + Ciclo Operacional). Componente `<x-relatorio.card-ncg>` generalizado para `<x-relatorio.card-informativo>` (aceita `codigo` como prop) — único arquivo deletado.
- Testes atualizados: `MotorTest` (15 indicadores em ordem do Anexo D), `IndicadorFormatterTest` (catálogo de 15), `CalcularDiagnosticoTest` (count 15 + `config('motor.version')` em vez de hardcoded).

**Cobertura final:**
- `app/Domain`: **100%** (gate ≥ 98%) — todas as 7 novas classes a 100%.
- Suíte completa: **674 testes verdes (1815 asserções)** — partindo de 577.
- 97 testes novos: Inadimplencia (12) + Pme (11) + MargemEbitda (17) + DespesasFinEbitda (15) + GiroAtivo (14) + FontesRecursos (16) + CicloOperacional (12).
- Pint + Larastan limpos.
- `MotorLatenciaTest` p95 ≤ 1s mantido.

**Verificação visual:**
- Seeder regenerado (`migrate:fresh --seed` + `DiagnosticosDemoSeeder`): 5 diagnósticos com `motor_version=1.1.0` × 15 indicadores.
- HTML renderizado conferido via `app()->handle()`: 28 ocorrências de `data-codigo` no relatório (13 essenciais × 2 layouts desktop+mobile + 2 informativos × 1 layout) ✓.
- Cobertura de farol nos 5 fixtures após bump: todos os 7 novos têm pelo menos 1 caso verde/amarelo/vermelho/indisponível (CA-4 satisfeito; faixa por faixa coberta nos unit tests, não nos fixtures).
- F-NB-1 — verificação visual em browser real (DevTools mobile + desktop): **pendente do PO** (briefing: "Verifico visualmente antes de aprovar").

**Pegadinhas encontradas:**
- Truncamento `bcadd(..., '0', 4)` matou fronteiras com delta < 0.0001 (ex.: `Fontes = 0.5001`). Solução: usar incrementos visíveis (`Q06 = 400100` para 0.5001 em vez de `400001`). Documentado no comentário inline do teste.
- `Margem EBITDA` ganhou caso de exception (`ebitda_extremo`) para margens < −100% via casos-extremos.md §2.2 — input suspeito não classifica, devolve indisponível.

**Decisão local:** componente `<x-relatorio.card-ncg>` foi generalizado para `<x-relatorio.card-informativo>` em vez de duplicar para Ciclo Operacional. Mudança mínima, sem novo IDR — refactor local de view.

**Impacto na STORY-031 (paralela):** já bumpei para `1.1.0`. Quando STORY-031 mergear sua substituição do `resumo_executivo`, terá de bumpar para `1.2.0` e re-emitir os 5 hashes do zero novamente. Briefing-031 §4 já cobre esse cenário.

**Pronto para PR.** Aguardando aval visual do PO antes de eventual tag rc.W25S2.1.
