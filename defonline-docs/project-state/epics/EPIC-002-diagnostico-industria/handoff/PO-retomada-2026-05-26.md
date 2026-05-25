---
artifact: po-session-handoff
written_by: po (alexandro)
written_at: 2026-05-25
target_session: próxima sessão do PO (≥ 2026-05-26)
status: ready-for-resume
---

# Retomada do PO — próxima sessão

> Documento de **handoff entre sessões** do mesmo papel (PO). Leia este primeiro ao abrir uma nova sessão, depois siga o "Prompt inicial sugerido" no final.

## Estado em uma frase

**SPRINT-2026-W25 — V1 e V2 fechadas (4 de 6 estórias core done).** Checkpoint 1 (V1: SPIKE + Quiz + Motor V1 + Relatório minimalista) e Checkpoint 2 (V2: Motor V2 com 14 indicadores + Resumo Executivo determinístico) **passaram**. Kill-switch do epic ficou para trás. Caminho feliz ponta-a-ponta vivo (precisa só do deploy de homol pra confirmar p95 ≤ 3s).

## Snapshot do projeto

- **Wave ativa:** WAVE-2026-01 ("Hipótese do Roberto") — `defonline-docs/project-state/roadmap/current-wave.md`.
- **Sprint ativa:** SPRINT-2026-W25 (sprint longa, 5 semanas, 2026-05-25 → 2026-07-03) — `defonline-docs/project-state/sprints/SPRINT-2026-W25.md`.
- **Epic ativo:** EPIC-002 ("Diagnóstico Econômico-Financeiro para Indústria") — `defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/epic.md`.
- **Estado machine-readable:** `defonline-docs/project-state/index.json` (sempre consultar para status atualizado das estórias).
- **Motor em produção (homol):** `motor_version = "1.2.0"`, `matrix_version = "dez-2025"`.

## Estórias do EPIC-002 — onde estão

| ID | Título | Fatia | Tamanho | Status |
|---|---|---|---|---|
| STORY-026 | SPIKE versionamento + persistência | V1 | S-M | ✅ done |
| STORY-027 | Quiz Indústria (23 campos) | V1 | L | ✅ done |
| STORY-028 | Motor V1 (7 indicadores) | V1 | L | ✅ done |
| STORY-029 | Relatório minimalista | V1 | M | ✅ done |
| STORY-030 | Motor V2 (14 indicadores) | V2 | L | ✅ done |
| STORY-031 | Resumo Executivo determinístico | V2 | M | ✅ done |
| **STORY-032** | **Matriz DEZ/2025 (recomendações por indicador)** | **V3** | **M** | **🟡 draft → pronta pra promover** |
| **STORY-033** | **Tooltips/box de explicação no quiz (§6.8)** | **V3** | **S** | **🟡 draft → depende de 23 textos do PO** |
| STORY-034 | Validações cruzadas DRE × Balanço (§6.6) | V4 | M | 🟡 draft |
| STORY-035 | Eventos analíticos (quiz_iniciado / diagnostico_concluido) | V4 | S | 🟡 draft |
| STORY-036 | Validação externa do motor (NRF §9.3) | V5 | M-L | 🟡 draft (não-código) |
| STORY-037 | Validação final EPIC-002 + handoff técnico | V5 | M | 🟡 draft |

## Próximos passos (em ordem de prioridade)

### Imediatos (antes de despachar V3)

1. **Deploy em homol + medir p95** (fecha F-NB-1 das STORY-029 e STORY-030 e STORY-031). Eu (PO) gravar screencast Checkpoint 2 (V1 + V2 visíveis).
2. **Mini-retro do Checkpoint 2** — registrar 3 lições aprendidas no `current-sprint.md`:
   - Bumps de `motor_version` em paralelo funcionaram (regra documentada nos briefings 030/031 segurou).
   - **PO não inventa regra:** invented "regra patrimonial" no briefing-031 que precisou ser cancelada. Reforçar princípio nos próximos briefings.
   - Pre-push da STORY-027 não rodou Dusk (bug pego pela 029). **Adicionar Dusk ao hook obrigatório** — abrir como estória S no próximo planning.

### Fatia V3 — Checkpoint 3 (semana 3 da sprint)

3. **STORY-033 precisa de input do PO antes de virar `ready`:** redigir os **23 tooltips/textos curtos de explicação por campo do quiz** (espec §6.8 + Anexo A §A.6). Cada texto: ≤ 200 chars, linguagem amigável (Roberto, não contador), exemplo concreto se útil. **Bloqueia 033** até esses textos estarem em algum doc tipo `epics/EPIC-002-diagnostico-industria/design/tooltips-anexo-a.md`. Estimo 1-2h de redação minha.
4. **STORY-032 (Matriz DEZ/2025) já pode ir agora** — não depende de input meu. Redigir briefing operacional do PO (mesmo padrão dos anteriores em `briefings/STORY-XXX-abertura.md`) destacando:
   - Anexo F é a fonte canônica (`defonline-docs/especificacao/V2/anexos/anexo-F-matriz-recomendacoes-dez2025.md`).
   - 14 indicadores × 3 setores × 3 faixas — mas EPIC-002 só Indústria → ~42 textos relevantes (14 × 1 setor × 3 faixas).
   - Snapshot no momento do cálculo: motor já injeta `mensagem` no snapshot do indicador (hoje placeholder genérico "Faixa verde."). STORY-032 substitui pelos textos reais.
   - Bump `motor_version` para `"1.3.0"` (MINOR — comportamento de output muda, mas semântica do veredito e estrutura JSON não mudam). Re-emitir 5 golden hashes.
   - `matrix_version` continua `"dez-2025"` (não muda — matriz é a mesma DEZ/2025; o que mudou é só que agora ela é lida em vez de placeholder).
   - Config em `config/motor/matriz/dez-2025.php` (PHP array versionado em código, conforme decisão da epic).
   - **Casos limite:** indicador com `farol='nenhum'` (NCG abs, Ciclo Operacional, ou indisponível) → `mensagem` continua como está (faixa fixa do NCG abs / texto informativo do Ciclo Op / motivo da indisponibilidade). Matriz não substitui esses casos.
   - Snapshots antigos (1.0.0, 1.1.0, 1.2.0) **não são recalculados** — continuam exibindo o texto que tinham no momento (placeholder ou nada). IDR-010 §sub-decisão 4.
5. **Promover STORY-032 para `ready` e despachar com briefing.**

### Fatia V4 — Checkpoint 4

6. **STORY-034 (Validações cruzadas DRE × Balanço)** — eu (PO) preciso fechar a **lista canônica de regras** antes de despachar. Default mínimo: 3 regras. Olhar `epic.md` linha 99 e espec §6.6.
7. **STORY-035 (Eventos analíticos)** — payload já está bem definido na epic.md. Briefing direto.

### Fatia V5 — Checkpoint 5 (fechamento)

8. **STORY-036 (Validação externa)** — não-código. Contratar especialista financeiro externo conforme NRF §9.3. **Operacional do PO**, não passa por Programador.
9. **STORY-037 (Validação final interna + handoff técnico)** — Validador faz; PO orquestra.

## Pendências exclusivas do PO (não bloqueiam Programador imediato)

- [ ] **Redigir os 23 tooltips/textos do Anexo A** → bloqueia STORY-033.
- [ ] **Fechar lista de regras DRE × Balanço (≥ 3)** → bloqueia STORY-034.
- [ ] **Iniciar contratação do validador externo** (NRF §9.3) → bloqueia STORY-036; tem lead time longo, melhor já começar.
- [ ] **Deploy em homol + screencast Checkpoint 2** → cheque F-NB-1 das stories 029/030/031.
- [ ] **Mini-retro do Checkpoint 2** registrada em `current-sprint.md`.
- [ ] **Considerar abrir estória S** para "Dusk no pre-push obrigatório" — débito pego pela 029.

## Princípios que esta sprint reforçou (importantíssimo lembrar)

1. **PO NÃO inventa regra de negócio.** Aconteceu na STORY-031 (regra patrimonial inventada no briefing) — Programador pegou, PO cancelou job, briefing revisado retroativamente. A spec é a fonte autoritativa. Se não está na spec, não existe. Se eu acho que faz sentido econômico, abro PDR/IDR para discutir formalmente — não cola direto no briefing.
2. **Briefings explicitam "spec vence"** desde o início. Se Programador detectar divergência, **PARA e pergunta**. Esse foi o protocolo que funcionou.
3. **Bumps de `motor_version` em paralelo são viáveis** com regra explícita nos briefings (re-detectar state no PR, bumpar próximo MINOR, re-emitir golden hashes). Mas serializar quando possível é menos arriscado.
4. **Snapshot é a verdade** (IDR-010). Toda mudança de output do motor → bump de `motor_version` → re-emite golden hashes. Diagnósticos antigos **não recalculam**.
5. **Decisões locais do Programador são bem-vindas** (ex.: card-ncg → card-informativo na 030; generalização da fórmula de severidade para amarelos na 031). PO valida em revisão; não precisa abrir IDR para refactor local sem impacto transversal.
6. **PO faz smoke check, não verifica visualmente em browser** durante sessão (sandbox não tem). F-NB-1 visual fica para deploy de homol + screencast manual.

## Como abrir a próxima sessão

Abra uma nova sessão do Cowork na pasta `DEFOnline`. Cole o seguinte como **primeira mensagem**:

> ```
> Você é o PO da plataforma DEFOnline (Diagnóstico Econômico-Financeiro Online).
>
> Comece lendo este arquivo de retomada:
> defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/handoff/PO-retomada-2026-05-26.md
>
> Depois confirme:
> 1. Que entendeu o estado da sprint (V1 e V2 done, V3 é o próximo Checkpoint).
> 2. Que vai seguir o princípio "spec vence briefing" rigorosamente — sem inventar regra de negócio.
> 3. Que vai me perguntar (via AskUserQuestion) qual dos próximos passos atacar primeiro
>    antes de produzir qualquer artefato.
>
> Sua tarefa: me ajudar a tocar a fatia V3 do EPIC-002.
> ```

Esse prompt orienta o agente PO a:
- ler o handoff,
- confirmar estado,
- reforçar o princípio anti-invenção (lição da sprint),
- usar `AskUserQuestion` em vez de chutar o próximo passo.

## Snapshot rápido das decisões arquiteturais (referência)

- **IDR-010** (versionamento + persistência idempotente) — `decisions/idr/IDR-010-...` — accepted.
- **IDR-009** (404 silente cross-tenant) — accepted.
- **ADR-003** (multi-tenancy via Global Scope, UUID v7, soft delete) — accepted.
- **ADR-001..006** — stack/topologia/persistência/observabilidade/infra/cicd — accepted.

## Estado de qualidade técnica (snapshot 2026-05-25)

- **Testes Pest:** 695 verdes, ~1820 asserções.
- **Cobertura `app/Domain`:** **100%** (gate ≥ 98%).
- **Cobertura total:** 96.8% (gate ≥ 80%).
- **Larastan:** 0 erros.
- **Pint:** todos os 213 arquivos OK.
- **Dusk:** 13/13 verdes (após fix da 029).
- **Motor latência p95 isolado:** < 500ms.
- **Render do relatório:** < 100ms p50 (medição local).
- **p95 ponta-a-ponta em homol:** **pendente de deploy** (F-NB-1).

— PO (Alexandro), 2026-05-25
