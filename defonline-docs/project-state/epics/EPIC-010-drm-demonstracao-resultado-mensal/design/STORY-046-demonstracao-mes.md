---
id: SCREEN-STORY-046-demonstracao-mes
story: STORY-046-design-telas-drm
implemented_by: STORY-049
epic: EPIC-010-drm-demonstracao-resultado-mensal
status: ready
created_at: 2026-08-04
updated_at: 2026-08-06
approved_by: Alexandro
approved_at: 2026-08-06
owner_designer: designer (claude-opus-5)
related_ddrs: [DDR-004, DDR-005, DDR-001]
ds_components_used:
  - page-header
  - card
  - button (secondary, ghost, link)
  - pill
  - help
  - breadcrumb
  - aviso-inline             # novo — DDR-001
  - demonstracao-linha       # novo — DDR-004
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Demonstração do Mês

> Referência: estória `STORY-046-design-telas-drm`. Implementada por `STORY-049`.
> Decisões que valem além desta tela: **DDR-004** (par nominal+percentual, três níveis, cards por trecho da conta) e **DDR-005** (indisponibilidade). Este spec **aplica**.
> **Microcopy provisória** — pacote editorial é `STORY-052`.

## 1. Objetivo da tela

Responder, em uma olhada, à pergunta que dá nome ao módulo: **este mês o negócio deu lucro, e quanto sobrou depois que eu me paguei?**

Tudo o mais na tela existe para sustentar essa resposta com a conta que a produziu.

## 2. Fluxo

### Entrada

- Do fechamento do lançamento (`SCREEN-STORY-046-lancamento-mensal` → `Fechar o mês`) — é o destino do sucesso.
- Da **Série histórica**, ao tocar em uma competência lançada.
- De link direto / bookmark.

Precisa ser verdade antes: sessão autenticada; a Empresa Analisada é do usuário; existe versão vigente da competência. Falha em qualquer um → **404 silente** (`IDR-009`).

### Ações possíveis

| Ação | Tipo | Destino |
|---|---|---|
| Alternar o modo do pró-labore | secundária (`button` variante `link`) | mesma tela, re-renderizada |
| `Corrigir este mês` | secundária | Lançamento em modo retificação (`STORY-050`) |
| `Ver todos os meses` | secundária | Série histórica |
| Expandir ressalvas do lançamento | terciária (`<details>`) | in loco |

**Esta tela não tem ação primária.** É tela de leitura. Declarado aqui porque a regra de ouro do DS reserva `--color-tertiary` a **um** CTA por tela: como não há CTA primário, o `tertiary` é usado apenas no botão-link de troca de modo, e não compete com nada.

### Saída

- Não há submit. Sair é navegar.
- Erro recuperável: ver §4.

## 3. Layout

### Mobile (≥360px)

```
+--------------------------------------------------+
| ‹ Minhas Empresas / Padaria do Bairro / Ago 2026 |
+--------------------------------------------------+
| Agosto de 2026                                   |   page-header (h1)
| Padaria do Bairro · Comércio                     |
+--------------------------------------------------+
|                                                  |
|  ┌────────────────────────────────────────────┐  |
|  │ QUANTO VENDEU                              │  |   destaque 1
|  │ R$ 84.300,00                        100%   │  |
|  └────────────────────────────────────────────┘  |
|  ┌────────────────────────────────────────────┐  |
|  │ SOBROU ANTES DA SUA RETIRADA               │  |   destaque 2
|  │ R$ 11.240,00                       13,3%   │  |
|  └────────────────────────────────────────────┘  |
|  ┌────────────────────────────────────────────┐  |
|  │ SOBROU DEPOIS DA SUA RETIRADA              │  |   destaque 3
|  │ R$ 4.240,00                         5,0%   │  |
|  └────────────────────────────────────────────┘  |
|                                                  |
|  [ espaço da leitura em prosa — STORY-054 ]      |   ← ausente, não vazio
|                                                  |
|  Vendo com a sua retirada fora do resultado      |
|  operacional.  [ Ver com a retirada dentro ] [?] |
|                                                  |
|  ▸ Ressalvas registradas no lançamento (2)       |   <details>
|                                                  |
|  ┌────────────────────────────────────────────┐  |
|  │ Receita                                    │  |   card por trecho
|  │  Receita Bruta                             │  |   da conta (DDR-004)
|  │    R$ 84.300,00                    100,0%  │  |
|  │  (–) Deduções da Receita Bruta             │  |
|  │    R$  5.058,00                      6,0%  │  |
|  │ ────────────────────────────────────────── │  |
|  │  = Receita Operacional Líquida             │  |
|  │    R$ 79.242,00                     94,0%  │  |
|  └────────────────────────────────────────────┘  |
|  ┌────────────────────────────────────────────┐  |
|  │ Custo e lucro bruto                        │  |
|  │  (–) Custo das Mercadorias Vendidas        │  |
|  │    R$ 41.200,00                     48,9%  │  |
|  │ ────────────────────────────────────────── │  |
|  │  = Lucro Bruto (Margem Bruta)              │  |
|  │    R$ 38.042,00                     45,1%  │  |
|  └────────────────────────────────────────────┘  |
|  ┌────────────────────────────────────────────┐  |
|  │ Despesas operacionais                      │  |
|  │  (–) Despesas Operacionais                 │  |
|  │    R$ 26.802,00                     31,8%  │  |
|  │      · Despesas com Vendas                 │  |   nível 2
|  │        R$  6.100,00                  7,2%  │  |
|  │      · Despesas Administrativas e Gerais   │  |
|  │        R$ 20.702,00                 24,6%  │  |
|  │ ────────────────────────────────────────── │  |
|  │  = Resultado Operacional                   │  |
|  │    R$ 11.240,00                     13,3%  │  |
|  └────────────────────────────────────────────┘  |
|  ┌────────────────────────────────────────────┐  |
|  │ Financeiras                                │  |
|  │  (–) Despesas Financeiras                  │  |
|  │    R$  1.180,00                      1,4%  │  |
|  │      · Juros pagos      R$ 940,00    1,1%  │  |
|  │      · Tarifas bancárias R$ 240,00   0,3%  │  |
|  │ ────────────────────────────────────────── │  |
|  │  = Resultado antes do pró-labore           │  |
|  │    R$ 10.060,00                     11,9%  │  |
|  └────────────────────────────────────────────┘  |
|  ┌────────────────────────────────────────────┐  |
|  │ Resultado                                  │  |
|  │  (–) Pró-labore                            │  |
|  │    R$  7.000,00                      8,3%  │  |
|  │ ────────────────────────────────────────── │  |
|  │  = Lucro Líquido do Mês (Margem Líquida)   │  |
|  │    R$  4.240,00                      5,0%  │  |
|  └────────────────────────────────────────────┘  |
|                                                  |
|  motor v1.0.0 · gerado em 04/09/2026 14:22       |   rodapé do snapshot
|                                                  |
|  [ Corrigir este mês ]   [ Ver todos os meses ]  |
+--------------------------------------------------+
```

- **Ordem de leitura:** a resposta primeiro (três destaques), a conta depois. Quem só quer saber se deu lucro para na primeira dobra.
- **Os três destaques são `<x-card>`** com rótulo em `label` (uppercase, `--color-secondary`) e valor em `--text-h2` / peso 500 / `--color-primary`, com o percentual à direita em `--text-body` / `--color-secondary`.
- **Alinhamento:** `tabular-nums` e alinhamento à direita em todos os números, para comparar ordens de grandeza sem ler dígito a dígito.
- **Não há scroll horizontal:** o rótulo ocupa a linha inteira e os números vêm na linha seguinte, à direita.

### Desktop (≥1024px)

```
+---------+------------------------------------------------------------------+
| nav     | ‹ Minhas Empresas / Padaria do Bairro / Agosto de 2026           |
| lateral | Agosto de 2026                                                   |
|         | Padaria do Bairro · Comércio                                     |
|         +------------------------------------------------------------------+
|         | ┌──────────────┐ ┌──────────────────┐ ┌──────────────────────┐  |
|         | │ QUANTO VENDEU│ │ SOBROU ANTES DA  │ │ SOBROU DEPOIS DA     │  |
|         | │ R$ 84.300,00 │ │ SUA RETIRADA     │ │ SUA RETIRADA         │  |
|         | │ 100%         │ │ R$ 11.240,00     │ │ R$ 4.240,00          │  |
|         | │              │ │ 13,3%            │ │ 5,0%                 │  |
|         | └──────────────┘ └──────────────────┘ └──────────────────────┘  |
|         |                                                                  |
|         | [ espaço da leitura em prosa — STORY-054 ]                       |
|         |                                                                  |
|         | Vendo com a sua retirada fora do resultado operacional.          |
|         | [ Ver com a retirada dentro ] [?]                                |
|         |                                                                  |
|         | ▸ Ressalvas registradas no lançamento (2)                        |
|         |                                                                  |
|         | ┌──────────────────────────────────────────────────────────────┐ |
|         | │ Linha                                 |    Valor  | % receita │ |
|         | │───────────────────────────────────────|───────────|───────────│ |
|         | │ Receita Bruta                         | 84.300,00 |    100,0% │ |
|         | │ (–) Deduções da Receita Bruta         |  5.058,00 |      6,0% │ |
|         | │▓= Receita Operacional Líquida         | 79.242,00 |     94,0% │ |
|         | │ (–) Custo das Mercadorias Vendidas    | 41.200,00 |     48,9% │ |
|         | │▓= Lucro Bruto (Margem Bruta)          | 38.042,00 |     45,1% │ |
|         | │ (–) Despesas Operacionais             | 26.802,00 |     31,8% │ |
|         | │      Despesas com Vendas              |  6.100,00 |      7,2% │ |
|         | │      Despesas Administrativas e Gerais| 20.702,00 |     24,6% │ |
|         | │▓= Resultado Operacional               | 11.240,00 |     13,3% │ |
|         | │ (–) Despesas Financeiras              |  1.180,00 |      1,4% │ |
|         | │      Juros pagos                      |    940,00 |      1,1% │ |
|         | │      Tarifas bancárias                |    240,00 |      0,3% │ |
|         | │▓= Resultado antes do pró-labore       | 10.060,00 |     11,9% │ |
|         | │ (–) Pró-labore                        |  7.000,00 |      8,3% │ |
|         | │▓= Lucro Líquido do Mês                |  4.240,00 |      5,0% │ |
|         | └──────────────────────────────────────────────────────────────┘ |
|         | motor v1.0.0 · gerado em 04/09/2026 14:22                        |
|         | [ Corrigir este mês ]              [ Ver todos os meses ]         |
+---------+------------------------------------------------------------------+
```

`▓` = fundo `--color-neutral` + régua superior `--color-border` (linha totalizadora, nível 1).

**O que o espaço extra faz** (declaração obrigatória):

- **Os três destaques deixam de empilhar e viram três colunas**, o que os coloca todos na primeira dobra junto com o topo da conta — no mobile é impossível.
- **Os cards por trecho colapsam numa tabela única.** No desktop a régua e o recuo bastam para separar os trechos; a borda de card seria ruído. É a mesma informação com um separador a menos, não um layout diferente.
- **A largura sobra de propósito.** Rótulos em português são longos ("Despesas Administrativas e Gerais"); a folga existe para eles não quebrarem. Registrado para não ser lido como "desktop empilhado".
- Container: `max-width: 960px` do shell.

### Tablet (768–1023px)

Herda **mobile** (destaques empilhados, cards por trecho). Sem comportamento próprio.

## 4. Estados

### 4.1. Caminho feliz

Sketches da §3.

### 4.2. Loading

Skeleton com a **forma da tela**, não spinner: três retângulos de destaque + cinco blocos de linhas em `--color-neutral`. A tela lê um snapshot persistido (`STORY-049` CA-8, não invoca motor), então o estado tende a ser imperceptível — desenhado mesmo assim, porque rede ruim em celular é o contexto real.

### 4.3. Vazio

**Não existe estado vazio nesta tela.** Ou há versão vigente da competência, ou é 404 silente. Uma demonstração sem lançamento é uma URL inválida, não uma tela vazia. Registrado para que não seja desenhado por engano.

O estado "esta empresa ainda não lançou nada" pertence à **Série histórica**.

### 4.4. Sem faturamento (`D01 = 0`) — Anexo K §K.7

O estado mais importante do módulo, nas palavras do próprio Anexo K: *"é a competência mais importante de todas para o usuário ver: quanto custou manter o negócio parado."*

Aplicação do DDR-005: a **coluna inteira** de percentuais está indisponível pelo mesmo motivo, então o motivo é dito **uma vez**, acima da conta, e cada célula carrega só a marca `—`.

```
+--------------------------------------------------+
|  ┌────────────────────────────────────────────┐  |
|  │ QUANTO VENDEU                              │  |
|  │ R$ 0,00                                —   │  |
|  └────────────────────────────────────────────┘  |
|  ┌────────────────────────────────────────────┐  |
|  │ FALTOU DEPOIS DA SUA RETIRADA              │  |
|  │ −R$ 8.430,00                           —   │  |
|  │ [Prejuízo no mês]                          │  |
|  └────────────────────────────────────────────┘  |
|                                                  |
|  ┌────────────────────────────────────────────┐  |
|  │ ⓘ Sem faturamento neste mês — os           │  |
|  │   percentuais sobre a receita não se       │  |
|  │   aplicam. Os valores em reais continuam   │  |
|  │   abaixo.                                  │  |
|  └────────────────────────────────────────────┘  |
|                                                  |
|  ┌────────────────────────────────────────────┐  |
|  │ Despesas operacionais                      │  |
|  │  (–) Despesas Operacionais                 │  |
|  │    R$ 8.430,00                          —  │  |  ← o número que importa
|  └────────────────────────────────────────────┘  |
```

- Frase **verbatim** do Anexo K §K.7, acrescida de uma oração que aponta para o que ficou.
- `aviso-inline` variante **`info`**, nunca `alerta` nem `bloqueio`: não é erro, é um mês legítimo (obra parada, sazonalidade — alerta A1).
- **Nenhuma opacidade reduzida na linha inteira** (DDR-005, item 5). Só a célula de percentual é afetada; os valores nominais têm o mesmo peso visual de sempre.
- O 2º destaque também exibe `—` no percentual; **não desaparece**, porque seu valor nominal existe.

### 4.5. Prejuízo — `STORY-049` CA-6

**Sinalizado, não dramatizado** (DDR-004, item 6):

- Valor com sinal: `−R$ 3.240,00`, em `--color-primary`. **Sem cor.** Vermelho no produto significa erro (`--color-destructive`) ou farol (`--color-farol-vermelho`, que é interpretação e pertence à `STORY-054`); nenhum dos dois é o que um prejuízo é.
- O 3º destaque troca o verbo: **`SOBROU DEPOIS DA SUA RETIRADA` → `FALTOU DEPOIS DA SUA RETIRADA`**. É o sinal que se lê antes do número, e funciona para quem não distingue cores.
- `pill` neutro `Prejuízo no mês` junto ao rótulo da linha final, na tabela e no card.
- O usuário **já viu** o alerta A3 no fechamento do lançamento; aqui não se repete a ressalva, apenas se registra o fato.

### 4.6. Linhas não operacionais (L7b) — `PDR-004` decisão E

L7b é **omitida quando `D41` e `D42` são zero** (`estrutura-drm` §3.3). Omissão é ausência de linha, não linha zerada — e como o render é genérico sobre o array do snapshot, isso sai de graça: o motor não emite a linha, a tela não a desenha. Nenhum tratamento especial.

### 4.7. Modo do pró-labore — `STORY-049` CA-3b

O `PDR-004` exige que **o modo vigente seja exibido junto à demonstração**. A construção é uma frase que declara o estado atual seguida do botão que oferece o outro:

```
Vendo com a sua retirada fora do resultado operacional.
[ Ver com a retirada dentro ]  [?]
```

- Frase em `--color-secondary`; o botão é `<x-button variant="link">` — **`<button>`, não `<a>`**: muda estado, não navega.
- `<x-help>` explica a diferença em uma frase (conteúdo da `STORY-052`).
- **Trocar não recalcula nada** (`ADR-007`): é escolher o outro array do snapshot e gravar `posicao_prolabore` em `drm_preferencias`. A troca vale para toda a série, inclusive meses antigos.
- **O que muda visualmente:** no modo `dentro`, as linhas L9 e L10 desaparecem, o pró-labore vira item de nível 3 dentro das Despesas Administrativas, e L7 passa a se chamar `Resultado Operacional (EBITDA)`. **O 2º destaque acompanha:** `SOBROU ANTES DA SUA RETIRADA` (L9) dá lugar a `RESULTADO OPERACIONAL (EBITDA)` (L7), pela regra de seleção do DDR-004, item 3.
- **O Lucro Líquido do Mês é idêntico nos dois modos** (`estrutura-drm` §1.3). A tela não precisa dizer isso, mas não pode contradizê-lo — nenhum arredondamento de apresentação pode fazer os dois modos exibirem valores finais diferentes.

### 4.8. Ressalvas do lançamento — `STORY-049` CA-7

Os alertas que o usuário aceitou (`alertas_aceitos`, coluna própria) aparecem num `<details>` recolhido, no mesmo padrão do glossário do relatório de Diagnóstico em produção:

```
▸ Ressalvas registradas no lançamento (2)
   • Você não informou nenhuma venda neste mês.
   • Este mês fechou com prejuízo de R$ 3.240,00.
```

Recolhido por padrão: a ressalva contextualiza o número, não compete com ele. **Não é `aviso-inline`** — não pede ação e não é novidade para o usuário, que já a aceitou.

Sem ressalvas, o `<details>` **não é renderizado** (não existe "Ressalvas (0)").

### 4.9. Mês retificado — `STORY-050` CA-4

`pill` `Retificado` ao lado do título, e uma linha no rodapé:

```
Agosto de 2026  [Retificado]
…
motor v1.0.0 · gerado em 04/09/2026 14:22 · corrigido 1× — última correção em 12/10/2026
```

Tudo derivado da linha vigente (`versao > 1`; quantas vezes = `versao − 1`; quando = `gerado_em`) — sem coluna extra e sem `COUNT` (`ADR-007`).

### 4.10. Erro

| Tipo | Tratamento |
|---|---|
| Competência inexistente / cross-tenant | **404 silente** (`IDR-009`). Página de erro do shell. |
| Falha ao carregar | `aviso-inline` `bloqueio` + `Tentar novamente`. Ocupa a área da conta; o cabeçalho permanece. |
| Snapshot com estrutura de linhas desconhecida | **Renderiza normalmente.** É o cenário do CA-8b: o render é genérico. Linha de código desconhecido cai no card corrente; nunca derruba a tela, nunca exibe erro ao usuário. |
| Erro inesperado (5xx) | Mensagem honesta + caminho de suporte, sem stack trace. |

### 4.11. Sem permissão

Não existe. 404 silente (`IDR-009`).

### 4.12. Espaço reservado para farol e leitura (`STORY-054`)

A exigência da estória é *"sem que a tela pareça incompleta antes deles"*. A solução é **ausência, não vazio**:

- **Antes da `STORY-054`:** não existe coluna de farol nem card de leitura. A tabela tem três colunas e a tela começa nos destaques. Nada a explicar, porque não há buraco.
- **Depois:** o farol entra como badge à direita do rótulo, **dentro da célula que já existe** (mesmo padrão do `card-indicador` do Diagnóstico); a leitura entra num `<x-card>` acima da conta, na posição que o `resumo-executivo` ocupa no relatório de Diagnóstico. **Nenhum dos dois muda a grade.**
- Uma coluna de farol vazia foi rejeitada explicitamente: coluna com cabeçalho e nenhum conteúdo é exatamente o que "parecer incompleto" quer dizer.

**Restrição herdada:** os tokens de farol reprovam AA como texto pequeno (`farol-verde` 3,39:1; `farol-amarelo` 3,19:1; `farol-cinza` 2,56:1 sobre branco). O que a `STORY-059` decidir para o Diagnóstico vale aqui sem renegociação. A `STORY-054` **não** deve inventar tratamento próprio.

## 5. Microcopy completo

> **Provisório** — `STORY-052`. Os rótulos das linhas **não estão aqui**: vêm resolvidos de dentro do snapshot (`ADR-007`), fixados em `estrutura-drm` §1. A tela não os reescreve.

| Lugar | Texto |
|---|---|
| Título | `{Mês} de {ano}` |
| Subtítulo | `{Empresa} · {Setor}` |
| Destaque 1 | `QUANTO VENDEU` |
| Destaque 2 (modo `fora`) | `SOBROU ANTES DA SUA RETIRADA` |
| Destaque 2 (modo `dentro`) | `RESULTADO OPERACIONAL (EBITDA)` |
| Destaque 3 (positivo) | `SOBROU DEPOIS DA SUA RETIRADA` |
| Destaque 3 (negativo) | `FALTOU DEPOIS DA SUA RETIRADA` |
| Pill de prejuízo | `Prejuízo no mês` |
| Pill de retificação | `Retificado` |
| Modo vigente (`fora`) | `Vendo com a sua retirada fora do resultado operacional.` |
| Modo vigente (`dentro`) | `Vendo com a sua retirada dentro das despesas operacionais.` |
| Troca de modo (`fora`→`dentro`) | `Ver com a retirada dentro` |
| Troca de modo (`dentro`→`fora`) | `Ver com a retirada fora` |
| Sem faturamento | `Sem faturamento neste mês — os percentuais sobre a receita não se aplicam. Os valores em reais continuam abaixo.` |
| Percentual indisponível (texto acessível da célula) | `Percentual indisponível: sem faturamento neste mês` |
| Ressalvas | `Ressalvas registradas no lançamento ({n})` |
| Cabeçalhos da tabela | `Linha` · `Valor` · `% da receita` |
| Rodapé | `motor v{versão} · gerado em {data} {hora}` |
| Rodapé (retificado) | `… · corrigido {n}× — última correção em {data}` |
| CTA secundário | `Corrigir este mês` |
| CTA secundário | `Ver todos os meses` |
| Erro de carregamento | `Não conseguimos carregar a demonstração agora.` + `Tentar novamente` |
| Erro inesperado | `Algo deu errado do nosso lado. Já fomos notificados. Se continuar, fale com o suporte.` |

**Nomes dos trechos (cards do mobile)** — provisórios, e vivem na mesma configuração que decide onde o card fecha (DDR-004, item 4): `Receita` · `Custo e lucro bruto` · `Despesas operacionais` · `Financeiras` · `Resultado`.

## 6. Acessibilidade (notas específicas desta tela)

- **Desktop é `<table>` de verdade.** `<caption>` (`sr-only`) descrevendo a demonstração; `<th scope="col">` nos cabeçalhos; **`<th scope="row">` no rótulo de cada linha**. É o que faz um leitor de tela anunciar "Lucro Bruto, Valor, 38.042,00" em vez de ler números soltos.
- **Mobile é `<dl>` dentro de cada card**, com `<dt>` = rótulo e `<dd>` = valor e percentual. Cada card tem `<h3>` com o nome do trecho.
- **Hierarquia não é só recuo.** Níveis 2 e 3 usam recuo **e** tamanho (`--text-body-sm`) **e** cor (`--color-secondary`) **e**, no mobile, o marcador `·`. Recuo sozinho não é anunciado por leitor de tela — a nidificação do `<dl>`/a ordem das linhas carrega a estrutura.
- **Célula indisponível:** `—` visual **mais** texto acessível com o motivo (DDR-005, item 1). `—` sozinho não é informação.
- **Prejuízo:** comunicado por sinal aritmético, pela troca de verbo no destaque e pelo `pill` textual — **três canais, nenhum deles cor**.
- **Troca de modo:** `<button>`, não link. Após a troca, a mudança é anunciada por `aria-live="polite"` (o número de linhas muda; sem anúncio, quem usa leitor de tela não percebe).
- **Ordem de tabulação:** breadcrumb → troca de modo → `<details>` de ressalvas → tabela (não focável) → `Corrigir este mês` → `Ver todos os meses`.
- **Foco inicial:** nenhum roubo de foco. Tela de leitura começa no topo do documento.
- **Zoom 200%:** a tabela do desktop passa a caber pior; é aceitável porque abaixo de 1024px o layout de cards assume, e o layout de cards não tem largura mínima problemática.

### Contraste — verificado

| Uso | Par | Razão | AA |
|---|---|---|---|
| Valores e rótulos de nível 1 | `primary` / `surface` | 15,54:1 | ✅ |
| Linha totalizadora (fundo destacado) | `primary` / `neutral` | 14,70:1 | ✅ |
| Níveis 2 e 3, percentuais, rodapé | `secondary` / `surface` | 7,80:1 | ✅ |
| Níveis 2 e 3 sobre linha destacada | `secondary` / `neutral` | 7,38:1 | ✅ |
| `pill` (neutro) | `secondary` / `neutral` | 7,38:1 | ✅ |
| Botão-link de troca de modo | `tertiary` / `surface` | 4,70:1 | ✅ |

**Restrição:** o botão-link de troca de modo fica sobre `--color-surface`. Sobre `--color-neutral` o `tertiary` mede **4,45:1** e reprovaria. Registrado no DDR-002 e em `design/system/tokens.md`.

**Herdado, não criado aqui:** os tokens de farol reprovam AA como texto pequeno — dívida da `STORY-059`, que vale para o slot reservado da §4.12.

## 7. Identificadores estáveis sugeridos para E2E

| Elemento | Identificador lógico |
|---|---|
| Destaque N | `drm-demonstracao-destaque-{1..3}` |
| Tabela (desktop) | `drm-demonstracao-tabela` |
| Cards (mobile) | `drm-demonstracao-cards` |
| Linha, por código do snapshot | `drm-demonstracao-linha-{codigo}` |
| Valor nominal da linha | `drm-demonstracao-valor-{codigo}` |
| Percentual da linha | `drm-demonstracao-pct-{codigo}` |
| Card de trecho | `drm-demonstracao-trecho-{slug}` |
| Modo vigente | `drm-demonstracao-modo-vigente` |
| Troca de modo | `drm-demonstracao-trocar-modo` |
| Aviso de sem faturamento | `drm-demonstracao-sem-faturamento` |
| Pill de prejuízo | `drm-demonstracao-prejuizo` |
| Pill de retificação | `drm-demonstracao-retificado` |
| Ressalvas | `drm-demonstracao-ressalvas` |
| Rodapé de versões | `drm-demonstracao-rodape` |
| Corrigir | `drm-demonstracao-corrigir` |
| Ver série | `drm-demonstracao-ver-serie` |

## 8. Exceções ao Design System

| O que diverge | Por quê | Vira DDR? |
|---|---|---|
| `demonstracao-linha` — componente novo | O `linha-indicador` do Diagnóstico não serve: lá cada linha é objeto independente; aqui são elos de uma conta, com três níveis e o par nominal+percentual | **Sim — DDR-004** |
| Card por **trecho da conta**, não por linha, no mobile | Um card por linha cortaria a subtração que o usuário precisa ver. Divergência deliberada do relatório de Diagnóstico, justificada pela natureza do conteúdo | **Sim — DDR-004** |
| Variante `link` do `<x-button>` | O DS tem `primary/secondary/ghost`. A troca de modo muda estado e não navega — `<a>` seria semântica errada, e `ghost` teria peso de botão numa linha de texto corrido | **Sim — DDR-004** (§Impacto no DS) |
| Prejuízo sem cor | Vermelho no produto já significa erro ou farol. Sinal + verbo + pill cobrem o caso com três canais não cromáticos | **Sim — DDR-004**, item 6 |
| Marca `—` para indisponível, em vez de `Indisponível` por extenso como no Diagnóstico | Repetir a palavra em até 17 linhas seria o mesmo erro de escala do DDR-003. O motivo é dito uma vez, no escopo em que vale | **Sim — DDR-005** |

## 9. Dependências e premissas

- **Snapshot:** `ADR-007`. A tela lê **só** a versão vigente e **nunca** invoca o motor. Render genérico sobre `demonstracao.linhas.{fora|dentro}[]`, sem hardcode de `L1..L11`.
- **Estrutura das linhas:** `estrutura-drm` §1 (ordem canônica e rótulos, resolvidos dentro do snapshot).
- **Textos finais:** `STORY-052`.
- **Farol e leitura:** `STORY-054`, com a restrição de contraste da `STORY-059`.
- **Comparativos por linha:** `STORY-051`. **Vêm para esta tela**, não para a Série histórica — ver abaixo.
- **Permissões:** 404 silente (`IDR-009`).

### Divisão de trabalho com a Série histórica

Decidida aqui para as duas telas não brigarem quando a `STORY-051` chegar:

- **Esta tela** recebe os **quatro comparativos por linha de nível 1** (`estrutura-drm` §5): variação vs. mês anterior, vs. média móvel, acumulado no ano e participação média. É onde as linhas existem. Entram num bloco expansível por linha totalizadora — **não** como colunas novas, que empurrariam a tabela para scroll horizontal no desktop e seriam impossíveis no mobile.
- **A Série histórica** recebe os comparativos **do mês inteiro** (o resultado final contra o mês anterior e contra a média), um por competência da lista.
- Nenhuma das duas duplica a outra.

### Proposta ao Arquiteto / `STORY-048` — barata agora, cara depois

Duas regras desta tela hoje dependem de configuração porque o snapshot não diz o suficiente sobre cada linha:

1. **Quais linhas são os três destaques** (DDR-004, item 3).
2. **Onde um card de trecho fecha** no mobile (DDR-004, item 4).

Se cada linha do snapshot carregasse dois campos a mais — `tipo` (`total | deducao | subtotal | item`) e `destaque` (bool) — as duas regras sairiam da configuração e passariam a ser propriedade do dado, exatamente como `ADR-007` fez com os rótulos e pelo mesmo motivo registrado lá.

**O motor ainda não foi construído** (`STORY-048` não começou), então o custo agora é ~zero. Depois seria mudança em snapshot imutável — o beco do `IDR-013`.

**A tela não depende disso.** A regra por configuração está especificada e tem fallback que não quebra. É proposta, não requisito — e a decisão é do Arquiteto com o PO, não minha.

## 10. Histórico de mudanças

| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-08-04 | criação — spec completo, 12 estados, dois modos de pró-labore | designer (claude-opus-5) | `STORY-046` |
| 2026-08-06 | aprovada pelo PO — `draft` → `ready` | PO (Alexandro) | `PDR-007` §1. Destrava a `STORY-049` |
