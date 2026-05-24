---
report_date: 2026-05-24
sprint_id: SPRINT-2026-W22
wave: WAVE-2026-01
audience: humano-stakeholder
trigger: fim-de-epico
epic_closed: EPIC-001
---

# Status DEFOnline — 2026-05-24 (fechamento do EPIC-001)

## TL;DR (3 linhas)

- **Onde estamos:** EPIC-001 (Cadastro mínimo) **fechado como `approved`** — Roberto consegue percorrer cadastro → Termo+LGPD → confirmar email → cadastrar Empresa via RFB → ver "Minhas Empresas" em https://defonline.xandrix.com.br (v0.8.0-rc.1).
- **O que mudou:** validação independente (STORY-017) executada hoje pelo Validador (40 PASS / 0 FAIL / 6 ressalvas de evidência); PO sobrescreveu o veredito inicial `approved_with_pending` para `approved` puro após análise; 3 estórias de débito (STORY-021/022/023) registradas; 5 estórias transicionadas para `done` (STORY-013, 014, 015, 016, 018).
- **Próxima entrega visível:** STORY-019 (App shell + design system v1, EPIC-004) entra na próxima sprint (SPRINT-2026-W23) — vai harmonizar a paleta atual com o `design-system.md` oficial antes do EPIC-002 (Diagnóstico Indústria) começar.

## Onda atual

- **Onda:** WAVE-2026-01 — "Hipótese do Roberto" (validar valor real ao dono de pequena indústria entregando o ciclo cadastro → diagnóstico → histórico em homologação, sem cobrança).
- **Progresso da onda:** 2 de 5 épicos `done` (EPIC-000 Foundation + EPIC-001 Cadastro mínimo). 11 das 23 estórias da onda em `done` (~48%), restando EPIC-004 (App shell, 2 estórias `ready`), EPIC-002 (Diagnóstico Indústria, `draft`), EPIC-003 (Histórico básico, `draft`).

## Épicos

### Em andamento

| Épico | Status | Estórias done | Próximo marco |
|---|---|---|---|
| EPIC-004 — App shell + design system v1 | `ready` | 0/2 | STORY-019 entra na SPRINT-2026-W23 |

### Concluídos nesta onda

- **EPIC-000 — Foundation técnica do projeto pós-reset** — concluído em 2026-05-22 — relatório de validação: [epics/EPIC-000-foundation/validation/report.md](defonline-docs/project-state/epics/EPIC-000-foundation/validation/report.md).
- **EPIC-001 — Cadastro mínimo de Usuário e Empresa Analisada** — concluído em 2026-05-24 — relatório de validação: [epics/EPIC-001-cadastro-minimo/validation/report.md](defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/validation/report.md) (veredito final `approved` após PO sobrescrever `approved_with_pending` inicial — detalhes na seção "Decisão do PO" do report).

### Próximos

- **EPIC-004 — App shell + materialização do design system v1** — começa na SPRINT-2026-W23 (após fechamento desta sprint W22).
- **EPIC-002 — Diagnóstico Econômico-Financeiro para Indústria** — bloqueado por EPIC-001 ✓ e EPIC-004. Começa após STORY-020 (validação do EPIC-004).
- **EPIC-003 — Histórico básico de diagnósticos** — bloqueado por EPIC-002.

## Sprint corrente

- **Sprint:** SPRINT-2026-W22 (25/05 a 12/06 — última semana ativa).
- **Objetivo original:** fechar o EPIC-001 inteiro. ✅ **Cumprido.**
- **Estórias:** 8 `done` (STORY-011..STORY-018), 0 `in_progress`, 0 `blocked`, 3 `ready` recém-criadas (STORY-021, 022, 023 — débitos do EPIC-001, **não obrigatoriamente** desta sprint; vão para W23 ou janela ociosa).

## O que o usuário pode ver agora em homologação

Tudo em https://defonline.xandrix.com.br (v0.8.0-rc.1):

- ✅ **`/cadastro`** — cadastro de Usuário com CPF (mascarado), nome, email, senha forte (8+ chars, letras + números), telefone WhatsApp + 3 aceites (Termo, LGPD, opt-in marketing) com texto placeholder.
- ✅ **`/login`** — autenticação com throttle 5/min (ADR-001); mensagem genérica em falha; bloqueio se email não confirmado.
- ✅ **`/email/confirmar/{usuario}`** — link assinado TTL 60 min via Resend (IDR-007); reenvio com throttle 3/h por email.
- ✅ **`/home`** ("Minhas Empresas") — saudação + lista da empresa cadastrada com badge da fonte + botão "Iniciar diagnóstico" desabilitado (EPIC-002 ativa).
- ✅ **`/empresas/nova`** — cadastro de Empresa Analisada com pré-preenchimento via RFB (provedor `cnpja` ativo) + fallback transparente para manual em qualquer falha. Mascaramento de documento + multi-tenant 403 + audit log sem PII.
- ✅ **`/termos/termo-adesao`** e **`/termos/politica-privacidade`** — texto placeholder PT-BR com banner explícito + canal DPO + versão `v1-placeholder` hashada nos aceites.
- ✅ **Eventos de produto:** `usuario_cadastrado` (após confirmação de email) e `empresa_cadastrada` (dentro da transação do cadastro) — primeiros consumidores reais do EventLogger do EPIC-000.

## Qualidade

- **Cobertura unitária geral** do código novo do EPIC-001: **95.4% a 97.2%** por estória (meta 80%) ✓
- **Cobertura em `app/Domain`**: **100%** (meta 98%) ✓
- **Suíte Pest**: 265 testes (a partir da STORY-018) com ~830 asserções.
- **Suíte Dusk (E2E browser real)**: 12 testes cobrindo todo o caminho do épico.
- **Pipeline principal**: verde nos últimos 5 release-homolog (v0.5 → v0.8) e nos últimos 3 main.yml.
- **Smoke pós-deploy**: read-only por design — única exceção que tentou submit em produção (rc.1 da STORY-011) virou lição absorvida em todas as estórias subsequentes.

## Decisões registradas no período (desde o último status report 2026-05-22)

- **IDR-004** — RFB com 2 provedores reais via abstração (cnpja + receitaws). [link](defonline-docs/project-state/decisions/idr/IDR-004-rfb-provider-abstraction-cnpja-receitaws.md)
- **IDR-005** — Default de produção será `cnpja` (Arquiteto, depois). [link](defonline-docs/project-state/decisions/idr/IDR-005-rfb-provider-default-producao-cnpja.md)
- **IDR-006** — Wiring de infra RFB (Postgres + secrets via Ansible Vault). [link](defonline-docs/project-state/decisions/idr/IDR-006-rfb-wiring-infra-postgres-secrets.md)
- **IDR-007** — Resend como provedor de email transacional em homol e produção. [link](defonline-docs/project-state/decisions/idr/IDR-007-email-provider-resend.md)
- **PO 2026-05-24** — EPIC-004 inserido na WAVE-2026-01 (app shell antes do EPIC-002) — registrado em `current-wave.md` + nota em `next-wave.md` (cascata de numeração).
- **PO 2026-05-24** — Veredito do EPIC-001 elevado de `approved_with_pending` para `approved` após análise das 6 ressalvas serem todas de evidência ou processo (nenhuma técnica). Trade-off aceito explicitamente em `validation/report.md` seção "Decisão do PO".

## Bloqueios e riscos abertos

- **Risco "PO sobrescreveu sem smoke manual"** — assumido em 2026-05-24. Se algum dos 3 itens "PASS por confiança" (hash bcrypt no banco real, dashboard Resend, email não-PII no inbox real) for descoberto como bug em produção, retroage como falha de decisão de PO. Mitigação: monitor do Resend (bounce rate em dashboard) + revisão na retro da WAVE-2026-01.
- **Débito operacional não-virado-estória**: rotacionar chave cnpja queimada (STORY-018 notas — chave colada em chat e logada em transcript/history; persistida sob risco aceito pelo PO). Ação: PO faz manualmente no painel cnpja + `ansible-vault edit` em janela curta.

## Olhando à frente

### Próximos 7-14 dias

- **Fechar SPRINT-2026-W22** (12/06). EPIC-001 já está done; sprint encerra naturalmente.
- **Abrir SPRINT-2026-W23** com **STORY-019** (implementação do EPIC-004 App shell) + **STORY-020** (validação) como objetivo central. STORY-021 entra também (spike do Arquiteto, S — não compete com EPIC-004).
- **STORY-022 e STORY-023** ficam em backlog `ready`; entram em janela ociosa ou em sprint subsequente — não competem com o caminho crítico.
- **Smoke manual do PO** dos 3 itens "PASS por confiança" do EPIC-001 (15 min) — recomendação do Validador; PO **declinou no fechamento** mas pode executar a qualquer momento sem reabrir validação.

### Decisões aguardando você (Alexandro)

- **STORY-021** — escolher Opção A/B/C cross-tenant. Spike do Arquiteto pequeno (S). Sem bloqueio formal do EPIC-002, mas vale fechar antes da implementação de qualquer rota nova autenticada. **Sem urgência até abertura do EPIC-002.**
- **STORY-019 entra na SPRINT-2026-W23** — confirmação de prioridade quando abrir a próxima sprint (sugestão: STORY-019 + STORY-021 em paralelo; STORY-022 e 023 como buffer).
- **Rotacionar chave cnpja queimada** — ação operacional sua, ~10 min. Não bloqueia nada hoje mas vale fazer essa semana.

### Riscos abertos

- **Risco de decisão por omissão na rota cross-tenant** (STORY-021): se EPIC-002 começar antes da IDR ser fechada, o time vai herdar 403 implicitamente em 6+ rotas novas. **Probabilidade**: média. **Impacto**: baixo (refator depois é factível, mas trabalho dobrado). **Mitigação**: garantir STORY-021 `done` antes de STORY-021 do EPIC-002 — owner: PO.
- **Risco de Resend rejeitar envios em volume**: domínio recém-verificado, sem aquecimento; primeiros envios reais podem cair em spam. **Probabilidade**: média. **Impacto**: médio (UX do email quebra). **Mitigação**: monitorar bounce rate no painel Resend nos primeiros 14 dias; se >5% sustentado, abrir IDR de provedor alternativo (Postmark). **Owner**: PO.
- **Risco de descobrir bug pós-fechamento** dos 3 itens "PASS por confiança" do EPIC-001. **Probabilidade**: baixa (CI verde + qualidade histórica). **Impacto**: depende do bug. **Mitigação**: smoke manual de 15 min em janela conveniente. **Owner**: PO.

### Próximos marcos previstos

- **12/06/2026**: fim da SPRINT-2026-W22 (já completa funcionalmente; encerramento administrativo).
- **15/06/2026** (estimativa): abertura da SPRINT-2026-W23 com EPIC-004 como objetivo.
- **~30/06/2026** (estimativa): fim do EPIC-004 + abertura efetiva do EPIC-002.
- **D+14 após primeiro deploy de produção** (provavelmente outubro/2026): primeira medição da métrica primária do EPIC-001 (≥ 80% conversão cadastro→Empresa listada).

## Apêndice — links rápidos

- Índice do projeto: [defonline-docs/project-state/index.json](defonline-docs/project-state/index.json)
- Onda atual: [roadmap/current-wave.md](defonline-docs/project-state/roadmap/current-wave.md)
- Sprint atual: [sprints/SPRINT-2026-W22.md](defonline-docs/project-state/sprints/SPRINT-2026-W22.md)
- Relatório de validação do EPIC-001: [epics/EPIC-001-cadastro-minimo/validation/report.md](defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/validation/report.md)
- Checklist usado pelo Validador: [epics/EPIC-001-cadastro-minimo/validation/checklist.md](defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/validation/checklist.md)
- Estórias novas (débito): [STORY-021](defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/stories/STORY-021-spike-403-vs-404-cross-tenant.md), [STORY-022](defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/stories/STORY-022-alinhar-nomenclatura-business-metrics.md), [STORY-023](defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/stories/STORY-023-fix-bump-rc-dispara-release-homolog.md)
- Versão atual em homol: [https://defonline.xandrix.com.br/health](https://defonline.xandrix.com.br/health) → `v0.8.0-rc.1`
