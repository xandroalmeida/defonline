---
id: SCREEN-STORY-070-cupom-no-checkout
story: STORY-070-design-telas-cobranca
implemented_by: STORY-083
epic: EPIC-005-cobranca-e-planos
status: draft
created_at: 2026-11-05
updated_at: 2026-11-05
owner_designer: designer (claude-sonnet-5-5)
related_ddrs: [DDR-008, DDR-009]
ds_components_used: [input, label, button, aviso-inline, resumo-pedido, pill]
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Cupom no checkout, cupom concedido e fim do desconto

> Referência: `STORY-070`; implementada por `STORY-083`. Acrescenta o campo de cupom a **todos** os checkouts (`checkout-pix-creditos`, `checkout-assinatura`, `checkout-creditos-cartao`, `checkout-pix-anual`) e define o aviso de fim de desconto. Microcopy provisória.

## 1. Objetivo
Mostrar, antes de confirmar, **preço cheio, desconto e valor a pagar**, e deixar claro por quanto tempo o desconto vale e o que acontece depois.

## 2. Fluxo
- O campo fica colapsado: `Tem cupom? Adicionar cupom` (botão tipo link, sobre `surface`). Aberto: campo + `Aplicar`.
- **Cupom concedido à conta** (beta): já vem aplicado, sem o usuário digitar, com a frase `Cupom {código} aplicado à sua conta.` e sem campo para digitar outro, porque cupons não acumulam (pergunta 1).
- Aplicar atualiza o `resumo-pedido` na hora, sem recarregar a página; o botão principal muda para o novo valor.
- Remover: `Remover cupom` volta ao preço cheio.

## 3. Layout

### Campo (mobile e desktop)
```
| Tem cupom?                                       |
| [ Código do cupom            ] [ Aplicar ]       |
```
Aplicado:
```
| ✓ BETA50 — 50% de desconto em 3 cobranças        |
|   [ Remover cupom ]                              |
```
(O "✓" é complemento; o texto `aplicado` é o sinal.)

### Resumo com cupom — 50% por 3 ciclos, plano mensal
```
Preço                          R$ 49,90
Desconto (BETA50)            − R$ 24,95
A pagar hoje                   R$ 24,95
Ciclo        Mensal, renova sozinho no cartão
Próxima cobrança   05/12/2026 · R$ 24,95
Depois             R$ 49,90 por mês, a partir de 05/02/2027
```
A linha `Depois` é o ponto central: o desconto tem fim e ele está dito antes de pagar (CA-7).

### Desconto total
```
Preço                          R$ 49,90
Desconto (BETA100)           − R$ 49,90
A pagar hoje                   R$ 0,00
Ciclo        Mensal. Sem cobrança enquanto durar o desconto
Próxima cobrança   Nenhuma até 05/02/2027
Depois       R$ 49,90 por mês, a partir de 05/02/2027.
             Cadastre um cartão em Meu plano para continuar.
```
Sem campos de cartão e sem Pix: o bloco de pagamento é substituído por `Você não precisa pagar nada agora.`; botão `Ativar sem pagar` (CA-6). O aceite do Termo continua obrigatório.

## 4. Estados
- **Loading:** `Aplicar` em `loading`; resumo mantém os valores anteriores, esmaecidos só pela palavra `Atualizando…` (sem trocar de layout).
- **Vazio:** campo colapsado; sem cupom, `Desconto` diz `Sem desconto`.
- **Erro (CA-3):** sempre sob o campo, motivo específico, sem culpar:
  | Caso | Texto |
  |---|---|
  | não existe | Não encontramos esse cupom. Confira o código. |
  | vencido | Este cupom venceu em {data}. |
  | inativo | Este cupom não está mais ativo. |
  | esgotado | Este cupom atingiu o limite de usos. |
  | já usado pela conta | Você já usou este cupom. |
  | não cobre o item | Este cupom não vale para {plano/pacote/ciclo}. Ele vale para {itens}. |
  | outro cupom já aplicado | Só é possível usar um cupom por compra. Remova {código} para usar este. |
- **Valor abaixo do mínimo do provedor (CA-5):** a regra é do ADR-010 e ainda não existe. Padrão provisório: `aviso-inline` `info`: `Com este cupom o valor fica abaixo do mínimo que o processador aceita. [texto final conforme ADR-010]`. Pergunta 2.
- **Último uso disputado (CA-4):** se, ao confirmar, o cupom esgotou: o formulário permanece, o desconto sai do resumo e aparece `Este cupom acabou de atingir o limite de usos. O valor voltou ao preço cheio — confira antes de pagar.` O pagamento **não** prossegue com valor diferente do que estava na tela sem novo clique.
- **Cupom concedido (CA-2):** `aviso-inline` `info` no resumo: `Cupom {código} aplicado à sua conta: {50%} de desconto por {3} cobranças.`
- **Sem permissão:** "Logar como" como nas telas-base.

### Aviso de fim do desconto (CA-8) — Meu plano e e-mail
Sete dias antes: `aviso-inline` `info` (DDR-009): `Seu desconto termina em {data}. A partir daí o plano custa R$ 49,90 por mês.` Ação: `Ver meu plano`. Sem cartão cadastrado (desconto total): `alerta`: `Seu desconto termina em {data}. Sem um cartão cadastrado, as funções pagas serão bloqueadas depois dessa data.` + `Cadastrar cartão`. Depois da data, sem cartão: situação `Encerrado`/bloqueio com `Cadastrar cartão` (o usuário regulariza em Meu plano).

## 5. Microcopy
Listada nas tabelas acima. Rótulos: `Tem cupom?` · `Código do cupom` (placeholder `Ex.: BETA50`) · `Aplicar` · `Remover cupom` · `Ativar sem pagar` · `Você não precisa pagar nada agora.`

## 6. Acessibilidade
Resultado de `Aplicar` anunciado por `aria-live="polite"` ("Cupom aplicado. Novo valor a pagar: R$ 24,95"); erro por `aria-describedby`; foco permanece no campo em erro e vai ao botão `Remover cupom` em sucesso. Código em caixa alta é só estilo (`text-transform`), o valor enviado é normalizado no servidor.

## 7. Identificadores E2E
`screen-checkout-cupom-toggle` · `-campo` · `-aplicar` · `-remover` · `screen-checkout-error-cupom` · `screen-checkout-cupom-concedido` · `screen-checkout-sem-pagamento`.

## 8. Exceções ao DS
Nenhuma.

## 9. Perguntas ao PO
1. **Cupom concedido + cupom digitado:** padrão adotado: cupons não acumulam, então um conta com cupom concedido **não vê o campo de digitar**, e se tiver um código melhor, pede ao suporte. Alternativa: mostrar o campo e substituir o concedido. Qual?
2. **Valor mínimo do provedor:** texto e comportamento final dependem do ADR-010.
3. **Desconto total e e-mail:** o usuário sem cartão recebe o aviso de D-7 por e-mail (STORY-086) além do aviso em tela? Padrão: sim.

## 10. Histórico
| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-11-05 | criação | designer (claude-sonnet-5-5) | STORY-070 |
