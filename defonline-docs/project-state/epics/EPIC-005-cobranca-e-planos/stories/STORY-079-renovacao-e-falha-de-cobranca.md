---
story_id: STORY-079
slug: renovacao-e-falha-de-cobranca
title: "Renovação e falha de cobrança"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: done
owner_agent: claude-sonnet-5-5 (STORY-079)
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-079 — Renovação e falha de cobrança

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A renovação não pode surpreender o usuário nem manter acesso sem pagamento. As regras já estão na spec e nos Termos.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.4 e §3.7 (renovação)
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §3.2 e tabela de contingência
  - ADR-010

## O quê

Renovar assinaturas, avisar antes do fim do ciclo e tratar falhas com retentativas.

## Por quê

Preserva receita e evita cancelamento involuntário.

## Critérios de aceite

- [ ] **CA-1:** O usuário recebe e-mail 7 dias antes do fim do ciclo, sem duplicar mesmo que a rotina rode de novo.
- [ ] **CA-2:** Na renovação paga, o ciclo avança com preço e cota vigentes naquele momento, e a mudança de preço só vale após o aviso de 30 dias.
- [ ] **CA-3:** Dada uma cobrança recusada, o usuário é avisado com link para atualizar o cartão, a assinatura fica "Em atraso" e o sistema tenta até 3 vezes, a cada 48 horas.
- [ ] **CA-4:** Pagamento durante as retentativas restabelece a assinatura e interrompe as tentativas.
- [ ] **CA-5:** Após a terceira falha, o ciclo é encerrado e as funções pagas são bloqueadas, com o histórico preservado.
- [ ] **CA-6:** Eventos fora de ordem ou perdidos não deixam a assinatura em estado errado, conforme a conciliação do ADR-010.
- [ ] **CA-7:** A indisponibilidade do provedor não é contada como recusa do cliente e gera alerta à equipe.
- [ ] **CA-8:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Ajustes do ADR-010 (prevalecem sobre os CAs acima onde houver conflito)

O ADR-010 está `proposed`. O orquestrador adotou as recomendações dele em 2026-11-05 para não parar o trabalho, e o PO ainda vai revisá-las.

- A retentativa de 3 vezes a cada 48 horas é uma régua própria nossa, a menos que o sandbox mostre retentativa do Asaas (ADR-010 §4.2). Registre o que foi observado. O mecanismo não pode gerar cobrança duplicada.
- A conciliação horária e diária do ADR-010 §3 entra aqui.

## Fora de escopo

- Tela de atualização do cartão (STORY-081).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-077
- **Bloqueia:** STORY-083, STORY-086, STORY-087

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
- Leitura: estória (com "Ajustes do ADR-010"), ADR-010 §3 e §4.2, IDR-018, IDR-019, notas das STORY-076 e 077, spec funcional §2.4, §3.7, §4.12 e a tela `meu-plano` §4.3.
- Plano: (1) migration com colunas de atraso/aviso em `assinaturas`; (2) regra pura `ReguaDeCobranca` no domínio; (3) falha de renovação em `ProcessarEvento` -> atraso + e-mail; (4) pagamento em atraso restabelece (`ConfirmarAssinatura`); (5) comando horário da régua (retentar, encerrar, cancelar no provedor); (6) aviso D-7; (7) conciliação horária e diária; (8) Fake e Asaas com `retryCharge`, `cancelSubscription`, `listSubscriptionCharges`, `listCheckoutCharges`.

### Descobertas
- **Sandbox, retentativa do Asaas (ADR-010 §4.2):** (1) cartão recusado é rejeitado SÍNCRONO ao criar a assinatura ou trocar o cartão (`invalid_action`, "Transação não autorizada"), então não há como produzir, no sandbox, uma renovação recusada nem observar retentativa automática; (2) a doc do Asaas só diz que o cartão é tentado no vencimento, sem retentativa; (3) reagendar a cobrança PENDENTE de uma assinatura para hoje (`PUT /payments/{id}` com `dueDate`) é aceito, a cobrança continua uma só, e em ~3 min de observação continuou `PENDING` (captura fica para o lote do Asaas); (4) `GET /checkouts/{id}` não existe (resposta vazia); `GET /payments?checkoutSession=` é aceito (o filtro local do adaptador protege se o Asaas o ignorar); `GET /subscriptions/{id}/payments` com `limit/offset/dateCreated[ge]` funciona; `DELETE /subscriptions/{id}` cancela e é idempotente. Conclusão: régua própria, sobre a mesma cobrança. Verificado em `tests/Sandbox/AsaasSandboxTest.php`. NÃO verificado: reagendar uma cobrança já `OVERDUE`; captura imediata em produção.
- Bug achado pelos testes: `Http::get($url, $query)` do Laravel substitui a query da URL; filtros e paginação vão no array.
- `Model::query()->update([...])` em massa não usa o `$dateFormat` com offset (IDR-018): datas em teste Dusk/Pest de update em massa precisam de `format('Y-m-d H:i:sP')`. O código de produção grava por `save()`.
- CA-2: não há mecanismo de reajuste com aviso de 30 dias; o preço da renovação é o contratado (o que o provedor cobra) e o catálogo só afeta novas assinaturas. A cota é a do catálogo no momento da renovação.

### Junção com a STORY-081 e cancelar em atraso (2026-11-05, após o merge da pista B)
- **Dois caminhos para sair do atraso, um só efeito.** O pagamento pela régua (retentativa da cobrança atrasada) e o pagamento da 1ª cobrança do cartão novo (081) passam por `ConfirmarAssinatura::renovar`, que restabelece (`Ativa`, cota cheia, régua zerada) e grava UMA Invoice por cobrança (`UNIQUE (provedor, id_externo)`). O parâmetro `recuperaAtraso` da 081 foi removido: qualquer cobrança paga em atraso restabelece (CA-4).
- **Troca de cartão concluída em atraso tira a cobrança antiga da régua** (`TrocarCartao::confirmar`): `atraso_cobranca_id`, `proxima_tentativa_em` e `tentativas_cobranca` são zerados, então a cobrança velha não é mais retentada nem encerra o ciclo; a assinatura segue `em_atraso` (sem acesso) até o cartão novo pagar. Se a 1ª cobrança do cartão novo for recusada, `FalhaDeRenovacao` recomeça a régua sobre ela (auditoria `cobranca.regua_retomada_apos_troca_de_cartao`). Pagamento tardio da cobrança da assinatura antiga (retentativa em voo) não renova e dispara `cobranca.pagamento_da_assinatura_antiga` (devolução manual, STORY-087).
- **Cancelar em atraso (decisão do PO, Termo v2 "a qualquer momento")**: `GerirRenovacao::cancelar` aceita `EmAtraso`. Efeito: `cancelSubscription` no provedor (a recorrência acaba de vez), status `Expirada` com `encerrada_em` e `data_cancelamento` agora, `proxima_tentativa_em` nulo; acesso bloqueado na hora; faturas, `atraso_desde` e `atraso_cobranca_id` preservados; troca de cartão pendente vira `cancelada`. Provedor fora do ar: nada muda, a régua segue. Auditoria `cobranca.renovacao_cancelada_em_atraso`.
- **Corrida cancelar × retentativa em voo**: a régua só reserva a tentativa sob lock e o cancelamento trava a mesma linha, então ou o cancelamento vem antes (a rodada vira `Aguardar`, zero tentativas) ou depois da reserva. Neste caso o pedido de retentativa já saiu e o banco pode aprovar: o pagamento NÃO restabelece (assinatura `Expirada`), cai em `cobranca.renovacao_de_assinatura_inativa` (alerta, devolução manual na 087) e a régua não volta a agir. Não há como desfazer uma captura no provedor com a porta atual (sem estorno até a 087). Testes: `CancelarRenovacaoEmAtrasoTest`, `AtrasoComTrocaDeCartaoTest`.

### Pix anual fora da régua (2026-11-05, pós-junção com a 080)
- A pendência "Pix anual precisa de ramo próprio" está resolvida: assinatura com `meio_pagamento = pix` é excluída da régua, do aviso D-7 de cartão e da conciliação por assinatura; as cobranças dela entram na conciliação pelas Invoices. Ver notas da STORY-080.

### Dúvidas ao PO
- "Até 3 cobranças a cada 48 h": adotei recusa original + 3 retentativas (+48h, +96h, +144h), encerrando em +192h. A alternativa (3 tentativas no total) é mudar `ReguaDeCobranca::MAX_TENTATIVAS` para 2.
- Acesso durante o atraso: segue a regra já existente (STORY-073): em atraso não há acesso às funções pagas.
- Link "atualizar cartão" dos e-mails aponta a `meu-plano` (STORY-081), com queda para `/planos`.
- Pix anual (STORY-080) precisa de ramo próprio: hoje `cobranca.vencida` de assinatura nossa entra na régua de cartão.

### Bloqueios encontrados
- Nenhum.

### IDRs criados
- IDR-020 (régua de cobrança, estado do atraso em `assinaturas`, conciliação por evento sintético).

### Cobertura final
- Suíte All: 2470 testes verdes, 98,5% total; `app/Domain/Cobranca` 100% nos arquivos novos (`ReguaDeCobranca`, `AcaoDaRegua`, `CobrancaNoProvedor`) e 99,4% em `Carteira`. Serviços novos entre 95% e 100%. Pint e PHPStan limpos. Dusk inteiro: 77 verdes (625 asserções).
- Teste antigo alterado: `AssinaturaComCartaoTest` ("renovação em atraso fica para a 079") virou "cancelada ou encerrada não renova e alerta", porque agora o atraso restabelece (CA-4).

### Evidências
- CA-1 `RenovacaoEFalhaDeCobrancaTest` (CA-1); CA-2 idem (CA-2) + `AssinaturaComCartaoTest`; CA-3/CA-4/CA-5/CA-7/CA-8 `RenovacaoEFalhaDeCobrancaTest`, `tests/Browser/RenovacaoFalhaCobrancaBrowserTest.php`; CA-6 `ConciliacaoDeCobrancasTest`; régua sem cobrança duplicada: "retenta em +48 h, +96 h e +144 h... nunca cria cobrança nova" e "duas rodadas ao mesmo tempo"; domínio `tests/Domain/Cobranca/ReguaDeCobrancaTest.php`; adaptador `AsaasRetentativaEConciliacaoTest`; sandbox `tests/Sandbox/AsaasSandboxTest.php`.
