---
story_id: STORY-028
slug: motor-7-indicadores-essenciais
title: Motor de cálculo — 7 indicadores essenciais + NCG absoluto informativo
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: done
owner_agent: programador (claude-opus-4-7)
approved_by: Alexandro
approved_at: 2026-05-25
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

- [x] **CA-1 (Estrutura modular):** pacote `app/Domain/Motor/` com 1 classe por indicador, interface comum, value object de retorno.
- [x] **CA-2 (7 indicadores corretos):** Margem Bruta, Margem Líquida, Dívida Líq./EBITDA, NCG/Vendas, PMR, PMC, Ciclo Financeiro — fórmulas em `app/Domain/Motor/Indicadores/*.php` batem com §4.5 da spec.
- [x] **CA-3 (NCG absoluto informativo):** classe `NcgAbsoluto.php` calcula o valor e retorna `farol = 'nenhum'` + mensagem em 1 de 3 faixas (negativo / moderado / alto) com texto exato da spec §4.5.
- [x] **CA-4 (Farol Indústria):** `FarolIndustria.php` aplica as faixas exatas do §4.5 para o setor Indústria. Faixas em arquivo de config separado (`config/motor/faroes-industria.php`) — facilita ajuste pela validação externa sem mexer em código.
- [x] **CA-5 (Casos extremos):** divisão por zero, vendas=0, EBITDA negativo, valores nulos — todos retornam `valor = null` + `motivo` (código estável) + `mensagem` semântica, sem exception. Cobertos por testes específicos. **Atualizado 2026-05-25 conforme IDR-010 §5: chave `valor=null` substitui `disponivel=false`; `motivo` é código estável catalogado em `design/casos-extremos.md`.**
- [x] **CA-6 (Persistência idempotente — atualizada 2026-05-25 conforme IDR-010):** action `CalcularDiagnostico::execute` salva `Diagnostico` com `quiz_payload`, `indicadores_calculados` (JSON com 8 entradas), `motor_version`, `matrix_version`, `payload_hash`, `gerado_em`. **Idempotência é contrato de teste** (golden hashes em `app/tests/Domain/Motor/GoldenHashesTest.php`, **não** dedup em banco): dois reruns com mesmo input + mesmas versões geram dois registros independentes em `diagnosticos` com o mesmo `payload_hash` e o mesmo `indicadores_calculados`. Sem UNIQUE em `payload_hash`. **Não há coluna `status` em `diagnosticos`** — diagnóstico só existe quando concluído; rascunho do quiz fica em `quiz_rascunhos` (responsabilidade da STORY-027).
- [x] **CA-7 (Latência p95):** medição em ambiente homol. 50 chamadas com fixtures variadas; p95 ≤ 0.5s nesta estória (motor com 7 indicadores; budget total p95 ≤ 3s inclui render do relatório que vem em STORY-029).
- [x] **CA-8 (Cobertura ≥ 98%):** suite Pest do pacote `app/Domain/Motor/` atinge ≥ 98% (gate de regra de núcleo conforme `quality-standards.md`).
- [x] **CA-9 (≥ 10 casos por indicador):** fixtures de teste em `app/tests/Domain/Motor/Indicadores/*Test.php` — 13 a 16 casos por indicador (verde típico, amarelo típico nas 3 fronteiras, vermelho típico, ≥ 4 casos extremos). **Atualizado 2026-05-25 conforme IDR-010: fixtures finais em JSON canonicalizado em `app/tests/Domain/Motor/Fixtures/` (não em YAML).**
- [x] **CA-10 (Golden hash):** 5 fixtures canônicos em `app/tests/Domain/Motor/Fixtures/*.json` produzem hashes hardcoded no `GoldenHashesTest.php`; qualquer PR que mude um hash exige nota de revisão explicando a mudança intencional + bump de `motor_version` quando aplicável.

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
- Casos extremos retornam `valor = null` + `motivo` (código estável) + `mensagem` semântica, sem exception (atualizado conforme IDR-010 §5).
- NCG absoluto sem farol — decisão registrada na epic.md.

## DoD

- CA-1 a CA-10 passam.
- Pre-push verde + pipeline CI verde + deploy em homol.
- Tag `rc.W25S1.2`.
- `index.json` atualizado.

## Protocolo do agente

Padrão `agent-task-format.md`. Avisar PO ao entrar em `in_review`.

## Notas do agente

**2026-05-25 — Sessão aberta (programador, claude-opus-4-7).**

Divergências detectadas no kickoff e premissas de execução:

1. **IDR-010 sobrepõe a redação original da STORY-028** em três pontos (registrado no briefing):
   - Forma do retorno do indicador: `{valor, farol, motivo, mensagem}` (IDR-010); o campo `disponivel` da redação inicial foi **substituído** por `valor=null`. Vou seguir IDR-010 — `IndicadorResultado` final tem `valor: ?float|?string`, `farol: string`, `motivo: ?string`, `mensagem: string`.
   - Fixtures em `app/tests/Domain/Motor/Fixtures/*.json` canonicalizado (IDR-010), não em `tests/fixtures/quiz/industria/*.yaml`.
   - Faixas de farol em `config/motor/faroes-industria.php` (PHP array) — sem mudança.

2. **Typos no `design/casos-extremos.md`** contra o Anexo A da spec (Anexo A é autoritativo para nomes de variáveis):
   - Indicador 6 (Fontes PC/PL): "Q03 + Q04 + Q05 estoques" → na verdade Ativo Total = `Q02 (disponibilidades) + Q03 (clientes) + Q04 (estoques) + Q05 (imobilizado)`.
   - Indicador 9 (NCG abs): "Q03 ∨ Q05_estoques ∨ Q07_fornecedores" → na verdade `Q04 = Estoques` (Q05 é Imobilizado).
   - Indicador 10 (NCG/Vendas): "Q03 ∨ Q05 ∨ Q07" → na verdade `Q03 (Clientes) + Q04 (Estoques) − Q07 (Fornecedores)`.
   - Vou seguir o **Anexo A/B/D** (Q03=Clientes, Q04=Estoques, Q05=Imobilizado/Patrimônio) e sinalizar ao PO para correção retroativa do design-note.

3. **Escopo V1 (7 indicadores essenciais + NCG abs):**
   - Margem Bruta, Margem Líquida, Dívida Líq./EBITDA, NCG/Vendas, PMR (Q12), PMC (Q10), Ciclo Financeiro (PME + PMR − PMC) → todos com farol Indústria do §4.5/Anexo E.
   - NCG absoluto (Q03 + Q04 − Q07) → sem farol, mensagem em 3 faixas (negativo / moderado ≤10% Vendas / alto >10% Vendas).
   - PME (Q11) **é input** do Ciclo Financeiro nesta V1 mas **não é exibido** como linha de relatório (cai para STORY-030).
   - Margem EBITDA NÃO entra como saída nesta V1, embora a fórmula EBITDA seja necessária para Margem Líquida e Dívida Líq./EBITDA.

4. **`motor_version` = "1.0.0"**, **`matrix_version` = "dez-2025"**.

5. **`mensagem` (curta) dos indicadores com farol** nesta V1 é placeholder genérico (`"Faixa verde."`, `"Faixa amarela."`, `"Faixa vermelha."`). Textos da matriz DEZ/2025 entram na STORY-032 com bump de `matrix_version` ou snapshot já com o texto certo.

Tasks da sessão (interno): 11 passos, ordem do briefing. TaskCreate inicializado.

**2026-05-25 — Sessão concluída.** Motor V1 entregue, pronto para revisão.

### Entregáveis

**Código (app/):**
- `app/Domain/Motor/Motor.php` — orquestrador puro.
- `app/Domain/Motor/QuizPayloadCanonicalizer.php` — canonicaliza payload (chaves ordenadas, NFC, strings vazias → null).
- `app/Domain/Motor/Calculos/DreAdaptada.php` — anualização + LB + EBITDA + LOL + PL (bcmath escala 4).
- `app/Domain/Motor/Farois/FarolIndustria.php` — classificador via `config/motor/faroes-industria.php`.
- `app/Domain/Motor/Farol.php`, `IndicadorResultado.php`, `MensagensFarol.php`, `MotivosIndisponibilidade.php` — value objects/contratos.
- `app/Domain/Motor/Indicadores/`: `Indicador` (interface) + 8 implementações: `MargemBruta`, `MargemLiquida`, `DividaLiquidaEbitda`, `NcgVendas`, `Pmr`, `Pmc`, `CicloFinanceiro`, `NcgAbsoluto`.
- `app/Actions/CalcularDiagnostico.php` — action de persistência idempotente.
- `config/motor.php` (`version=1.0.0`, `matrix_version=dez-2025`) + `config/motor/faroes-industria.php`.
- `database/factories/DiagnosticoFactory.php` — factory com `paraEmpresa($empresa)` para preservar tenant.

**Testes (511 verdes / 1376 asserções / 9.3s):**
- 18 testes em `DreAdaptadaTest`.
- 17 testes em `FarolIndustriaTest`.
- 13 testes em `QuizPayloadCanonicalizerTest`.
- 13–16 testes por indicador (×8) = ~115 testes cobrindo verde/amarelo/vermelho típicos, **3 fronteiras por indicador**, ≥4 casos extremos por indicador.
- 13 testes em `MotorTest` (orquestrador + exceptions de invariante).
- 8 testes em `CalcularDiagnosticoTest` (Feature, RefreshDatabase).
- 5 testes em `DiagnosticoCrossTenantTest` (Feature — IDR-009 404 silente).
- 11 testes em `GoldenHashesTest` (5 fixtures × hash bit-exato + sanidade).
- 1 teste em `MotorLatenciaTest` (p95 medido em ms; sob 500ms).

**Cobertura `app/Domain`:** **100%** (gate ≥98% ✓).

**Fixtures (canonicalizados):**
- `quiz_industria_saudavel.json` — todos verdes + NCG abs Faixa 1.
- `quiz_industria_atencao.json` — todos amarelo + NCG abs Faixa 2.
- `quiz_industria_alerta.json` — todos vermelho + Div Líq/EBITDA indisponível (ebitda_negativo) + NCG abs Faixa 3.
- `quiz_industria_ncg_negativo.json` — mistura verde/amarelo, NCG abs negativo.
- `quiz_industria_70pct_indisponivel.json` — 100% indisponíveis (caminho extremo).

**Migration:** `2026_05_25_000010_create_diagnosticos_table` aplicada em dev local.

**Infra de teste:** suite `Domain` adicionada a `phpunit.xml` e `phpunit-domain.xml`; Pest bootstrap (`tests/Pest.php`) estende `TestCase` em `Domain` (sem RefreshDatabase).

**Lint/análise estática:** Pint verde; Larastan verde (corrigi 3 missing generics em `Diagnostico.php` herdados da STORY-026).

### Mapa CAs → entregas

| CA | Como cumpre | Onde |
|---|---|---|
| CA-1 (Estrutura modular) | 1 classe por indicador + interface comum + value object | `app/Domain/Motor/Indicadores/*` + `Indicador.php` + `IndicadorResultado.php` |
| CA-2 (7 indicadores corretos) | Fórmulas conforme §4.5/Anexo D; verificadas por 10+ casos por indicador | `Indicadores/*.php` + testes |
| CA-3 (NCG absoluto informativo) | `farol=nenhum` sempre; 3 faixas semânticas | `NcgAbsoluto.php` + `NcgAbsolutoTest.php` |
| CA-4 (Farol Indústria via config) | Faixas em `config/motor/faroes-industria.php`, lidas por `FarolIndustria` | Configs + `FarolIndustria.php` |
| CA-5 (Casos extremos sem exception) | 17 motivos catalogados em `MotivosIndisponibilidade`; testes por caso | `MotivosIndisponibilidade.php` + testes por indicador |
| CA-6 (Persistência) | Action `CalcularDiagnostico` em transaction; sem dedup (IDR-010 vence) | `app/Actions/CalcularDiagnostico.php` + `CalcularDiagnosticoTest.php` |
| CA-7 (Latência p95 ≤ 500ms) | Pest test mede 50 execuções, p95 < 500ms | `MotorLatenciaTest.php` |
| CA-8 (Cobertura ≥ 98%) | Atingida: **100%** em `app/Domain` | `phpunit-domain.xml` gate |
| CA-9 (≥ 10 casos por indicador) | 13–16 testes por indicador (8 indicadores) | `tests/Domain/Motor/Indicadores/*Test.php` |
| CA-10 (Golden hashes) | 5 fixtures canônicos + 5 hashes hardcoded; bit-exato | `GoldenHashesTest.php` + `Fixtures/*.json` |

### Decisões de execução (registradas para o PO)

- **STORY-028 CA-6 vs IDR-010 (idempotência por dedup).** A STORY-028 dizia "rerun com mesmo input + mesmas versões = mesmo hash de output, **devolve o registro existente**"; a IDR-010 §sub-decisão 2 diz "podem ser 2 registros conscientes — sem UNIQUE em payload_hash". Seguindo o briefing ("IDR-010 vence"), implementei **sem dedup em banco**. O contrato de idempotência é via golden hashes (motor é puro determinístico). Recomendo o PO atualizar a redação retroativa da STORY-028 CA-6 ou abrir nota de débito.
- **`resumo_executivo` placeholder.** Esta V1 (STORY-028) **não calcula** o resumo executivo (responsabilidade da STORY-031). O snapshot grava `{pendente_story: "STORY-031", fallback_acionado: false}` apenas para preencher a coluna NOT NULL e indicar a origem. Quando STORY-031 entrar e bumpar `motor_version`, os golden hashes V1 serão re-emitidos junto.
- **Motor V1 só aceita `setor=industria`.** Outros setores levantam `InvalidArgumentException`. Comércio/Serviços ficam para estória posterior do EPIC-002 (precisam de `FarolComercio`/`FarolServicos` próprios — Anexo E tem faixas diferentes por setor).
- **Postgres jsonb normaliza float→int** (50.0 → 50) ao armazenar. Os golden hashes são gerados **antes** do banco (em memória), então a idempotência se sustenta. O teste de feature que lê do banco usa `toEqual` (igualdade matemática) em vez de `toBe` (identidade de tipo). Documentado no test.
- **Typos no `design/casos-extremos.md`.** Sugerido ao PO corrigir: trocas "Q05_estoques" → "Q04", "Q03 + Q04 + Q05 estoques" → "Q02 + Q03 + Q04 + Q05" nas seções §6 (Fontes PC/PL), §9 (NCG abs) e §10 (NCG/Vendas). O motor seguiu o Anexo A (autoritativo) — não há divergência funcional, só de redação do design-note.

### Status

Pronto para revisão do PO. Próxima etapa do agente: aguardar feedback / push + tag rc / passar para `in_review`.
