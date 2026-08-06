# Design System vivo — DEFOnline

**Status:** ativo desde 2026-08-04 (inicializado pelo Designer na `STORY-046`)
**Herda de:** [`especificacao/V2/design-system.md`](../../../especificacao/V2/design-system.md) — **v1, canônico**
**Materializado em código por:** `app/resources/css/tokens.css` (`IDR-008`)

---

## O que é este diretório

Este é o Design System **vivo**: a evolução do DS canônico à medida que ele encontra telas reais.

Ele **herda, não substitui**. O DS canônico em `especificacao/V2/design-system.md` continua sendo a fonte de verdade da fundação visual — paleta, tipografia, espaçamento, raios, sombras e a regra do acento único. Nada aqui pode contradizê-lo.

| Arquivo | O que carrega |
|---|---|
| [`tokens.md`](tokens.md) | **Ponteiro** para o DS canônico + as **restrições de uso** de token descobertas em telas reais (contraste, combinações proibidas) |
| [`components.md`](components.md) | Componentes que existem no produto — os implementados e os especificados, com anatomia, variantes e estados |
| [`patterns.md`](patterns.md) | Padrões compostos: formulário longo, pré-preenchimento, demonstração financeira, indisponibilidade |
| [`voice-and-tone.md`](voice-and-tone.md) | **Ponteiro** para a referência de tom + o vocabulário fixado por DDR |

## As regras que governam este diretório

1. **Mudança na fundação visual** (paleta, tipografia, regra do acento único) exige **DDR + atualização coordenada** do DS canônico. Não se muda aqui e propaga depois.
2. **Componente novo entra por DDR**, nunca direto. Cada componente abaixo cita o DDR que o criou.
3. **O DS é atualizado na mesma operação** em que a tela que o consome é especificada. "Atualizo o DS depois" é como componente fantasma nasce.
4. **Stack-agnóstico.** Descreve comportamento, tokens e estados — não código de framework. O de-para para Blade/Livewire/Tailwind é do Programador (`IDR-008`).
5. **Nenhum hex literal fora de `tokens.css`** (`IDR-008`). O teste arquitetural `tests/Arch/DesignTokensTest.php` reprova.

## Estado atual — 2026-08-04

| | Quantidade |
|---|---|
| Componentes implementados em produção | 12 |
| Componentes especificados, aguardando implementação | 5 |
| Padrões registrados | 4 |
| DDRs vigentes | 5 (todos `proposed` — aguardando aprovação do PO) |
| Tokens novos criados desde o DS canônico v1 | **0** |

O zero da última linha é deliberado e vale ser lido: as três telas do DRM (`STORY-046`), incluindo um formulário de 38 campos e uma demonstração financeira de três níveis, foram especificadas **sem nenhum token novo**. A paleta v1 aguentou.

## Dívidas conhecidas

Registradas aqui porque atravessam o produto inteiro e nenhuma pertence a uma estória só. Detalhe e evidência em [`tokens.md`](tokens.md).

| Dívida | Onde | Dono sugerido |
|---|---|---|
| Pills de farol reprovam AA como texto pequeno (verde 3,39:1; amarelo 3,19:1; cinza 2,56:1) | `<x-relatorio.farol>` | **`STORY-059`** — já é a estória, CA-3 delega a solução ao Designer |
| Borda de input mede 1,23:1 (WCAG 1.4.11 pede 3:1 para limite de componente interativo) | `.input`, `.input-affix` | escalado ao PO na `STORY-046` |
| `<x-help>` tem alvo de toque de 18×18px (piso é 44px) | `.help__trigger` | escalado ao PO na `STORY-046` |

## Histórico

- **2026-08-04 — inicialização.** Criado pelo Designer durante a `STORY-046` (specs das três telas do DRM). Registra os 12 componentes já em produção desde a `STORY-019`, especifica 5 novos, e formaliza 4 padrões compostos. Nenhum token novo. Três dívidas de acessibilidade do produto documentadas com evidência numérica.
