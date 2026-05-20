---
report_date: YYYY-MM-DD
sprint_id: SPRINT-YYYY-WNN
wave: WAVE-YYYY-NN
audience: humano-stakeholder
---

# Status DEFOnline — <data>

## TL;DR (3 linhas)

- Onde estamos: <fase atual / sprint>
- O que mudou desde o último relatório: <highlight>
- Próxima entrega visível ao usuário: <quando e o quê>

## Onda atual

- **Onda:** WAVE-YYYY-NN — <objetivo da onda>
- **Progresso da onda:** X de Y épicos concluídos (Z% das estórias da onda `done`)

## Épicos

### Em andamento
| Épico | Status | % estórias done | Próximo marco |
|---|---|---|---|
| EPIC-XXX | in_progress | 4/7 | <marco> |

### Concluídos nesta onda
- EPIC-XXX — <título> — concluído em YYYY-MM-DD — relatório de validação: `epics/EPIC-XXX-*/validation/report.md`

### Próximos
- EPIC-XXX — <título> — começa após <gatilho>

## Sprint corrente

- **Sprint:** SPRINT-YYYY-WNN (de YYYY-MM-DD a YYYY-MM-DD)
- **Objetivo:** <goal do sprint>
- **Estórias:** X done / Y in_progress / Z blocked / W ready

## O que o usuário pode ver agora em homologação

<Lista concreta do que está deployado em homologação e o usuário consegue tocar.>

- ✅ <funcionalidade>
- ✅ <funcionalidade>

## Qualidade

- Cobertura unitária média do código novo do sprint: **X%** (meta 80%)
- Cobertura em núcleo/regras de negócio: **X%** (meta 98%)
- Cenários E2E ativos: **N**
- Pipeline principal: <verde/vermelho>

## Decisões registradas no período

- PDR-XXX — <título>
- ADR-XXX — <título> (Arquiteto)

## Bloqueios e riscos abertos

- <bloqueio> — impacto: <…> — proposta: <…>

## Olhando à frente

> Status report **não é só sobre o passado** — é principalmente sobre acionar futuro. Quem lê precisa saber o que vai acontecer e o que depende dele.

### Próximos 7–14 dias

- <objetivo concreto, observável>
- <objetivo concreto, observável>
- <objetivo concreto, observável>

### Decisões aguardando você (Alexandro)

> Liste explicitamente o que precisa de aprovação humana — ADRs propostas, PDRs em rascunho, escolhas de produto entre opções equivalentes. Inclua impacto se decidir / se não decidir.

- **ADR-XXX** (`title`) — proposta pelo Arquiteto em <data> — bloqueia STORY-YYY do sprint atual. [link]
- **Escolha de produto entre A e B** — afeta priorização da próxima onda. Detalhes em [link]. Sem urgência mas vale conversar até <data>.

### Riscos abertos

> Liste riscos relevantes que ainda não viraram problemas, com mitigação proposta. Diferente de bloqueios (que já estão atrapalhando agora).

- **Risco**: <descrição>. **Probabilidade**: alta/média/baixa. **Impacto**: alto/médio/baixo. **Mitigação proposta**: <ação>. **Owner**: <quem acompanha>.

### Próximos marcos previstos

- **<data ou janela>**: fim do EPIC-XXX (entrega visível: <o quê>).
- **<data ou janela>**: virada de sprint — `SPRINT-YYYY-WNN+1`.
- **<data ou janela>**: validação de métrica do EPIC-YYY (janela D+14 do deploy).

## Apêndice — links rápidos

- Índice do projeto: `defonline-docs/project-state/index.json`
- Onda atual: `defonline-docs/project-state/roadmap/current-wave.md`
- Sprint atual: `defonline-docs/project-state/sprints/SPRINT-YYYY-WNN.md`
