---
id: DDR-001
title: Formulário longo é wizard por blocos com rascunho por bloco — dialeto único de formulário do produto
status: accepted
created_at: 2026-08-04
decided_at: 2026-08-06
approved_by: Alexandro
supersedes: ~
superseded_by: ~
related_ddrs: [DDR-002, DDR-003]
related_adrs: [ADR-001, ADR-007]
related_pdrs: [PDR-003]
scope: formulários
affects_screens: [SCREEN-STORY-046-lancamento-mensal]
---

# DDR-001 — Formulário longo é wizard por blocos com rascunho por bloco

## Contexto

O DEFOnline tem hoje **um** formulário longo em produção: o Quiz do Diagnóstico (`STORY-027`), com 23 campos em 4 blocos. Ele resolveu, sem nunca ter sido registrado como decisão, um conjunto de problemas que vão reaparecer em todo formulário longo do produto: como fatiar, como salvar, como retomar, como mostrar progresso, como distinguir um aviso de um bloqueio.

A `STORY-046` traz o segundo formulário longo — o lançamento mensal do DRM, com até 38 campos monetários (Anexo K §K.3, v1.1). A estória é explícita: *"Reaproveite os padrões do Quiz. Não crie um segundo dialeto de formulário dentro do mesmo produto."*

O risco concreto não é estético. Um segundo dialeto significa que o usuário que aprendeu a preencher o Diagnóstico precisa reaprender a preencher o DRM — e o DRM é o módulo cuja métrica primária é *voltar no mês seguinte* (`EPIC-010`, métrica primária: 2º lançamento em 45 dias). Custo de reaprendizado é atrito, e atrito aqui é a métrica.

Este DDR **promove ao Design System o padrão que já está em produção**, com a extensão mínima que o DRM exige. Não inventa nada.

Documentos lidos: `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` (integral), `PDR-003` (decisão 6), `ADR-007` (§rascunho), `especificacao/V2/design-system.md`, `IDR-008`, e o código em produção — `app/resources/views/livewire/diagnostico/quiz.blade.php` e `app/resources/css/app.css` (`@layer components`).

## Forças (drivers)

- **Persona e contexto de uso** (alto): dono de MPE preenchendo no celular, à noite, depois de fechar a loja. Vai ser interrompido. A retomada não é caso de borda — é o caso comum.
- **Métrica primária do EPIC-010** (alto): 2º lançamento em 45 dias. Um formulário que o usuário não consegue abandonar e retomar não tem 2º lançamento.
- **Padronização > criatividade — Princípio #4** (alto): já existe um dialeto em produção, testado por E2E e validado pela EBC. Divergir dele exige justificativa que não temos.
- **Custo de implementação** (médio): reaproveitar o padrão do Quiz é barato — `STORY-047` já é `L`. Um dialeto novo somaria custo sem entregar nada.
- **Simplicidade radical — Princípio #1** (médio): 38 campos numa página violam o princípio por definição. Fatiar é a única saída que não remove campo.
- **Acessibilidade — Princípio #5** (alto): formulário longo é onde a navegação por teclado e o resumo de erros deixam de ser detalhe e viram viabilidade.

## Opções consideradas

### Opção A — Página única com seções ancoradas (long form + sumário lateral)

Todos os campos numa página, agrupados por seção, com um índice lateral que rola até a seção.

```
Mobile (≥360px)              Desktop (≥1024px)
+----------------------+     +---------+--------------------------+
| Lançar agosto/2026   |     | Receita | Lançar agosto/2026       |
+----------------------+     | Custo   |  Receita                 |
| ▸ Receita            |     | Vendas  |   [R$ ............]      |
|   [R$ ...........]   |     | Admin.  |   [R$ ............]      |
|   [R$ ...........]   |     | Fin.    |  Custo do que foi vendido|
| ▸ Custo              |     |         |   [R$ ............]      |
|   ... (38 campos)    |     |         |   ... (38 campos)        |
+----------------------+     +---------+--------------------------+
```

- **Prós:** o usuário vê o todo; nenhuma navegação entre etapas; rolar é mais barato que clicar.
- **Contras:** o *scroll* de 38 campos em 360px é a própria definição do problema que a estória descreve — "se a tela de lançamento for uma lista de 38 campos, o módulo morre no segundo mês". Rascunho passa a ser salvo por *debounce* ou por foco, sem ponto de corte natural, o que torna "onde eu parei" indefinível. Divergiria do Quiz sem ganho.

### Opção B — Wizard por blocos, com rascunho salvo ao concluir cada bloco *(padrão vigente do Quiz)*

Um bloco por tela. Barra de progresso segmentada no topo. `Próximo` salva o rascunho e avança. `Voltar` não perde nada. Retomada abre no bloco em que parou, com aviso.

```
Mobile (≥360px)
+----------------------------------+
| Lançar agosto/2026               |
| Padaria do Bairro · Comércio     |
+----------------------------------+
| ███ ███ ▒▒▒ ▒▒▒ ▒▒▒ ▒▒▒          |  ← 6 segmentos
| Bloco 2 de 6 · Custo das         |
| Mercadorias Vendidas (CMV)       |
+----------------------------------+
| ┌──────────────────────────────┐ |
| │ (card)                       │ |
| │  campos do bloco             │ |
| └──────────────────────────────┘ |
+----------------------------------+
| [ Voltar ]          [ Próximo ]  |
| Pode sair e voltar depois.       |
+----------------------------------+
```

```
Desktop (≥1024px)
+---------+--------------------------------------------------+
| nav     | Lançar agosto/2026 — Padaria do Bairro · Comércio |
| lateral +--------------------------------------------------+
|         | ███ ███ ▒▒▒ ▒▒▒ ▒▒▒ ▒▒▒                          |
|         | Bloco 2 de 6 · Custo das Mercadorias Vendidas     |
|         | ┌──────────────────────────────────────────────┐ |
|         | │ (card — campos em 2 colunas quando o bloco    │ |
|         | │  tem mais de 8 campos; 1 coluna abaixo disso) │ |
|         | └──────────────────────────────────────────────┘ |
|         |  [ Voltar ]                        [ Próximo ]   |
+---------+--------------------------------------------------+
```

- **Prós:** ponto de corte natural para o rascunho (fim de bloco) — "onde parei" é uma resposta exata, não uma aproximação; progresso legível em 1 segundo; cada tela cabe na dobra do celular; **é o que já está em produção e testado**; permite que o modo agregado (DDR-002) reduza um bloco inteiro a um campo, o que é o mecanismo anti-atrito nº 1 do `PDR-003`.
- **Contras:** o usuário não vê o todo antes de fechar — resolvido por um bloco final de conferência; mais cliques que a Opção A quando o formulário é curto (irrelevante aqui).

### Opção C — Acordeão de grupos numa página só

Grupos colapsáveis; abre um por vez; salva ao fechar o grupo.

- **Prós:** meio-termo; mantém noção de todo.
- **Contras:** herda o pior dos dois — o scroll cresce conforme o usuário abre grupos, e o estado "quais grupos já conferi" precisa de marcação própria que compete com a marcação de valores herdados (DDR-003). Duas marcações concorrentes na mesma tela é ruído. Nenhum precedente no produto.

### Status quo — não decidir; cada formulário resolve do seu jeito

- **Consequência:** o DRM inventa o segundo dialeto por omissão, exatamente o que a `STORY-046` proíbe. Todo formulário futuro renegocia as mesmas seis questões.

## Avaliação contra os princípios

| Princípio | A | B | C | Status quo |
|---|---|---|---|---|
| 1. Simplicidade radical | ❌ 38 campos numa tela | ✅ uma tarefa por tela | ⚠️ scroll cresce com o uso | ❌ sem critério |
| 2. Mobile-first com paridade | ❌ scroll incontrolável em 360px | ✅ bloco cabe na dobra | ⚠️ depende de quantos grupos abertos | ❌ |
| 3. Tom profissional para MPE | ✅ | ✅ | ✅ | ⚠️ |
| 4. Padronização > criatividade | ❌ diverge do Quiz | ✅ **é** o padrão do Quiz | ❌ terceiro padrão | ❌ |
| 5. Acessibilidade como hábito | ⚠️ ordem de tab longuíssima | ✅ tab curto por bloco, resumo de erro por bloco | ⚠️ foco pula ao colapsar | ❌ |
| 6. Performance percebida | ⚠️ render de 38 campos + máscaras | ✅ render por bloco | ⚠️ | ❌ |
| 7. Estados além do caminho feliz | ⚠️ retomada sem ponto de corte | ✅ retomada exata | ⚠️ | ❌ |

## Decisão

> **Adotada: Opção B.**

O padrão do Quiz vira o **dialeto único de formulário longo do DEFOnline**, registrado no Design System como o padrão `formulario-longo`. A força decisiva é a combinação de Princípio #4 com a métrica primária do `EPIC-010`: o padrão já existe, já é testado, e o custo de divergir dele é pago pelo usuário na forma de reaprendizado — no módulo cuja única métrica que importa é ele voltar.

A Opção A perde porque materializa literalmente o cenário de fracasso descrito na estória. A Opção C perde porque introduz uma segunda marcação de estado por grupo, que colide com a marcação de valores herdados do DDR-003.

### O que o padrão fixa

1. **Fatiamento.** Um bloco por tela. Bloco é uma pergunta que o usuário responde de uma sentada. Máximo sugerido: o que couber sem que a barra de rolagem passe de ~2 dobras em 360×800.
2. **Progresso.** Barra segmentada — um segmento por bloco, preenchidos até o atual — mais uma linha textual `Bloco N de M · <nome do bloco>`. A barra é `role="progressbar"` com `aria-valuenow`. **A barra sozinha não basta**: o nome do bloco é o que responde "onde estou".
3. **Rascunho.** Salvo ao concluir cada bloco, nunca por *debounce* silencioso. O botão de avanço declara o salvamento (`Próximo` → `Salvando…`). Uma linha permanente no rodapé do formulário: *"Você pode sair e voltar depois — o que já preencheu fica salvo."*
4. **Retomada.** Ao reabrir, aviso `info` no topo nomeando **o bloco**, não o número: *"Retomando seu rascunho — você parou em Despesas administrativas."* Rascunho expirado tem aviso próprio e recomeça do bloco 1.
5. **Três severidades de aviso inline**, e só três — componente `aviso-inline`:
   - `info` — contexto, sem ação obrigatória. Borda e fundo neutros.
   - `alerta` — pede confirmação e **oferece uma saída para frente**: botão "corrigir" por item + um único aceite explícito ao final.
   - `bloqueio` — impede o avanço e **não tem botão de seguir em frente**. A ausência do botão é o que distingue bloqueio de alerta; nenhuma outra pista visual carrega essa distinção sozinha.
6. **Bloco final de conferência.** Todo formulário longo termina numa tela que mostra tudo o que será gravado, agrupado pelos blocos anteriores, com link para voltar a cada um. É onde os alertas que só são computáveis no fim aparecem.
7. **Acessibilidade.** Cada bloco é `<fieldset>` com `<legend>`; ao trocar de bloco o foco vai para o cabeçalho do bloco (`tabindex="-1"`), não para o primeiro campo — assim o leitor de tela anuncia onde chegou antes de anunciar o que preencher. Resumo de erros no topo do bloco, com link para cada campo. Mensagem de erro associada por `aria-describedby`.

## Consequências

### Positivas

- O DRM nasce com o vocabulário de formulário que o usuário já conhece do Diagnóstico.
- O `aviso-inline` consolida três construções quase idênticas hoje espalhadas no `quiz.blade.php` (retomada, alertas cruzados, bloqueios) num componente só — dívida paga na mesma operação.
- "Onde parei" tem resposta exata e barata, porque o rascunho tem ponto de corte.
- O padrão sustenta o modo agregado (DDR-002): um grupo agregado colapsa um bloco inteiro em um campo, e o ganho fica **visível na barra de progresso**.

### Negativas / trade-offs assumidos

- **O usuário não vê o formulário inteiro antes do bloco de conferência.** Aceito, e mitigado pelo bloco 6. Quem quer visão de todo antes de preencher não é a persona deste módulo.
- **Mais navegação que uma página única.** Real, e o preço de manter cada tela dentro da dobra do celular.
- **Refatorar o Quiz para consumir os componentes promovidos é trabalho que este DDR cria e não paga.** Registrado abaixo como pendência a levar ao PO — o Quiz **não** deve ser refatorado dentro do `EPIC-010`.

### Impacto no Design System

Entram em `design/system/`:

- Padrão `formulario-longo` (`patterns.md`) — os 7 itens acima.
- Componente `progresso-blocos` (`components.md`) — promovido do Quiz, sem mudança visual.
- Componente `aviso-inline` (`components.md`), variantes `info | alerta | bloqueio` — consolidação de três construções existentes.

**Nenhum token novo.** Tudo se resolve com `--color-border`, `--color-neutral`, `--color-surface`, `--color-primary`, `--color-secondary`, `--color-destructive` e `--color-farol-amarelo` já em `tokens.css`.

### Impacto em telas existentes

- `SCREEN-STORY-046-lancamento-mensal` — consome o padrão integralmente.
- **Quiz do Diagnóstico (em produção)** — já **é** este padrão; a refatoração para consumir os componentes nomeados é cosmética e sem urgência. **Não** cabe no `EPIC-010`. Sugerido ao PO como estória de manutenção do `EPIC-002`, com um único ganho real: os três avisos inline passariam a ter contraste e semântica revisados num lugar só.

## Implementação sugerida (notas para o Programador)

- `progresso-blocos` e `aviso-inline` como Blade components anônimos em `resources/views/components/`, no padrão do `IDR-008`. O `aviso-inline` recebe `variant`, `role` e `aria-live` já resolvidos por variante — `assertive` só em `bloqueio`.
- O foco no cabeçalho do bloco pode reusar o mecanismo de evento Alpine que o Quiz já usa para `focar-campo`.
- Identificadores lógicos sugeridos nos specs de tela, não aqui.

## Critérios para revisitar

- Se um terceiro formulário longo não couber no padrão sem forçar — sinal de que o padrão é estreito demais.
- Se a telemetria da `STORY-056` mostrar abandono concentrado **na transição entre blocos** (e não dentro deles), o fatiamento é o problema, não o número de campos.
- Se o tempo mediano do 2º lançamento não cair abaixo de 5 minutos (métrica secundária do `EPIC-010`) mesmo com todos os grupos agregados, o gargalo não é o formulário e este DDR não é a alavanca.

## Aprovação humana

| Campo | Valor |
|---|---|
| Apresentado em | 2026-08-04 |
| Aprovado por | (pendente) |
| Data da aprovação | (pendente) |
| Observações do aprovador | |

> Sem este bloco preenchido, o DDR **não** pode ir para `accepted`.
