---
pdr_id: PDR-001
slug: escopo-wave-2026-01
title: Escopo da WAVE-2026-01 — Hipótese do Roberto
status: accepted
decided_at: 2026-05-20
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-000, EPIC-001, EPIC-002, EPIC-003]
related_adrs: []
---

# PDR-001 — Escopo da WAVE-2026-01 — Hipótese do Roberto

## Contexto

O reset técnico de 19/05/2026 descartou a primeira tentativa de implementação. As regras de negócio da especificação V2.5 permanecem válidas, mas decisões de stack, infraestrutura e fundação técnica precisam ser refeitas do zero. A herança de PostgreSQL como banco e TDD+E2E como exigência segue, sujeita à ratificação formal por ADR no spike inicial de arquitetura.

A primeira onda do projeto pós-reset precisa responder a uma pergunta única antes de qualquer outra: **o produto entrega valor real ao dono de pequena indústria (persona Roberto)?** A visão recém-aprovada cobre três públicos (donos, contadores, parceiros financeiros), mas a onda 1 elege Roberto como persona alvo primária para concentrar foco. O north star aprovado — MPEs ativas com diagnóstico em 90 dias, alvo de 500 em 12 meses pós go-live — exige que a onda 1 deixe pelo menos a fundação que permitirá medi-lo.

## Opções consideradas

### Opção 1 — Roberto puro: só Indústria, só web (recomendada)

- **Descrição.** Onda entrega Foundation técnica, cadastro mínimo de Usuário e Empresa Analisada, quiz/motor/relatório web cobrindo apenas o setor Indústria (setor de Roberto), e histórico básico de diagnósticos. Sem cobrança, sem PDF, sem solicitação de captação, sem hotsite público (acesso por convite no beta fechado). API RFB com fallback manual já desde a onda 1.
- **Prós.** Escopo enxuto (4 épicos, ~12–14 semanas), ciclo de feedback curto, hipótese central testada exatamente no perfil que mais paga a tese, retrabalho mínimo se a hipótese falhar. Permite mensurar o north star desde o primeiro diagnóstico em produção.
- **Contras.** Joana e Marcos não conseguem usar com sentido completo; aprendizado sobre multi-setor adiado para a onda 2. Receita zero até depois do go-live MVP.

### Opção 2 — Multi-setor sem PDF

- **Descrição.** Mesma estrutura da Opção 1, mas com cobertura dos três setores (Indústria, Comércio, Serviços) já na onda 1. Relatório só web, sem PDF e sem captação.
- **Prós.** Beta mais amplo, testa hipótese também com Joana (Comércio); produto comercializável depois com menos custo de adaptação.
- **Contras.** Triplica o trabalho da matriz de recomendações e dos casos de teste do motor; ciclo de feedback mais longo (~16 semanas); a hipótese central de Roberto fica diluída entre três perfis.

### Opção 3 — Multi-setor com PDF e captação

- **Descrição.** Tudo da Opção 2 + exportação em PDF do relatório + solicitação manual de captação (formulário + SLA interno de 5 dias).
- **Prós.** Quase MVP completo ao fim da onda 1, sobrando apenas a comercialização para a onda 2.
- **Contras.** Onda longa (~20 semanas), grande superfície funcional antes de ter aprendizado de mercado, PDF e RFB integrada como caminho preferencial desviam foco da hipótese central, risco alto de chegar ao fim com produto pronto e hipótese ainda não validada.

### Status quo

Não há status quo aplicável: o reset técnico exige começar a fundação. Não fazer nada agora significa permanecer sem implementação, o que é incompatível com o princípio de entrega em produção desde o dia 1.

## Decisão

> **Optamos pela Opção 1 — Roberto puro: só Indústria, só web.**

A WAVE-2026-01, denominada "Hipótese do Roberto", é composta por quatro épicos sequenciais — Foundation, Cadastro mínimo, Diagnóstico para Indústria e Histórico básico — entregando ao fim, em ambiente de homologação acessível por convite, o ciclo completo cadastro → quiz → relatório → histórico para uma única persona (Roberto) em um único setor (Indústria), sem cobrança, sem PDF e sem solicitação de captação.

## Justificativa

A escolha alinha-se diretamente à visão recém-aprovada e à hipótese central de produto declarada em `north-star.md`. O produto só justifica investimento maior se entregar valor real ao usuário-alvo; a Opção 1 testa isso no perfil que mais exige do diagnóstico (decisão de alto valor) e no setor mais demandante (Indústria, com maior complexidade de indicadores como NCG e PME). Se Roberto disser "isso resolve meu problema", a expansão para Joana (Comércio) e o caminho do contador Marcos passam a ser exercício de adaptação, não de validação. Se Roberto disser "não", aprendemos cedo, com investimento contido.

As Opções 2 e 3 são tentadoras pela amplitude, mas confundem cobertura com confiança: cobrir três setores antes de saber se o produto entrega valor é optimization sem hipótese. A Opção 3, em particular, antecipa custo de PDF, integração RFB profunda e captação manual antes do primeiro sinal de mercado — risco alto frente ao princípio de entrega em produção desde o dia 1, que exige aprendizado, não acabamento.

## Consequências

### Positivas

- Ciclo curto de feedback (~12–14 semanas até o relatório do Roberto em homologação).
- Foco operacional claro: time não decide entre frentes na onda 1.
- North star mensurável desde o primeiro diagnóstico em produção.
- Onda 2 entra com aprendizado real sobre o que ajustar antes de comercializar.

### Negativas / trade-offs aceitos

- Roberto recebe relatório web mas não consegue baixar PDF na onda 1.
- Joana (Comércio) não consegue usar até a onda 2.
- Marcos (contador, Pro) não consegue usar até onda mais à frente.
- Nenhuma receita até o fim da onda 2; sustenta-se com o orçamento pré-aprovado da fase de validação.
- Solicitação de captação, parte central da dor do Roberto, fica adiada — mitigação: deixar claro no relatório que o recurso virá, evitando frustração.
- Beta fechado por convite implica curadoria manual dos primeiros Robertos — esforço operacional do PO/EBP.

### Para o time técnico

- ADRs que esta decisão pode demandar (todas saem do EPIC-000 como spikes paralelas, uma ADR por spike): stack de linguagem/framework, padrão de arquitetura macro, esquema CI/CD, plataforma de hospedagem, ferramenta de observabilidade (logs/métricas/tracing), estratégia de testes automatizados (unitário + E2E), ratificação formal de PostgreSQL como banco principal, estratégia de migração de schema, padrão de autenticação/sessão, modelo de captura de eventos para north star, política de ambiente local de desenvolvimento.
- Impacto em épicos: EPIC-000 (Foundation) entra como pré-requisito de todos os outros; EPIC-001/002/003 são vertical slicing dentro da experiência do Roberto.

## Sinais de revisão

Esta decisão deve ser reconsiderada — via novo PDR que a supersede — caso:

- Roberto, no beta fechado, indique que o produto **não** resolve o problema dele (NPS sustentado abaixo de 20 ou pesquisa qualitativa convergente). Aí: revisitar a hipótese central, não só o escopo da onda.
- Surja pressão comercial irrefutável (parceiro patrocinador, oportunidade de canal contábil) que demande PDF ou multi-setor antes da onda 2 fechar.
- O ciclo da onda exceda 20 semanas — aí a decisão de "enxuta" foi ilusória e o real problema é outro (escopo mal dimensionado, débito acumulando, equipe abaixo do esperado). Force revisão da decomposição.

## Nota sobre o schema do index.json

Esta sessão estendeu o `index.json` com uma seção `product` (referencia `product/vision.md`, `product/personas.md`, `product/north-star.md`), aditiva ao esquema documentado em `references/indexing.md`. Como é mudança aditiva (não breaking), não houve bump de `version`. Caso novas extensões sejam necessárias, o PDR seguinte ou um PDR dedicado de schema documenta a evolução completa.
