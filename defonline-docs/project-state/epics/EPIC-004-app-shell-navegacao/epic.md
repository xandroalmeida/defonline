---
epic_id: EPIC-004
slug: app-shell-navegacao
title: App shell + materialização do design system v1 (chassi visual e navegação)
wave: WAVE-2026-01
status: ready
owner_role: po
created_at: 2026-05-24
updated_at: 2026-05-24
target_completion: 2026-06-26
---

# EPIC-004 — App shell + materialização do design system v1

## Por que existimos (problema do usuário)

Ao fim do EPIC-001, Roberto entra no DEFOnline, cadastra a marcenaria e vê "Minhas Empresas" — mas a aplicação ainda é um conjunto de telas isoladas, sem chassi visual unificado, sem menu de navegação, sem identidade clara de produto. Cada story do EPIC-001 entregou a sua tela com estilo ad-hoc; a STORY-016 chega a redefinir paleta (`#1f2937/#2563eb/#f9fafb`) que **diverge** do design system oficial (`#0A2540/#635BFF/#F6F9FC` em `especificacao/V2/design-system.md`). É drift visual confirmado, e ele só vai piorar quando o EPIC-002 (quiz + relatório com 14 indicadores + semáforo) e o EPIC-003 (lista + comparativo) chegarem com muito mais superfície.

A persona alvo é Roberto digitando no celular: ele precisa de um produto que **pareça produto** — header com a marca, lugar visível para sair, navegação clara entre "Minhas Empresas / Diagnósticos / Histórico / Conta", e a sensação de "estou em uma plataforma, não em uma sequência de formulários". Sem chassi, a percepção de qualidade do beta fechado vai pesar negativamente em entrevistas com Roberto, mesmo que a função funcione.

Este épico **não entrega funcionalidade nova ao usuário final** — ele entrega o palco onde o EPIC-002 e o EPIC-003 vão atuar. É fundação de UX da mesma forma que o EPIC-000 foi fundação técnica.

## Resultado esperado (outcome)

Ao fim do EPIC-004, toda rota autenticada do DEFOnline (`/home`, `/empresas/nova`, `/conta` futura, telas do EPIC-002/003) é renderizada dentro de um layout Blade base único com:

- **Header global** com logo "DEFOnline", primeiro nome do usuário logado, dropdown de "Conta" com ação "Sair", indicador discreto de ambiente (`homol` quando aplicável).
- **Navegação principal** responsiva: sidebar fixa no desktop (≥ 1024px) e drawer/hamburguer no mobile (< 1024px), com itens **Minhas Empresas** (ativo), **Diagnósticos** (desabilitado — EPIC-002 ativa), **Histórico** (desabilitado — EPIC-003 ativa), **Conta** (placeholder para edição/senha — onda 2).
- **Breadcrumb** reutilizável renderizando em telas internas (rota atual + 1 nível pai).
- **Footer** mínimo institucional (versão deployada — já existe no EPIC-000 + link para Termo + link para Política de Privacidade).
- **Tokens do design system materializados em código** — paleta `#0A2540 / #425466 / #635BFF / #F6F9FC / #FFFFFF`, tipografia Inter 300/400/500/600, espaçamentos, raios e sombras, todos expostos como CSS vars (ou equivalente, conforme IDR a registrar) num único arquivo central.
- **Componentes-base do design system implementados** — `button-primary`, `button-secondary`, `button-ghost`, `card`, `input`, `label`, `link`, conforme `especificacao/V2/design-system.md` §Componentes-base.

As três telas existentes do EPIC-001 (`/cadastro`, `/login`, `/home`) são **refatoradas** para consumirem o novo layout, corrigindo o drift de paleta da STORY-016 e da STORY-011 sem regressão funcional.

## Métrica de sucesso (como saberemos que funcionou)

- **Métrica primária (qualidade percebida — alimenta D2 Ativação indiretamente):** em entrevista qualitativa com os primeiros Robertos do beta, 4 de 5 descrevem o produto como "parece profissional" / "parece um sistema sério" sem ser provocado. Janela: D+14 após deploy em homologação.
- **Métrica de cobertura visual:** 100% das rotas autenticadas existentes consomem o novo layout. Zero telas com paleta divergente do design system oficial após esta sprint.
- **Métrica de consistência:** uma mudança de cor primária no design system requer alteração em **um único arquivo de tokens** e propaga em todas as telas — auditado por inspeção manual em pelo menos 3 telas após mudar `--color-primary` localmente.
- **Métrica de qualidade técnica:** cobertura ≥ 80% (gate da STORY-010 ativo); zero regressões nos testes Dusk existentes do EPIC-001; novo teste Dusk validando navegação por menu em mobile e desktop.

## Entregável visível no fim do épico

- [ ] Em homologação, Roberto loga e vê o produto com header, menu lateral (desktop) ou drawer (mobile), breadcrumb e footer aplicados a todas as telas autenticadas.
- [ ] Navegação entre "Minhas Empresas" e "Adicionar Empresa" (`/empresas/nova`) feita pelo menu, não por URL direta.
- [ ] Botões "Diagnósticos" e "Histórico" no menu ficam visíveis mas desabilitados, com tooltip "Em breve — onda 2" (ou similar), preparando o usuário para a chegada do EPIC-002/003.
- [ ] Telas existentes (`/cadastro`, `/login`, `/home`, `/empresas/nova`) usam paleta Stripe-like oficial (`#0A2540 / #635BFF / #F6F9FC`) e tipografia Inter — corrige drift da STORY-016 e da STORY-011.
- [ ] Footer mostra versão deployada (já existe) + links para Termo e Política de Privacidade.
- [ ] `design-system.md` promovido de `alpha` para `v1` com base no aprendizado real da primeira aplicação (gatilho previsto na linha 208 do documento).
- [ ] IDR registrando escolha de framework CSS (Tailwind v4 nativo do Laravel/Livewire, CSS vanilla com vars, ou outra alternativa) com prós/contras documentados.

## Fora de escopo (explicitamente)

- **Página `/conta`** com edição de Usuário (nome, senha, telefone) — entra na onda 2 (recuperação de senha).
- **Tela específica de "Adicionar empresa"** no menu — epic do EPIC-001 declara que botão "Adicionar empresa" não aparece nesta onda (Pro feature). O item de menu pode existir mas leva à mesma `/empresas/nova` da STORY-014.
- **Tutorial opcional de 3 slides** após login (espec §3.3) — pode virar story futura; fora deste épico.
- **Dark mode** — não previsto no design system alpha; não entra.
- **Acessibilidade WCAG AAA** — alvo neste épico é AA básico (semântica HTML, labels, foco visível, contraste de cores do design system). Auditoria completa fica para uma story dedicada após v1 estabilizar.
- **Internacionalização (i18n)** — produto é PT-BR no MVP (roadmap §4.3 v2.0).
- **Hotsite público** — entra no EPIC-008 (renumerado, ex-007) da onda 2. Este épico cobre apenas a área logada.
- **Telas do EPIC-002 (quiz, relatório, semáforo)** — só preparamos o palco; quem entra com conteúdo é o EPIC-002.
- **Design system v2** ou redesign profundo — fora; o alvo é materializar a `alpha` em v1 com ajustes pontuais nascidos da aplicação real.

## Referências da especificação

- `defonline-docs/especificacao/V2/design-system.md` (paleta, tipografia, espaçamento, raios, sombras, componentes-base, do's and don'ts).
- `defonline-docs/especificacao/V2/especificacao-funcional.md` §3.3 (painel principal após cadastros — base mental do shell), §6.x (decisões abertas sobre UX que podem informar este épico).
- `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §1 (uptime, latência), §9.x (acessibilidade básica esperada).
- ADR-001 (stack: Laravel 13 + Livewire 4) — decisão de framework CSS deve respeitar essa stack.
- ADR-002 (topologia: monolito modular) — implica que componentes Blade vivem no mesmo deploy.

## Dependências

- **Bloqueia:** EPIC-002 (telas do quiz e do relatório precisam do chassi para não nascerem com mais drift); EPIC-003 (lista cronológica + comparativo precisam de breadcrumb e header consistentes).
- **Bloqueado por:** EPIC-001 (precisa das telas existentes para refatorá-las dentro do novo layout). Pode começar **assim que STORY-016 (`/home` = Minhas Empresas) fechar `done`**; não precisa esperar STORY-017 (validação) nem STORY-018 (provedores RFB reais — não toca UI).
- **Decisões arquiteturais necessárias:** IDR de framework CSS (Tailwind 4 vs CSS vanilla com vars vs outro). Sem ADR nova — design system já está especificado e ADRs existentes não restringem a escolha.

## Estórias

Decompostas em 1 implementação principal + 1 validação. Princípio: **shell + design system materializado + refactor das telas existentes** em uma estória vertical única (STORY-019). Quebrar em duas (shell antes, refactor depois) introduziria estado intermediário com mistura de paletas — vale a pena pagar o custo de uma story `M-L` para entregar o chassi pronto.

- [ ] **STORY-019** (implementation, programador, `ready`, M-L) — App shell + materialização do design system v1 + refactor das telas existentes do EPIC-001 para consumirem o novo layout. Inclui IDR de framework CSS. Não bloqueada por nenhuma outra deste épico; bloqueada apenas pelo fechamento da STORY-016 (`done`) — começa quando o EPIC-001 tiver `/home` redefinida.
- [ ] **STORY-020** (validation, validador, `draft`, M) — Validação final do EPIC-004. Promovida para `ready` quando STORY-019 estiver `in_review`. Checklist em `validation/checklist.md` (a criar pelo PO antes da promoção). Mesmo padrão da STORY-008/STORY-017.

**Paralelismo:** STORY-019 é vertical; não paraleliza dentro do épico. **Paraleliza fora do épico**: pode rodar simultaneamente com STORY-018 (provedores RFB reais — não toca UI) se a capacidade do time permitir. Recomendação do PO: **STORY-019 entra na próxima sprint** (após SPRINT-2026-W22 fechar), antes de a primeira story do EPIC-002 começar. Não entra na SPRINT-2026-W22 ativa.

**Decisões abertas que NÃO bloqueiam abertura do épico:**

- Framework CSS (Tailwind v4 nativo Laravel/Livewire vs CSS vanilla com vars vs outro) — fica para IDR no início da STORY-019. Liberdade técnica do agente programador, com requisito de documentar trade-offs.
- Posicionamento exato dos itens "Diagnósticos" e "Histórico" no menu — Programador propõe na implementação; PO valida em homologação.
- Necessidade de skeleton screens / loading states para o shell — sai como observação da validação; se não emergir como dor, fica para depois.

## Validação final

Critérios em `validation/checklist.md` (a criar antes da STORY-020 promover para `ready`). Relatório do validador em `validation/report.md`.

**Definição de épico concluído:** STORY-019 e STORY-020 `done` + relatório do validador `approved` + Roberto em homologação navegando por menu entre `/home` e `/empresas/nova` em mobile (drawer) e desktop (sidebar fixa) + paleta Stripe-like oficial aplicada em 100% das rotas autenticadas + `design-system.md` promovido para v1 + IDR de framework CSS aceita.

## Histórico

- 2026-05-24 — Criado como `ready` pelo PO após análise de gap no backlog: nenhum item priorizado previa app shell, menu de navegação, breadcrumb ou materialização do design system. Drift de paleta já confirmado entre `design-system.md` (oficial: `#0A2540/#635BFF/#F6F9FC`) e STORY-016/011 (`#1f2937/#2563eb/#f9fafb`). Risco de retrabalho ao chegar no EPIC-002 (quiz + relatório com 14 indicadores e semáforo) sem chassi pronto motivou a criação **antes** do início efetivo do EPIC-002. STORY-019 fica `ready` na **próxima sprint** (após SPRINT-2026-W22 — não entra na sprint ativa do EPIC-001). EPIC-004 inserido na WAVE-2026-01; epic placeholder homônimo "Cobrança e Plano Básico" da WAVE-2026-02 renumerado para EPIC-005, com cascata na ordem dos placeholders subsequentes.
