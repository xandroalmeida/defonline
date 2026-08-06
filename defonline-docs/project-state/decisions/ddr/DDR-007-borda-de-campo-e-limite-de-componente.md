---
id: DDR-007
title: "Borda de campo — o que separa `--color-border` de `--color-input` é a WCAG 1.4.11"
status: accepted
created_at: 2026-08-06
decided_at: 2026-08-06
approved_by: Alexandro
supersedes: ~
superseded_by: ~
related_ddrs: [DDR-006]
related_adrs: []
related_pdrs: [PDR-008]
scope: formulários
affects_screens: [SCREEN-STORY-046-lancamento-mensal]
---

# DDR-007 — Borda de campo e limite de componente

## Contexto

`--color-input` (`#E3E8EE`) sobre `--color-surface` (`#FFFFFF`) mede **1,23:1**. A WCAG 2.1 §1.4.11 (*Non-text Contrast*) pede **3:1** para a informação visual necessária a identificar um componente de interface e seu estado — e a borda é literalmente o que diz "isto aqui é um campo onde se digita".

O achado nasceu na `STORY-046`, ao especificar o formulário de lançamento do DRM, e **não é dele**: atinge `.input` e `.input-affix`, ou seja, **todas as telas de formulário já em produção** — cadastro, login, nova empresa, e os 20 campos do Quiz. O `PDR-008` §3 mandou o achado para a `STORY-059` como CA-10 e disse duas coisas que este DDR respeita: exige DDR próprio, e **não segura a estória** — se a decisão não estiver ratificada quando os demais CAs fecharem, o CA-10 fica explicitamente aberto.

O agravante de sequência: o formulário do DRM tem até 38 campos monetários preenchidos no celular à noite (`PDR-003`). Borda invisível num formulário de 5 campos é irritação; em 38, com o dedo grande e a luz ruim, é erro de digitação em campo errado.

Documentos lidos: `STORY-059` CA-10 e Protocolo do agente, `PDR-008` §3, `design/system/tokens.md` §*Dívidas de acessibilidade* item 2, `SCREEN-STORY-046-lancamento-mensal`, `IDR-008`, `especificacao/V2/design-system.md` §Tokens, `app.css` (`.input`, `.input-affix`, `.input:focus`).

## Forças (drivers)

- **Princípio #5, acessibilidade como hábito** (altíssimo): 1,23:1 contra piso de 3:1 não é margem fina, é um quarto do exigido.
- **Atinge todo formulário do produto** (altíssimo): não há como testar a mudança "numa tela só". O que for decidido aqui muda a aparência de tudo que tem campo.
- **A estética Stripe do DS canônico é deliberada** (alto): campo de borda quase invisível sobre branco é a assinatura visual do produto, escolhida no DS v1. Escurecer a borda mexe na identidade, não só num pixel.
- **`--color-border` e `--color-input` têm hoje o mesmo hex** (médio): dois tokens, um valor. Isso é ou redundância a eliminar, ou uma distinção que nunca foi exercida — e este DDR é a hora de decidir qual.
- **Custo de reavaliação** (médio): telas em produção, já vistas pelo Key User, precisariam de nova conferência visual.

## Opções consideradas

### Opção A — Escurecer `--color-input` até 3:1, mantendo `--color-border` claro

Os dois tokens deixam de compartilhar valor. `--color-input` vai para `#7C8798` (**3,64:1** sobre `surface`, **3,44:1** sobre `neutral`); `--color-border`, que separa seções e não delimita componente interativo, fica em `#E3E8EE`.

```
  Hoje                          Opção A
  +----------------------+      +======================+
  | Faturamento          |      | Faturamento          |
  |  R$ ____________     |      |  R$ ____________     |
  +----------------------+      +======================+
   borda 1,23:1 (some)           borda 3,64:1 (delimita)
```

- **Prós:** resolve o critério onde ele se aplica e só lá; separa dois tokens que hoje são sinônimos por acidente; o campo passa a ser identificável sem depender de foco ou de placeholder.
- **Contras:** o formulário fica visualmente mais pesado — 38 retângulos de borda média-escura numa tela é bem mais denso que 38 de borda quase branca; afasta o produto da referência visual Stripe assumida no DS v1.

### Opção B — Escurecer só o suficiente e aceitar 3:1 exato

Mesma ideia da A, com `#8792A5` (**3,14:1** sobre `surface`) — o valor mínimo que passa.

- **Prós:** mais leve que a A; ainda passa.
- **Contras:** **2,97:1 sobre `neutral`** — reprova em qualquer campo dentro de card neutro, e o produto tem esses. Margem de 0,14 não sobrevive a um ajuste futuro de fundo. Passar raspando num critério de acessibilidade é dívida disfarçada de economia.

### Opção C — Manter a borda clara e adicionar um segundo canal (fundo do campo)

Campo ganha fundo `--color-neutral` em vez de branco, criando a delimitação por diferença de superfície em vez de por borda.

- **Prós:** preserva a leveza da borda; padrão consagrado (campo "afundado").
- **Contras:** `neutral` sobre `surface` mede **1,06:1** — pior que a borda. Não resolve o critério, só troca o canal que falha. Rejeitada por medição, não por gosto.

### Status quo — manter `#E3E8EE`

- **Prós:** zero impacto visual; zero reconferência de telas.
- **Contras:** todo formulário do produto segue violando 1.4.11, e o formulário de 38 campos do DRM nasce com o defeito multiplicado. É a opção que a `STORY-059` existe para não deixar em pé.

## Avaliação contra os princípios

| Princípio | A | B | C | Status quo |
|---|---|---|---|---|
| 1. Simplicidade radical | ✅ um token, um valor | ✅ | ⚠️ dois canais para o mesmo fim | ✅ |
| 2. Mobile-first com paridade | ✅ ganho maior no celular | ✅ | ✅ | ❌ o pior caso é o mobile |
| 3. Tom profissional para MPE | ⚠️ mais denso que o DS v1 | ⚠️ idem, menos | ✅ | ✅ |
| 4. Padronização > criatividade | ✅ exerce a distinção border/input | ✅ | ⚠️ novo padrão de campo | ✅ |
| 5. Acessibilidade como hábito | ✅ 3,44:1 no pior fundo | ⚠️ 2,97:1 no fundo neutro | ❌ 1,06:1 | ❌ 1,23:1 |
| 6. Performance percebida | ✅ | ✅ | ✅ | ✅ |
| 7. Estados além do caminho feliz | ✅ erro e foco seguem por cima | ✅ | ⚠️ estado de erro compete com o fundo | ✅ |

## Decisão

> **Adotada:** Opção A — `--color-input` vai para `#7C8798`; `--color-border` permanece `#E3E8EE`.

A força decisiva é a quarta combinada com a primeira: os dois tokens existem desde o DS v1 e nunca foram diferentes, o que fazia parecer que a distinção era decorativa. Ela não é — **`--color-border` separa, `--color-input` delimita componente interativo**, e só o segundo responde à WCAG 1.4.11. Exercer a distinção resolve o critério sem escurecer uma única linha divisória de seção.

A Opção B é a mesma decisão com margem que não sobrevive ao primeiro campo dentro de card. A C falha na medição.

O trade-off de peso visual (princípio #3) é assumido conscientemente: o produto fica um pouco menos etéreo e um pouco mais legível, e a persona — gestor de MPE preenchendo formulário longo no celular, à noite — está do lado da legibilidade.

## Consequências

### Positivas

- `.input` e `.input-affix` passam a 3,64:1 sobre `surface` e 3,44:1 sobre `neutral`, atendendo 1.4.11 com margem.
- O formulário de 38 campos do DRM (`STORY-047`) nasce correto, em vez de herdar e multiplicar o defeito.
- Os dois tokens passam a significar coisas diferentes, e o DS ganha a regra explícita de quando usar cada um.

### Negativas / trade-offs assumidos

- **Toda tela com formulário muda de aparência.** Cadastro, login, nova empresa, Quiz. É mudança visível, não sutil — precisa de conferência do PO em homologação, não só de teste automatizado.
- O produto se afasta um passo da referência Stripe do DS v1. Declarado aqui para não ser descoberto depois como "drift".
- O estado de foco (`--color-ring`) e o de erro (`destructive`) continuam por cima da borda nova; nenhum precisou mudar, mas os dois ficam menos contrastantes **em relação** à borda de repouso, que subiu. Se o foco ficar ambíguo em uso real, é sinal de revisita.

### Impacto no Design System

- **Nenhum token novo.** `--color-input` muda de valor em `tokens.css` e no DS canônico, com bump de versão lá.
- Regra nova em `design/system/tokens.md`: `--color-border` para divisória e contorno decorativo; `--color-input` para o limite de qualquer componente com o qual o usuário interage. Não são intercambiáveis, apesar de terem sido idênticos até aqui.

### Impacto em telas existentes

- `/cadastro`, `/login`, `/empresas/nova`, o Quiz inteiro e `SCREEN-STORY-046-lancamento-mensal`. Nenhum spec precisa ser reescrito — todos referenciam o token, não o hex.

## Implementação sugerida (notas para o Programador)

- Uma linha em `tokens.css`. `.input`, `.input-affix` e derivados já consomem `var(--color-input)`; nada em Blade deve mudar.
- Conferir que nenhum componente usa `--color-input` como divisória (uso trocado): se usar, é ali que a mudança vai parecer errada, e a correção é passar para `--color-border`.
- Depois do deploy, conferir em homologação, em celular real, o Quiz e `/empresas/nova` — são as telas com mais campos por viewport.

## Critérios para revisitar

- Se o PO julgar, vendo em homologação, que o peso visual custou mais do que a legibilidade ganhou: o caminho de volta não é o status quo, é procurar 3:1 com outro matiz (menos saturado, mais claro) — o piso não é negociável, o hex é.
- Se o DS ganhar tema escuro: o valor é recalculado contra o fundo escuro.
- Se a WCAG 3 (APCA) entrar no gate: a métrica muda de escala.

## Aprovação humana

| Campo | Valor |
|---|---|
| Apresentado em | 2026-08-06 |
| Aprovado por | Alexandro (PO) |
| Data da aprovação | 2026-08-06 |
| Observações do aprovador | ~ |

> Sem este bloco preenchido, o DDR **não** pode ir para `accepted`.
