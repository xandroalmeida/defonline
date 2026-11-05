---
story_id: STORY-073
slug: carteira-consumo-e-bloqueio-de-analise
title: "Carteira, interruptor de cobrança e bloqueio de análise"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: programador (claude-sonnet-5-5)
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: L
---

# STORY-073 — Carteira, interruptor de cobrança e bloqueio de análise

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A cobrança será construída aos poucos e publicada na `main`, enquanto os usuários do beta seguem usando o produto. O interruptor protege esses usuários até o lançamento. A carteira concentra a regra de consumo.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.1, §2.2 e §4.3
  - `defonline-docs/especificacao/V2/arquitetura-tecnica.md` §4.1 (Subscription, CreditLot)
  - `app/app/Actions/CalcularDiagnostico.php`

## O quê

Criar a carteira do usuário e fazer cada análise consumir cota ou crédito, com bloqueio quando não há saldo, tudo atrás de um interruptor.

## Por quê

É o que transforma plano e crédito em direito de uso, sem cobrar ou bloquear ninguém antes da hora.

## Critérios de aceite

- [ ] **CA-1:** Existe um interruptor de cobrança, desligado por padrão, alterável na tela da STORY-071 e auditado. Desligado, o fluxo de diagnóstico funciona como hoje e nada é consumido.
- [ ] **CA-2:** Ligado, ao concluir uma análise o sistema debita 1 da cota; sem cota, 1 crédito do lote com vencimento mais próximo.
- [ ] **CA-3:** Dado que não há cota nem créditos válidos, quando o usuário tenta iniciar ou concluir a análise, ela é bloqueada com orientação para assinar ou comprar, sem perder o rascunho do quiz.
- [ ] **CA-4:** Créditos vencidos não são consumidos, e lotes esgotados ficam marcados.
- [ ] **CA-5:** Falha no cálculo do diagnóstico não consome saldo.
- [ ] **CA-6:** Duas conclusões simultâneas com saldo 1 geram uma única análise concluída, e o saldo nunca fica negativo.
- [ ] **CA-7:** A carteira é do Usuário: análises em empresas diferentes consomem o mesmo saldo.
- [ ] **CA-8:** Cada consumo fica registrado com análise, Empresa Analisada e origem (cota ou crédito).
- [ ] **CA-9:** Diagnósticos já emitidos continuam acessíveis com ou sem saldo, dentro da janela de retenção.
- [ ] **CA-11:** A verificação de saldo devolve o motivo do bloqueio, e a tela segue `design/screens/STORY-070-bloqueio-sem-saldo.md`, com o rascunho preservado e o texto de cada causa. Ações que levam a telas ainda inexistentes ficam ocultas até elas existirem.
- [ ] **CA-10:** Cobertura de 98% nas regras de consumo, com testes de concorrência.

## Fora de escopo

- Pagamento e renovação da cota (STORY-076 e STORY-077).
- Bloqueio do DRM (STORY-084).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-070, STORY-071
- **Bloqueia:** STORY-074, STORY-076, STORY-077, STORY-084, STORY-087

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
- Lido: estória, spec STORY-070, PDR-020, espec §2.1/§2.2/§4.3, arquitetura §4.1, `CalcularDiagnostico`, `Quiz`, `Planos`.
- Regra pura em `app/Domain/Cobranca` (decisão de consumo e motivo de bloqueio, sem banco); serviço `Carteira` (lock por usuário, débito, registro) também em `app/Domain/Cobranca`.
- Tabelas: `cobranca_config` (interruptor), `assinaturas`, `lotes_credito`, `consumos_analise` (CHECKs impedem saldo negativo; `diagnostico_id` e `quiz_id` únicos).
- `CalcularDiagnostico` consome dentro da mesma transação do Diagnóstico, só com o interruptor ligado. `Quiz` verifica ao abrir e ao concluir e mostra o bloqueio no lugar do quiz.
- Testes: Domain puro (decisão), Feature (carteira, quiz, interruptor, CA-9), `tests/Concorrencia` com processos reais no Postgres, Dusk do bloqueio.

### Decisões tomadas
- Regra pura em `app/Domain/Cobranca/RegrasConsumo` (cota, depois lote por vencimento, senão `Bloqueio` com `MotivoBloqueio`); serviço `Carteira` (consumo sob lock por Usuário, concessão, consulta) no mesmo namespace. Detalhe e alternativas em `IDR-016`.
- O trial é uma assinatura com `trial = true` e `plano_id` nulo. Desvio consciente da lista de status da arquitetura §4.1 (`Trial` como status): o status fica `ativa|em_atraso|cancelada|expirada` e a flag preserva "já teve trial" depois que ele vence. Elegibilidade por CPF fica com a STORY-074; aqui vale "este Usuário nunca teve trial".
- Cancelada dá acesso até o vencimento (espec §2.4). Para cota esgotada com plano cancelado, a frase diz "Seu plano termina em {data}" em vez de "Novas análises em {data}" (variação de microcopy fora da spec, que é provisória; confirmar com o PO/Designer).
- A causa `DescontoAcabouSemCartao` existe no enum e na tela, mas quem a produz é o cupom (STORY-083).
- Ações do bloqueio (trial, planos, créditos, cartão, meu plano) ficam ocultas enquanto a rota não existe (spec §9). Nomes de rota esperados em `TextoBloqueio::ROTAS`; só "Ver diagnósticos" (rota `home`) aparece hoje.
- Interruptor ausente = desligado (`CobrancaConfig::ligada()`), e `atual()` recria a linha desligada. Isso foi necessário porque o `DatabaseTruncation` do Dusk (TRUNCATE CASCADE em `usuarios`) apaga as tabelas de configuração que apontam para `usuarios`; sem isso o quiz inteiro quebrava nos testes de browser.
- `CalcularDiagnostico` ganhou a dependência `Carteira` e cobra dentro da mesma transação do Diagnóstico, só com o interruptor ligado.
- Dois consumos do mesmo quiz: o segundo recebe `AnaliseJaConcluida` (tela "Esta análise já foi concluída." com link) e não cobra. A dedupe é pelo id do rascunho (`quiz_id`, único em `consumos_analise`).

### Descobertas
- `evento_produto` e `term_acceptances` são append-only até para o superusuário via cascata de FK; a limpeza dos testes de concorrência usa `session_replication_role = replica` no superusuário do `.env.testing`.
- `DatabaseTruncation` + Postgres faz TRUNCATE CASCADE: depois do primeiro teste de Dusk que deixa usuários, `planos`, `trial_config`, `pacotes_credito` e `cobranca_config` somem. `PlanosBackofficeBrowserTest` (STORY-071) só passa isolado; na suíte Dusk completa falha por isso, com ou sem esta estória. Não corrigi (fora de escopo); o Dusk desta estória recria o mínimo em `afterTruncatingDatabase`.
- Interromper `php artisan dusk` no meio (pkill) deixa `app/.env` trocado pelo `.env.dusk.local`, e o hook de segredos bloqueia o commit. Restaurar com `cp app/.env.backup app/.env`.
- Livewire exige uma raiz só: o `@if` com `<div>` irmãos fora da raiz funcionava nos testes de Feature e quebrava no browser (o clique em "Próximo" não fazia nada). O desvio ficou dentro da `<div x-data>` existente.

### Bloqueios encontrados
Nenhum.

### IDRs criados
- `IDR-016-carteira-consumo-e-lock-por-usuario`.

### Cobertura final
Suíte All: 1398 testes, cobertura total 98,3% (mínimo 80). Gate de `app/Domain`: 99,7% (mínimo 98); `Carteira` 100%.

### Evidências
- `tests/Domain/Cobranca/RegrasConsumoTest.php` (regra pura), `tests/Feature/Cobranca/CarteiraTest.php`, `tests/Feature/Livewire/Diagnostico/QuizCobrancaTest.php`, `tests/Feature/Livewire/Backoffice/CobrancaInterruptorTest.php`, `tests/Concorrencia/CarteiraConcorrenciaTest.php` (processos reais), `tests/Browser/BloqueioSemSaldoBrowserTest.php`.
- Mutação: sem `travar()` em `Carteira::consumir`, os 5 testes de concorrência falham.
