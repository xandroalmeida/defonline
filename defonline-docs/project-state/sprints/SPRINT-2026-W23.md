---
sprint_id: SPRINT-2026-W23
wave: WAVE-2026-01
status: closed
start_date: 2026-05-25
end_date: 2026-05-29
closed_at: 2026-05-24
closed_early: true
goal: "Entregar o EPIC-004 inteiro (App shell + materialização do design system v1 + landing pública placeholder), corrigindo os 11 problemas de UX/UI auditados no EPIC-001 — com o bug funcional do 'Adicionar empresa quando já tem empresa' eliminado e o domínio público mostrando uma landing digna no lugar da página de debug da STORY-007. Validação independente aprovada."
goal_achieved: true
---

# SPRINT-2026-W23

## Objetivo do sprint

Fechar o EPIC-004 ("App shell + materialização do design system v1") inteiro nesta janela. Ao fim do sprint, em homologação:

- **Visitante anônimo** em `https://defonline.xandrix.com.br/` vê a **landing pública nova** (logo "D" + tagline "Diagnóstico estratégico para sua empresa." + CTAs `Criar conta grátis` / `Já tenho conta` + footer com versão) — sem o `hello DEFOnline` de debug.
- **Roberto autenticado** navega entre todas as telas (`/home`, `/empresas/nova`, `/empresas/{id}/show`) dentro de um **chassi visual único** — header com logomarca e dropdown Conta, sidebar fixa no desktop / drawer com hamburger no mobile, breadcrumb nas telas internas, footer institucional com versão discreta — em paleta Stripe-like oficial (`#0A2540 / #635BFF / #F6F9FC`) e tipografia Inter, mobile-first.
- **Bug funcional eliminado:** botão `Adicionar empresa` (primary) visível no header da seção `/home` em **todos** os estados (vazio, 1 empresa, 2+) — Roberto não fica mais preso quando tem 1 empresa cadastrada e quer adicionar outra.
- **Política de Cancelar/Voltar consolidada:** `Cancelar` em `/empresas/nova` ao lado de `Cadastrar empresa`; `Voltar para Minhas Empresas` em `/empresas/{id}/show`; nunca os dois juntos.
- **4 sinais de identificação de tela ativa** funcionando: sidebar destacada + breadcrumb + `<h1>` + `<title>`.
- **Número da versão visível** no footer de todas as rotas autenticadas e da landing (`v0.4.2 · homol` em homologação, `v0.4.2` em produção).
- **Tokens do design system materializados em código** num único arquivo central — alterar uma cor do design system propaga em todas as telas sem busca-e-substitui.
- **`design-system.md` promovido de `alpha` para `v1`** com aprendizado real da primeira aplicação.

Esta sprint é deliberadamente **curta — 1 semana (5 dias úteis)** — seguindo o experimento recomendado na retro da SPRINT-2026-W22 ("considerar 1 semana em vez de 2-3 para time pequeno + MVP cedo"). A velocidade real medida na W22 (8 estórias S/M em ~5 dias úteis efetivos) suporta com folga as 3 estórias do EPIC-004 (1 M-L + 1 S + 1 M).

> **Nota sobre a numeração da sprint:** SPRINT-2026-W23 começa em 2026-05-25 (Monday) — ISO week 22 do calendário. A SPRINT-2026-W22 foi nominalmente atribuída a essa semana mas fechou 19 dias antes da `end_date` planejada (em 2026-05-24), sem ocupar a janela calendarizada. A numeração `W23` aqui é sequencial (sprint nº 23), não estritamente ISO. Convenção a formalizar no `sprint-mechanics.md` se o time decidir adotar sprints de 1 semana como padrão.

## Estórias incluídas

**Núcleo focado em UX/UI (escopo principal — goal):**

| ID | Título | Épico | Tamanho | Status atual | Bloqueada por |
|---|---|---|---|---|---|
| STORY-019 | App shell + materialização do design system v1 + refactor das telas existentes | EPIC-004 | M-L | ready | STORY-016 (`done`) ✓ |
| STORY-024 | Landing pública simples + remoção da página de debug `/` | EPIC-004 | S | ready | STORY-019 (`done`) |
| STORY-020 | Validação final do EPIC-004 | EPIC-004 | M | draft → promove para `ready` quando STORY-019 **e** STORY-024 estiverem `in_review` | STORY-019, STORY-024 |

**Débitos paralelos (não competem com goal — podem entrar se houver capacidade):**

| ID | Título | Épico | Tamanho | Status atual | Justificativa de incluir |
|---|---|---|---|---|---|
| STORY-021 | SPIKE — Decidir 403 vs 404 cross-tenant (alinhar STORY-014 com ADR-003/NRF) | EPIC-001 | S | ready | Retro da W22 recomenda explicitamente paralelizar este spike com STORY-019 — testa se o time pequeno consegue paralelizar trabalho de papéis distintos (Arquiteto + Programador). Não toca UI, não bloqueia EPIC-004. |
| STORY-022 | Alinhar nomenclatura `kind` ↔ `tipo` em `business_metrics` | EPIC-001 | S | ready | Bugfix curto, não toca UI. Pode entrar nas "frestas" entre os passes do EPIC-004. |
| STORY-023 | Fix — `bump-rc.yml` deve disparar `release-homolog.yml` automaticamente | EPIC-001 | S | ready | Bugfix de CI/CD, não toca código de aplicação. Roda fora do caminho crítico. |

**Total estimado núcleo:** 1 M-L + 1 S + 1 M ≈ ~8-10h efetivas + buffer.
**Total estimado núcleo + 3 débitos S:** ~11-14h efetivas. Cabe em 5 dias úteis com base na velocidade da W22 (~1.6 estórias/dia útil).

## Ordem sugerida de execução

```
Dia 1 (Seg 2026-05-25)
  Programador: começar STORY-019
    - Manhã: leitura obrigatória (design/ux-specs.md, fluxo-navegacao.md, mock-shell.html)
    - Tarde: IDR-XXX framework CSS (Tailwind v4 vs CSS vanilla com vars) → aguardar aprovação do PO no chat
  Arquiteto (paralelo): começar STORY-021 (spike 403 vs 404)
    - Spike termina em IDR aceita pelo PO

Dia 2 (Ter 2026-05-26)
  Programador: STORY-019
    - Manhã: tokens.css + componentes-base (button, card, input, label, link, logo)
    - Tarde: layout base + header + sidebar/drawer + breadcrumb + footer

Dia 3 (Qua 2026-05-27)
  Programador: STORY-019
    - Manhã: refactor das telas existentes (/cadastro, /login, /home, /empresas/nova, /empresas/{id}/show)
    - Tarde: bug fix Adicionar empresa + Cancelar em /empresas/nova + Voltar em /empresas/{id}/show + testes
  Programador alternativo (se disponível): STORY-022 (renomear kind↔tipo)

Dia 4 (Qui 2026-05-28)
  Programador: STORY-019 → in_review (PO valida no chat)
    - Manhã: pre-push + pipeline + deploy homologação + smoke manual
  Programador: começar STORY-024 (landing) — depende de STORY-019 done
    - Tarde: remoção dos 5 arquivos de debug + view landing + smoke Dusk substituído
  Programador alternativo: STORY-023 (fix bump-rc.yml) se sobrar capacidade

Dia 5 (Sex 2026-05-29)
  Programador: STORY-024 → in_review
  PO: escreve last touches do validation/checklist.md (se faltar algo) → promove STORY-020 para ready
  Validador: STORY-020 (validação final do EPIC-004)
  PO + Validador: smoke manual em mobile real + desktop
  Fechamento: tag de release final + index.json + retro escrita
```

**Notas:**

- **STORY-019 é caminho crítico.** Sem ela, STORY-024 e STORY-020 ficam bloqueadas.
- **STORY-024 é S (curta).** Cabe em meio dia se STORY-019 entregar shell + componentes prontos. O risco está só na coordenação do smoke Dusk substituído (CI roda o `LandingBrowserTest` no group `smoke` em vez do `HelloWorldBrowserTest`).
- **STORY-020 é validação independente** — o Validador entra com `validation/checklist.md` já completo (PO redigiu antes da sprint abrir). Pode rodar em paralelo com STORY-024 estar em `in_review` se a estrutura do shell estiver estável.
- **Débitos do EPIC-001** são oportunísticos. Se Programador único, foco total no núcleo; entram só se sobrar Sexta. Se houver Arquiteto/Programador secundário, STORY-021 entra em paralelo desde o Dia 1 (recomendação explícita da retro da W22).

## Compromisso visível ao fim do sprint

Em homologação, ao fim de 2026-05-29:

- ✅ `https://defonline.xandrix.com.br/` mostra a **landing pública nova**: logo "D" + wordmark + tagline "Diagnóstico estratégico para sua empresa." + CTAs `Criar conta grátis` e `Já tenho conta` + footer com `© DEFOnline 2026` + `v0.X.Y · homol` — **sem mais `hello DEFOnline` + botão de debug + link para Mailpit**.
- ✅ Login com conta de teste → Roberto cai em `/home` dentro do shell completo: header com logo + nome + dropdown Conta + pill `homol`; sidebar fixa (desktop) ou drawer (mobile) com itens Minhas Empresas (ativo), Adicionar Empresa, Diagnósticos (disabled), Histórico (disabled), Conta (disabled) com tooltips "Em breve — Onda 2".
- ✅ Em `/home` com 1 ou 2+ empresas cadastradas, botão `Adicionar empresa` (primary) **sempre visível** no header da seção — **Roberto não fica mais preso**.
- ✅ Clicar `Adicionar empresa` → `/empresas/nova` dentro do shell, com breadcrumb `Minhas Empresas › Nova empresa` + botão `Cancelar` ao lado de `Cadastrar empresa` (no desktop) ou abaixo (no mobile).
- ✅ Clicar em um card de empresa → `/empresas/{id}/show` dentro do shell, com breadcrumb + botão `Voltar para Minhas Empresas` no final.
- ✅ Em qualquer rota autenticada, **4 sinais redundantes de identificação da tela ativa** funcionam: sidebar destacada + breadcrumb + `<h1>` + `<title>`.
- ✅ Footer de todas as rotas autenticadas e da landing mostra a versão deployada (`v0.X.Y` em produção, `v0.X.Y · homol` em homologação) discretamente no canto direito.
- ✅ Drawer mobile abre e fecha por hamburger, por clique no overlay e por Escape; sidebar visível em viewports ≥ 1024px e oculta em < 1024px.
- ✅ Logomarca "D" SVG inline renderiza em todas as telas (light variant); wordmark aparece no desktop e mobile ≥ 480px.
- ✅ Validação independente do EPIC-004 com veredito documentado em `validation/report.md`.
- ✅ EPIC-004 promovido para `done` no `index.json`.
- ✅ `design-system.md` promovido de `alpha` para `v1` com histórico atualizado.
- ✅ IDR-XXX (framework CSS) aceita.

**Métricas de qualidade técnica (gates herdados):**

- Cobertura ≥ 80% (gate da STORY-010 ativo).
- Zero regressão nos testes Pest/Dusk do EPIC-001.
- Zero hex literal de design system fora de `tokens.css` (teste arquitetural cobra).
- Pipeline `release-homolog.yml` verde com novo smoke Dusk substituindo o `HelloWorldBrowserTest`.

## Capacidade e premissas

- **Time:** Alexandro (PO + revisão) + agentes Claude (Programador para STORY-019/024, Validador para STORY-020, Arquiteto para STORY-021 se entrar).
- **Cadência esperada:** com base no histórico real da W22 (~1.6 estórias/dia útil), a janela de 5 dias comporta 3-8 estórias S/M. Núcleo (3 estórias do EPIC-004) é folgado.
- **Sem feriado nacional na janela.** 2026-05-25 a 2026-05-29 são dias úteis normais.
- **Cobertura ≥ 80% obrigatória** (gate da STORY-010 ativo). Estórias novas devem manter os ~96% atuais (média EPIC-001).
- **Ambiente de homologação `https://defonline.xandrix.com.br`** funcionando — premissa de que a infra do EPIC-000 continua estável.

## Riscos identificados na abertura

| Risco | Probabilidade | Impacto | Mitigação | Owner |
|---|---|---|---|---|
| IDR do framework CSS (Tailwind v4 vs CSS vanilla) demora a fechar e atrasa STORY-019 | média | médio | PO se compromete a responder no chat em **≤ 4h** após Programador propor o IDR. Default em caso de indecisão: Tailwind v4 (alinha com ecossistema Laravel/Livewire). | PO |
| Refactor das telas existentes do EPIC-001 quebra testes Dusk em massa | média | médio | Programador roda Dusk localmente após cada tela refatorada; ajusta selector quando necessário (preservando `dusk="..."` IDs sempre que possível). Critério de aceite (CA-8 da STORY-019) já formaliza "zero regressão funcional, ajustes só de selector". | Programador |
| Mock do designer (`mock-shell.html`) não cobre algum estado/edge case que aparece na implementação | média | baixo | PO disponível em `≤ 2h` no chat para decidir; documenta no `ux-specs.md §13` (ambiguidades) se nova decisão emergir. Decisões pequenas (cor de hover, micro-spacing) ficam a critério do Programador respeitando os tokens. | PO + Programador |
| Drift de paleta esconde mais hex divergente do que o esperado | média | baixo | Teste arquitetural (CA-6 da STORY-019) cobra; busca-e-substitui sistemática contra os tokens. Não é bloqueante, é gradual. | Programador |
| Remoção dos 5 arquivos de debug (STORY-024) quebra `RequestIdPropagationTest` por engano | baixa | médio | STORY-024 CA-1 lista explicitamente o que **fica** intocado (`HelloWorldEmail` job, mail, flag, tests). Pre-push roda a suíte inteira; falha bloqueia. | Programador |
| Smoke Dusk substituído (`LandingBrowserTest` no group `smoke`) não roda no CI por causa de algum nome/wiring | baixa | médio | STORY-024 CA-10 dá o template do teste com group `#[Group('smoke')]` explícito. Verificar no pipeline da rc.1 que ainda há ≥1 teste rodando no group `smoke` contra `/`. | Programador |
| Validação do EPIC-004 (STORY-020) encontra fail bloqueante na Sexta sem buffer | média | médio | PO escreveu `validation/checklist.md` completo **antes** da sprint abrir — Validador entra com critério claro. Estrutura permite Validador rodar em paralelo com STORY-024 estar `in_review` se shell estiver estável (ganha ~meio dia). Em último caso, validação desliza para SPRINT-2026-W24 (sem perder o épico — só atrasa o `done`). | PO + Validador |
| Sprint de 1 semana é experimento — pode não ser suficiente para validação independente caber | baixa | médio | Se a validação não couber, registra como aprendizado da retro e a próxima sprint volta a 2 semanas. Não é regressão de produto, é de processo. | PO |
| Logo SVG "D" não escala bem em algum tamanho usado na aplicação (ex.: favicon 16x16) | baixa | baixo | Designer já testou em 24/32/48px (`design/logo.html`). Favicon não é objetivo desta sprint — fica para depois ou STORY-024 inclui se trivial. | Programador |
| Débitos do EPIC-001 (STORY-021/022/023) se forem incluídos e estourarem buffer, comem capacidade do núcleo | baixa | baixo | Débitos são **opcionais explícitos** — entram só se sobrar capacidade. Se Programador único, foco total no núcleo. STORY-021 (spike) é o único explicitamente recomendado pela retro da W22; STORY-022/023 podem deslizar para a sprint seguinte sem custo. | PO |

## Decisões pendentes que podem afetar o sprint

- **IDR-XXX framework CSS (Tailwind v4 vs CSS vanilla com vars)** — abre dentro da STORY-019 no Dia 1. PO se compromete a responder ≤ 4h. Default: Tailwind v4.
- **Texto definitivo do Termo de Adesão / Política de Privacidade / DPO formal** — continua pendente do jurídico; placeholder cobre. Não bloqueia.
- **Quais débitos do EPIC-001 entram nesta sprint** — decisão do Dia 1 pelo PO baseado em capacidade real disponível. Recomendação: STORY-021 sim (executa retro da W22); STORY-022/023 só se sobrar.

## Mudanças no escopo do sprint

> Toda alteração no conjunto de estórias após esta abertura registra aqui.

| Data | O que mudou | Motivo | Custo |
|---|---|---|---|
| — | — | — | — |

## Fechamento do sprint

**Fechado em 2026-05-24**, 5 dias antes da `end_date` planejada (2026-05-29) e antes mesmo da `start_date` calendarizada (2026-05-25) — a sprint executou em janela comprimida puxada do buffer da W22, que fechara em 2026-05-24. Goal totalmente atingido.

### O que foi entregue

Todas as 3 estórias do núcleo em `done`:

| ID | Título | Tag rc | Notas |
|---|---|---|---|
| STORY-019 | App shell + materialização do design system v1 + refactor das telas existentes | `v0.8.1-rc.1` (+ hotfix `v0.8.2-rc.1` CI Vite build) | IDR-008 aceita (Tailwind v4 + `@theme`). Tokens centralizados em `resources/css/tokens.css`; 10+ Blade components; refactor das 4 telas do EPIC-001 sem regressão funcional; bug `Adicionar empresa` eliminado; políticas Cancelar/Voltar consolidadas; 4 sinais de tela ativa; drawer mobile + dropdown Conta acessíveis. |
| STORY-024 | Landing pública simples + remoção da página de debug `/` | `v0.8.1-rc.1` | Landing `D + DEFOnline + tagline + 2 CTAs + footer com versão`; remoção dos 5 arquivos de debug da STORY-007 (preservando `HelloWorldEmail` job/mail/flag/tests); smoke Dusk `LandingBrowserTest` no group `smoke` substituindo `HelloWorldBrowserTest`. |
| STORY-020 | Validação final do EPIC-004 | (sem nova tag — só docs; deploy efetivo em `v0.8.3-rc.1`) | 1º passe: `approved_with_pending` (F-NB-1 deploy pendente + F-NB-2 comentário Blade residual). 3 hotfixes durante o passe (CI Vite build, Dockerfile stage `vite-build`, Ansible prune pré-pull) liberaram o deploy `v0.8.3-rc.1`. 2º passe: **APPROVED**. F-NB-2 mantido como follow-up trivial. |

**Mudanças de escopo registradas durante o sprint:**

| Data | Mudança | Custo |
|---|---|---|
| 2026-05-24 | +IDR-008 (Framework CSS — Tailwind v4 com `@theme`) — formalizada após PO aprovar opção A no chat dentro da STORY-019 | Zero (decisão coube no Dia 1 conforme risco previsto) |
| 2026-05-24 | +Template DDR (Design Decision Record) commitado junto da STORY-019 (`37a34eb`) | Zero (docs trivial, viu uso natural ao formalizar IDR-008) |
| 2026-05-24 | +3 hotfixes no fechamento (CI Vite build, Dockerfile stage `vite-build`, Ansible prune) — surfaced no 1º passe da validação | Absorvidos sem atraso; geraram os tags `v0.8.2-rc.1` e `v0.8.3-rc.1` |

**Versão em homologação ao fim da sprint:** `v0.8.3-rc.1` (cobre STORY-019 + STORY-024 + STORY-020 + 3 hotfixes; confirmada por screenshot em [evidence/11-homol-landing-desktop-1280x800.png](../epics/EPIC-004-app-shell-navegacao/validation/evidence/11-homol-landing-desktop-1280x800.png)).

### O que ficou para trás (e por quê)

**Nada do núcleo planejado.** As 3 estórias núcleo entregaram.

**Débitos do EPIC-001 listados como opcionais — não puxados:**

- **STORY-021** (SPIKE 403 vs 404 cross-tenant) — recomendada explicitamente pela retro da W22, mas não puxada nesta janela comprimida; permanece `ready` para SPRINT-2026-W24.
- **STORY-022** (renomear `kind` ↔ `tipo` em `business_metrics`) — `ready`, backlog.
- **STORY-023** (fix `bump-rc.yml` não dispara `release-homolog.yml`) — `ready`, backlog. (Nota: deploy do EPIC-004 confirmou empiricamente que o problema persiste — os 3 hotfixes precisaram de `gh workflow run` manual.)

**Pendência não-bloqueante restante:**

- **F-NB-2** (comentário Blade em `landing.blade.php:5` mencionando "STORY-007"/"livewire/hello-world") — invisível ao usuário; trivial de remover. Fica como follow-up oportunista.

### Aprendizados (retro por escrito — time pequeno)

**1. O que funcionou e queremos continuar:**

- **Sprint de 1 semana provou o conceito** — o experimento recomendado na retro da W22 confirmou-se: 3 estórias do núcleo entregues + validação independente aprovada em janela comprimida de ~1 dia útil efetivo de execução (puxando do buffer da W22). A velocidade real continua excedendo a estimativa.
- **IDR aberta e fechada no mesmo dia** — a IDR-008 (Tailwind v4 + `@theme`) executou exatamente o ciclo desenhado no risco: Programador propôs as alternativas, PO respondeu em horas, decisão registrada antes do código depender dela. Padrão a manter.
- **Mock navegável (`mock-shell.html`) + ux-specs.md como contrato visual** — permitiu Programador implementar 6 telas sem ping-pong de design durante a execução; única ambiguidade tratada por decisão local respeitando os tokens.
- **Teste arquitetural "nenhum hex fora de `tokens.css`"** (CA-6 da STORY-019) — gate técnico que blindou o objetivo principal do EPIC-004 (acabar com o drift de paleta). Padrão a propagar para outras categorias de drift futuras.
- **Validador conservador + 2 passes** — `approved_with_pending` no 1º passe forçou os hotfixes de CI/Dockerfile/Ansible serem aplicados antes do épico ser declarado `done`. Sem essa disciplina, o deploy real teria ficado por conta do EPIC-002 absorver — risco real evitado.

**2. O que custou caro e queremos mudar:**

- **3 hotfixes de infra surfacearam no fechamento, não na execução** — CI sem build Vite, Dockerfile sem stage de assets, Ansible sem prune. Os três são sintomas do mesmo gap: o pipeline `release-homolog` não exercita o build completo de assets desde o EPIC-000 (último a tocar `vite.config.js`). Mudança proposta: incluir smoke de "ativos estáticos servem com CSS aplicado" no pipeline (não apenas "/" responde 200).
- **Bug do `bump-rc.yml` continuou nos mordendo** (STORY-023, ainda `ready`) — cada um dos 3 hotfixes precisou de `gh workflow run` manual. Mudança proposta: STORY-023 promovida para topo do backlog da próxima sprint, não fica mais opcional.
- **Numeração `W23` ambígua** (a SPRINT calendarizada para a semana ISO 22 fechou em 2026-05-24; esta sprint foi nominalmente W23 mas executou antes da `start_date`). Convenção a formalizar no `sprint-mechanics.md` antes da próxima abertura: ou se adota número sequencial puro (W23 = 23ª sprint, independente do calendário) ou se adota semana ISO real (e a sprint pode começar imediatamente sem esperar segunda-feira).

**3. Um experimento concreto para o próximo sprint:**

> **SPRINT-2026-W24 abre como sprint de 1 semana padrão** (já o segundo ciclo curto consecutivo, validando o experimento da W22 como a nova norma de capacidade) e **inclui STORY-021 + STORY-023 como núcleo** (não opcionais) + arrancada do EPIC-002 (Diagnóstico Indústria). Justificativa: dois sprints curtos seguidos comprovam que o time pequeno + agentes Claude opera melhor em janelas curtas; e os débitos críticos de CI/CD não devem mais ficar como opcionais.

### Ajustes para o próximo sprint

- **Promover STORY-023 do backlog opcional para núcleo** — o bug do `bump-rc.yml` virou fricção recorrente comprovada (3 ocorrências consecutivas durante o EPIC-004).
- **Smoke de assets estáticos no pipeline** — registrar como item de runbook (`pipeline-asset-smoke.md`) ou virar estória técnica curta na próxima sprint.
- **Formalizar convenção de numeração de sprint** no `defonline-docs/skills/po/sprint-mechanics.md` antes da abertura da W24.
- **Manter validação independente em 2 passes como padrão** quando houver pendências de deploy/infra — provou valor evitando contaminação do épico seguinte.

### Métricas finais da sprint

| Métrica | Valor | Meta |
|---|---|---|
| Estórias núcleo `done` | **3/3** | 3 |
| Débitos opcionais puxados | **0/3** | — (opcionais) |
| Estórias entregues / planejadas (núcleo) | **100%** | — |
| Cobertura geral (após STORY-019) | **96.19%** | ≥ 80% |
| Suíte Pest (fim da sprint) | **316 testes / 979 asserções** (+51 testes vs W22) | — |
| Suíte Dusk (fim da sprint) | **13 testes / 81 asserções** (+1 vs W22) | — |
| Teste arquitetural "hex fora de tokens.css" | **verde** (0 violações) | 0 |
| Pipelines `release-homolog` verdes | **3/3** (`v0.8.1` → `v0.8.3`) | 100% |
| Hotfixes durante o fechamento | **3** (CI Vite, Dockerfile, Ansible) | — |
| Bloqueios reportados | **0** | — |
| Mudanças de escopo no meio | **3** (IDR-008, DDR template, 3 hotfixes infra) | — |
| Veredito da validação do épico | **APPROVED** (após 2º passe pós-deploy) | approved |
| Vereditos da validação (1º → 2º passe) | `approved_with_pending` → `approved` | — |
| Pendências não-bloqueantes restantes | **1** (F-NB-2 comentário Blade) | — |
| Velocidade real (estórias/dia útil) | **~3** (3 estórias num único dia útil efetivo) | — |
| Sprint encerrado | **5 dias antes** da end_date, **antes da start_date** | — |

### Comemoração explícita (cultura)

EPIC-004 fechado em ~1 dia útil efetivo de execução, 11 problemas de UX/UI auditados eliminados, drift de paleta erradicado com gate arquitetural, app shell único reutilizado por todas as rotas, landing pública digna substituindo o `hello DEFOnline` de debug, design-system promovido de `alpha` para `v1` com aprendizado real da primeira aplicação, IDR-008 formalizando Tailwind v4 + `@theme` como padrão transversal, e 3 hotfixes de infra surfaceados e resolvidos antes do próximo épico herdar o problema. 🚀 A WAVE-2026-01 está agora com **3 de 5 épicos `done` em ~6 dias úteis de execução real** (EPIC-000 + EPIC-001 + EPIC-004), restando EPIC-002 (Diagnóstico Indústria) e EPIC-003 (Histórico) — agora desbloqueados pelo shell e podendo arrancar com fundação técnica + funcional + visual completas.

> "Sprint bem conduzido entrega mais que estórias — entrega ritmo, previsibilidade e aprendizado contínuo." (`sprint-mechanics.md`)
