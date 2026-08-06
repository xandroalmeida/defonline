# Tokens — ponteiro e restrições de uso

> **A definição dos tokens NÃO está aqui.** Ela vive em dois lugares, nesta ordem de autoridade:
>
> 1. [`especificacao/V2/design-system.md`](../../../especificacao/V2/design-system.md) §Tokens — **fonte canônica**
> 2. `app/resources/css/tokens.css` — materialização em código (`IDR-008`), única fonte autorizada de hex literal
>
> Duplicar a tabela de tokens aqui criaria uma terceira verdade que sairia de sincronia na primeira mudança. Este arquivo carrega o que **não** está lá: as restrições de uso que só aparecem quando um token encontra uma tela real.

## Nenhum token novo desde o DS canônico v1

Estado em 2026-08-04. As três telas do DRM (`STORY-046`) — formulário de 38 campos, demonstração financeira de três níveis, série histórica com quatro estados de competência — foram especificadas com a paleta v1 intacta.

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
| `farol-vermelho` | `surface` | 4,83:1 | ✅ |

### Combinações **proibidas** para texto normal

| Texto | Fundo | Razão | Consequência prática |
|---|---|---|---|
| `tertiary` | `neutral` | **4,45:1** ❌ | Link ou rótulo em `tertiary` **nunca** sobre `--color-neutral`. No `seletor-modo`, a opção selecionada usa `tertiary` **só** em borda e marcador (piso 3:1); o rótulo é `primary` |
| `destructive` | `neutral` | **4,45:1** ❌ | Mensagem de erro fica sobre `--color-surface`. O padrão em produção (`destructive/5` sobre branco) atende |
| `farol-verde` | `surface` | **3,39:1** ❌ | Ver dívida 1 abaixo |
| `farol-amarelo` | `surface` | **3,19:1** ❌ | Ver dívida 1 abaixo |
| `farol-cinza` | `surface` | **2,56:1** ❌ | Reprova até o piso de 3:1 para ícone. Ver dívida 1 |

`tertiary` e `destructive` passam por **0,20** sobre branco e reprovam por **0,05** sobre neutral. A margem é fina o suficiente para que "quase igual" não valha: fundo branco ou reprova.

---

## Dívidas de acessibilidade do produto

Atravessam telas já em produção. **Nenhuma foi criada pela `STORY-046`**; todas foram descobertas ao verificar as combinações que ela usa.

### 1. Pills de farol reprovam AA como texto pequeno

`<x-relatorio.farol>` renderiza o rótulo em `text-xs` (12px) na cor do farol sobre um fundo de 10–12% da mesma cor — na prática, quase branco. Verde mede 3,39:1, amarelo 3,19:1, cinza 2,56:1. O piso para texto normal é 4,5:1.

A ironia importa: o farol ganhou ícone **e** texto justamente para não depender de cor, protegendo quem tem dificuldade de distinguir cores — e é exatamente essa pessoa que o contraste insuficiente ataca.

**Dono:** `STORY-059`, CA-3, que já delega a solução ao Designer.
**Restrição para quem resolver:** o `EPIC-010` reserva slot de farol na Demonstração do Mês (`SCREEN-STORY-046-demonstracao-mes` §4.12). O que a `STORY-059` decidir vale lá **sem renegociação** — a `STORY-054` não deve inventar tratamento próprio.

**Direções possíveis** (a decisão é da `STORY-059`, não deste arquivo): escurecer verde e amarelo até 4,5:1 sobre branco; ou manter a cor no ícone (piso 3:1) e levar o rótulo para `--color-primary`; ou aumentar o rótulo para ≥18pt e passar a valer o piso de 3:1. A terceira é a pior — aumenta o peso visual do farol sem ganho.

### 2. Borda de input mede 1,23:1

`--color-input` (#E3E8EE) sobre `--color-surface` (#FFFFFF) = **1,23:1**. A WCAG 2.1 §1.4.11 (Non-text Contrast) pede **3:1** para informação visual necessária para identificar componentes de interface — e a borda é o que identifica um campo como campo.

Atinge `.input` e `.input-affix`, portanto **todas as telas de formulário do produto**. Corrigir exige mexer num token da fundação e reavaliar telas em produção — **exige DDR próprio**, não cabe numa estória de tela.

**Escalado ao PO** na `STORY-046`. Sugestão: avaliar junto com a `STORY-059`, que já é a estória de dívida de acessibilidade.

### 3. `<x-help>` com alvo de toque de 18×18px

`.help__trigger` mede 18×18px. O piso é **44×44px** (`accessibility-basics` §7). Está em produção no Quiz e seria herdado pelo formulário do DRM, que usa `<x-help>` em dezenas de campos — multiplicando o problema.

É **bug**, não decisão de design. Correção provável: manter o círculo de 18px como área visível e ampliar a área de toque por padding transparente até 44px.

**Escalado ao PO** na `STORY-046`. Sugestão: `STORY-059`.

---

## Como propor token novo

1. Confirme que nenhuma combinação existente resolve. Cinco casos das telas do DRM pareciam pedir token e nenhum precisou — o padrão é que texto resolve o que cor pareceria resolver.
2. Se resolver mesmo assim: **DDR** com as opções consideradas e as medições de contraste de cada uma.
3. Aprovado, o token entra em `app/resources/css/tokens.css` (`@theme`) **e** no DS canônico, com bump de versão lá.
4. Nunca introduza segundo acento concorrente com `tertiary`, gradiente, ou peso ≥700 — são os três "don'ts" duros do DS canônico.
