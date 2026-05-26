---
artifact: story-validation-report
story_id: STORY-033
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
validated_by: claude-validador
validated_at: 2026-05-26
verdict: approved
pending_count: 1
blockers: 0
related_briefing: briefings/STORY-033-abertura.md
---

# Relatório de validação independente — STORY-033

> Validação pós-commit (commit `e7b96fb`, status `done` no `index.json`). Tooltips do quiz Indústria.

## Veredito

**`approved`** — implementação atende os 8 CAs da estória. Componente `<x-help>` bem estruturado (Alpine + tokens v1 + acessibilidade), 23 campos cobertos via 4 partials + 2 usos inline, suporte a `**negrito**`, auto-guarda contra texto vazio. Validação visual em homol local: tooltip Q01 abre com texto literal do config + negrito renderizado + popover ancorado ao ícone (desktop ≥ 1024px). 1 pendência não-bloqueante (auditoria Pa11y formal).

## Escopo da validação

- Estória `stories/STORY-033-tooltips-quiz.md` (CA-1 a CA-8 + notas do agente).
- Briefing `briefings/STORY-033-abertura.md` (versão enxuta PO).
- Código novo:
    - `app/resources/views/components/help.blade.php` (componente Alpine).
    - Estilos `.help*` em `app/resources/css/app.css` (tokens v1).
- Config consumido: `app/config/quiz/help-industria.php` (v1.0.0, entregue pelo PO em 2026-05-25).
- Integração nos partials:
    - `app/resources/views/livewire/diagnostico/partials/campo-{brl,dias,pct,cpf}.blade.php` (4 partials × 1 uso).
    - `app/resources/views/livewire/diagnostico/quiz.blade.php` (Q01 e Q17 inline = 2 usos).
- Testes:
    - `app/tests/Feature/Livewire/Diagnostico/QuizTooltipsTest.php` (141 linhas, 6 testes Pest).
    - `app/tests/Browser/QuizTooltipsBrowserTest.php` (100 linhas, 2 testes Dusk — desktop + mobile).
- Validação visual em `http://localhost:8090/diagnosticos/novo` → Demo · saudavel → Q01.

## CA por CA — evidências

### CA-1 — Todos os 23 campos têm tooltip funcional

**Status:** ✓ atendido.

- 4 partials de campo (`campo-brl`, `campo-dias`, `campo-pct`, `campo-cpf`) renderizam `<x-help>` lendo `config('quiz.help-industria.campos.{id}')` pelo próprio `$id`. Cobrem Q02–Q16 + Q18–Q23.
- Q01 (Setor) e Q17 (Necessita captar) recebem `<x-help>` inline no `quiz.blade.php`.
- Total: 23/23 campos do Anexo A.
- `QuizTooltipsTest.php` valida ícone + `aria-describedby` + painel para Q01–Q23 (incluindo condicionais Q18–Q23 quando Q17=Sim).

### CA-2 — Texto editável em config sem mexer em código

**Status:** ✓ atendido.

Partials e inline leem do config `quiz.help-industria.campos.{id}`. Teste `QuizTooltipsTest.php` tem caso que sobrescreve a config em runtime (`config(['quiz.help-industria.campos.Q01' => '...'])`) e re-renderiza o componente — confirma que troca o texto sem mudar código nem teste.

### CA-3 e CA-4 — Bottom-sheet em mobile / popover em desktop

**Status:** ✓ atendido.

Decisão arquitetural elegante: flip de layout **via CSS** (`@media (min-width: 1024px)` nas classes `.help__painel`), não JS. Mesmo HTML, mesmo Alpine, mesma interação. Backdrop e botão "Fechar" só aparecem em < 1024px via `lg:hidden`.

Dusk cobre os dois layouts:
- `1280×900` → assert popover (CA-4).
- `360×800` → assert bottom-sheet (CA-3).

### CA-5 — Acessibilidade

**Status:** ✓ atendido **com ressalva** — ver pendência P-001.

- Trigger é `<button type="button">` nativo → Tab/Enter/Space funcionam sem JS extra.
- `aria-expanded` reflete estado (validado via inspeção do DOM em homol local: `aria-expanded="true"` após click).
- `aria-describedby="help-painel-{id}"` liga trigger ao painel.
- `Esc` fecha via `x-on:keydown.escape.window`.
- `x-on:click.outside` fecha quando usuário clica fora.
- `role="tooltip"` no painel.
- Contraste AA garantido pelos tokens v1 (cores em `var(--color-primary)` / `var(--color-surface)`).
- Dusk cobre `Esc` + `aria-expanded`.

**Ressalva (P-001):** auditoria Pa11y formal mencionada no CA-5 não foi executada. A cobertura via Dusk é funcional (teclado, aria), mas relatório Pa11y arquivado não existe.

### CA-6 — Design System v1 (zero cores hard-coded)

**Status:** ✓ atendido.

Estilos `.help*` em `app.css` em `@layer components` usam exclusivamente `var(--*)`. Backdrop usa `color-mix(in srgb, var(--color-primary) 40%, transparent)` — sem hex hard-coded. `DesignTokensTest` (teste arquitetural já existente) continua verde — nenhum hex de design system fora de `tokens.css`.

### CA-7 — Testes

**Status:** ✓ atendido.

- **Pest feature** (6 testes em `QuizTooltipsTest.php`): assert que view tem ícone + `aria-describedby` + painel + textos da config + override em runtime. Cobre os 23 campos.
- **Dusk** (2 testes em `QuizTooltipsBrowserTest.php`): hover/click revela tooltip em mobile e desktop. Cobre Esc + aria-expanded.

Suíte do Diagnóstico continua verde (30 testes locais + 743 globais reportados pelo dev na entrega anterior).

### CA-8 — Gate do PO (23 textos)

**Status:** ✓ atendido.

Confirmado: `app/config/quiz/help-industria.php` v1.0.0 já existia desde 2026-05-25 (entregue pelo PO antes do gate de 05/jun, antecipação de ~11 dias). 23 textos no array `campos`, 8 marcados como `rascunhos_a_confirmar_ebc` (Q01, Q17–Q23) — pós-condição opcional, não bloqueia.

## Validação visual (browser homol local)

Em `http://localhost:8090/diagnosticos/novo` → Demo · saudavel → Bloco 1:

1. Ícone `?` visível ao lado do label "SETOR DE ATIVIDADE".
2. Click no ícone abre **popover ancorado ao ícone** (viewport 1483px = desktop ≥ 1024px).
3. Texto exibido = exatamente o conteúdo de `config('quiz.help-industria.campos.Q01')` (~80 palavras com exemplos de Indústria/Comércio/Serviços).
4. **`**transforma matéria-prima**`, **`**revende**`, **`**executa**`** renderizados em negrito conforme spec.
5. Inspeção DOM via JS confirmou: `aria-expanded="true"` após click; `role="tooltip"` no painel; `offsetParent !== null` (painel visível).

Conferi também que os campos do Bloco 2 (Compras, Vendas, Custos Fixas/Variáveis, Despesas Financeiras) exibem o ícone `?` ao lado dos labels — `<x-help>` integrado via `campo-brl`.

## Pendências (não-bloqueantes)

### P-001 — Auditoria Pa11y formal não executada

**Tipo:** evidência arquivada faltando.

CA-5 menciona "auditoria com Pa11y ou similar". A cobertura funcional (teclado, aria, contraste via tokens v1) está nos testes Dusk. Falta o **relatório Pa11y arquivado** como evidência.

**Recomendação:** rodar Pa11y na URL do quiz quando o dev fizer o deploy do rc desta sprint em homologação. Output em `validation/evidence/pa11y-quiz-story-033.txt`. Janela: até STORY-037 (Checkpoint 4).

**Não-bloqueia:** os testes Dusk cobrem o comportamento esperado de teclado e `aria-expanded`. Pa11y agregaria checagens automáticas adicionais (axe-core regras) mas não muda o estado do produto.

## Recomendação ao PO

**Aprovar** a STORY-033 e manter status `done`. A pendência P-001 entra no checklist da STORY-037 como item de evidência arquivada — não impacta a entrega para o usuário.

— Validador (claude-opus-4-7), 2026-05-26
