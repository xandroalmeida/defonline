---
epic_id: EPIC-003
slug: historico-basico
title: Histórico básico de diagnósticos
wave: WAVE-2026-01
status: draft
owner_role: po
created_at: 2026-05-20
updated_at: 2026-05-20
target_completion: 2026-08-26
---

# EPIC-003 — Histórico básico de diagnósticos

## Por que existimos (problema do usuário)

Diagnóstico único pode ser curiosidade. Diagnóstico recorrente é hábito. Sem histórico, Roberto não tem como saber se a marcenaria está melhorando ou piorando — e o produto perde o motivo principal para ele voltar. A recorrência (driver D3 da árvore do north star) só acontece se houver razão para voltar; o histórico é essa razão.

Adicionalmente, o gatilho de uso de Roberto é não-mensal (3–4 diagnósticos/ano). Sem histórico que sobreviva entre sessões com meses de intervalo, o diagnóstico vira evento isolado, e Roberto perde a sensação de continuidade.

## Resultado esperado (outcome)

Ao fim do EPIC-003, Roberto entra na conta dele meses depois do último diagnóstico, vê a lista cronológica de diagnósticos da marcenaria nos últimos 12 meses rolantes, abre qualquer diagnóstico anterior em formato idêntico ao do dia em que foi gerado, e visualiza um comparativo lado a lado de dois diagnósticos escolhidos com indicação visual da direção de cada um dos 14 indicadores (melhorou, piorou, manteve).

## Métrica de sucesso (como saberemos que funcionou)

- **Métrica primária (driver D3 — Recorrência da árvore do north star):** ≥ 40% dos Usuários ativados retornam para 2º+ diagnóstico em 180 dias. Janela: D+180 após o 1º diagnóstico — **não fecha dentro desta onda**; aparece nos status reports da onda 2 e 3.
- **Métrica de qualidade:** zero perdas de diagnóstico antigo (todo diagnóstico salvo em homologação continua acessível pelo mesmo Usuário); fidelidade visual completa entre o relatório do dia e o relatório reaberto via histórico.

## Entregável visível no fim do épico

- [ ] Lista cronológica de diagnósticos da Empresa Analisada nos últimos 12 meses (decrescente por data).
- [ ] Diagnóstico anterior reaberto exibe o relatório idêntico ao que Roberto viu no dia (mesmos cálculos, mesmas recomendações da versão da matriz vigente naquele momento — versionamento da matriz em ADR).
- [ ] Comparativo lado a lado de dois diagnósticos selecionados, com seta/cor por indicador indicando direção da variação.
- [ ] Evento `diagnostico_visualizado` e `comparativo_aberto` emitidos para alimentar D3.

## Fora de escopo (explicitamente)

- Gráficos de tendência de 6 / 12 meses — roadmap §1.2 (v1.2).
- Alertas automáticos de deterioração entre diagnósticos — roadmap §1.2 (v1.1).
- Metas por indicador definidas pelo Usuário — roadmap §1.2 (v1.1).
- Exportação do histórico em CSV / Excel — não previsto no MVP.
- Histórico além de 12 meses rolantes — espec §4.8 mantém a janela.
- Compartilhamento do histórico com terceiros (contador, sócio) — roadmap §1.1 (v1.2).
- Comparativo de 3+ diagnósticos simultâneos — onda 1 entrega apenas par.

## Referências da especificação

- `defonline-docs/especificacao/V2/especificacao-funcional.md` §4.8 (histórico e comparativo).

## Dependências

- **Bloqueia:** nada nesta onda. Pavimenta o caminho para evolução de retenção na onda 2/3.
- **Bloqueado por:** EPIC-002 (precisa do diagnóstico para listar).
- **Decisões arquiteturais necessárias:** modelo de versionamento dos diagnósticos (relatório precisa ser fiel ao do dia, mesmo se a matriz tiver evoluído — ver espec §I.1.2); estratégia de paginação se houver volume alto de diagnósticos (provavelmente não na onda 1).

## Estórias

(A ser detalhado quando o EPIC-002 estiver bem encaminhado. Princípios para o Fluxo B futuro: vertical slicing — primeira estória entrega listagem; segunda entrega reabertura fiel do diagnóstico anterior; terceira entrega comparativo. Última estória é validação assumida pela skill `validador`.)

## Validação final

Critérios em `validation/checklist.md` (a criar). Relatório do validador em `validation/report.md`.

**Definição de épico concluído:** todas as estórias `done` + relatório do validador `approved` + Roberto com 2+ diagnósticos em homologação capaz de comparar lado a lado.

## Histórico

- 2026-05-20 — Criado como draft junto com a abertura da WAVE-2026-01.
