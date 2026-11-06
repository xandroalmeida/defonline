---
story_id: STORY-085
slug: operacao-financeira-no-backoffice
title: "Backoffice — assinaturas, reembolso e nota fiscal"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: done
owner_agent: programador-pista-c
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-085 — Backoffice — assinaturas, reembolso e nota fiscal

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A equipe precisa atender pedidos de arrependimento e emitir nota fiscal, que os Termos prometem. A emissão fica fora do código no MVP (spec §2.4), mas precisa de controle.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.4
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §3.2
  - ADR-010

## O quê

Dar à equipe uma visão de assinaturas e cobranças, com reembolso e controle de nota fiscal.

## Por quê

Permite cumprir o arrependimento e a emissão de nota sem acesso direto ao banco.

## Critérios de aceite

- [ ] **CA-1:** Tela e ações exigem a permissão nova `financeiro.gerenciar`; sem ela, 404 e nenhum item no menu.
- [ ] **CA-2:** A tela lista e filtra assinaturas e cobranças por usuário, status e período.
- [ ] **CA-3:** A tela indica se uma cobrança está dentro dos 7 dias corridos e se houve análise desde a contratação, para orientar o arrependimento.
- [ ] **CA-4:** O admin solicita o reembolso de uma cobrança, com confirmação e motivo. Depois da confirmação do provedor, a cobrança fica "Estornada", e o acesso, a cota ou os créditos dela são retirados.
- [ ] **CA-5:** O mesmo reembolso não é feito duas vezes.
- [ ] **CA-6:** A tela lista cobranças pagas sem nota fiscal, e o admin as marca como emitidas.
- [ ] **CA-7:** Reembolso e marcação de nota vão para `audit_logs`.
- [ ] **CA-8:** Durante o "Logar como", essas ações ficam indisponíveis.

## Ajustes do ADR-010 (prevalecem sobre os CAs acima onde houver conflito)

O ADR-010 está `proposed`. O orquestrador adotou as recomendações dele em 2026-11-05 para não parar o trabalho, e o PO ainda vai revisá-las.

- Estorno de cartão é total pela API (parcial só a partir do dia seguinte). Estorno de Pix fica em `aguardando_autorizacao`: a tela orienta o admin a autorizar no painel do Asaas, e o estado muda com o evento recebido.
- Contestação (chargeback) aparece na lista com destaque.

## Fora de escopo

- Emissão automática de nota fiscal.
- Inadimplência e dashboards do backoffice completo (roadmap §6.1).

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

- Ver IDR-027. Resumo: trava `reembolso_estado` antes da chamada de rede; "Estornada" só com o provedor confirmando (cartão na hora; Pix e em andamento pelo webhook); retira o saldo restante do lote (estado novo `estornado`) ou encerra a assinatura (cancelamento da recorrência pelo job da régua); acesso de cobrança antiga com cobrança paga mais recente é mantido.
- Pagamento capturado depois do cancelamento/encerramento ou na assinatura antiga (casos da 079/081) e assinatura duplicada (077) viram Invoice `paga` com `devolucao_motivo`, destacada na tela.
- Contestação marca `contestada`, alerta e destaca; não retira acesso sozinha.
- Tela `/backoffice/financeiro` no padrão de Planos/Cupons; filtros usuário, status (assinatura e cobrança) e período; seção "Pagas sem nota fiscal" com painel para marcar (número opcional).

### Descobertas

- Sandbox Asaas: cobrança recém-criada recusa o estorno ("tente novamente em alguns instantes") por alguns segundos; cartão estorna `REFUNDED`; Pix fica `AWAITING_CRITICAL_ACTION_AUTHORIZATION`.
- Pagamento "sem destino" não podia ser `renovacao=true`: testes da 079/081 contam renovações por esse campo.
- `x-botao-confirmar` espera expressão de chamada (`acao="metodo()"`), não nome.

### Bloqueios encontrados

Nenhum.

### Integração na junção (2026-11-05)
- **Cupom:** estornar cobrança com cupom não devolve o uso (a vaga do limite continua tomada). A tela mostra preço cheio, desconto, cupom e valor pago, e diz que o uso não volta. Invoice de valor zero (contratação sem cobrança) não oferece reembolso (sem botão, painel nem serviço) e sai da lista "pagas sem nota fiscal".
- **Troca de plano (STORY-082):** a Invoice `troca_de_plano` guarda `troca_anterior` (a assinatura antes e os marcadores de depois; migration 400087). Estornar a diferença volta ao plano anterior, sem encerrar a assinatura, quando o ciclo ainda é o da troca (uso desde a troca mantido; o valor no provedor volta pela rotina `ajuste_provedor_pendente`). Se o ciclo já seguiu (renovou, outra troca, assinatura inativa) ou a troca é anterior ao registro, o plano atual fica, só o dinheiro é devolvido, e a tela avisa em destaque ANTES de confirmar.

### IDRs criados

IDR-027.

### Cobertura final

Ver relatório final do agente (suíte, Pint, PHPStan e Dusk rodados ao fim).

### Evidências

Testes: `tests/Feature/Cobranca/ReembolsoTest.php`, `tests/Feature/Livewire/Backoffice/FinanceiroTest.php`, `tests/Feature/Cobranca/AsaasGestaoDeAssinaturaTest.php` (refund), `tests/Domain/Cobranca/MeuPlanoDominioTest.php`, `tests/Sandbox/AsaasSandboxTest.php` (estorno real no sandbox), Dusk `tests/Browser/FinanceiroBackofficeBrowserTest.php`.
