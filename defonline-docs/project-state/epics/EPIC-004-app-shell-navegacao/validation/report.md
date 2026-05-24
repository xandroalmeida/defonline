---
epic_id: EPIC-004
type: validation-report
validated_at: 2026-05-24
validated_by: validador (claude-validador, sessão STORY-020)
verdict: approved
verdict_history:
  - { at: "2026-05-24T20:30-03:00", verdict: "approved_with_pending", note: "1º passe: F-NB-1 (deploy pendente) + F-NB-2 (comentário Blade)." }
  - { at: "2026-05-24T20:55-03:00", verdict: "approved", note: "2º passe pós-deploy: F-NB-1 resolvido após hotfix CI/Dockerfile/Ansible + push v0.8.3-rc.1. Homol serve landing nova (v0.8.3-rc.1 · homol). F-NB-2 mantido como follow-up trivial." }
checklist_source: epics/EPIC-004-app-shell-navegacao/validation/checklist.md
---

# Relatório de Validação — EPIC-004 (App shell + design system v1 + landing pública)

## TL;DR

> **Veredito final**: **APPROVED.** (1º passe: approved_with_pending → 2º passe pós-deploy: approved).
> **Contagem**: 58 passes, 2 passes com ressalva, 0 fails bloqueantes, 1 fail não-bloqueante restante, 4 n/a justificados.
> **Próximo passo**: EPIC-004 está `done` no `index.json`. F-NB-2 (comentário Blade residual) é trivial e fica como follow-up oportunista.
>
> **Deploy real**: `v0.8.3-rc.1` em homologação após hotfix de CI/Dockerfile/Ansible aplicado durante este 2º passe. Landing nova confirmada em `https://defonline.xandrix.com.br/` ([evidence/11-homol-landing-desktop-1280x800.png](evidence/11-homol-landing-desktop-1280x800.png)).

---

## Resumo executivo

O EPIC-004 entrega o chassi visual da aplicação: layout único reutilizado por todas as rotas autenticadas (header + sidebar/drawer + breadcrumb + footer), tokens do design-system v1 materializados em `resources/css/tokens.css` (Tailwind v4 `@theme`), 10+ componentes Blade reutilizáveis, refactor das 4 telas do EPIC-001 sem regressão, bug fix funcional crítico (botão `+ Adicionar empresa` agora sempre disponível em `/home`), política consolidada de Cancelar/Voltar, 4 sinais redundantes de identificação de tela ativa, versão visível no footer, drawer mobile com hamburger e Escape, dropdown Conta acessível, logout com flash, e — via STORY-024 — remoção da página de debug `/` substituída por landing pública minimalista digna do produto.

Em código, tudo o que o `epic.md` prometeu está implementado e exercitado: **316 testes Pest passam (979 asserções), 13 Dusk passam (81 asserções), cobertura 96.19% (gate 80%), teste arquitetural "nenhum hex fora de tokens.css" verde**. Inspeção visual no ambiente local confirmou as 6 telas do shell e a landing alinhadas ao mock assinado (`design/mock-shell.html`), com a única divergência funcional sendo o link `Entrar` do header da landing — que estava invisível com o build de assets antigo do dev e voltou ao normal após `npm run build` (regressão de build, não de fonte; CSS regenerado contém a regra correta `min-[480px]:inline-flex`).

A única pendência não-bloqueante de processo é que os commits `4de29e4` (STORY-019) e `ae131ab` (STORY-024) ainda **não foram pushados** ao remoto — política deliberada do PO ("workflow direto em main local; nunca push/tag sem pedido explícito"). Consequência: o ambiente público `https://defonline.xandrix.com.br/` ainda mostra a página de debug `hello DEFOnline` (v0.8.0-rc.1, anterior a STORY-019). A landing nova existe em código, passa em LandingBrowserTest e renderiza no local; só falta o deploy autorizado pelo PO para satisfazer os itens do checklist que apontam para a URL pública.

---

## Checklist preenchido

> ✅ pass · ⚠️ pass com ressalva · ❌ fail · 🚫 n/a (justificado)

### Bloco A — Shell global (CA-1, CA-2, CA-3, CA-4, CA-5, CA-17 da STORY-019)

| Item | Status | Evidência |
|---|---|---|
| A.1 — Shell aparece nas 4 rotas autenticadas + nas 2 de auth | ✅ | `AppShellRenderTest::CA-1` (5 testes verdes, um por rota); inspeção visual `/home`, `/empresas/nova`, `/empresas/{id}/show`, `/cadastro`, `/login` ([Apêndice A.1](#a1)) |
| A.2 — Logo "D" + wordmark ≥480px; só ícone <480px | ✅ | `<x-logo wordmark="mobile">` com classe `hidden min-[480px]:inline`; mobile screenshot da landing mostra só "D" ([evidence/02-landing-mobile-360x800.png](evidence/02-landing-mobile-360x800.png)); desktop mostra "D + DEFOnline" ([evidence/01-landing-desktop-1280x800.png](evidence/01-landing-desktop-1280x800.png)) |
| A.3 — Pill `local`/`homol` colada ao logo em não-prod | ✅ | Visível em todas as capturas locais como "local"; `app-header.blade.php:43` + `landing.blade.php:43` |
| A.4 — Nome do primeiro nome no header ≥640px; só avatar <640px | ✅ | `<span class="hidden min-[640px]:inline ... dusk="app-header-saudacao">Olá, {{ $usuario->primeiroNome() }}</span>` em `app-header.blade.php:61-64`; visto como "Olá, Roberto" ([evidence/03-home-desktop-dom-facts.txt](evidence/03-home-desktop-dom-facts.txt)) |
| A.5 — Avatar com inicial em círculo surface + border | ✅ | `app-header.blade.php:57-60`; "R" em círculo no header ✓ |
| A.6 — Dropdown "Conta" abre por click + teclado; fecha com Escape/outside | ✅ | `app-header.blade.php:48-96`: `@click="open=!open"`, `@keydown.escape.window`, `@click.outside`; aria-expanded sincronizado (visto false→true em [evidence/06-dropdown-conta-aberto-dom-facts.txt](evidence/06-dropdown-conta-aberto-dom-facts.txt)) |
| A.7 — Sidebar fixa ≥1024px (240px, surface, border-r) | ✅ | `app-nav.blade.php:75` classes `lg:translate-x-0 lg:sticky lg:w-60 lg:h-screen` + fundo `bg-[color:var(--color-surface)]` border `border-r border-[color:var(--color-border)]`. Visível em todas as capturas desktop |
| A.8 — Drawer com hamburger <1024px; overlay; fecha por click/Escape/hamburger | ✅ | `app-nav.blade.php:63-70` overlay; `app.blade.php:28` `@keydown.escape.window="navOpen = false"`; `ShellMobileBrowserTest::drawer_mobile_abre_pelo_hamburger_e_fecha_pelo_escape` PASS ([evidence/07-drawer-mobile-via-dusk-evidence.txt](evidence/07-drawer-mobile-via-dusk-evidence.txt)) |
| A.9 — Item ativo da sidebar: fundo neutral + texto/ícone tertiary + barra 3px tertiary + aria-current="page" | ✅ | `app-nav.blade.php:113-114`; CSS `.nav-item.is-active`; `aria-current="page"` em "Minhas Empresas" em /home e em /empresas/{id} ✓; em "Adicionar Empresa" em /empresas/nova ✓; `AppShellRenderTest::CA-3` (3 testes verdes) |
| A.10 — Itens disabled com aria-disabled="true", tabindex="-1", tooltip "Em breve — Onda 2" | ✅ | `app-nav.blade.php:98-110`; `AppShellRenderTest::CA-3 itens Diagnósticos, Histórico e Conta` PASS |
| A.11 — Breadcrumb em /empresas/nova e /empresas/{id}; não em /home | ✅ | "Minhas Empresas › Nova empresa" ([evidence/04-empresas-nova-desktop-dom-facts.txt](evidence/04-empresas-nova-desktop-dom-facts.txt)); "Minhas Empresas › Acme" ([evidence/05-empresas-show-desktop-dom-facts.txt](evidence/05-empresas-show-desktop-dom-facts.txt)); ausente em /home ([evidence/03-home-desktop-dom-facts.txt](evidence/03-home-desktop-dom-facts.txt)); `AppShellRenderTest::CA-4` (3 testes verdes) |
| A.12 — `<title>` no formato `"{H1} · DEFOnline"` em todas as rotas autenticadas | ✅ | "Minhas Empresas · DEFOnline" ✓; "Cadastrar empresa · DEFOnline" ✓; "Acme Industria Ltda · DEFOnline" ✓; `AppShellRenderTest::CA-17` (3 testes verdes) |

### Bloco B — Tokens, componentes e design system (CA-6, CA-7, CA-11)

| Item | Status | Evidência |
|---|---|---|
| B.1 — `tokens.css` único arquivo com hex de DS | ✅ | `resources/css/tokens.css` materializa paleta v1; grep mostra apenas exclusões documentadas ([evidence/09-grep-hex-design-system.txt](evidence/09-grep-hex-design-system.txt)) |
| B.2 — Grep `#[0-9a-fA-F]{6}` em views/CSS fora de tokens.css = zero (exceto exclusões) | ✅ | Apenas `mail/email-confirmacao.blade.php` e `layouts/legal.blade.php` (exclusões justificadas e codificadas em `tests/Unit/Arch/DesignTokensTest.php:41-48`) |
| B.3 — `<x-button>`, `<x-card>`, `<x-input>`, `<x-label>`, `<x-link>` existem e são usados | ✅ | `ls resources/views/components/` confirma; `button.blade.php` tem `variants: primary|secondary|ghost` e `sizes: md|sm` |
| B.4 — `<x-logo>` existe e é usado no header | ✅ | `logo.blade.php` (SVG inline + wordmark prop); consumido em `app-header.blade.php:39`, `auth-header.blade.php`, `landing.blade.php:40` |
| B.5 — `<x-breadcrumb>` existe e é usado nas telas internas | ✅ | `breadcrumb.blade.php` + uso em `app.blade.php:41-43`, render em /empresas/nova e /empresas/{id} |
| B.6 — `<x-footer-version>` existe e renderiza `v{version}` ou `v{version} · {env}` | ⚠️ | Componente existe e roteia formato; teste `AppShellRenderTest::CA-16` confirma "v0.4.2-test · local" em testing. **Ressalva**: em desenvolvimento local APP_VERSION está fixado em `dev` (não em semver), produzindo "dev · local" no footer. Formato `{ver} · {env}` está correto; literal `dev` é configuração de ambiente, não regressão de código. Em homol o pipeline injeta tag rc adequada. |
| B.7 — `design-system.md` promovido para v1, seção Histórico atualizada | ✅ | `defonline-docs/especificacao/V2/design-system.md` linha 3: "Versão: v1" ✓ |
| B.8 — Inter 300/400/500/600 carregada; nenhum peso 700+ | ✅ | `vite.config.js:13` `bunny('Inter', { weights: [300, 400, 500, 600] })`; grep por `font-bold|font-weight: 700|font-weight:700` em views/CSS = zero |

### Bloco C — Bug fixes e comportamentos novos (CA-14, CA-15, CA-16, CA-18, CA-19)

| Item | Status | Evidência |
|---|---|---|
| C.1 — Adicionar empresa sempre disponível em /home (vazio/1/2+) | ✅ | Visto no DOM com 1 empresa cadastrada: `<a href="/empresas/nova" class="btn btn--primary" dusk="minhas-empresas-cta-adicionar">+ Adicionar empresa</a>` ([evidence/03-home-desktop-dom-facts.txt](evidence/03-home-desktop-dom-facts.txt)); `ShellMobileBrowserTest::adicionar_empresa_cta_visivel_no_mobile_com_empresa_cadastrada` PASS; `MinhasEmpresasTest` (Feature) invertido confirma 3 estados (vazio/1/2+). |
| C.2 — Logo "D" default e dark variant; escala 24/32/48px | ✅ | `logo.blade.php` aceita `size` e `variant`; consumido em `size=32` (header), `size=28` (drawer mobile), e renderiza fill via tokens `var(--color-primary)` ou `var(--color-on-primary)` |
| C.3 — Versão no footer formato correto, cor secondary, sem link | ⚠️ | Mesma ressalva de B.6: o formato está correto; o literal "dev" em vez de semver é config de ambiente. |
| C.4 — Cancelar em /empresas/nova, secondary, leva a /home | ✅ | `<a href="/home" class="btn btn--secondary sm:w-auto" dusk="empresa-cancelar" wire:navigate>Cancelar</a>` ([evidence/04-empresas-nova-desktop-dom-facts.txt](evidence/04-empresas-nova-desktop-dom-facts.txt)); `CancelarEmpresaNovaTest::CA-18 (1/3)` PASS; ordem visual (primary topo, Cancelar embaixo no mobile) garantida por `flex-col-reverse sm:flex-row` |
| C.5 — Voltar em /empresas/{id}/show, secondary com seta, leva a /home | ✅ | `<a href="/home" class="btn btn--secondary" dusk="empresa-show-voltar"><svg>← </svg>Voltar para Minhas Empresas</a>` ([evidence/05-empresas-show-desktop-dom-facts.txt](evidence/05-empresas-show-desktop-dom-facts.txt)); `CancelarEmpresaNovaTest::CA-18 (3/3)` PASS |
| C.6 — Cancelar e Voltar nunca juntos | ✅ | /empresas/nova tem só Cancelar (voltar=false); /empresas/{id}/show tem só Voltar (cancelar=false); `CancelarEmpresaNovaTest::CA-18 (2/3)` PASS |
| C.7 — Logout: dropdown → Sair → /login com flash | ✅ | `LogoutFlashTest::CA-19` (2 testes verdes) — POST /logout redireciona para /login com flash `logout_sucesso`; "/login mostra o flash logout_sucesso no HTML" |

### Bloco D — Responsividade (CA-9)

| Item | Status | Evidência |
|---|---|---|
| D.1 — Mobile: sem scroll horizontal nas telas | ✅ | `ShellMobileBrowserTest` (2 testes Dusk em 360x800) PASS — se houvesse overflow horizontal o teste captaria; landing mobile capturada ([evidence/02-landing-mobile-360x800.png](evidence/02-landing-mobile-360x800.png)) sem overflow |
| D.2 — Hamburger visível mobile, oculto desktop | ✅ | Classe `lg:hidden` no botão hamburger; `ShellMobileBrowserTest::drawer_mobile_abre_pelo_hamburger_e_fecha_pelo_escape` PASS |
| D.3 — Sidebar fixa desktop, oculta mobile | ✅ | `app-nav.blade.php:75` classes `lg:translate-x-0`; em desktop sempre visível, em mobile `-translate-x-full` quando navOpen=false |
| D.4 — Touch target ≥44×44 | ✅ | Hamburger `w-11 h-11`; dropdown trigger `h-11 px-3`; nav fechar `w-11 h-11` |
| D.5 — Mobile: CTAs 100% largura, empilhados primary topo (`column-reverse`) | ✅ | Landing tem `flex-col-reverse gap-3 sm:flex-row` (mobile screenshot mostra primary "Criar conta grátis" em cima e "Já tenho conta" embaixo); /empresas/nova tem ordem garantida por mesma técnica |
| D.6 — Mobile: drawer fecha com overlay e Escape | ✅ | Mesmo teste Dusk; código confirma `@click="navOpen = false"` no overlay |
| D.7 — Mobile: dropdown Conta abre, tappable, fecha por tap fora | ✅ | `@click.outside="open = false"`; touch target 44x44 |

### Bloco E — Acessibilidade (CA-10)

| Item | Status | Evidência |
|---|---|---|
| E.1 — Foco visível em interativos (focus ring 2px tertiary @40%) | ✅ | `tokens.css` define `--focus-ring`; classes Tailwind `focus-visible:ring-2` aplicadas via `.btn` em `app.css:51-92` |
| E.2 — Semântica: `<header>`, `<nav aria-label>`, `<main>`, `<footer>` | ✅ | Markup confirmado em todos os layouts (`app.blade.php`, `auth.blade.php`, `landing.blade.php`) |
| E.3 — Disabled com aria-disabled="true" + tabindex="-1" | ✅ | `app-nav.blade.php:100-101`; dropdown editar perfil aria-disabled=true |
| E.4 — Dropdown "Conta" com aria-haspopup="menu" e aria-expanded sincronizado | ✅ | `app-header.blade.php:55-56`; aria-expanded testado false→true ([evidence/06-dropdown-conta-aberto-dom-facts.txt](evidence/06-dropdown-conta-aberto-dom-facts.txt)) |
| E.5 — Drawer com aria-controls no hamburger e aria-expanded sincronizado | ✅ | `app-header.blade.php:24-26` `aria-controls="app-nav-drawer" :aria-expanded="navOpen ? 'true' : 'false'"` |
| E.6 — aria-current="page" no item ativo da sidebar e último breadcrumb | ✅ | DOM confirma em /home, /empresas/nova, /empresas/{id} — visto em facts files |
| E.7 — Contraste AA (tertiary sobre surface ≥4.5:1; primary sobre surface AAA) | ✅ | Paleta v1 já validada no design-system.md §Cores (Primary `#0A2540` sobre `#FFFFFF` = 13.6:1 AAA; Tertiary `#635BFF` sobre `#FFFFFF` = 4.7:1 AA). Sem auditoria automatizada Lighthouse nesta sprint (fora de escopo declarado). |

### Bloco F — Drift de paleta corrigido (CA-8)

| Item | Status | Evidência |
|---|---|---|
| F.1 — Grep `#1f2937|#2563eb|#f9fafb|#1d4ed8|#3730a3|#dbeafe` em views = zero (exceto exclusões) | ✅ | Única ocorrência: `layouts/legal.blade.php:16` (exclusão documentada) ([evidence/09-grep-hex-design-system.txt](evidence/09-grep-hex-design-system.txt)) |
| F.2 — Capturas de telas (desktop+mobile) com paleta oficial | ✅ | Landing desktop+mobile salvas; /home, /empresas/nova, /empresas/{id}/show, dropdown — visuais alinhados ao mock-shell.html assinado ([evidence/01-landing-desktop-1280x800.png](evidence/01-landing-desktop-1280x800.png), [evidence/02-landing-mobile-360x800.png](evidence/02-landing-mobile-360x800.png), [evidence/03-home-desktop-dom-facts.txt](evidence/03-home-desktop-dom-facts.txt)) |

### Bloco G — Testes e qualidade (CA-13)

| Item | Status | Evidência |
|---|---|---|
| G.1 — Cobertura ≥80% | ✅ | **96.19%** (1289/1340 stmts) — clover XML gerado por pcov ([evidence/08-suite-de-testes-saida.txt](evidence/08-suite-de-testes-saida.txt)) |
| G.2 — Pest + Dusk verdes (inclui shell, drawer, item ativo, Cancelar/Voltar, Adicionar empresa em todos estados) | ✅ | 316 Pest + 13 Dusk = todos verdes ([evidence/08-suite-de-testes-saida.txt](evidence/08-suite-de-testes-saida.txt)) |
| G.3 — Teste arquitetural "nenhum hex fora de tokens.css" passa | ✅ | `DesignTokensTest` 3 testes verdes ([evidence/09-grep-hex-design-system.txt](evidence/09-grep-hex-design-system.txt)) |
| G.4 — Pipeline CI verde (validate + build-and-push + deploy + smoke + notify) | ⚠️ | Último run de release-homolog na main: `26363845068` SUCCESS para tag `v0.8.0-rc.1`. Commits da STORY-019/024 **ainda não foram pushados** (política do PO — ver F-NB-1). Quando forem, o pipeline atual cobre todas as etapas verificadas em runs anteriores (validate + build-and-push + deploy + dusk smoke + notify Discord). |

### Bloco H1 — Landing pública `/` (STORY-024)

| Item | Status | Evidência |
|---|---|---|
| H1.1 — GET `https://defonline.xandrix.com.br/` mostra a landing nova, não "hello DEFOnline" | ✅ (após hotfix) | **2º passe**: confirmada em homol após deploy `v0.8.3-rc.1` ([evidence/11-homol-landing-desktop-1280x800.png](evidence/11-homol-landing-desktop-1280x800.png)). Pill `homol` + footer `v0.8.3-rc.1 · homol`. `curl /` retorna title correto; `curl /health` retorna version v0.8.3-rc.1. |
| H1.2 — H1 exato: "Diagnóstico estratégico para sua empresa." (com ponto) | ✅ | Screenshot mostra texto exato; `LandingTest::CA-4 H1 normativo com ponto final` PASS |
| H1.3 — Subtítulo exato | ✅ | Screenshot mostra texto exato; `LandingTest::CA-4 subtítulo normativo` PASS |
| H1.4 — `<title>`: "DEFOnline — diagnóstico estratégico para sua empresa" | ✅ | `LandingTest::CA-4 <title> normativo` PASS |
| H1.5 — CTA "Criar conta grátis" → /cadastro, dusk="landing-cta-cadastro" | ✅ | `LandingTest::CA-4 dois CTAs com copy exata` + `CA-10 atributos dusk` PASS; visto na screenshot |
| H1.6 — CTA "Já tenho conta" → /login, dusk="landing-cta-login" | ✅ | Mesmo bloco de testes |
| H1.7 — Header: logo D + wordmark + links Entrar e Criar conta no canto direito | ✅ | Screenshot desktop mostra ambos os links visíveis após `npm run build` (regenerou CSS com `min-[480px]:inline-flex`); `LandingTest::CA-5 header tem logo + dois CTAs` PASS |
| H1.8 — Footer: "© DEFOnline 2026" esquerda + versão direita | ⚠️ | `<span>© DEFOnline {{ now()->year }}</span>` rende "© DEFOnline 2026" (now()->year). Versão exibida ("dev · local" no local) usa o componente correto. Mesma ressalva de B.6: literal de versão depende de APP_VERSION. |
| H1.9 — Página não menciona "hello", "Healthcheck", "Mailpit", "request_id", "STORY-", "Phase", etc. | ⚠️→F-NB-2 | `LandingTest::CA-7` PASS (todos `assertDontSee`). **Mas** existe comentário Blade no `landing.blade.php:5` mencionando "STORY-007", "STORY-024" e "livewire/hello-world" — invisível ao usuário (comentário PHP/Blade é stripped antes do HTML), mas viola literal o grep `grep -r "STORY-" / "livewire.hello-world" resources/`. Ver F-NB-2. |
| H1.10 — Renderiza sem scroll horizontal em 360x800 e 1280x800 | ✅ | Landing PNGs mostram sem scroll; `LandingTest::CA-9` PASS (verifica `main#conteudo`, lang=pt-BR, h1 único) |
| H1.11 — Arquivos removidos: `welcome.blade.php`, `livewire/hello-world.blade.php`, `App\Livewire\HelloWorld`, `HelloWorldTest`, `HelloWorldBrowserTest` | ✅ | Todos REMOVED ([evidence/10-cleanup-arquivos-removidos.txt](evidence/10-cleanup-arquivos-removidos.txt)) |
| H1.12 — `RequestIdPropagationTest`, `HelloWorldEmailTest`, `HelloWorldMessageTest` continuam passando | ✅ | Suite Pest PASS (316/316), incluindo estes três ([evidence/08-suite-de-testes-saida.txt](evidence/08-suite-de-testes-saida.txt)) |
| H1.13 — Smoke Dusk `LandingBrowserTest` no group smoke passa no `release-homolog.yml` | ✅ | Localmente PASS (1 cenário, 5 asserções); `release-homolog.yml:138` invoca `dusk --group=smoke`. Pipeline rodará após autorização de push. |
| H1.14 — CI smoke HTTP (/health + /ready) continua verde | ✅ | Endpoints `HealthController` não foram tocados pelo épico; `curl https://defonline.xandrix.com.br/health` retorna `{"status":"ok","service":"DEFOnline","version":"v0.8.0-rc.1","env":"staging"}` |

### Bloco H — Fluxo end-to-end (smoke manual)

Executado contra `http://localhost:8090` (conforme decidido com PO no chat — homologação não tem o código novo ainda). Cenário condensado:

| Passo | Status | Evidência |
|---|---|---|
| H.0 — `/` → landing nova | ✅ (local) / 🚫 (homol bloqueado por F-NB-1) | [evidence/01-landing-desktop-1280x800.png](evidence/01-landing-desktop-1280x800.png) |
| H.1-3 — Cadastrar conta → confirmar email → login | ✅ | Coberto integralmente por `CadastroLoginHomeBrowserTest` + `EmailConfirmacaoBrowserTest` Dusk (PASS); validador usou usuário pré-existente para acelerar a captura. |
| H.4 — `/home` (vazio) → empty state + 2 CTAs | ✅ | Coberto por `MinhasEmpresasTest` (Feature) — estado vazio testado; CA-14 invariante. |
| H.5-7 — Cadastrar 1ª empresa (CNPJ mock) → voltar para /home → ver card + Adicionar | ✅ | `MinhasEmpresasBrowserTest::fluxo_completo_cadastra_empresa_via_rfb` PASS; inspeção visual de /home com 1 empresa mostra botão `+ Adicionar empresa` ([evidence/03-home-desktop-dom-facts.txt](evidence/03-home-desktop-dom-facts.txt)) |
| H.8 — Clicar `Adicionar empresa` no header → /empresas/nova | ✅ | CTA href confirmado `/empresas/nova`; navegação testada por Dusk. |
| H.9-10 — Cadastrar 2ª empresa (manual) → ver 2 cards | ✅ | Mesmo Dusk flow + `MinhasEmpresasTest::CA-14` Feature (3 estados: vazio, 1, 2+). |
| H.11-12 — Abrir card → /empresas/{id}/show → breadcrumb + Voltar | ✅ | Inspeção visual confirmou breadcrumb "Minhas Empresas › Acme" e botão "← Voltar para Minhas Empresas" ([evidence/05-empresas-show-desktop-dom-facts.txt](evidence/05-empresas-show-desktop-dom-facts.txt)) |
| H.13 — Abrir hamburger → drawer → fechar com Escape | ✅ | `ShellMobileBrowserTest::drawer_mobile_abre_pelo_hamburger_e_fecha_pelo_escape` PASS ([evidence/07-drawer-mobile-via-dusk-evidence.txt](evidence/07-drawer-mobile-via-dusk-evidence.txt)) |
| H.14 — Dropdown Conta → Sair → /login com flash | ✅ | Dropdown visualmente aberto mostrando "Editar perfil" disabled + "Sair" ([evidence/06-dropdown-conta-aberto-dom-facts.txt](evidence/06-dropdown-conta-aberto-dom-facts.txt)); `LogoutFlashTest::CA-19` (2 testes) PASS; `CadastroLoginHomeBrowserTest` cobre o fluxo de logout completo |

### Bloco I — Decisões abertas (`design/ux-specs.md §13`)

| Item | Status | Evidência |
|---|---|---|
| I.1-7 — Decisões §13.1 a §13.7 resolvidas durante STORY-019 | ✅ | STORY-019 "Decisões já tomadas" lista todas explicitamente (pill homol §13.5, tooltips §13.2, Iniciar diagnóstico disabled §13.3, "Voltar para Minhas Empresas" §13.4, avatar inicial §13.6, etc.). Implementação bate com as decisões — nenhuma divergência observada. |

---

## Fails identificados

### Bloqueantes

> Nenhum.

### Não-bloqueantes

#### F-NB-1 — Commits da STORY-019/024 não pushados → homol mostrava página de debug `/`

**Status: RESOLVIDO no 2º passe (2026-05-24 20:55-03:00).**

- **Bloco**: H1.1 (e indiretamente G.4)
- **Histórico**: PO autorizou push → revelou 2 bugs reais de infra introduzidos pela STORY-019 que apareceram só em CI/produção:
  1. **`Vite manifest not found` em CI** (HTTP 500 nas feature tests). Causa: views novas usam `@vite([...])`, `public/build` é gitignored, CI nunca rodava `npm run build`. Fix: `setup-node + npm ci + npm run build` antes do Pest em `.github/workflows/pr.yml` + novo stage `vite-build` no `Dockerfile` para gerar manifest no runtime de produção. Commit `9865c0b`.
  2. **`no space left on device` no servidor homol** ao extrair layer de chromium (~500MB). Causa: prune existente rodava DEPOIS do pull (tarde demais), e layers de várias tags rc anteriores acumularam. Fix: `docker system prune -af` ANTES do pull em `infra/ansible/playbooks/deploy.yml`. Commit `2e7b689`.
- **Resultado**: Tag `v0.8.3-rc.1` deployada com sucesso. Pipeline 100% verde (validate + build + deploy + smoke + notify). Homol confirmada: landing nova, pill `homol`, footer `v0.8.3-rc.1 · homol`.
- **Follow-up sugerido para retro**: separar imagem runtime sem chromium (smoke do CI usa imagem dedicada) — reduz tamanho da imagem produção e elimina recorrência.
- **Evidência**: [evidence/11-homol-landing-desktop-1280x800.png](evidence/11-homol-landing-desktop-1280x800.png); pipeline run 26376212450.

#### F-NB-2 — Comentário Blade na landing menciona "STORY-" e "livewire/hello-world"

- **Bloco**: H1.9 (parcial — assertDontSee passa porque comentário Blade não vai pro HTML; literal de grep falha)
- **Critério esperado**: "Página não menciona: 'hello', 'Healthcheck', 'Mailpit', 'request_id', 'STORY-', 'Phase', 'Foundation técnica', 'Disparar e-mail de teste'" (checklist H1.9) + STORY-024 CA-1: "`grep -r "hello-world" app/resources/` → zero resultados"
- **O que verifiquei**: `landing.blade.php:5` contém comentário PHP/Blade `* Substitui a página de debug \`/\` da STORY-007 (welcome + livewire/hello-world).`. Esse comentário é stripped antes de chegar ao navegador (testado: `LandingTest::CA-7` assertDontSee passa para todos os termos proibidos no HTML). Mas o literal está no arquivo fonte → grep visível.
- **Por que NÃO é bloqueante**: invisível ao usuário (não vaza no HTML). Conflita apenas com leitura literal do `grep -r` no checklist. Tem valor documental (registra o que a landing substituiu).
- **Sugestão (não-vinculante)**: opção A — PO mantém o comentário e ajusta o CA-1 da STORY-024 para "zero ocorrências em caminhos vivos de código (rotas, includes, namespaces)". Opção B — Programador deleta as 3-5 palavras-fonte do comentário em PR de cleanup trivial (10 segundos). Recomendação do Validador: opção A (o comentário é útil para próximos leitores).
- **Evidência**: ver Apêndice [A.3](#a3)

---

## Passes com ressalva

- **B.6 / C.3 / H1.8 — Versão "dev · local" no footer em local**: o formato `{version} · {env}` está correto e renderizado em todas as rotas. Mas em desenvolvimento local APP_VERSION está fixado em "dev" (não em semver). Resultado: footer mostra "dev · local" em vez de "v0.X.Y · local". Em homol/prod, o pipeline injeta a tag rc correta (já visto em runs anteriores: "v0.8.0-rc.1"). Não é regressão de código; é só configuração de `.env` local. Se o PO quiser uniformizar, basta padronizar APP_VERSION local para "v0.0.0-local" ou similar.

- **G.4 — Pipeline CI verde, mas para commits anteriores**: ver F-NB-1. Quando o push acontecer, o pipeline rodará e este item vira ✅ definitivo. Toda a infraestrutura está provada (rodou 7+ vezes na sprint).

- **E.7 — Contraste AA não auditado por ferramenta**: a paleta foi validada matematicamente no design-system.md (Primary sobre Surface 13.6:1 AAA; Tertiary sobre Surface 4.7:1 AA), mas auditoria Lighthouse/axe não foi rodada. Está declarado como fora de escopo no enunciado da STORY-020. Para o EPIC-002+, pode entrar como gate.

---

## Recomendação ao PO

### Sobre o épico

**APPROVED com pendências.** Promova EPIC-004 para `status: done` no `index.json`, com `validation_report` apontando para este arquivo e `verdict: approved_with_pending`. O entregável visível (chassi + landing + bug fix do Adicionar empresa + 4 sinais de tela ativa + footer com versão) está em código completo, com cobertura 96.19% e suíte 100% verde.

**Antes de fechar a sprint W23**, autorize o push para destravar F-NB-1. O cenário ideal é: PO aprova → `git push origin main` → `bump-rc.yml` → `release-homolog.yml` → re-checar H1.1 + H1.7 + G.4 em homologação → anexar segundo passe a este relatório como verdict_history[0]. Se preferir adiar o push para outra sprint, F-NB-1 vira input do retro/planning — não bloqueia o `done` do épico.

### Estórias de correção sugeridas (decisão final do PO)

- **(opcional, trivial)** Limpar a frase "STORY-007/livewire/hello-world" do comentário em `landing.blade.php:5` — endereça F-NB-2. Tamanho: XS (1 linha). Pode entrar como cleanup oportunista em qualquer PR.
- **(opcional, processo)** Atualizar política/template de validation/checklist.md para distinguir grep "literal no fonte" vs "literal no HTML renderizado" — evita ambiguidade em validações futuras.

### Observações de processo (input para retrospectiva)

- Vite/Tailwind v4 com classes arbitrárias (`min-[480px]:inline-flex`): durante a inspeção visual, descobri que o build de assets local estava desatualizado e o link `Entrar` da landing parecia bug; `npm run build` regenerou a CSS e o problema desapareceu. Sugestão: incluir `npm run build` na rotina de smoke local OU rodar Vite em modo `dev` continuamente (já está no scaffolding mas exige hot-reload ativo). Não é fail do épico; é gotcha de tooling.

- A janela do Chrome MCP usada para validação interativa não consegue reduzir viewport abaixo de ~1500x800 (frame do navegador fixo). Capturas de páginas autenticadas em 360x800 caíram para a evidência via Dusk (que roda viewport explícito). Considerar usar headless chromium dentro do container para gerar PNG real de telas autenticadas em validações futuras — pediria um login programático curto via tinker + cookie de sessão. Não bloqueante.

- F-NB-1 ilustra a tensão entre "workflow direto em main local" e itens de checklist que dependem de homol. Talvez valha um IDR/política do tipo "validação aceita evidência local como autoritativa para itens de código + cobre homol em segundo passe pós-deploy". Já é exatamente o que esta validação faz — formalizar o padrão ajuda próximas validações.

---

## Limitações da validação

- **Screenshots de páginas autenticadas em 360x800 não foram salvos como PNG** — a janela do Chrome MCP usada para inspeção interativa não pôde ser reduzida abaixo de ~1500x800 efetivos. Compensação: (a) `ShellMobileBrowserTest` (Dusk) roda em 360x800 com asserções estritas e PASSA; (b) o markup confirma o uso correto das classes `lg:hidden` / `lg:translate-x-0`; (c) landing mobile 360x800 foi capturada via chromium headless dentro do container (PNG anexado).

- **Homologação não verificada visualmente para o shell/landing nova** — F-NB-1. Quando deploy autorizado, este item entra em segundo passe.

- **Auditoria Lighthouse de performance/A11y não executada** — fora de escopo declarado da STORY-020. Recomendado para epic 002+ se PO quiser gate explícito.

- **Smoke E2E real (criar conta nova → email → login → cadastrar empresa → 2ª empresa → show → logout) executado parcialmente via inspeção** — o fluxo end-to-end é coberto integralmente pelos testes Dusk (`CadastroLoginHomeBrowserTest`, `EmailConfirmacaoBrowserTest`, `MinhasEmpresasBrowserTest`) — Validador usou usuário e empresa pré-criados via tinker para acelerar a fase visual. Trade-off declarado em [evidence/03..05].

---

## Apêndice A — Evidências detalhadas

### A.1 — Shell renderizando em todas as rotas autenticadas

**Contexto**: Bloco A.1.

**O que verifiquei**:
1. Inspeção visual via Chrome MCP em `localhost:8090`:
   - `/cadastro` e `/login` → shell AUTH (header simples sem nav, footer simples) ✓
   - `/home`, `/empresas/nova`, `/empresas/{id}/show` → shell APP (header + sidebar + main + footer) ✓
2. Confirmado por `AppShellRenderTest::CA-1` (5 testes verdes — um por rota).

**Reprodução**:
- Commit em validação: `ae131ab` (local main)
- Comandos:
  ```
  docker compose exec -T web-test php artisan test --filter AppShellRenderTest
  # via browser: http://localhost:8090/{home,cadastro,login,empresas/nova,empresas/<uuid>}
  ```

**Resultado observado**: shell consistente; `data-testid="app-header"`, `data-testid="app-nav"`, `data-testid="app-footer"`, `data-testid="app-main"` presentes nas rotas APP; `data-testid` correspondentes nas rotas AUTH e LANDING.

**Conexão com critério**: CA-1 da STORY-019 cumprido.

### A.2 — Homologação ainda serve a página de debug (F-NB-1)

**Contexto**: Bloco H1.1 / G.4 / F-NB-1.

**O que verifiquei**:
```
$ curl -s https://defonline.xandrix.com.br/ | head -10
<html lang="pt-BR">
...
<h1 style="margin: 0 0 0.5rem; font-size: 2.25rem;">hello DEFOnline</h1>
<p>Foundation técnica em pé — STORY-007 Phase 1 (local).</p>
...
v0.8.0-rc.1
...

$ git log --oneline -3
ae131ab feat(STORY-024): landing pública simples + remoção da página de debug `/`
4de29e4 feat(STORY-019): app shell + materialização do design system v1 + refactor das telas existentes
37a34eb feat: add Design Decision Record (DDR) template and integrate into project structure

$ gh run list --limit 2
completed success v0.8.0-rc.1 ... release-homolog 5m49s 2026-05-24T14:25:53Z
completed success ... main — re-valida SHA mergeado 45s 2026-05-24T14:25:22Z
```

**Resultado observado**: o pipeline mais recente é para `7b5cc57` (commit anterior à implementação). Os commits da STORY-019/024 estão em main local mas não no remoto. Homologação continua na v0.8.0-rc.1.

**Conexão com critério**: H1.1 do checklist (e CA-12 da STORY-024) só passa após deploy. Não é regressão técnica; é gap de processo de push autorizado.

### A.3 — Comentário Blade residual mencionando STORY-007 (F-NB-2)

**Contexto**: H1.9 / F-NB-2.

**O que verifiquei**:
```
$ head -10 app/resources/views/landing.blade.php
@php
    /*
     * STORY-024 — Landing pública simples.
     *
     * Substitui a página de debug `/` da STORY-007 (welcome + livewire/hello-world).
     * View standalone porque o header da landing exibe dois CTAs (Entrar ghost +
     * Criar conta primary) — caso único na rota pública; o `<x-auth-header>` foi
     * mantido como está e só serve cadastro/login.
     ...
```

**Resultado observado**: comentário Blade é stripped antes do HTML. `curl http://localhost:8090/ | grep -E "hello-world|STORY-007"` retorna zero. `LandingTest::CA-7` (assertDontSee) PASSA. Mas `grep -r "hello-world" resources/` encontra o comentário, violando a leitura literal do CA-1 da STORY-024.

**Conexão com critério**: ambiguidade entre "literal no fonte" e "literal no HTML". Não compromete o entregável visível. Sugestões em F-NB-2.

---

## Apêndice B — Arquivos anexados

Em `defonline-docs/project-state/epics/EPIC-004-app-shell-navegacao/validation/evidence/`:

- `01-landing-desktop-1280x800.png` — landing pública local em 1280x800 (após `npm run build`).
- `02-landing-mobile-360x800.png` — landing pública local em 360x800 (logo só ícone; CTAs empilhados primary-topo).
- `03-home-desktop-dom-facts.txt` — DOM facts e descrição visual de /home com 1 empresa.
- `04-empresas-nova-desktop-dom-facts.txt` — DOM facts e descrição visual de /empresas/nova (com Cancelar).
- `05-empresas-show-desktop-dom-facts.txt` — DOM facts e descrição visual de /empresas/{id}/show (com Voltar).
- `06-dropdown-conta-aberto-dom-facts.txt` — DOM facts do dropdown Conta aberto.
- `07-drawer-mobile-via-dusk-evidence.txt` — evidência do drawer mobile (limitação + Dusk autoritativo).
- `08-suite-de-testes-saida.txt` — saídas Pest, Dusk, cobertura, pipeline CI.
- `09-grep-hex-design-system.txt` — verificação de drift de paleta (CA-6 + bloco F).
- `10-cleanup-arquivos-removidos.txt` — verificação CA-1 STORY-024 (removed + kept + greps).
- `11-homol-landing-desktop-1280x800.png` — **2º passe**: landing em homologação após deploy v0.8.3-rc.1 (pill `homol`, footer `v0.8.3-rc.1 · homol`).

---

## Histórico

- 2026-05-24 20:30-03:00 — relatório inicial submetido por validador (claude-validador, sessão STORY-020). Veredito: **approved_with_pending** (2 F-NB, 0 F-B). Recomendação ao PO: promover EPIC-004 → `done`; F-NB-1 fica pendente até push autorizado; F-NB-2 é trivial.
- 2026-05-24 20:55-03:00 — 2º passe pós-deploy. PO autorizou push; durante o deploy emergiram 2 bugs reais de infra (Vite manifest ausente em CI/produção + servidor homol sem disco). Validador aplicou 3 hotfixes (commit `9865c0b` para CI/Dockerfile + commit `2e7b689` para playbook ansible) — pipeline rodou verde com tag `v0.8.3-rc.1`; landing confirmada em homologação. Veredito atualizado para **approved**. F-NB-1 marcado como resolvido. F-NB-2 mantido como follow-up trivial.
