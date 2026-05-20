# project-state — estado vivo do DEFOnline

Esta pasta é o **banco de dados** do projeto DEFOnline. Ela é gerenciada principalmente pelo Product Owner (skill `po`), com contribuições do Arquiteto, Programador e Validador conforme o workflow.

## Para humanos com pressa

- Visão geral em tempo real: leia `index.json` (é pequeno e legível).
- Último status report: o arquivo mais recente em `reports/`.
- Onda atual: `roadmap/current-wave.md`.

## Estrutura

```
project-state/
├── index.json                ← fonte de verdade queryable do estado
├── README.md                 ← este arquivo
├── product/                  ← visão de produto consolidada
│   ├── vision.md
│   ├── personas.md
│   └── north-star.md
├── roadmap/                  ← onda atual + rascunho da próxima
│   ├── current-wave.md
│   └── next-wave.md
├── epics/                    ← um diretório por épico
│   └── EPIC-XXX-<slug>/
│       ├── epic.md
│       ├── stories/
│       │   └── STORY-XXX-<slug>.md
│       └── validation/
│           ├── checklist.md
│           └── report.md
├── sprints/                  ← sprints (intervalos fixos de execução)
│   └── SPRINT-YYYY-WNN.md
├── decisions/
│   ├── pdr/                  ← Product Decision Records (PO)
│   ├── adr/                  ← Architecture Decision Records (Arquiteto)
│   └── idr/                  ← Implementation Decision Records (Programador)
└── reports/                  ← status reports periódicos para humanos
    └── status-YYYY-MM-DD.md
```

## Quem mexe em quê

| Pasta | Quem escreve |
|---|---|
| `product/`, `roadmap/`, `sprints/`, `epics/*/epic.md`, `epics/*/stories/*.md`, `epics/*/validation/checklist.md`, `reports/`, `decisions/pdr/` | **PO** (skill `po`) |
| `decisions/adr/` | **Arquiteto** (skill `arquiteto`) |
| `decisions/idr/` | **Programador** (skill `programador`) |
| `epics/*/validation/report.md` | **Validador** (skill `validador`) |
| `epics/*/stories/*.md` (seção "Notas do agente" e frontmatter durante execução) | **Programador / Validador** (quem assumiu) |
| `index.json` | Quem fizer qualquer mudança nas pastas acima também atualiza o índice |

## Convenções de ID

- Épicos: `EPIC-001`, `EPIC-002`, ... — três dígitos, ordem global.
- Estórias: `STORY-001`, `STORY-002`, ... — ordem global do projeto, não por épico.
- Sprints: `SPRINT-YYYY-WNN` (ex: `SPRINT-2026-W21` para a semana ISO 21 de 2026).
- Ondas: `WAVE-YYYY-NN` (ex: `WAVE-2026-01` para a primeira onda de 2026).
- PDR/ADR/IDR: `PDR-001`, `ADR-001`, `IDR-001` — ordem global por categoria.

Sempre acompanhe o ID com um slug curto kebab-case (`EPIC-001-cadastro-empresa`).

## Como começar (primeira sessão de PO)

1. Carregar a skill `po`.
2. Definir a visão em `product/vision.md` (curto, 1 página).
3. Planejar a primeira onda (Fluxo A da skill).
4. Detalhar o EPIC-000 (Foundation: pipeline + ambiente + hello world deployado).
5. Detalhar o primeiro épico de funcionalidade.
6. Atualizar `index.json` e abrir SPRINT-... inicial.

## Vinculação com a documentação canônica

Os documentos canônicos da especificação vivem em `defonline-docs/especificacao/V2/` e **não** são duplicados aqui. Estórias e épicos **referenciam** esses arquivos por caminho — nunca copiam o conteúdo.

## Versionamento

`project-state/` vive dentro do repo git `defonline-docs/`. Todo histórico de planejamento, decisão e execução fica versionado. PRs em `project-state/` são bem-vindos quando uma decisão precisa de revisão de pares.
