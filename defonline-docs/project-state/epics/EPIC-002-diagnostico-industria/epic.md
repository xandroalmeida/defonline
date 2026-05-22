---
epic_id: EPIC-002
slug: diagnostico-industria
title: Diagnóstico Econômico-Financeiro para Indústria
wave: WAVE-2026-01
status: draft
owner_role: po
created_at: 2026-05-20
updated_at: 2026-05-20
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

(A ser detalhado quando o EPIC-001 estiver bem encaminhado. Princípios para o Fluxo B futuro: dividir verticalmente — primeira estória entrega quiz + motor com ~7 indicadores essenciais + relatório minimalista; estórias seguintes completam para 14 indicadores, adicionam Resumo Executivo, refinam recomendações da matriz DEZ/2025, adicionam tooltips e validações cruzadas. **Forte sinal de quebra se a estimativa do épico exceder 6 semanas** — vide PDR-001 sinais de revisão.)

## Validação final

Critérios em `validation/checklist.md` (a criar — vai exigir validação externa de especialista conforme NRF §9.3 antes do beta com Robertos reais). Relatório do validador em `validation/report.md`.

**Definição de épico concluído:** todas as estórias `done` + relatório do validador `approved` + validação externa do motor aprovada + ≥ 10 Robertos do beta com diagnóstico concluído em homologação + NPS médio coletado.

## Histórico

- 2026-05-20 — Criado como draft junto com a abertura da WAVE-2026-01.
