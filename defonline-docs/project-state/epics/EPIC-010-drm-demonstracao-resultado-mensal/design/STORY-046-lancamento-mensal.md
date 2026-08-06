---
id: SCREEN-STORY-046-lancamento-mensal
story: STORY-046-design-telas-drm
implemented_by: STORY-047
epic: EPIC-010-drm-demonstracao-resultado-mensal
status: draft
created_at: 2026-08-04
updated_at: 2026-08-04
owner_designer: designer (claude-opus-5)
related_ddrs: [DDR-001, DDR-002, DDR-003, DDR-005]
ds_components_used:
  - page-header
  - card
  - button (primary, secondary, ghost, link)
  - label
  - campo-monetario          # .input-affix em produção (STORY-027)
  - help
  - pill
  - breadcrumb
  - progresso-blocos         # novo — DDR-001
  - aviso-inline             # novo — DDR-001
  - seletor-modo             # novo — DDR-002
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Lançamento mensal do DRM

> Referência: estória `STORY-046-design-telas-drm` (CAs e contexto vêm de lá — **não duplico**).
> Implementada por `STORY-047`. Decisões de design que valem além desta tela estão nos DDRs 001, 002, 003 e 005 — este spec **aplica**, não redecide.
> **Toda a microcopy deste spec é provisória** e será substituída pelo pacote editorial da EBC (`STORY-052`). Está marcada como tal na §5.

## 1. Objetivo da tela

Registrar o movimento de caixa de **uma competência** de uma Empresa Analisada, em poucos minutos, de um celular, por alguém que não gosta de planilha.

O antagonista desta tela não é a complexidade — é o **abandono no segundo mês**. Cada decisão abaixo existe para atacar isso.

## 2. Fluxo

### Entrada

- De `Empresa Analisada → Lançar o mês` (CTA primário quando há mês em aberto).
- Da **Série histórica** (`SCREEN-STORY-046-serie-historica`): botão `Lançar` de uma competência em falta, ou `Continuar` de um rascunho em aberto.
- Do alerta A6 (salto de competência), que oferece lançar o mês faltante.
- Da **Demonstração do Mês** → `Corrigir este mês` (retificação, `STORY-050`): mesma tela, com as diferenças da §4.9.

Precisa ser verdade antes: sessão autenticada; a Empresa Analisada pertence ao usuário (senão **404 silente**, `IDR-009` — ver §4.7); o setor da empresa está definido (dele saem os campos visíveis).

### Ações possíveis

| Ação | Tipo | Destino |
|---|---|---|
| `Próximo` — salva o bloco e avança | primária, por bloco | próximo bloco |
| `Voltar` — retrocede sem perder nada | secundária | bloco anterior |
| Trocar o modo de um grupo | secundária, no bloco do grupo | mesmo bloco, re-renderizado |
| `Conferi este bloco` — limpa as marcas de herança do bloco | secundária | mesmo bloco |
| `Conferir tudo e ir ao fechamento` | secundária, só no bloco 1 | bloco 6 |
| `Fechar o mês` | primária, só no bloco 6 | Demonstração do Mês |
| Sair (navegação do shell) | — | rascunho fica salvo até o último bloco concluído |

### Saída

- **Sucesso:** redireciona para a Demonstração do Mês da competência recém-fechada.
- **Cancelamento:** não existe botão de cancelar. Sair é sair; o rascunho fica. Descartar rascunho **não está no MVP** — anotado na §9.
- **Erro recuperável:** permanece no bloco, com o erro associado ao campo ou ao bloco. Nunca perde o que foi digitado.

### Os seis blocos

O fatiamento segue os grupos do Anexo K, para que a escolha de modo (DDR-002) possa colapsar **um bloco inteiro** em um campo. É o que torna o mecanismo visível na barra de progresso.

| # | Bloco | Campos (Anexo K) | Agregado | Detalhado (Ind. / Com. / Serv.) |
|---|---|---|---|---|
| 1 | O mês e o que entrou | D00, D01, D02 | 3 | 3 |
| 2 | Custo do que foi vendido¹ | D03 ou D04–D07 | 1 | 4 / 3 / 2 |
| 3 | Despesas com vendas | D08 ou D09–D14 | 1 | 6 |
| 4 | Despesas administrativas e gerais | D15 ou D16–D36 | 1 | 21 |
| 5 | Sua retirada e as despesas financeiras | D40 + (D37 ou D38–D39) + D41, D42 | 2 (+2 recolhidos) | 3 (+2 recolhidos) |
| 6 | Conferir e fechar | — | — | — |

¹ Rótulo por setor (Anexo K §K.4): **CPV** (Indústria) / **CMV** (Comércio) / **Custo dos Serviços Prestados** (Serviços).

**Contagem total:** 38 campos monetários em modo detalhado na Indústria (37 no Comércio, 36 nos Serviços — a diferença é a supressão de D05 e D04); **9 em modo agregado**, dois dos quais (D41/D42) tipicamente ficam zerados e recolhidos.

> **Nota ao PO.** A `STORY-046` cita "36 campos" em dois trechos; o Anexo K **v1.1** (pós-`PDR-004`, que aprovou D41/D42) fixa **38**. Não é divergência de escopo — 36 é o número correto para o setor Serviços. Este spec usa 38 como o teto e declara o número por setor onde ele importa (o custo em campos do `seletor-modo`).

## 3. Layout

### Mobile (≥360px) — bloco de grupo, o caso difícil

```
+--------------------------------------------------+
| ‹ Minhas Empresas / Padaria do Bairro / Lançar   |   breadcrumb
+--------------------------------------------------+
| Lançar agosto/2026                               |   page-header (h1)
| Padaria do Bairro · Comércio                     |   subtítulo
+--------------------------------------------------+
| ███ ███ ███ ▒▒▒ ▒▒▒ ▒▒▒                          |   progresso-blocos
| Bloco 4 de 6 · Despesas administrativas e gerais |
+--------------------------------------------------+
| ┌──────────────────────────────────────────────┐ |
| │ ⓘ 19 valores vieram de julho/2026.           │ |   aviso-inline info
| │   Confira e ajuste o que mudou.              │ |   (DDR-003)
| │   [ Conferi este bloco ]                     │ |
| └──────────────────────────────────────────────┘ |
|                                                  |
| Como você quer informar?                    [?]  |   seletor-modo
| +---------------------+----------------------+   |   (DDR-002)
| | (•) Informar        | ( ) Detalhar         |   |
| |     o total         |     por item         |   |
| |     1 campo         |     21 campos        |   |
| +---------------------+----------------------+   |
| Com o total, a demonstração do mês fica          |
| correta do mesmo jeito. Detalhando, o DRM        |
| também consegue apontar qual item subiu.         |
|                                                  |
| ────────────────────────────────────────────     |
|                                                  |
| SALÁRIOS                                    [?]  |   campo-monetario
| [ R$                            12.400,00 ]      |   (.input-affix)
| veio de julho                                    |   marca de herança
|                                                  |
| ENCARGOS TRABALHISTAS (FGTS, INSS)          [?]  |
| [ R$                             3.100,00 ]      |
| veio de julho                                    |
|                                                  |
| ALUGUEL                                     [?]  |
| [ R$                             3.200,00 ]      |
|                                                  |   ← sem marca: já editado
| … (demais itens do grupo)                        |
+--------------------------------------------------+
| [ Voltar ]                         [ Próximo ]   |
| Você pode sair e voltar depois — o que já        |
| preencheu fica salvo.                            |
+--------------------------------------------------+
```

- **Hierarquia:** progresso → o que mudou desde a última vez (herança) → como informar (modo) → os campos. O usuário decide o custo do bloco **antes** de ver 21 campos.
- **Componentes do DS:** `breadcrumb`, `page-header`, `progresso-blocos`, `aviso-inline`, `seletor-modo`, `label`, `campo-monetario`, `help`, `button`.
- **Espaçamento:** `--spacing-md` entre campos, `--spacing-lg` entre as zonas (aviso / seletor / campos). A régua entre o seletor e os campos é `--color-border`.
- **Alvo de toque:** campos e botões ≥ 44px (`--touch-target-min`, já garantido por `.input-affix` e `.btn`); as duas opções do `seletor-modo` também.
- **Sem scroll horizontal em 360px.** O rótulo do campo fica **acima** do input (nunca ao lado), e o valor é alinhado à direita dentro do `.input-affix`, como já acontece no Quiz.

### Mobile — bloco 1, com a oferta de conferência total

```
+--------------------------------------------------+
| ███ ▒▒▒ ▒▒▒ ▒▒▒ ▒▒▒ ▒▒▒                          |
| Bloco 1 de 6 · O mês e o que entrou              |
+--------------------------------------------------+
| COMPETÊNCIA                                 [?]  |
| [ agosto/2026                             ▾ ]    |
|                                                  |
| ┌──────────────────────────────────────────────┐ |
| │ ⓘ Julho/2026 está inteiro aqui — 24 valores  │ |
| │   repetidos. Se nada mudou, dá para conferir │ |
| │   tudo de uma vez e ir direto ao fechamento. │ |
| │   [ Conferir tudo e ir ao fechamento ]       │ |
| │   [ Prefiro passar bloco a bloco ]           │ |
| └──────────────────────────────────────────────┘ |
|                                                  |
| QUANTO VENDEU NO MÊS                        [?]  |
| [ R$                            84.300,00 ]      |
| veio de julho                                    |
|                                                  |
| IMPOSTOS SOBRE VENDAS PAGOS NO MÊS          [?]  |
| [ R$                             5.058,00 ]      |
| veio de julho                                    |
+--------------------------------------------------+
| [ Próximo ]                                      |
+--------------------------------------------------+
```

**Este é o caminho de 2 toques do mês que não mudou** — e ele desemboca no bloco 6, **não** no fechamento. Confirmar em massa nunca é o último ato (DDR-003, item 5).

### Desktop (≥1024px)

```
+---------+------------------------------------------------------------+
| nav     | ‹ Minhas Empresas / Padaria do Bairro / Lançar             |
| lateral | Lançar agosto/2026                                         |
| (shell  | Padaria do Bairro · Comércio                               |
|  já     +------------------------------------------------------------+
|  existe)| ███ ███ ███ ▒▒▒ ▒▒▒ ▒▒▒                                    |
|         | Bloco 4 de 6 · Despesas administrativas e gerais           |
|         +------------------------------------------------------------+
|         | ⓘ 19 valores vieram de julho/2026.  [ Conferi este bloco ] |
|         |                                                            |
|         | Como você quer informar?                              [?]  |
|         |  +------------------------+ +-------------------------+    |
|         |  | (•) Informar o total   | | ( ) Detalhar por item   |    |
|         |  |     1 campo            | |     21 campos           |    |
|         |  +------------------------+ +-------------------------+    |
|         |  Com o total, a demonstração do mês fica correta do        |
|         |  mesmo jeito. Detalhando, o DRM também consegue            |
|         |  apontar qual item subiu.                                  |
|         | ────────────────────────────────────────────────────────   |
|         | SALÁRIOS              [?]   ENCARGOS TRAB. (FGTS…)   [?]   |
|         | [ R$        12.400,00 ]     [ R$          3.100,00 ]       |
|         | veio de julho               veio de julho                  |
|         |                                                            |
|         | VALE TRANSPORTE       [?]   VALE REFEIÇÃO / CESTA    [?]   |
|         | [ R$           780,00 ]     [ R$          1.240,00 ]       |
|         | …                                                          |
|         +------------------------------------------------------------+
|         | [ Voltar ]                                   [ Próximo ]   |
+---------+------------------------------------------------------------+
```

**O que o espaço extra faz** (declaração obrigatória — `mobile-desktop-parity.md`):

- **Duas colunas de campos**, e **somente** em blocos com **mais de 8 campos** — na prática, o bloco 4 em modo detalhado. Abaixo disso, uma coluna, porque duas colunas com 3 campos produzem o "desktop empilhado" que a régua proíbe pelo motivo oposto. **A ordem de tabulação segue a leitura visual: esquerda→direita, depois a linha seguinte** (§6).
- As duas opções do `seletor-modo` ganham folga lateral em vez de virar uma terceira coluna com a explicação — a frase continua abaixo, ocupando a largura, porque é texto corrido e texto corrido não quer 200px.
- O `aviso-inline` põe o botão na mesma linha do texto em vez de abaixo.
- Container do shell: `max-width: 960px` (`--container-default`), já herdado de `<x-layouts.app>`.

### Tablet (768–1023px)

Herda **mobile** (uma coluna). Declarado: a segunda coluna de campos só entra a partir de 1024px, que é o breakpoint único do shell (`--breakpoint-shell`, `IDR-008`). Não há comportamento próprio de tablet nesta tela.

## 4. Estados

### 4.1. Caminho feliz — preenchido

Coberto pelos sketches da §3. Detalhamento de microcopy na §5.

**Do 2º lançamento em diante**, o caminho feliz é: abrir → `Conferir tudo e ir ao fechamento` → revisar → `Fechar o mês`. Dois toques mais uma leitura. É o desenho que responde à métrica de ≤5 minutos.

### 4.2. Loading

| Quando | Tratamento |
|---|---|
| Primeiro carregamento do formulário | **Skeleton do bloco**: barra de progresso já renderizada (o número de blocos é conhecido), 3 retângulos de campo em `--color-neutral` dentro do card. Nunca spinner em tela vazia. |
| `Próximo` / `Voltar` (salva o bloco) | O botão troca o rótulo para `Salvando…` e fica `disabled` — **exatamente o padrão do Quiz em produção**. O restante da tela não bloqueia. |
| `Fechar o mês` | Botão vira `Fechando o mês…`, `disabled`. É a única operação que pode passar de 1s (motor + persistência). |
| Troca de modo de um grupo | Re-render local do bloco, sem estado de loading — é troca de visibilidade, não ida ao servidor para buscar dado. |

### 4.3. Vazio — primeiro lançamento da empresa

Não há competência anterior: **todos os campos vêm vazios, sem marca de herança**, com placeholder `0,00`.

O bloco 1 troca a oferta de conferência total por uma orientação de primeira vez, exibida **uma única vez** (não reaparece no 2º mês):

```
+--------------------------------------------------+
| ┌──────────────────────────────────────────────┐ |
| │ ⓘ Este é o primeiro mês desta empresa.       │ |
| │   Em cada grupo de despesa você escolhe se   │ |
| │   informa só o total ou item a item — e a    │ |
| │   escolha vale para os próximos meses.       │ |
| │   No mês que vem, a maior parte já vem       │ |
| │   preenchida.                                │ |
| └──────────────────────────────────────────────┘ |
+--------------------------------------------------+
```

A última frase é deliberada: é a promessa que sustenta a métrica primária, feita no momento em que o esforço é maior.

### 4.4. Erro

| Tipo | Onde aparece | Tratamento |
|---|---|---|
| **Campo inválido** (formato, negativo — regra B3) | sob o campo | `--color-destructive` sobre `--color-surface` (4,70:1), texto associado por `aria-describedby`. A máscara BRL já impede digitar sinal negativo; a mensagem cobre colagem e o servidor. |
| **B4 — Impostos maiores que a receita** | bloqueio no bloco 1, ao tentar avançar | `aviso-inline` `bloqueio`, `role="alert" aria-live="assertive"`, com botão `Corrigir` que foca D02. **Sem botão de seguir em frente.** |
| **B2 — Competência futura** | no campo D00, imediato | O seletor de competência **não oferece** meses futuros; a mensagem cobre a rota de URL manipulada. |
| **B1 — Competência já lançada** | bloqueio no bloco 1, imediato ao escolher a competência | `aviso-inline` `bloqueio` com **duas saídas**: `Abrir agosto/2026` e `Corrigir agosto/2026`. É o único bloqueio com botões, porque as saídas não são "seguir mesmo assim" — são ir para outro lugar. |
| **Falha de rede ao salvar o bloco** | acima dos botões de navegação | `aviso-inline` `bloqueio` + `Tentar novamente`. **Os valores digitados permanecem na tela.** Só o avanço falha. |
| **Sessão expirada** | redirect para login com retorno | O rascunho já está salvo até o último bloco concluído. |
| **Erro inesperado (5xx)** | acima dos botões | Mensagem honesta + caminho de suporte, sem stack trace nem código HTTP. |
| **Retificação — concorrência otimista** | bloco 6, no submit | Ver §4.9. |

**Resumo de erros no bloco 6.** Ao tentar fechar com bloqueios pendentes em blocos anteriores, o topo do bloco 6 lista cada bloqueio com link para o campo (`accessibility-basics` §5). Formulário longo sem resumo de erros obriga o usuário a caçar.

### 4.5. Sem permissão

**Não existe estado "sem permissão" nesta tela.** Tenant = Usuário; acesso a Empresa Analisada de outro usuário retorna **404 silente** (`IDR-009`), servido pela página de erro 404 do shell. Registrado explicitamente para que não seja desenhado por engano.

### 4.6. Parcial / degradado — **herança parcial**

Estado real e frequente, não caso de borda (DDR-003, item 6): o grupo estava **agregado** no mês anterior e agora está **detalhado**. Os itens não têm de onde herdar — o total do grupo **não pode** ser distribuído entre eles (`ADR-007`: nunca inferir item a partir do total).

```
+--------------------------------------------------+
| ┌──────────────────────────────────────────────┐ |
| │ ⓘ Em julho você informou este grupo pelo     │ |
| │   total, então os itens começam vazios.      │ |
| │   O total de julho era R$ 20.702,00.         │ |
| └──────────────────────────────────────────────┘ |
+--------------------------------------------------+
```

Mostrar o total anterior é o máximo que se pode dizer com honestidade: dá referência de ordem de grandeza sem inventar a distribuição.

O caminho inverso (era detalhado, agora agregado) **não** é degradado — o total vem pré-preenchido com a soma dos itens do mês anterior, com a legenda da §4.8.

### 4.7. Primeira vez vs. recorrente

| | 1º lançamento | 2º em diante |
|---|---|---|
| Campos | vazios | preenchidos e marcados (DDR-003) |
| Modo dos grupos | default `detalhado`¹ | vem da preferência salva; o seletor mostra o estado |
| Bloco 1 | orientação de primeira vez (§4.3) | oferta de conferência total |
| Alertas A4/A6 | não se aplicam | A4 a partir do 4º; A6 quando há lacuna |

¹ **Decisão de design, aberta ao PO:** o default do 1º lançamento é `detalhado`. Razão: quem chega sem histórico não sabe o que perde ao agregar, e o seletor está ali para ele reduzir se quiser — enquanto o caminho inverso (nasce agregado e o usuário nunca descobre o detalhe) é irreversível para aquele mês. **Contra-argumento honesto:** 38 campos no primeiro contato é o pior momento possível para o atrito, e o 1º lançamento é a porta de entrada do módulo. É uma escolha de produto, não de design — anotada na §9 para decisão do PO, com implementação idêntica nos dois casos.

### 4.8. Troca de modo de um grupo

**Detalhado → agregado (não destrutivo).** Sem confirmação. O total vem pré-preenchido com a soma; os itens ficam preservados na sessão.

```
| TOTAL DAS DESPESAS ADMINISTRATIVAS E GERAIS |
| [ R$                          20.702,00 ]   |
| somamos os itens que você já tinha          |
| preenchido — pode ajustar                   |
```

**Agregado → detalhado (destrutivo).** O total digitado é descartado (Anexo K §K.5). Confirmação **inline**, no lugar do seletor — **não modal** (DDR-002, item 5):

```
+--------------------------------------------------+
| ┌──────────────────────────────────────────────┐ |
| │ ⚠ Detalhar os itens deste grupo?             │ |
| │   O total de R$ 20.702,00 que você informou  │ |
| │   será descartado, e os 21 itens começam     │ |
| │   vazios.                                    │ |
| │   [ Detalhar por item ]  [ Cancelar ]        │ |
| └──────────────────────────────────────────────┘ |
+--------------------------------------------------+
```

Enquanto a confirmação está aberta, o rádio **permanece na opção anterior** — o estado visual não pode antecipar uma troca que ainda não aconteceu. `Cancelar` devolve o seletor intacto.

### 4.9. Retificação (entrada da `STORY-050`)

Mesma tela, quatro diferenças. Especificadas aqui para que a `STORY-050` não redesenhe o formulário:

1. **Título:** `Corrigir agosto/2026` em vez de `Lançar agosto/2026`.
2. **Aviso permanente no bloco 1**, variante `info`: *"Você está corrigindo um mês já fechado. A versão anterior fica guardada."*
3. **Campos vêm da versão vigente, sem marca de herança** — não são valores herdados de outro mês; são os valores daquele mês.
4. **B1 não se aplica** (`STORY-050` CA-6). As demais regras valem.

**Concorrência otimista** (`ADR-007`): se `base_versao` divergir da vigente no submit, o bloco 6 mostra `aviso-inline` `bloqueio`: *"Este mês foi corrigido em outra sessão enquanto você preenchia. Abra a versão atual para não sobrescrever a correção."* + `Abrir a versão atual`. Sem botão de forçar.

**Retificação no-op** (payload idêntico, bloqueada pelo `ADR-007`): `aviso-inline` `info`, não `bloqueio` — nada deu errado. *"Nenhum valor mudou, então não há o que corrigir."* + `Voltar à demonstração`.

### 4.10. Rascunho

| Situação | Tratamento |
|---|---|
| Retomando | `aviso-inline` `info` no topo: *"Retomando seu rascunho — você parou em Despesas administrativas."* Abre no bloco em que parou. **Nomeia o bloco, não o número** (DDR-001). |
| Expirado | `aviso-inline` `info`: *"Seu rascunho expirou. Vamos começar do início."* Mesmo texto do Quiz em produção. |
| Salvamento | Rodapé permanente: *"Você pode sair e voltar depois — o que já preencheu fica salvo."* |

### 4.11. Alertas no bloco 6 — conferir e fechar

Os alertas A1, A2, A3 e A5 só são computáveis com o formulário inteiro. Aparecem no bloco 6, **agrupados num único `aviso-inline` `alerta` com um só aceite explícito** — o padrão `alertas_aceitos` do Quiz em produção. A4 e A6 entram pela `STORY-051` no mesmo bloco, sem mudança de layout.

```
+--------------------------------------------------+
| ███ ███ ███ ███ ███ ███                          |
| Bloco 6 de 6 · Conferir e fechar                 |
+--------------------------------------------------+
| ┌──────────────────────────────────────────────┐ |
| │ ⚠ Confira antes de fechar                    │ |
| │                                              │ |
| │ • Este mês fechou com prejuízo de            │ |
| │   R$ 3.240,00. O dado está registrado como   │ |
| │   você informou — a demonstração vai         │ |
| │   explicar de onde ele veio.   [ Revisar ]   │ |
| │                                              │ |
| │ • Você não informou retirada (pró-labore).   │ |
| │   Se você tirou dinheiro da empresa neste    │ |
| │   mês, vale registrar.         [ Revisar ]   │ |
| │                                              │ |
| │ [ Entendi, pode fechar assim ]               │ |
| └──────────────────────────────────────────────┘ |
+--------------------------------------------------+
| O que vai ser gravado                            |
|                                                  |
| O mês e o que entrou                    [editar] |
|   Quanto vendeu             R$ 84.300,00         |
|   Impostos sobre vendas      R$ 5.058,00         |
|                                                  |
| Custo das Mercadorias Vendidas          [editar] |
|   informado pelo total      R$ 41.200,00         |
|                                                  |
| Despesas administrativas e gerais       [editar] |
|   detalhado — 21 itens      R$ 20.702,00         |
|   Salários                  R$ 12.400,00         |
|   veio de julho                                  |
|   … (itens)                                      |
+--------------------------------------------------+
| [ Voltar ]                     [ Fechar o mês ]  |
+--------------------------------------------------+
```

- **`Fechar o mês` fica `disabled`** enquanto houver alerta não aceito ou bloqueio pendente. O motivo é textual acima do botão, nunca só o estado desabilitado.
- **As marcas de herança sobrevivem até aqui** (DDR-003): no bloco de conferência, `veio de julho` mostra o que o usuário **não** olhou individualmente. É a última rede antes do fechamento.
- Cada grupo declara o modo em que foi lançado — é o que determina a granularidade da série histórica para sempre.

### 4.12. Adaptação por setor

Campo suprimido **não é renderizado**, sem "não se aplica", sem espaço reservado, sem explicação (`ADR-007`: gravado como nulo). Os três sinais que tornam isso natural em vez de incompleto:

1. O **rótulo do grupo de custo** é o do setor: CPV / CMV / Custo dos Serviços Prestados.
2. O **custo em campos do `seletor-modo`** já vem contado depois da supressão: Serviços lê `2 campos` onde Indústria lê `4`.
3. O **setor aparece como fato** no subtítulo da tela (`Padaria do Bairro · Comércio`), nunca como justificativa de ausência.

Nenhum texto do tipo "seu setor não usa este campo". Explicar uma ausência é chamar atenção para ela.

## 5. Microcopy completo

> **Provisório.** Redação final e revisão editorial pertencem à `STORY-052` (pacote de conteúdo da EBC). Os rótulos de campo vêm do Anexo K §K.3 e **não devem ser reescritos** aqui — só os textos de interface abaixo.

| Lugar | Texto |
|---|---|
| Título (lançamento) | `Lançar {mês}/{ano}` |
| Título (retificação) | `Corrigir {mês}/{ano}` |
| Subtítulo | `{Empresa} · {Setor}` |
| Progresso | `Bloco {n} de 6 · {nome do bloco}` |
| Bloco 1 | `O mês e o que entrou` |
| Bloco 2 | `Custo dos Produtos Vendidos (CPV)` / `Custo das Mercadorias Vendidas (CMV)` / `Custo dos Serviços Prestados` |
| Bloco 3 | `Despesas com vendas` |
| Bloco 4 | `Despesas administrativas e gerais` |
| Bloco 5 | `Sua retirada e as despesas financeiras` |
| Bloco 6 | `Conferir e fechar` |
| Label D00 | `Competência` |
| Placeholder monetário | `0,00` |
| Prefixo monetário | `R$` |
| Seletor de modo — pergunta | `Como você quer informar?` |
| Seletor — opção agregada | `Informar o total` · `1 campo` |
| Seletor — opção detalhada | `Detalhar por item` · `{n} campos` |
| Seletor — explicação | `Com o total, a demonstração do mês fica correta do mesmo jeito. Detalhando, o DRM também consegue apontar qual item subiu.` |
| Legenda do total somado | `somamos os itens que você já tinha preenchido — pode ajustar` |
| Confirmação destrutiva — título | `Detalhar os itens deste grupo?` |
| Confirmação destrutiva — corpo | `O total de {valor} que você informou será descartado, e os {n} itens começam vazios.` |
| Confirmação destrutiva — CTAs | `Detalhar por item` / `Cancelar` |
| Marca de herança | `veio de {mês anterior}` |
| Aviso de herança por bloco | `{n} valores vieram de {mês}/{ano}. Confira e ajuste o que mudou.` + `Conferi este bloco` |
| Oferta de conferência total | `{Mês}/{ano} está inteiro aqui — {n} valores repetidos. Se nada mudou, dá para conferir tudo de uma vez e ir direto ao fechamento.` |
| Oferta — CTAs | `Conferir tudo e ir ao fechamento` / `Prefiro passar bloco a bloco` |
| Primeira vez | `Este é o primeiro mês desta empresa. Em cada grupo de despesa você escolhe se informa só o total ou item a item — e a escolha vale para os próximos meses. No mês que vem, a maior parte já vem preenchida.` |
| Herança parcial | `Em {mês} você informou este grupo pelo total, então os itens começam vazios. O total de {mês} era {valor}.` |
| Não operacionais (recolhido) | `Teve algo fora do movimento normal neste mês? (opcional)` |
| Rodapé de salvamento | `Você pode sair e voltar depois — o que já preencheu fica salvo.` |
| Retomada | `Retomando seu rascunho — você parou em {nome do bloco}.` |
| Rascunho expirado | `Seu rascunho expirou. Vamos começar do início.` |
| **B1** — duplicada | `Você já lançou {mês}/{ano} para esta empresa.` + `Abrir {mês}/{ano}` / `Corrigir {mês}/{ano}` |
| **B2** — futura | `Ainda não dá para lançar {mês}/{ano} — o mês não terminou.` |
| **B3** — negativo | `Este campo não aceita valor negativo. Se houve devolução, reduza o valor da venda ou da compra correspondente.` |
| **B4** — imposto > receita | `Os impostos ({valor}) estão maiores que o quanto você vendeu ({valor}). Confira os dois valores.` + `Corrigir` |
| **A1** — sem faturamento | `Você não informou nenhuma venda neste mês. Se foi isso mesmo, tudo bem — os percentuais sobre a receita não vão aparecer na demonstração.` |
| **A2** — custo > receita | `O custo do que foi vendido ({valor}) ficou maior que o quanto você vendeu ({valor}). Acontece em mês de reposição de estoque — vale conferir.` |
| **A3** — prejuízo | `Este mês fechou com prejuízo de {valor}. O dado está registrado como você informou — a demonstração vai explicar de onde ele veio.` |
| **A5** — pró-labore zero | `Você não informou retirada (pró-labore). Se você tirou dinheiro da empresa neste mês, vale registrar.` |
| Aceite dos alertas | `Entendi, pode fechar assim` |
| Título dos alertas | `Confira antes de fechar` |
| Título dos bloqueios | `Corrija antes de fechar o mês` |
| Conferência — título | `O que vai ser gravado` |
| Conferência — modo agregado | `informado pelo total` |
| Conferência — modo detalhado | `detalhado — {n} itens` |
| Navegação | `Voltar` / `Próximo` / `Salvando…` |
| Fechamento | `Fechar o mês` / `Fechando o mês…` |
| Erro de rede | `Não conseguimos salvar agora.` + `Tentar novamente` |
| Erro inesperado | `Algo deu errado do nosso lado. Já fomos notificados. Se continuar, fale com o suporte.` |
| Retificação — aviso | `Você está corrigindo um mês já fechado. A versão anterior fica guardada.` |
| Retificação — conflito | `Este mês foi corrigido em outra sessão enquanto você preenchia. Abra a versão atual para não sobrescrever a correção.` + `Abrir a versão atual` |
| Retificação — no-op | `Nenhum valor mudou, então não há o que corrigir.` + `Voltar à demonstração` |

**Tooltips por campo** (`<x-help>`): saem do Anexo K §K.10, com a prioridade de redação definida lá — D07 vs. D09, D10 vs. D38, D16 vs. D40, D02 (DAS do Simples), D20/D21 (sazonalidade). Conteúdo é da `STORY-052`; a superfície já existe em produção (`config/quiz/help-industria.php` é o precedente estrutural).

## 6. Acessibilidade (notas específicas desta tela)

Além do piso de `references/accessibility-basics.md`:

- **Estrutura semântica.** Cada bloco é `<fieldset>` com `<legend>` = nome do bloco. Cada grupo com seletor tem `<fieldset>` aninhado para o `radiogroup`. Os campos usam `<label for>` real — o rótulo nunca é placeholder.
- **Foco ao trocar de bloco:** vai para o **cabeçalho do bloco** (`tabindex="-1"`), não para o primeiro campo. O leitor de tela anuncia onde chegou antes de anunciar o que preencher. Reaproveita o mecanismo de evento Alpine que o Quiz já usa em `focar-campo`.
- **Ordem de tabulação.** Uma coluna: ordem natural do DOM. **Duas colunas (desktop, bloco 4 detalhado): a ordem de tabulação é a ordem visual — esquerda→direita e depois a linha seguinte.** É o único ponto desta tela em que ordem de DOM e ordem visual podem divergir, e o DOM precisa seguir a leitura.
- **`progresso-blocos`:** `role="progressbar"` com `aria-valuemin/max/now` e `aria-label`. Os segmentos são decorativos (`aria-hidden`); a informação real está na linha textual `Bloco N de 6 · Nome`.
- **Marca de herança:** o texto `veio de julho` é ligado ao campo por `aria-describedby` enquanto existir; some junto com a marca. Não é `aria-label` — duplicaria o nome do campo.
- **Erros:** associados por `aria-describedby`. `aviso-inline` `bloqueio` usa `role="alert" aria-live="assertive"`; `alerta` e `info` usam `aria-live="polite"`. Resumo de erros no topo do bloco 6, com links para os campos.
- **Confirmação de troca de modo:** é bloco inline com `role="alertdialog"`? **Não** — é `aviso-inline` `alerta` com `aria-live="polite"`. Sem foco-trap: o foco vai para o botão de confirmação, e `Esc` cancela. Deliberado (DDR-002, item 5): não introduzimos diálogo modal no produto.
- **Alvos de toque:** ≥44×44px em campos, botões, opções do seletor e no `<x-help>`. **Atenção:** o `.help__trigger` em produção tem 18×18px — abaixo do piso. Registrado na §8 como divergência a corrigir; o mesmo problema já existe no Quiz.
- **Teclado sem mouse:** o fluxo inteiro (escolher competência, trocar modo, preencher, avançar, aceitar alertas, fechar) precisa ser completável só com teclado. É o teste do `accessibility-basics` §3, e num formulário deste tamanho é o mais revelador.
- **`prefers-reduced-motion`:** a transição entre blocos não é animada. Nada a fazer.

### Contraste — combinações usadas nesta tela (verificado)

| Uso | Par | Razão | AA texto normal |
|---|---|---|---|
| Texto e valores | `primary` / `surface` | 15,54:1 | ✅ |
| Rótulos, legendas, marca de herança | `secondary` / `surface` | 7,80:1 | ✅ |
| Texto sobre linha destacada | `primary` / `neutral` | 14,70:1 | ✅ |
| Erro de campo | `destructive` / `surface` | 4,70:1 | ✅ |
| Rótulo de botão primário | `on-primary` / `tertiary` | 4,70:1 | ✅ |
| Título do `aviso-inline` `alerta` | `primary` / `surface` | 15,54:1 | ✅ |

**Duas restrições que saíram desta verificação e que o Programador precisa respeitar:**

1. **`tertiary` sobre `neutral` mede 4,45:1 e reprova AA para texto normal.** Portanto nenhum texto em `--color-tertiary` pode ficar sobre fundo `--color-neutral` — inclusive no `seletor-modo`, cuja opção selecionada usa `tertiary` **só** em borda e marcador (piso 3:1), com o rótulo em `--color-primary`.
2. **`destructive` sobre `neutral` mede 4,45:1** — mesma reprovação. Mensagem de erro fica sobre `--color-surface`. O padrão em produção (`destructive/5` sobre branco) atende.

**Achado fora do escopo desta estória, registrado:** a borda de input (`--color-input` #E3E8EE sobre branco) mede **1,23:1**, abaixo do 3:1 que a WCAG 1.4.11 pede para limites de componente interativo. É dívida do Design System, atinge todas as telas em produção e **não é criada por esta tela**. Escalado ao PO na §9 — não corrigido aqui, porque mexer nesse token afeta o produto inteiro e exige DDR próprio.

## 7. Identificadores estáveis sugeridos para E2E

Nomes lógicos; o atributo concreto é decisão do Programador (em Dusk, `dusk="..."`).

| Elemento | Identificador lógico |
|---|---|
| Barra de progresso, segmento N | `drm-lancamento-progresso-{n}` |
| Cabeçalho do bloco | `drm-lancamento-bloco-{n}` |
| Campo monetário Dnn | `drm-lancamento-campo-{Dnn}` |
| Erro do campo Dnn | `drm-lancamento-erro-{Dnn}` |
| Marca de herança do campo Dnn | `drm-lancamento-herdado-{Dnn}` |
| Seletor de modo do grupo | `drm-lancamento-modo-{grupo}` |
| Opção agregada / detalhada | `drm-lancamento-modo-{grupo}-agregado` / `-detalhado` |
| Confirmação destrutiva | `drm-lancamento-modo-{grupo}-confirmar` |
| Aviso de herança do bloco | `drm-lancamento-heranca-bloco-{n}` |
| Botão conferir bloco | `drm-lancamento-conferir-bloco-{n}` |
| Oferta de conferência total | `drm-lancamento-conferir-tudo` |
| Bloqueio da regra Bn | `drm-lancamento-bloqueio-{B1..B4}` |
| Alerta da regra An | `drm-lancamento-alerta-{A1..A6}` |
| Aceite dos alertas | `drm-lancamento-aceitar-alertas` |
| Bloco de conferência | `drm-lancamento-conferencia` |
| Navegação | `drm-lancamento-voltar` / `drm-lancamento-proximo` |
| Fechamento | `drm-lancamento-fechar` |
| Aviso de retomada | `drm-lancamento-retomando` |

## 8. Exceções ao Design System

| O que diverge | Por quê | Vira DDR? |
|---|---|---|
| `seletor-modo` — componente que o DS não tem | Usado 4× por tela, 12× por ano; é o controle do mecanismo anti-atrito nº 1 do `PDR-003`. Lista de rádios comum empurraria o custo em campos para fora da dobra em 360px | **Sim — DDR-002** |
| `aviso-inline` — consolida 3 construções quase idênticas do Quiz | Padronização (Princípio #4): hoje são três blocos inline visualmente parecidos e semanticamente diferentes, sem componente | **Sim — DDR-001** |
| `progresso-blocos` — promoção de padrão inline do Quiz | Já está em produção; passa a ter nome e estados | **Sim — DDR-001** |
| Estado `herdado` do `campo-monetario` | É estado de componente existente, não componente novo | **Sim — DDR-003** |
| Confirmação destrutiva **inline** em vez de modal | O DS não tem diálogo modal; um formulário longo em celular não precisa de foco-trap onde um bloco inline resolve. Evita introduzir o componente mais caro de acertar em acessibilidade | Não — decisão registrada no DDR-002, item 5 |
| `<x-help>` com alvo de 18×18px | **Divergência real do piso de 44px**, herdada do Quiz em produção. Não é criada aqui. Precisa de correção — escalada na §9 | Não — é bug, não decisão |
| Borda de input em 1,23:1 (WCAG 1.4.11) | Dívida do DS, atinge o produto inteiro. Não é criada por esta tela | Não — escalada na §9 |

## 9. Dependências e premissas

- **Contrato de dados:** `ADR-007` — `drm_rascunhos` (payload com `modos`, `repetidos[]`, progresso, `origem`, `base_versao`), `drm_lancamentos` (`versao`, `payload_hash`, `alertas_aceitos` em coluna própria), `drm_preferencias` (modo por grupo, `posicao_prolabore`). **Não duplico aqui.**
- **Campos, visibilidade e regras:** Anexo K §K.3 a §K.9. Fonte única; este spec não redefine campo nem regra.
- **Textos finais:** `STORY-052`. Todos os textos da §5 são provisórios.
- **Permissões:** tenant = Usuário. Cross-tenant → 404 silente (`IDR-009`).

### Escalado ao PO — decisões que não são minhas

1. **Default do modo dos grupos no 1º lançamento** (§4.7). Proponho `detalhado` pelo argumento da irreversibilidade; o contra-argumento (38 campos no primeiro contato) é de produto. Custo de implementação idêntico nos dois casos. **Decisão do PO.**
2. **Descartar rascunho.** Não há ação de descarte no MVP; o rascunho expira. Nenhuma estória pede — registro para que a ausência seja deliberada e não esquecimento.
3. **Alvo de toque do `<x-help>` (18×18px).** Viola o piso de 44px em produção, no Quiz e agora aqui. É bug de acessibilidade, e o piso *"vence qualquer outro princípio em conflito"*. Sugiro anexá-lo à `STORY-059`, que já é a estória de dívida de acessibilidade — **não** cabe no `EPIC-010`.
4. **Contraste da borda de input (1,23:1, WCAG 1.4.11).** Dívida de token; corrigir afeta todas as telas e exige DDR próprio. Sugiro avaliar junto com a `STORY-059`.

### Proposta ao Arquiteto / `STORY-048`

Nenhuma vinda desta tela. A proposta de acrescentar `tipo` e `destaque` às linhas do snapshot está no spec da Demonstração do Mês.

## 10. Histórico de mudanças

| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-08-04 | criação — spec completo, 12 estados, microcopy provisória | designer (claude-opus-5) | `STORY-046`. Sem sync prévio com Programador: a `STORY-047` ainda não tem `owner_agent`; as restrições técnicas vieram do `ADR-007` e do código do Quiz em produção |

> Mudança depois que o código começar é mudança consciente — registre aqui **e** nas Notas do agente da `STORY-047`.
