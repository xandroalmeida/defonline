# Glossário do método

Termos que o PO usa e o agente deve entender uniformemente.

| Termo | Significa |
|---|---|
| **Onda (wave)** | Ciclo de planejamento de algumas semanas a alguns meses com um objetivo único de negócio. Contém épicos. |
| **Épico (epic)** | Conjunto de estórias que entrega valor visível ao usuário. Tem outcome, métrica e entregável demonstrável. Vive em `epics/EPIC-XXX-*/`. |
| **Estória (story)** | Unidade de trabalho executável em uma sessão por um agente. Atravessa o stack verticalmente. Vive em `epics/.../stories/STORY-XXX-*.md`. |
| **Sprint** | Intervalo fixo (1–2 semanas) que agrupa estórias com um goal compartilhado. |
| **Spike** | Estória especial de investigação — geralmente endereçada ao Arquiteto — para destravar uma decisão antes de implementação. |
| **Vertical slicing** | Quebrar trabalho por fatia de funcionalidade (UI + API + banco para um pedaço pequeno) em vez de por camada (toda UI, depois toda API). |
| **CA (Critério de Aceite)** | Asserção testável que define se a estória cumpriu seu objetivo. |
| **DoD (Definition of Done)** | Checklist de qualidade que define "estória pronta" — comum a todas as estórias. |
| **PDR (Product Decision Record)** | Decisão de produto registrada. Feita pelo PO. |
| **ADR (Architecture Decision Record)** | Decisão arquitetural registrada. Feita pelo Arquiteto. |
| **IDR (Implementation Decision Record)** | Decisão de implementação de baixo nível com impacto futuro. Feita pelo Programador. |
| **TDD** | Test-Driven Development — escrever teste antes do código. Inegociável aqui. |
| **E2E** | End-to-End — teste que percorre o sistema do ponto de vista do usuário (browser real para FE, cliente HTTP para API). |
| **Homologação** | Ambiente equivalente a produção mas isolado, usado para validação. Existe desde o dia 1. |
| **Foundation epic** | Épico zero do projeto: pipeline + ambiente + "hello world" deployado. Pré-requisito de qualquer funcionalidade. |
| **Index** | `project-state/index.json` — única fonte de verdade queryable do estado do projeto. |
| **Outcome** | Resultado observável para o usuário (≠ output, que é o que o time produziu). PO pensa em outcomes. |
