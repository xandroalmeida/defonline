---
story_id: STORY-019
slug: app-shell-navegacao
title: App shell + materialização do design system v1 + refactor das telas existentes
epic_id: EPIC-004
sprint_id: null
type: implementation
target_role: programador
status: ready
owner_agent: null
created_at: 2026-05-24
updated_at: 2026-05-24
estimated_session_size: M-L
---

# STORY-019 — App shell + materialização do design system v1 + refactor das telas existentes

> **Para o agente programador:** esta é a estória de abertura do EPIC-004 — chassi visual da aplicação. Não entrega funcionalidade nova ao Roberto; entrega o palco onde o EPIC-002 (quiz + relatório) e o EPIC-003 (histórico) vão atuar. Você materializa em código o `especificacao/V2/design-system.md` (hoje só em markdown) e refatora as telas do EPIC-001 para consumirem o novo layout, **corrigindo o drift de paleta** que apareceu nas STORY-011 e STORY-016.

## Contexto (por que esta estória existe)

Hoje o DEFOnline é um conjunto de telas isoladas:

- `/cadastro` e `/login` (STORY-011) usam estilos avulsos definidos por componente.
- `/empresas/nova` (STORY-014/STORY-015) tem seu próprio cabeçalho e disposição.
- `/home` (STORY-016, status `ready`) está prevista para abrir com "Olá Roberto + lista + logout", paleta `#1f2937/#2563eb/#f9fafb` — **divergente do design system oficial** `#0A2540/#635BFF/#F6F9FC` em `especificacao/V2/design-system.md`.

Sem chassi unificado e sem materialização do design system em código, a chegada do EPIC-002 (quiz com tooltips, relatório com 14 indicadores, semáforo verde/amarelo/vermelho, Resumo Executivo) e do EPIC-003 (lista cronológica + comparativo lado a lado) garante retrabalho: cada tela vai precisar do header/menu/breadcrumb e cada componente novo vai inventar sua paleta. O custo de criar o palco depois é maior do que criar antes.

Esta estória **antecede** a primeira story do EPIC-002 e roda em paralelo com STORY-018 (provedores RFB reais — não toca UI) se a capacidade permitir.

- Épico: `epics/EPIC-004-app-shell-navegacao/epic.md`
- Documentos canônicos:
  - `especificacao/V2/design-system.md` (tokens, componentes-base, do's and don'ts — fonte da verdade)
  - `especificacao/V2/especificacao-funcional.md` §3.3 (painel principal — base mental do shell)
  - ADR-001 (stack Laravel 13 + Livewire 4 — restringe a escolha de framework CSS a alternativas compatíveis)
  - ADR-002 (topologia monolito — componentes Blade vivem no mesmo deploy)

## O quê (objetivo desta estória)

Entregar, em uma estória vertical única:

1. **Layout Blade base** (`layouts/app.blade.php` ou equivalente) usado por todas as rotas autenticadas. Slots para conteúdo principal, breadcrumb e título de página.
2. **Header global** com logo "DEFOnline", nome do usuário logado (primeiro nome via accessor), dropdown "Conta" contendo ações de "Sair" (logout) e placeholder "Editar perfil" desabilitado (onda 2), indicador discreto de ambiente quando não for produção.
3. **Navegação principal responsiva**: sidebar fixa no desktop (≥ 1024px) e drawer/hamburger no mobile (< 1024px). Itens:
   - **Minhas Empresas** → `/home` (rota redefinida pela STORY-016) — ativo.
   - **Adicionar Empresa** → `/empresas/nova` — ativo (atalho de conveniência; epic do EPIC-001 declara que botão "Adicionar empresa" não aparece no card de Minhas Empresas, mas no menu vale).
   - **Diagnósticos** → desabilitado, tooltip "Em breve — EPIC-002".
   - **Histórico** → desabilitado, tooltip "Em breve — EPIC-003".
   - **Conta** → placeholder desabilitado, tooltip "Em breve — onda 2".
4. **Breadcrumb** reutilizável (componente Blade) renderizando rota atual + 1 nível pai (ex.: "Minhas Empresas" para `/home`; "Minhas Empresas › Nova empresa" para `/empresas/nova`).
5. **Footer** mínimo: versão deployada (já existe no Welcome do EPIC-000 — extrair para parcial reutilizável), link para Termo de Adesão, link para Política de Privacidade (placeholders quando rota ainda não existe).
6. **Tokens do design system materializados em código** — arquivo único central (`resources/css/tokens.css` ou equivalente da stack escolhida) com CSS vars para todas as cores, espaçamentos, raios, sombras e tipografia conforme `design-system.md` §Tokens.
7. **Componentes-base implementados como componentes Blade reutilizáveis** (Blade Components ou Livewire pures): `<x-button variant="primary|secondary|ghost">`, `<x-card>`, `<x-input>`, `<x-label>`, `<x-link>`. Visual conforme `design-system.md` §Componentes-base.
8. **Refactor das telas existentes do EPIC-001** (`/cadastro`, `/login`, `/home`, `/empresas/nova`) para consumirem o novo layout e os novos componentes Blade — **sem regressão funcional**. Corrige drift de paleta da STORY-011 e da STORY-016.
9. **IDR registrando escolha do framework CSS** com prós/contras (sugestão: Tailwind v4 — alinha com ecossistema Laravel/Livewire atual; alternativa: CSS vanilla com vars puras).
10. **Promoção de `design-system.md`** de `alpha` para `v1` (gatilho da linha 208 do documento), ajustando tokens só se a aplicação real revelou problema concreto. Mudanças vão no commit da estória.

## Por quê (valor para o usuário)

Para Roberto: o produto **parece produto**. Header com marca, menu para circular, breadcrumb para se localizar. A primeira impressão muda de "formulário web" para "plataforma". Em entrevista qualitativa do beta, esperamos que 4 de 5 Robertos descrevam o produto como "parece sério" / "parece profissional" sem provocação.

Para a EBP: paleta única, identidade visual coerente do dia 1 da onda 2 (quando o produto for comercializável). Cada nova tela do EPIC-002/003 já nasce dentro do chassi, sem custo de retrabalho.

Para o time de implementação: alteração de uma cor primária no design system se propaga em **um arquivo único**, não em busca-e-substitui por componente.

## Critérios de aceite

- [ ] **CA-1 (Layout global):** Existe layout Blade base único (sugestão: `resources/views/components/layouts/app.blade.php` ou `resources/views/layouts/app.blade.php`) consumido por **todas** as rotas autenticadas. Telas existentes (`/cadastro`, `/login`, `/home`, `/empresas/nova`) renderizam dentro dele. Feature test verifica que cada rota retorna HTML contendo a estrutura do shell (header com `data-testid="app-header"`, nav com `data-testid="app-nav"`, footer com `data-testid="app-footer"`).
- [ ] **CA-2 (Header global):** Header em todas as rotas autenticadas mostra: logo "DEFOnline" (texto ou SVG simples, link para `/home`); nome do usuário logado via accessor `User::primeiroNome()`; dropdown "Conta" com botão "Sair" (POST `/logout` da STORY-011 — não alterar route) e item "Editar perfil" `disabled` com tooltip "Em breve — onda 2"; indicador `[homol]` em pill discreta quando `config('app.env') !== 'production'`. Em rotas não autenticadas (`/cadastro`, `/login` antes de logar), o header mostra só o logo + link "Já tem conta? Entrar" / "Criar conta", conforme aplicável.
- [ ] **CA-3 (Navegação responsiva):** Menu principal renderiza como sidebar fixa em viewport ≥ 1024px (largura ~240px, fundo `surface` `#FFFFFF`, borda direita `border` `#E3E8EE`) e como drawer/off-canvas em viewport < 1024px, acionado por botão hamburger no header. Itens: **Minhas Empresas** (link, ativo destacado conforme rota atual), **Adicionar Empresa** (link), **Diagnósticos** (`disabled` + tooltip "Em breve — EPIC-002"), **Histórico** (`disabled` + tooltip "Em breve — EPIC-003"), **Conta** (`disabled` + tooltip "Em breve — onda 2"). Item ativo usa estado `tertiary` (`#635BFF`) conforme design-system §"single accent". Estado ativo é determinado pela rota atual (`request()->routeIs(...)` ou similar).
- [ ] **CA-4 (Breadcrumb):** Componente Blade `<x-breadcrumb>` reutilizável aceita array `[['label' => 'Minhas Empresas', 'url' => '/home']]` e renderiza com separador "›" em texto `secondary` (`#425466`). Telas internas (`/empresas/nova`) renderizam breadcrumb com pai apontando para `/home`. Tela `/home` renderiza só o título da seção sem breadcrumb (raiz). Acessibilidade: `<nav aria-label="breadcrumb">` com `<ol>`.
- [ ] **CA-5 (Footer):** Footer único mínimo em todas as rotas autenticadas: versão deployada (já existe no Welcome do EPIC-000 — extrair como parcial Blade `<x-footer-version>` reutilizável), link "Termo de Adesão" (apontando para `/termo` ou placeholder se rota ainda não existir — não criar rota nesta story; STORY-012 já modelou conteúdo), link "Política de Privacidade" (placeholder se rota ainda não existir). Em rotas não autenticadas, footer pode ser ainda mais enxuto (só versão + © DEFOnline 2026).
- [ ] **CA-6 (Tokens do design system materializados):** Arquivo único central (sugestão: `resources/css/tokens.css`) expõe **todas** as cores, espaçamentos, raios, sombras e tipografia de `design-system.md` §Tokens como CSS vars (`--color-primary: #0A2540`, etc.). Aplicação consome as vars; **nenhum hex literal de cor do design system aparece em arquivo de view ou componente fora do `tokens.css`**. Teste arquitetural (Pest): grep nos arquivos `resources/views/**` e `resources/css/**` (exceto `tokens.css`) por hex `#[0-9a-fA-F]{6}` falha se encontrar cor do design system literalmente — exceção: cores semânticas de farol podem aparecer em componente específico do EPIC-002.
- [ ] **CA-7 (Componentes-base implementados):** Existem Blade Components reutilizáveis para `<x-button variant="primary|secondary|ghost">`, `<x-card>`, `<x-input>`, `<x-label>`, `<x-link>` (nomes podem variar — Programador decide). Comportamento visual conforme `design-system.md` §Componentes-base (raios, paddings, focus rings, hover states, disabled states). Cada componente tem ao menos 1 teste de snapshot ou render verificando classes/atributos esperados.
- [ ] **CA-8 (Refactor sem regressão):** Telas existentes (`/cadastro`, `/login`, `/home`, `/empresas/nova`) refatoradas para usar layout novo + componentes novos. **Zero regressão funcional**: todos os testes Pest e Dusk do EPIC-001 continuam verdes sem alteração (exceto onde a estrutura do HTML mudou — neste caso, o teste é ajustado para selecionar o novo elemento, mas o comportamento testado permanece o mesmo). Paleta divergente da STORY-011 e STORY-016 é eliminada — busca por `#1f2937`, `#2563eb`, `#f9fafb` (paleta da STORY-016) em `resources/views/**` retorna zero ocorrências após o refactor.
- [ ] **CA-9 (Responsividade mobile-first):** Em viewport 360px (Roberto digitando no celular — persona declarada no EPIC-001), todas as telas refatoradas renderizam sem scroll horizontal, com drawer fechado por padrão, header com hamburger visível. Botões e inputs têm área de toque ≥ 44×44px. Em viewport ≥ 1024px, sidebar fixa visível e drawer/hamburger ocultos. Validar com Dusk em duas resoluções (360x800 e 1280x800).
- [ ] **CA-10 (Acessibilidade básica):** Foco visível em todos os elementos interativos (focus ring `tertiary @ 40%` conforme design-system); semântica HTML correta (`<header>`, `<nav>`, `<main>`, `<footer>`); itens disabled têm `aria-disabled="true"` e `tabindex="-1"`; dropdown "Conta" abre por clique e por teclado (Enter/Space) e fecha com Escape; drawer mobile fecha com Escape. Não exigimos WCAG AAA — alvo é AA básico.
- [ ] **CA-11 (Promoção do design-system para v1):** `especificacao/V2/design-system.md` atualizado de `alpha` para `v1` no frontmatter/cabeçalho. Mudanças nos tokens (se houve algum ajuste vindo da aplicação real) registradas na seção "Histórico" do documento. Se nada mudou, a promoção fica como ato simbólico documentando que os tokens passaram a teste real de aplicação.
- [ ] **CA-12 (IDR de framework CSS):** Antes de codificar o tokens.css, o agente cria `decisions/idr/IDR-XXX-framework-css.md` registrando: opção escolhida (Tailwind v4 nativo Laravel 13/Livewire 4 ou CSS vanilla com vars ou outro), prós/contras de 2-3 alternativas consideradas, racional (custo de aprendizagem do time, alinhamento com stack atual, peso do bundle final, manutenibilidade dos tokens). PO aprova no chat antes do agente seguir. Pattern segue IDRs existentes (IDR-001..007).
- [ ] **CA-13 (Testes):** Cobertura ≥ 80% (gate da STORY-010 ativo). Testes:
  - Feature: cada rota autenticada renderiza dentro do shell (`data-testid` checks).
  - Feature: navegação principal mostra item ativo correto conforme rota atual.
  - Feature: itens disabled têm `aria-disabled` e classe `disabled`.
  - Arquitetural: nenhum hex do design system fora de `tokens.css`.
  - Dusk: fluxo `cadastrar → confirmar email → cadastrar empresa (mock RFB) → ver Minhas Empresas → navegar para Adicionar Empresa via menu → voltar para /home via breadcrumb → logout via dropdown Conta`. Roda em 360x800 (mobile) e 1280x800 (desktop).
  - Dusk: drawer mobile abre/fecha com hamburger e com Escape.
  - Smoke pós-deploy: probe read-only em `/home` em homologação, verificando presença do shell.

## Fora de escopo

- **Página `/conta`** com edição de dados do Usuário — onda 2.
- **Rotas reais para Termo / Política de Privacidade** — placeholders nesta story; conteúdo é trabalho separado (jurídico revisar — pendência declarada no EPIC-001).
- **Dark mode** — não previsto no design system.
- **Tutorial onboarding de 3 slides** — backlog futuro.
- **Reposicionar `/home` para `/dashboard`** ou renomear rotas existentes — a STORY-016 fixou `/home` como Minhas Empresas; não mexer agora.
- **Animações elaboradas** (slide-in, parallax, etc.) — apenas transições simples (drawer 200ms, dropdown 150ms). Design é flat por princípio (`design-system.md` §Filosofia).
- **Telas do EPIC-002** (quiz, relatório, semáforo) — só preparamos o palco.

## Padrões de qualidade exigidos

- Cobertura ≥ 80% (gate da STORY-010).
- **Zero regressão** nos testes existentes do EPIC-001 (Pest + Dusk).
- Acessibilidade AA básica: semântica HTML, foco visível, `aria-*` correto em estados disabled e dropdowns.
- Mobile-first: testar 360x800 antes de 1280x800.
- **Nenhum hex literal do design system fora de `tokens.css`** (teste arquitetural cobra).

## Dependências

- **Bloqueada por:** STORY-016 (`done`) — precisa que `/home` esteja redefinida como Minhas Empresas para refatorar coerentemente. STORY-017 (validação do EPIC-001) e STORY-018 (provedores RFB reais) **não bloqueiam** — STORY-019 pode rodar em paralelo com elas após STORY-016 fechar.
- **Bloqueia:** STORY-020 (validação final do EPIC-004). Indiretamente, recomendação do PO: primeira story do EPIC-002 espera STORY-019 fechar `done` antes de começar, para não nascer com paleta divergente.
- **Pré-requisitos de ambiente:** todos os anteriores; ambiente de homologação `https://defonline.xandrix.com.br` funcionando.

## Decisões já tomadas

- **`design-system.md` é fonte da verdade** — paleta Stripe-like (`#0A2540 / #425466 / #635BFF / #F6F9FC / #FFFFFF`), Inter 300/400/500/600, tokens listados em §Tokens.
- **Single-accent** — Tertiary (`#635BFF`) usado em **uma** ação por tela; itens ativos do menu também consomem Tertiary (decisão UX coerente com §"Do's").
- **Itens "Diagnósticos" e "Histórico" no menu já aparecem desabilitados** — preparam o usuário para a chegada do EPIC-002/003 sem prometer demais (tooltip "Em breve" explicita).
- **Mobile-first** — Roberto digita no celular (persona EPIC-001).
- **Footer com versão deployada continua existindo** — já é entregue desde STORY-007; apenas extraímos para parcial reutilizável.
- **Não criar rotas para Termo / Política de Privacidade** — placeholders apenas; conteúdo definitivo está pendente do jurídico (decisão PO 2026-05-22 mantida).

## Liberdade técnica do agente

Você decide:

- **Framework CSS** (Tailwind v4 nativo Laravel 13 vs CSS vanilla com vars vs outro). Registre como IDR antes de codar.
- **Estrutura de Blade Components** (anonymous vs class-based, namespace de pastas).
- **Tipo do menu lateral mobile** (drawer com overlay, slide-in lateral, off-canvas com push do conteúdo). Princípio: simplicidade > novidade.
- **Como expor o `primeiroNome()`** no model User (accessor Eloquent vs método explícito).
- **Estratégia de teste arquitetural** para o gate "nenhum hex fora de tokens.css" — Pest expressivo, comando Artisan custom, ou outro.
- **Animação de transição** do drawer e do dropdown (CSS pura preferida; nada de libs de animação).

Você **não** decide:

- Os tokens do design system (paleta, tipografia, espaçamento) — `design-system.md` é fonte da verdade. Ajustes só com IDR e justificativa concreta vindo da aplicação real.
- A existência de itens disabled "Diagnósticos / Histórico / Conta" — decisão PO declarada acima.
- Que `/home` é Minhas Empresas (STORY-016) e `/empresas/nova` é cadastro (STORY-014). Mexer em rotas exige decisão PO.
- Que rotas reais para Termo / Política de Privacidade não entram nesta story.

## Definição de Pronto (DoD)

- [ ] CA-1 a CA-13 passam.
- [ ] Pre-push verde (Pint + Larastan + Pest + Dusk + cobertura ≥ 80%).
- [ ] Pipeline CI verde (validate + build-and-push + deploy + smoke + notify).
- [ ] Deploy em homologação validado: percorrer fluxo `cadastrar → confirmar email → cadastrar empresa → /home → menu para /empresas/nova → breadcrumb de volta → logout` em mobile real (≤ 5 min) e em desktop. Screenshot anexado.
- [ ] `design-system.md` promovido para v1 com seção "Histórico" atualizada.
- [ ] IDR de framework CSS aceita por PO.
- [ ] `index.json` `done`.
- [ ] "Notas do agente" preenchidas.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`. **Antes de codificar:** abrir IDR de framework CSS e aguardar aprovação do PO no chat. **Ao terminar:** avisar o Validador no chat — STORY-020 vai promover de `draft` para `ready` e o Validador entra com `validation/checklist.md` (PO escreve antes) + execução.

## Notas do agente

### Decisões tomadas
- <data> — <decisão>

### Descobertas
- <data> — <gotcha>

### Bloqueios encontrados
- <data> — <bloqueio>

### IDRs criados
- IDR-XXX — Framework CSS escolhido

### Cobertura final
- Geral: <%>

### Telas refatoradas
- `/cadastro` — <evidência: screenshot mobile + desktop>
- `/login` — <evidência>
- `/home` — <evidência>
- `/empresas/nova` — <evidência>

### Drift de paleta corrigido
- Busca por hex divergente da STORY-016 (`#1f2937`, `#2563eb`, `#f9fafb`): <evidência grep — zero ocorrências>

### Links de evidência
- PR: <url>
- Pipeline: <url>
- Tag rc.N: <vX.Y.Z-rc.N>
- IDR: `decisions/idr/IDR-XXX-framework-css.md`
- Screenshot shell mobile (360x800): <anexar>
- Screenshot shell desktop (1280x800): <anexar>
- Screenshot drawer mobile aberto: <anexar>
- Screenshot dropdown "Conta" aberto: <anexar>
