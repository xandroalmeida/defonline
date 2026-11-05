---
story_id: STORY-077
slug: assinatura-com-cartao
title: "Assinatura de plano com cartão recorrente"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: claude-sonnet-5-5 (STORY-077)
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: L
---

# STORY-077 — Assinatura de plano com cartão recorrente

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A assinatura é o coração do modelo. O DEFOnline não pode armazenar dados de cartão (NRF §3.2).

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.1, §3.4 e §4.3
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §3.2 e §4
  - `defonline-docs/especificacao/V2/arquitetura-tecnica.md` §4.1
  - ADR-010
  - Spec de tela da STORY-070

## O quê

Permitir que o usuário assine um plano mensal ou anual com cartão e passe a ter cota e benefícios imediatamente.

## Por quê

Cria a receita recorrente que sustenta o produto.

## Critérios de aceite

- [ ] **CA-1:** O checkout mostra plano, ciclo, valor, próxima cobrança e renovação automática, e registra o aceite do Termo vigente.
- [ ] **CA-2:** Com o pagamento aprovado, a assinatura fica ativa e a cota está disponível de imediato.
- [ ] **CA-3:** Cartão recusado mantém o usuário sem assinatura e exibe o motivo em linguagem simples.
- [ ] **CA-4:** Dados de cartão não são gravados no banco nem aparecem em log. Só ficam token, bandeira e últimos 4 dígitos, conforme o ADR-010.
- [ ] **CA-5:** A assinatura guarda snapshot de preço e cota, e mudanças no catálogo não a afetam até a renovação.
- [ ] **CA-6:** A cota é renovada a cada mês no dia de aniversário, inclusive no ciclo anual, sem acumular, salvo configuração. Em um mês sem o dia de aniversário, vale o último dia do mês.
- [ ] **CA-7:** Um usuário com assinatura ativa não consegue contratar uma segunda.
- [ ] **CA-8:** O mesmo evento do provedor recebido duas vezes não ativa nem renova duas vezes.
- [ ] **CA-9:** Durante o "Logar como", o admin não consegue contratar.
- [ ] **CA-10:** Teste E2E percorre planos, checkout e assinatura ativa com o adaptador de teste.
- [ ] **CA-11:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Ajustes do ADR-010 (prevalecem sobre os CAs acima onde houver conflito)

O ADR-010 está `proposed`. O orquestrador adotou as recomendações dele em 2026-11-05 para não parar o trabalho, e o PO ainda vai revisá-las.

- O cartão é digitado no Checkout hospedado (`RECURRENT`). Nosso sistema não tem formulário de cartão: a seção de campos de `STORY-070-checkout-assinatura.md` não se aplica, e o botão leva ao Asaas após o aceite do Termo.
- O CA-4 passa a valer assim: não há número, validade ou CVV em nenhum DTO, coluna ou log. Bandeira e 4 últimos dígitos só se o provedor informar. Inclua o teste arquitetural do ADR-010 §2.
- A assinatura só fica ativa com `cobranca.paga`.
- Recusa de cartão no checkout é tratada no Asaas: a volta sem pagamento mantém o usuário sem assinatura e oferece nova tentativa.

## Fora de escopo

- Falha de renovação (STORY-079).
- Pix no anual (STORY-080).
- Cupom (STORY-083).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-069, STORY-070, STORY-073, STORY-075
- **Bloqueia:** STORY-078, STORY-079, STORY-080, STORY-081, STORY-083, STORY-085, STORY-086, STORY-087

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
- Leitura: estória (com "Ajustes do ADR-010", que prevalecem), ADR-010, IDR-018, notas da STORY-076, spec `STORY-070-checkout-assinatura.md` e skill do programador. Pelo ADR-010 a seção de campos de cartão da spec não se aplica: o cartão é digitado no Checkout hospedado `RECURRENT`; o botão leva ao provedor depois do aceite do Termo.
- Entrada `GET /assinar/{plano}/{ciclo}` (`checkout.assinatura`, `RotasCobranca::ASSINAR`) e retorno `GET /assinar/retorno/{invoice}` (`assinatura.retorno`, polling de 3 s). Cobrança desligada = 404 nas duas.
- Fluxo: `IniciarAssinatura` (aceite -> Invoice `tipo=assinatura` com retrato de preço e cota -> `startCheckout` recorrente). A assinatura só nasce em `ConfirmarAssinatura`, chamado por `ProcessarEvento` em `cobranca.paga` depois de `getCharge`. Nada de ativar pelo retorno do navegador.
- Renovação (CA-6/CA-8): uma cobrança paga que não é a da Invoice do checkout mas pertence a uma assinatura nossa (`provedor` + id da assinatura) vira uma Invoice `renovacao` (`UNIQUE (provedor, id_externo)`), estende o período e devolve a cota cheia. Mensal: renova com o pagamento. Anual: ano inteiro de acesso pago, e `assinaturas:renovar-cota` (horário, idempotente) devolve a cota todo mês no aniversário; num mês sem o dia vale o último dia, e o dia 31 volta nos meses que o têm (`CotaMensal`, aniversário = `data_inicio + n meses`, sem deriva). `periodos_pagos` ancora o vencimento em `data_inicio`.
- Preço no CA-5: a assinatura guarda `preco_snapshot_centavos` e `cota_snapshot`. Na renovação o preço é o que o provedor cobrou (ele manda; o valor da assinatura no provedor é fixo), com `warning` se mudou; a cota passa a ser a do catálogo de hoje (é o "até a renovação" do CA-5). Se o PO preferir manter a cota antiga até a troca de plano, é mudar uma linha em `ConfirmarAssinatura::renovar`.
- Recusa (CA-3): o Asaas trata no checkout hospedado; a volta sem pagamento (`cobranca.falhou`, prazo vencido ou cancelamento) mantém o usuário sem assinatura e oferece "Tentar de novo". Motivo específico (limite, CVV) não é mostrado porque o evento canônico não o carrega; texto genérico "O banco não autorizou o pagamento. Tente outro cartão." Se o usuário acertar o cartão na própria página do provedor, o pagamento chega e ativa (a Invoice `falhou` ainda aceita `paga`).
- Segurança (CA-4): `CobrancaNoProvedor` só tem `cartaoBandeira` e `cartaoUltimos4` (lidos de `creditCardBrand`/`creditCardNumber`, normalizados para os 4 últimos dígitos; o token é ignorado e removido do payload). Colunas `cartao_bandeira(20)`/`cartao_ultimos4(4)` em `invoices` e `assinaturas`. Testes arquiteturais novos em `PagamentoArchTest` e `SchemaSemDadosDeCartaoTest` (ADR-010 §2).
- Pagamento duplicado (duas abas, dois checkouts pagos): a Invoice fica `paga`, a segunda assinatura não nasce, `Log::error cobranca.assinatura_duplicada` e a tela explica; devolução é manual (fora de escopo, estorno é 087).
- Plano anual: só cartão (PedidoCheckout recusa Pix recorrente); o ramo Pix é da STORY-080.
- `Assinatura` passou a usar `$dateFormat` com offset (IDR-018 avisava), senão `proxima_cota_em` deslocava 3 h no banco local.
- Refatoração pequena: o registro do aceite saiu de `IniciarCompraDeCreditos` para `RegistrarAceiteDoCheckout` (segundo uso).
- Teste alterado: em `PaginaTest`, "esconde Assinar enquanto o checkout de assinatura não existe" virou "mostra Assinar em cada plano ativo, apontando ao checkout existente com plano e ciclo" (mais um teste de plano inativo sem botão); removi `rotaFalsa(ASSINAR)` dos dois testes que a usavam, porque a rota é real agora.

### Descobertas
- Sandbox real: `POST /checkouts` com `RECURRENT`, `CREDIT_CARD` e `subscription{cycle MONTHLY|YEARLY, nextDueDate hoje 00:00:00}` é aceito (mensal e anual), e o cancelamento funciona (`tests/Sandbox`, rodado com a chave do `app/.env` sem imprimi-la).
- Doc do Asaas: `nextDueDate` é a data da primeira cobrança, criada só quando o pagador conclui o checkout; a cobrança tem o campo `subscription`. A doc NÃO diz se a cobrança herda `externalReference`/`checkoutSession`; o código tolera os dois casos (acha a Invoice por referência, checkout ou id da cobrança; renovação por id da assinatura).
- `ReceberWebhook` engole exceção do processamento síncrono (grava `erro`, o reprocessador retoma): testes de falha conferem o estado, não a exceção.

### Bloqueios encontrados
- Nenhum. Não verifiquei com pagamento real na página hospedada do Asaas (ADR-010 pendências 1, 5 e 6): sem isso não sei (a) se `creditCard.creditCardNumber/Brand` aparece na cobrança de um checkout, então "cartão final 4242" pode não aparecer em produção (a tela omite quando não há), (b) se a 1ª cobrança nasce `CONFIRMED` na hora com `nextDueDate` = hoje e (c) como a 2ª cobrança herda campos. Dúvidas ao PO/Arquiteto: ver relatório (retentativa e atraso são da 079).

### IDRs criados
- IDR-019 (assinatura: Invoice por cobrança, `periodos_pagos` e cota mensal do anual).

### Cobertura final
- Suíte All: 2349 testes verdes; cobertura total 98,6%; `app/Domain` 99,7% (gate 98). Dusk inteiro: 73 verdes. Pint e PHPStan limpos.

### Evidências
- `tests/Feature/Cobranca/{AssinaturaComCartaoTest,AsaasAssinaturaTest,SchemaSemDadosDeCartaoTest}.php`, `tests/Feature/Livewire/AssinarPlanoTest.php`, `tests/Domain/Cobranca/CicloECotaMensalTest.php`, `tests/Unit/Arch/PagamentoArchTest.php`, `tests/Browser/AssinaturaCartaoBrowserTest.php`, `tests/Sandbox/AsaasSandboxTest.php`.
