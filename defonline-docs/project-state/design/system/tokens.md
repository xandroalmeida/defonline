# Tokens — ponteiro e restrições de uso

> **A definição dos tokens NÃO está aqui.** Ela vive em dois lugares, nesta ordem de autoridade:
>
> 1. [`especificacao/V2/design-system.md`](../../../especificacao/V2/design-system.md) §Tokens — **fonte canônica**
> 2. `app/resources/css/tokens.css` — materialização em código (`IDR-008`), única fonte autorizada de hex literal
>
> Duplicar a tabela de tokens aqui criaria uma terceira verdade que sairia de sincronia na primeira mudança. Este arquivo carrega o que **não** está lá: as restrições de uso que só aparecem quando um token encontra uma tela real.

## Nenhum token novo veio de tela — os quatro que existem vieram de acessibilidade

Estado em 2026-08-06. As três telas do DRM (`STORY-046`) — formulário de 38 campos, demonstração financeira de três níveis, série histórica com quatro estados de competência — foram especificadas com a paleta v1 intacta, e a tabela abaixo registra onde a tentação de criar token apareceu e o que a substituiu.

Os **quatro tokens novos** que o DS ganhou desde então (`--color-farol-*-texto`, `STORY-059` / `DDR-006`) não nasceram de uma tela querendo uma cor: nasceram de uma medição de contraste que reprovava. É outra categoria de motivo, e é a única que tem valido a pena.

Onde a tentação de criar token apareceu e o que a substituiu:

| Tentação | O que foi usado no lugar | Onde está registrado |
|---|---|---|
| Cor para campo "herdado do mês passado" | Legenda textual que **some ao conferir** | DDR-003 |
| Vermelho para prejuízo | Sinal aritmético + troca de verbo + `pill` neutro | DDR-004, item 6 |
| Amarelo para "mês atípico" | `pill` neutro + frase explicativa | `SCREEN-STORY-046-serie-historica` §4.6 |
| Cor para variação (verde ↑ / vermelho ↓) | Sinal + frase. A direção não tem semântica fixa | `SCREEN-STORY-046-serie-historica` §4.5 |
| Cor para "indisponível" | Marca `—` + motivo em prosa, uma vez, no escopo em que vale | DDR-005 |

O padrão é o mesmo nos cinco casos: **quando a informação precisava de um canal, o canal escolhido foi texto, não cor** — o que resolve acessibilidade e economia de tokens de uma vez.

---

## Restrições de uso verificadas

Medições WCAG 2.1 (fórmula de luminância relativa) sobre os hex de `tokens.css`. **Estas restrições são vinculantes** — um spec ou uma implementação que as viole está errado, não é questão de gosto.

### Combinações aprovadas

| Texto | Fundo | Razão | AA normal (4.5:1) |
|---|---|---|---|
| `primary` | `surface` | 15,54:1 | ✅ |
| `primary` | `neutral` | 14,70:1 | ✅ |
| `secondary` | `surface` | 7,80:1 | ✅ |
| `secondary` | `neutral` | 7,38:1 | ✅ |
| `on-primary` | `tertiary` | 4,70:1 | ✅ |
| `tertiary` | `surface` | 4,70:1 | ✅ |
| `destructive` | `surface` | 4,70:1 | ✅ |
| `tertiary-hover` | fundo do badge (`tertiary` 10% sobre `surface`) | 5,53:1 | ✅ |
| `farol-verde-texto` | fundo do pill verde | 4,76:1 | ✅ |
| `farol-amarelo-texto` | fundo do pill amarelo | 4,84:1 | ✅ |
| `farol-vermelho-texto` | fundo do pill vermelho | 4,79:1 | ✅ |
| `farol-cinza-texto` | fundo do pill cinza | 4,79:1 | ✅ |

### Combinações **proibidas** para texto normal

| Texto | Fundo | Razão | Consequência prática |
|---|---|---|---|
| `tertiary` | `neutral` | **4,45:1** ❌ | Link ou rótulo em `tertiary` **nunca** sobre `--color-neutral`. No `seletor-modo`, a opção selecionada usa `tertiary` **só** em borda e marcador (piso 3:1); o rótulo é `primary` |
| `tertiary` | fundo do badge (`tertiary` 10%) | **4,13:1** ❌ | Foi o defeito do badge "Versão MVP". Sobre fundo tingido da própria cor, use `tertiary-hover` |
| `destructive` | `neutral` | **4,45:1** ❌ | Mensagem de erro fica sobre `--color-surface`. O padrão em produção (`destructive/5` sobre branco) atende |
| `farol-verde` | fundo do pill verde | **3,02:1** ❌ | Cor **viva** não escreve. Use `farol-verde-texto` — DDR-006 |
| `farol-amarelo` | fundo do pill amarelo | **2,81:1** ❌ | Idem — `farol-amarelo-texto` |
| `farol-vermelho` | fundo do pill vermelho | **4,13:1** ❌ | Idem. Passava sobre branco puro (4,83:1) e reprovava sobre o próprio fundo tingido |
| `farol-cinza` | fundo do pill cinza | **2,27:1** ❌ | Reprova até o piso de 3:1 para ícone — `farol-cinza-texto` |

`tertiary` e `destructive` passam por **0,20** sobre branco e reprovam por **0,05** sobre neutral. A margem é fina o suficiente para que "quase igual" não valha: fundo branco ou reprova.

### Regra de uso: pigmento e tinta (DDR-006)

Os faróis têm **dois tokens por cor**, e trocá-los de lugar quebra o contraste:

| Token | Papel | Onde entra |
|---|---|---|
| `--color-farol-<cor>` | **pigmento** — cor viva | fundo do pill, borda do card do Resumo Executivo, faixa de alerta do Quiz |
| `--color-farol-<cor>-texto` | **tinta** | texto e ícone sobre o fundo tingido |

E o fundo do pill é **opaco** (`color-mix` com `--color-surface`), não alpha. Enquanto foi translúcido, o mesmo pill media 4,76:1 na tabela e **4,2:1** dentro do card do Resumo Executivo, cujo cabeçalho já é tingido da mesma cor: os dois tingimentos somavam. Componente não pode mudar de legibilidade conforme onde é colocado.

### Regra de uso: `border` × `input` (DDR-007)

Idênticos do DS v1 até a `STORY-059`, o que fazia a distinção parecer decorativa. Não é:

| Token | Papel | Piso |
|---|---|---|
| `--color-border` `#E3E8EE` | **separa** — divisória de seção, contorno de card | nenhum (não é componente interativo) |
| `--color-input` `#7C8798` | **delimita componente interativo** — a borda que diz "aqui se digita" | **3:1** (WCAG 2.1 §1.4.11) |

Usar `border` em campo devolve o defeito de 1,23:1; usar `input` como divisória deixa a tela pesada.

---

## Dívidas de acessibilidade do produto — **as três foram fechadas**

Atravessam telas já em produção. **Nenhuma foi criada pela `STORY-046`**; todas foram descobertas ao verificar as combinações que ela usa. As três foram resolvidas pela `STORY-059` em 2026-08-06 e ficam registradas aqui como histórico — o que foi medido, o que se decidiu e onde a decisão mora.

### 1. Pills de farol reprovavam AA como texto pequeno — **resolvida (DDR-006)**

`<x-relatorio.farol>` renderizava o rótulo em `text-xs` (12px) na cor do farol sobre um fundo de 10–12% da mesma cor. Medições sobre o fundo real do pill: verde 3,02:1, amarelo 2,81:1, vermelho 4,13:1, cinza 2,27:1 — piso 4,5:1. O ícone, que herda a mesma cor, reprovava até o piso de 3:1 nos casos amarelo e cinza.

A ironia importa, e foi o argumento que deu urgência: o farol ganhou ícone **e** texto justamente para não depender de cor, protegendo quem tem dificuldade de distinguir cores — e é exatamente essa pessoa que o contraste insuficiente atacava.

**Como foi resolvida.** Separando pigmento de tinta (ver §*Regra de uso* acima): a cor viva segue tingindo o fundo, e quatro tokens `-texto` novos escrevem o rótulo e o ícone a ≥4,5:1. O fundo do pill passou a ser **opaco**, porque translúcido ele somava com o tingimento do card do Resumo Executivo e derrubava o rótulo para 4,2:1 num nó só da página — achado do Lighthouse, não da conta em papel.

**O que vale para o `EPIC-010`:** o slot de farol da Demonstração do Mês (`SCREEN-STORY-046-demonstracao-mes` §4.12) herda isso **sem renegociação**. A `STORY-054` usa `.farol--<cor>`; não inventa tratamento próprio.

### 2. Borda de input media 1,23:1 — **resolvida (DDR-007)**

`--color-input` (#E3E8EE) sobre `--color-surface` (#FFFFFF) = **1,23:1**, contra os **3:1** da WCAG 2.1 §1.4.11 — e a borda é o que identifica um campo como campo. Atingia `.input` e `.input-affix`, ou seja, todas as telas de formulário do produto.

**Como foi resolvida.** `--color-input` foi para `#7C8798` (**3,64:1** sobre `surface`, **3,44:1** sobre `neutral`), e `--color-border` ficou claro. Os dois tokens deixaram de ser sinônimos — ver §*Regra de uso* acima. Ratificado pelo PO em 2026-08-06, com o trade-off de peso visual assumido no DDR-007.

### 3. `<x-help>` com alvo de toque de 18×18px — **resolvida (bug, sem DDR)**

`.help__trigger` media 18×18px contra o piso de **44×44px** (`accessibility-basics` §7). Estava em produção no Quiz e seria herdado pelo formulário do DRM, que usa `<x-help>` em dezenas de campos.

**Como foi resolvida.** Pseudo-elemento transparente de 44×44px centrado no botão: a área de toque cresce, o círculo visível continua com 18px e o layout não se desloca. Coberto por teste em browser real, que mede o hit-testing dos dois lados — acerta dentro dos 44px, não rouba toque a 40px do centro.

---

## Como propor token novo

1. Confirme que nenhuma combinação existente resolve. Cinco casos das telas do DRM pareciam pedir token e nenhum precisou — o padrão é que texto resolve o que cor pareceria resolver.
2. Se resolver mesmo assim: **DDR** com as opções consideradas e as medições de contraste de cada uma.
3. Aprovado, o token entra em `app/resources/css/tokens.css` (`@theme`) **e** no DS canônico, com bump de versão lá.
4. Nunca introduza segundo acento concorrente com `tertiary`, gradiente, ou peso ≥700 — são os três "don'ts" duros do DS canônico.
