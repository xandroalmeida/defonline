---
idr_id: IDR-008
slug: framework-css-tailwind-v4-theme
title: Framework CSS — Tailwind v4 com @theme materializando os tokens do design-system
status: accepted
decided_at: 2026-05-25
decided_by: PO (Alexandro)
owner_agent: programador (claude-opus-4-7)
related_story: STORY-019
related_adrs: ["ADR-001"]
related_idrs: []
supersedes: null
superseded_by: null
created_at: 2026-05-25
updated_at: 2026-05-25
---

# IDR-008 — Framework CSS: Tailwind v4 com `@theme` materializando os tokens do design-system

## Contexto

A STORY-019 (EPIC-004) entrega o app shell e a primeira materialização em código do `defonline-docs/especificacao/V2/design-system.md` (hoje em status `alpha`). A spec exige:

- Uma fonte única de tokens (CA-6: nenhum hex literal de design system fora de `tokens.css`).
- Componentes-base Blade (`<x-button>`, `<x-card>`, `<x-input>`, `<x-label>`, `<x-link>`, `<x-logo>`, `<x-breadcrumb>`, `<x-footer-version>` — CA-7).
- Mobile-first com breakpoint único declarado em `design/ux-specs.md §7` (`< 1024px` mobile/drawer, `≥ 1024px` desktop/sidebar fixa).
- Sem gradientes, sem segundo acento, sem peso 700 (`design-system.md` §Filosofia / §Don'ts).

A stack base do projeto já vem do scaffold Laravel 13 com Tailwind v4 (`app/package.json` lista `@tailwindcss/vite ^4.0.0`, `tailwindcss ^4.0.0`; `app/vite.config.js` registra o plugin; `app/resources/css/app.css` faz `@import 'tailwindcss'` e define `@theme { --font-sans: 'Instrument Sans' }`). A escolha do framework CSS — antes virgem — é formalizada agora porque a partir desta story toda nova tela depende da decisão.

## Decisão

> **Adotamos Tailwind v4 como framework CSS do projeto, com os tokens do design-system declarados dentro de um único bloco `@theme` em `resources/css/tokens.css`. Esse arquivo é a única fonte autorizada de hex literais de design system.**

Concretamente:

- `resources/css/tokens.css` declara `@theme { --color-primary: #0A2540; --color-secondary: #425466; ... --spacing-md: 16px; ... }` para todos os tokens (cores, espaçamento, raios, sombras, tipografia) listados em `design-system.md §Tokens`.
- `resources/css/app.css` faz `@import 'tailwindcss'; @import './tokens.css';` e nada mais — sem hex literais.
- Componentes Blade consomem os tokens via classes utilitárias do Tailwind v4 (`bg-tertiary`, `text-on-primary`, `rounded-md`, etc. — geradas automaticamente a partir do `@theme`) ou via `var(--color-tertiary)` em CSS custom dentro de `@layer components` quando preciso.
- A fonte `Instrument Sans` do scaffold é substituída por `Inter` (300/400/500/600) — exigência explícita do design-system §Tipografia.
- Teste arquitetural Pest (`tests/Arch/DesignTokensTest.php`) grep em `resources/views/**/*.blade.php` e `resources/css/**/*.css` (exceto `tokens.css`) por hex de design system (`#0A2540|#425466|#635BFF|#F6F9FC|#E3E8EE|#1F2937|#2563EB|#F9FAFB` etc.) — falha se encontrar.

## Por quê

- **Aproveita stack já instalada.** O scaffold Laravel 13 entregou Tailwind v4 com Vite plugin pronto. Não há custo de bring-up; remover Tailwind para usar CSS vanilla seria desinstalar algo que já roda.
- **`@theme` do Tailwind v4 satisfaz CA-6 nativamente.** Os tokens declarados em `@theme` viram simultaneamente (a) CSS custom properties (`var(--color-tertiary)`) consumíveis por qualquer regra CSS, e (b) classes utilitárias do Tailwind (`bg-tertiary`, `text-tertiary`, etc.) com tipagem implícita. Uma única declaração serve aos dois mundos — o hex literal vive em um lugar só, exatamente como CA-6 exige.
- **Single accent + flat design são amigáveis ao Tailwind.** A regra "Tertiary é o único acento interativo" do design-system se traduz naturalmente em uma classe utilitária restrita; o teste arquitetural ajuda a manter o gate. Sem gradientes nem efeitos elaborados, o trade-off "estética customizada vs. utility-first" desaparece: o design é simples o bastante para que utilities resolvam.
- **Curva de aprendizagem zero para o time.** O time é 1 pessoa + agentes Claude. Tailwind v4 é vocabulário amplamente conhecido pelos agentes, e o PO já optou pelo scaffold com Tailwind no momento da inicialização do projeto (decisão implícita do ADR-001 ao escolher Laravel 13 starter). Reverter para CSS vanilla seria custo gratuito.
- **Bundle final permanece pequeno.** Tailwind v4 com JIT só inclui as utilities efetivamente usadas; o output em produção fica na casa de dezenas de KB. Para um produto MVP isso é irrelevante.
- **Compatível com Livewire 4 e Blade Components.** Sem fricção de integração — Livewire emite HTML padrão que recebe as classes utilitárias normalmente.

## Alternativas consideradas

- **CSS vanilla puro com vars (sem Tailwind).** Descartado. Resolveria o objetivo arquitetural com simplicidade conceitual (CSS vars + classes próprias), mas exigiria: (a) desinstalar Tailwind do scaffold, (b) reescrever todo o sistema de utilities (`flex`, `grid`, `gap-*`, breakpoints responsivos `md:`, `lg:`), (c) custo recorrente em cada nova tela para autorizar utilities. Para 1 pessoa de equipe, o custo de oportunidade é alto e o ganho (independência de framework) é teórico — Tailwind v4 só lê tokens de `@theme` e não impõe magia escondida, então a "saída" depois é viável sem grande lock-in.

- **Híbrido (Tailwind para layout/responsividade + CSS próprio para componentes em `@layer components`).** Descartado como obrigatório, aceito como técnica pontual. Acrescenta uma decisão "quando usar Tailwind direto vs. quando criar classe própria" que vira fonte de inconsistência. Em vez disso, a decisão é: padrão é Tailwind direto; `@layer components` é usado **somente** quando a combinação de utilities ficar repetida em ≥3 lugares e prejudicar legibilidade (ex.: focus ring acessível combinado, animação do drawer). Isso captura a flexibilidade sem virar regra dupla.

- **Bootstrap 5 / outras libs de componentes (Flowbite, Mantine, etc.).** Descartadas sem aprofundar. Trazem estética opinada incompatível com a paleta Stripe-like do design-system; adicionariam dependência grande para resolver problema pequeno.

## Consequências

### Para outros agentes
- **Padrão default para CSS no projeto:** Tailwind v4 com tokens em `@theme` dentro de `resources/css/tokens.css`. Toda nova view ou componente consome tokens via classes utilitárias geradas a partir do `@theme` (`bg-primary`, `text-secondary`, `p-md`, `rounded-lg`, etc.) ou via `var(--*)` quando preciso.
- **Nenhum hex literal de design system fora de `tokens.css`.** Teste arquitetural Pest reprova. Faróis semânticos (verde/amarelo/vermelho do EPIC-002) ficam em escopo separado (`tokens.css` + componente dedicado ao semáforo do relatório) — mesma regra.
- **Componentes Blade reutilizáveis:** padrão é Blade Component (anonymous) em `resources/views/components/`. Class-based component só quando há lógica não-trivial (ex.: cálculo de estado ativo do menu).
- **Atalhos próprios em `@layer components`** ficam reservados para casos com ≥3 usos repetidos que comprometem legibilidade. Documentar com comentário curto explicando o trigger.
- **Não adicionar outra lib de UI (Flowbite, DaisyUI, Headless UI etc.)** sem nova IDR. Mantém o design coerente com a estética flat do design-system.

### Para o projeto
- **Sem novas dependências.** Tailwind v4 e `@tailwindcss/vite` já estão em `package.json`. Apenas o `vite.config.js` ajusta a fonte (Bunny `Inter` em vez de `Instrument Sans`).
- **Bundle final muda pouco** (Tailwind JIT só compila utilities usadas). Esperado <50KB de CSS em prod.
- **Trabalho de migração da fonte:** trocar `bunny('Instrument Sans', ...)` por `bunny('Inter', { weights: [300, 400, 500, 600] })` em `vite.config.js`, atualizar `--font-sans` em `tokens.css`.

### Trade-offs aceitos
- **Acoplamento ao Tailwind.** Mitigado pelo fato de os tokens viverem em CSS vars (`var(--color-*)`), portáveis para qualquer framework — uma migração futura para CSS vanilla ou Lightning CSS preserva 100% dos tokens; só o sistema de utilities é reescrito.
- **Verbosidade de classes em alguns componentes.** Aceito como custo de utility-first. Mitigado pelo padrão "extrair para `@layer components` quando houver ≥3 usos repetidos".
- **Visibilidade reduzida das classes em PRs maiores.** Mitigado pelo pre-push (Pint formata HTML) e pela revisão visual (Dusk screenshots em CI).

## Como verificar

- **Teste arquitetural Pest** (`tests/Arch/DesignTokensTest.php`) que grep em `resources/views/**/*.blade.php` por hex de design system (`#0A2540|#425466|#635BFF|#F6F9FC|#FFFFFF|#E3E8EE|#1F2937|#2563EB|#F9FAFB`) e falha se encontrar fora de `tokens.css`. Idem para `resources/css/**/*.css` exceto `tokens.css`.
- **Inspeção visual** das telas refatoradas em homologação após deploy da rc — paleta Stripe-like, fonte Inter, sem drift.
- **Build de produção** (`npm run build`) sem erros e bundle CSS resultante < 100KB.
- **Sinais de revisão (quando reabrir esta IDR):**
  1. Aparecer necessidade de design não-flat (gradientes, animações elaboradas) que justifique outro framework — `design-system.md` evolve primeiro.
  2. Tailwind v4 mudar o mecanismo `@theme` de forma adversa em release futura.
  3. Cobertura de browsers exigir polyfills que Tailwind não cobre bem.
  4. Necessidade de SSR/streaming com framework específico (Next, etc.) — fora do escopo da stack Laravel/Livewire atual.

## Tipo

- [x] **Padrão transversal**: framework CSS que vira default no projeto.
- [ ] **Workaround**: contornar bug/limitação documentado.
- [ ] **Convenção interna**: padrão de código local que precisa ser seguido (formato de erro, naming de evento, etc).
- [ ] **Otimização**: mudança feita por motivo de performance, com medição.
- [ ] **Refatoração estrutural**: mudança que afeta vários módulos por motivo de qualidade.

---

## Histórico

- 2026-05-25 — criada como `accepted` por programador (claude-opus-4-7) após o PO aprovar a opção A (Tailwind v4 + `@theme`) em conversa no chat. Alternativas CSS vanilla e híbrido apresentadas com prós/contras; PO acolheu o argumento de aproveitar a stack já instalada e a aderência natural do `@theme` ao gate CA-6 da STORY-019. PO também autorizou na mesma rodada incluir páginas de erro 404/403/500 simplificadas dentro do shell (decisão registrada nas Notas do agente da STORY-019, não nesta IDR — é decisão de escopo de story, não de padrão transversal).
