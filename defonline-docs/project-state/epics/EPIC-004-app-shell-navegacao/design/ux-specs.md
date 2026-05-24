# UX Specs — App Shell DEFOnline (EPIC-004 / STORY-019)

Especificação consolidada de UX para o agente programador implementar o app shell. Complementa `epic.md` e `STORY-019` — não substitui. Persona-alvo: Roberto (~50 anos, dono de marcenaria, digitando no celular). Princípio: mobile-first, flat, single-accent (`#635BFF`), Inter 300/400/500/600.

Companion files na mesma pasta:
- `mock-shell.html` — protótipo navegável com toggle entre as 6 telas-foco.
- `logo.svg` — logomarca "D" + acento tertiary.
- `logo.html` — logo em 3 contextos / 3 tamanhos.

---

## 1. Inventário das telas

| Rota | `<title>` | H1 página | Breadcrumb | CTA primário (header da seção) | Ações secundárias |
|---|---|---|---|---|---|
| `/cadastro` | "Criar conta · DEFOnline" | "Criar conta" | — (auth, sem shell completo) | `Criar conta` (submit, primary) | "Entrar" no header (ghost-sm); link "Entrar" abaixo do form |
| `/login` | "Entrar · DEFOnline" | "Entrar" | — (auth, sem shell completo) | `Entrar` (submit, primary) | "Criar conta" no header (ghost-sm); link "Reenviar email"; link "Criar conta" abaixo do form |
| `/home` (vazio) | "Minhas Empresas · DEFOnline" | "Minhas Empresas" | — (raiz) | `Adicionar empresa` (primary, no header da seção) + `Cadastrar primeira empresa` (primary, dentro do empty state) | nenhuma |
| `/home` (≥1 empresa) | "Minhas Empresas · DEFOnline" | "Minhas Empresas" | — (raiz) | `Adicionar empresa` (primary, **sempre visível** no header da seção — corrige bug) | Por card: `Ver detalhes` (secondary-sm), `Iniciar diagnóstico` (ghost-sm, disabled, tooltip "Em breve") |
| `/empresas/nova` | "Cadastrar empresa · DEFOnline" | "Cadastrar empresa" | Minhas Empresas › Nova empresa | `Cadastrar empresa` (primary, dentro do form) | `Cancelar` (secondary, ao lado esquerdo no desktop; abaixo no mobile); `Consultar Receita` (secondary-sm, contextual) |
| `/empresas/{id}/show` | "{razão social} · DEFOnline" | "{razão social ou fantasia}" | Minhas Empresas › {nome} | `Iniciar diagnóstico` (primary, disabled, tooltip "Em breve") | `Voltar para Minhas Empresas` (secondary, com ícone seta-esquerda, ao final do conteúdo) |

Notas:
- O CTA primário aparece **uma vez por tela**. Em `/home (com empresas)` é "Adicionar empresa"; em `/empresas/{id}/show` é "Iniciar diagnóstico" (mesmo desabilitado — ocupa o slot e prepara o usuário para Onda 2).
- O título da página vai também no `<title>` do documento (padrão "Nome da página · DEFOnline").

---

## 2. Política de botões — Cancelar / Voltar

Regra de ouro: **nunca os dois ao mesmo tempo na mesma tela.**

### Quando usar "Cancelar"
- Em **formulários de criação ou edição**. Sinaliza "descartar o que estou digitando e sair sem salvar".
- Aparece **ao lado** do CTA primário do form (`Cadastrar empresa`, `Salvar`, etc.).
- Variant: `btn--secondary`.
- Destino: tela "pai" definida pelo fluxo (`/home` para `/empresas/nova`).
- Confirmação modal `Descartar alterações?` **fica fora desta story**. Por enquanto, Cancelar simplesmente navega para o pai.

### Quando usar "Voltar"
- Em **telas de detalhe / leitura** onde há contexto/breadcrumb claro pai. Sinaliza "saí do contexto, quero voltar".
- Aparece **uma vez** na tela, ao final do conteúdo ou colado ao CTA primário (à esquerda no desktop, abaixo no mobile).
- Variant: `btn--secondary` com ícone seta-esquerda.
- Texto preferencial: `Voltar para {nome do pai}` ("Voltar para Minhas Empresas"). Mais informativo que só "Voltar".
- Em `/empresas/{id}/show` o botão Voltar **complementa** o breadcrumb (que também volta para `Minhas Empresas`) — redundância intencional, porque Roberto no celular não percebe o breadcrumb tão facilmente.

### Posicionamento
- **Desktop ≥ 1024px:** Cancelar/Voltar fica à **esquerda** do CTA primário, na mesma linha (`form-actions` com `justify-content: flex-end`).
- **Mobile < 1024px:** Cancelar/Voltar fica **abaixo** do CTA primário (`flex-direction: column-reverse` — CTA primário no topo do bloco, secundário embaixo). Largura 100%.
- Ordem visual no desktop: `[Cancelar] [CTA Primário]` — primário sempre por último (canto direito), seguindo padrão Stripe/Linear.

### Don'ts
- Nunca `[Voltar] [Cancelar]` juntos.
- Nunca botão "Sair" como secundário do form (Sair é só no dropdown Conta).
- Nunca CTA secundário com cor tertiary — single-accent quebraria.

---

## 3. Política de identificação da tela ativa

Roberto precisa **sempre** saber em que tela está. Quatro sinais redundantes:

1. **Sidebar/drawer:** item correspondente recebe `.is-active`:
   - `background: var(--color-neutral)`
   - `color: var(--color-tertiary)` (texto e ícone)
   - barra vertical de 3px à esquerda em tertiary (indicação ativa única na tela — não conta como segundo acento porque é parte do estado de navegação)
   - `aria-current="page"`
2. **Breadcrumb:** último item com `aria-current="page"`, sem link, cor `primary`.
3. **H1 da página:** uma única `<h1>` por view, com o mesmo texto do item de menu (ou do nome da entidade).
4. **`<title>` do documento:** `"{H1 da página} · DEFOnline"`.

Determinação do item ativo na sidebar pelo Laravel: `request()->routeIs('home')` para Minhas Empresas; `request()->routeIs('empresas.nova')` para Adicionar Empresa; `request()->routeIs('empresas.show')` mantém Minhas Empresas ativo (é navegação contextual dentro do mesmo "ramo" do menu).

---

## 4. Política de versão (footer)

- **Localização:** canto **direito** do footer (lado oposto aos links institucionais).
- **Formato:**
  - Produção: `v0.4.2`
  - Não-produção: `v0.4.2 · homol` (com middle-dot `·` separando ambiente)
- **Tipografia:** `font-weight: 400`, cor `var(--color-secondary)` (`#425466`), tamanho `var(--fs-body-sm)` (14px).
- **Sem link.** É metadado, não ação.
- Versão vem da config `app.version` (já existe desde STORY-007 do EPIC-000) — extrair para `<x-footer-version>` parcial reutilizável.

Footer completo (autenticado):
```
[Termo de Adesão]  [Política de Privacidade]  © DEFOnline 2026          v0.4.2 · homol
```

Footer simplificado (auth pages — cadastro/login antes de logar):
```
© DEFOnline 2026                                                          v0.4.2 · homol
```

---

## 5. Estados de empresa na `/home`

Três estados visuais. **Em todos os três, o botão `Adicionar empresa` está visível no header da seção** — esta é a correção explícita do bug atual onde o botão sumia quando havia empresas.

### Estado vazio (0 empresas)
- Card com empty state ilustrado (ícone de pasta vazia, dentro de círculo neutro).
- Título: "Nenhuma empresa cadastrada ainda".
- Caption: "Você precisa cadastrar pelo menos uma empresa para usar o diagnóstico."
- CTA dentro do empty state: `Cadastrar primeira empresa` (primary).
- CTA no header da seção: `Adicionar empresa` (primary, redundante mas presente — o usuário com 0 empresas verá o do empty state primeiro; o do header serve para consistência visual com os outros estados).

### Estado 1 empresa
- Header da seção com h1 "Minhas Empresas" + subtitle "Você tem 1 empresa cadastrada." + `Adicionar empresa` no canto direito.
- Grid de 1 card de empresa ocupa coluna completa em mobile e meia-coluna em desktop (≥ 768px). Quando há só 1 card, ele ocupa coluna única à esquerda (não centraliza — manter alinhamento à esquerda evita "card flutuando").

### Estado 2+ empresas
- Igual ao estado 1, mas grid de 2 colunas em ≥ 768px.
- Subtitle: "Você tem N empresas cadastradas."
- **CTA `Adicionar empresa` permanece no header da seção, sempre visível.** Mesma posição, mesmo tratamento.

### Card de empresa (componente)
- Nome (h3, weight 500) + pill de fonte (`RFB` ou `Manual`).
- Documento mascarado (body-sm, color secondary).
- Município / UF (body-sm, color secondary).
- Ações: `Ver detalhes` (secondary-sm) + `Iniciar diagnóstico` (ghost-sm, disabled com tooltip).

---

## 6. Tooltips de itens disabled

Texto único e curto. Não usar emoji.

| Elemento | Tooltip |
|---|---|
| Item de menu **Diagnósticos** | `Em breve — Onda 2` |
| Item de menu **Histórico** | `Em breve — Onda 2` |
| Item de menu **Conta** | `Em breve — Onda 2` |
| Item de dropdown **Editar perfil** | `Em breve — Onda 2` |
| Botão **Iniciar diagnóstico** (no card e no show) | `Em breve — Onda 2` |

Observações:
- Mantemos `Onda 2` (e não `EPIC-002`) porque a granularidade de épico é vocabulário interno; "Onda 2" é o termo que aparece no roadmap público do produto e Roberto reconhece (mesmo que vagamente).
- Implementação acessível: `aria-disabled="true"` + `title="Em breve — Onda 2"` no elemento. Tooltip CSS-only via `::after` é aceitável (no mock usamos `<span class="tooltip">`).
- Tooltip aparece em hover (desktop) e focus (teclado). No mobile, o `title` do HTML serve como fallback após long-press.

---

## 7. Breakpoints e layout

| Faixa | Comportamento da nav | Sidebar/drawer | Header |
|---|---|---|---|
| `< 1024px` (mobile/tablet) | Drawer off-canvas | Oculta por padrão. Acionada pelo hamburger no header. Overlay escurece o conteúdo. Largura 280px (max 80vw). Slide-in 200ms. Fecha com hamburger, com Escape ou com clique no overlay. | Hamburger visível. Logo só ícone "D" (≤ 480px) ou ícone + wordmark (≥ 480px). Nome do usuário oculto (≤ 640px) ou visível (≥ 640px). |
| `≥ 1024px` (desktop) | Sidebar fixa | `width: 240px`, fundo `var(--color-surface)`, borda direita `1px solid var(--color-border)`. Padding `var(--space-md) var(--space-sm)`. Itens com altura mínima 40px. | Hamburger oculto. Logo + wordmark "DEFOnline". Nome do usuário visível. |

Container do main: `max-width: 960px` (default), `640px` para formulários (`container--mid`), `480px` para auth (`container--narrow`).

Touch targets: todos os botões e inputs com altura mínima **44px** (CSS `min-height: 44px` na classe `.btn` e `.input`).

---

## 8. Acessibilidade

Alvo: WCAG 2.1 AA básico.

- **Semântica:** `<header>`, `<nav aria-label="...">`, `<main>`, `<footer>`. `<nav aria-label="breadcrumb">` para breadcrumbs. `<nav aria-label="Navegação principal">` para a sidebar.
- **Foco visível:** focus ring `2px var(--color-ring)` (tertiary @ 40% alpha) com `outline-offset: 2px` em todos os elementos interativos. Não usar `outline: none` sem substituto.
- **`aria-current="page"`** no item de menu ativo e no último item do breadcrumb.
- **Itens disabled:** `aria-disabled="true"` + `tabindex="-1"` (não recebem foco no Tab) + `title="..."` para fallback de tooltip.
- **Dropdown "Conta":** `aria-haspopup="menu"`, `aria-expanded` sincronizado com estado. `<div role="menu">` contendo `<button role="menuitem">`. Abre por click ou Enter/Space. Fecha com Escape ou clique fora.
- **Drawer mobile:** `aria-controls` no hamburger apontando para `id` da `<nav>`. `aria-expanded` no hamburger. Fecha com Escape.
- **Contraste:** primary `#0A2540` sobre surface `#FFFFFF` → 14.7:1 (passa AAA). Secondary `#425466` sobre surface → 7.3:1 (passa AAA). Tertiary `#635BFF` sobre surface → 4.5:1 (passa AA para texto normal).
- **`<title>` único e descritivo** por tela.
- **`<label>` associada a cada input via `for`/`id`.**
- **Skip link** ("Pular para o conteúdo") **fora de escopo desta story** — backlog futuro.

---

## 9. Loading states (observação)

Fora do escopo de implementação obrigatória desta story, mas registrado para futuro:

- **Lista de empresas:** quando o EPIC-003 ou paginação chegar, usar skeleton screens leves (`background: var(--color-neutral)` + animação de pulso 1.5s `opacity 0.6 → 1`). Manter altura do card original para evitar layout shift.
- **Consulta RFB:** já existe (`wire:loading` no botão "Consultar Receita") — manter o padrão atual de mudar o texto do botão para "Consultando…" e desabilitar.
- **Navegação entre telas (Livewire `wire:navigate`):** barra de progresso no topo (1px, cor tertiary) — o próprio Livewire 3+ entrega isso por padrão; basta não desabilitar.
- **Submissão de form:** durante submit, CTA primário troca para "Cadastrando…" e desabilita; Cancelar permanece habilitado.

---

## 10. Don'ts visuais (consolidado)

Reforço de `design-system.md` aplicado ao shell:

- **Sem gradientes.** Nem mesmo sutis. Fundo é sólido (`surface` ou `neutral`).
- **Sem `font-weight: 700` ou maior.** Hierarquia sai do tamanho + cor, não do peso.
- **Sem segundo acento.** `#635BFF` é o único acento interativo. Faróis (verde/amarelo/vermelho) só entram no EPIC-002 e ficam confinados a badges/dots semânticos — nunca em backgrounds grandes.
- **Sem sombras pretas saturadas.** Usar `var(--shadow-sm)` ou `var(--shadow-md)`.
- **Sem hex literal de design system fora de `tokens.css`.** Teste arquitetural (CA-6 / STORY-019) reprova.
- **Sem mais de uma `<h1>` por tela.**
- **Sem texto em caixa alta longo** (mais de 3 palavras). `text-transform: uppercase` reservado para labels curtas (chips, headers de seção do menu, eyebrow).
- **Sem ícones decorativos sem `aria-hidden="true"`** ou `role="img" + aria-label`.
- **Sem CTA secundário com cor primária.** Botão "Cancelar" / "Voltar" é sempre `secondary` (surface + borda) ou `ghost`.
- **Sem ícone à esquerda do texto em CTA principal** a menos que adicione significado (ex.: "+" em "Adicionar empresa" é OK; ícone decorativo em "Entrar" não é).

---

## 11. Mapeamento para implementação (sugestão)

Para o agente programador. Não normativo — referência.

```
resources/
├── css/
│   └── tokens.css                          # CA-6: única fonte de hex
├── views/
│   ├── components/
│   │   ├── layouts/
│   │   │   ├── app.blade.php               # shell autenticado (header + nav + main + footer)
│   │   │   └── auth.blade.php              # shell para /cadastro e /login (header simples + footer simples)
│   │   ├── app-header.blade.php
│   │   ├── app-nav.blade.php               # sidebar + drawer (Alpine para toggle)
│   │   ├── app-footer.blade.php
│   │   ├── breadcrumb.blade.php            # aceita prop $items
│   │   ├── page-header.blade.php           # title + subtitle + slot de ações
│   │   ├── button.blade.php                # variant=primary|secondary|ghost, size=md|sm
│   │   ├── card.blade.php
│   │   ├── input.blade.php
│   │   ├── label.blade.php
│   │   ├── link.blade.php
│   │   └── logo.blade.php                  # SVG inline (versão light e dark)
│   └── livewire/                           # views Livewire refatoradas
```

Itens de menu como array central (ex.: `config/nav.php` ou `App\Support\Navigation::items()`) — facilita adicionar/desabilitar itens sem caçar em views.

---

## 12. Aceite UX (checklist para o validador)

Espelho dos CAs da STORY-019 do ponto de vista de UX. O validador pode usar como roteiro de inspeção visual.

- [ ] Em todas as 4 rotas autenticadas, o shell aparece (header + nav + footer).
- [ ] No mobile (360x800), o hamburger abre o drawer; clicar no overlay ou Escape fecha.
- [ ] No desktop (1280x800), a sidebar fica fixa e o hamburger some.
- [ ] O item ativo da sidebar destaca em tertiary com a barra vertical à esquerda.
- [ ] O breadcrumb aparece em `/empresas/nova` e em `/empresas/{id}/show` mas **não** em `/home`.
- [ ] Em `/home` com empresas, o botão `Adicionar empresa` está visível no header da seção (corrige bug).
- [ ] Em `/empresas/nova`, há botão `Cancelar` ao lado de `Cadastrar empresa`.
- [ ] Em `/empresas/{id}/show`, há botão `Voltar para Minhas Empresas`.
- [ ] Pill `homol` aparece no header em ambiente não-prod.
- [ ] Footer mostra `v0.4.2 · homol` no canto direito e links de Termo/Privacidade + © no canto esquerdo.
- [ ] Itens disabled (Diagnósticos, Histórico, Conta) mostram tooltip "Em breve — Onda 2" no hover.
- [ ] Nenhum hex literal de design system fora de `tokens.css` (busca `grep`).
- [ ] Foco visível em todos os botões e inputs.

---

## 13. Ambiguidades em aberto (para o PO decidir)

Lista curta — não bloqueia a STORY-019, mas precisa de decisão antes do EPIC-002 começar:

1. **Logo wordmark "DEFOnline" no header dark do hotsite (EPIC-008):** mantemos o ícone "D" em primary `#0A2540`? Em fundo escuro o ícone ficaria invisível — proposta no mock é virar o "D" em `#FFFFFF` mantendo o ponto tertiary. Confirmar.
2. **Texto exato dos tooltips em PT-BR:** "Em breve — Onda 2" é claro o suficiente? Alternativa testada: "Disponível em breve". PO escolhe.
3. **Botão "Iniciar diagnóstico" no `/empresas/{id}/show`:** ocupa o slot do CTA primário mesmo desabilitado. Alternativa: nenhum CTA primário até EPIC-002 entregar. Mantemos como está (mostrar o futuro) ou esconder?
4. **`Voltar para Minhas Empresas` vs `Voltar`:** preferimos o texto longo (mais informativo, recomendação UX). Confirmar.
5. **Pill de ambiente `homol`:** posição atual é colada ao logo. Alternativa: canto direito do header, antes do dropdown. PO escolhe.
6. **Avatar com iniciais no header (mock usa "R" para Roberto):** mantemos? Custa pouco e dá identidade. Alternativa: só nome, sem avatar.
