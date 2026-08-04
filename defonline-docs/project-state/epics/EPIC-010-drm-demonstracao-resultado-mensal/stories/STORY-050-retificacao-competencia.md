---
story_id: STORY-050
slug: retificacao-competencia
title: "Retificação de competência — corrigir um mês já lançado sem perder o histórico"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: M
checkpoint: CP2
---

# STORY-050 — Retificação de competência

## Contexto

O usuário lança setembro na primeira semana de outubro e, ao conciliar o extrato, descobre que errou a taxa da maquininha. Sem retificação, ele tem três opções ruins: conviver com o número errado, abandonar o módulo, ou recomeçar do zero. Todas custam a recorrência que é a métrica primária do épico.

A mecânica de versionamento foi decidida na STORY-045. Esta estória a materializa.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: decisão da STORY-045 (**contrato desta estória**); `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` §K.9; `decisions/idr/IDR-010-*`

## O quê

Permitir que o usuário corrija uma competência já fechada, preservando a versão anterior e sinalizando a retificação onde o número aparece.

## Por quê

Confiança. Um número que não pode ser corrigido é um número em que não se confia — e uma série histórica construída sobre erros conhecidos é pior do que série nenhuma.

## Critérios de aceite

- [ ] **CA-1 (Entrada):** a partir da demonstração de um mês fechado e a partir da série histórica, o usuário aciona a retificação e reabre o lançamento com os valores atuais preenchidos.
- [ ] **CA-2 (Preservação):** ao confirmar, a versão anterior é preservada conforme a decisão da STORY-045 e permanece recuperável. Nada é sobrescrito de forma destrutiva.
- [ ] **CA-3 (Recálculo da competência retificada):** a demonstração daquela competência passa a refletir os novos valores, com novo snapshot e nova versão. **Nenhuma outra competência é recalculada.**
- [ ] **CA-4 (Sinalização):** a demonstração e a série histórica indicam que aquele mês foi retificado, com data da última retificação. O usuário consegue ver quantas vezes o mês foi corrigido.
- [ ] **CA-5 (Efeito nos comparativos):** retificar um mês altera os comparativos dos meses seguintes que o usam como base. Esse recálculo derivado é esperado e deve ser consistente — sem valores obsoletos em cache.
- [ ] **CA-6 (Mesmas regras):** todas as regras bloqueantes e alertas do Anexo K §K.6 valem na retificação, exceto B1 (competência duplicada), que não se aplica.
- [ ] **CA-7 (Auditoria):** cada retificação é registrada com autor, momento e origem, no padrão de audit log já existente no produto.
- [ ] **CA-8 (Escopo):** só o dono retifica. Cross-tenant retorna 404 silente.
- [ ] **CA-9 (Testes):** feature cobrindo retificação simples, retificação múltipla do mesmo mês, efeito nos comparativos do mês seguinte, e preservação da versão anterior; E2E percorrendo lançar → retificar → conferir sinalização.
- [ ] **CA-10 (Cobertura):** ≥ 80%; ≥ 98% no que for regra de negócio.

## Fora de escopo

- Tela de comparação lado a lado entre versões. Se a decisão da STORY-045 tornar isso barato, proponha ao PO — não implemente por conta.
- Exclusão de lançamento. Não está no MVP; se o usuário quiser zerar um mês, ele retifica com zeros.
- Retificação em lote.

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`.

## Dependências

- **Bloqueada por:** STORY-045, STORY-047, STORY-048, STORY-049.
- **Bloqueia:** STORY-051 (a série precisa saber exibir mês retificado).

## Decisões já tomadas (não as reabra)

- Decisão da STORY-045 — mecânica de versionamento.
- IDR-010 — sem recálculo retroativo de competências não retificadas.
- PDR-003, decisão 7.

## Definição de Pronto

- [ ] CA-1 a CA-10 passam.
- [ ] Pipeline verde; deploy em homologação verificado.
- [ ] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### IDRs criados
### Cobertura final
