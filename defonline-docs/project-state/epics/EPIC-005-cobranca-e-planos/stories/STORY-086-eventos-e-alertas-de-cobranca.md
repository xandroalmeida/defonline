---
story_id: STORY-086
slug: eventos-e-alertas-de-cobranca
title: "Eventos e alertas de cobrança"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: in_review
owner_agent: programador-pista-c
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: S
---

# STORY-086 — Eventos e alertas de cobrança

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

O épico não fecha sem medir conversão e detectar falhas de pagamento. O NRF §6 pede esses indicadores.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §6
  - `decisions/adr/ADR-004-observabilidade.md`
  - `product/north-star.md`

## O quê

Instrumentar o funil de contratação e alertar a equipe sobre falhas.

## Por quê

Sem isso, não saberemos se o preço funciona nem se a cobrança parou.

## Critérios de aceite

- [x] **CA-1:** Há eventos de produto para: trial ativado e encerrado, checkout iniciado, contratação concluída ou abandonada, cupom aplicado, cancelamento e bloqueio por falta de saldo.
- [x] **CA-2:** Os eventos passam pelo canal oficial e não trazem dado pessoal nem de cartão, conforme o teste arquitetural existente.
- [x] **CA-3:** É possível calcular a conversão trial → pago em 30 dias, a conversão do checkout e o churn mensal.
- [x] **CA-4:** A taxa de falha de pagamento acima de 10% em 1 hora gera alerta P2.
- [x] **CA-5:** Falhas de autenticação do webhook e erros de processamento geram alerta.

## Fora de escopo

- Painel de métricas no backoffice.

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-074, STORY-076, STORY-077, STORY-079
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

### Plano (antes de codar)

Lidos: a estória, ADR-004, NRF §6, `EventosDrm`/`EventLogger::emitOnce` (STORY-056), `RfbAlerter`, e o código de 076 a 085 que o alerta e os eventos tocam. Entendimento: instrumentar o funil (trial, checkout, contratação, cupom, cancelamento, bloqueio) pelo canal oficial, sem dado pessoal; permitir calcular as três métricas; alertar P2 por falha de pagamento e alertar falha de autenticação do webhook e erro de processamento. Testes: um por evento (esquema exato), idempotência, falha do provedor sem evento, Pix anual, limites do alerta (10%, mínimo, janela, silêncio), mascaramento de dado pessoal, métricas com dados de borda (29d23h, 35d, fuso de Brasília), integração do fluxo real com as métricas.

### Decisões tomadas

- Ver IDR-028 (catálogo `EventosCobranca`, canal único `AlertaDaEquipe`, ouvinte `AlertaDeCobranca`, métricas e alerta de falha).
- Eventos além dos do CA-1: `cancelamento_revertido` (sem ele o churn conta quem reativou) e os fatos `pagamento_confirmado`/`pagamento_recusado` (sem eles não há taxa de falha). Não alteram CA.
- Mínimo de 5 tentativas na janela de 1 h para alertar a taxa de falha (1 recusa em 1 tentativa seria 100%). É parâmetro em `config/cobranca/observabilidade.php`. **Decisão de produto a confirmar com o PO.**
- Conversão trial → pago conta só **assinatura** (como diz o NRF §6.3), não compra de créditos.
- O alerta de IP em excesso do trial deixou de levar o IP inteiro: LGPD e a regra desta estória (canal sem dado pessoal). Testes da 074 ajustados de acordo (`TrialTest`, `TrialNaHomeTest`, `AtivarTest` passaram a mockar `AlertaDaEquipe`, e o teste do alerta exige o IP mascarado).

### Descobertas

- O middleware de log acrescenta `request_id` e `user_id` a toda linha: se entrassem na chave do silêncio, cada requisição alertaria de novo (o teste de rajada de webhook pegou isto).
- A mensagem de exceção do cliente HTTP, quando o Telegram cai, traz a URL com o token do bot: o canal agora registra só a classe da exceção.
- Hooks `EventoProduto::creating` em teste só rodam em cadeia se retornarem nulo (o Laravel para no primeiro retorno não nulo).

### Bloqueios encontrados

Nenhum.

### Pendências para quem juntar as pistas (orquestrador / STORY-083)

- **`cupom_aplicado`**: chamar `EventosCobranca::cupomAplicado($usuarioId, $cupomId, 'assinatura'|'credito', $invoiceId, $descontoCentavos)` onde o cupom reduz o pedido, depois de criar a Invoice (checkout de assinatura e de créditos). Sem ponto de emissão ligado, a 083 ou a junção o fazem.
- **STORY-082 (troca de plano, na main, ainda sem commit)**: quando uma troca de plano encerrar ou criar assinatura, avaliar se vale `contratacao_concluida`/`cancelamento`; não foi tocado.
- Configurar `TELEGRAM_BOT_TOKEN` e `TELEGRAM_CHAT_ID` antes de ligar a cobrança em produção; sem eles os alertas só aparecem no log (`alerta.alert`).

### IDRs criados

IDR-028.

### Cobertura final

Suíte completa com cobertura: 3081 testes passando, cobertura total 98,7%. Código novo: `AlertaDaEquipe`, `AlertaDeCobranca`, `EventosCobranca`, `MetricasDeCobranca` e os três comandos novos, todos em 100%; `Domain/Cobranca/Trial` 100% e `Carteira` 99,5% (a linha descoberta é anterior a esta estória).

### Evidências

Suíte Pest 3081 verdes; Pint e PHPStan limpos; Dusk inteiro (104 testes) com 103 verdes e 1 falha fora desta estória: `DrmRetificacaoBrowserTest` (data do rodapé em UTC contra Brasília, corrigida em outra pista).

Testes: `tests/Feature/Observabilidade/EventosCobrancaTest.php` (CA-1, CA-2, CA-3 com fluxo real), `AlertaDeCobrancaTest.php` e `AlertaDaEquipeTest.php` (CA-2, CA-5), `FalhaDePagamentoTest.php` (CA-4), `MetricasDeCobrancaTest.php` (CA-3). Consulta de exemplo: `php artisan cobranca:metricas --dias=90 --mes=2026-10`.
