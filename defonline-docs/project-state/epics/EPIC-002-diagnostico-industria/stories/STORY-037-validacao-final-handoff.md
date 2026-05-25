---
story_id: STORY-037
slug: validacao-final-handoff
title: Validação final EPIC-002 (interna) + smoke E2E + pacote de handoff
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: validation
target_role: validador
status: draft
owner_agent: claude-validador
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: M
---

# STORY-037 — Validação final + handoff

> Última estória da sprint. **Fecha o EPIC-002 sob ótica técnica** e entrega o pacote de handoff para o time de comercial/implantação tocar o beta a partir daqui.

## Contexto

A SPRINT-2026-W25 cobre o EPIC-002 em sua totalidade do ponto de vista tech. O recrutamento dos 10+ Robertos, coleta de NPS e operação do beta de 60 dias rodam fora desta sprint (decisão do PO em 2026-05-25, registrada na sprint). Tech entrega: produto validado externamente + pacote de handoff documentado.

## O quê

1. **Smoke E2E em homologação:**
   - Criar conta de teste interna (se ainda não existir).
   - Cadastrar Empresa Analisada (Indústria, marcenaria fictícia).
   - Abrir quiz, preencher 23 campos, confirmar tooltips funcionando, ver validação cruzada disparar com input inconsistente, aceitar e continuar.
   - Calcular diagnóstico, ver relatório em ≤ 3s, conferir 14 indicadores + NCG abs + Resumo Executivo + recomendações DEZ/2025.
   - Confirmar eventos `quiz_iniciado` e `diagnostico_concluido` em banco.
   - Confirmar idempotência: re-submeter o mesmo quiz → não duplica diagnóstico.
   - Logout e login com outra conta → tentar acessar o diagnóstico anterior → 403/404 (conforme IDR-007).
2. **Validação independente formal:**
   - `validation/checklist.md` (PO escreve durante a sprint) listando todos os critérios de aceite do EPIC-002 + critérios da `epic.md`.
   - Validador (agente Claude) roda o checklist, registra evidências em `validation/evidence/` (screenshots, prints de DevTools, SQL outputs).
   - `validation/report.md` com veredito formal: `approved` / `approved_with_pending` / `blocked`.
3. **Pacote de handoff** em `epics/EPIC-002-diagnostico-industria/handoff/README.md`:
   - Link do ambiente de homologação + credenciais de conta de teste.
   - Passo a passo de criação de conta de teste + Empresa Analisada.
   - Roteiro de smoke E2E reproduzível (espelha o item 1 acima).
   - Evidências do p95 ≤ 3s (gráfico/tabela).
   - Cópia ou link do parecer externo (STORY-036).
   - Lista de feature flags ativas e o que controlam.
   - Escopo coberto: setor Indústria, 14 indicadores, matriz DEZ/2025. Escopo **fora**: Comércio, Serviços, PDF, captação, edição de quiz após cálculo, compartilhamento.
   - Contato de suporte técnico durante o beta (responsável de plantão, canal de comunicação, SLA mínimo).
   - Decisões abertas conhecidas (decisões §6 da spec ainda não totalmente fechadas) e como tratá-las se Roberto reclamar.
4. **Promoção do EPIC-002 a `done`** no `index.json` (ótica técnica).
5. **Atualização da matriz de status da WAVE-2026-01** — EPIC-002 sai de `draft` para `done`, EPIC-003 destravado para SPRINT-2026-W30.

## Critérios de aceite

- [ ] **CA-1 (smoke E2E executado):** todos os passos do item 1 acima, com evidências em `validation/evidence/`.
- [ ] **CA-2 (checklist):** `validation/checklist.md` cobre todos os entregáveis da `epic.md` e todos os CA das STORY-026 a STORY-035.
- [ ] **CA-3 (validação independente):** `validation/report.md` com veredito formal. Se `approved_with_pending`, PO triagem cada item.
- [ ] **CA-4 (gate validação externa):** STORY-036 está `approved` ou `approved_with_pending` com follow-ups não-críticos documentados.
- [ ] **CA-5 (p95 ≤ 3s comprovado):** medição final em 50 navegações em homol, p95 ≤ 3s. Gráfico/tabela anexada.
- [ ] **CA-6 (handoff):** `handoff/README.md` cobre os 9 pontos do item 3 acima. Revisado em dry-run com 1 representante de implantação na Semana 4 (Checkpoint 4) antes do fechamento.
- [ ] **CA-7 (cobertura):** geral ≥ 80%, motor ≥ 98%.
- [ ] **CA-8 (zero regressão):** todos os testes Pest + Dusk de EPIC-000/001/004 continuam verdes.
- [ ] **CA-9 (`done` no `index.json`):** EPIC-002 promovido, EPIC-003 destravado.
- [ ] **CA-10 (comunicação):** PO comunica fechamento ao stakeholder (Alexandro + futuro time de comercial/implantação) com link para handoff.

## Fora de escopo

- Recrutamento dos Robertos do beta — comercial.
- Convites, onboarding humano, coleta de NPS — implantação.
- Janela de observação de 60 dias — comercial + PO via status reports da onda.
- Comunicação pública / marketing — fora da sprint.

## Dependências

- **Bloqueada por:** todas as anteriores (STORY-026 a STORY-036).
- **Bloqueia:** abertura da SPRINT-2026-W30 (EPIC-003) + execução do beta pelo time de comercial/implantação.

## Decisões já tomadas

- Fecha o EPIC-002 sob ótica técnica.
- Pacote de handoff é deliverable formal — não é "opcional se sobrar tempo".
- Dry-run com implantação na Semana 4 para não deixar lacunas no handoff.
- Critério "≥ 10 Robertos + NPS ≥ 30" da `epic.md` permanece como meta da WAVE-2026-01, executado pós-handoff.

## DoD

- CA-1 a CA-10 atendidos.
- Tag final `v1.0.0` (primeira versão "production-ready" do produto sob ótica tech) ou equivalente decidida no Checkpoint 5.
- `index.json` atualizado.
- Retro da sprint escrita no próprio `SPRINT-2026-W25.md`.

## Protocolo do agente

Padrão. Validador roda o checklist sistematicamente. PO revisa e assina.

## Notas do agente

*(A preencher.)*
