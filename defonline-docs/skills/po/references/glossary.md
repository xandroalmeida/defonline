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
| **DDR (Design Decision Record)** | Decisão de design durável registrada (afeta múltiplas telas, define padrão, ou é cara de reverter). Feita pelo Designer. Modelo: Designer propõe, humano aprova explicitamente (análogo a ADR). Vive em `decisions/ddr/DDR-XXX-*.md`. |
| **Designer** | 5º papel do método (habilitado em PDR-002). Decide UX/UI das telas dentro do que o PO especificou; mantém o Design System vivo; produz screen specs mobile-first; trabalha em paralelo com o Programador na mesma estória (`requires_design: true`). Não decide stack (Arquiteto), CA (PO) nem implementação (Programador). |
| **Screen spec** | Spec de tela versionada produzida pelo Designer para cada estória de UI. Cobre layout mobile e desktop, todos os estados aplicáveis (vazio, loading, erro, sucesso, sem permissão), microcopy completo e identificadores estáveis para E2E. Vive em `design/screens/STORY-XXX-*.md`. |
| **Design System (DS)** | Vocabulário visual e de interação compartilhado pelas telas. Canônico em `especificacao/V2/design-system.md`; vive (evolução) em `design/system/`. Mantido pelo Designer; mudanças de fundação visual via DDR. |
| **`requires_design`** | Campo opcional do frontmatter de estória (schema v2). Quando `true`, sinaliza que a estória envolve UI nova/alterada e o Designer entra em paralelo com o Programador desde o início. |
| **TDD** | Test-Driven Development — escrever teste antes do código. Inegociável aqui. |
| **E2E** | End-to-End — teste que percorre o sistema do ponto de vista do usuário (browser real para FE, cliente HTTP para API). |
| **Homologação** | Ambiente equivalente a produção mas isolado, usado para validação. Existe desde o dia 1. |
| **Foundation epic** | Épico zero do projeto: pipeline + ambiente + "hello world" deployado. Pré-requisito de qualquer funcionalidade. |
| **Index** | `project-state/index.json` — única fonte de verdade queryable do estado do projeto. |
| **Outcome** | Resultado observável para o usuário (≠ output, que é o que o time produziu). PO pensa em outcomes. |
