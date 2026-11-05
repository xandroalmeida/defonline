---
id: DDR-008
title: Resumo do pedido — toda cobrança mostra, antes da confirmação, as mesmas seis linhas, inclusive as que valem "Não há"
status: proposed
created_at: 2026-11-05
decided_at: ~
approved_by: ~
supersedes: ~
superseded_by: ~
related_ddrs: [DDR-005]
related_adrs: []
related_pdrs: [PDR-020]
scope: transversal (cobrança)
affects_screens: [SCREEN-STORY-070-checkout-pix-creditos, SCREEN-STORY-070-checkout-assinatura, SCREEN-STORY-070-checkout-creditos-cartao, SCREEN-STORY-070-checkout-pix-anual, SCREEN-STORY-070-cupom-no-checkout, SCREEN-STORY-070-troca-de-plano]
---

# DDR-008 — Resumo do pedido sem letra miúda

## Contexto

A STORY-070 (CA-3) exige que o checkout mostre preço cheio, desconto, valor a pagar, ciclo, próxima cobrança e valor das cobranças seguintes antes da confirmação. O "porquê" da estória é que Roberto entenda o que compra, quanto paga, quando renova e como cancela, sem letra miúda. Há seis tipos de checkout (pacote Pix, pacote cartão, plano mensal cartão, plano anual cartão, plano anual Pix, upgrade com proporcional) e três variáveis que mudam o texto (cupom, ciclos de desconto, desconto total). Se cada tela montar o seu resumo, a próxima cobrança vai aparecer num lugar diferente em cada uma, e o usuário vai aprender a procurar.

Leitura: STORY-070; PDR-020; spec funcional §2.1, §2.2, §2.4, §2.5, §3.4; DS v1.1; DDR-005 (ausência explícita, nunca célula vazia).

## Forças (drivers)

- **Confiança no momento de pagar** (alto): é o primeiro fluxo em que o usuário entrega dinheiro; surpresa de cobrança vira chargeback e reclamação (motivo declarado da STORY-081).
- **Princípio de simplicidade** (alto): o resumo não pode virar tabela de condições.
- **Consistência entre seis checkouts** (alto): o mesmo componente tem de servir a todos.
- **Cupom com duração em ciclos** (médio): "cobranças seguintes" precisa dizer quanto, por quanto tempo e quando muda.

## Opções consideradas

### Opção A — Resumo mínimo (item + total) e as condições em texto corrido abaixo
Prós: curto. Contras: é a definição de letra miúda; a próxima cobrança fica escondida em parágrafo.

### Opção B — Seis linhas fixas, sempre presentes (escolhida)
```
Plano Básico · mensal
Preço                         R$ 49,90
Desconto (cupom BETA)       − R$ 24,95
─────────────────────────────────────
A pagar hoje                  R$ 24,95
─────────────────────────────────────
Ciclo        Mensal, renova sozinho no cartão
Próxima      05/12/2026 · R$ 24,95
Depois       R$ 49,90 por mês, a partir de 05/02/2027
```
Prós: o mesmo lugar para a mesma resposta em qualquer checkout; linha que não se aplica diz "Não há", o que também é informação ("compra única, sem renovação").
Contras: em pacote avulso, três linhas dizem "Não há" ou "Sem renovação". Aceito: é o preço da previsibilidade.

### Status quo
Não há checkout hoje.

## Avaliação contra os princípios

Honestidade e previsibilidade vencem a economia de linhas. A opção B é a única em que a pergunta "quanto vou pagar mês que vem?" tem resposta na mesma posição em toda tela.

## Decisão

> **Opção B.** Componente `resumo-pedido`, com a anatomia abaixo, usado em todo checkout e na confirmação de troca de plano.

1. **Linhas, nesta ordem, sempre presentes:** `Preço` · `Desconto` · `A pagar hoje` · `Ciclo` · `Próxima cobrança` · `Depois` (cobranças seguintes).
2. `Desconto` aparece com `—` ("Sem desconto") quando não há cupom, para o campo de cupom não mudar a altura do resumo de forma surpreendente. Com cupom: `− R$ x` e o código entre parênteses.
3. **`A pagar hoje` é a linha dominante** (`--text-h3`, peso 600) e é o mesmo número que está escrito no botão de confirmar (`Pagar R$ 24,95`). Os dois nunca divergem.
4. **`Ciclo` diz como renova**, em uma frase: "Mensal, renova sozinho no cartão" / "Anual, renova sozinho no cartão" / "Anual, não renova sozinho — avisamos 7 dias antes" / "Compra única, não renova".
5. **`Próxima cobrança`** traz data e valor, ou "Não há" (pacote; plano anual pago por Pix; desconto total sem cartão cadastrado: ver 6).
6. **`Depois`** diz o valor que passa a valer e desde quando, quando diferente da próxima cobrança (fim do cupom, reajuste); senão repete a cadência ("R$ 49,90 por mês"). Pacote: "Não há".
7. **Desconto total** (`A pagar hoje` = R$ 0,00): a linha `A pagar hoje` continua visível com R$ 0,00 e o botão vira `Ativar sem pagar`. `Depois` diz o preço cheio e a data em que o desconto acaba. Nunca esconder o preço de depois.
8. **Valores em `tabular-nums`, alinhados à direita**, rótulos à esquerda. Em mobile as três últimas linhas empilham rótulo sobre valor.
9. **Só texto e sinal**, nunca cor para desconto: `−` e a palavra "desconto". Verde de desconto é farol, e farol é interpretação.
10. **Semântica:** `<dl>` com `<dt>`/`<dd>`; `A pagar hoje` é a última linha antes do aceite e o resumo é uma `region` nomeada "Resumo do pedido".

## Consequências

### Positivas
- Um componente, seis checkouts, um lugar para cada resposta.
- Testável: o E2E afirma as seis linhas em qualquer fluxo.

### Negativas / trade-offs aceitos
- Linhas "Não há" em pacote. Aceitas.

### Impacto no Design System
- Componente novo `resumo-pedido` (entra em `components.md` quando o DDR for aceito). Zero token novo.

### Para o Programador
- O cálculo de datas e valores vem pronto do back-end (Domain). O componente só apresenta; não calcula desconto nem proporcional na view.
