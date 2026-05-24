# O índice do projeto (`index.json`)

`index.json` é o "banco de dados" queryable do projeto. Ele é a **única** fonte de verdade para perguntas tipo "o que está em andamento?" — os `.md` carregam o conteúdo descritivo, o índice carrega o estado.

> **Schema atual: version 2** (migração `1 → 2` documentada em PDR-002, que habilitou o papel Designer adicionando `decisions.ddr[]`, `design.screens[]` e `requires_design` em `stories[]`).

## Esquema

```jsonc
{
  "version": 2,
  "generated_at": "YYYY-MM-DDTHH:MM:SSZ",
  "project": {
    "name": "DEFOnline",
    "current_wave": "WAVE-2026-01",
    "current_sprint": "SPRINT-2026-W21"
  },

  "waves": [
    {
      "id": "WAVE-2026-01",
      "title": "MVP de diagnóstico",
      "status": "active",            // planned | active | closed
      "goal": "Validar que MPEs conseguem subir planilha e ver diagnóstico",
      "start_date": "2026-05-20",
      "epic_ids": ["EPIC-000", "EPIC-001", "EPIC-002"]
    }
  ],

  "epics": [
    {
      "id": "EPIC-001",
      "slug": "cadastro-empresa",
      "title": "Cadastro de empresa",
      "wave": "WAVE-2026-01",
      "status": "in_progress",        // draft | ready | in_progress | in_review | done | abandoned
      "path": "epics/EPIC-001-cadastro-empresa/epic.md",
      "story_ids": ["STORY-001", "STORY-002", "STORY-003"],
      "validation_report": null,      // preenchido pelo validador quando aprovar
      "created_at": "2026-05-20",
      "updated_at": "2026-05-20"
    }
  ],

  "stories": [
    {
      "id": "STORY-001",
      "epic_id": "EPIC-001",
      "title": "Cadastro mínimo: nome e CNPJ",
      "type": "implementation",
      "target_role": "programador",      // programador | arquiteto | validador | designer
      "requires_design": true,           // adicionado em schema v2 — sinaliza paralelismo Designer↔Programador
      "design_screen_id": "SCREEN-STORY-001-cadastro-minimo", // opcional — preenchido quando há spec de tela
      "status": "in_progress",
      "owner_agent": "claude-session-abc",
      "sprint_id": "SPRINT-2026-W21",
      "path": "epics/EPIC-001-cadastro-empresa/stories/STORY-001-cadastro-minimo.md",
      "blocked_by": [],
      "blocks": ["STORY-002"],
      "created_at": "2026-05-20",
      "updated_at": "2026-05-20"
    }
  ],

  "sprints": [
    {
      "id": "SPRINT-2026-W21",
      "wave": "WAVE-2026-01",
      "status": "active",
      "start_date": "2026-05-18",
      "end_date": "2026-05-31",
      "story_ids": ["STORY-001", "STORY-002"]
    }
  ],

  "decisions": {
    "pdr": [
      {
        "id": "PDR-001",
        "title": "Escopo do MVP é diagnóstico só, sem dashboard",
        "status": "accepted",
        "path": "decisions/pdr/PDR-001-mvp-diagnostico.md",
        "decided_at": "2026-05-20"
      }
    ],
    "adr": [],
    "idr": [],
    "ddr": [                              // adicionado em schema v2 — Design Decision Records (Designer)
      {
        "id": "DDR-001",
        "title": "Navegação principal lateral persistente",
        "status": "accepted",             // proposed | accepted | superseded | rejected | deferred
        "path": "decisions/ddr/DDR-001-nav-lateral-persistente.md",
        "decided_at": "2026-06-01",
        "decided_by": "designer",
        "approved_by": "Alexandro",
        "scope": "transversal",
        "affects_screens": []
      }
    ]
  },

  "design": {                             // adicionado em schema v2 — artefatos do Designer
    "screens": [
      {
        "id": "SCREEN-STORY-XXX-<slug>",
        "story": "STORY-XXX",
        "epic": "EPIC-XXX",
        "status": "ready",                // draft | ready | in_implementation | shipped | superseded
        "path": "design/screens/STORY-XXX-<slug>.md",
        "viewports": ["mobile", "desktop"],
        "related_ddrs": [],
        "ds_components_used": [],
        "owner_designer": "designer",
        "created_at": "YYYY-MM-DD",
        "updated_at": "YYYY-MM-DD"
      }
    ],
    "system": {                           // ponteiros para o Design System vivo (herdado de especificacao/V2/design-system.md)
      "root_path": "design/system/",
      "tokens": "design/system/tokens.md",
      "components": "design/system/components.md",
      "patterns": "design/system/patterns.md",
      "voice_and_tone": "design/system/voice-and-tone.md",
      "canonical_reference": "../especificacao/V2/design-system.md"
    }
  },

  "reports": [
    {
      "date": "2026-05-20",
      "path": "reports/status-2026-05-20.md"
    }
  ]
}
```

## Invariantes (regras que sempre valem)

1. Todo `epic.id` em `epics[]` aparece em exatamente uma `wave.epic_ids`.
2. Todo `story.id` em `stories[]` tem `epic_id` que existe em `epics[]`.
3. Todo `story.id` em `epic.story_ids` existe em `stories[]`.
4. `story.blocked_by` e `story.blocks` referenciam IDs existentes.
5. Um épico só pode ser `done` se todas as suas estórias estão `done` E `validation_report` está setado e aprovado.
6. Uma estória só pode ser `done` se nenhum `blocked_by` está aberto.
7. `updated_at` é atualizado em toda alteração.
8. `path` é relativo a `project-state/`.
9. **(v2)** Se `story.requires_design == true`, deve existir entrada em `design.screens[]` com `story == story.id` antes do `status` da estória passar de `in_progress` para `in_review`. (Garantia de que UI não vai para revisão sem spec.)
10. **(v2)** `story.design_screen_id`, quando preenchido, aponta para `design.screens[].id` existente.
11. **(v2)** `decisions.ddr[*].status: accepted` exige `approved_by` preenchido (aprovação humana explícita — análogo a ADR).
12. **(v2)** `design.screens[*].status: ready` é pré-condição para o Programador codificar UI (modelo paralelo descrito em `designer/references/collaboration-with-developer.md`).

## Como o PO mantém

Toda vez que você (PO):

- **Cria** um épico/estória/sprint/decisão → adiciona entry no índice.
- **Move** estória para outro sprint → atualiza `story.sprint_id` e os `story_ids` dos sprints envolvidos.
- **Marca** algo como `done` → muda `status`, atualiza `updated_at`, verifica invariantes 5, 6 e 9.
- **Cria** PDR → adiciona em `decisions.pdr[]`.

E toda vez que o Designer:

- **Cria** DDR → adiciona em `decisions.ddr[]` (no schema vigente; sem editar o schema).
- **Cria** screen spec → adiciona em `design.screens[]` referenciando a estória.
- **Move** spec entre estados (`draft → ready → in_implementation → shipped`) → atualiza `design.screens[*].status` e `updated_at`.

Quando estiver em dúvida, releia o arquivo `.md` correspondente — a verdade descritiva está nele. O índice só reflete metadados.

## Como agentes leem

Agente programador típico inicia perguntando:

> "Qual é a próxima estória `ready` com `target_role: programador` no sprint atual cujas `blocked_by` estão todas `done`?"

Agente designer típico inicia perguntando:

> "Quais estórias `ready` ou `in_progress` no sprint atual têm `target_role: designer` (dono direto) **ou** `requires_design: true` com `design_screen_id` ainda sem entrada em `design.screens[]` com `status: ready` (paralelismo Designer↔Programador)?"

A resposta vem de um filtro simples sobre `index.json`. Por isso o esquema é simples e plano — não é Postgres, é um JSON pequeno.

## Como humanos leem

Um humano abrindo o índice deveria, em 30 segundos, responder:

- Em que onda estamos? (`project.current_wave`)
- Quantos épicos abertos? (filtrar `epics` por `status != done && != abandoned`)
- O que está em revisão? (filtrar `epics` por `status == in_review`)
- Qual o último status report? (último item de `reports[]`)

Se isso não está fácil de ler, o índice está com algo errado — provavelmente desatualizado.

## Migrações futuras

Se o esquema precisar mudar, bump `version` e documente a mudança em um PDR. Não quebre o esquema silenciosamente.

### Migrações já realizadas

- **v1 → v2** (PDR-002, 2026-05-24): habilitação do papel Designer. Adicionados: `decisions.ddr[]`, `design.screens[]`, `design.system{}`, e em cada `story[]` os campos `requires_design: bool` e `design_screen_id: string?`. Enum `target_role` ganhou `designer`. Novas invariantes 9–12.
