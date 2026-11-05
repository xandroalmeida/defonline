---
idr_id: IDR-020
slug: regua-de-cobranca-e-conciliacao
title: Régua de cobrança sobre a mesma cobrança, atraso em colunas da assinatura e conciliação por evento sintético
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5 (STORY-079)
related_story: STORY-079
related_adrs: ["ADR-010"]
related_idrs: ["IDR-018", "IDR-019"]
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-020 — Régua de cobrança e conciliação

## Contexto

A STORY-079 trata a renovação que falha (CA-3 a CA-7) e implementa a conciliação horária e diária do ADR-010 §3. O ADR-010 §4.2 deixou a retentativa em aberto (o Asaas não a documenta) e recomendou régua própria; o orquestrador adotou a recomendação. Quem faz cancelamento (081), troca de plano (082), estorno e alertas (086/087) herda estas escolhas.

## Decisão

- **A régua retenta a MESMA cobrança, nunca cria outra.** `PaymentProvider::retryCharge(cobrança)` pede ao provedor nova captura da cobrança já existente. Como a cobrança é uma só, "cobrança em dobro" não existe por construção; o `UNIQUE (provedor, id_externo)` de `invoices` (IDR-019) garante uma renovação por cobrança paga. No Asaas, `retryCharge` reagenda a cobrança para hoje (`PUT /payments/{id}` com `dueDate`), porque o Asaas tenta o cartão no vencimento. 4xx do provedor vira `TentativaNaoRealizada` (conta como tentativa, com alerta); rede e 5xx, `ProvedorIndisponivel` (não conta, CA-7).
- **Cronologia** (`ReguaDeCobranca`, domínio puro): recusa avisada pelo provedor = `t0` (assinatura `em_atraso`, e-mail com o link, 1ª retentativa agendada); retentativas em `t0+48h`, `+96h` e `+144h`; em `t0+192h` (48 h depois da última), sem pagamento, o ciclo é encerrado (`expirada`, `encerrada_em`, recorrência cancelada no provedor, e-mail). A decisão de encerrar espera 48 h depois da 3ª tentativa para o webhook dela chegar. Interpretação adotada de "até 3 cobranças a cada 48 h": 3 retentativas depois da recusa original (ver dúvida ao PO nas Notas da STORY-079).
- **Reserva antes da chamada:** a tentativa é gravada (`tentativas_cobranca`, `proxima_tentativa_em`) sob lock da linha ANTES de chamar o provedor; falha de indisponibilidade desfaz a reserva. Duas rodadas simultâneas nunca pedem a mesma tentativa.
- **Estado do atraso na própria `assinaturas`** (`atraso_desde`, `atraso_cobranca_id`, `tentativas_cobranca`, `proxima_tentativa_em`, `atraso_aviso_em`, `encerramento_pendente_no_provedor`, `encerrada_em`, `aviso_renovacao_periodo`), sem tabela de tentativas: a tela "Meu plano" (081, spec §4.3) lê "Tentamos de novo em..." e "Última tentativa em..." direto daí (`ReguaDeCobranca::ultimaTentativaEm`).
- **Quem decide "falhou":** só `cobranca.falhou`/`cobranca.vencida` de uma cobrança de assinatura NOSSA (já existe a linha em `assinaturas`), cuja cobrança é a do ciclo em aberto e cujo estado atual no provedor não é pago nem final. Assinatura que já está em atraso ignora novas recusas da mesma cobrança. O pagamento (`cobranca.paga`) em atraso restabelece (`ConfirmarAssinatura::renovar`) e zera a régua.
- **Conciliação por evento sintético:** `cobranca:conciliar --modo=horaria|diaria` lista no provedor (`listCheckoutCharges`, `listSubscriptionCharges`) o que o webhook pode ter perdido e grava `provider_events` com `evento_id = conciliacao:{cobrança}:{tipo}` (único) que passa pelo MESMO `ProcessarEvento`: o estado atual é conferido de novo e rodar duas vezes não aplica nada duas vezes. Janela diária: 14 dias (ADR-010 §3).
- **Interface:** `PaymentProvider` ganha `retryCharge`, `cancelSubscription`, `listSubscriptionCharges`, `listCheckoutCharges`; `CobrancaNoProvedor` ganha `vencimento` e `semPagamento()`.
- **Alertas:** continuam `Log::error` com `module=cobranca` (chaves `cobranca.provedor_indisponivel`, `cobranca.aviso_nao_enviado`, `cobranca.retentativa_recusada_pelo_provedor`, `cobranca.conciliacao_com_erro`); o roteamento para a equipe é da STORY-086.
- **GET com query no cliente HTTP do Laravel:** `Http::get($url, $query)` SUBSTITUI a query string da URL. Filtros e paginação do Asaas vão no array `$query`, nunca concatenados à URL (um teste cobre).

## Alternativas consideradas

- **Nova cobrança a cada tentativa** (D+2, D+4 do ADR-010): precisaria do token do cartão no nosso servidor (contra o ADR-010 §2) e abre a porta à cobrança duplicada. Descartada.
- **Tabela `tentativas_cobranca`:** mais histórico, mais joins; o histórico fino já vai para `audit_logs`. Fica para a 087 se o suporte pedir.
- **Conciliar aplicando direto, sem evento sintético:** duplicaria a lógica de `ProcessarEvento`/`ConfirmarAssinatura`. Descartada.

## Consequências

- Para a 081: o link "atualizar cartão" dos e-mails usa a rota nomeada `meu-plano` (`RotasCobranca::MEU_PLANO`); até ela existir, cai em `/planos`. Se a 081 escolher outro nome, mude a constante. "Salvar e cobrar agora" deve usar a mesma cobrança (`atraso_cobranca_id`) e `ConfirmarAssinatura` para restabelecer.
- Para a 082/087: `cancelSubscription` existe; assinatura `expirada` com `encerramento_pendente_no_provedor` é refeita pela régua horária.
- Para a 080 (Pix anual): `cobranca.vencida` de assinatura nossa hoje entra na régua de cartão; Pix anual precisa de ramo próprio (aviso e novo Pix) antes de existir.
- Pendente de produção (não observável no sandbox): se o Asaas captura de fato na hora ao reagendar uma cobrança vencida, ou só no lote diário. A régua funciona nos dois casos, só muda a latência do resultado.

## Como verificar

`tests/Feature/Cobranca/RenovacaoEFalhaDeCobrancaTest.php`, `ConciliacaoDeCobrancasTest.php`, `AsaasRetentativaEConciliacaoTest.php`, `tests/Domain/Cobranca/ReguaDeCobrancaTest.php`, `tests/Browser/RenovacaoFalhaCobrancaBrowserTest.php` e `tests/Sandbox/AsaasSandboxTest.php` (grupo `asaas-sandbox`).
