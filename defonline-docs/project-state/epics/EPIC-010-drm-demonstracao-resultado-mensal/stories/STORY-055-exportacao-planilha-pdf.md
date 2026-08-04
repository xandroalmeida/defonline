---
story_id: STORY-055
slug: exportacao-planilha-pdf
title: "Exportação da Demonstração — planilha e PDF"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: M
checkpoint: CP3
---

# STORY-055 — Exportação em planilha e PDF

## Contexto

O resultado do mês precisa sair do produto. O dono leva ao contador, ao sócio, ao gerente do banco. E quer a planilha — porque é onde ele consegue mexer, e porque foi de uma planilha que a proposta do Key User nasceu.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: ADR da STORY-053 (**contrato desta estória**, inclusive quanto ao escopo recomendado); `epics/EPIC-010-*/design/estrutura-drm.md`; `Demonstração de Resultado Mensal - DRM.xlsx` — a planilha original é a referência de layout que o Key User já validou.

## O quê

Exportar a Demonstração de Resultado Mensal em planilha e em PDF, no escopo recomendado pelo ADR da STORY-053.

## Critérios de aceite

- [ ] **CA-1 (Planilha):** exportação em formato de planilha reproduzindo a estrutura de `design/estrutura-drm.md` §1, com valores nominais e percentuais, cabeçalho identificando empresa e competência.
- [ ] **CA-2 (Fidelidade ao original):** a planilha exportada é reconhecível por quem conhece o modelo do Key User — mesma ordem de linhas, mesma hierarquia, mesma coluna de percentual.
- [ ] **CA-3 (PDF):** exportação em PDF A4 com a demonstração completa, farol e leitura quando disponíveis, e rodapé com versão do motor, versão do conteúdo e data de geração. **Se o ADR da STORY-053 recomendou adiar o PDF, este critério é `n/a`** e a exportação em PDF volta ao EPIC-007 — registre a decisão nas notas.
- [ ] **CA-4 (Escopo):** o escopo exportável — uma competência, um intervalo ou a série — segue a recomendação do ADR.
- [ ] **CA-5 (Fonte é o snapshot):** a exportação usa o resultado persistido, não recálculo. Exportar um mês retificado produz os valores retificados, com a retificação sinalizada no documento.
- [ ] **CA-6 (Escopo de acesso):** só o dono exporta. Cross-tenant retorna 404 silente.
- [ ] **CA-7 (Sem faturamento e prejuízo):** ambos os estados são exportados corretamente, com a mesma semântica da tela — percentuais indisponíveis explicados, prejuízo sinalizado sem dramatizar.
- [ ] **CA-8 (Nomenclatura do arquivo):** nome previsível e ordenável, identificando empresa e competência.
- [ ] **CA-9 (Desempenho e retenção):** conforme o ADR. Se a geração for assíncrona, o usuário recebe feedback claro do estado.
- [ ] **CA-10 (Testes):** feature cobrindo exportação nos três setores, mês sem faturamento, mês retificado e cross-tenant; verificação de conteúdo do arquivo gerado, não só do status da resposta; E2E percorrendo demonstração → exportar → arquivo recebido.
- [ ] **CA-11 (Cobertura):** ≥ 80%.

## Fora de escopo

- Envio por e-mail, compartilhamento por link público, integração com armazenamento em nuvem.
- Personalização de layout ou marca do usuário.
- Exportação do relatório de Diagnóstico — é o EPIC-007, ainda que reuse a mesma base decidida na STORY-053.

## Dependências

- **Bloqueada por:** STORY-053 (ADR), STORY-049, STORY-051. STORY-054 é desejável, não bloqueante — sem ela, o documento sai sem farol e sem leitura.
- **Bloqueia:** nada.

## Decisões já tomadas (não as reabra)

- ADR da STORY-053 — mecânica, escopo e retenção.
- Fonte é o snapshot, nunca recálculo.
- `design/estrutura-drm.md` — estrutura e ordem das linhas.

## Definição de Pronto

- [ ] CA-1 a CA-11 passam (CA-3 pode ser `n/a` conforme o ADR).
- [ ] **Checkpoint 3 demonstrado em homologação:** demonstração com farol e leitura, exportada.
- [ ] Pipeline verde; `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### IDRs criados
### Cobertura final
