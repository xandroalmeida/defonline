---
story_id: STORY-012
slug: termo-adesao-lgpd-aceites
title: Termo de Adesão + consentimento LGPD (com texto placeholder até jurídico retornar)
epic_id: EPIC-001
sprint_id: SPRINT-2026-W22
type: implementation
target_role: programador
status: in_review
owner_agent: programador (claude-opus-4-7)
created_at: 2026-05-22
updated_at: 2026-05-22
estimated_session_size: S
---

# STORY-012 — Termo de Adesão + consentimento LGPD (aceites obrigatórios, texto placeholder)

> **Para o agente programador:** o texto definitivo do Termo de Adesão e do consentimento LGPD depende da revisão jurídica externa, postergada pelo PO em 2026-05-22. Esta estória entrega o **mecanismo de aceite persistido** com texto placeholder PT-BR genérico. Quando o jurídico voltar com a redação final, vira bugfix curto (atualizar conteúdo, manter mecanismo). Não tente redigir cláusulas jurídicas — esse não é seu papel.

## Contexto (por que esta estória existe)

LGPD exige aceite explícito, granular e auditável de termos e consentimentos. Sem o mecanismo persistido, qualquer cadastro feito agora teria que ser "re-aceito" depois — retrabalho desnecessário. Com o mecanismo no lugar, basta atualizar o conteúdo quando o jurídico voltar.

A espec separa três aceites distintos:

| Aceite | Obrigatoriedade | Base legal (NRF §7.1) |
|---|---|---|
| Termo de Adesão | Obrigatório (bloqueia cadastro) | Execução de contrato (Art. 7º, V) |
| Consentimento LGPD básico | Obrigatório (bloqueia cadastro) | Execução de contrato |
| Opt-in de marketing | Opcional (default = false) | Consentimento (Art. 7º, I) |

- Épico: `epics/EPIC-001-cadastro-minimo/epic.md`
- Documentos canônicos:
  - `especificacao-funcional.md` §3.3 Passo 1 (aceites no fluxo de cadastro)
  - `requisitos-nao-funcionais-e-juridicos.md` §7 (LGPD — base legal estendida); §8 (premissas de revisão do Termo de Adesão)
  - `arquitetura-tecnica.md` §4.1 — tabela `TermAcceptance` (modelo a respeitar)
  - ADR-003 — auditoria, `audit_logs` append-only

## O quê (objetivo desta estória)

Adicionar à tela de cadastro (STORY-011) três checkboxes — dois obrigatórios (Termo + LGPD), um opcional (marketing). Persistir cada aceite em tabela `term_acceptances` com data, IP, user-agent, hash do conteúdo aceito e versão. Bloquear submit do cadastro se algum dos obrigatórios não for marcado.

## Por quê (valor para o usuário)

Para Roberto, é a sensação concreta de estar entrando num produto com regras claras — ele aceita ou não, sem letra miúda escondida. Para a EBP, é a evidência judicial de que cada Usuário concordou explicitamente com os termos vigentes na data do cadastro (com hash do conteúdo + versão preservados para o caso de o texto mudar).

## Critérios de aceite

- [x] **CA-1:** O componente Livewire de cadastro (STORY-011) ganha **três checkboxes**, nesta ordem, abaixo dos campos pessoais:
  1. "Li e aceito o **Termo de Adesão**" (obrigatório; link `[abrir em nova aba]` para `/termos/termo-adesao` que renderiza o texto placeholder).
  2. "Li e aceito a **Política de Privacidade e LGPD**" (obrigatório; link para `/termos/politica-privacidade`).
  3. "Quero receber comunicações de marketing por email/WhatsApp" (**opcional**, default desmarcado).
- [x] **CA-2:** Submit com **algum dos dois obrigatórios desmarcado** falha com mensagem específica por campo ("Você precisa aceitar o Termo de Adesão para continuar."). Submit válido cria o Usuário (STORY-011) **e** três registros em `term_acceptances` (mesmo para marketing — registra `aceito: false`, para evidenciar a oferta explícita).
- [x] **CA-3:** Migration cria tabela `term_acceptances` com colunas mínimas: `id`, `usuario_id` (FK), `termo_tipo` (enum: `'termo_adesao' | 'lgpd' | 'marketing'`), `aceito` (boolean), `versao` (string — ex: `'v1-placeholder'`), `conteudo_hash` (SHA-256 do texto exato exibido), `ip` (inet), `user_agent` (text), `aceito_at` (timestamp). Índice em `(usuario_id, termo_tipo, aceito_at)`. **Append-only** (sem update/delete na app — Policy nega; teste arquitetural Pest cobre, mesmo padrão do `AuditLog` e `EventoProduto`).
- [x] **CA-4:** Existe arquivo `app/resources/views/legal/termo-adesao-v1-placeholder.blade.php` com texto PT-BR genérico — não é cláusula jurídica formal, é placeholder explicitando: nome do produto, finalidade do tratamento de dados, direitos do titular (LGPD Art. 18 resumido), canal de contato (`dpo@ebparcerias.com`, mesmo que o DPO formal ainda esteja `[DECIDIR]`), retenção de 30 dias para anonimização pós-exclusão, e **um banner visível "TEXTO PLACEHOLDER — substituído após revisão jurídica."** Idem para `politica-privacidade-v1-placeholder.blade.php`. Versão `v1-placeholder` registrada no aceite.
- [x] **CA-5:** Rotas públicas `/termos/termo-adesao` e `/termos/politica-privacidade` renderizam o conteúdo atual com header de versão e data — não exigem login. Layout simples (sem chrome do app).
- [x] **CA-6:** Cada aceite gera entrada em `audit_logs` (`action: 'termo.aceito'` ou `'termo.recusado'`, contendo `termo_tipo` e `versao`, **sem ip/user-agent em log** — esses ficam só na tabela `term_acceptances`). Reuso de `AuditLogger` do EPIC-000.
- [x] **CA-7:** Testes: 1+ UnitPure (validação de aceites obrigatórios em FormRequest/Livewire rules), Feature cobrindo CA-1 a CA-6 (submit sem aceites bloqueia; submit com aceites cria 3 rows; teste arquitetural impede update/delete), 1 Dusk percorrendo `cadastro → marcar obrigatórios → submit OK`. Cobertura ≥ 80%.

## Fora de escopo

- **Redigir cláusulas jurídicas reais** — não é sua função. Use o placeholder.
- **Re-aceite quando texto mudar** — entra em estória futura quando o jurídico voltar (vai exigir UX de "novo Termo, releia e aceite").
- **Exclusão de conta / direito ao esquecimento (Art. 18 IV)** — onda futura. Esta estória só registra o aceite na entrada.
- **Banner de cookies / consentimento granular de analytics** — espec §3.2 (hotsite), fora do escopo deste épico.
- **DPA simplificado para plano Pro** (NRF §7.2) — Pro entra na onda 2; esta estória não precisa cobrir.

## Padrões de qualidade exigidos

- Cobertura ≥ 80% no código novo (gate aplicado).
- **Sem PII em log/evento** (ip e user-agent **só** na tabela `term_acceptances`, nunca em `audit_logs` ou `evento_produto`).
- E2E em Dusk cobrindo o gating dos aceites.

## Dependências

- **Bloqueada por:** STORY-011 (precisa do form e da entidade Usuário existindo).
- **Bloqueia:** STORY-016 (lista "Minhas Empresas" depende de cadastro completo).
- **Pré-requisitos de ambiente:** Docker rodando, homologação acessível.

## Decisões já tomadas (não as reabra)

- **Texto placeholder explicitamente PT-BR genérico** — decisão PO 2026-05-22. Não tente "improvisar redação jurídica" — `conteudo_hash` do placeholder é diferente do hash do texto final, então o `term_acceptances` vai sinalizar "esses Usuários aceitaram a v1-placeholder" e o time pode pedir re-aceite quando o jurídico chegar.
- **Marketing como opt-in opcional, default desmarcado** — NRF §7.1 (consentimento Art. 7º, I).
- **`term_acceptances` append-only** — ADR-003 + paralelo com `audit_logs` e `evento_produto`.

## Liberdade técnica do agente

Você decide:
- Estrutura do enum (`enum` PostgreSQL nativo, string com check constraint, ou enum PHP em código com cast Eloquent — escolha de organização sua).
- Como renderizar o texto placeholder (Blade view, Markdown convertido, etc.).
- Layout das telas de termos (mínimo, mas legível).

Você **não** decide:
- Quais aceites são obrigatórios vs. opcionais (espec).
- Que ip/user-agent vão para `term_acceptances` mas **não** para log (LGPD + arquitetura).
- Que existem três aceites distintos (espec §3.3).

## Definição de Pronto (DoD)

- [x] Todos os CAs passam (CA-1..CA-7 marcados).
- [x] Pre-push verde local (Pint, PHPStan 0 erros, Pest 108 testes / cobertura 96.1% e 100% no Domain, Dusk 6 testes).
- [ ] Pipeline CI verde — pendente: depende de push, e [feedback-workflow-direto-em-main](file:../../../../../../../.claude/projects/-Users-alexandro-Projetos-DEFOnline/memory/feedback-workflow-direto-em-main.md) manda esperar o PO autorizar antes de push/PR/tag.
- [ ] Deploy em homologação validado por smoke — idem (pós-aprovação do PO).
- [x] `index.json` atualizado: `in_progress` (transição para `done` após smoke aprovado).
- [x] "Notas do agente" preenchidas.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`. Status: `ready → in_progress → in_review → done`. PO valida em homologação (clica e tenta submeter sem aceites, vê o erro; aceita, cria conta) antes de marcar `done`.

## Notas do agente

### Decisões tomadas
- 2026-05-22 — Mecânica de versionamento: registrar `versao` ('v1-placeholder') + `conteudo_hash` SHA-256 do HTML renderizado da view. Quando o jurídico voltar com a redação final, basta trocar a view + bumpar a versão; os hashes antigos ficam preservados em `term_acceptances` e identificam quem precisa re-aceitar (essa UX entra em estória futura).
- 2026-05-22 — Enum PHP `App\Domain\TermoTipo` + check constraint no Postgres (`termo_tipo IN ('termo_adesao','lgpd','marketing')`). Combina defesa de aplicação com defesa no banco; evita instalar enum nativo Postgres só para 3 valores que mudam pouco.
- 2026-05-22 — `term_acceptances` append-only via mesmo padrão de `audit_logs`/`evento_produto`: bloqueio em model (RuntimeException em `update`/`delete`) + `REVOKE UPDATE, DELETE` no role `defonline_app` (ADR-005 §7.5).
- 2026-05-22 — IP + user_agent ficam SÓ em `term_acceptances`; NÃO entram no `context` do `AuditLogger` para os aceites (CA-6 — minimização de PII em log). Teste explícito confirma a ausência.
- 2026-05-22 — Marketing recusado também gera linha (`aceito=false`) + `audit_log` (`termo.recusado`) — espec CA-2 manda evidenciar a oferta.
- 2026-05-22 — Rotas dos termos via `Route::view(...)` direto (sem controller dedicado) — não há lógica condicional, só servir Blade público.

### Descobertas
- 2026-05-22 — `phpstan` reclama de `?? throw` quando o array é typed (`const REGISTRO = [...]` cobre todos os enum cases); resolvi removendo o fallback (o array é exaustivo por construção).
- 2026-05-22 — Os testes do happy path em `CadastroTest` (STORY-011) precisaram receber `aceite_termo_adesao=true` + `aceite_lgpd=true` para continuar passando — agora os aceites são parte do contrato de submit.

### Bloqueios encontrados
- Nenhum.

### IDRs criados
- (nenhum — decisões couberam no escopo dado pela espec).

### Cobertura final
- Geral: 96.1% (gate ≥80%)
- Domain: 100% (gate ≥98%)
- Tests Pest: 108 (330 asserções)
- Tests Dusk: 6 (32 asserções)

### Links de evidência
- PR: — (entrega local; tag/PR só após aprovação do PO, ver [feedback-workflow-direto-em-main](file:../../../../../../../.claude/projects/-Users-alexandro-Projetos-DEFOnline/memory/feedback-workflow-direto-em-main.md))
- Pipeline: — (idem)
- Tag rc.N: — (idem)
