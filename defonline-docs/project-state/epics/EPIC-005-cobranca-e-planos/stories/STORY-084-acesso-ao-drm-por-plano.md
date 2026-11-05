---
story_id: STORY-084
slug: acesso-ao-drm-por-plano
title: "Acesso ao DRM por plano ou trial"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: in_review
owner_agent: programador (pista B)
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-084 — Acesso ao DRM por plano ou trial

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

O PDR-020 incluiu o DRM em todos os planos pagos e no trial. O PDR-003 proíbe paywall dentro do EPIC-010, por isso a regra vive aqui.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.1
  - `decisions/pdr/PDR-003-modulo-drm-raio-x-do-negocio.md`
  - `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`

## O quê

Condicionar novos lançamentos do Raio X do Negócio a ter plano ativo ou trial, sem esconder o que já foi lançado.

## Por quê

É o principal motivo para manter uma assinatura mensal.

## Critérios de aceite

- [ ] **CA-1:** Com plano ativo ou trial, o usuário usa o módulo normalmente, sem consumir cota.
- [ ] **CA-2:** Quem só tem créditos, ou não tem nada, vê convite para assinar e não faz novos lançamentos nem retificações.
- [ ] **CA-3:** Lançamentos já feitos, demonstrações e série histórica continuam visíveis, com exportação, se já existir, sem novo cálculo a partir de novos dados.
- [ ] **CA-4:** Quem assina de novo volta a lançar e encontra o histórico intacto.
- [ ] **CA-5:** Um cancelamento mantém o acesso até o fim do ciclo pago.
- [ ] **CA-6:** Um rascunho de lançamento não se perde ao bloquear e fica disponível quando o acesso voltar.
- [ ] **CA-7:** Eventos analíticos do DRM (STORY-056) continuam corretos.
- [ ] **CA-8:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Fora de escopo

- Mudar o módulo DRM além do controle de acesso.

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-073, STORY-074, STORY-047
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
- **Um único ponto da regra:** `App\Support\Cobranca\AcessoDrm::bloqueioPara($usuarioId): ?Bloqueio` (e `permitido()`). Respeita o interruptor (desligado = `null`). A regra pura está em `RegrasConsumo::bloqueioDoDrm` e `Carteira::bloqueioDoDrm`. As estórias STORY-051 (série) e STORY-056 (eventos) devem chamar `AcessoDrm` em qualquer ação nova que **grave** dados do DRM; leitura nunca passa por ele.
- **Regra:** assinatura ativa ou cancelada dentro do ciclo, ou trial vigente (mesmo com a cota zerada, pois lançar não consome cota) libera. Sem assinatura (inclui só créditos e créditos vencidos) = `SemNada`; em atraso, plano encerrado e teste encerrado = "lançamento pausado" (spec STORY-070 §4, variante Raio X). Crédito avulso nunca dá acesso.
- **Aplicação:** middleware `drm.acesso` (`ExigirAcessoDrm`) nas rotas `drm.lancar` e `drm.corrigir`, que devolve a view `drm.bloqueio` (reaproveita `x-bloqueio-sem-saldo`, com prop nova `rodape` e prefixo `screen-bloqueio-raiox`, e `TextoBloqueio::paraDrm`). `drm.mes` (demonstração) fica fora, então histórico e exportação seguem visíveis (CA-3).
- **Toque em `app/Livewire/Drm/*`:** só um guard de 3 linhas no início de `Lancamento::fechar` (acesso perdido com a página aberta: recarrega e mostra o bloqueio; o rascunho fica).
- Ações: Ver planos (primária) e Começar teste grátis quando elegível; em atraso, Atualizar cartão e Ver meu plano. Ações só aparecem se a rota existir (padrão da 073).

### Descobertas
- CA-7: a STORY-056 ainda não está na main; nenhum evento foi tocado. O bloqueio acontece antes do componente montar, então nenhum evento de DRM é emitido por tentativa bloqueada. Quando a 056 chegar, ela deve emitir só dentro de ações liberadas por `AcessoDrm`.
- CA-3 "exportação": não existe exportação do DRM ainda; nada a manter além de `drm.mes`.
- A série histórica (051) não existe ainda; quando existir, não deve usar o middleware.

### Bloqueios encontrados
Nenhum.

### IDRs criados
Nenhum (decisão local, sem impacto transversal).

### Cobertura final
Ver relatório do orquestrador.

### Evidências
`app/tests/Feature/Cobranca/AcessoDrmTest.php` (20 casos) e `app/tests/Browser/AcessoDrmBrowserTest.php`.
