---
id: SCREEN-STORY-070-checkout-assinatura
story: STORY-070-design-telas-cobranca
implemented_by: STORY-077
epic: EPIC-005-cobranca-e-planos
status: draft
created_at: 2026-11-05
updated_at: 2026-11-05
owner_designer: designer (claude-sonnet-5-5)
related_ddrs: [DDR-008, DDR-009, DDR-010]
ds_components_used: [page-header, card, button, label, input, pill, help, breadcrumb, aviso-inline, resumo-pedido]
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Checkout de assinatura com cartão (plano mensal ou anual)

> Referência: `STORY-070`; implementada por `STORY-077`. **Estrutura comum de checkout (cabeçalho, resumo, aceite, estados de e-mail não confirmado, "Logar como", falha do provedor) em `SCREEN-STORY-070-checkout-pix-creditos`.** Aqui só o que é próprio da assinatura: plano e ciclo, formulário de cartão, recorrência, recusa e ativação.
> **Microcopy provisória.**

## 1. Objetivo da tela

Fazer Roberto assinar um plano sabendo, antes de pagar, **quanto paga hoje, quando renova, quanto paga depois e como cancelar**.

## 2. Fluxo

### Entrada
De `Planos e preços` (botão `Assinar` com ciclo já escolhido), do bloqueio da análise ou de `Meu plano` (sem plano). Pré-condições: as de `checkout-pix-creditos` mais **não ter assinatura ativa** (CA-7 da 077; ver §4.3).

### Ações
- Primária: `Assinar e pagar R$ {valor}` (ou `Ativar sem pagar`, com desconto total — `cupom-no-checkout`).
- Secundárias: `Trocar ciclo` (alterna Mensal/Anual sem sair da tela), `Voltar aos planos`.

### Saída
- Aprovado: Confirmação com a assinatura ativa e a cota disponível.
- Recusado: permanece no formulário, sem assinatura.
- Plano anual: oferece também Pix (`SCREEN-STORY-070-checkout-pix-anual`); aqui só o ramo cartão.

## 3. Layout

### Mobile (≥360px)

```
+--------------------------------------------------+
| ‹ Planos e preços / Assinar                      |
| Assinar plano Básico                             |
| ( Mensal ) ( Anual — economize 33% )             |  ← troca de ciclo
|                                                  |
| ┌ Resumo do pedido ────────────────────────────┐ |
| │ Plano Básico · mensal                         │ |
| │ Preço                          R$ 49,90       │ |
| │ Desconto                       Sem desconto   │ |
| │ A pagar hoje                   R$ 49,90       │ |
| │ Ciclo     Mensal, renova sozinho no cartão    │ |
| │ Próxima cobrança  05/12/2026 · R$ 49,90       │ |
| │ Depois            R$ 49,90 por mês            │ |
| └───────────────────────────────────────────────┘ |
| Inclui 1 análise por mês, empresas sem limite e  |
| o Raio X do Negócio.                             |
|                                                  |
| Cupom: [ Adicionar cupom ]                       |
|                                                  |
| Cartão de crédito                                |
| NÚMERO DO CARTÃO                                 |
| [ 0000 0000 0000 0000          ]                 |
| NOME COMO ESTÁ NO CARTÃO                         |
| [                              ]                 |
| VALIDADE          CÓDIGO (CVV)  [?]              |
| [ MM/AA ]         [ 000 ]                        |
| Seus dados vão direto ao processador de          |
| pagamento. Nós guardamos só a bandeira e os      |
| 4 últimos dígitos.                               |
|                                                  |
| ☐ Li e aceito o Termo de Adesão (v2) e a         |
|   Política de Privacidade.                       |
|                                                  |
| [      Assinar e pagar · R$ 49,90         ]      |
| Cancele quando quiser em Meu plano. Reembolso    |
| em até 7 dias, se ainda não tiver feito análise. |
+--------------------------------------------------+
```

- A **troca de ciclo** é `seletor-modo` (DDR-010): `Mensal — R$ 49,90 por mês` / `Anual — R$ 399,00 por ano`, linha factual `renova todo mês` / `renova a cada 12 meses`. A economia ("33%") é texto, calculada pelo back-end a partir do catálogo, e nunca um adjetivo ("melhor").
- Campos de cartão seguem `input`/`label` do DS; número e CVV com `inputmode="numeric"` e `autocomplete` (`cc-number`, `cc-name`, `cc-exp`, `cc-csc`). **Se o ADR-010 escolher campos hospedados pelo provedor ou redirecionamento, o bloco de campos muda e o resto da tela não** (ver §9).
- A frase de segurança é texto de apoio; não usa ícone de cadeado nem selo (não é garantia que a tela possa dar sozinha).
- A linha "Cancele quando quiser em Meu plano" fica **abaixo do botão**, visível sem rolar, e leva ao conceito certo: cancelar é parar de renovar.

### Desktop (≥1024px)
Duas colunas como em `checkout-pix-creditos`: formulário à esquerda (ciclo, cartão, aceite, botão), resumo fixo à direita. Validade e CVV lado a lado.

## 4. Estados

### 4.1 Caminho feliz
Preenche → aceita → `Assinar e pagar` → Confirmação: `Assinatura ativa` / `Plano Básico · mensal. Você tem 1 análise disponível agora. A próxima cobrança é em 05/12/2026, de R$ 49,90, no cartão final 4242.` + `Fazer uma análise` / `Ver meu plano`. A cota está disponível de imediato (CA-2).

### 4.2 Loading
Botão `loading` (`Processando pagamento…`), todos os campos `disabled`, sem possibilidade de clicar duas vezes. Acima de 10 s: `Ainda processando. Não feche esta tela nem pague de novo.` Ao voltar de um refresh durante o processamento, a tela mostra `Estamos confirmando seu pagamento` até o provedor responder.

### 4.3 Vazio / já assinante
- Usuário com assinatura ativa abrindo o checkout: sem formulário; `aviso-inline` `info`: `Você já tem o plano Básico, que renova em 05/12/2026.` + `Ver meu plano` / `Trocar de plano` (leva a `troca-de-plano`). (CA-7)
- Plano desativado ou removido do catálogo: `bloqueio` `Este plano não está mais disponível.` + `Ver os planos`.

### 4.4 Erro
- **Campo inválido** (número, validade, CVV, nome): sob o campo, no momento de sair dele: `Confira o número do cartão.` / `Validade inválida.` / `Digite o código de 3 ou 4 dígitos.` / `Digite o nome como está no cartão.`
- **Cartão recusado (CA-3):** o formulário continua preenchido (exceto CVV, que é limpo), com `aviso-inline` `alerta` no topo, título `Cartão recusado`, com o motivo mais específico que o provedor deixar dizer, em linguagem simples: `O banco não autorizou a compra. Tente outro cartão ou fale com o banco.` / `Limite insuficiente neste cartão.` / `O código de segurança não confere.` / `Cartão vencido.` Quando o motivo for desconhecido: `O banco não autorizou o pagamento. Tente outro cartão.` Nunca "transação negada código 51". O usuário continua **sem assinatura**, e a tela diz: `Nada foi cobrado.` somente se for verdade (cobrança não criada); senão omitir a frase.
- **Falha do provedor / indisponibilidade:** diferente de recusa, e dito como tal: `Não conseguimos falar com o processador de pagamento agora. Não é problema com o seu cartão.` + `Tentar de novo`.
- **Aprovação incerta (resposta perdida):** `Estamos confirmando seu pagamento` e, ao confirmar, vai para a Confirmação. A tela nunca convida a "tentar de novo" antes de saber, para não cobrar duas vezes.

### 4.5 Sem permissão
"Logar como": botão desabilitado com a mesma explicação de `checkout-pix-creditos` §4.5.

### 4.6 Parcial
Catálogo carregou mas o provedor ainda não respondeu à tokenização: botão em `loading`; nada parcial é mostrado.

### 4.7 Primeira vez
Usuário vindo do teste: `aviso-inline` `info` no topo: `Ao assinar, seu teste termina. A análise que sobrou do teste não soma à cota do plano.` (CA-8 da 074; evita a surpresa de quem acha que acumula.)

## 5. Microcopy completo (provisório)

| Lugar | Texto |
|---|---|
| Título | Assinar plano {nome} |
| Ciclo mensal / anual | Mensal — R$ {x} por mês · renova todo mês / Anual — R$ {y} por ano · renova a cada 12 meses · economia de {n}% |
| Benefícios | Inclui {n} análise(s) por mês, empresas sem limite e o Raio X do Negócio. |
| Ciclo (resumo) | Mensal, renova sozinho no cartão / Anual, renova sozinho no cartão |
| Seção cartão | Cartão de crédito |
| Labels | Número do cartão · Nome como está no cartão · Validade · Código (CVV) |
| Placeholders | Ex.: 0000 0000 0000 0000 · Ex.: MARIA S SOUZA · Ex.: 08/29 · Ex.: 123 |
| Segurança | Seus dados vão direto ao processador de pagamento. Nós guardamos só a bandeira e os 4 últimos dígitos. |
| CTA | Assinar e pagar · R$ {valor} |
| CTA loading | Processando pagamento… |
| Apoio | Cancele quando quiser em Meu plano. Reembolso em até 7 dias, se você ainda não tiver feito nenhuma análise. |
| Sucesso | Assinatura ativa · Você tem {n} análise(s) disponível(is) agora. A próxima cobrança é em {data}, de R$ {valor}, no cartão final {4}. |
| Recusa (título) | Cartão recusado |
| Falha do provedor | Não conseguimos falar com o processador de pagamento agora. Não é problema com o seu cartão. |
| Já assinante | Você já tem o plano {nome}, que renova em {data}. |
| Vindo do teste | Ao assinar, seu teste termina. A análise que sobrou do teste não soma à cota do plano. |

## 6. Acessibilidade

- Ordem de tab: troca de ciclo → cupom → número → nome → validade → CVV → aceite → botão.
- `autocomplete` correto nos campos de cartão (reduz digitação e erro). CVV com `[?]` (`help`) que explica onde fica o código.
- Erro de campo por `aria-describedby`; recusa em `aviso-inline` `alerta` com foco movido para ele (`role="alert"`).
- `seletor-modo`: `<fieldset>` com `<legend>` `Ciclo de cobrança`, rádios reais.
- Contraste conforme `tokens.md`; alvos ≥44px; nenhuma informação só por cor (recusa tem título e texto).

## 7. Identificadores E2E

`screen-checkout-ciclo` · `screen-checkout-cartao-numero` · `-nome` · `-validade` · `-cvv` · `screen-checkout-error-cartao` · `screen-checkout-recusa` · `screen-checkout-ja-assinante` · `screen-checkout-confirmacao` (mais os de `checkout-pix-creditos`).

## 8. Exceções ao Design System

| O que diverge | Por quê | Vai virar DDR? |
|---|---|---|
| `seletor-modo` para Mensal/Anual (linha factual é consequência, não custo em campos) | Mesma forma de componente, dois usos | Sim — DDR-010 |

## 9. Dependências e premissas

- **Campos de cartão dependem do ADR-010 (STORY-069, CA-2).** A spec assume campos na nossa página, tokenizados no navegador direto no provedor. Se o ADR escolher campos hospedados ou página do provedor, o Designer ajusta só a seção "Cartão de crédito".
- O provedor pode exigir dados do titular (CPF/CNPJ, CEP, número do endereço). **Pergunta ao PO/ADR-010:** quais; se exigir endereço, a tela ganha um bloco "Dados do titular" pré-preenchido com o cadastro. Não inventei esse bloco.
- O motivo da recusa só aparece se o provedor o fornecer.

## 10. Histórico de mudanças

| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-11-05 | criação | designer (claude-sonnet-5-5) | STORY-070 |
