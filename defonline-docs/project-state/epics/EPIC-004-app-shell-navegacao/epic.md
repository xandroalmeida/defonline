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
companion_design_artifacts:
  - design/mock-shell.html      # protótipo navegável (6 telas)
  - design/logo.svg             # logomarca "D" + acento tertiary
  - design/logo.html            # logo em 3 contextos × 3 tamanhos
  - design/ux-specs.md          # spec UX consolidada (inventário + políticas)
---

# EPIC-004 — App shell + materialização do design system v1

## Por que existimos (problema do usuário)

Ao fim do EPIC-001, Roberto entra no DEFOnline, cadastra a marcenaria e vê "Minhas Empresas" — mas a aplicação ainda é um conjunto de telas isoladas, sem chassi visual unificado, sem menu de navegação, sem identidade clara de produto. Cada story do EPIC-001 entregou a sua tela com estilo ad-hoc; a STORY-016 chega a redefinir paleta (`#1f2937/#2563eb/#f9fafb`) que **diverge** do design system oficial (`#0A2540/#635BFF/#F6F9FC` em `especificacao/V2/design-system.md`). É drift visual confirmado, e ele só vai piorar quando o EPIC-002 (quiz + relatório com 14 indicadores + semáforo) e o EPIC-003 (lista + comparativo) chegarem com muito mais superfície.

A persona alvo é Roberto digitando no celular: ele precisa de um produto que **pareça produto** — header com a marca, lugar visível para sair, navegação clara entre "Minhas Empresas / Diagnósticos / Histórico / Conta", e a sensação de "estou em uma plataforma, não em uma sequência de formulários". Sem chassi, a percepção de qualidade do beta fechado vai pesar negativamente em entrevistas com Roberto, mesmo que a função funcione.

Este épico **não entrega funcionalidade nova ao usuário final** — ele entrega o palco onde o EPIC-002 e o EPIC-003 vão atuar. É fundação de UX da mesma forma que o EPIC-000 foi fundação técnica.

### Achados da auditoria UX (2026-05-24)

Uma auditoria do PO + designer nas 4 telas existentes (`/cadastro`, `/login`, `/home`, `/empresas/nova`) confirmou os problemas abaixo. **Todos** entram no escopo desta story (estão refletidos em CAs novos).

1. **Bug funcional crítico — não dá para adicionar uma segunda empresa.** Em `/home` (componente `MinhasEmpresas`), o CTA `Cadastrar primeira Empresa` aparece **apenas** no estado vazio. Quando há ≥1 empresa cadastrada, a tela mostra só a lista + botão `Iniciar diagnóstico` (disabled). Roberto fica preso — não há caminho de UI para `/empresas/nova` (só URL direta). **Correção:** botão `Adicionar empresa` (primary) no header da seção `/home` em **todos** os estados (0, 1, 2+).
2. **Sem botão Cancelar em formulários de criação.** `/empresas/nova` só oferece `Cadastrar empresa` (submit) — Roberto que abriu por engano só consegue sair por refresh do navegador ou URL. **Correção:** botão `Cancelar` (secondary) ao lado do submit, navega de volta para `/home` sem persistir nada.
3. **Sem botão Voltar consistente em telas de detalhe.** `/empresas/{id}/show` tem um `<a class="botao botao--ativo">` cru em vez de componente padrão. **Correção:** `Voltar para Minhas Empresas` como componente secondary com ícone, posição padronizada (ao lado/abaixo do CTA primário).
4. **Sem app shell.** Cada view tem seu próprio nav improvisado (`<nav class="topo">` só em `MinhasEmpresas`). Login, cadastro e cadastrar-empresa **não têm header** — Roberto não vê marca nem sabe onde está.
5. **Sem identificação visual da tela ativa.** Nenhuma das telas indica em que parte da plataforma o usuário está. Sem breadcrumb, sem highlight de menu, sem padrão de `<title>`.
6. **Versão escondida.** O número da versão só aparece na tela `welcome` (não autenticada). Em produção, área logada não mostra versão — dificulta suporte ("qual versão você está rodando, Roberto?").
7. **Sem identidade visual de marca.** Logo é texto puro ("DEFOnline" em `<strong>`). **Correção:** logomarca SVG com inicial "D" geométrica, monocromática em `primary` + ponto de acento em `tertiary`. Versão wordmark ao lado do ícone no desktop, só ícone no mobile (≤480px). Entregue como `design/logo.svg`.
8. **Drift de paleta confirmado.** Hex `#1f2937`, `#2563eb`, `#f9fafb`, `#3730a3`, `#dbeafe` etc. espalhados pelas views. Nenhuma cor do `design-system.md` aparece literal — todo o material foi feito "no olho".
9. **Sem footer institucional na área logada.** Links de Termo e Política só existem no fluxo de cadastro. **Correção:** footer mínimo presente em todas as rotas autenticadas com Termo + Privacidade + © + versão.
10. **Sem dropdown "Conta".** `Sair` é botão cru no header de uma única tela. **Correção:** dropdown padronizado `Conta` no header com `Sair` ativo + `Editar perfil` disabled (Onda 2).
11. **Sem responsividade mobile-first explícita.** `max-width: 480px` fixo + sem hamburger + sem drawer. Roberto no celular hoje vê a lista de empresas em coluna espremida.

## Resultado esperado (outcome)

Ao fim do EPIC-004, toda rota autenticada do DEFOnline (`/home`, `/empresas/nova`, `/empresas/{id}`, `/conta` futura, telas do EPIC-002/003) é renderizada dentro de um layout Blade base único com:

- **Logomarca "D"** (SVG `design/logo.svg`) — inicial geométrica monocromática em `primary` com ponto de acento em `tertiary`. Wordmark "DEFOnline" ao lado no desktop, só ícone no mobile (≤480px). Versão dark do logo prevista para hotsite (EPIC-008).
- **Header global** com logo, primeiro nome do usuário logado, dropdown de "Conta" com ação "Sair" + item disabled "Editar perfil" (tooltip "Em breve — Onda 2"), pill discreta indicando ambiente (`homol`) quando `config('app.env') !== 'production'`.
- **Navegação principal responsiva** — sidebar fixa no desktop (≥ 1024px, 240px) e drawer/hamburger no mobile (< 1024px, 280px com overlay e fechamento por Escape/clique-fora), com itens **Minhas Empresas** (ativo), **Adicionar Empresa** (atalho), **Diagnósticos** (disabled — EPIC-002), **Histórico** (disabled — EPIC-003), **Conta** (disabled — Onda 2). Item ativo destacado com fundo `neutral` + texto/ícone `tertiary` + barra vertical de 3px à esquerda (`aria-current="page"`).
- **Breadcrumb** reutilizável renderizando em telas internas (rota atual + 1 nível pai). Não aparece em telas raiz (`/home`).
- **Identificação consistente da tela ativa** em quatro sinais redundantes: (1) item da sidebar destacado, (2) breadcrumb, (3) `<h1>` da página, (4) `<title>` do documento no formato `"{H1} · DEFOnline"`.
- **Botões Cancelar/Voltar padronizados** segundo política única (`design/ux-specs.md` §2): Cancelar em formulários de criação/edição (`/empresas/nova`); Voltar em telas de detalhe/leitura (`/empresas/{id}/show`); nunca os dois juntos.
- **Bug fix do "Adicionar empresa"** — botão presente no header da seção de `/home` em todos os estados (vazio, 1 empresa, 2+).
- **Footer** mínimo institucional em todas as rotas autenticadas: links Termo de Adesão + Política de Privacidade + © DEFOnline 2026 (à esquerda) e **número da versão discreta** (`v0.4.2` em produção, `v0.4.2 · homol` em homologação) com peso 400 em cor `secondary` no canto direito (à direita).
- **Tokens do design system materializados em código** — paleta `#0A2540 / #425466 / #635BFF / #F6F9FC / #FFFFFF`, tipografia Inter 300/400/500/600, espaçamentos, raios e sombras, todos expostos como CSS vars num único arquivo central. Zero hex literal de design system fora desse arquivo (teste arquitetural cobra).
- **Componentes-base do design system implementados** — `button-primary`, `button-secondary`, `button-ghost`, `card`, `input`, `label`, `link`, conforme `especificacao/V2/design-system.md` §Componentes-base.

As quatro telas existentes do EPIC-001 (`/cadastro`, `/login`, `/home`, `/empresas/nova`) + a tela `/empresas/{id}/show` são **refatoradas** para consumirem o novo layout, corrigindo o drift de paleta da STORY-016 e da STORY-011 e os 11 problemas auditados sem regressão funcional.

## Métrica de sucesso (como saberemos que funcionou)

- **Métrica primária (qualidade percebida — alimenta D2 Ativação indiretamente):** em entrevista qualitativa com os primeiros Robertos do beta, 4 de 5 descrevem o produto como "parece profissional" / "parece um sistema sério" sem ser provocado. Janela: D+14 após deploy em homologação.
- **Métrica de cobertura visual:** 100% das rotas autenticadas existentes consomem o novo layout. Zero telas com paleta divergente do design system oficial após esta sprint.
- **Métrica de consistência:** uma mudança de cor primária no design system requer alteração em **um único arquivo de tokens** e propaga em todas as telas — auditado por inspeção manual em pelo menos 3 telas após mudar `--color-primary` localmente.
- **Métrica de qualidade técnica:** cobertura ≥ 80% (gate da STORY-010 ativo); zero regressões nos testes Dusk existentes do EPIC-001; novo teste Dusk validando navegação por menu em mobile e desktop.

## Entregável visível no fim do épico

- [ ] Em homologação, qualquer visitante anônimo em `https://defonline.xandrix.com.br/` vê a **landing pública placeholder** (logo "D" + tagline "Diagnóstico estratégico para sua empresa." + CTAs `Criar conta grátis` e `Já tenho conta` + footer com versão) — **sem mais "hello DEFOnline" + botão de debug**.
- [ ] Em homologação, Roberto loga e vê o produto com header (com logomarca "D" + wordmark), menu lateral (desktop) ou drawer (mobile), breadcrumb e footer aplicados a todas as telas autenticadas.
- [ ] Navegação entre "Minhas Empresas" e "Adicionar Empresa" (`/empresas/nova`) feita pelo menu, **sem precisar digitar URL**. Bug fix: botão "Adicionar empresa" também aparece no header da seção `/home` em todos os estados (vazio, 1 empresa, 2+).
- [ ] Botões "Diagnósticos", "Histórico" e "Conta" no menu ficam visíveis mas desabilitados, com tooltip "Em breve — Onda 2".
- [ ] Formulário `/empresas/nova` tem botão `Cancelar` ao lado de `Cadastrar empresa` (volta para `/home` sem persistir).
- [ ] Tela `/empresas/{id}/show` tem botão `Voltar para Minhas Empresas` consistente com o componente padrão.
- [ ] Roberto consegue identificar onde está em qualquer tela autenticada por **4 sinais redundantes**: item ativo no menu + breadcrumb + `<h1>` + `<title>`.
- [ ] Telas existentes (`/cadastro`, `/login`, `/home`, `/empresas/nova`, `/empresas/{id}/show`) usam paleta Stripe-like oficial (`#0A2540 / #635BFF / #F6F9FC`), tipografia Inter (≤600) e logomarca "D" — corrige drift da STORY-016 e da STORY-011.
- [ ] Footer mostra **número da versão discreto** no canto direito (formato `v0.4.2` em produção, `v0.4.2 · homol` fora dela) + links para Termo e Política de Privacidade + © DEFOnline 2026 no canto esquerdo, em todas as rotas autenticadas e (versão simplificada) nas rotas de auth.
- [ ] `design-system.md` promovido de `alpha` para `v1` com base no aprendizado real da primeira aplicação (gatilho previsto na linha 208 do documento).
- [ ] IDR registrando escolha de framework CSS (Tailwind v4 nativo do Laravel/Livewire, CSS vanilla com vars, ou outra alternativa) com prós/contras documentados.
- [ ] Companion design artifacts em `design/` (`mock-shell.html`, `logo.svg`, `logo.html`, `ux-specs.md`) servem como referência visual para o agente programador e para o validador.

## Fora de escopo (explicitamente)

- **Página `/conta`** com edição de Usuário (nome, senha, telefone) — entra na onda 2 (recuperação de senha).
- **Tela específica de "Adicionar empresa"** no menu — epic do EPIC-001 declara que botão "Adicionar empresa" não aparece nesta onda (Pro feature). O item de menu pode existir mas leva à mesma `/empresas/nova` da STORY-014.
- **Tutorial opcional de 3 slides** após login (espec §3.3) — pode virar story futura; fora deste épico.
- **Dark mode** — não previsto no design system alpha; não entra.
- **Acessibilidade WCAG AAA** — alvo neste épico é AA básico (semântica HTML, labels, foco visível, contraste de cores do design system). Auditoria completa fica para uma story dedicada após v1 estabilizar.
- **Internacionalização (i18n)** — produto é PT-BR no MVP (roadmap §4.3 v2.0).
- **Hotsite público completo** (hero com ilustração, social proof, FAQ, captura de leads, tracking, SEO, planos) — entra no EPIC-008 (renumerado, ex-007) da onda 2. STORY-024 deste épico entrega **apenas uma landing placeholder mínima** em `/` (logo + tagline + CTAs Criar conta / Entrar + footer) para que o domínio público pare de mostrar a página de debug da STORY-007 enquanto o hotsite completo não chega.
- **Telas do EPIC-002 (quiz, relatório, semáforo)** — só preparamos o palco; quem entra com conteúdo é o EPIC-002.
- **Design system v2** ou redesign profundo — fora; o alvo é materializar a `alpha` em v1 com ajustes pontuais nascidos da aplicação real.
- **Remoção do `HelloWorldEmail` job + `HelloWorldMessage` mail + feature flag `HelloWorldEmailHabilitado`** — esse scaffolding da STORY-007 ainda é vetor do `RequestIdPropagationTest`; STORY-024 remove apenas o que é visível ao usuário (view + Livewire component + testes da página). Cleanup completo do scaffolding fica para uma story de housekeeping futura.

## Referências da especificação

- `defonline-docs/especificacao/V2/design-system.md` (paleta, tipografia, espaçamento, raios, sombras, componentes-base, do's and don'ts).
- `defonline-docs/especificacao/V2/especificacao-funcional.md` §3.3 (painel principal após cadastros — base mental do shell), §6.x (decisões abertas sobre UX que podem informar este épico).
- `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §1 (uptime, latência), §9.x (acessibilidade básica esperada).
- ADR-001 (stack: Laravel 13 + Livewire 4) — decisão de framework CSS deve respeitar essa stack.
- ADR-002 (topologia: monolito modular) — implica que componentes Blade vivem no mesmo deploy.

## Dependências

- **Bloqueia:** EPIC-002 (telas do quiz e do relatório precisam do chassi para não nascerem com mais drift); EPIC-003 (lista cronológica + comparativo precisam de breadcrumb e header consistentes); EPIC-008 hotsite (a landing placeholder da STORY-024 ocupa `/` até EPIC-008 substituí-la — EPIC-008 deverá explicitamente substituir/expandir a landing).
- **Bloqueado por:** EPIC-001 (precisa das telas existentes para refatorá-las dentro do novo layout). Pode começar **assim que STORY-016 (`/home` = Minhas Empresas) fechar `done`**; não precisa esperar STORY-017 (validação) nem STORY-018 (provedores RFB reais — não toca UI).
- **Decisões arquiteturais necessárias:** IDR de framework CSS (Tailwind 4 vs CSS vanilla com vars vs outro). Sem ADR nova — design system já está especificado e ADRs existentes não restringem a escolha.

## Estórias

Decompostas em 2 implementações + 1 validação. Princípio principal: **shell + design system materializado + refactor das telas existentes** em uma estória vertical única (STORY-019). Quebrar em duas (shell antes, refactor depois) introduziria estado intermediário com mistura de paletas — vale a pena pagar o custo de uma story `M-L` para entregar o chassi pronto.

Acrescentada em 2026-05-24 (tarde): STORY-024 entrega a landing pública simples e remove a página de debug `/` herdada da STORY-007 (Foundation Phase 1). Story curta (`S`), depende de STORY-019 estar `done` para reusar os componentes do shell e do design system.

- [ ] **STORY-019** (implementation, programador, `ready`, M-L) — App shell + materialização do design system v1 + refactor das telas existentes do EPIC-001 para consumirem o novo layout. Inclui IDR de framework CSS. Não bloqueada por nenhuma outra deste épico; bloqueada apenas pelo fechamento da STORY-016 (`done`) — começa quando o EPIC-001 tiver `/home` redefinida.
- [ ] **STORY-024** (implementation, programador, `ready`, S) — Landing pública simples + remoção da página de debug `/` (`hello-world` da STORY-007). Reusa shell `auth`, `<x-logo>`, `<x-button>`, `<x-footer-version>` da STORY-019. Substitui o teste Dusk smoke `HelloWorldBrowserTest` por um equivalente na landing nova. Bloqueada por STORY-019 `done`.
- [ ] **STORY-020** (validation, validador, `draft`, M) — Validação final do EPIC-004. Promovida para `ready` quando STORY-019 **e** STORY-024 estiverem `in_review`. Checklist em `validation/checklist.md` (criado pelo PO; adicionar seção para a landing). Mesmo padrão da STORY-008/STORY-017.

**Paralelismo:** STORY-019 é vertical e não paraleliza dentro do épico. STORY-024 só começa após STORY-019 `done`. **Paraleliza fora do épico**: STORY-019 pode rodar simultaneamente com STORY-018 (provedores RFB reais — não toca UI) se a capacidade do time permitir. STORY-024 pode rodar simultaneamente com a primeira story do EPIC-002 (ambas dependem apenas do shell pronto). Recomendação do PO: **STORY-019 entra na próxima sprint** (após SPRINT-2026-W22 fechar), antes de a primeira story do EPIC-002 começar. STORY-024 pode fechar na mesma sprint da STORY-019 (é S) ou na sprint seguinte, conforme capacidade.

**Decisões abertas que NÃO bloqueiam abertura do épico:**

- Framework CSS (Tailwind v4 nativo Laravel/Livewire vs CSS vanilla com vars vs outro) — fica para IDR no início da STORY-019. Liberdade técnica do agente programador, com requisito de documentar trade-offs.
- Posicionamento exato dos itens "Diagnósticos" e "Histórico" no menu — Programador propõe na implementação; PO valida em homologação.
- Necessidade de skeleton screens / loading states para o shell — sai como observação da validação; se não emergir como dor, fica para depois.

## Validação final

Critérios em `validation/checklist.md` (já criado pelo PO em 2026-05-24). Relatório do validador em `validation/report.md`.

**Definição de épico concluído:** STORY-019 + STORY-024 + STORY-020 `done` + relatório do validador `approved` + visitante anônimo em homologação vê a landing nova em `/` (sem página de debug) + Roberto autenticado navegando por menu entre `/home` e `/empresas/nova` em mobile (drawer) e desktop (sidebar fixa) + botão "Adicionar empresa" funcional em `/home` em todos os estados + paleta Stripe-like oficial aplicada em 100% das rotas autenticadas + `design-system.md` promovido para v1 + IDR de framework CSS aceita.

## Histórico

- 2026-05-24 — Criado como `ready` pelo PO após análise de gap no backlog: nenhum item priorizado previa app shell, menu de navegação, breadcrumb ou materialização do design system. Drift de paleta já confirmado entre `design-system.md` (oficial: `#0A2540/#635BFF/#F6F9FC`) e STORY-016/011 (`#1f2937/#2563eb/#f9fafb`). Risco de retrabalho ao chegar no EPIC-002 (quiz + relatório com 14 indicadores e semáforo) sem chassi pronto motivou a criação **antes** do início efetivo do EPIC-002. STORY-019 fica `ready` na **próxima sprint** (após SPRINT-2026-W22 — não entra na sprint ativa do EPIC-001). EPIC-004 inserido na WAVE-2026-01; epic placeholder homônimo "Cobrança e Plano Básico" da WAVE-2026-02 renumerado para EPIC-005, com cascata na ordem dos placeholders subsequentes.
- 2026-05-24 (tarde) — PO + designer fizeram auditoria UX das 4 telas existentes e identificaram **11 problemas** (ver §Achados da auditoria UX). O mais crítico é funcional: na `/home` com ≥1 empresa cadastrada, **não existe caminho de UI para cadastrar outra** — Roberto fica preso. Os 11 achados foram incorporados ao outcome do épico e materializados em CAs novos na STORY-019 (CA-14 a CA-19). Entregáveis de design adicionados em `design/`: `mock-shell.html` (protótipo navegável com 6 telas), `logo.svg` (logomarca "D"), `logo.html` (logo em 3 contextos × 3 tamanhos) e `ux-specs.md` (spec UX consolidada com inventário de telas, política Cancelar/Voltar, política de versão, política de identificação de tela ativa, breakpoints, a11y, don'ts). Fluxo de navegação ponta-a-ponta documentado em `design/fluxo-navegacao.md`. Decisões abertas em `ux-specs.md §13` ficam para PO resolver durante a STORY-019 (não bloqueiam abertura).
- 2026-05-24 (final do dia) — PO adicionou **STORY-024** (S) ao épico: substitui a página de debug `/` (`livewire/hello-world` herdada da STORY-007 — Foundation Phase 1) por uma **landing pública placeholder mínima** (logo + tagline + CTAs `Criar conta grátis` / `Já tenho conta` + footer com versão). Motivação: o domínio público hoje mostra "hello DEFOnline" + botão de debug + link para Mailpit, sinal técnico que prejudica a primeira impressão do produto. STORY-024 depende de STORY-019 `done` para reusar `<x-logo>`, `<x-button>`, `<x-footer-version>` e o shell `auth`. Hotsite completo permanece reservado para EPIC-008. Cleanup do scaffolding técnico residual (`HelloWorldEmail` job + mail + flag) fica para uma story de housekeeping futura porque ainda é vetor do `RequestIdPropagationTest`. Validação (STORY-020) agora cobre ambas as stories.
