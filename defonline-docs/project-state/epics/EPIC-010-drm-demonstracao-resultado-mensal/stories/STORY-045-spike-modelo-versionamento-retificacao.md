---
story_id: STORY-045
slug: spike-modelo-versionamento-retificacao
title: "Spike — modelo de dados do DRM, versionamento e retificação de competência"
epic_id: EPIC-010
type: spike
target_role: arquiteto
requires_design: false
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: M
---

# STORY-045 — Spike: modelo de dados, versionamento e retificação

> **Para o agente que vai executar:** você é o **Arquiteto**. Esta estória não entrega funcionalidade ao usuário; entrega a decisão que destrava todas as outras do épico. Nenhuma estória de implementação do EPIC-010 pode começar antes desta fechar.

## Contexto

O DRM é o primeiro objeto do produto que o usuário precisa **corrigir depois de criado**. Ele vai lançar setembro na primeira semana de outubro, e vai descobrir na conciliação do extrato que errou a taxa da maquininha. Se o lançamento for imutável, o módulo é inutilizável. Se for livremente editável, perdemos a trilha de auditoria e a série histórica muda debaixo dos pés do usuário sem explicação.

Isso colide com o `IDR-010`, que estabeleceu snapshot imutável para o Diagnóstico — persistir a saída, as versões que a produziram, e nunca recalcular o passado. **O IDR-010 não deve ser revogado.** A questão é como acomodar um objeto retificável ao lado de um imutável sem corromper nenhum dos dois princípios.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `decisions/pdr/PDR-003-modulo-drm-raio-x-do-negocio.md`; `decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md`; `decisions/adr/ADR-003-persistencia.md`; `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` (§K.5, §K.9); `epics/EPIC-010-*/design/estrutura-drm.md`

## O quê

Produzir um **ADR ou IDR** (você decide o instrumento pelo alcance da decisão) que fixe o modelo de dados do módulo DRM e a mecânica de retificação, versionamento e idempotência, de modo que as estórias de implementação sigam sem reabrir o assunto.

## Por quê

Retificação decidida tarde, ou decidida pelo programador no meio da implementação, vira migração dolorosa depois que existirem lançamentos reais. Esta é a única decisão do épico com potencial de exigir retrabalho estrutural.

## Perguntas que a decisão precisa responder

1. **Retificação.** Nova versão do lançamento, histórico append-only, ou mutação com audit trail? Como a tela sabe que aquela competência foi retificada e quantas vezes?
2. **Snapshot.** O resultado calculado é persistido junto ao lançamento, como no Diagnóstico, ou recalculado a cada leitura? Se persistido, o que acontece com a versão do motor quando ela evoluir — o épico já declarou que **não há recálculo retroativo**.
3. **Identidade e idempotência.** A competência é única por Empresa Analisada (regra B1 do Anexo K). Qual a chave? Como o duplo submit não cria dois lançamentos?
4. **Modo agregado vs. detalhado** (Anexo K §K.5). O modo é atributo do lançamento, preferência da empresa, ou ambos? Como a série histórica lida com meses de granularidades diferentes sem mentir?
5. **Ausência vs. zero.** Como o modelo distingue "mês não lançado" de "mês sem movimento"? A série histórica depende disso.
6. **Multi-tenancy.** Confirmar herança do padrão do ADR-003 (FK + Global Scope + soft delete + CHECK constraints) e do IDR-009 (404 silente cross-tenant).
7. **Rascunho.** Reaproveita o padrão de `quiz_rascunhos` ou o DRM precisa de mecânica própria por causa do pré-preenchimento a partir da competência anterior?
8. **Convivência com o Diagnóstico.** Nada no modelo do DRM pode afetar `payload_hash`, `motor_version` ou `matrix_version` do Diagnóstico. Declarar explicitamente a fronteira.

## Critérios de aceite

- [ ] **CA-1:** ADR ou IDR publicado em `decisions/`, `status: accepted`, respondendo às 8 perguntas acima com opções consideradas e justificativa.
- [ ] **CA-2:** A decisão declara explicitamente sua relação com o IDR-010 — se o estende, restringe seu alcance ao Diagnóstico, ou convive em paralelo. O IDR-010 **não é revogado**.
- [ ] **CA-3:** Requisito de produto atendido e demonstrável na decisão: o usuário retifica uma competência fechada, a versão anterior é preservada e recuperável, e a tela consegue sinalizar a retificação.
- [ ] **CA-4:** Esboço de esquema de tabelas com chaves, unicidade, constraints e política de soft delete, aderente às convenções do ADR-003 e do `database-schema.md` (nomes em português, PK uuid v7, enums por CHECK, FK RESTRICT por padrão).
- [ ] **CA-5:** Impacto declarado sobre cada estória de implementação do épico — quais premissas cada uma pode assumir.
- [ ] **CA-6:** Se houver spike de código, ele vive em branch `spike/STORY-045-*` e **não** é mergeado em `main`.
- [ ] **CA-7:** `index.json` atualizado com a nova decisão indexada.

## Fora de escopo

- Escrever a migration definitiva ou qualquer código de produção.
- Decidir o motor de exportação (é a STORY-053).
- Decidir posição do pró-labore ou rótulos — são decisões de negócio (`[DECIDIR-B]`, `[DECIDIR-C]`), não suas.

## Padrões de qualidade exigidos

Padrões em `defonline-docs/skills/po/references/quality-standards.md`. Sendo spike, o entregável é a **decisão documentada**, não cobertura de teste. Se houver protótipo, ele é descartável e assim declarado.

## Dependências

- **Bloqueada por:** nada.
- **Bloqueia:** STORY-047, STORY-048, STORY-049, STORY-050, STORY-051.

## Decisões já tomadas (não as reabra)

- PDR-003 — módulo independente, três setores, sem cobrança, entrada em reais, competência de primeira classe, retificação como requisito.
- ADR-001/002/003 — stack, topologia, persistência.
- IDR-009 — 404 silente cross-tenant.
- IDR-010 — vigente para o Diagnóstico.

## Definição de Pronto

- [ ] CA-1 a CA-7 atendidos.
- [ ] Decisão revisada com o PO antes de `accepted` (o PO valida se o requisito de produto de CA-3 foi atendido, não a solução técnica).
- [ ] `index.json` atualizado.
- [ ] Notas do agente preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Ao terminar, marque `in_review` e avise o PO.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### ADR/IDR criados
