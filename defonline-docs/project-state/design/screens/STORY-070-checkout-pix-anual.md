---
id: SCREEN-STORY-070-checkout-pix-anual
story: STORY-070-design-telas-cobranca
implemented_by: STORY-080
epic: EPIC-005-cobranca-e-planos
status: draft
created_at: 2026-11-05
updated_at: 2026-11-05
owner_designer: designer (claude-sonnet-5-5)
related_ddrs: [DDR-008, DDR-009, DDR-010]
ds_components_used: [page-header, card, button, aviso-inline, resumo-pedido, seletor-modo, pill]
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Plano anual com Pix: contratação e renovação

> Referência: `STORY-070`; implementada por `STORY-080`. Estende `checkout-assinatura` (cartão) com o ramo Pix, e reaproveita a tela de QR Code de `checkout-pix-creditos`. Só o que muda está aqui. Microcopy provisória.

## 1. Objetivo
Deixar quem não usa cartão contratar o plano anual com Pix, **entendendo que este plano não renova sozinho** e que a renovação é um novo QR Code.

## 2. Fluxo
- Entrada: `checkout-assinatura` com ciclo Anual. No mensal o seletor de meio **não aparece** (só cartão; DDR-010 item 4).
- Pix anual: Revisão → QR Code → Confirmação (assinatura só fica ativa depois da confirmação do banco, nunca antes — CA-2).
- Renovação: e-mail e aviso em Meu plano levam a `Pagar renovação`, que abre esta mesma tela com o QR da renovação.

## 3. Layout

### Bloco de pagamento (anual) — mobile e desktop
```
| Como você quer pagar?                      [?]   |
| +----------------------+---------------------+   |
| | (•) Cartão           | ( ) Pix             |   |
| |  libera na hora ·    |  libera quando o    |   |
| |  renova sozinho      |  banco confirmar ·  |   |
| |                      |  você paga a        |   |
| |                      |  renovação          |   |
| +----------------------+---------------------+   |
```
Com Pix selecionado, o **resumo muda a linha `Ciclo`** para `Anual, não renova sozinho — avisamos 7 dias antes`, a `Próxima cobrança` para `Não há cobrança automática. Renovação em 05/11/2027` e `Depois` para `Você paga R$ 399,00 por um novo QR Code, se quiser continuar`. O botão vira `Gerar QR Code Pix · R$ 399,00`.

### Tela do QR Code
Igual a `checkout-pix-creditos` §3.3/3.4, com o título `Pague o plano anual` e a linha `O plano começa quando o banco confirmar o pagamento.`

### Renovação no último mês do ciclo (Meu plano)
`aviso-inline` `info` (DDR-009), do 7º dia antes do fim em diante: `Seu plano anual termina em 05/11/2027. Ele não renova sozinho.` + `Pagar renovação · R$ 399,00`. A renovação **estende o plano a partir do fim do ciclo atual** (sem perder dias), e a tela diz a nova data: `O novo ciclo vai até 05/11/2028.`

## 4. Estados
- **Feliz:** Confirmação: `Plano anual ativo` / `Plano Básico · anual, até 05/11/2027. Esta assinatura não renova sozinha: avisaremos 7 dias antes.`
- **Loading:** como as telas-base.
- **Vazio:** n/a. Plano mensal não oferece Pix; se alguém chega com `?meio=pix` num mensal, o seletor não aparece e a tela segue com cartão.
- **Erro:** Pix expirado: `O QR Code expirou e nada foi cobrado.` + `Gerar novo QR Code`; a assinatura **não** é ativada (CA-3). Falha do provedor e rede como na 076.
- **Sem pagamento até o fim do ciclo (CA-5):** não há retentativa de cobrança. Em Meu plano, após a data: situação `Encerrado` (DDR-009) com `Seu plano terminou em 05/11/2027. Seus diagnósticos continuam disponíveis.` + `Renovar com Pix` / `Assinar com cartão`. Nada de "em atraso", porque nada falhou.
- **Sem permissão:** "Logar como" como na 076.

## 5. Microcopy (provisório)
| Lugar | Texto |
|---|---|
| Opção cartão (anual) | Cartão de crédito · libera na hora, renova sozinho |
| Opção Pix (anual) | Pix · libera quando o banco confirmar, você paga a renovação |
| Ciclo (Pix) | Anual, não renova sozinho — avisamos 7 dias antes |
| Próxima cobrança (Pix) | Não há cobrança automática. Renovação em {data} |
| Depois (Pix) | Você paga R$ {valor} com um novo QR Code, se quiser continuar |
| Título QR | Pague o plano anual |
| Apoio QR | O plano começa quando o banco confirmar o pagamento. |
| Aviso de renovação | Seu plano anual termina em {data}. Ele não renova sozinho. |
| Ação | Pagar renovação · R$ {valor} |
| Novo ciclo | O novo ciclo vai até {data}. |
| Encerrado | Seu plano terminou em {data}. Seus diagnósticos continuam disponíveis. |

## 6. Acessibilidade
Como as telas-base. O aviso de renovação é `aviso-inline` `info` com `role="status"`; o texto do aviso nomeia a data, não só "em breve".

## 7. Identificadores E2E
`screen-checkout-meio-pix` · `screen-checkout-pix-qr` · `screen-meuplano-renovar-pix` · `screen-meuplano-encerrado`.

## 8. Exceções ao DS
Nenhuma (DDR-010).

## 9. Dependências e perguntas ao PO
- **Valor mínimo/Pix em plano anual** depende do ADR-010 (CA-4: "Pix em plano anual").
- **Pergunta:** quem tem plano anual por Pix, cancela como? Padrão adotado: **não há botão "Cancelar renovação"** (não há renovação a cancelar); Meu plano mostra `Este plano não renova sozinho. Termina em {data}.` Isso preserva o CA-4 da STORY-070 (a tela diz até quando o acesso vai), mas a ação de cancelar some. Confirma?

## 10. Histórico
| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-11-05 | criação | designer (claude-sonnet-5-5) | STORY-070 |
