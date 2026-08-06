---
id: DDR-004
title: Demonstração financeira — par nominal + percentual em toda linha, três níveis de hierarquia e cards por trecho da conta no mobile
status: accepted
created_at: 2026-08-04
decided_at: 2026-08-06
approved_by: Alexandro
supersedes: ~
superseded_by: ~
related_ddrs: [DDR-005]
related_adrs: [ADR-007]
related_pdrs: [PDR-003, PDR-004]
scope: apresentação de dados financeiros
affects_screens: [SCREEN-STORY-046-demonstracao-mes, SCREEN-STORY-046-serie-historica]
---

# DDR-004 — Demonstração financeira: nominal + percentual, três níveis, cards por trecho da conta

## Contexto

A Demonstração do Mês tem **até 17 linhas** em três níveis (`design/estrutura-drm.md` §1), e cada uma precisa carregar **dois números ao mesmo tempo** — valor em reais e percentual sobre a Receita Bruta. O par não é enfeite: o valor nominal é o pedido literal do Key User (`PDR-003`, pedido A, já entregue no Diagnóstico pela `STORY-044`) e o percentual é o que o `PDR-004`, decisão C, estendeu a **todas** as linhas.

O risco é nomeado na própria estória: *"sem virar planilha"*. Dezessete linhas × dois números, num celular de 360px, é a definição de planilha — e planilha é precisamente o artefato que a persona deste módulo não usa. É o que o `EPIC-010` diz sobre a Joana.

Há ainda três restrições que estreitam o desenho antes de ele começar:

- **`ADR-007` / `STORY-049` CA-8b:** a tela renderiza **genericamente** a lista de linhas do snapshot, sem hardcode de `L1..L11`, para que um bump MAJOR do motor não quebre a exibição de meses antigos.
- **`PDR-004` decisão B:** dois modos de apresentação do pró-labore, com **número de linhas, rótulos e ordem diferentes**. O layout tem de servir aos dois sem saber qual está vendo.
- **`STORY-049` fora de escopo:** farol e leitura em prosa são da `STORY-054`. A tela precisa reservar lugar para eles *"sem que a tela pareça incompleta antes deles"*.

Documentos lidos: `design/estrutura-drm.md` (§1 a §4), `PDR-004`, `ADR-007` (§*snapshot auto-descritivo*), Anexo K §K.7, `STORY-049`, `STORY-054`, `STORY-059`, e o relatório de Diagnóstico em produção (`diagnosticos/show.blade.php`, `components/relatorio/*`).

## Forças (drivers)

- **Dois números por linha × 17 linhas × 360px** (altíssimo): é o problema.
- **Render genérico obrigatório** (alto): qualquer solução que dependa de conhecer `L1..L11` está proibida pelo CA-8b.
- **Dois modos com estruturas diferentes** (alto): o layout não pode assumir que existe uma linha "Resultado antes do pró-labore" — ela só existe no modo `fora`.
- **Precedente em produção** (alto): o relatório de Diagnóstico já resolveu "tabela densa no desktop / cards no mobile" e "valor + valores nominais na mesma célula". Princípio #4 manda herdar.
- **Prejuízo não é erro** (alto): `STORY-049` CA-6 — *"sinalizado, não dramatizado"*. Qualquer uso de vermelho arrasta a semântica de erro.
- **Contraste** (alto): os tokens de farol não passam AA como texto pequeno (verde 3,39:1; amarelo 3,19:1 sobre branco) — dívida já endereçada pela `STORY-059`.

## Opções consideradas

### Opção A — Duas colunas de número, tabela em ambos os viewports

`Linha | R$ | %` em desktop e em mobile, com fonte reduzida no celular.

- **Prós:** máxima fidelidade à leitura contábil; a conta encadeada fica óbvia.
- **Contras:** em 360px, rótulos como "Despesas Administrativas e Gerais" quebram em 3 linhas contra duas colunas numéricas, ou a tabela ganha scroll horizontal — o anti-padrão explicitamente proibido em `mobile-desktop-parity.md`. E contraria o CA-9 da `STORY-049`, que pede cards no mobile.

### Opção B — Um card por linha no mobile (espelho do relatório de Diagnóstico)

17 cards empilhados, cada um com rótulo, R$ e %.

- **Prós:** paridade literal com o relatório de Diagnóstico; CA-9 satisfeito ao pé da letra.
- **Contras:** **quebra a aritmética**. Os 13 indicadores do Diagnóstico são objetos independentes — cada card é uma coisa inteira. As linhas da demonstração **não são**: são uma conta encadeada em que cada linha só significa alguma coisa em relação à anterior. Uma borda entre "(–) Custo" e "= Lucro Bruto" corta exatamente a subtração que o usuário precisa enxergar. Além disso, 17 cards em 360px são ~6 dobras de rolagem.

### Opção C — Cards por **trecho da conta** no mobile; tabela de três colunas no desktop

O mobile agrupa as linhas em cards, mas o corte não é por linha: **cada card fecha num totalizador**. A conta encadeada fica inteira dentro do card; o que separa os cards são justamente os pontos em que a conta "descansa".

```
Mobile (≥360px) — destaques + cards por trecho
+----------------------------------------+
|  QUANTO VENDEU                         |
|  R$ 84.300,00                    100%  |
+----------------------------------------+
|  SOBROU ANTES DA SUA RETIRADA          |
|  R$ 11.240,00                   13,3%  |
+----------------------------------------+
|  SOBROU DEPOIS DA SUA RETIRADA         |
|  R$ 4.240,00                     5,0%  |
+----------------------------------------+

+----------------------------------------+
| Receita                                |
|  Receita Bruta                         |
|    R$ 84.300,00                 100,0% |
|  (–) Deduções da Receita Bruta         |
|    R$  5.058,00                   6,0% |
| ────────────────────────────────────── |
|  = Receita Operacional Líquida         |
|    R$ 79.242,00                  94,0% |
+----------------------------------------+
| Custo e lucro bruto                    |
|  (–) Custo das Mercadorias Vendidas    |
|    R$ 41.200,00                  48,9% |
| ────────────────────────────────────── |
|  = Lucro Bruto (Margem Bruta)          |
|    R$ 38.042,00                  45,1% |
+----------------------------------------+
| Despesas operacionais                  |
|  (–) Despesas Operacionais             |
|    R$ 26.802,00                  31,8% |
|     · Despesas com Vendas              |
|       R$  6.100,00                7,2% |
|     · Despesas Administrativas         |
|       R$ 20.702,00               24,6% |
| ────────────────────────────────────── |
|  = Resultado Operacional               |
|    R$ 11.240,00                  13,3% |
+----------------------------------------+
```

```
Desktop (≥1024px) — três colunas, uma tabela só
+---------+----------------------------------------------------------+
| nav     |  QUANTO VENDEU   SOBROU ANTES DA    SOBROU DEPOIS DA     |
| lateral |  R$ 84.300,00      RETIRADA           RETIRADA           |
|         |  100%              R$ 11.240,00       R$ 4.240,00        |
|         |                    13,3%              5,0%               |
|         +----------------------------------------------------------+
|         | Linha                          |    Valor    | % receita  |
|         |--------------------------------|-------------|------------|
|         | Receita Bruta                  |  84.300,00  |    100,0%  |
|         | (–) Deduções da Receita Bruta  |   5.058,00  |      6,0%  |
|         |═ Receita Operacional Líquida   |  79.242,00  |     94,0%  |  ← fundo neutral
|         | (–) Custo das Mercadorias V.   |  41.200,00  |     48,9%  |
|         |═ Lucro Bruto (Margem Bruta)    |  38.042,00  |     45,1%  |  ← fundo neutral
|         | (–) Despesas Operacionais      |  26.802,00  |     31,8%  |
|         |     Despesas com Vendas        |   6.100,00  |      7,2%  |  ← nível 2, recuado
|         |     Despesas Administrativas   |  20.702,00  |     24,6%  |  ← nível 2, recuado
|         |═ Resultado Operacional         |  11.240,00  |     13,3%  |
+---------+----------------------------------------------------------+
```

- **Prós:** preserva a conta dentro de cada card; reduz 17 cards a ~5; satisfaz "cards empilhados no mobile" sem pagar o preço da Opção B; o desktop é a mesma informação com um separador a menos.
- **Contras:** o agrupamento precisa de um critério, e o critério **não pode ser hardcode de códigos de linha** (CA-8b). Resolvido abaixo, com fallback declarado.

### Status quo — não há

A tela não existe.

## Avaliação contra os princípios

| Princípio | A | B | C |
|---|---|---|---|
| 1. Simplicidade radical | ⚠️ densa demais no celular | ⚠️ 6 dobras de rolagem | ✅ 3 números primeiro, detalhe depois |
| 2. Mobile-first com paridade | ❌ scroll horizontal | ⚠️ paridade literal, leitura pior | ✅ mesma leitura nos dois |
| 3. Tom profissional para MPE | ⚠️ vira planilha | ✅ | ✅ |
| 4. Padronização > criatividade | ⚠️ | ✅ espelha o Diagnóstico | ✅ herda tokens, densidade e composição |
| 5. Acessibilidade como hábito | ⚠️ tabela larga é hostil a zoom | ✅ | ✅ `<table>` semântica no desktop, lista no mobile |
| 6. Performance percebida | ✅ | ✅ | ✅ |
| 7. Estados além do caminho feliz | ⚠️ | ⚠️ | ✅ sem faturamento e prejuízo desenhados |

## Decisão

> **Adotada: Opção C.**

A força decisiva é que **as linhas da demonstração não são objetos independentes — são uma conta**. Essa é a diferença de fundo em relação ao relatório de Diagnóstico, e é o que impede copiar de lá o corte por card sem pensar. Cortar por trecho da conta em vez de por linha entrega o que o CA-9 da `STORY-049` pede (cards empilhados, mesma linguagem visual) sem destruir a única coisa que a demonstração tem a dizer.

### O que o padrão fixa

1. **Toda linha carrega o par.** Valor em reais e percentual sobre a receita, sempre os dois. Nunca uma linha com só um deles. Ausência de percentual é **indisponibilidade explícita** (DDR-005), não célula em branco.
2. **Três destaques antes da conta.** *Quanto vendeu*, *quanto sobrou antes da retirada*, *quanto sobrou depois*. No mobile empilhados, no desktop em três colunas. São os três números que a estória exige que sejam achados sem ler a tabela.
3. **Seleção dos destaques, sem hardcode.** A regra é declarativa e tem fallback:
   - 1º = a linha ancorada em 100% (a base dos percentuais);
   - 3º = a **última linha de nível 1** do array — é o resultado final nos dois modos;
   - 2º = a linha de código `L9` se existir no array; senão `L7`.
   - **Se qualquer um não for encontrado, aquele destaque simplesmente não é exibido** e a tabela segue completa. Nunca erro, nunca placeholder.
   - **Pedido ao contrato do motor (`STORY-048`), não requisito desta tela:** se cada linha do snapshot carregasse `tipo` (`total | deducao | subtotal | item`) e `destaque: bool`, tanto esta regra quanto o agrupamento do item 4 deixariam de precisar de configuração. O motor ainda não foi construído — é barato agora e caro depois. Registrado como proposta ao Arquiteto/PO; **a tela não depende disso para ser implementada**.
4. **Agrupamento em cards, no mobile:** um card fecha a cada linha totalizadora de nível 1. Enquanto o item 3 não existir no snapshot, a lista de códigos que fecham card vive em **configuração versionada**, nunca no template — e uma linha desconhecida cai no card corrente em vez de derrubar o render.
5. **Três níveis de hierarquia, sem token novo:**
   - **Nível 1 totalizador** (`= …`): rótulo `--color-primary` peso 500, linha com fundo `--color-neutral` e régua superior `--color-border`, valor em `--text-body`.
   - **Nível 1 dedutor** (`(–) …`): rótulo `--color-primary` peso 400, sem fundo. O prefixo `(–)` é o sinal — não a cor.
   - **Nível 2 subtotal**: recuo `--spacing-md`, rótulo `--color-secondary`, `--text-body-sm`.
   - **Nível 3 item**: recuo `--spacing-lg`, `--color-secondary`, `--text-body-sm`, marcador `·`.
6. **Prejuízo é neutro.** Valor negativo é exibido com sinal (`−R$ 3.240,00`), em `--color-primary`, com um `pill` neutro *"Prejuízo no mês"* junto ao rótulo da linha final. **Nenhuma cor.** Duas razões: vermelho no produto significa erro (`--color-destructive`) ou farol (`--color-farol-vermelho`, que é interpretação e pertence à `STORY-054`); e o sinal aritmético já é inequívoco e funciona para daltônicos. O destaque nº 3 troca o rótulo de *"Sobrou depois da sua retirada"* para *"Faltou depois da sua retirada"* — a mudança de verbo carrega o sinal sem dramatizar.
7. **Espaço do farol e da leitura é ausente, não vazio.** Antes da `STORY-054` **não existe coluna de farol nem card de leitura** — não há buraco a explicar. Quando chegarem: o farol entra como badge à direita do rótulo, dentro da própria linha (mesmo padrão do `card-indicador` do Diagnóstico), e a leitura entra num card acima da tabela, na posição que o `resumo-executivo` ocupa no relatório de Diagnóstico. Nenhum dos dois exige mudar a grade.
   **Restrição herdada:** os tokens de farol reprovam AA como texto pequeno; o que a `STORY-059` decidir para o Diagnóstico vale aqui sem renegociação.
8. **Alinhamento numérico:** `tabular-nums` e alinhamento à direita nas duas colunas de número, em ambos os viewports — é o que permite comparar ordens de grandeza sem ler dígito a dígito.
9. **Percentual com 1 casa decimal** (`estrutura-drm` §2). Arredondamento só na apresentação.

## Consequências

### Positivas

- Os três números que importam ficam acima da conta, no celular e no desktop.
- A conta encadeada sobrevive ao mobile — que era o risco real da paridade literal com o Diagnóstico.
- Os dois modos de pró-labore são servidos pelo mesmo layout sem ramificação: o modo muda o array, não a grade.
- Prejuízo, sem faturamento e omissão de L7b ficam resolvidos sem cor nova e sem token novo.

### Negativas / trade-offs assumidos

- **O agrupamento em cards precisa de configuração** enquanto o snapshot não carregar `tipo`. É dívida pequena, declarada, com fallback que não quebra.
- **Prejuízo sem cor pode parecer discreto demais** para quem espera vermelho. Aceito: a troca de verbo no destaque e o `pill` fazem o trabalho, e o alerta A3 já preparou o usuário no lançamento.
- **A tabela do desktop tem só três colunas** e usa pouco da largura de 960px. Proposital: o espaço extra vai para respiração e para o comprimento dos rótulos, que são longos em português. Declarado aqui para não ser lido como "desktop empilhado".

### Impacto no Design System

- Componente `demonstracao-linha` em `components.md`, com duas renderizações (linha de tabela no desktop, linha de card no mobile) e quatro variantes por nível/tipo.
- Padrão `demonstracao-financeira` em `patterns.md` — os 9 itens acima.
- Variante `link` do `<x-button>`: botão que se parece com link, para ações que mudam estado sem navegar (a troca de modo do pró-labore). Hoje o produto só tem `primary | secondary | ghost`, e usar `<a>` para trocar estado seria semântica errada.
- **Nenhum token novo.**

### Impacto em telas existentes

- `SCREEN-STORY-046-demonstracao-mes` e `SCREEN-STORY-046-serie-historica` consomem o padrão.
- **Relatório de Diagnóstico:** não muda. A divergência de corte (card por indicador lá, card por trecho aqui) é deliberada e está justificada na Decisão — os dois conteúdos têm naturezas diferentes.
- Exportação (`STORY-055`) herda hierarquia e o par nominal/percentual.

## Implementação sugerida (notas para o Programador)

- A regra dos destaques e a lista de códigos que fecham card cabem num único arquivo de configuração; a tela lê de lá e nunca de constante no template.
- Uma linha de código desconhecido **não** pode derrubar o render — é o cenário exato que o CA-8b manda testar.
- No mobile, cada card é `<x-card>` contendo uma `<dl>`; no desktop, uma `<table>` com `<th scope="row">` no rótulo. Duas marcações para o mesmo dado é o que o relatório de Diagnóstico já faz.

## Critérios para revisitar

- Se o snapshot passar a carregar `tipo`/`destaque`, a configuração dos itens 3 e 4 sai e este DDR ganha uma revisão editorial.
- Se a `STORY-054` mostrar que o farol precisa de coluna própria no desktop (e não de badge no rótulo), o item 7 é reaberto.
- Se aparecer uma terceira demonstração no produto (fluxo de caixa, por exemplo), revalidar o padrão com dois consumidores.

## Aprovação humana

| Campo | Valor |
|---|---|
| Apresentado em | 2026-08-04 |
| Aprovado por | (pendente) |
| Data da aprovação | (pendente) |
| Observações do aprovador | |

> Sem este bloco preenchido, o DDR **não** pode ir para `accepted`.
