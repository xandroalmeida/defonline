---
story_id: STORY-028
slug: motor-7-indicadores-essenciais
title: Motor de cálculo — 7 indicadores essenciais + NCG absoluto informativo
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: ready
owner_agent: claude-programador
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: L
---

# STORY-028 — Motor: 7 indicadores essenciais + NCG absoluto

> **Para o agente programador:** esta é a fatia V1 do motor — 7 indicadores essenciais que entregam um relatório minimalista no Checkpoint 1 da sprint. Os 7 restantes ficam na STORY-030 (V2). Esta estória entrega: **classe de motor isolada**, **14 fórmulas dos 7 indicadores essenciais**, **cálculo do NCG absoluto informativo (sem farol)**, **lógica de farol verde/amarelo/vermelho para Indústria conforme §4.5**, **persistência em `indicadores_calculados`** (snapshot) e **cobertura ≥ 98% com ≥ 10 casos por fórmula** (NRF §9.2).

## Contexto

A epic.md sinaliza explicitamente o princípio de fatiamento vertical: *"primeira estória entrega quiz + motor com ~7 indicadores essenciais + relatório minimalista; estórias seguintes completam para 14 indicadores."*

Os 7 indicadores **essenciais** escolhidos pelo PO para a V1 (cobrem as decisões mais críticas que Roberto enfrenta — investir, captar, ajustar preço):

1. **Margem Bruta** — vendas vs custo direto.
2. **Margem Líquida** — resultado final sobre vendas.
3. **Dívida Líquida / EBITDA** — alavancagem.
4. **NCG / Vendas** — capital de giro proporcional ao tamanho.
5. **PMR** — prazo médio de recebimento.
6. **PMC** — prazo médio de pagamento (compras).
7. **Ciclo Financeiro** — (PMR + PME) − PMC.

**Mais:** **NCG absoluto (R$)** — calculado mas **sem farol**, exibido com mensagem informativa em 3 faixas (negativo / moderado ≤10% vendas / alto >10%), conforme spec §4.5. **Decisão registrada na epic.md.**

Fórmulas exatas em `especificacao/V2/especificacao-funcional.md §4.5`.

## O quê

1. **Pacote `app/Domain/Motor/`** (módulo isolado, fácil de testar, fácil de extrair para package se evoluir):
   - `MotorV1.php` — orquestrador.
   - `Indicadores/MargemBruta.php`, `MargemLiquida.php`, etc. — 1 classe por indicador (cumpre OCP).
   - `Indicadores/Indicador.php` — interface comum (`calcular(QuizPayload $p): IndicadorResultado`).
   - `Farois/FarolIndustria.php` — mapeia valor calculado → cor (`verde`, `amarelo`, `vermelho`, `nenhum`).
   - `IndicadorResultado.php` — value object (`valor`, `farol`, `mensagem_curta`, `disponivel`).
2. **Tratamento de casos extremos** conforme catálogo da STORY-026 — divisão por zero, vendas=0, etc.: `disponivel = false`, `valor = null`, `mensagem_curta = "indisponível porque..."`.
3. **NCG absoluto sem farol:** `farol = 'nenhum'`, `mensagem_curta` selecionada entre 3 strings conforme faixa.
4. **Persistência:** ao receber o quiz finalizado, motor calcula 8 valores (7 + NCG abs), monta JSON `indicadores_calculados`, grava no `diagnosticos.indicadores_calculados`. Também grava `motor_version = "1.0.0"`, `matrix_version = "dez-2025"`.
5. **Endpoint Livewire/Action:** chamada de STORY-027 ao submit dispara `App\Actions\CalcularDiagnostico::execute($empresa, $quizPayload)`. Retorna `Diagnostico` salvo.
6. **Latência:** medida em CA-7. Alvo p95 ≤ 3s (com 7 indicadores, é trivial — mas estabelece baseline).
7. **Testes:**
   - 1 unit test por indicador (suite de ≥ 10 casos canônicos = mínimo NRF §9.2).
   - Fixtures de quiz em `tests/fixtures/quiz/industria/` em formato YAML — 10 perfis por indicador, com input + valor esperado + farol esperado.
   - Golden test: dado fixture X, motor produz hash Y; mudou hash = mudou comportamento = PR exige justificativa + bump.

## Por quê

Sem motor, não há relatório. Sem teste de motor, não há validação externa possível. Esta é a estória mais densa em testes do EPIC-002 (≥ 70 casos só nesta — 7 indicadores × 10 + casos do NCG abs).

## Critérios de aceite

- [ ] **CA-1 (Estrutura modular):** pacote `app/Domain/Motor/` com 1 classe por indicador, interface comum, value object de retorno.
- [ ] **CA-2 (7 indicadores corretos):** Margem Bruta, Margem Líquida, Dívida Líq./EBITDA, NCG/Vendas, PMR, PMC, Ciclo Financeiro — fórmulas em `app/Domain/Motor/Indicadores/*.php` batem com §4.5 da spec.
- [ ] **CA-3 (NCG absoluto informativo):** classe `NcgAbsoluto.php` calcula o valor e retorna `farol = 'nenhum'` + mensagem em 1 de 3 faixas (negativo / moderado / alto) com texto exato da spec §4.5.
- [ ] **CA-4 (Farol Indústria):** `FarolIndustria.php` aplica as faixas exatas do §4.5 para o setor Indústria. Faixas em arquivo de config separado (`config/motor/faroes-industria.php`) — facilita ajuste pela validação externa sem mexer em código.
- [ ] **CA-5 (Casos extremos):** divisão por zero, vendas=0, EBITDA negativo, valores nulos — todos retornam `disponivel = false` + mensagem semântica, sem exception. Cobertos por testes específicos.
- [ ] **CA-6 (Persistência):** action `CalcularDiagnostico::execute` salva `Diagnostico` com `quiz_payload`, `indicadores_calculados` (JSON com 8 entradas), `motor_version`, `matrix_version`, `status = 'concluido'`. Idempotente (rerun com mesmo input + mesmas versões = mesmo hash de output, devolve o registro existente).
- [ ] **CA-7 (Latência p95):** medição em ambiente homol. 50 chamadas com fixtures variadas; p95 ≤ 0.5s nesta estória (motor com 7 indicadores; budget total p95 ≤ 3s inclui render do relatório que vem em STORY-029).
- [ ] **CA-8 (Cobertura ≥ 98%):** suite Pest do pacote `app/Domain/Motor/` atinge ≥ 98% (gate de regra de núcleo conforme `quality-standards.md`).
- [ ] **CA-9 (≥ 10 casos por indicador):** fixtures em `tests/fixtures/quiz/industria/` — 10 perfis canônicos por indicador. Documentar em `design/fixtures.md` o critério de seleção (verde típico, amarelo típico, vermelho típico, edge cases…).
- [ ] **CA-10 (Golden hash):** fixture canônica `tests/fixtures/quiz/industria/perfil-padrao-roberto.yaml` produz hash X (registrado em teste); qualquer PR que mude X exige nota de revisão explicando a mudança intencional.

## Fora de escopo

- 7 indicadores restantes — STORY-030.
- Resumo Executivo — STORY-031.
- Matriz DEZ/2025 (textos de recomendação) — STORY-032.
- Render do relatório — STORY-029.
- Validação cruzada DRE×Balanço — STORY-034.
- Eventos analíticos — STORY-035.

## Dependências

- **Bloqueada por:** STORY-026 (schema + idempotência + casos extremos), STORY-027 (quiz submete payload).
- **Bloqueia:** STORY-029 (render), STORY-030 (extensão), STORY-031 (Resumo Executivo lê os indicadores).

## Decisões já tomadas

- Motor único atual (`MotorV1`); evolução por versão de motor (não por branch de motor antigo). Snapshot dos resultados em `indicadores_calculados` garante reprodutibilidade.
- Faixas de farol em arquivo de config (`config/motor/faroes-industria.php`) — facilita ajuste pelo Validador externo (STORY-036).
- Casos extremos retornam `disponivel = false`, sem exception.
- NCG absoluto sem farol — decisão registrada na epic.md.

## DoD

- CA-1 a CA-10 passam.
- Pre-push verde + pipeline CI verde + deploy em homol.
- Tag `rc.W25S1.2`.
- `index.json` atualizado.

## Protocolo do agente

Padrão `agent-task-format.md`. Avisar PO ao entrar em `in_review`.

## Notas do agente

*(A preencher.)*
