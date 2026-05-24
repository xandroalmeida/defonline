---
story_id: STORY-019
slug: app-shell-navegacao
title: App shell + materialização do design system v1 + refactor das telas existentes
epic_id: EPIC-004
sprint_id: SPRINT-2026-W23
type: implementation
target_role: programador
status: ready
owner_agent: null
created_at: 2026-05-24
updated_at: 2026-05-24
estimated_session_size: M-L
---

# STORY-019 — App shell + materialização do design system v1 + refactor das telas existentes

> **Para o agente programador:** esta é a estória de abertura do EPIC-004 — chassi visual da aplicação. Não entrega funcionalidade nova ao Roberto; entrega o palco onde o EPIC-002 (quiz + relatório) e o EPIC-003 (histórico) vão atuar. Você materializa em código o `especificacao/V2/design-system.md` (hoje só em markdown) e refatora as telas do EPIC-001 para consumirem o novo layout, **corrigindo o drift de paleta** que apareceu nas STORY-011 e STORY-016 + **6 problemas adicionais** (CA-14 a CA-19) identificados em auditoria UX (2026-05-24).
>
> **Antes de codar, leia obrigatoriamente:**
> - `defonline-docs/project-state/epics/EPIC-004-app-shell-navegacao/design/ux-specs.md` — spec UX consolidada (inventário de telas, política Cancelar/Voltar, política de versão, política de identificação de tela ativa).
> - `defonline-docs/project-state/epics/EPIC-004-app-shell-navegacao/design/fluxo-navegacao.md` — fluxo de navegação ponta-a-ponta (mapa de rotas, fluxos detalhados por tela, mobile).
> - `defonline-docs/project-state/epics/EPIC-004-app-shell-navegacao/design/mock-shell.html` — protótipo navegável com as 6 telas. Abra no navegador e percorra. É o "design assinado" pelo PO.
> - `defonline-docs/project-state/epics/EPIC-004-app-shell-navegacao/design/logo.svg` — logomarca "D" oficial (use inline; não baixe).

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

- [ ] **CA-14 (Bug fix — Adicionar empresa sempre disponível):** Em `/home` (componente `MinhasEmpresas`), o botão `Adicionar empresa` (primary, com ícone "+") aparece no header da seção em **todos** os estados (vazio, 1 empresa, 2+). No estado vazio, o CTA inline `Cadastrar primeira empresa` dentro do empty state permanece como hoje — mas o do header da seção é o oficial. Teste Dusk: criar usuário com 1 empresa via factory → acessar `/home` → asserir presença do botão `[dusk="minhas-empresas-cta-adicionar"]` → clicar → asserir navegação para `/empresas/nova`. Atualmente este caminho não existe e o teste falha — a story corrige.

- [ ] **CA-15 (Logomarca "D"):** Criar `resources/views/components/logo.blade.php` consumindo o SVG de `defonline-docs/project-state/epics/EPIC-004-app-shell-navegacao/design/logo.svg`. Componente aceita props `size` (default 32) e `variant` (`default` | `dark`, com `dark` invertendo `fill` do path principal para `#FFFFFF`). Inline no header (não via `<img>` ou tag externa). Wordmark "DEFOnline" em texto Inter weight 500 fica ao lado do ícone no desktop e no mobile ≥ 480px; oculto em viewport < 480px (`@media (max-width: 479px)`). Inclui `aria-label="DEFOnline"` no `<svg>`.

- [ ] **CA-16 (Número da versão no footer):** Footer da rota autenticada (e versão simplificada da rota auth) mostra o número da versão no **canto direito**, separado dos links institucionais. Formato:
  - Produção (`config('app.env') === 'production'`): `v0.4.2` (lê de `config('app.version')`).
  - Não-produção: `v0.4.2 · homol` (middle-dot U+00B7 separando ambiente). Em `local` a string é `v0.4.2 · local`; em `testing` é omitida.
  - Tipografia: peso 400, cor `var(--color-secondary)` (`#425466`), tamanho `var(--fs-body-sm)` (14px). Sem link.
  - Implementado em `<x-footer-version>` parcial reutilizável (já previsto na STORY-019, agora com formato definido). Teste feature: GET `/home` no env `testing` com `APP_VERSION=v0.4.2-test` → asserir `v0.4.2-test` presente no HTML. Dusk: visualmente em homologação confirmar `· homol` aparece.

- [ ] **CA-17 (Identificação da tela ativa — 4 sinais):** Em cada rota autenticada, todos os quatro sinais abaixo estão presentes (exceto breadcrumb em raiz). Inspecionar via feature test em cada rota e Dusk em pelo menos `/home` e `/empresas/nova`:
  1. Item correspondente na sidebar com classe `is-active` + atributo `aria-current="page"` + barra vertical 3px tertiary à esquerda + texto/ícone tertiary + fundo neutral.
  2. Breadcrumb com último item sem link e com `aria-current="page"`. Não renderiza em `/home`.
  3. `<h1>` única na página com texto correspondente (ex.: "Minhas Empresas", "Cadastrar empresa", "{razão social}").
  4. `<title>` no formato `"{H1} · DEFOnline"`.

  Mapeamento canônico (de `design/ux-specs.md §1` e `design/fluxo-navegacao.md §7`):

  | Rota | Sidebar ativa | Breadcrumb | H1 | `<title>` |
  |---|---|---|---|---|
  | `/home` | Minhas Empresas | — | Minhas Empresas | "Minhas Empresas · DEFOnline" |
  | `/empresas/nova` | + Adicionar Empresa | Minhas Empresas › Nova empresa | Cadastrar empresa | "Cadastrar empresa · DEFOnline" |
  | `/empresas/{id}/show` | Minhas Empresas | Minhas Empresas › {nome} | {nome fantasia ou razão social} | "{razão social} · DEFOnline" |

- [ ] **CA-18 (Política de Cancelar / Voltar):** Botões secundários de navegação seguem a política consolidada em `design/ux-specs.md §2` e `design/fluxo-navegacao.md §6`. Concretamente nesta story:
  - `/empresas/nova` tem botão `Cancelar` (variant `secondary`) **ao lado** de `Cadastrar empresa` no desktop (mesma linha, primary à direita); **abaixo** dele no mobile (`flex-direction: column-reverse` com primary no topo). Click em Cancelar navega via GET para `/home` (sem confirmação modal — fora de escopo). `dusk="empresa-cancelar"`.
  - `/empresas/{id}/show` tem botão `Voltar para Minhas Empresas` (variant `secondary` com ícone seta-esquerda) no final do conteúdo. Substitui o `<a class="botao botao--ativo">` cru atual. `dusk="empresa-show-voltar"` (preserva o `dusk` existente).
  - **Cancelar e Voltar nunca coexistem na mesma tela.**
  - `/cadastro` e `/login` **não** ganham Cancelar nem Voltar (são raízes de fluxo público).
  - Test feature: presença do botão Cancelar em GET `/empresas/nova`. Test feature: clicar Cancelar (POST ou GET) volta para `/home`. Test Dusk mobile: ordem visual (primary primeiro, Cancelar embaixo).

- [ ] **CA-19 (Logout com redirect e flash):** O dropdown "Conta" no header tem item `Sair` que dispara POST `/logout` (route nomeada `logout`). Backend já existe (STORY-011) — não alterar. Após logout, redirect para `/login` com flash `cadastro_sucesso` ou flash novo `logout_sucesso` (mensagem: "Você saiu da conta com sucesso.") — Programador decide a chave; valida com PO se quiser reusar a existente. Test Dusk: navegar para `/home` → abrir dropdown Conta → click "Sair" → asserir URL `/login` + flash visível.

## Fora de escopo

- **Página `/conta`** com edição de dados do Usuário — onda 2.
- **Rotas reais para Termo / Política de Privacidade** — placeholders nesta story; conteúdo é trabalho separado (jurídico revisar — pendência declarada no EPIC-001).
- **Dark mode** — não previsto no design system.
- **Tutorial onboarding de 3 slides** — backlog futuro.
- **Reposicionar `/home` para `/dashboard`** ou renomear rotas existentes — a STORY-016 fixou `/home` como Minhas Empresas; não mexer agora.
- **Animações elaboradas** (slide-in, parallax, etc.) — apenas transições simples (drawer 200ms, dropdown 150ms). Design é flat por princípio (`design-system.md` §Filosofia).
- **Telas do EPIC-002** (quiz, relatório, semáforo) — só preparamos o palco.
- **Modal de confirmação "Descartar alterações?"** ao clicar `Cancelar` num form com dirty state — fica para depois. Por enquanto Cancelar simplesmente navega para o pai.
- **Persistência de rascunho** do form `/empresas/nova` (Roberto fecha aba e volta com dados) — não.
- **Páginas de erro customizadas 404/403/500** dentro do shell — desejável incluir, mas o PO autoriza o agente a deixar para story dedicada se a complexidade superar o orçamento da M-L. Decisão explícita no IDR ou em nota do agente.
- **Logo dark / hotsite** — só a variante light (sobre `#FFFFFF`) e a variante dark (sobre `#0A2540` — usada em `homol` pill background ou similar) entram. Aplicação no hotsite é EPIC-008.

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
- **Itens "Diagnósticos", "Histórico" e "Conta" no menu já aparecem desabilitados** — preparam o usuário para a chegada do EPIC-002/003 e Onda 2 sem prometer demais (tooltip "Em breve — Onda 2").
- **Mobile-first** — Roberto digita no celular (persona EPIC-001).
- **Footer com versão deployada continua existindo** — já é entregue desde STORY-007; apenas extraímos para parcial reutilizável `<x-footer-version>` e formalizamos o formato (`v0.4.2` / `v0.4.2 · homol`).
- **Não criar rotas para Termo / Política de Privacidade** — placeholders apenas; conteúdo definitivo está pendente do jurídico (decisão PO 2026-05-22 mantida).
- **Logomarca "D"** — SVG inline em `design/logo.svg`. Inicial geométrica monocromática em `primary` + ponto de acento em `tertiary`. Wordmark "DEFOnline" ao lado no desktop e mobile ≥ 480px; só ícone em < 480px.
- **Botão "Adicionar empresa" sempre visível em `/home`** — header da seção, em todos os estados (vazio, 1, 2+). É correção de bug funcional identificada na auditoria.
- **`Cancelar` em forms de criação, `Voltar` em telas de detalhe; nunca os dois juntos** — política consolidada em `design/ux-specs.md §2` e `design/fluxo-navegacao.md §6`.
- **4 sinais de identificação de tela ativa** — sidebar destacada + breadcrumb + H1 + title. Todos sempre presentes na rota autenticada (exceto breadcrumb na raiz).
- **Posição da pill `homol`** — colada ao logo no header (decisão do PO sobre `ux-specs.md §13.5`).
- **Texto exato dos tooltips disabled** — `"Em breve — Onda 2"` (decisão do PO sobre `ux-specs.md §13.2`).
- **Botão `Iniciar diagnóstico` em `/empresas/{id}/show`** — fica visível como disabled, ocupando o slot do CTA primário para preparar Roberto para EPIC-002 (decisão do PO sobre `ux-specs.md §13.3`).
- **Texto "Voltar para Minhas Empresas"** preferido sobre só "Voltar" — mais informativo (decisão do PO sobre `ux-specs.md §13.4`).
- **Avatar com inicial no header** mantido (decisão do PO sobre `ux-specs.md §13.6`) — barato, dá identidade. Inicial do `primeiroNome()` do User em círculo `surface` com borda `border`.

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

- [ ] CA-1 a CA-19 passam.
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
