---
story_id: STORY-045
slug: spike-modelo-versionamento-retificacao
title: "Spike — modelo de dados do DRM, versionamento e retificação de competência"
epic_id: EPIC-010
type: spike
target_role: arquiteto
requires_design: false
status: done
owner_agent: arquiteto (claude-opus-5)
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

- [x] **CA-1:** ADR ou IDR publicado em `decisions/`, `status: accepted`, respondendo às 8 perguntas acima com opções consideradas e justificativa. → **`ADR-007`**, `status: accepted` (PO Alexandro, 2026-08-04). As 8 perguntas estão respondidas em seção própria; 4 opções avaliadas para retificação e 3 para persistência do resultado.
- [x] **CA-2:** A decisão declara explicitamente sua relação com o IDR-010 — se o estende, restringe seu alcance ao Diagnóstico, ou convive em paralelo. O IDR-010 **não é revogado**. → seção *Relação com o IDR-010*: **estende**, com 1 divergência deliberada nomeada e justificada (papel do `payload_hash`). Nenhuma linha do IDR-010 precisa mudar.
- [x] **CA-3:** Requisito de produto atendido e demonstrável na decisão: o usuário retifica uma competência fechada, a versão anterior é preservada e recuperável, e a tela consegue sinalizar a retificação. → seção *O requisito de produto, demonstrado*, com o cenário da Joana percorrido linha a linha no banco.
- [x] **CA-4:** Esboço de esquema de tabelas com chaves, unicidade, constraints e política de soft delete, aderente às convenções do ADR-003 e do `database-schema.md` (nomes em português, PK uuid v7, enums por CHECK, FK RESTRICT por padrão). → seção *Esboço de esquema*: `drm_lancamentos`, `drm_rascunhos`, `drm_preferencias` + diagrama ER com a fronteira do Diagnóstico.
- [x] **CA-5:** Impacto declarado sobre cada estória de implementação do épico — quais premissas cada uma pode assumir. → tabela *Para o time — impacto por estória*, cobrindo 047–051 em profundidade e 053–058 no essencial.
- [x] **CA-6:** Se houver spike de código, ele vive em branch `spike/STORY-045-*` e **não** é mergeado em `main`. → **não houve spike de código** e a ADR justifica por quê (todos os mecanismos usados já estão em produção no projeto). Nenhum branch criado, nada a mergear.
- [x] **CA-7:** `index.json` atualizado com a nova decisão indexada. → `decisions.adr[]` recebeu `ADR-007` em `proposed`; `STORY-045` movida para `in_review`.

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

- [x] CA-1 a CA-7 atendidos.
- [x] Decisão revisada com o PO antes de `accepted` (o PO valida se o requisito de produto de CA-3 foi atendido, não a solução técnica). → PO confirmou os quatro pontos submetidos em 2026-08-04; `ADR-007` movida para `accepted`.
- [x] `index.json` atualizado.
- [x] Notas do agente preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Ao terminar, marque `in_review` e avise o PO.

## Notas do agente

### Decisões tomadas

Instrumento escolhido: **ADR**, não IDR. A decisão define modelo de dados macro, atravessa cinco estórias e dois agentes, e limita decisões futuras do Programador — os três testes de `adr-types.md` para "estrutural". Tipo: Persistência.

**A colisão com o IDR-010 é aparente.** Ela desaparece ao separar *imutabilidade do registro* (nenhuma linha muda depois de gravada — é o que o IDR-010 estabelece) de *mutabilidade do conceito* (a "competência 09/2026" é uma identidade estável que aponta para registros diferentes ao longo do tempo). O Diagnóstico nunca precisou da segunda porque cada diagnóstico é um evento isolado; a competência do DRM é única por empresa (regra B1) e o usuário se refere a ela como uma coisa só. Daí a ADR **estender** o IDR-010 em vez de disputar com ele.

As oito respostas, em uma linha cada:

1. **Retificação** — nova versão append-only + ponteiro `substituido_por_id`; vigente = ponteiro nulo, garantido por índice único parcial. Tela lê retificação e contagem de `versao`, sem coluna extra e sem `COUNT`.
2. **Snapshot** — persistido por versão, auto-descritivo, com os dois modos de pró-labore já resolvidos. `motor_drm_version` semver com contador independente do Diagnóstico, sem `matrix_version`. Retificar não é recálculo retroativo: é ato novo, carimbado com o motor do momento.
3. **Identidade** — `(empresa_analisada_id, competencia)`; `competencia` é `date` com dia 01. Idempotência em duas camadas: índice único parcial (duplo submit, resolvido pelo Postgres) + `payload_hash` contra a vigente (bloqueia retificação no-op).
4. **Modo agregado/detalhado** — **ambos**: preferência em `drm_preferencias`, fato carimbado no snapshot (e no hash). É o carimbo que sustenta a granularidade mista da série sem mentir.
5. **Ausência vs. zero** — ausência de lançamento é ausência de linha; espinha de meses gerada na leitura; `COALESCE(…, 0)` proibido em query de série.
6. **Multi-tenancy** — herança integral de ADR-003 + IDR-009, com um desvio declarado (`drm_preferencias` sem soft delete: é configuração, não tem PII).
7. **Rascunho** — reusa o padrão `quiz_rascunhos`; o pré-preenchimento **não exige mecânica própria** (é semente do payload jsonb). Retificação passa pelo mesmo caminho, com `origem` + `base_versao` dando concorrência otimista de brinde.
8. **Fronteira** — três tabelas com prefixo `drm_`, zero FK para tabelas do Diagnóstico, namespace/config/golden tests separados, e a restrição dura de que o DRM não pode ser capaz de alterar `diagnosticos.payload_hash`.

### Descobertas

- **`STORY-050` CA-5 ("sem valores obsoletos em cache") deixa de ser risco e vira impossibilidade**, porque comparativos e faróis **não** são persistidos — são derivados na leitura sobre as vigentes. Persisti-los obrigaria a reescrever snapshots dos meses seguintes ao retificar um mês, que é literalmente o recálculo retroativo que o épico proíbe.
- **A promessa do `PDR-004` §B vira propriedade do modelo, não disciplina de implementação**: guardar os dois modos de pró-labore no mesmo snapshot faz "alternar não recalcula nada, não quebra a série e não existe 'em que modo este mês foi lançado'" ser verdade por construção. `STORY-049` CA-3b passa a ser trocar de array.
- **Persistir os rótulos das linhas no snapshot é obrigatório**, não conveniência: é a lição do aditivo de 2026-08-04 do IDR-010, quando trocar uma palavra em texto de veredito se revelou output do motor e exigiu bump + reemissão de golden hashes.
- **A camada de leitura precisa renderizar o snapshot genericamente** (sem hardcode de `L1..L11`), senão um bump MAJOR do motor quebra a exibição de meses antigos. Restrição registrada para a `STORY-049`.
- **Competência futura (regra B2) não pode virar `CHECK`** — depende de `now()`, que não é `IMMUTABLE`, e o Postgres recusa. Fica como validação de aplicação junto às demais do §K.6.
- **Uma coluna `vigente` seria redundante** com `substituido_por_id IS NULL` e as duas derivariam. Descartada — um único representante, usado direto no predicado do índice parcial.
- **O índice único de vigência serve também como índice de leitura da série** (mesmo btree, varrido em ordem inversa). Um índice a menos que o esboço inicial previa.
- **`tratamento_sazonal` foi deliberadamente omitido** de `drm_preferencias`: hoje tem um único valor legal (§K.5-A) e coluna de valor único é peso morto. Gatilho de inclusão registrado na ADR: a `STORY-058` aprovar um segundo tratamento.
- **`STORY-058` não é hipotecada** por nada aqui: se a provisão for aprovada, é tabela nova + um valor a mais em `drm_preferencias`; `drm_lancamentos` não muda de forma.
- **A comparação lado a lado entre versões ficou barata** — as versões são registros completos. A `STORY-050` a coloca fora de escopo e manda propor ao PO se ficasse barata; fica registrado que ficou.

### Bloqueios encontrados

Nenhum bloqueio técnico. Quatro pontos foram submetidos ao PO e **todos confirmados em 2026-08-04** (registrados na §Aprovação humana da `ADR-007`):

1. O cenário demonstrado atende ao `PDR-003` dec. 7.
2. **Faróis fora do snapshot** — recalibrar as faixas da EBC pode mudar a cor de meses antigos; valores nominais e percentuais nunca mudam. **Consequência que a `STORY-054` precisa endereçar:** a tela tem de deixar legível que o farol é *leitura*, não medição, senão o usuário lê uma cor diferente num mês que ele já tinha visto e conclui que o número mudou.
3. Retificação no-op não incrementa `versao` e não aparece como retificação.
4. Sem campo "motivo da retificação" e sem exclusão de lançamento no MVP — ambos permanecem como acréscimos baratos e reversíveis.

### ADR/IDR criados

- **`ADR-007`** — *Modelo de dados do DRM — competência versionada append-only, snapshot imutável por versão e retificação com ponteiro de vigência*. `decisions/adr/ADR-007-modelo-dados-drm-versionamento-retificacao.md`, **`status: accepted`** (PO Alexandro, 2026-08-04), indexada no `index.json`.
- **`IDR-010`** — ponteiro recíproco acrescentado (`related_adrs`, `extended_by`, entrada de histórico). **Nenhuma decisão daquela IDR foi alterada**; a entrada registra os dois pontos de contato e a única divergência deliberada, para que um agente futuro não leia a diferença de tratamento do `payload_hash` como incoerência.
