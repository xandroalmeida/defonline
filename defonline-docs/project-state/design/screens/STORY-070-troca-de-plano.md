---
id: SCREEN-STORY-070-troca-de-plano
story: STORY-070-design-telas-cobranca
implemented_by: STORY-082
epic: EPIC-005-cobranca-e-planos
status: ready
created_at: 2026-11-05
updated_at: 2026-11-05
owner_designer: designer (claude-sonnet-5-5)
related_ddrs: [DDR-008, DDR-009, DDR-010]
ds_components_used: [page-header, card, button, pill, aviso-inline, resumo-pedido, seletor-modo]
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Troca de plano e de ciclo (upgrade imediato, downgrade agendado)

> Referência: `STORY-070`; implementada por `STORY-082`. Parte de `Meu plano` → `Trocar plano`. Reusa `resumo-pedido` (DDR-008) e o bloco de pagamento do checkout. Microcopy provisória.

## 1. Objetivo
Deixar Roberto subir (ou descer) de plano **vendo exatamente o que muda e quanto paga hoje**.

## 2. Fluxo
1. `Trocar plano` abre a lista dos planos ativos (cartões compactos, como `planos-e-precos`), com o atual marcado `Seu plano`.
2. Escolhe um → tela de revisão. **Upgrade** (mais caro ou mais cota): resumo com cobrança proporcional hoje. **Downgrade**: resumo sem cobrança hoje e com a data de vigência.
3. Confirma → upgrade: pagamento (cartão salvo, ou Pix se o plano é anual por Pix) e novo plano na hora; downgrade: agendado.

## 3. Layout

### Revisão de upgrade — mobile (desktop: duas colunas como o checkout)
```
| Trocar para o plano Pro                          |
| ┌ Resumo do pedido ────────────────────────────┐ |
| │ Pro · mensal                                  │ |
| │ Preço do Pro                   R$ 199,00      │ |
| │ Crédito do Básico não usado  − R$ 31,40       │ |
| │ A pagar hoje                   R$ 167,60      │ |
| │ Ciclo   Mensal, renova sozinho no cartão      │ |
| │ Próxima cobrança  05/12/2026 · R$ 199,00      │ |
| │ Depois            R$ 199,00 por mês           │ |
| └───────────────────────────────────────────────┘ |
| Hoje você paga só a diferença pelos 21 dias que  |
| faltam neste ciclo. Sua cota passa a ser de 10   |
| análises a partir de agora.                      |
| Cartão final 4242        [ Trocar cartão ]       |
| [      Trocar para o Pro · R$ 167,60       ]     |
```
- A linha `Desconto` do DDR-008 é reaproveitada como **crédito proporcional** (`Crédito do Básico não usado`), nunca negativa no total (CA-3).
- A mudança de **ciclo** (mensal ↔ anual) usa `seletor-modo` (DDR-010).
- O resumo diz de forma explícita a cota nova e quando ela vale.

### Revisão de downgrade
```
| Trocar para o plano Básico                       |
| Resumo: A pagar hoje R$ 0,00                     |
| Próxima cobrança  05/12/2026 · R$ 49,90          |
| Você continua no Pro até 05/12/2026, com todas   |
| as análises do ciclo. Depois disso, passa a ter  |
| 1 análise por mês.                               |
| [ Agendar troca para o Básico ]                  |
```
Em Meu plano, o downgrade agendado aparece no cartão do plano com `Desfazer` (CA-4).

## 4. Estados
- **Feliz (upgrade):** Confirmação: `Você agora está no plano Pro. Sua cota é de 10 análises e você já pode usá-las. Pagamos R$ 167,60 no cartão final 4242.`
- **Loading:** esqueleto do resumo; `Trocar…` em `loading` no botão.
- **Vazio:** sem outro plano ativo no catálogo: `Não há outros planos disponíveis no momento.` + `Voltar para Meu plano`.
- **Erro:** upgrade recusado (CA-2): `Cartão recusado` como no checkout, **mais** `Seu plano continua o Básico.` Sem cobrança parcial. Falha do provedor: `Não conseguimos concluir a troca agora. Seu plano continua o mesmo.`
- **Plano atual desativado no catálogo (CA-5):** o cartão do plano atual fica visível com `Seu plano · não está mais à venda; você pode continuar com ele`; o plano desativado **não** aparece como destino.
- **Primeiro e último dia do ciclo:** no último dia o crédito proporcional é ≈ 0 e o resumo mostra o valor real calculado; no primeiro, o crédito é ≈ o preço. Nenhum caso mostra valor negativo.
- **Sem permissão:** "Logar como": só leitura, como em Meu plano.
- **Plano anual por Pix:** o upgrade proporcional é pago com novo QR Code (mesma tela do Pix); **pergunta 1**.
- **Plano `Cancelado`:** `Trocar plano` não existe (Meu plano pede reativar primeiro).
- **Em atraso:** a troca fica indisponível: `Atualize o cartão antes de trocar de plano.`

## 5. Microcopy (provisório)
| Lugar | Texto |
|---|---|
| Título | Trocar para o plano {nome} |
| Crédito | Crédito do {atual} não usado |
| Explicação upgrade | Hoje você paga só a diferença pelos {n} dias que faltam neste ciclo. Sua cota passa a ser de {n} análises a partir de agora. |
| Explicação downgrade | Você continua no {atual} até {data}, com todas as análises do ciclo. Depois disso, passa a ter {n} por mês. |
| CTA | Trocar para o {nome} · R$ {valor} / Agendar troca para o {nome} |
| Agendado | Troca agendada para {data}. Dá para desfazer até lá. |
| Desfazer | Desfazer troca |
| Atual | Seu plano |

## 6. Acessibilidade
Resumo como `region`; resultado de `Desfazer` e de agendamento em `aria-live="polite"`; botões com nome completo (`Trocar para o plano Pro por R$ 167,60`).

## 7. Identificadores E2E
`screen-troca-lista` · `-plano-{id}` · `-resumo` · `-credito` · `-confirmar` · `-agendado` · `-desfazer`.

## 8. Exceções ao DS
Nenhuma.

## 9. Perguntas ao PO
1. **Upgrade em plano anual pago por Pix:** padrão adotado: gera QR Code da diferença; alternativa: não permitir upgrade no anual-Pix até o fim do ciclo. Qual?
2. **Troca de ciclo (mensal ↔ anual)**: tratada como upgrade/downgrade pela regra de valor; PO confirma que a spec §2.1 (upgrade proporcional, downgrade no próximo ciclo) vale para ciclo também.

## 10. Histórico
| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-11-05 | criação | designer (claude-sonnet-5-5) | STORY-070 |
