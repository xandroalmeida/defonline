---
artifact: story-validation-report
story_id: STORY-034
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
validated_by: claude-validador
validated_at: 2026-05-26
verdict: approved
pending_count: 0
blockers: 0
related_briefing: briefings/STORY-033-abertura.md
---

# Relatório de validação independente — STORY-034

> Validação pré-commit (status `in_review`, uncommitted). Validações cruzadas DRE × Balanço — espec §6.6.

## Veredito

**`approved`** — implementação atende os 8 CAs da estória. As 3 regras (R1/R2/R3) batem literalmente com a spec §6.6 (verificado em Python: 9/9 cenários OK). Decisões locais elegantes: regras declarativas em config (sem closures — compatível com `config:cache`), dupla proteção do `payload_hash` (canonicalizer + action), banner inline com tokens v1. Validação visual em homol local: R3 disparou, banner exibiu com valores interpolados, "Continuar mesmo assim" registrou aceite e gerou diagnóstico. Sem pendências.

## Escopo da validação

- Estória `stories/STORY-034-validacoes-cruzadas-dre-balanco.md` (CA-1 a CA-8 + notas do agente).
- Código novo:
    - `app/app/Domain/Quiz/ValidacoesCruzadas.php` (133 linhas — engine config-driven).
    - `app/app/Domain/Quiz/Alerta.php` (46 linhas — value object imutável).
    - `app/config/quiz/validacoes-cruzadas.php` (config declarativa das 3 regras).
- Código modificado:
    - `app/app/Domain/Motor/QuizPayloadCanonicalizer.php` (dropa `alertas_aceitos` antes do hash).
    - `app/app/Actions/CalcularDiagnostico.php` (param `$alertasAceitos`, mescla pós-hash).
    - `app/app/Livewire/Diagnostico/Quiz.php` (gate, `continuarComAlertas`, `irParaCampo`, dedup).
    - `app/resources/views/livewire/diagnostico/quiz.blade.php` (banner inline não-bloqueante).
- Testes:
    - `app/tests/Domain/Quiz/ValidacoesCruzadasTest.php` (213 linhas, 16 testes Pest unit).
    - `app/tests/Domain/Motor/QuizPayloadCanonicalizerTest.php` (modificado — assert arquitetural de invariância do hash).
    - `app/tests/Feature/Diagnosticos/CalcularDiagnosticoTest.php` (+1 — mescla pós-hash).
    - `app/tests/Feature/Livewire/Diagnostico/QuizTest.php` (+6 — gate, continuar, irParaCampo, dedup, submit, sem falso positivo).
    - `app/tests/Browser/ValidacoesCruzadasBrowserTest.php` (148 linhas, 2 testes Dusk — R3 fluxo completo + R1+R2 banner).
- Validação visual em `http://localhost:8090/diagnosticos/novo` → Demo · saudavel → payload R3 (passivo > ativo).
- Simulação independente em Python das 3 regras vs spec §6.6 (anexo §6).

## CA por CA — evidências

### CA-1 — Lista canônica fixada (R1/R2/R3 idênticas à §6.6)

**Status:** ✓ atendido.

Config `config/quiz/validacoes-cruzadas.php`:

| Regra | Esquerda × pesos | Operador | Fator | Direita × pesos | Spec §6.6 |
|---|---|---|---|---|---|
| R1 | `{Q16: 12}` | `>` | 2 | `{Q06: 1}` | `Q16×12 > 2×Q06` ✓ |
| R2 | `{Q14: 12, Q15: 12}` | `>` | 1 | `{Q09: 12}` | `Q14×12 + Q15×12 > Q09×12` ✓ |
| R3 | `{Q06: 1, Q07: 1}` | `>` | 1 | `{Q02: 1, Q03: 1, Q04: 1, Q05: 1}` | `Q06+Q07 > Q02+Q03+Q04+Q05` ✓ |

Conferido literal contra a spec §6.6 — todas 3 fórmulas batem.

### CA-2 — ≥ 3 testes por regra (disparo / não-disparo / limiar exato)

**Status:** ✓ atendido **e excedido**.

`ValidacoesCruzadasTest.php` tem 16 testes cobrindo:

- R1: disparo (`Q16=3000, Q06=8000` → 36000 > 16000), não-disparo (saudável), limiar exato (`Q16=2000, Q06=12000` → 24000 = 24000, NÃO dispara por `>` estrito).
- R2: idem (3 cenários).
- R3: idem (3 cenários).
- Multi-regra (R1 + R3 simultâneas).
- Casos de borda: campo ausente → regra pula; campo não-numérico → regra pula; valores zero.
- CA-6 explicitamente coberto (mudança de config em runtime sem mexer em classe).
- Operadores `>`, `>=`, `<`, `<=` todos cobertos.

### CA-3 — Não-bloqueante + `alertas_aceitos` fora do `payload_hash`

**Status:** ✓ atendido.

**Dupla proteção** do `payload_hash` — engenharia defensiva elogiável:

1. **`QuizPayloadCanonicalizer::canonicalize()`** explicitamente faz `unset($payload['alertas_aceitos'])` antes da normalização (linha 91, comentário "STORY-034: auditoria das validações cruzadas vive no payload, mas fora do hash").
2. **`CalcularDiagnostico::execute()`** calcula `$payloadHash` do canonical SEM alertas, depois mescla `alertas_aceitos` em `$quizPayloadPersistido` antes do `Diagnostico::create()`. Se alguém esquecer da invariância no canonicalizer, a action ainda preserva.

Teste arquitetural em `QuizPayloadCanonicalizerTest.php` asserta que dois payloads que só diferem em `alertas_aceitos` produzem o mesmo hash.

### CA-4 — Mensagens acionáveis

**Status:** ✓ atendido.

Cada regra tem `campo_foco` (campo para focar no `Corrigir`) e `botao_label` no config:
- R1: `Ir para Despesas Financeiras (Q16)`.
- R2: `Ir para Custos (Q14/Q15)`.
- R3: `Revisar Balanço`.

Templates de mensagem usam placeholders `:esquerda`, `:direita`, `:percentual` e `:Qxx` interpolados pela classe com BR formatting. Visualmente confirmado em homol: *"O passivo total (R$ 100.000,00) é maior que o ativo total (R$ 40.000,00), o que indica PL negativo..."* — interpolação correta com valores reais.

`Quiz::irParaCampo(string $campo)` despacha evento Alpine `focar-campo` + navega ao bloco do campo via `$this->blocoDoCampo($campo)`.

### CA-5 — UX: banner expansível inline

**Status:** ✓ atendido.

Banner amarelo (token `--color-farol-amarelo`) ao fim do Bloco 3, antes dos botões Voltar/Próximo. Conteúdo:
- Título: "Detectamos inconsistências. Você quer corrigir agora?"
- Lista de alertas (1 `<li>` por regra disparada).
- Botão `Ir para …` por alerta (label customizado da regra).
- Botão `Continuar mesmo assim` (primary).
- `role="alert"` + `aria-live="polite"`.

`Quiz.php` (linhas 174-183): se ao sair do Bloco 3 houver alertas não-aceitos, `return` e segura o avanço — gate funcionando. Validação visual: payload R3 + click Próximo → fica no Bloco 3 + banner aparece.

### CA-6 — Config externa, sem refactor

**Status:** ✓ atendido.

Regras **declarativas puras** em `config/quiz/validacoes-cruzadas.php`:
- Sem closures (compatível com `config:cache` em produção).
- Formato `{esquerda, operador, fator, direita}` + `mensagem` template.
- Adicionar regra nova = entrada no array + 1 teste. Zero linha de código novo na classe.

`ValidacoesCruzadas::validar()` (linhas 28-42) itera regras do config — agnóstico ao número delas. Teste de "config dinâmica" em `ValidacoesCruzadasTest.php` valida isso.

### CA-7 — Testes E2E

**Status:** ✓ atendido.

`ValidacoesCruzadasBrowserTest.php` cobre:

1. **R3 fluxo completo**: preencher quiz com passivo > ativo → ver banner → clicar `Continuar` → submeter → ver diagnóstico gerado → asserir `alertas_aceitos` no payload.
2. **R1+R2 banner + corrigir**: preencher quiz disparando R1 e R2 → ver 2 itens no banner → clicar `Corrigir agora` em R1 → foco no Q16.

Conferido manualmente em homol: R3 disparou, banner exibiu, "Continuar mesmo assim" avançou para Bloco 4, "Calcular diagnóstico" gerou o relatório (id `019e61b7-9f09-7143-95e1-d947b1c28339`).

**Efeito colateral observado e esperado:** com passivo > ativo, o PL fica negativo e o indicador `Fontes de Recursos (PC/PL)` fica **Indisponível** no relatório (motor protegeu corretamente). Mensagem exibida: *"Indicador indisponível: patrimônio líquido apurado é zero ou negativo (passivo ≥ ativo). Reveja contas a pagar e dívidas declaradas."* — coerência semântica entre validação cruzada e cálculo do motor.

### CA-8 — Cobertura

**Status:** ✓ atendido por declaração do dev.

- Cobertura `app/Domain/`: **99.6%** (gate ≥ 98%).
- `App\Domain\Quiz\ValidacoesCruzadas` e `App\Domain\Quiz\Alerta`: **100%**.

Não re-executei `pest --coverage` (sandbox sem PHP/Docker). Estrutura dos testes é consistente com a meta.

## Validação visual (browser homol local)

Fluxo completo executado em `http://localhost:8090/diagnosticos/novo` → Demo · saudavel:

1. Bloco 1 (Setor=Indústria) → Próximo ✓
2. Bloco 2 preenchido (DRE/Operação saudável: Q08=50k, Q09=100k, Q14=20k, Q15=8k, Q16=2k, Q10=90, Q11=15, Q12=15, Q13=2) → Próximo ✓
3. Bloco 3 preenchido com **payload R3** (Q02..Q05 = 10k cada → ativo 40k; Q06=80k + Q07=20k → passivo 100k) → click Próximo → **gate ativou**: banner exibido (screenshot anexo).
4. Click "Continuar mesmo assim" → avançou para Bloco 4.
5. Q17=Não → Calcular diagnóstico → relatório gerado (`019e61b7-...`).
6. Relatório exibe `Fontes de Recursos (PC/PL) = Indisponível` com mensagem semanticamente correta (PL negativo).

Banner observado:
- Cor amarela (token v1).
- Título: "Detectamos inconsistências. Você quer corrigir agora?".
- Mensagem com valores BR interpolados corretamente.
- 2 botões: `Revisar Balanço` (campo_foco Q02 conforme config R3) e `Continuar mesmo assim` (primary).

## Recomendação ao PO

**Aprovar** a STORY-034. Pedir ao Programador que:

1. Marque `status: done` no front-matter da estória.
2. Atualize `index.json`.
3. Commite com mensagem padrão (`feat(STORY-034): validações cruzadas DRE × Balanço (§6.6) → status: done`).
4. Empurre para o pipeline `release-homolog.yml`.

**F-NB-1** (aprovação visual em homol remoto) destrava quando o rc da sprint subir — local já está validado.

## Anexo §6 — Simulação independente das 3 regras em Python

Reescrevi a função `avaliar()` em Python (~10 linhas) e rodei 9 cenários (3 por regra: dispara / limiar exato / saudável):

| Cenário | Regra | Esquerda | Direita | Esperado | Calculado | OK? |
|---|---|---|---|---|---|---|
| R1 dispara: Q16=3000, Q06=8000 | R1 | 36000 | 8000 | True | True | ✓ |
| R1 limiar: Q16=2000, Q06=12000 | R1 | 24000 | 12000 | False (`>` estrito) | False | ✓ |
| R1 saudável: Q16=2000, Q06=40000 | R1 | 24000 | 40000 | False | False | ✓ |
| R2 dispara: Q14=15000, Q15=5000, Q09=18000 | R2 | 240000 | 216000 | True | True | ✓ |
| R2 limiar: Q14=10000, Q15=5000, Q09=15000 | R2 | 180000 | 180000 | False | False | ✓ |
| R2 saudável: Q14=20000, Q15=8000, Q09=100000 | R2 | 336000 | 1200000 | False | False | ✓ |
| R3 dispara: passivo 200k > ativo 100k | R3 | 200000 | 100000 | True | True | ✓ |
| R3 limiar: ativo == passivo | R3 | 40000 | 40000 | False | False | ✓ |
| R3 saudável | R3 | 120000 | 380000 | False | False | ✓ |

**9 de 9 OK.** As 3 fórmulas da `ValidacoesCruzadas` batem com a leitura literal de §6.6 da spec.

— Validador (claude-opus-4-7), 2026-05-26
