---
id: SCREEN-STORY-XXX-<slug>
story: STORY-XXX-<slug>      # estória do PO que originou
epic: EPIC-XXX-<slug>
status: draft                # draft | ready | in_implementation | shipped | superseded
created_at: YYYY-MM-DD
updated_at: YYYY-MM-DD
owner_designer: <nome>
related_ddrs: []             # DDRs que restringem este spec
ds_components_used: []       # ids de componentes do Design System usados
exceptions_to_ds: []         # divergências do DS justificadas neste spec
viewports: [mobile, desktop] # tablet só se comportamento mudar relevantemente
---

# Spec de tela — <nome da tela>

> Referência: estória `STORY-XXX-<slug>` (CAs e contexto vêm de lá — **não duplique**).
> Princípios aplicáveis: ver `defonline-docs/skills/designer/SKILL.md` (todos sempre — citar aqui se algum drove decisão).

## 1. Objetivo da tela

Em 1–3 linhas: qual é a **uma** tarefa principal que o usuário faz nesta tela? (Se você não consegue resumir em uma frase, a tela está pedindo simplificação — veja Princípio #1.)

## 2. Fluxo

### Entrada

- De onde o usuário chega? (link, navegação, redirect, deep link)
- O que precisa ser verdade antes (sessão, permissão, dado pré-carregado)?

### Ações possíveis na tela

- Ação primária: ...
- Ações secundárias: ...
- Saídas: para onde vai depois de cada ação?

### Saída

- Após sucesso: ...
- Após cancelamento: ...
- Após erro recuperável: ...

## 3. Layout

### Mobile (≥360px)

```
+--------------------------------+
| [header]                       |
+--------------------------------+
|                                |
| (área principal)               |
|                                |
+--------------------------------+
| [CTA primário]                 |
+--------------------------------+
```

- Componentes do DS usados: `<lista de ids>`
- Espaçamento, alinhamento, hierarquia: descreva em prosa curta apoiada no sketch.
- Alvo de toque mínimo: 44×44px.

### Desktop (≥1024px)

```
+---------+----------------------------------+
| [nav    | [header]                         |
| lateral +----------------------------------+
| ]       | (área principal — uso pleno     |
|         |  da largura, sem esticar)        |
|         +----------------------------------+
|         | [CTA primário] [secundário]      |
+---------+----------------------------------+
```

- Diferenças em relação ao mobile (não é "mobile esticado" — o espaço extra tem propósito).
- Componentes do DS usados: `<lista de ids>`.

### Tablet (768px) — **só se aplicável**

Inclua só quando o comportamento muda relevantemente em relação a mobile/desktop. Caso contrário, omita.

## 4. Estados

> Toda spec entrega **todos** os estados aplicáveis. Esquecer um estado é entregar meio spec (Princípio #7).

### 4.1. Caminho feliz (preenchido)

Descrição visual + microcopy. Já coberto pelos sketches acima — detalhar microcopy aqui.

### 4.2. Loading (primeiro fetch e refresh)

- Skeleton ou estado parcial preenchido — **não** spinner em tela vazia.
- Sketch:

```
+--------------------------------+
| ░░░░░░░░░░░░░░░░░░░░░░░░░░░░  |
| ░░░░░░░░░░░░░░░░░░░░░░░░░░░░  |
+--------------------------------+
```

### 4.3. Vazio (sem dados ainda — primeira vez)

- Mensagem clara + CTA primário que destrava a próxima ação.
- **Não** "Nenhum resultado." sozinho — sempre instruir.
- Sketch + microcopy.

### 4.4. Erro

Para cada tipo de erro previsível:

- **Erro de rede** — mensagem + retry visível.
- **Erro de permissão** — mensagem clara do que falta + a quem pedir.
- **Erro de dado inválido** (em form) — associado ao campo, não global.
- **Erro inesperado** — mensagem genérica honesta + caminho de suporte, sem stack trace.

Para cada um: microcopy exata + sketch.

### 4.5. Sem permissão

Quem chega aqui sem permissão vê o quê? (Mensagem + caminho de saída.)

### 4.6. Parcial / degradado

Aplicável quando parte dos dados carrega e parte falha. Como a tela se comporta?

### 4.7. Primeira vez vs recorrente (se aplicável)

Onboarding contextual da primeira vez, ausente nas seguintes.

## 5. Microcopy completo

Liste **toda** copy visível, em um só lugar — facilita revisão de tom e tradução futura:

| Lugar | Texto |
|---|---|
| Título da tela | ... |
| Subtítulo | ... |
| Label do campo X | ... |
| Placeholder do campo X | ... |
| Mensagem de erro do campo X (inválido) | ... |
| Mensagem de erro do campo X (obrigatório) | ... |
| CTA primário | ... |
| CTA secundário | ... |
| Mensagem de sucesso | ... |
| Estado vazio (título) | ... |
| Estado vazio (instrução) | ... |
| Estado vazio (CTA) | ... |

Vocabulário: `defonline-docs/skills/po/references/glossary.md`. Tom: `references/tone-and-voice.md`.

## 6. Acessibilidade (notas específicas desta tela)

Além do piso geral (`references/accessibility-basics.md`):

- Ordem de tab: ...
- Foco inicial ao abrir a tela: ...
- Labels acessíveis para ícones-ação: ...
- Mensagens de erro associadas a campos via `aria-describedby` (ou equivalente).
- Live regions para mensagens dinâmicas (toast, erro assíncrono): ...
- Contraste verificado para todos os tokens usados: ✅/❌ (anexar evidência se ❌ com justificativa).

## 7. Identificadores estáveis sugeridos para E2E

Para o teste de browser ancorar sem fragilidade. **Atributo concreto é decisão idiomática do Programador** conforme stack (em Laravel Dusk: atributo `dusk="..."`; em outros frameworks: `data-testid` ou equivalente). Sugestões abaixo são **nomes lógicos** — o Programador escolhe o atributo:

| Elemento | Identificador lógico sugerido |
|---|---|
| CTA primário | `screen-<slug>-primary-cta` |
| Campo X | `screen-<slug>-field-x` |
| Mensagem de erro X | `screen-<slug>-error-x` |
| Lista de itens | `screen-<slug>-list` |
| Item da lista | `screen-<slug>-item-<id>` |

> Esses identificadores facilitam o E2E em browser real que o PO já exige (`quality-standards.md`) — não criam exigência nova.

## 8. Exceções ao Design System

Liste **toda** divergência do DS e justifique. Sem justificativa, não é exceção — é desvio. Cada exceção é candidata a virar DDR se se repetir.

| O que diverge | Por quê | Vai virar DDR? |
|---|---|---|
| ... | ... | sim/não |

## 9. Dependências e premissas

- API/endpoint esperado: <referência ao contrato — não duplicar>
- Permissões necessárias: ...
- Premissas sobre o estado do back: ...
- Spec depende de DDR pendente? Liste em `related_ddrs` e marque `status: draft` até resolvido.

## 10. Histórico de mudanças

| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| YYYY-MM-DD | criação | <nome> | rabisco inicial pós-sync com Programador |
| YYYY-MM-DD | refino | <nome> | adicionados estados de erro e microcopy completo |

> **Mudança depois que o código começou** é mudança consciente — registre aqui e em "Notas do agente" da estória. Sem registro, é silêncio que vira retrabalho.
