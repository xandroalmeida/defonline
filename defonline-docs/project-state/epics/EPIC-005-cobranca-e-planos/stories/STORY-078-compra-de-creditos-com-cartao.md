---
story_id: STORY-078
slug: compra-de-creditos-com-cartao
title: "Compra de créditos com cartão"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: done
owner_agent: claude-sonnet-5-5 (STORY-078)
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: S
---

# STORY-078 — Compra de créditos com cartão

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A spec prevê créditos à vista com Pix ou cartão. As duas partes já existem nas STORY-076 e STORY-077.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.2 e §4.3
  - ADR-010

## O quê

Permitir pagar um pacote com cartão, sem criar recorrência.

## Por quê

Dá uma alternativa a quem prefere cartão.

## Critérios de aceite

- [ ] **CA-1:** No checkout de pacote, o usuário escolhe Pix ou cartão.
- [ ] **CA-2:** Com o pagamento aprovado, os créditos entram na carteira e nenhuma recorrência é criada.
- [ ] **CA-3:** Cartão recusado não gera crédito e explica o motivo.
- [ ] **CA-4:** O mesmo evento recebido duas vezes credita uma única vez.
- [ ] **CA-5:** Dados de cartão seguem a regra da STORY-077.
- [ ] **CA-6:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Ajustes do ADR-010 (prevalecem sobre os CAs acima onde houver conflito)

O ADR-010 está `proposed`. O orquestrador adotou as recomendações dele em 2026-11-05 para não parar o trabalho, e o PO ainda vai revisá-las.

- O cartão é digitado no Checkout hospedado (`DETACHED`, `CREDIT_CARD`).

## Fora de escopo

- Guardar cartão para compras futuras.

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-076, STORY-077
- **Bloqueia:** STORY-087

## Decisões já tomadas (não as reabra)

- PDR-020: catálogo em tela, trial, Asaas substituível, Termos `v2`, DRM em planos pagos e no trial, e cupons.

## Liberdade técnica

Você decide estrutura de código, tabelas, bibliotecas dentro dos ADRs e desenho dos testes. Não decide critérios de aceite nem padrões de qualidade. Se faltar uma decisão arquitetural, pare e registre em "Notas do agente".

## Definição de Pronto

- [ ] Todos os critérios de aceite têm teste e passam.
- [ ] `scripts/verificar.sh` verde.
- [ ] Commits em `main` local; push, PR e tag só com pedido do PO.
- [ ] IDR registrado se houve decisão técnica com impacto futuro.
- [ ] `index.json` e "Notas do agente" atualizados; status `in_review`.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Ao iniciar, atualize status e `owner_agent` aqui e no `index.json`. Se travar, use `status: blocked` e descreva o bloqueio.

## Notas do agente

### Decisões tomadas
- Leitura: estória (com "Ajustes do ADR-010", que prevalecem), ADR-010, IDR-018, IDR-019, notas das STORY-076 e 077, spec `STORY-070-checkout-creditos-cartao.md` e a skill do programador. Pelo ADR-010 a seção de campos de cartão da spec não se aplica: o cartão é digitado no Checkout hospedado `DETACHED`/`CREDIT_CARD`; o botão leva ao provedor depois do aceite do Termo.
- Mesma rota e mesmo componente da 076 (`creditos.comprar`, `ComprarCreditos`), com `meio` (`pix` padrão, `?meio=cartao`) e o seletor `x-cobranca.seletor-meio` (radiogroup nativo, DDR-010; será reutilizado pela 080). `IniciarCompraDeCreditos::executar` ganhou o parâmetro `MeioPagamento $meio = Pix`; o meio vai para `invoices.meio`, para o `PedidoCheckout` e para o prazo (`cartao_expiracao_minutos` no cartão, `pix_expiracao_minutos` no Pix). A reutilização de checkout pendente agora filtra por meio.
- Crédito: nenhum código novo. O cartão passa pelo mesmo `ProcessarEvento` da 076 (crédito só em `cobranca.paga` depois de `getCharge`, lock da linha, índice único em `lotes_credito.invoice_id`). Passei a gravar `cartao_bandeira` e `cartao_ultimos4` na Invoice paga (só o que o provedor informa). Nada cria `Assinatura` (CA-2).
- Recusa (CA-3): `cobranca.falhou` já virava `Falhou`/`recusado`. A tela de retorno agora distingue recusa de cartão (`Cartão recusado`, "O banco não autorizou o pagamento", "Você pode tentar outro cartão ou pagar com Pix", ações `Tentar de novo` e `Pagar com Pix`) de falha do provedor. Para isso a falha ao abrir o checkout passou a gravar `motivo_encerramento = provedor` também nos créditos (como já fazia a assinatura). Motivo específico do banco (limite, CVV) não é mostrado porque o evento canônico não o carrega (mesma limitação da 077).
- Aviso de "QR Code Pix em aberto" (spec §4): aparece ao escolher cartão se há Invoice Pix pendente e não expirada do mesmo pacote.
- Fake: `getCharge` devolve VISA/4242 quando o checkout foi pedido com cartão (antes só na assinatura); a página fake mostra aprovar/recusar/desistir para qualquer Invoice de cartão. E-mail do link de pagamento fala em cartão quando é o caso.
- CA-6: a rota continua 404 com a cobrança desligada; nada novo aparece para o usuário comum.

### Descobertas
- A spec diz que "o back-end rejeita o segundo" pagamento (Pix em aberto e cartão pagos). Isso NÃO existe e não foi pedido nos CAs: dois checkouts pagos do mesmo pacote viram dois lotes (dois pagamentos reais, duas Invoices). A tela só previne. Se o PO quiser rejeitar/estornar o segundo, é decisão nova (sugestão: tratar na 087, junto ao estorno).
- Sandbox/produção do Asaas: ainda não confirmei com pagamento real se `creditCard.creditCardNumber/Brand` aparece na cobrança de um checkout avulso (ADR-010 pendências 1 e 5); se não aparecer, "Pago com cartão final 4242" some da confirmação (a tela omite o final quando não há).

### Bloqueios encontrados
- Nenhum.

### IDRs criados
- Nenhum (decisões locais, sem impacto novo além do IDR-018/019).

### Cobertura final
- Ver relatório da execução: suíte All verde, Pint e PHPStan limpos, Dusk inteiro verde.

### Evidências
- `tests/Feature/Cobranca/CreditosComCartaoTest.php`, `tests/Feature/Livewire/{ComprarCreditos,AcompanharPagamento}Test.php`, `tests/Browser/CompraCreditosCartaoBrowserTest.php`.
