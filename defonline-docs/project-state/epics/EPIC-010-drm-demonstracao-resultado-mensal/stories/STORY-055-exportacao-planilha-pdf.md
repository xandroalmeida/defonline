---
story_id: STORY-055
slug: exportacao-planilha-pdf
title: "Exportação da Demonstração — planilha XLSX e camada de impressão"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-06
decisoes: [ADR-008, PDR-007, PDR-008]
estimated_session_size: M
checkpoint: CP3
---

# STORY-055 — Exportação em planilha e PDF

## Contexto

O resultado do mês precisa sair do produto. O dono leva ao contador, ao sócio, ao gerente do banco. E quer a planilha — porque é onde ele consegue mexer, e porque foi de uma planilha que a proposta do Key User nasceu.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: ADR da STORY-053 (**contrato desta estória**, inclusive quanto ao escopo recomendado); `epics/EPIC-010-*/design/estrutura-drm.md`; `Demonstração de Resultado Mensal - DRM.xlsx` — a planilha original é a referência de layout que o Key User já validou.

## O quê

Exportar a Demonstração de Resultado Mensal **em planilha XLSX**, e entregar a **camada de impressão** que permite ao usuário salvar um PDF pelo navegador enquanto o PDF da plataforma não existe.

> **O escopo desta estória mudou em 2026-08-06.** O `ADR-008` (aceito no `PDR-007`) separou os dois eixos: a planilha é desta estória; o **PDF gerado no servidor foi devolvido ao EPIC-007**, que abrirá com spike de motor. O `slug` do arquivo (`exportacao-planilha-pdf`) é histórico e não foi renomeado para não quebrar referências.

## Critérios de aceite

- [ ] **CA-1 (Planilha XLSX):** exportação em **XLSX** (via `phpoffice/phpspreadsheet`) reproduzindo a estrutura de `design/estrutura-drm.md` §1, com valores nominais e percentuais, cabeçalho identificando empresa e competência. **CSV não atende** — o `ADR-008` registra por quê: em CSV não existe separador certo, só escolha de quem vai reclamar.
- [ ] **CA-2 (Fidelidade ao original):** a planilha exportada é reconhecível por quem conhece o modelo do Key User — mesma ordem de linhas, mesma hierarquia, mesma coluna de percentual.
- [x] **CA-3 (PDF): `n/a` — resolvido em 2026-08-06 pelo `ADR-008`/`PDR-007`.** O PDF gerado no servidor voltou ao EPIC-007. **Não implemente PDF nesta estória** e não instale motor de renderização: `dompdf`, `mPDF` e SaaS de HTML→PDF estão desqualificados por antecipação, e a direção registrada (Chromium isolado em container) será validada em spike próprio do EPIC-007. A fila `pdf` do worker segue declarada e vazia — configuração intencionalmente morta.
- [ ] **CA-4 (Escopo do arquivo):** **um arquivo, duas abas** — a competência aberta e a série histórica. **Sem seletor de intervalo** (`ADR-008`). Fórmulas nas linhas derivadas: é isso que faz a planilha ser planilha em vez de PDF com bordas, e é o que o Key User pediu ao querer mexer nos números.
- [ ] **CA-5 (Fonte é o snapshot):** a exportação usa o resultado persistido, não recálculo. Exportar um mês retificado produz os valores retificados, com a retificação sinalizada no documento.
- [ ] **CA-6 (Escopo de acesso):** só o dono exporta. Cross-tenant retorna 404 silente.
- [ ] **CA-7 (Sem faturamento e prejuízo):** ambos os estados são exportados corretamente, com a mesma semântica da tela — percentuais indisponíveis explicados, prejuízo sinalizado sem dramatizar.
- [ ] **CA-8 (Nomenclatura do arquivo):** nome previsível e ordenável, identificando empresa e competência.
- [ ] **CA-9 (Síncrono e sem retenção):** geração **síncrona** por `streamDownload()`. O arquivo **nunca é armazenado** — sem storage, sem URL assinada, sem expurgo, sem estado "gerando" na UI e sem job em fila. É o que mantém a exportação fora do escopo de dado pessoal em repouso (`ADR-008`, força F5).
- [ ] **CA-10 (Testes):** feature cobrindo exportação nos três setores, mês sem faturamento, mês retificado e cross-tenant. **O teste relê o XLSX gerado e confere célula a célula, inclusive avaliando as fórmulas** com `getCalculatedValue()` contra o snapshot — verificar só o status da resposta não prova nada, e fórmula errada é planilha que mente. E2E percorrendo demonstração → exportar → arquivo recebido.
- [ ] **CA-11 (Cobertura):** ≥ 80%.
- [ ] **CA-12 (Camada de impressão):** folha de estilo `@media print` sobre o **mesmo markup** da tela da Demonstração do Mês — sem segundo template, sem hex literal fora do `tokens.css` (`IDR-008`) — mais o botão `Imprimir / Salvar em PDF`. Sai em A4 retrato, sem cabeçalho de navegação, sem drawer e sem botões de ação, com o rodapé de versão do motor, versão do conteúdo e data de geração. A especificação funcional §4.7 já previa isso: *"impressão é um atalho que abre a janela de impressão do navegador sobre a versão web"*.
- [ ] **CA-13 (Impressão é interina, e o produto não promete o contrário):** o botão de impressão **não** se chama "Exportar PDF" nem sugere que o arquivo é gerado por nós. Quando o EPIC-007 entregar o PDF da plataforma, esta camada é substituída, não acumulada.

## Fora de escopo

- **PDF gerado no servidor** — devolvido ao EPIC-007 pelo `ADR-008`. Não instale motor de renderização nem reative a fila `pdf`.
- Envio por e-mail, compartilhamento por link público, integração com armazenamento em nuvem.
- Personalização de layout ou marca do usuário.
- Exportação do relatório de Diagnóstico — é o EPIC-007, ainda que reuse a mesma base decidida na STORY-053.

## Dependências

- **Bloqueada por:** STORY-049, STORY-051. A STORY-053 fechou em 06/08/2026 com o `ADR-008` aceito — não é mais bloqueio. STORY-054 é desejável, não bloqueante: sem ela, o documento sai sem farol e sem leitura.
- **Bloqueia:** nada.

## Decisões já tomadas (não as reabra)

- **`ADR-008`** (STORY-053, aceito no `PDR-007`) — mecânica, escopo e retenção. XLSX síncrono sem retenção; PDF no EPIC-007; `@media print` como saída interina.
- **`PDR-008` §2** — a camada de impressão mora **aqui**, não na `STORY-049`, para não engordar o caminho crítico do Checkpoint 1.
- Fonte é o snapshot, nunca recálculo.
- `design/estrutura-drm.md` — estrutura e ordem das linhas.

## Definição de Pronto

- [ ] CA-1 a CA-13 passam. O **CA-3 já está resolvido como `n/a`** — não é decisão do agente.
- [ ] **Checkpoint 3 demonstrado em homologação:** demonstração com farol e leitura, exportada em XLSX e impressa pelo navegador.
- [ ] Pipeline verde; `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### IDRs criados
### Cobertura final
