---
story_id: STORY-056
slug: eventos-analiticos-drm
title: "Eventos analíticos do DRM — instrumentar recorrência, atrito e alcance"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: S
checkpoint: CP3
---

# STORY-056 — Eventos analíticos do DRM

## Contexto

O PDR-003 decidiu entregar o módulo **sem cobrança** com uma condição explícita: medir adesão antes de investir em gateway de pagamento. Essa condição só se cumpre se os eventos existirem. Sem esta estória, o épico fecha sem conseguir responder se a hipótese se confirmou — e a decisão do EPIC-005 volta a ser um chute.

A instrumentação já tem padrão consolidado no produto, incluindo bloqueio de dado pessoal em tempo de execução e teste arquitetural que impede emissão fora do canal oficial. Siga esse padrão.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `decisions/adr/ADR-004-*` (observabilidade); a instrumentação de eventos do Diagnóstico já em produção; `product/north-star.md`

## O quê

Emitir os eventos de produto que permitem calcular as três métricas do épico e alimentar a decisão de precificação do EPIC-005.

## Critérios de aceite

- [ ] **CA-1 (Recorrência — métrica primária):** eventos suficientes para calcular a taxa de usuários que concluem o 2º lançamento em até 45 dias após o 1º, e a distribuição de quantos lançamentos cada empresa acumula.
- [ ] **CA-2 (Atrito — métrica secundária):** eventos que permitam calcular taxa de conclusão dos lançamentos iniciados, tempo até a conclusão, e ponto de abandono quando o lançamento não é concluído.
- [ ] **CA-3 (Alcance — métrica secundária):** todo evento de lançamento carrega o setor da empresa, permitindo medir a participação de Comércio e Serviços.
- [ ] **CA-4 (Uso dos mecanismos anti-atrito):** eventos que revelem quantos grupos ficam em modo agregado vs. detalhado, quanto do pré-preenchimento é aceito sem alteração, e quantos lançamentos passam por rascunho retomado. **É o que vai dizer se os mecanismos do PDR-003 funcionaram.**
- [ ] **CA-5 (Qualidade do dado):** eventos de retificação, de alerta aceito e de regra bloqueante acionada — insumo direto do sinal de revisão do PDR-003 sobre taxa de retificação acima de 30%.
- [ ] **CA-6 (Exportação e leitura):** eventos de exportação, por formato.
- [ ] **CA-7 (Sem dado pessoal):** nenhum evento carrega valor financeiro absoluto, nome, documento ou qualquer dado que identifique pessoa ou empresa. Faixas e contagens, não valores. Garantido pelo bloqueio em tempo de execução já existente.
- [ ] **CA-8 (Idempotência):** eventos idempotentes, no padrão já adotado — reenvio não duplica.
- [ ] **CA-9 (Canal único):** emissão exclusivamente pelo canal oficial de eventos, coberta pelo teste arquitetural existente.
- [ ] **CA-10 (Testes):** feature verificando emissão de cada evento no fluxo correspondente e ausência de dado pessoal.
- [ ] **CA-11 (Cobertura):** ≥ 80%.

## Fora de escopo

- Painel ou relatório de análise. Os eventos ficam disponíveis para consulta; construir visualização é outra conversa.
- Integração com ferramenta externa de analytics.

## Dependências

- **Bloqueada por:** STORY-047, STORY-049, STORY-050, STORY-051.
- **Bloqueia:** STORY-057 — a validação confere se as métricas do épico são calculáveis.

## Decisões já tomadas (não as reabra)

- ADR-004 — observabilidade sobre a base já existente.
- Sem dado pessoal em evento, sem exceção.
- PDR-003, decisão 8 — medir antes de cobrar.

## Definição de Pronto

- [ ] CA-1 a CA-11 passam.
- [ ] **Demonstração de que as três métricas do épico são calculáveis** a partir dos eventos emitidos — consulta de exemplo registrada nas notas.
- [ ] Pipeline verde; `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### IDRs criados
### Cobertura final
