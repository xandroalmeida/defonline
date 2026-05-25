---
epic_id: EPIC-002
slug: diagnostico-industria
title: Diagnóstico Econômico-Financeiro para Indústria
wave: WAVE-2026-01
status: active
owner_role: po
created_at: 2026-05-20
updated_at: 2026-05-25
activated_at: 2026-05-25
target_completion: 2026-08-12
---

# EPIC-002 — Diagnóstico Econômico-Financeiro para Indústria

## Por que existimos (problema do usuário)

Esta é a estória que valida a hipótese central da WAVE-2026-01: **o produto entrega valor real ao Roberto?** Sem diagnóstico, o DEFOnline é só um cadastro vazio. Com diagnóstico, Roberto sai com uma resposta para "estou saudável? posso captar? quanto?" e o produto se justifica.

A dor concreta é: Roberto não consegue, sozinho, traduzir o controle financeiro da marcenaria em uma avaliação técnica. Contratar consultor é caro e demorado. O contador entrega obrigação fiscal. Este épico fecha essa lacuna em 15 minutos.

## Resultado esperado (outcome)

Ao fim do EPIC-002, Roberto seleciona a marcenaria entre as Empresas Analisadas dele, responde o quiz com os campos da Estrutura aplicáveis ao setor Indústria, aciona o cálculo e recebe — em poucos segundos — um relatório web com os 14 indicadores, semáforo visual (verde/amarelo/vermelho), recomendações específicas para Indústria conforme matriz DEZ/2025, Resumo Executivo gerado pelo algoritmo determinístico da spec V2.5 §4.7.1 e glossário inline. O relatório é legível por Roberto sozinho, sem precisar de contador para interpretar.

## Métrica de sucesso (como saberemos que funcionou)

- **Métrica primária (driver D2 — Ativação da árvore do north star):** ≥ 60% dos Usuários cadastrados concluem o 1º diagnóstico em até 7 dias após o cadastro. Janela: D+30 após deploy em homologação.
- **Métrica primária de produto (alimenta direto o north star):** ≥ 20 MPEs ativas com diagnóstico concluído ao fim do beta fechado de 60 dias.
- **Métrica de qualidade percebida:** NPS médio do relatório ≥ 30 entre os primeiros 20 Robertos.
- **Métrica de qualidade técnica:** geração do relatório em ≤ 3 segundos no p95 para dataset típico; cobertura de testes do motor ≥ 98% nas regras de cálculo (regra de núcleo conforme `quality-standards.md`); validação do motor com ≥ 10 casos de teste por fórmula (NRF §9.2).

## Entregável visível no fim do épico

- [ ] Roberto, em homologação, preenche o quiz da marcenaria e recebe o relatório web em ≤ 3s p95.
- [ ] O relatório exibe 14 indicadores com semáforo, recomendações textuais por indicador (matriz DEZ/2025 filtrada para Indústria), Resumo Executivo e glossário acessível.
- [ ] NCG absoluto aparece como indicador informativo sem farol (decisão da spec V2.5 §4.5).
- [ ] Validações cruzadas DRE × Balanço e mensagens de erro acionáveis quando Roberto digita dado inconsistente.
- [ ] Tooltip/box de explicação por indicador no quiz para reduzir erro de entrada (espec §6.8 e Anexo A §A.6).
- [ ] Eventos `quiz_iniciado` e `diagnostico_concluido` emitidos com `empresa_id`, `usuario_id` e timestamps.

## Fora de escopo (explicitamente)

- Setores Comércio e Serviços — só Indústria nesta onda. Comércio e Serviços entram em EPIC-005 (onda 2).
- Exportação em PDF do relatório — onda 2 (EPIC-006).
- Solicitação de análise primária de captação — onda 2 (EPIC-007).
- Compartilhamento do relatório por link público ou email — não previsto no MVP.
- Mecanismo de feedback 👍/👎 por recomendação — roadmap §1.4.
- Medianas setoriais (benchmark) — roadmap §2.1, depende de volume.
- Análise preditiva por IA — roadmap §2.3 (v2.0).
- Cálculo automático da análise de captação — roadmap §2.2 (v1.1).
- Edição do quiz após cálculo (correção de dado errado) — pode ser uma estória interna se sobrar tempo, senão fica para a onda 2.

## Referências da especificação

- `defonline-docs/especificacao/V2/especificacao-funcional.md` §4.1 (cadastro empresarial e contexto), §4.5 (motor de cálculo dos 14 indicadores), §4.7 (relatório), §4.7.1 (algoritmo do Resumo Executivo).
- `defonline-docs/especificacao/V2/especificacao-funcional.md` Seção 6 (decisões abertas — verificar item 6.4 expiração de rascunho, 6.6 validações cruzadas, 6.7 aviso PL, 6.8 tooltips, 6.10 trial).
- `defonline-docs/especificacao/V2/anexos/anexo-A-campos-quiz.md` (campos completos do quiz).
- `defonline-docs/especificacao/V2/anexos/anexo-F-matriz-recomendacoes-dez2025.md` (matriz vigente — recortar coluna Indústria).
- `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §1 (uptime, latência p95), §9.2 (testes do motor), §9.3 (validação externa pré-go-live).

## Dependências

- **Bloqueia:** EPIC-003 (histórico precisa de diagnósticos para listar).
- **Bloqueado por:** EPIC-000 (fundação) + EPIC-001 (Empresa Analisada cadastrada).
- **Decisões arquiteturais necessárias:** estratégia de versionamento do motor de cálculo (a matriz e as fórmulas vão evoluir — espec I.1.2); padrão de geração/renderização do relatório (decisão do Arquiteto); idempotência do cálculo (mesmo input → mesmo output, para reprocessamento); persistência de diagnóstico para o EPIC-003.

## Estórias

Decomposição vertical em **12 estórias** organizadas em **5 fatias verticais (V1 → V5)** — todas alocadas à **SPRINT-2026-W25** (sprint longa de 5 semanas com 5 checkpoints internos; ver `sprints/SPRINT-2026-W25.md`). O princípio de fatiamento segue o orientado no Fluxo B (PDR-001): primeira fatia entrega valor mínimo (motor com 7 indicadores essenciais + relatório minimalista); fatias seguintes completam o escopo. **Gate kill-switch formal no Checkpoint 2** se a V2 não fechar.

### V1 — Vertical mínima de valor (Semana 1 — Checkpoint 1)

Goal incremental: caminho feliz ponta-a-ponta vivo em homologação. Quiz reduzido + motor com 7 indicadores essenciais + relatório minimalista. Sem matriz, sem Resumo Executivo, sem tooltips.

- **STORY-026** — SPIKE/ADR: estratégia de versionamento do motor + persistência idempotente do diagnóstico (Arquiteto, S-M).
- **STORY-027** — Quiz de Indústria: formulário com 23 campos do Anexo A, máscaras, validações por campo, rascunho persistido 90 dias (Programador, L).
- **STORY-028** — Motor V1: 7 indicadores essenciais (Margem Bruta, Margem Líquida, Dívida Líq./EBITDA, NCG/Vendas, PMR, PMC, Ciclo Financeiro) + NCG absoluto informativo sem farol + ≥ 10 casos/fórmula (Programador, L).
- **STORY-029** — Relatório web minimalista dentro do app shell, glossário inline, baseline de p95 (Programador, M).

### V2 — Completar 14 indicadores e Resumo Executivo (Semana 2 — Checkpoint 2 / KILL-SWITCH)

Goal incremental: motor completo dos 14 indicadores + Resumo Executivo determinístico no topo do relatório.

- **STORY-030** — Motor V2: completar os 7 indicadores restantes (Margem EBITDA, Despesas Fin./EBITDA, Fontes PC/PL, Giro do Ativo, PME, Inadimplência, Ciclo Operacional) + ajuste de faixas de farol Indústria; bump para `motor_version = 1.1.0` (Programador, L).
- **STORY-031** — Resumo Executivo §4.7.1: algoritmo determinístico, sem IA, com veredito saudável/atenção/alerta + até 2 negativos + 1 positivo, truncamento ~80 chars, fallback fixo (Programador, M).

### V3 — Camada qualitativa: matriz DEZ/2025 + tooltips (Semana 3 — Checkpoint 3)

Goal incremental: mensagens textuais por indicador vindas da matriz + box de explicação por campo do quiz.

- **STORY-032** — Matriz DEZ/2025: recomendações por indicador × farol filtradas para Indústria (Anexo F), em config PHP versionado, snapshot no momento do cálculo (Programador, M).
- **STORY-033** — Tooltips/box de explicação por indicador no quiz (§6.8): 23 textos curtos consolidados pelo PO antes do Dia 1 da Semana 3 (Programador, S).

### V4 — Robustez: validações cruzadas + instrumentação (Semana 4 — Checkpoint 4)

Goal incremental: produto pronto para validação externa formal. Inconsistências de input disparam alertas acionáveis, eventos analíticos rodam, p95 ≤ 3s confirmado.

- **STORY-034** — Validações cruzadas DRE × Balanço (§6.6): alertas não-bloqueantes, lista canônica fechada pelo PO no Dia 1 da Semana 4 (default mínimo: 3 regras) (Programador, M).
- **STORY-035** — Eventos analíticos `quiz_iniciado` e `diagnostico_concluido` com `empresa_id`, `usuario_id`, `motor_version`, `matrix_version`, `timestamp` — alimentam D2 (Ativação) do north star (Programador, S).

### V5 — Validação externa + handoff técnico (Semana 5 — Checkpoint 5 / FECHAMENTO)

Goal incremental: EPIC-002 `done` sob ótica técnica. Validação externa do motor aprovada, validação interna independente aprovada, pacote de handoff entregue para comercial/implantação tocar o beta.

- **STORY-036** — Validação externa do motor (NRF §9.3): parecer técnico de especialista financeiro contratado pelo PO; gate técnico de qualidade antes de liberar uso por usuário real (Validador externo, M-L, não-código).
- **STORY-037** — Validação final EPIC-002 (interna) + smoke E2E em homologação + pacote de handoff em `handoff/README.md` para comercial/implantação operar o beta (Validador, M).

### Escopo fora desta sprint (handoff para comercial/implantação)

Por decisão explícita do PO em 2026-05-25 (registrada na `sprints/SPRINT-2026-W25.md`), as atividades abaixo **não** integram esta sprint e serão planejadas em conversa separada com os times de comercial e implantação:

- Recrutamento dos Robertos do beta fechado (alvo ≥ 10 Robertos por `epic.md`).
- Operação do convite, onboarding humano, suporte ao Roberto durante os primeiros usos.
- Coleta estruturada de NPS após o 1º diagnóstico.
- Entrevistas qualitativas com cada Roberto (mitigação do "Risco de hipótese" registrado em `current-wave.md`).
- Janela de observação de 60 dias do beta com leitura semanal das métricas D2/D3.
- Comunicação pública sobre o lançamento do beta.

O critério da seção **"Definição de épico concluído"** abaixo — *"≥ 10 Robertos do beta com diagnóstico concluído + NPS médio coletado"* — permanece como meta da WAVE-2026-01, executada por comercial/implantação após o handoff técnico, e aparece nos status reports da onda. O EPIC-002 fecha como `done` sob ótica técnica quando STORY-037 entrega o pacote de handoff.

## Validação final

Critérios em `validation/checklist.md` (a criar — vai exigir validação externa de especialista conforme NRF §9.3 antes do beta com Robertos reais). Relatório do validador em `validation/report.md`.

**Definição de épico concluído:** todas as estórias `done` + relatório do validador `approved` + validação externa do motor aprovada + ≥ 10 Robertos do beta com diagnóstico concluído em homologação + NPS médio coletado.

## Histórico

- 2026-05-20 — Criado como draft junto com a abertura da WAVE-2026-01.
- 2026-05-25 — **Épico promovido para `active`**. PO decompôs em 12 estórias (STORY-026 a STORY-037) organizadas em 5 fatias verticais (V1 → V5) e alocou todas à **SPRINT-2026-W25** (sprint longa de 5 semanas, 2026-05-25 → 2026-07-03, com 5 checkpoints internos e gate kill-switch no Checkpoint 2). Decisão explícita do PO de tratar o EPIC-002 "sob ótica técnica" — recrutamento de Robertos, NPS e operação do beta de 60 dias ficam fora da sprint e serão planejados com comercial/implantação após o handoff técnico (STORY-037). Estórias V1 (026, 027, 028, 029) promovidas para `ready`; V2-V5 ficam `draft` até promoção do PO na véspera de cada checkpoint (princípio Kanban de limitar WIP visível).
