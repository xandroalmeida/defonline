---
id: DDR-002
title: Entrada em dois níveis — seletor de modo por grupo, com o custo em campos declarado na própria escolha
status: accepted
created_at: 2026-08-04
decided_at: 2026-08-06
approved_by: Alexandro
supersedes: ~
superseded_by: ~
related_ddrs: [DDR-001, DDR-003, DDR-005]
related_adrs: [ADR-007]
related_pdrs: [PDR-003, PDR-004]
scope: formulários
affects_screens: [SCREEN-STORY-046-lancamento-mensal, SCREEN-STORY-046-serie-historica]
---

# DDR-002 — Entrada em dois níveis: seletor de modo por grupo

## Contexto

O `PDR-003`, decisão 6, obriga três mecanismos anti-atrito no MVP do DRM. O primeiro é **entrada em dois níveis**: cada grupo de custo/despesa aceita ou o total do grupo, ou o detalhamento item a item, com escolha reversível (Anexo K §K.5).

O impacto é desproporcional ao tamanho da decisão. Na configuração completa são **38 campos monetários**; com os quatro grupos em modo agregado caem para **9** — e dois desses (D41/D42) tipicamente ficam zerados. É a diferença entre um formulário de 38 campos e um de 7. Nenhum outro mecanismo do módulo tem esse fator.

A `STORY-046` coloca a pergunta de design com precisão: *"Como o usuário entende, em 3 segundos, que pode informar só o total de 'despesas administrativas' em vez de 21 itens — e o que ele perde ao fazer isso. A escolha não pode parecer um atalho preguiçoso; é uma opção legítima."*

Duas armadilhas simétricas. Se a escolha for enterrada ou tímida, ninguém a usa e o mecanismo não existe. Se for apresentada como o caminho fácil, o usuário agrega tudo por reflexo e perde para sempre a granularidade que faz a série histórica valer alguma coisa ("sua conta de energia subiu 40%") — e o Anexo K §K.5 é explícito: *"A tela deve deixar isso explícito no momento da escolha, sem culpabilizar quem escolhe agregado."*

Documentos lidos: Anexo K §K.5 e §K.5-A, `PDR-003` decisão 6, `PDR-004`, `ADR-007` (§*Modos agregado/detalhado* — modo é **fato carimbado no snapshot** e **preferência** em `drm_preferencias`), `especificacao/V2/design-system.md`.

## Forças (drivers)

- **Fator de redução de 38 → 9 campos** (altíssimo): nenhuma outra decisão de design deste épico tem esse alcance sobre a métrica de atrito.
- **Custo irreversível na série histórica** (alto): mês agregado não pode ser desagregado depois. `ADR-007`: *"nunca inferir o valor de um item a partir do total do grupo"*. A escolha de hoje limita a leitura de sempre.
- **Assimetria de risco entre os grupos** (alto): `administrativas` tem 21 itens; `financeiras` tem 2. A mesma interface serve os dois, mas o peso da decisão não é o mesmo — e a interface precisa mostrar isso.
- **Persona** (alto): não é planilheiro. "Detalhado" e "agregado" são palavras de contador, não dele.
- **Princípio #4 — padronização** (médio): o DS não tem controle segmentado. Introduzir um é criar componente.
- **Acessibilidade** (alto): seja o que for, tem de ser um grupo de rádio de verdade, navegável por setas, com `<legend>`.

## Opções consideradas

### Opção A — Pergunta única de configuração, antes do formulário

Uma tela inicial: "Você quer detalhar as despesas?" Sim/Não, valendo para os quatro grupos.

```
+----------------------------------+
| Como você quer lançar?           |
|  ( ) Rápido — só os totais       |
|  ( ) Completo — item a item      |
|              [ Começar ]         |
+----------------------------------+
```

- **Prós:** decisão tomada uma vez; formulário depois é homogêneo.
- **Contras:** viola o Anexo K, que fixa o modo **por grupo**. Achata a assimetria — força quem só quer detalhar energia a detalhar também as duas despesas financeiras. E os rótulos "Rápido/Completo" são exatamente o enquadramento proibido: transformam a escolha em preguiça vs. virtude. Ainda acrescenta uma tela antes da primeira pergunta, num fluxo cuja meta é ≤5 minutos.

### Opção B — Seletor de modo no cabeçalho de cada bloco de grupo, com o custo em campos na própria opção

O bloco do grupo abre com a escolha. Cada opção declara quantos campos ela custa. Uma frase abaixo diz o que muda — e protege explicitamente quem escolhe o total.

```
Mobile (≥360px)
+------------------------------------------------+
| Despesas administrativas e gerais          [?] |
+------------------------------------------------+
| Como você quer informar?                       |
| +--------------------+---------------------+   |
| | (•) Informar       | ( ) Detalhar        |   |
| |     o total        |     por item        |   |
| |     1 campo        |     21 campos       |   |
| +--------------------+---------------------+   |
|                                                |
| Com o total, a demonstração do mês fica        |
| correta do mesmo jeito. Detalhando, o DRM      |
| também consegue apontar qual item subiu.       |
+------------------------------------------------+
| TOTAL DAS DESPESAS ADMINISTRATIVAS E GERAIS    |
| [ R$                            8.430,00 ]     |
+------------------------------------------------+
```

```
Desktop (≥1024px) — as duas opções lado a lado com mais folga; a frase
explicativa ocupa a largura total abaixo delas, não uma terceira coluna.
+--------------------------------------------------------------+
| Despesas administrativas e gerais                        [?]  |
| Como você quer informar?                                      |
|  +---------------------------+ +--------------------------+   |
|  | (•) Informar o total      | | ( ) Detalhar por item    |   |
|  |     1 campo               | |     21 campos            |   |
|  +---------------------------+ +--------------------------+   |
|  Com o total, a demonstração do mês fica correta do mesmo     |
|  jeito. Detalhando, o DRM também consegue apontar qual        |
|  item subiu.                                                  |
+--------------------------------------------------------------+
```

- **Prós:** respeita o modo por grupo do Anexo K; **o número de campos é o argumento**, e ele é factual, não persuasivo; a assimetria aparece sozinha (21 vs. 2) sem que a interface precise opinar; a escolha está no lugar exato onde a consequência acontece; reversível a qualquer momento porque o seletor permanece visível no bloco.
- **Contras:** repete a mesma pergunta quatro vezes no primeiro lançamento. Mitigado: o modo é preferência persistida em `drm_preferencias` (`ADR-007`), então **a partir do 2º mês a escolha já vem feita** e o seletor é apenas informação de estado.

### Opção C — Progressive disclosure sem escolha explícita

Um campo de total sempre visível, com um link "detalhar" que expande os itens; preencher itens desativa o total.

- **Prós:** zero componente novo; menos texto.
- **Contras:** o modo deixa de ser um estado nomeado e passa a ser inferido do que está preenchido — e o modo **é** dado persistido que entra no `payload_hash` (`ADR-007`). Interface que esconde um estado persistido produz exatamente a classe de bug que o `ADR-007` se deu ao trabalho de tornar impossível. Pior: o usuário nunca vê "21 campos", que é a informação que faz o mecanismo funcionar.

### Status quo — não há status quo

O mecanismo é obrigatório pelo `PDR-003`, decisão 6. Não fazê-lo é não entregar o épico.

## Avaliação contra os princípios

| Princípio | A | B | C |
|---|---|---|---|
| 1. Simplicidade radical | ⚠️ uma tela a mais antes de tudo | ✅ a escolha vive onde a consequência vive | ✅ |
| 2. Mobile-first com paridade | ✅ | ✅ duas opções cabem lado a lado em 360px | ✅ |
| 3. Tom profissional para MPE | ❌ "Rápido vs. Completo" julga o usuário | ✅ factual, sem adjetivo de valor | ⚠️ omite a consequência |
| 4. Padronização > criatividade | ✅ usa rádio comum | ⚠️ **componente novo** (justificado abaixo) | ✅ |
| 5. Acessibilidade como hábito | ✅ | ✅ radiogroup real, setas, `<legend>` | ❌ estado inferido não é anunciável |
| 6. Performance percebida | ✅ | ✅ troca de modo é render local | ✅ |
| 7. Estados além do caminho feliz | ⚠️ | ✅ inclui o caminho destrutivo (abaixo) | ❌ caminho destrutivo fica implícito |

## Decisão

> **Adotada: Opção B.**

A força decisiva é que **o número de campos é o argumento honesto**. "21 campos" informa sem persuadir: quem tem 5 minutos escolhe o total e a demonstração sai correta; quem quer saber por que a energia subiu paga os 21 campos sabendo o que compra. Nenhuma das duas escolhas é enquadrada como melhor, o que é exatamente o que o Anexo K §K.5 exige — e é a razão de a Opção A cair, apesar de ser a mais barata.

A Opção C cai por um motivo mais duro que estética: esconder um estado que é persistido e entra no `payload_hash` contradiz o modelo do `ADR-007`.

### O que o padrão fixa

1. **Um seletor por grupo**, no cabeçalho do bloco daquele grupo, acima dos campos.
2. **Duas opções, cada uma com o custo em campos declarado** — contado **depois** da supressão por setor (Anexo K §K.4). Serviços vê "2 campos" onde Indústria vê "4"; é o mesmo controle mostrando um fato diferente, e é assim que "menos campos" parece natural em vez de incompleto.
3. **Uma frase, sempre a mesma estrutura:** o que o total garante, seguido do que o detalhe acrescenta. Nessa ordem — a garantia primeiro. Nunca uma advertência.
4. **Semântica de `radiogroup`**: `<fieldset>` + `<legend>` + dois `<input type="radio">`. A aparência de controle segmentado é estilo sobre rádios reais; setas navegam, `<legend>` nomeia o grupo, alvo ≥44×44px.
5. **Caminho destrutivo (agregado → detalhado) é confirmado inline, não em modal.** O Anexo K exige confirmação explícita porque o total digitado é descartado. A confirmação usa o `aviso-inline` variante `alerta` do DDR-001, no lugar do seletor, com dois botões. **Não introduzimos diálogo modal** — o DS não tem um, e um formulário longo em celular não precisa de foco-trap onde um bloco inline resolve.
6. **Caminho não destrutivo (detalhado → agregado) não confirma.** O total é pré-preenchido com a soma dos itens e uma legenda declara isso; os itens ficam preservados na sessão.
7. **Modo é preferência.** A partir do 2º lançamento o seletor abre já resolvido. Ele continua visível — é informação de estado, não só de escolha.

## Consequências

### Positivas

- O mecanismo anti-atrito de maior alcance do módulo ganha uma superfície de 3 segundos.
- A assimetria entre grupos (21 itens vs. 2) fica visível sem que a interface precise ter opinião.
- A adaptação por setor deixa de precisar de explicação: aparece como um número menor no mesmo lugar.
- O caminho destrutivo fica resolvido sem introduzir modal no produto.

### Negativas / trade-offs assumidos

- **Componente novo no DS** (`seletor-modo`). Justificativa: usado 4× por tela, 12× por ano, e é o controle de que depende o mecanismo anti-atrito nº 1 do `PDR-003`. Uma lista de rádios comum empurraria o custo em campos — que é a informação inteira — para baixo da dobra em 360px.
- **A pergunta se repete quatro vezes no 1º lançamento.** Aceito: é o lançamento em que ela precisa ser feita. Do 2º em diante, vem resolvida.
- **A frase explicativa acrescenta ~2 linhas por grupo.** Aceito; é o único lugar em que o produto explica um custo irreversível.

### Impacto no Design System

- Componente novo `seletor-modo` em `components.md` — 2 opções, cada uma com rótulo e uma linha de custo; estados `default | hover | focus | selecionado | disabled`.
- Reuso do `aviso-inline` variante `alerta` (DDR-001) para a confirmação destrutiva.
- **Nenhum token novo.** Opção selecionada usa `--color-surface` sobre trilho `--color-neutral`, borda `--color-tertiary` e rótulo `--color-primary`; não selecionada usa fundo transparente e rótulo `--color-secondary`.
  **Restrição de contraste verificada:** `--color-tertiary` sobre `--color-neutral` mede **4,45:1** e reprova AA para texto normal. Portanto o rótulo da opção selecionada é `--color-primary` (14,7:1 sobre neutral) e o `tertiary` aparece **só como borda e marcador do rádio**, onde o piso é 3:1. Regra registrada em `design/system/tokens.md`.

### Impacto em telas existentes

- `SCREEN-STORY-046-lancamento-mensal` — consome o seletor nos quatro grupos.
- `SCREEN-STORY-046-serie-historica` — herda a consequência: item de mês agregado é `indisponivel:grupo_agregado` (DDR-005).
- Nenhuma tela em produção é afetada.

## Implementação sugerida (notas para o Programador)

- A contagem de campos por opção depende do setor da Empresa Analisada; é derivável da mesma configuração declarativa que decide quais campos renderizar (Anexo K §K.4). Um só lugar decide as duas coisas.
- A confirmação inline substitui o seletor no lugar; ao cancelar, o seletor volta com o modo anterior selecionado — o rádio não deve ficar visualmente trocado enquanto a confirmação está aberta.
- Preferência lida de `drm_preferencias`, criada preguiçosamente com defaults (`ADR-007`).

## Critérios para revisitar

- **Se mais de 90% dos grupos ficarem agregados** após 3 meses de uso real: a granularidade fina não está sendo comprada por ninguém, e vale perguntar ao PO se detalhar 21 itens deveria continuar existindo como está.
- **Se menos de 10% ficarem agregados**: o mecanismo não está sendo percebido — o problema é de visibilidade, não de escolha.
- Se aparecer um terceiro modo (ex.: detalhar só alguns itens do grupo), o controle de duas opções não serve mais.

## Aprovação humana

| Campo | Valor |
|---|---|
| Apresentado em | 2026-08-04 |
| Aprovado por | (pendente) |
| Data da aprovação | (pendente) |
| Observações do aprovador | |

> Sem este bloco preenchido, o DDR **não** pode ir para `accepted`.
