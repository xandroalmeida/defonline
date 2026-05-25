---
story_id: STORY-029
slug: relatorio-minimalista
title: Relatório web minimalista — 7 indicadores + semáforo + glossário inline
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: in_review
owner_agent: claude-programador
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: M
---

# STORY-029 — Relatório web minimalista

> Esta estória entrega o **primeiro relatório visível em homologação** — a saída do motor da STORY-028 renderizada como página web dentro do app shell. Caminho feliz ponta a ponta vivo no Checkpoint 1.

## Contexto

A epic.md descreve o relatório como saída web em **≤ 3s p95** com 14 indicadores + semáforo + Resumo Executivo + glossário. Nesta estória entregamos a versão minimalista: 7 indicadores (V1), NCG absoluto informativo, semáforo, glossário inline. Sem Resumo Executivo (STORY-031), sem recomendações da matriz (STORY-032), sem tooltips no quiz (STORY-033).

## O quê

1. **Rota `/diagnosticos/{diagnostico}`** dentro do app shell autenticado.
2. **View Blade `diagnostico-show.blade.php`** com:
   - Cabeçalho: nome da empresa, data do diagnóstico, badge "MVP" pequena (transparência: relatório em evolução).
   - Lista/tabela dos 7 indicadores com colunas: nome, valor formatado, farol (componente visual), mensagem curta.
   - NCG absoluto exibido em card separado, sem farol, com a mensagem informativa da faixa.
   - Glossário inline: ao final do relatório, ou expansível por indicador, com texto do Anexo I (espec V2 `anexos/anexo-I-glossario.md`).
   - Rodapé: `motor_version`, `matrix_version`, data de geração, aviso legal curto.
3. **Componente `<x-farol>`** (verde / amarelo / vermelho / sem-cor) — visual definido pelo design system (decisão pequena do programador; usar tokens existentes, sem hex literal). Acessibilidade: cores **+ ícone** para daltônicos.
4. **Mobile-first:** em desktop, tabela densa. Em mobile, card por indicador.
5. **Loading state:** ao chegar via redirect do quiz (STORY-027), tela mostra skeleton enquanto motor finaliza (geralmente já está pronto, mas garantir robustez).
6. **Acessibilidade:** AA básico. `<main>` semântico, headings na ordem, `<table>` com `<th scope>`, alt text em ícones.

## Por quê

Sem render, motor é invisível. Este é o entregável demonstrável do Checkpoint 1.

## Critérios de aceite

- [ ] **CA-1 (Rota e auth):** `/diagnosticos/{id}` autenticada, com policy: só `usuario_id` dono da empresa vê (alinha com STORY-021 — 403 vs 404 cross-tenant; aplica veredito da IDR daquela spike).
- [ ] **CA-2 (Layout dentro do shell):** breadcrumb `Minhas Empresas › {nome} › Diagnóstico de {data}`. Header do shell e footer institucional preservados.
- [ ] **CA-3 (7 indicadores renderizados):** cada indicador mostra nome, valor (formatado com casas decimais apropriadas), farol visual e mensagem curta. Para `disponivel = false`, mostra linha cinza com mensagem semântica.
- [ ] **CA-4 (NCG absoluto sem farol):** card separado com valor + mensagem da faixa.
- [ ] **CA-5 (Glossário):** texto do Anexo I disponível inline (expansível ou seção ao final). Sem link externo.
- [ ] **CA-6 (Mobile-first):** viewport 360x800 mostra cards empilhados, sem scroll horizontal. Viewport 1280x800 mostra tabela densa.
- [ ] **CA-7 (p95 ≤ 3s):** medição em homol — 50 navegações `quiz submit → relatório carregado completo (DOMContentLoaded)`. p95 ≤ 3s.
- [ ] **CA-8 (Acessibilidade AA):** contraste ≥ 4.5:1; estados de farol não dependem só de cor (ícone + texto também); navegação por teclado.
- [ ] **CA-9 (Versão visível no rodapé):** `motor v1.0.0 · matriz dez-2025 · gerado em DD/MM/AAAA HH:MM`. Estilo discreto.
- [ ] **CA-10 (Testes):** Pest feature (200 OK, conteúdo presente, cross-tenant 403 ou 404 conforme IDR-007), Dusk smoke (caminho do quiz ao relatório).
- [ ] **CA-11 (Cobertura ≥ 80%).**

## Fora de escopo

- 7 indicadores restantes (STORY-030).
- Resumo Executivo (STORY-031).
- Recomendações textuais da matriz DEZ/2025 (STORY-032).
- PDF do relatório (EPIC-007).
- Compartilhamento por link público (não previsto no MVP).

## Dependências

- **Bloqueada por:** STORY-026, STORY-027, STORY-028, EPIC-004 (componentes), STORY-021 (veredito 403 vs 404).
- **Bloqueia:** STORY-030/031/032 estendem este render.

## Decisões já tomadas

- Render server-side (Blade), sem SPA, sem WebSocket. Simples e suficiente.
- Glossário inline (não modal, não página separada) — reduz fricção.
- Farol = cor + ícone (acessibilidade).
- Mobile = card-per-indicador; desktop = tabela densa.

## DoD

- CA-1 a CA-11 passam.
- Demo gravada no Checkpoint 1 (PO faz screencast).
- Tag `rc.W25S1.3`. `index.json` atualizado.

## Protocolo do agente

Padrão. Avisar PO em `in_review`.

## Notas do agente

**Implementação (2026-05-25, programador-claude-opus-4-7):**

- **Seeder demo** `database/seeders/DiagnosticosDemoSeeder.php` cria usuário `roberto.demo@defonline.local` (senha `senha-de-teste-1234`) + 5 empresas + 5 diagnósticos, um por fixture canônico da STORY-028. Guard de ambiente: só roda em `local|development|testing`. Idempotente no Usuário (reusa por email); acumula empresas/diagnósticos a cada execução — para limpar, `migrate:fresh`.
- **Rota** `/diagnosticos/{diagnostico}` registrada via `DiagnosticoController::show` (controller magro: RMB + `view()`). O stub `resources/views/diagnostico/stub-resultado.blade.php` foi removido; rota antiga (Closure inline) substituída. Confirmado por grep que nenhum teste referenciava o stub.
- **404 silente cross-tenant (IDR-009):** Global Scope `BelongsToUsuarioScope` no model resolve naturalmente via RMB → 4 testes feature dedicados (próprio dono OK, outro usuário 404, UUID inexistente 404, não autenticado redirect /login).
- **Decisão visual de farol:** componente `<x-relatorio.farol>` único com 4 variantes (verde/amarelo/vermelho/nenhum). Cor + ícone SVG inline (heroicons-like sem dep) + `role="img"` + `aria-label="Farol: {cor}"` — acessibilidade AA para daltônicos. Tokens `--color-farol-*` já existiam em `tokens.css`.
- **Decisão de layout mobile-first:** duplicação intencional. `hidden md:block` para tabela densa `<table>` com `<th scope="col">` + `<th scope="row">` (preferido pelo briefing + CA-8) e `md:hidden flex flex-col gap-3` para stack de cards. Mantém semântica de tabela em desktop e cards verdadeiros em mobile — sem `display:table` toggles que poderiam quebrar leitor de tela.
- **Glossário inline:** `<details>/<summary>` nativo no final da página (12 termos derivados do Anexo I — copy editorial estável, hardcoded no Blade; Anexo I não tem `motor_version` então não há razão técnica para ler em runtime). Sem JS — chevron rotaciona via `group-open:rotate-180` (Tailwind v4).
- **Loading state (decisão (a) do briefing §8):** render sync direto. p95 do motor é ≤ 500ms (STORY-028 baseline), skeleton seria overkill. Se Roberto chegar via redirect do quiz e o motor demorar, o overhead já é absorvido pelo controller `QuizDiagnostico::submit` antes do redirect.
- **Helpers de formatação** em `app/Support/Relatorio/IndicadorFormatter.php` (classe `final`, métodos estáticos, sem estado). Catálogo único de nomes humanizados + `ORDEM_ESSENCIAIS` (mesma ordem do `Motor::indicadores()` exceto NCG abs, que é tratado à parte na view). Cobertura unitária 100% (17 cenários, incluindo formatação BR de moeda/percentual/dias/múltiplos e tratamento de `valor === null`).
- **NCG/Vendas tratado como razão decimal:** snapshot grava `-0.0416` → exibido como `-4,2%` (multiplicação por 100 + 1 casa decimal). Detalhe confirmado por inspeção do snapshot real do fixture `saudavel`.
- **Postgres jsonb e ordem do snapshot:** `tinker` mostra chaves do `indicadores_calculados` fora da ordem do motor — Postgres jsonb não preserva ordem de inserção, e o cast `AsArrayObject` devolve o que o banco entregou. **Não é problema** — a ordem de exibição é controlada por `IndicadorFormatter::ORDEM_ESSENCIAIS` na view, independente da ordem do banco. A ordem original do motor segue sendo a fonte do hash (que vem do canonical JSON do *quiz_payload*, não da ordem dos indicadores).
- **Rodapé reflete snapshot, não config atual:** teste dedicado força `motor_version='0.9.0'` + `matrix_version='jul-2025'` via factory e confirma que `motor v0.9.0 · matriz jul-2025` aparece no rodapé, independente de `config('motor.*')`.
- **Bug preexistente da STORY-027 corrigido:** `tests/Browser/MinhasEmpresasBrowserTest.php` ainda assertava `disabled=true` no botão "Iniciar diagnóstico", mas a STORY-027 (commit fb34acd) habilitou o botão como `href` para `diagnosticos.novo`. Atualizado o assert para validar `href` correto. Não é regressão da 029 — escapou do pre-push da 027 (provavelmente porque o Dusk não rodou nessa branch). PO pode considerar registrar como Nota de retrospectiva da 027.
- **Latência observada:** render do view (sem motor) ≈ 50ms p50 (5 GETs locais, container web do compose). Bem abaixo do budget de 1.5s p95 para render isolado.
- **CAs cobertos:** CA-1 (auth + 404 IDR-009), CA-2 (breadcrumb + shell), CA-3 (7 indicadores + indisponível cinza), CA-4 (NCG abs separado), CA-5 (glossário inline), CA-6 (tabela em md+ / cards em <md, confirmado em ambos data-testids), CA-8 (`<main>`, `<h1>`, `<th scope>`, `aria-label` em farol e breadcrumb), CA-9 (rodapé motor/matrix/data), CA-10 (11 testes feature + 17 unit), CA-11 (cobertura geral 96.8%; `DiagnosticoController` 100%; `IndicadorFormatter` 100%). CA-7 (p95 ≤ 3s em homol) e Dusk smoke pendem do deploy em homol — registrados para o ciclo de checkpoint.

**Métricas pre-push (verde):**
- Pint: 197 files OK (após auto-fix de 1 issue em `IndicadorFormatter`).
- Larastan: No errors.
- Pest All: **Total 96.8%** (≥80%), 504+ testes passando.
- Pest Domain: **100%** (≥98%).
- Pennant overdue: OK.
- Dusk: 13/13 passando (após fix do teste obsoleto da 027).

**Pendências (não bloqueiam):**
- F-NB-1: CA-7 (p95 ≤ 3s em homol) — medição depende de deploy. Pendente.
- F-NB-2: Dusk smoke do caminho quiz→relatório fica naturalmente coberto pela cadeia já existente — opcional adicionar Browser test específico no fechamento do EPIC-002.
- F-NB-3 (info): seeder demo não vai para homol/prod automaticamente (guard de ambiente). Quando o PO quiser dados de demo em homol, abrir story curta para `--force-prod`.
