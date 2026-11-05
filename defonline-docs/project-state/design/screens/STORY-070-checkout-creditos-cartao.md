---
id: SCREEN-STORY-070-checkout-creditos-cartao
story: STORY-070-design-telas-cobranca
implemented_by: STORY-078
epic: EPIC-005-cobranca-e-planos
status: ready
created_at: 2026-11-05
updated_at: 2026-11-05
owner_designer: designer (claude-sonnet-5-5)
related_ddrs: [DDR-008, DDR-010]
ds_components_used: [page-header, card, button, label, input, aviso-inline, resumo-pedido, seletor-modo]
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Checkout de pacote de créditos: escolha entre Pix e cartão

> Referência: `STORY-070`; implementada por `STORY-078`. Estende `SCREEN-STORY-070-checkout-pix-creditos` (estrutura comum, Pix) com o cartão do `SCREEN-STORY-070-checkout-assinatura` (campos de cartão). **Esta spec só descreve o que muda.** Microcopy provisória.

## 1. Objetivo
Deixar Roberto escolher **Pix ou cartão** para o pacote, sabendo que o cartão **não cria recorrência**.

## 2. Fluxo
- Entrada: a mesma da 076. A 076 publica o checkout só com Pix (sem seletor); a 078 acrescenta o seletor.
- Ação primária muda com o meio: `Gerar QR Code Pix · R$ {valor}` ou `Pagar com cartão · R$ {valor}`.
- Saída: Pix → tela do QR (como na 076); cartão aprovado → Confirmação.

## 3. Layout

### Mobile (≥360px) — só o bloco "Pagamento" muda

```
| Como você quer pagar?                      [?]   |
| +----------------------+---------------------+   |
| | (•) Pix              | ( ) Cartão          |   |
| |  libera quando o     |  libera na hora ·   |   |
| |  banco confirmar     |  sem renovação      |   |
| +----------------------+---------------------+   |
| (Pix) QR Code vale por 30 minutos.               |
| (Cartão)  campos de cartão, como em              |
|           checkout-assinatura                    |
```

- `seletor-modo` (DDR-010). **Pix vem selecionado** por ser o meio da 076 e ser sem cadastro de cartão; não há "recomendado".
- Trocar o meio **não** zera o aceite nem o cupom; só troca o bloco de pagamento e o texto do botão. O resumo não muda (o preço é o mesmo nos dois meios).
- A linha `Ciclo` do resumo continua `Compra única, não renova` nos dois meios: é a resposta direta à dúvida "cartão significa assinatura?".

### Desktop
Igual ao mobile, em duas colunas; o seletor ocupa a largura da coluna esquerda.

## 4. Estados
- **Caminho feliz (cartão):** Confirmação idêntica à da 076, com `Pago com cartão final 4242` no lugar de "Recebemos o Pix".
- **Loading:** como `checkout-assinatura` §4.2.
- **Vazio:** como a 076.
- **Erro:** cartão recusado e falha do provedor como em `checkout-assinatura` §4.4, com uma linha a mais no `alerta` de recusa: `Você pode tentar outro cartão ou pagar com Pix.`, com a ação `Pagar com Pix` que troca o seletor. Pix: erros como na 076.
- **Sem permissão:** "Logar como" como na 076.
- **Troca de meio depois de gerar um QR:** se o usuário já gerou o QR e volta para pagar no cartão, o QR em aberto continua válido até expirar; a tela avisa `Você tem um QR Code Pix em aberto, válido até {hh:mm}. Se pagar com cartão, ignore-o.` (evita pagamento duplo; o back-end rejeita o segundo, mas a tela previne.)

## 5. Microcopy (provisório)

| Lugar | Texto |
|---|---|
| Legenda do seletor | Como você quer pagar? |
| Opção Pix | Pix · libera quando o banco confirmar |
| Opção cartão | Cartão de crédito · libera na hora, sem renovação |
| CTA Pix / cartão | Gerar QR Code Pix · R$ {valor} / Pagar com cartão · R$ {valor} |
| Sucesso (cartão) | Pagamento confirmado · Pago com cartão final {4}. Você tem {n} créditos, válidos até {data}. |
| Recusa, linha extra | Você pode tentar outro cartão ou pagar com Pix. |
| QR em aberto | Você tem um QR Code Pix em aberto, válido até {hh:mm}. Se pagar com cartão, ignore-o. |

## 6. Acessibilidade
Como DDR-002/010 (`radiogroup` real, setas navegam); trocar o meio anuncia o bloco novo por `aria-live="polite"` ("Campos do cartão exibidos"). Foco permanece no seletor.

## 7. Identificadores E2E
`screen-checkout-meio` · `screen-checkout-meio-pix` · `screen-checkout-meio-cartao` (demais como as duas telas-base).

## 8. Exceções ao DS
Nenhuma além da DDR-010.

## 9. Dependências
Campos de cartão sujeitos ao ADR-010 (ver `checkout-assinatura` §9). Dado de cartão não é gravado nem logado (CA-5 da 078) — a tela não repete o número em nenhuma confirmação: só bandeira e últimos 4.

## 10. Histórico
| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-11-05 | criação | designer (claude-sonnet-5-5) | STORY-070 |
