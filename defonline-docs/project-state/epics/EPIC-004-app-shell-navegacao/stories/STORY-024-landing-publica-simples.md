---
story_id: STORY-024
slug: landing-publica-simples
title: Landing pública simples + remoção da página de debug `/`
epic_id: EPIC-004
sprint_id: SPRINT-2026-W23
type: implementation
target_role: programador
status: ready
owner_agent: null
created_at: 2026-05-24
updated_at: 2026-05-24
estimated_session_size: S
---

# STORY-024 — Landing pública simples + remoção da página de debug `/`

> **Para o agente programador:** esta é uma estória curta (S) de cleanup + entrega visível. A rota `/` hoje renderiza uma **página de debug** (`livewire/hello-world`) criada na STORY-007 (Foundation Phase 1) para validar a fundação técnica (versão, healthcheck, dispatch de email de teste, propagação de `request_id`). A fundação está provada e a página perdeu propósito — pior, ela é a primeira impressão que qualquer visitante (incluindo Roberto vindo de link compartilhado) tem do produto. Você remove essa página e coloca no lugar uma **landing pública simples** que dá identidade ao DEFOnline até o hotsite real chegar no EPIC-008.
>
> **Esta story depende de STORY-019 estar `done`.** Você reusa o `<x-logo>`, o `<x-button>`, o `<x-footer-version>`, os tokens CSS e o shell `layouts/auth.blade.php` (ou equivalente) entregues lá.

## Contexto (por que esta estória existe)

Hoje em `https://defonline.xandrix.com.br/` o visitante vê:

- `hello DEFOnline` em h1.
- "Foundation técnica em pé — STORY-007 Phase 1 (local)" como caption.
- Um card listando Versão, Ambiente, Healthcheck (`OK`), `request_id`.
- Um botão "Disparar e-mail de teste (web → fila → worker → Mailpit)".
- Links de Endpoints técnicos para `/health`, `/ready` e `Mailpit: localhost:8025`.

É uma página **de debug**. Faz todo o sentido para validar a fundação (STORY-007) — mas STORY-007 está `done` há muito tempo, a fundação foi exercitada por todas as stories subsequentes, o `usuario_cadastrado` real (STORY-011) substituiu o evento proxy `hello_world_visualizado`, o EPIC-001 inteiro flexiona o pipeline `web → fila → worker → email` com email real de confirmação (STORY-013), e a propagação de `request_id` é coberta por testes próprios (`RequestIdPropagationTest`).

O custo de manter a página:

- **Primeira impressão ruim.** Qualquer link compartilhado para a raiz mostra "hello DEFOnline" + botão de debug.
- **Confusão com `Mailpit: localhost:8025`** — link quebrado em produção, sugere ambiente errado.
- **Mistura sinal de produto com sinal técnico** — `/health` e `/ready` continuam existindo, mas não precisam estar linkados na home pública.
- **Risco de SEO** quando o domínio começar a ser compartilhado em entrevistas com Robertos.

A entrega correta de uma landing de marketing/conversão fica reservada para o **EPIC-008 (Hotsite)** — copy, hero, social proof, FAQ, captura de leads, etc. Esta story entrega **apenas** um placeholder digno: logo, frase de proposta de valor curta, dois CTAs (`Criar conta` e `Entrar`) e o footer institucional. É o equivalente de "site institucional de uma linha" que muitos SaaS B2B sérios mantêm enquanto não têm verba/conteúdo para o hotsite completo.

- Épico: `epics/EPIC-004-app-shell-navegacao/epic.md`
- Documentos canônicos:
  - `especificacao/V2/design-system.md` (tokens, componentes — fonte da verdade)
  - `defonline-docs/project-state/epics/EPIC-004-app-shell-navegacao/design/ux-specs.md` (políticas UX)
  - `defonline-docs/project-state/epics/EPIC-004-app-shell-navegacao/design/fluxo-navegacao.md` §2 (mapa de rotas — incluir `/` como rota pública)
  - ADR-006 (smoke read-only — orienta o teste smoke novo)

## O quê (objetivo desta estória)

Dois movimentos:

### Movimento 1 — Remover a página de debug

Remover do código:

- `app/resources/views/welcome.blade.php`
- `app/resources/views/livewire/hello-world.blade.php`
- `app/app/Livewire/HelloWorld.php`
- `app/tests/Feature/Livewire/HelloWorldTest.php`
- `app/tests/Browser/HelloWorldBrowserTest.php`

**Manter** (não tocar):

- `app/app/Jobs/HelloWorldEmail.php` — ainda é o melhor exercitador isolado do pipeline `web → fila → worker → mail` em testes; é usado em `tests/Feature/Observabilidade/RequestIdPropagationTest.php` para validar propagação de `request_id` no job. Renomear/limpar é um IDR à parte, fora desta story.
- `app/app/Mail/HelloWorldMessage.php` — idem.
- `app/app/Features/HelloWorldEmailHabilitado.php` + `tests/Unit/Features/HelloWorldEmailHabilitadoTest.php` — feature flag de exemplo; útil como referência. Remoção fica para depois.
- `app/tests/Feature/Jobs/HelloWorldEmailTest.php` — testa o job, não a página.
- `app/tests/Unit/Mail/HelloWorldMessageTest.php` — idem.
- `app/tests/Feature/Observabilidade/RequestIdPropagationTest.php` — usa `HelloWorldEmail::dispatch` como vetor; continua válido.
- `/health` e `/ready` (`HealthController`) — permanecem como endpoints técnicos para CI smoke (`release-homolog.yml`).
- Evento `hello_world_visualizado` no banco de dados — não migrar/remover. É histórico; novos eventos não serão emitidos.

### Movimento 2 — Especificar e implementar a landing pública simples

Substituir a rota `/` por uma view simples que represente o produto. Sem auth, sem state, sem JS extra (além do que já vem do Livewire global se for usado — mas Livewire **não é necessário** aqui; uma view Blade pura serve).

**Conteúdo da landing (PT-BR):**

```
┌──────────────────────────────────────────────────────────────────┐
│ [D] DEFOnline                              [Entrar] [Criar conta]│  ← header AUTH (estilo)
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│                                                                  │
│         Diagnóstico estratégico para sua empresa.                │
│                                                                  │
│         Respostas claras sobre como sua indústria está           │
│         em 14 indicadores essenciais — em minutos, sem           │
│         consultor caro, sem planilha.                            │
│                                                                  │
│              [ Criar conta grátis ]   [ Já tenho conta ]         │
│                                                                  │
│                                                                  │
├──────────────────────────────────────────────────────────────────┤
│ © DEFOnline 2026                                v0.4.2 · homol   │  ← footer AUTH simplificado
└──────────────────────────────────────────────────────────────────┘
```

**Componentes-base e padrões reusados (de STORY-019):**

- `<x-logo>` (SVG inline, default light).
- Header no padrão `AUTH` shell (logo à esquerda + links no canto direito). **Não** monta sidebar/drawer — landing não tem navegação interna.
- Tipografia Inter (300/400/500/600). H1 da landing usa peso 300 com tamanho `--fs-h1` (40px desktop, 28px mobile) ou `--fs-display` (80px) se o programador preferir — mantém estética Stripe.
- `<x-button variant="primary">` para "Criar conta grátis"; `<x-button variant="secondary">` para "Já tenho conta".
- `<x-footer-version>` no canto direito do footer (mesma especificação da STORY-019: `v0.4.2 · homol` em não-prod, `v0.4.2` em prod).
- 100% tokens via CSS vars; **zero hex literal**.

**Copy normativa (PT-BR, sem placeholder):**

| Elemento | Texto |
|---|---|
| `<title>` | `DEFOnline — diagnóstico estratégico para sua empresa` |
| H1 | `Diagnóstico estratégico para sua empresa.` |
| Subtítulo (lead) | `Respostas claras sobre como sua indústria está em 14 indicadores essenciais — em minutos, sem consultor caro, sem planilha.` |
| CTA primário | `Criar conta grátis` |
| CTA secundário | `Já tenho conta` |
| Link header — esquerda | logo "D" + wordmark "DEFOnline" (sem link, ou link para `/` mesmo) |
| Link header — direita | `Entrar` (ghost), `Criar conta` (primary, pequeno) |
| Footer esquerdo | `© DEFOnline 2026` |
| Footer direito | versão (componente `<x-footer-version>`) |

**Notas:**

- "Criar conta **grátis**" — o "grátis" é literal e proposital: comunica ausência de fricção/cobrança no MVP. Quando o EPIC-005 (Cobrança) chegar, esta story decide se ajusta o texto.
- O segundo CTA "Já tenho conta" leva para `/login`. Mesmo que redundante com o link `Entrar` do header, repetimos abaixo do H1 porque é onde os olhos vão.
- **Sem hero image**, **sem ilustração**, **sem screenshots da app**. Mantemos um placeholder honesto: texto + 2 CTAs. EPIC-008 entra com tudo isso depois.
- **Sem rastreador de marketing** (Google Analytics, Meta Pixel, etc.). Decisão de tracking fica para EPIC-008 e tem dependência jurídica (LGPD).
- **Sem links institucionais no footer da landing** (Termo, Privacidade) — eles ficam só nas rotas autenticadas e nas rotas de auth (cadastro/login). Decisão: a landing é minimalista; quem clica em "Criar conta" verá Termo e Privacidade no fluxo de aceites da STORY-011.

## Por quê (valor para o usuário)

Para Roberto vindo de link compartilhado: vê uma página que **parece um produto sério**, entende em 5 segundos o que é (diagnóstico estratégico em 14 indicadores), tem caminho claro para entrar (se já tem conta) ou criar (se não tem). Não vê mais "hello DEFOnline" + botão de debug.

Para a EBP: a primeira impressão pública do produto deixa de ser uma página técnica. Entrevistas com Robertos podem ser introduzidas com "entre em defonline.xandrix.com.br" sem constrangimento.

Para o time: reduz superfície de código não usada (remove HelloWorld Livewire component + 2 testes). Mantém o que ainda tem valor (`HelloWorldEmail` job para teste de propagação).

## Critérios de aceite

- [ ] **CA-1 (Remoção limpa):** Os 5 arquivos abaixo deixam de existir:
  - `app/resources/views/welcome.blade.php`
  - `app/resources/views/livewire/hello-world.blade.php`
  - `app/app/Livewire/HelloWorld.php`
  - `app/tests/Feature/Livewire/HelloWorldTest.php`
  - `app/tests/Browser/HelloWorldBrowserTest.php`

  Buscas confirmam:
  - `grep -r "Livewire\\\\HelloWorld" app/` → zero resultados em código de produção.
  - `grep -r "hello-world" app/resources/` → zero resultados.
  - `grep -r "livewire.hello-world" app/` → zero resultados.

  Arquivos que **permanecem inalterados** (lista explícita para evitar overreach): `HelloWorldEmail.php`, `HelloWorldMessage.php`, `HelloWorldEmailHabilitado.php`, `RequestIdPropagationTest.php`, `HelloWorldEmailTest.php`, `HelloWorldMessageTest.php`, `HelloWorldEmailHabilitadoTest.php`, `HealthController.php`.

- [ ] **CA-2 (Rota `/` aponta para a landing):** Em `routes/web.php`, a definição atual:
  ```php
  Route::get('/', function () { return view('welcome'); });
  ```
  é substituída por uma rota nomeada apontando para a nova view (a critério: closure que retorna `view('landing')`, controller `LandingController@index`, ou `Route::view('/', 'landing')->name('landing')`). Programador escolhe — preferência do PO: `Route::view('/', 'landing')->name('landing')` (mais simples; sem state). Rota deve ser `name('landing')` para uso em redirects futuros.

- [ ] **CA-3 (View `landing.blade.php` existe e renderiza):** Nova view `app/resources/views/landing.blade.php` (Blade puro, sem Livewire) consome o shell de auth (`<x-layouts.auth>` ou equivalente entregue pela STORY-019). Renderiza H1, subtítulo, dois CTAs e usa `<x-logo>`, `<x-button>`, `<x-footer-version>`.

- [ ] **CA-4 (Copy exata):** Os textos abaixo aparecem **literalmente** na página, sem variações:
  - `<title>` do documento: `"DEFOnline — diagnóstico estratégico para sua empresa"`.
  - H1: `"Diagnóstico estratégico para sua empresa."` (com ponto final).
  - Subtítulo: `"Respostas claras sobre como sua indústria está em 14 indicadores essenciais — em minutos, sem consultor caro, sem planilha."`.
  - CTA primário: `"Criar conta grátis"` → href `route('cadastro')` (que aponta para `/cadastro`).
  - CTA secundário: `"Já tenho conta"` → href `route('login')` (que aponta para `/login`).
  - Footer: `"© DEFOnline 2026"` à esquerda.
  - Versão à direita via `<x-footer-version>`.

  Mudanças de copy depois desta story exigem decisão do PO (não é "ajuste de texto", é mudança de proposta de valor pública).

- [ ] **CA-5 (Header da landing):** Header mostra logo "D" + wordmark `DEFOnline` à esquerda; à direita, dois itens: link `Entrar` (variant ghost ou link) e botão `Criar conta` (variant primary, tamanho pequeno). Em mobile (<480px), o wordmark pode sumir e os dois CTAs do header podem se reduzir a um único `Entrar` (segundo CTA vira redundante com o do corpo) — programador decide com base na largura disponível. Não há hamburger nesta página (landing não tem nav interna).

- [ ] **CA-6 (Layout responsivo):** Em viewport 360x800, todos os elementos cabem sem scroll horizontal, H1 quebra em 2-3 linhas naturalmente, CTAs ficam empilhados ou lado a lado conforme o que couber. Em viewport 1280x800, conteúdo centralizado (max-width ~720px), respira em torno do espaço negativo (`neutral` como background, `surface` se algum container — provavelmente sem container, fundo direto).

- [ ] **CA-7 (Sem dependências de debug):** A view nova **não** referencia:
  - Mailpit
  - `/health`, `/ready` (como links no HTML)
  - `request_id` visível
  - Versão como texto solto fora do `<x-footer-version>`
  - `HelloWorldEmail` ou afins
  - String "STORY-" ou "Phase" (vocabulário interno de projeto)

  Endpoints `/health` e `/ready` continuam funcionando — são consumidos por CI/observabilidade, não pela landing.

- [ ] **CA-8 (Versão visível no footer):** `<x-footer-version>` renderiza no canto direito do footer da landing, mesmo padrão da STORY-019 (CA-16): `v0.4.2` em produção, `v0.4.2 · homol` em homologação, `v0.4.2 · local` em local, vazio em `testing`. Cor `secondary`, peso 400, tamanho body-sm. Sem link.

- [ ] **CA-9 (Acessibilidade básica):** `<main>` com `id="conteudo"`, `<h1>` única, contraste AA (primary sobre neutral ≥ 7:1; tertiary sobre surface ≥ 4.5:1 já validado em STORY-019). Foco visível em ambos CTAs. `<title>` descritivo. `lang="pt-BR"` no `<html>`.

- [ ] **CA-10 (Smoke test substituído):** O teste `HelloWorldBrowserTest::test_visitor_sees_hello_page_with_version_and_ok_status` (com `#[Group('smoke')]`) — que é executado pelo `release-homolog.yml` na etapa "Dusk smoke (1 cenário crítico contra URL real)" — é substituído por um novo teste Dusk em `tests/Browser/LandingBrowserTest.php`:
  ```php
  #[Group('smoke')]
  public function test_visitor_sees_landing_with_logo_and_ctas(): void
  {
      $this->browse(function (Browser $browser) {
          $browser->visit('/')
              ->assertSee('Diagnóstico estratégico')
              ->assertPresent('@landing-cta-cadastro')
              ->assertPresent('@landing-cta-login');
      });
  }
  ```
  Os dois CTAs recebem atributo `dusk="landing-cta-cadastro"` e `dusk="landing-cta-login"` na view. Smoke do CI continua passando porque ainda há ≥1 teste com `#[Group('smoke')]` apontando para `/`.

- [ ] **CA-11 (Testes):** Cobertura ≥ 80% (gate da STORY-010 ativo, herdado da STORY-019).
  - Feature test: GET `/` retorna 200 e contém o H1 e os textos da copy normativa.
  - Feature test: GET `/` contém link para `/cadastro` e `/login`.
  - Feature test: GET `/` **não** contém as strings `"hello"`, `"Healthcheck"`, `"Mailpit"`, `"request_id"`.
  - Dusk smoke (CA-10).
  - Zero regressão: todos os outros testes Pest e Dusk continuam verdes. Em particular, `RequestIdPropagationTest`, `HelloWorldEmailTest` e `HelloWorldMessageTest` **continuam passando sem alteração** porque o job e o mail não foram tocados.

- [ ] **CA-12 (Deploy + smoke verdes):** Pipeline `release-homolog.yml` verde com o novo smoke Dusk substituindo o anterior. CI smoke HTTP (`curl /health + /ready`) continua passando porque os endpoints não foram tocados.

## Fora de escopo

- **Hero image, ilustração ou screenshots do produto.** EPIC-008.
- **Social proof, depoimentos, logos de clientes.** EPIC-008.
- **FAQ, "Como funciona", seção de planos.** EPIC-008.
- **Formulário de captura de lead** (Roberto deixar email para receber novidades). EPIC-008.
- **Tracking analítico** (GA, Meta Pixel, Hotjar). EPIC-008 com decisão jurídica (LGPD).
- **Multi-idioma.** Roadmap §4.3 v2.0.
- **Remoção do `HelloWorldEmail` job + mail + feature flag.** Vale a pena, mas fica para uma story de cleanup à parte (`STORY-XXX cleanup: remover scaffolding STORY-007`) — não nesta story porque o job ainda é vetor do `RequestIdPropagationTest` e remover sem substituir esse vetor enfraquece a cobertura. Programador pode criar um issue/proposta após esta story.
- **Mudança de copy depois de aceite.** Exige decisão do PO.
- **SEO meta tags** (description, og:image, og:title, twitter:card). EPIC-008 trata. Por enquanto só `<title>` é normativo.
- **robots.txt e sitemap.xml.** EPIC-008.

## Padrões de qualidade exigidos

- Cobertura ≥ 80% (gate da STORY-010, herdado).
- **Zero regressão** nos testes existentes do EPIC-001 + STORY-019 (Pest + Dusk).
- 100% tokens via CSS vars (gate herdado de STORY-019 CA-6).
- Nenhum hex literal de design system fora de `tokens.css`.

## Dependências

- **Bloqueada por:** STORY-019 (`done`) — precisa dos componentes `<x-logo>`, `<x-button>`, `<x-footer-version>`, do layout auth, dos tokens CSS e do design system v1. Sem isso, a landing não tem como ser construída no padrão.
- **Bloqueia:** STORY-020 (validação final do EPIC-004) só aceita o épico como `done` depois desta story estar `done`. Ajustar `validation/checklist.md` para incluir a inspeção da landing (PO atualiza).
- **Pré-requisitos de ambiente:** todos os anteriores; homologação `https://defonline.xandrix.com.br` funcionando.

## Decisões já tomadas

- **A página de debug `/` é removida nesta story, não em outra.** Ela bloqueia o uso público do domínio.
- **A landing reusa o shell `auth`** (header simples + footer simplificado), não o shell `app` (sidebar/drawer). Landing não tem nav interna.
- **Copy normativa** acima é a versão aceita pelo PO. Mudanças exigem nova rodada.
- **Sem rastreador de marketing** nesta story.
- **Sem links institucionais (Termo, Privacidade) no footer da landing** — eles ficam no fluxo de aceites quando Roberto for criar conta.
- **Mantemos `HelloWorldEmail` job + mail + flag** — útil como vetor de teste. Limpeza fica para depois.
- **`/health` e `/ready` permanecem como endpoints técnicos** — não viram links visíveis na landing.

## Liberdade técnica do agente

Você decide:

- **Tipo de definição da rota** (`Route::view`, closure, controller). Preferência: `Route::view('/', 'landing')->name('landing')`.
- **Tamanho do H1** — `--fs-h1` (40px) ou `--fs-display` (80px). Programador propõe; se for display, validar em mobile.
- **Posicionamento vertical** do bloco H1+subtítulo+CTAs (centro vertical absoluto vs ~30% do topo). Recomendação: ~30% do topo para deixar respiro visual em cima.
- **Mobile <480px** — se mantém o segundo CTA do header ou só `Entrar`.
- **Atributo `dusk`** nos elementos da landing. Os obrigatórios são `landing-cta-cadastro` e `landing-cta-login` (CA-10). Demais são livres.
- **Se vale a pena criar um `<x-landing-hero>`** como componente separado ou se a view inline já basta. Preferência do PO: view inline (é uma página só).

Você **não** decide:

- A copy (CA-4).
- A remoção dos 5 arquivos vs outros (CA-1).
- Manter Mailpit, request_id, healthcheck visíveis (CA-7 proíbe).
- Adicionar tracking (fora de escopo).
- Renomear ou remover `HelloWorldEmail` / `HelloWorldMessage` / `HelloWorldEmailHabilitado` (fora de escopo).

## Definição de Pronto (DoD)

- [ ] CA-1 a CA-12 passam.
- [ ] Pre-push verde (Pint + Larastan + Pest + Dusk + cobertura ≥ 80%).
- [ ] Pipeline CI verde (validate + build-and-push + deploy + smoke + notify).
- [ ] Deploy em homologação validado: visitar `https://defonline.xandrix.com.br/` em mobile real e em desktop; ver landing renderizada; clicar `Criar conta grátis` → vai para `/cadastro`; clicar `Já tenho conta` → vai para `/login`. Screenshot anexado (mobile + desktop).
- [ ] Página de debug não acessível em lugar nenhum (visitar `/` em homologação não mostra mais "hello DEFOnline").
- [ ] `index.json` `done`.
- [ ] "Notas do agente" preenchidas.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`. Story curta (S) — não exige IDR. Avisar PO no chat quando entrar em `in_review`.

## Notas do agente

### Decisões tomadas
- <data> — <decisão>

### Descobertas
- <data> — <gotcha>

### Bloqueios encontrados
- <data> — <bloqueio>

### Cobertura final
- Geral: <%>

### Arquivos removidos
- `app/resources/views/welcome.blade.php`
- `app/resources/views/livewire/hello-world.blade.php`
- `app/app/Livewire/HelloWorld.php`
- `app/tests/Feature/Livewire/HelloWorldTest.php`
- `app/tests/Browser/HelloWorldBrowserTest.php`

### Arquivos criados
- `app/resources/views/landing.blade.php`
- `app/tests/Feature/LandingTest.php` (ou nome equivalente)
- `app/tests/Browser/LandingBrowserTest.php`

### Links de evidência
- PR: <url>
- Pipeline: <url>
- Tag rc.N: <vX.Y.Z-rc.N>
- Screenshot landing mobile (360x800): <anexar>
- Screenshot landing desktop (1280x800): <anexar>
- Print: clicando `Criar conta grátis` vai para `/cadastro`: <anexar>
- Print: clicando `Já tenho conta` vai para `/login`: <anexar>
