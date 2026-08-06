---
id: SCREEN-STORY-046-serie-historica
story: STORY-046-design-telas-drm
implemented_by: STORY-051
epic: EPIC-010-drm-demonstracao-resultado-mensal
status: ready
created_at: 2026-08-04
updated_at: 2026-08-06
approved_by: Alexandro
approved_at: 2026-08-06
owner_designer: designer (claude-opus-5)
related_ddrs: [DDR-005, DDR-004, DDR-001]
ds_components_used:
  - page-header
  - card
  - button (primary, secondary, ghost)
  - pill
  - help
  - breadcrumb
  - aviso-inline             # novo — DDR-001
  - serie-linha-competencia  # novo — DDR-005 (estados) + DDR-004 (par nominal/percentual)
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Série histórica

> Referência: estória `STORY-046-design-telas-drm`. Implementada por `STORY-051`; a lista e seus estados são também a porta de entrada usada pela `STORY-047` e pela `STORY-050`.
> Decisões que valem além desta tela: **DDR-005** (ausência ≠ zero) e **DDR-004** (par nominal+percentual). Este spec **aplica**.
> **Microcopy provisória** — `STORY-052`.

## 1. Objetivo da tela

Mostrar **como os meses desta empresa se comportam ao longo do tempo** — e dar o caminho para o mês que falta lançar e para o mês que precisa ser corrigido.

É a tela que transforma doze lançamentos numa razão para fazer o décimo terceiro.

## 2. Fluxo

### Entrada

- De `Empresa Analisada → Ver todos os meses`.
- Da **Demonstração do Mês** → `Ver todos os meses`.
- Da navegação lateral do shell, no contexto de uma empresa.

Precisa ser verdade antes: sessão autenticada; empresa do usuário (senão 404 silente, `IDR-009`). **Não** precisa haver lançamento — o estado vazio é caso legítimo e é a primeira tela que muitos usuários verão.

### Ações possíveis

| Ação | Tipo | Destino |
|---|---|---|
| `Lançar {mês}` (topo) | **primária** | Lançamento, competência em aberto mais recente |
| Tocar na competência lançada | navegação | Demonstração do Mês |
| `Lançar` (linha de mês em falta) | secundária | Lançamento, naquela competência |
| `Continuar` (linha de rascunho) | secundária | Lançamento, retomando o rascunho |
| `Corrigir` (linha lançada) | secundária | Lançamento em modo retificação (`STORY-050`) |

Esta é a única das três telas com **ação primária**, e portanto a única que usa `--color-tertiary` num botão cheio.

### Saída

Navegação. Não há submit.

## 3. Layout

### Mobile (≥360px)

```
+--------------------------------------------------+
| ‹ Minhas Empresas / Padaria do Bairro / Meses    |
+--------------------------------------------------+
| Todos os meses                                   |
| Padaria do Bairro · Comércio                     |
|                          [ Lançar setembro/2026 ]|
+--------------------------------------------------+
|                                                  |
|  2026                                            |
|                                                  |
|  ┌────────────────────────────────────────────┐  |
|  │ AGOSTO                            lançado  │  |
|  │ Lucro do mês                               │  |
|  │ R$ 4.240,00                         5,0%   │  |
|  │ +12,4% em relação a julho                  │  |
|  │ +5,1% em relação à média dos meses         │  |
|  │ anteriores                                 │  |
|  │                              [ Corrigir ]  │  |
|  └────────────────────────────────────────────┘  |
|                                                  |
|  ╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌  |
|  ╎ JULHO                        não lançado   ╎  |
|  ╎ Sem lançamento para este mês.              ╎  |
|  ╎                              [ Lançar ]    ╎  |
|  ╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌  |
|                                                  |
|  ┌────────────────────────────────────────────┐  |
|  │ JUNHO             lançado  [Rascunho]      │  |
|  │ …                                          │  |
|  └────────────────────────────────────────────┘  |
|                                                  |
|  ┌────────────────────────────────────────────┐  |
|  │ MAIO                 lançado [Retificado]  │  |
|  │ Lucro do mês                               │  |
|  │ R$ 3.770,00                         4,6%   │  |
|  │ −2,1% em relação a abril                   │  |
|  │ média disponível a partir do 4º mês        │  |
|  │                              [ Corrigir ]  │  |
|  └────────────────────────────────────────────┘  |
|                                                  |
|  ┌────────────────────────────────────────────┐  |
|  │ DEZEMBRO/2025    lançado  [Mês atípico]    │  |
|  │ Lucro do mês                               │  |
|  │ −R$ 1.180,00                       −1,3%   │  |
|  │ [Prejuízo no mês]                          │  |
|  │ −140% em relação a novembro                │  |
|  │ Dezembro teve 13º salário — por isso o     │  |
|  │ resultado do mês caiu.                [?]  │  |
|  │                              [ Corrigir ]  │  |
|  └────────────────────────────────────────────┘  |
+--------------------------------------------------+
```

- **Ordem:** mais recente primeiro. Cabeçalho de ano (`2026`) sempre que o ano muda — é o único agrupamento; não há paginação no MVP (24 competências é o cenário de desempenho do `STORY-051` CA-11, e cabe numa lista).
- **A competência é o link** (o rótulo `AGOSTO` é `<a>`); o botão de ação é elemento irmão. **Nunca botão dentro de link** — nidificação de interativos é bug de acessibilidade.
- **Uma ação por linha**, sempre no mesmo lugar. Sem menu de três pontos: menu seria componente novo, e uma ação não justifica um.
- Alvos ≥44px em rótulo-link e botão.

### Desktop (≥1024px)

```
+---------+-------------------------------------------------------------------+
| nav     | ‹ Minhas Empresas / Padaria do Bairro / Meses                     |
| lateral | Todos os meses                                                    |
|         | Padaria do Bairro · Comércio           [ Lançar setembro/2026 ]   |
|         +-------------------------------------------------------------------+
|         | 2026                                                              |
|         | ┌───────────────────────────────────────────────────────────────┐ |
|         | │ Mês        │ Quanto  │ Lucro do mês │ vs. mês  │ vs.    │      │ |
|         | │            │ vendeu  │   R$ e %     │ anterior │ média  │      │ |
|         | │────────────│─────────│──────────────│──────────│────────│──────│ |
|         | │ Agosto     │84.300,00│  4.240,00    │ +12,4%   │ +5,1%  │Corri-│ |
|         | │ lançado    │         │      5,0%    │          │        │ gir  │ |
|         | │────────────│─────────│──────────────│──────────│────────│──────│ |
|         | │╌Julho╌╌╌╌╌╌│╌╌╌╌╌╌╌╌╌│╌Sem lança-╌╌╌│╌╌╌╌╌╌╌╌╌╌│╌╌╌╌╌╌╌╌│Lan- ╌│ |
|         | │╌não lançado│╌╌╌╌╌╌╌╌╌│╌mento para╌╌╌│╌╌╌╌╌╌╌╌╌╌│╌╌╌╌╌╌╌╌│çar ╌╌│ |
|         | │╌╌╌╌╌╌╌╌╌╌╌╌│╌╌╌╌╌╌╌╌╌│╌este mês.╌╌╌╌│╌╌╌╌╌╌╌╌╌╌│╌╌╌╌╌╌╌╌│╌╌╌╌╌╌│ |
|         | │────────────│─────────│──────────────│──────────│────────│──────│ |
|         | │ Maio       │82.100,00│  3.770,00    │ −2,1%    │ dispo- │Corri-│ |
|         | │ lançado    │         │      4,6%    │          │ nível  │ gir  │ |
|         | │ [Retificado]│        │              │          │ a par- │      │ |
|         | │            │         │              │          │ tir do │      │ |
|         | │            │         │              │          │ 4º mês │      │ |
|         | └───────────────────────────────────────────────────────────────┘ |
+---------+-------------------------------------------------------------------+
```

**O que o espaço extra faz** (declaração obrigatória):

- **Os dois comparativos viram colunas próprias.** No mobile eles são duas linhas de texto sob o valor; no desktop ficam alinhados verticalmente entre competências, o que é o que permite varrer a coluna e ver a tendência sem ler número por número. **É o único ganho real de leitura do desktop nesta tela, e é o motivo de a lista virar tabela.**
- Entra a coluna `Quanto vendeu`, que no mobile fica de fora por espaço — é contexto útil, não essencial.
- **Seis colunas**, dentro do limite de 5–7 de `mobile-desktop-parity.md`.
- **A linha de mês não lançado atravessa a tabela com borda tracejada** e não preenche as colunas numéricas. É o item 3 do DDR-005 em forma de tabela: a ausência é estrutural, não um traço numa célula.

### Tablet (768–1023px)

Herda **mobile** (lista de cards). A tabela de 6 colunas não cabe confortavelmente abaixo de 1024px.

## 4. Estados

### 4.1. Caminho feliz

Sketches da §3.

### 4.2. Loading

Skeleton com a forma da lista: 5 blocos de linha em `--color-neutral`, com cabeçalho de ano já visível. Nunca spinner.

`STORY-051` CA-11 exige ≤2s p95 com 24 competências. Se a série passar a demorar, o skeleton é o que segura a percepção — e a saída registrada no `ADR-007` é cache de leitura, nunca persistir comparativo.

### 4.3. Vazio — nenhuma competência lançada

Estado de primeira vez, e uma das primeiras telas que o usuário verá.

```
+--------------------------------------------------+
| Todos os meses                                   |
| Padaria do Bairro · Comércio                     |
+--------------------------------------------------+
|  ┌────────────────────────────────────────────┐  |
|  │                                            │  |
|  │  Você ainda não lançou nenhum mês para     │  |
|  │  esta empresa.                             │  |
|  │                                            │  |
|  │  O primeiro lançamento leva alguns         │  |
|  │  minutos. Do segundo em diante, a maior    │  |
|  │  parte já vem preenchida.                  │  |
|  │                                            │  |
|  │  [ Lançar setembro/2026 ]                  │  |
|  │                                            │  |
|  └────────────────────────────────────────────┘  |
+--------------------------------------------------+
```

Sem ilustração — `tone-and-voice.md` §Do's: ilustração só quando comunica. A segunda frase é a promessa que sustenta a métrica primária do épico, feita no momento em que o usuário decide se começa.

### 4.4. Os quatro estados de competência — `STORY-051` CA-1

| Estado | Aparência | Ação |
|---|---|---|
| **Lançada** | borda sólida; valor e percentual; dois comparativos | `Corrigir` |
| **Retificada** | idem + `pill` `Retificado`; no desktop, `corrigido {n}×` sob o rótulo | `Corrigir` |
| **Não lançada** | **borda tracejada**; sem área de valor; rótulo `não lançado`; frase `Sem lançamento para este mês.` | `Lançar` |
| **Rascunho em aberto** | borda sólida + `pill` `Rascunho`; sem valor (não há lançamento fechado); `você parou em {bloco}` | `Continuar` |

**A distinção que este spec existe para garantir** (DDR-005, item 3):

```
mês com movimento zero      mês não lançado
┌──────────────────────┐    ╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌
│ AGOSTO      lançado  │    ╎ JULHO   não lançado ╎
│ Lucro do mês         │    ╎ Sem lançamento para ╎
│ R$ 0,00       0,0%   │    ╎ este mês.           ╎
│                      │    ╎        [ Lançar ]   ╎
└──────────────────────┘    ╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌
```

Quatro diferenças simultâneas — borda, presença de número, rótulo de estado e presença de CTA. **Nenhuma delas é cor.** Uma só diferença seria frágil demais para a garantia que o `ADR-007` construiu o modelo inteiro para proteger.

Um mês pode acumular estados: **lançado + retificado + atípico** convivem numa linha. Ordem dos `pill`s: `Retificado` antes de `Mês atípico`.

### 4.5. Comparativos e suas indisponibilidades — `STORY-051` CA-3, CA-4

Esta tela mostra os comparativos **do mês inteiro**: a linha de resultado final contra o mês anterior e contra a média móvel. Os quatro comparativos **por linha da demonstração** ficam na Demonstração do Mês — divisão registrada lá e repetida aqui para não haver dúvida.

| Situação | Texto |
|---|---|
| Disponível | `+12,4% em relação a julho` |
| Sem mês anterior (é o 1º) | `primeiro mês lançado` |
| Mês anterior não lançado | `julho não foi lançado, então não dá para comparar` |
| Menos de 3 competências anteriores | `média disponível a partir do 4º mês lançado` |
| Janela inteira sem faturamento | `sem faturamento nos meses anteriores` |

**Nunca `—` sozinho, nunca `0%`, nunca `N/A`** (DDR-005). A indisponibilidade de comparativo é a que mais tenta virar zero, e zero aqui é exatamente a "queda fantasma" que o `estrutura-drm` §4 proíbe.

**Sem cor na variação.** Uma queda de custo é boa e uma queda de lucro é ruim — a direção não tem semântica fixa por linha, e verde/vermelho estariam errados metade do tempo. O sinal (`+` / `−`) e a palavra carregam a informação. Decisão registrada aqui porque contraria a expectativa mais comum em telas financeiras.

### 4.6. Mês atípico (alerta A4) — `STORY-051` CA-5

*"A marcação precisa ser legível como explicação"* — não como advertência.

- `pill` **neutro** `Mês atípico` (nunca `--color-farol-amarelo`, que é semáforo e pertence à `STORY-054`).
- Uma frase de explicação **abaixo do comparativo**, no fluxo de leitura, e não num tooltip: `Dezembro teve 13º salário — por isso o resultado do mês caiu.`
- `<x-help>` ao lado, para quem quiser o contexto de regime de caixa (Anexo K §K.8).
- O mês atípico **entra nos cálculos** (`STORY-051` CA-7). A marcação explica o dado; não o remove. Nada na tela pode sugerir que aquele mês foi descartado.

### 4.7. Prejuízo

Mesmo tratamento da Demonstração do Mês (DDR-004, item 6): valor com sinal, `--color-primary`, `pill` neutro `Prejuízo no mês`. Sem cor.

### 4.8. Granularidade mista — `STORY-051` CA-8

Meses lançados em modo agregado e detalhado convivem na série. **Esta tela não é afetada**: todo comparativo de nível 1 está sempre disponível, porque a estrutura de linhas de nível 1 é idêntica nos dois modos (`ADR-007`).

A indisponibilidade `indisponivel:grupo_agregado` só aparece nos comparativos **por item**, que vivem na Demonstração do Mês. Registrado aqui para que a `STORY-051` não procure um estado que não existe nesta tela.

### 4.9. Salto de competência (alerta A6) — `STORY-051` CA-6

A lacuna **já é visível** como linha de mês não lançado (§4.4) — a espinha de meses é gerada na leitura (`ADR-007`), então nenhum mês some. O alerta A6 vive no **lançamento**, não aqui.

O que esta tela acrescenta: quando há 2 ou mais meses em falta, um `aviso-inline` `info` no topo, com a contagem e um caminho:

```
ⓘ Você tem 3 meses sem lançamento entre janeiro e agosto.
  A comparação com a média fica mais útil quando a série
  está completa.
```

Sem culpa, sem urgência fabricada, e com a razão prática de completar — não um dever.

### 4.10. Erro

| Tipo | Tratamento |
|---|---|
| Empresa inexistente / cross-tenant | **404 silente** (`IDR-009`) |
| Falha ao carregar a série | `aviso-inline` `bloqueio` + `Tentar novamente`, na área da lista. Cabeçalho e CTA primário permanecem — lançar um mês novo não depende de a série ter carregado |
| Erro inesperado (5xx) | Mensagem honesta + suporte, sem stack trace |

### 4.11. Parcial / degradado

Se a lista carrega mas os comparativos falham, **a lista é exibida sem eles**, com um `aviso-inline` `info`: `Não conseguimos calcular as comparações agora. Os valores de cada mês estão corretos.`

É o estado que separa medição de leitura: os valores vêm do snapshot e são confiáveis; os comparativos são derivados na leitura (`ADR-007`) e podem falhar sozinhos. Degradar sem mentir é preferível a uma tela de erro que esconde dado bom.

### 4.12. Sem permissão

Não existe. 404 silente (`IDR-009`).

## 5. Por que não há gráfico no MVP

A estória convida: *"Sem gráfico obrigatório no MVP. Se propuser um, justifique o ganho de leitura no spec."* **Não proponho** — e a razão é específica, não conservadorismo:

1. **A série tem lacunas por desenho.** Um gráfico de linha liga pontos; ligar agosto a junho pulando julho **desenha uma reta onde não há dado**. É a "queda fantasma" do `estrutura-drm` §4 em forma visual, e o produto inteiro foi construído para não fazer isso — inclusive proibindo `COALESCE(…, 0)` no banco. Um gráfico honesto teria de interromper a linha em cada lacuna, e com lacunas frequentes ele vira um tracejado ilegível.
2. **Meses atípicos distorcem a escala.** Dezembro com 13º salário achata visualmente todos os outros meses. O tratamento textual (`pill` + frase) explica; o gráfico só exibe o degrau.
3. **A pergunta do módulo é "este mês deu lucro?"**, e ela é respondida por um número, não por uma tendência. A tendência já está na tela em duas colunas de comparativo, com as indisponibilidades declaradas — que é mais informação do que um gráfico consegue carregar honestamente.
4. **12 pontos não justificam um eixo.** Abaixo de ~20 observações, uma tabela ordenada costuma ser lida mais rápido que um gráfico, e não exige aprender a escala.

**Quando reabrir:** quando existirem usuários com ≥12 competências lançadas **e** telemetria (`STORY-056`) mostrando rolagem repetida da lista sem clique — sinal de que estão procurando uma forma na série que a lista não entrega. Aí o gráfico correto é provavelmente uma barra por mês (que representa lacuna como ausência de barra, sem interpolar), não uma linha.

## 6. Microcopy completo

> **Provisório** — `STORY-052`.

| Lugar | Texto |
|---|---|
| Título | `Todos os meses` |
| Subtítulo | `{Empresa} · {Setor}` |
| CTA primário | `Lançar {mês}/{ano}` |
| Cabeçalho de ano | `{ano}` |
| Rótulo da competência | `{MÊS}` (mês do ano corrente) / `{MÊS}/{ano}` (anos anteriores) |
| Estado — lançada | `lançado` |
| Estado — não lançada | `não lançado` |
| Pill — retificada | `Retificado` |
| Pill — rascunho | `Rascunho` |
| Pill — atípico | `Mês atípico` |
| Pill — prejuízo | `Prejuízo no mês` |
| Rótulo do valor | `Lucro do mês` |
| Sem lançamento | `Sem lançamento para este mês.` |
| Rascunho em aberto | `Você parou em {nome do bloco}.` |
| Retificações (desktop) | `corrigido {n}×` |
| Comparativo — mês anterior | `{+/−}{x,x}% em relação a {mês anterior}` |
| Comparativo — média | `{+/−}{x,x}% em relação à média dos meses anteriores` |
| Indisponível — 1º mês | `primeiro mês lançado` |
| Indisponível — anterior em falta | `{mês} não foi lançado, então não dá para comparar` |
| Indisponível — média | `média disponível a partir do 4º mês lançado` |
| Indisponível — sem faturamento | `sem faturamento nos meses anteriores` |
| Explicação de atípico | `{Mês} teve {rubrica} — por isso o resultado do mês caiu.` |
| Lacunas | `Você tem {n} meses sem lançamento entre {mês} e {mês}. A comparação com a média fica mais útil quando a série está completa.` |
| Ações de linha | `Lançar` / `Continuar` / `Corrigir` |
| Cabeçalhos (desktop) | `Mês` · `Quanto vendeu` · `Lucro do mês` · `vs. mês anterior` · `vs. média` |
| Vazio — título | `Você ainda não lançou nenhum mês para esta empresa.` |
| Vazio — instrução | `O primeiro lançamento leva alguns minutos. Do segundo em diante, a maior parte já vem preenchida.` |
| Vazio — CTA | `Lançar {mês}/{ano}` |
| Erro de carregamento | `Não conseguimos carregar os meses agora.` + `Tentar novamente` |
| Comparativos degradados | `Não conseguimos calcular as comparações agora. Os valores de cada mês estão corretos.` |
| Erro inesperado | `Algo deu errado do nosso lado. Já fomos notificados. Se continuar, fale com o suporte.` |

## 7. Acessibilidade (notas específicas desta tela)

- **Mobile é `<ul>`/`<li>`**, um `<li>` por competência, com `<h3>` no rótulo do mês. Não é sequência de `<div>`.
- **Desktop é `<table>`** com `<th scope="col">` e **`<th scope="row">` no mês**. A linha de mês não lançado é uma `<tr>` normal com uma célula que atravessa as colunas numéricas (`colspan`) — não uma linha com células vazias, que o leitor de tela anunciaria como "vazio, vazio, vazio".
- **Nunca botão dentro de link.** O rótulo do mês é `<a>`; a ação de linha é `<button>`/`<a>` irmão. Nidificação de interativos quebra teclado e leitor de tela.
- **A ação de linha é ambígua fora de contexto** ("Corrigir" o quê?). Cada botão recebe nome acessível completo: `Corrigir agosto de 2026`, `Lançar julho de 2026`, `Continuar o rascunho de junho de 2026`.
- **Estado nunca só visual.** `não lançado` e `lançado` são texto. A borda tracejada é reforço, não o canal.
- **`pill`s** são texto, não ícone. Um `pill` `Mês atípico` é lido como está.
- **Variações sem cor** (§4.5) — o sinal e a frase carregam tudo.
- **Ordem de tabulação:** CTA primário → cada competência (link do mês → ação da linha) → em ordem cronológica descendente, igual à ordem visual.
- **Foco inicial:** topo do documento. Vindo de "criei um lançamento", nada de roubo de foco — a competência recém-criada aparece no topo da lista por ordenação natural.
- **Zoom 200% / 320px de largura efetiva:** o layout de cards vale abaixo de 1024px, então zoom alto no desktop cai no layout de cards e continua utilizável.

### Contraste — verificado

| Uso | Par | Razão | AA |
|---|---|---|---|
| Rótulo do mês, valores | `primary` / `surface` | 15,54:1 | ✅ |
| Estado, comparativos, explicações | `secondary` / `surface` | 7,80:1 | ✅ |
| `pill` (neutro) | `secondary` / `neutral` | 7,38:1 | ✅ |
| Rótulo do CTA primário | `on-primary` / `tertiary` | 4,70:1 | ✅ |
| Cabeçalho de tabela | `secondary` / `neutral` | 7,38:1 | ✅ |

**Restrição:** a borda tracejada de mês não lançado usa `--color-border` (1,23:1) — é reforço visual, **não** o canal da informação, que é textual. Se fosse o único canal, reprovaria.

## 8. Identificadores estáveis sugeridos para E2E

| Elemento | Identificador lógico |
|---|---|
| Lista (mobile) / tabela (desktop) | `drm-serie-lista` / `drm-serie-tabela` |
| Linha da competência | `drm-serie-competencia-{AAAA-MM}` |
| Estado da competência | `drm-serie-estado-{AAAA-MM}` |
| Valor do mês | `drm-serie-valor-{AAAA-MM}` |
| Comparativo vs. anterior | `drm-serie-vs-anterior-{AAAA-MM}` |
| Comparativo vs. média | `drm-serie-vs-media-{AAAA-MM}` |
| Pill de retificação | `drm-serie-retificado-{AAAA-MM}` |
| Pill de atípico | `drm-serie-atipico-{AAAA-MM}` |
| Pill de rascunho | `drm-serie-rascunho-{AAAA-MM}` |
| Ação da linha | `drm-serie-acao-{AAAA-MM}` |
| CTA primário | `drm-serie-lancar` |
| Aviso de lacunas | `drm-serie-lacunas` |
| Estado vazio | `drm-serie-vazio` |

## 9. Exceções ao Design System

| O que diverge | Por quê | Vira DDR? |
|---|---|---|
| Estado `nao-lancado` com borda tracejada e sem área de valor | Ausência precisa ser **estruturalmente** diferente de zero, não numericamente. É a garantia do `ADR-007` chegando à tela | **Sim — DDR-005**, item 3 |
| Variações sem cor (verde/vermelho) | A direção não tem semântica fixa: queda de custo é boa, queda de lucro é ruim. Cor estaria errada metade do tempo | Não — decisão local, registrada aqui |
| `pill` neutro para `Mês atípico` em vez de token de farol | Farol é interpretação e pertence à `STORY-054`. Amarelo aqui seria advertência, e a estória pede explicação | Não — decisão local |
| **Ausência de gráfico** | Justificada na §5 | Não |
| Uma ação por linha, sem menu | Menu de três pontos seria componente novo; uma ação não justifica um | Não |

## 10. Dependências e premissas

- **Série e comparativos:** `ADR-007` — espinha de meses gerada na leitura + `LEFT JOIN` com versões vigentes; ausência ⇒ `indisponivel:sem_lancamento`; `COALESCE(…, 0)` proibido; comparativos derivados, nunca persistidos.
- **Definição dos comparativos:** `estrutura-drm` §5 (bases e condições de indisponibilidade).
- **Rubricas sazonais:** Anexo K §K.8 e `estrutura-drm` §6 — tratamento vigente é caixa + A4 + marcação. Se a `STORY-058` aprovar provisão, esta tela ganha estado novo; nada aqui a impede.
- **Textos finais:** `STORY-052`.
- **Permissões:** 404 silente (`IDR-009`).
- **Divisão com a Demonstração do Mês:** comparativos por linha ficam lá; comparativos do mês inteiro ficam aqui.

### Escalado ao PO

1. **Paginação.** Não há, no MVP. 24 competências (cenário do CA-11) cabem numa lista com cabeçalho de ano. Uma empresa com 5 anos de série teria 60 linhas — ainda utilizável, mas é o ponto em que vale rever. Registro para que a ausência seja deliberada.
2. **Filtro por ano.** Idem. O cabeçalho de ano dá orientação; filtro seria complexidade sem demanda.

## 11. Histórico de mudanças

| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-08-04 | criação — spec completo, 4 estados de competência, indisponibilidades, justificativa da ausência de gráfico | designer (claude-opus-5) | `STORY-046` |
| 2026-08-06 | aprovada pelo PO — `draft` → `ready` | PO (Alexandro) | `PDR-007` §1. Destrava a `STORY-051` |
