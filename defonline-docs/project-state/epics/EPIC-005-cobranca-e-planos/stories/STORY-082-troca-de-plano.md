---
story_id: STORY-082
slug: troca-de-plano
title: "Upgrade e downgrade de plano"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: claude-sonnet-5-5
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-082 — Upgrade e downgrade de plano

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

Os Termos definem upgrade imediato e proporcional e downgrade no ciclo seguinte. Como o catálogo é configurável, "superior" é definido pelo preço, não pelo nome do plano.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.1

## O quê

Permitir trocar de plano e de ciclo, com a cobrança proporcional na subida.

## Por quê

Quem cresce sobe sem trocar de conta. É o caminho natural de Roberto para o Pro quando atende mais empresas.

## Critérios de aceite

- [ ] **CA-1:** Antes de confirmar um upgrade, o usuário vê o valor proporcional da diferença no ciclo vigente.
- [ ] **CA-2:** Com o pagamento aprovado, o novo plano e sua cota valem na hora. Upgrade recusado mantém o plano atual.
- [ ] **CA-3:** O cálculo proporcional é coberto por testes, incluindo primeiro e último dia do ciclo, e nunca gera valor negativo.
- [ ] **CA-4:** O downgrade fica agendado para o próximo ciclo, visível em Meu plano, e pode ser desfeito antes disso.
- [ ] **CA-5:** Só é possível escolher planos ativos. Um plano desativado continua valendo para quem já o tem até a troca ou o cancelamento.
- [ ] **CA-6:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Ajustes do ADR-010 (prevalecem sobre os CAs acima onde houver conflito)

O ADR-010 está `proposed`. O orquestrador adotou as recomendações dele em 2026-11-05 para não parar o trabalho, e o PO ainda vai revisá-las.

- Upgrade cobra a diferença por um checkout avulso e depois ajusta o valor da assinatura no provedor (`changeSubscriptionValue`). Diferença abaixo de R$ 5,00 é aplicada sem cobrança.

## Fora de escopo

- Crédito em conta para downgrade.

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-081
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

Ver IDR-026. Em resumo: "superior" = preço por mês (empate só sobe se a cota for maior); a diferença do upgrade é uma Invoice avulsa (`troca_de_plano`), o plano só muda com o pagamento conferido; abaixo de R$ 5,00 aplica sem cobrança; downgrade e anual para mensal ficam agendados em `assinaturas.troca_*` e valem na renovação; o valor no provedor muda por `changeSubscriptionValue` (repetível por rotina horária); Pix anual: upgrade por novo QR Code da diferença, destino só anual; em atraso ou cancelada não troca; rota `plano.trocar` (`RotasCobranca::TROCAR`).

### Descobertas

- A `/planos` (STORY-075) já mostrava "Trocar" quando a rota existia e `MeuPlano` já apontava para ela: a revisão é `/planos/trocar/{plano}/{ciclo}`.
- Teste antigo de `MeuPlanoTest` ("só aparece quando a tela de troca existir") registrava a rota falsa `plano.trocar`; com a rota real a premissa acabou e ele foi trocado por dois testes (leva a `/planos`; não existe com a renovação cancelada).
- A troca de ciclo imediata reancora `data_inicio`, o que reabre a contagem do arrependimento de 7 dias (ver IDR-026, para a 087).

### Bloqueios encontrados

Nenhum. Perguntas ao PO (respostas adotadas, a confirmar): (1) upgrade em Pix anual gera QR Code da diferença; (2) ciclo segue a regra de preço por mês, e anual para mensal só no fim do ciclo.

### IDRs criados

IDR-026.

### Cobertura final

Testes: `tests/Domain/Cobranca/CalculoDeTrocaTest.php` (13), `tests/Feature/Cobranca/TrocaDePlanoTest.php` (28), `tests/Feature/Livewire/TrocarPlanoTest.php` (25), `tests/Browser/TrocaDePlanoBrowserTest.php` (4). CA-1: revisão com valor proporcional (domínio e tela). CA-2: pagamento aprova e vale na hora; recusa, expiração e cancelamento mantêm o plano. CA-3: primeiro e último dia, fora do ciclo, varredura de preços, nunca negativo. CA-4: agendado, visível em Meu plano, "Desfazer troca". CA-5: destino só ativo, plano atual desativado segue valendo. CA-6: 404 com o interruptor desligado.

### Evidências

Suíte, Pint, PHPStan e Dusk inteiro verdes (ver relatório do agente). Pendente de produção: o Asaas aceitar `value`/`cycle` no `PUT /subscriptions/{id}` de cartão (ADR-010 Q5).
