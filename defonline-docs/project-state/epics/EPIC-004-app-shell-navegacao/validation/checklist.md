# Checklist de validação — EPIC-004 / STORY-020

> **Para o validador:** este checklist é o roteiro de inspeção final do épico EPIC-004 (App shell + materialização do design system v1 + landing pública simples). Ele complementa os CAs das STORY-019 e STORY-024 — não substitui. Cobre tanto verificações automáticas (testes/CI) quanto inspeção visual/funcional em homologação. Persona-alvo: Roberto no celular. Roda em **mobile (360x800)** e **desktop (1280x800)**.

Companion: `design/mock-shell.html` é o "design assinado" pelo PO para o shell autenticado — use como referência visual ao comparar com o que está em homologação.

---

## A. Shell global (CA-1, CA-2, CA-3, CA-4, CA-5, CA-17)

- [ ] Em todas as 4 rotas autenticadas (`/home`, `/empresas/nova`, `/empresas/{id}/show`) e nas 2 de auth (`/cadastro`, `/login`), o shell esperado aparece (autenticado: header + nav + main + footer institucional completo; auth: header simples + footer simplificado).
- [ ] Logo "D" (SVG inline) + wordmark "DEFOnline" visível em viewport ≥ 480px; só ícone em viewport < 480px.
- [ ] Pill `homol` (ou `local`) aparece no header colada ao logo em ambiente não-produção.
- [ ] Nome do primeiro nome do usuário aparece no header em viewport ≥ 640px; em < 640px, só avatar com inicial.
- [ ] Avatar com inicial do `primeiroNome()` em círculo `surface` com borda `border`.
- [ ] Dropdown "Conta" abre por clique e por teclado (Enter/Space); fecha com Escape, com clique fora ou com novo click no trigger.
- [ ] Sidebar fixa em ≥ 1024px (240px de largura, fundo `surface`, borda direita `border`).
- [ ] Drawer com hamburger em < 1024px; overlay escurece o conteúdo; fecha com clique no overlay, com Escape ou com novo click no hamburger.
- [ ] Item ativo da sidebar destacado com fundo `neutral` + texto/ícone `tertiary` + barra vertical 3px tertiary à esquerda; `aria-current="page"` correto.
- [ ] Itens disabled (Diagnósticos, Histórico, Conta) com `aria-disabled="true"`, `tabindex="-1"` e tooltip "Em breve — Onda 2" no hover/focus.
- [ ] Breadcrumb aparece em `/empresas/nova` e `/empresas/{id}/show` com formato `Minhas Empresas › ...`; **não** aparece em `/home`.
- [ ] `<title>` no formato `"{H1} · DEFOnline"` em todas as rotas autenticadas.

---

## B. Tokens, componentes e design system (CA-6, CA-7, CA-11)

- [ ] `tokens.css` (ou equivalente) é o único arquivo com hex literal do design system.
- [ ] Grep `#[0-9a-fA-F]{6}` em `resources/views/**` e `resources/css/**` (exceto `tokens.css`) retorna zero ocorrências de cores do design system.
- [ ] Componentes `<x-button variant="primary|secondary|ghost">`, `<x-card>`, `<x-input>`, `<x-label>`, `<x-link>` existem e são usados em todas as views refatoradas.
- [ ] `<x-logo>` existe e é usado no header.
- [ ] `<x-breadcrumb>` existe e é usado nas telas internas.
- [ ] `<x-footer-version>` existe e renderiza no formato `v{version}` ou `v{version} · {env}`.
- [ ] `especificacao/V2/design-system.md` promovido para v1, com seção "Histórico" atualizada.
- [ ] Inter (300/400/500/600) carregada e aplicada; nenhum peso 700+ em qualquer arquivo.

---

## C. Bug fixes e comportamentos novos (CA-14, CA-15, CA-16, CA-18, CA-19)

- [ ] **Adicionar empresa sempre disponível em `/home`:** com 0, 1 ou 2+ empresas, o botão `[+ Adicionar empresa]` aparece no header da seção e leva a `/empresas/nova`. Teste manual: criar conta nova → cadastrar 1ª empresa → voltar para `/home` → clicar `[+ Adicionar empresa]` → cadastrar 2ª empresa → voltar para `/home` com 2 cards. **Sem digitar URL.**
- [ ] **Logo "D":** versão default (sobre `surface`) e versão dark (sobre `primary`) renderizam conforme `design/logo.html`. Escala bem em 24/32/48px.
- [ ] **Versão no footer:** `v{X.Y.Z}` em produção; `v{X.Y.Z} · homol` em homologação. Cor `secondary`, peso 400, tamanho body-sm. Canto direito do footer. Sem link.
- [ ] **Cancelar em `/empresas/nova`:** botão `Cancelar` (secondary) presente; click navega para `/home` sem persistir. No desktop ao lado do `Cadastrar empresa`, primary à direita; no mobile abaixo dele.
- [ ] **Voltar em `/empresas/{id}/show`:** botão `Voltar para Minhas Empresas` (secondary com ícone seta-esquerda) substitui o `<a class="botao botao--ativo">` cru. Click leva a `/home`.
- [ ] **Cancelar e Voltar nunca juntos** em qualquer tela.
- [ ] **Logout:** dropdown Conta → click `Sair` → redirect para `/login` com flash de sucesso.

---

## D. Responsividade (CA-9)

Validar em **360x800** (mobile) e **1280x800** (desktop):

- [ ] Em mobile, nenhuma tela apresenta scroll horizontal.
- [ ] Hamburger visível no mobile, oculto no desktop.
- [ ] Sidebar fixa visível no desktop, oculta no mobile.
- [ ] Botões e inputs com touch target ≥ 44×44px.
- [ ] Em mobile, CTAs em forms ocupam largura 100% e ficam empilhados (primary em cima, Cancelar embaixo via `column-reverse`).
- [ ] Em mobile, drawer fecha com clique no overlay e com Escape.
- [ ] Em mobile, dropdown Conta abre, é tappable e fecha por tap fora.

---

## E. Acessibilidade (CA-10)

- [ ] Foco visível em todos elementos interativos (focus ring 2px tertiary @ 40% alpha).
- [ ] Semântica correta: `<header>`, `<nav aria-label="...">`, `<main>`, `<footer>`.
- [ ] Itens disabled com `aria-disabled="true"` e não-focalizáveis via Tab (`tabindex="-1"`).
- [ ] Dropdown "Conta" com `aria-haspopup="menu"` e `aria-expanded` sincronizado.
- [ ] Drawer mobile com `aria-controls` no hamburger e `aria-expanded` sincronizado.
- [ ] `aria-current="page"` no item ativo da sidebar e no último item do breadcrumb.
- [ ] Contraste textual passa AA (tertiary sobre surface ≥ 4.5:1; primary sobre surface AAA).

---

## F. Drift de paleta corrigido (CA-8)

- [ ] Busca `grep -r "#1f2937\|#2563eb\|#f9fafb\|#1d4ed8\|#3730a3\|#dbeafe" resources/views/` retorna zero ocorrências.
- [ ] Captura de tela de `/home`, `/login`, `/cadastro`, `/empresas/nova`, `/empresas/{id}/show` em desktop e mobile com cores do design system oficial.

---

## G. Testes e qualidade (CA-13)

- [ ] Cobertura ≥ 80% (gate da STORY-010).
- [ ] Pest + Dusk verdes (incluindo testes novos: shell presente em cada rota, item ativo correto, drawer abre/fecha, Cancelar/Voltar funcionam, Adicionar empresa em todos os estados).
- [ ] Teste arquitetural de "nenhum hex fora de tokens.css" passa.
- [ ] Pipeline CI verde (validate + build-and-push + deploy + smoke + notify).

---

## H1. Landing pública `/` (STORY-024)

- [ ] GET `https://defonline.xandrix.com.br/` mostra a landing nova, **não** a página "hello DEFOnline".
- [ ] H1 exato: `Diagnóstico estratégico para sua empresa.` (com ponto final).
- [ ] Subtítulo exato: `Respostas claras sobre como sua indústria está em 14 indicadores essenciais — em minutos, sem consultor caro, sem planilha.`.
- [ ] `<title>` do documento: `DEFOnline — diagnóstico estratégico para sua empresa`.
- [ ] CTA `Criar conta grátis` (primary) leva para `/cadastro`. `dusk="landing-cta-cadastro"` presente.
- [ ] CTA `Já tenho conta` (secondary) leva para `/login`. `dusk="landing-cta-login"` presente.
- [ ] Header da landing mostra logo "D" + wordmark "DEFOnline" + links `Entrar` e `Criar conta` no canto direito.
- [ ] Footer da landing mostra `© DEFOnline 2026` à esquerda e versão à direita (`v0.4.2 · homol` em homologação).
- [ ] Página **não** menciona: "hello", "Healthcheck", "Mailpit", "request_id", "STORY-", "Phase", "Foundation técnica", "Disparar e-mail de teste".
- [ ] Página renderiza sem scroll horizontal em 360x800 e em 1280x800.
- [ ] Arquivos removidos (confirmar via `git log` ou inspeção): `welcome.blade.php`, `livewire/hello-world.blade.php`, `App\Livewire\HelloWorld`, `HelloWorldTest`, `HelloWorldBrowserTest`.
- [ ] `RequestIdPropagationTest`, `HelloWorldEmailTest`, `HelloWorldMessageTest` **continuam passando** (job/mail preservados intencionalmente).
- [ ] Smoke Dusk `LandingBrowserTest` no group `smoke` passa no pipeline `release-homolog.yml`.
- [ ] CI smoke HTTP (`curl /health` + `curl /ready`) continua verde — endpoints técnicos não foram tocados.

## H. Fluxo end-to-end em homologação (smoke manual)

Roteiro do validador, mobile real (≤ 5 min):

0. [ ] Abrir `/` → ver landing nova (logo + tagline + CTAs + versão).
1. [ ] Abrir `/cadastro` → criar conta nova → submeter.
2. [ ] Receber email → clicar link → confirmar email.
3. [ ] Login com nova conta.
4. [ ] Em `/home` (vazio) → ver empty state + 2 CTAs (header `Adicionar empresa` + inline `Cadastrar primeira empresa`).
5. [ ] Clicar inline `Cadastrar primeira empresa` → `/empresas/nova`.
6. [ ] Preencher CNPJ (use o de teste) → consultar Receita → cadastrar.
7. [ ] Voltar para `/home` → ver 1 card de empresa + botão `Adicionar empresa` ainda visível no header da seção.
8. [ ] Clicar `Adicionar empresa` no header → `/empresas/nova` (sem digitar URL).
9. [ ] Preencher dados manualmente (sem RFB) → cadastrar.
10. [ ] Voltar para `/home` → ver 2 cards.
11. [ ] Abrir um card → `/empresas/{id}/show` → conferir breadcrumb e botão `Voltar para Minhas Empresas`.
12. [ ] Clicar `Voltar para Minhas Empresas` → voltar para `/home`.
13. [ ] Abrir hamburger → ver sidebar → fechar com Escape.
14. [ ] Dropdown Conta → Sair → cair em `/login` com flash.
15. [ ] Anexar screenshots: shell mobile, shell desktop, drawer aberto, dropdown aberto, `/home` com empresas (botão `Adicionar` visível).

---

## I. Decisões abertas que precisam ser resolvidas até a aprovação

Lista de `design/ux-specs.md §13` — confirmar que foram resolvidas durante a STORY-019 (ver "Decisões já tomadas" na story atualizada). Se aparecer divergência entre código e decisão registrada, reportar.

---

## Resultado da validação

- [ ] **Aprovado** — todos os itens acima passam. Marcar EPIC-004 como `done` no `index.json`. Notificar PO no chat.
- [ ] **Reprovado** — itens reprovados anotados em `validation/report.md` com prints. Story volta para `in_progress` com lista de correções.
