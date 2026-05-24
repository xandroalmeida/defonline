---
sprint_id: SPRINT-2026-W23
wave: WAVE-2026-01
status: open
start_date: 2026-05-25
end_date: 2026-05-29
goal: "Entregar o EPIC-004 inteiro (App shell + materialização do design system v1 + landing pública placeholder), corrigindo os 11 problemas de UX/UI auditados no EPIC-001 — com o bug funcional do 'Adicionar empresa quando já tem empresa' eliminado e o domínio público mostrando uma landing digna no lugar da página de debug da STORY-007. Validação independente aprovada."
goal_achieved: null
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

> A ser preenchido ao fim da sprint pelo PO. Padrão da W22:
> - O que foi entregue (tabela ID × título × tag rc × notas)
> - Mudanças de escopo registradas durante o sprint
> - Versão em homologação ao fim da sprint
> - O que ficou para trás (e por quê)
> - Aprendizados (retro por escrito: o que funcionou / o que custou caro / um experimento para a próxima)
> - Ajustes para o próximo sprint
> - Métricas finais (estórias done, cobertura, pipelines verdes, velocidade real, etc.)
> - Comemoração explícita (cultura)
