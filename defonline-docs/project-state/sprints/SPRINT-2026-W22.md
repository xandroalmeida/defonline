---
sprint_id: SPRINT-2026-W22
wave: WAVE-2026-01
status: closed
start_date: 2026-05-25
end_date: 2026-06-12
closed_at: 2026-05-24
closed_early: true
goal: "Entregar o EPIC-001 inteiro: ciclo cadastro Usuário → Termo+LGPD → confirmação de email → cadastro Empresa Analisada (manual + RFB com provedor real cnpja + fallback) → Minhas Empresas → eventos de produto, com validação independente aprovada."
goal_achieved: true
---

# SPRINT-2026-W22

## Objetivo do sprint

Fechar o EPIC-001 ("Cadastro mínimo de Usuário e Empresa Analisada") inteiro nesta janela. Ao fim do sprint, Roberto deve conseguir, em homologação, percorrer o ciclo completo do cadastro em **≤ 5 minutos no celular** — esse é o entregável visível declarado em `epics/EPIC-001-cadastro-minimo/epic.md`. Métrica primária do épico (driver D1 — Aquisição): ≥ 80% dos convidados que chegam à tela de cadastro completam o fluxo até ver a Empresa listada. Esta métrica só pode ser medida em D+14 após o deploy de homologação — o que importa neste sprint é entregar o fluxo funcionando, observável e instrumentado.

Janela de 3 semanas escolhida com honestidade: o caminho crítico é STORY-011 → STORY-014 → STORY-015 → STORY-016 → STORY-017 (5 estórias em série), mas STORY-012, STORY-013 paralelizam com STORY-014 após STORY-011, e a STORY-018 (provedores RFB reais) paraleliza com STORY-016 após STORY-015 — então o tempo total de parede é menor que a soma. Estimativa atual ~13h efetivas (11h originais + 2h para STORY-018); 3 semanas comportam o ruído (bugs surfacing, deploy de homologação encontrando coisa nova, contexto de Multi-tenancy via Global Scope sendo aplicado pela primeira vez).

## Estórias incluídas

| ID | Título | Épico | Tamanho | Status atual | Bloqueada por |
|---|---|---|---|---|---|
| STORY-011 | Walking skeleton — cadastro de Usuário + login + home autenticada | EPIC-001 | M | ready | — |
| STORY-012 | Termo de Adesão + consentimento LGPD (texto placeholder) | EPIC-001 | S | ready | STORY-011 |
| STORY-013 | Confirmação de email por link assinado (conta inativa até confirmar) | EPIC-001 | M | ready | STORY-011 |
| STORY-014 | Cadastro de Empresa Analisada por preenchimento manual | EPIC-001 | M | ready | STORY-011 |
| STORY-015 | Enriquecimento via API RFB (mock + fallback transparente) | EPIC-001 | M | ready | STORY-014 |
| STORY-016 | Tela "Minhas Empresas" + emissão de eventos `usuario_cadastrado` e `empresa_cadastrada` | EPIC-001 | S | ready | STORY-012, STORY-013, STORY-014 |
| STORY-017 | Validação final do EPIC-001 Cadastro mínimo | EPIC-001 | M | draft → promove para ready quando STORY-016 estiver `in_review` | STORY-011..STORY-016 |
| STORY-018 | Provedores reais da RFB (cnpja, receitaws) com rate-limit por provedor | EPIC-001 | M | ready | STORY-015 |

**Total estimado:** 6 M + 2 S ≈ ~13h efetivas + buffer para deploys/bugs/contexto novo.

## Ordem sugerida de execução

Caminho crítico em série, com paralelização em dois momentos (Semana 1 e Semana 2):

```
Semana 1 (25-31/05)
  Seg-Ter: STORY-011 (walking skeleton)                          ← desbloqueia 012, 013, 014
  Qua-Sex: STORY-012 // STORY-013 // STORY-014 (em paralelo)     ← três PRs independentes

Semana 2 (01-07/06)
  Seg-Qua: STORY-015 (RFB enrichment com mock + abstração)       ← desbloqueia 016 e 018
  Qui-Sex: STORY-016 (Minhas Empresas + eventos)                 ← desbloqueia 017
           // STORY-018 (clientes reais cnpja + receitaws, rate-limit por provedor)
                                                                  ← paraleliza com STORY-016 (dependem só da STORY-015)

Semana 3 (08-12/06) — buffer e fechamento
  Seg: promover STORY-017 para `ready` (PO escreve validation/checklist.md primeiro)
  Ter-Qua: STORY-017 (validação independente do Validador)
  Qui-Sex: buffer para correções de F-NB ou fechamento
```

**Nota sobre STORY-018:** depende apenas da STORY-015 (interface `RfbCnpjClient`, DTO, mock, cache, métricas). Pode ser puxada por outro agente Programador em paralelo com STORY-016, sem interferência — STORY-018 substitui o bind do `RfbCnpjClient` para clientes reais, STORY-016 lê dados já gravados pelo cadastro. Se a capacidade não permitir paralelismo real, STORY-018 desliza para Semana 3 (Seg-Ter), comendo parte do buffer.

## Compromisso visível ao fim do sprint

Em homologação, ao fim de 2026-06-12:

- ✅ Tela `/cadastro` em `https://defonline.xandrix.com.br/cadastro` aceita CPF + email + senha + telefone + 3 aceites (2 obrigatórios + 1 opt-in).
- ✅ Email de confirmação chega no inbox real (SMTP de homol), link assinado expira em 60 min.
- ✅ Login bloqueado até confirmar; tela `/home` substituída por "Minhas Empresas".
- ✅ Cadastro de Empresa Analisada em `/empresas/nova` aceita CNPJ (com botão "Consultar Receita" pré-preenchendo via **provedor real cnpja** em homologação — IDR-005) ou CPF (manual). Fallback transparente se o provedor real falhar (timeout, 5xx, rate-limit estourado).
- ✅ "Minhas Empresas" lista a empresa cadastrada com badge "Receita Federal" ou "Manual" + botão "Iniciar diagnóstico" desabilitado (EPIC-002 ativa).
- ✅ Eventos `usuario_cadastrado` e `empresa_cadastrada` aparecem na tabela `evento_produto` em homologação, com schema da ADR-004 e sem PII.
- ✅ Métrica `business_metrics` registra cada consulta RFB com `meta->>'provider'` (`cnpja` em homol) e `status` correto; rate-limit por provedor (3 RPM default — IDR-006) ativo via `RateLimiter` no driver `database`; comando agendado de monitoramento dispara alerta Telegram se taxa de erro > 5% em 10 min **por provedor**.
- ✅ Validação independente do EPIC-001 com veredito documentado em `validation/report.md`.
- ✅ EPIC-001 promovido para `done` no `index.json`.

## Capacidade e premissas

- **Time:** Alexandro (PO + revisão) + agentes Claude (Programador para STORY-011..016, Validador para STORY-017).
- **Cadência esperada:** 1-2 estórias por dia útil em modo agente focado. Tem folga para imprevistos.
- **Sem feriado nacional na janela.** Feriado de Corpus Christi cai em 04/06/2026 (quinta), mas como não é dia comercial obrigatório, não afeta cadência de agente.
- **Cobertura ≥ 80% obrigatória** (gate da STORY-010 ativo). Estórias novas devem manter ou subir os 92.4% atuais.

## Riscos identificados na abertura

| Risco | Probabilidade | Impacto | Mitigação | Owner |
|---|---|---|---|---|
| Texto definitivo de Termo / LGPD chega do jurídico durante o sprint e gera retrabalho | baixa | baixo | STORY-012 já entra com placeholder + hash de versão — troca futura é bugfix curto (mecanismo está modelado) | PO |
| Mock de RFB "esconde" complexidade que vai aparecer só com provedor real | baixa (após STORY-018) | médio | STORY-018 entra nesta sprint e exercita os clientes reais cnpja + receitaws em homologação contra CNPJs públicos. Cenários do mock continuam cobrindo testes locais; provedor real expõe gotchas que sobrarem | Programador (STORY-018) |
| STORY-018 estoura buffer da Semana 3 (não paraleliza com STORY-016 por falta de capacidade) | média | baixo | STORY-018 não bloqueia STORY-017 (que valida o EPIC-001 com fluxo funcionando — não exige `provider: cnpja` específico). Em último caso, STORY-018 entrega no buffer da Semana 3 (Qui-Sex) — perde o teste em homologação no D+0 mas não atrasa o fechamento do épico | PO |
| Rate-limit gratuito do cnpja (3 RPM) é exercitado em homologação por testes manuais simultâneos | baixa | baixo | Cenários de teste em homol respeitam janela; runbook `RFB-provider-switch.md` documenta troca para receitaws se cnpja banir por excesso. Tabela `cache` no Postgres já está modelada (IDR-006) | Programador (STORY-018) |
| SMTP de homologação rejeita emails de teste por reputação / DNS de domínio | média | médio | Validar no início da STORY-013 que SMTP de homol entrega para ao menos 1 domínio de teste (Gmail/inbox próprio). Mailpit local cobre desenvolvimento sem fricção | Programador |
| Multi-tenancy via Global Scope tem armadilha pega só em produção (vazamento cross-tenant) | média | alto | Feature test arquitetural verifica que listagem de Empresas filtra por `auth()->id()` sempre. Validação do EPIC-001 inclui teste cross-tenant explícito (item do checklist a escrever) | Programador + Validador |
| Cobertura cai abaixo de 80% no acumulado por código novo de regra de negócio menos testado | média | médio | Gate da STORY-010 bloqueia push, então o problema vira fricção, não regressão. Programador escreve teste antes de relaxar o gate | Programador |
| Validação do EPIC-001 (STORY-017) encontra fail bloqueante e estende o sprint | média | médio | Janela de 3 semanas já tem buffer para 1 ciclo de correção (paralelo ao precedente do EPIC-000 que precisou de STORY-010 corretiva). Se exceder, STORY-017 fica para SPRINT-2026-W25 | PO + Validador |

## Decisões pendentes que podem afetar o sprint

- ~~**Provedor RFB real**~~ — **fechado em 2026-05-23**. Abstração (IDR-004), primário `cnpja` em produção (IDR-005), wiring de infra (IDR-006), runbook e capacity planning entregues. STORY-018 entra nesta sprint para entregar os clientes reais.
- **Texto definitivo do Termo de Adesão / Política de Privacidade / DPO formal** — não bloqueia (postergado em 2026-05-22; placeholder cobre).
- **Provedor SMTP de homologação** — já configurado no EPIC-000 (Phase 3). Premissa: continua funcionando. Risco coberto na tabela acima.

## Mudanças no escopo do sprint

> Toda alteração no conjunto de estórias após esta abertura registra aqui.

| Data | O que mudou | Motivo | Custo |
|---|---|---|---|
| 2026-05-23 | **+ STORY-018** (Provedores reais cnpja + receitaws com rate-limit por provedor) | Decisões IDR-004/005/006 aceitas em 2026-05-23 fecharam a frente RFB. Sem STORY-018 nesta sprint, homologação rodaria com mock — perda do sinal de produção real ao fechar o EPIC-001. STORY-018 paraleliza com STORY-016 (depende só da STORY-015), sem alterar caminho crítico para STORY-017. | +1M (~2h efetivas). Buffer de Semana 3 absorve sem realocar. |

## Fechamento do sprint

**Fechado em 2026-05-24**, 19 dias antes da `end_date` planejada (12/06/2026). Goal totalmente atingido.

### O que foi entregue

Todas as 8 estórias planejadas em `done`:

| ID | Título | Tag rc | Notas |
|---|---|---|---|
| STORY-011 | Walking skeleton — cadastro Usuário + login + home | `v0.2.0-rc.2` | Lição da rc.1 (E2E destrutivo no smoke) absorvida em todas as subsequentes |
| STORY-012 | Termo de Adesão + LGPD (placeholder) | `v0.3.0-rc.1` | Mecanismo entregue; texto definitivo entra como bugfix quando jurídico voltar |
| STORY-013 | Confirmação de email por link assinado | (`v0.4+`) | Resend wiring veio depois (IDR-007) |
| STORY-014 | Cadastro de Empresa Analisada manual | `v0.4.0-rc.2` | Multi-tenant 403 — divergência com ADR-003 (404) registrada e endereçada por STORY-021 |
| STORY-015 | Enriquecimento RFB (mock + fallback) | `v0.5.0-rc.1` | Abstração `RfbCnpjClient` permitiu STORY-018 não tocar UX |
| STORY-016 | Tela "Minhas Empresas" + eventos `usuario_cadastrado`/`empresa_cadastrada` | `v0.6.0-rc.1` (incorporado em rc seguinte) | Primeiros consumidores reais do EventLogger do EPIC-000 |
| STORY-018 | Provedores reais RFB (cnpja + receitaws) | `v0.6.0-rc.1` | Smoke contratual corrigiu drift do endpoint público cnpja; chave queimada em débito |
| STORY-017 | Validação final do EPIC-001 | (sem tag — só docs) | Validador → `approved_with_pending` → PO sobrescreveu para `approved` após análise |

**Mudanças de escopo registradas durante o sprint:**

| Data | Mudança | Custo |
|---|---|---|
| 2026-05-23 | +STORY-018 (provedores reais RFB) — após IDR-004/005/006 aceitas | +1M absorvido pelo buffer |
| 2026-05-24 | +IDR-007 (Resend) — débito histórico de SMTP fechado durante smoke da STORY-018 | Zero (cabeu na janela) |
| 2026-05-24 | +EPIC-004 (App shell + design system) inserido na WAVE-2026-01 — após drift de paleta identificado em análise de gap do roadmap | Não impacta esta sprint (STORY-019/020 entram na próxima) |
| 2026-05-24 | +STORY-021/022/023 (débitos do EPIC-001) — geradas no fechamento da validação | Não consomem capacidade desta sprint (`sprint_id: null`) |

**Versão em homologação ao fim da sprint:** `v0.8.0-rc.1` (cobrindo todos os commits do EPIC-001 + STORY-018 + Resend + placeholder docs do EPIC-004).

### O que ficou para trás (e por quê)

**Nada do planejado.** Os 8 itens originais entregaram.

**Débitos identificados durante a execução** (não competem com goal — viraram estórias separadas):

- **STORY-021** (spike Arquiteto — 403 vs 404 cross-tenant) — `ready`, próxima sprint.
- **STORY-022** (renomear `kind` ↔ `tipo` em `business_metrics`) — `ready`, backlog.
- **STORY-023** (fix `bump-rc.yml` não dispara `release-homolog.yml`) — `ready`, backlog.
- **Ação operacional não-virada-estória:** rotacionar chave cnpja queimada (STORY-018 notas) — PO executa fora do fluxo de sprint.

### Aprendizados (retro por escrito — time pequeno)

**1. O que funcionou e queremos continuar:**

- **Abstração antes do provedor real** (STORY-015 entregando `RfbCnpjClient` + DTO + cache + métricas/alerta antes da STORY-018 trazer os clientes concretos) — STORY-018 só precisou cumprir o contrato; zero retrabalho. Padrão a propagar para EPIC-002 (motor de cálculo + indicadores).
- **Lição absorvida coletivamente da rc.1 da STORY-011** (smoke E2E destrutivo em homol) — todas as 7 estórias seguintes mantiveram smoke read-only. Disciplina espontânea sem precisar de checklist de processo.
- **Notas do agente substanciais em todas as estórias** (decisões, descobertas, gotchas, lições) — gerou documentação real e rastreável; permitiu o Validador trabalhar com evidência forte mesmo sem acesso direto a homol.
- **Validador sendo conservador no veredito** (`approved_with_pending` em vez de `approved` puro) + **PO assumindo a sobrescrita com transparência** (mantendo o `verdict_history` na fonte) — separa rigor de validação do trade-off de decisão de produto sem misturar.
- **Pipelines verdes consistentemente** (14 jobs no `release-homolog` da v0.8.0-rc.1) — gates de cobertura + lint + types + security scan trabalhando como esperado.

**2. O que custou caro e queremos mudar:**

- **Sprint fechou 19 dias antes da `end_date`.** Goal atingido, mas isso evidencia que a estimativa original (3 semanas para 8 estórias com agente Claude em modo focado) foi **muito conservadora**. Recalibrar para SPRINT-2026-W23: usar histórico real desta sprint como base (8 estórias entregues em ~5 dias úteis efetivos, ainda que sob compressão).
- **Chave de API do cnpja queimada no chat** (STORY-018 Decisões) — falha de processo de PO ao colar credencial em texto plano. Mitigação registrada (rotação pendente). Mudança para o próximo sprint: PO nunca cola credencial no chat; usa `gh secret set` ou `ansible-vault encrypt_string` direto no terminal.
- **Divergência arquitetural conhecida (403 vs 404 cross-tenant)** ficou aberta por 1 sprint inteira sem IDR formal — programador absorveu como decisão local na STORY-014; só virou estória explícita (STORY-021) no fechamento da validação. Para próximas waves: divergência detectada → IDR aberta no mesmo dia, não no fim.
- **Checklist do PO usou `kind`, schema usou `tipo`** (item 4.8 do `validation/checklist.md`) — falha de redação do PO. Mudança: PO grep cruzado (`grep -rn 'kind|tipo' app/ defonline-docs/`) antes de redigir item de checklist que cita schema.
- **Smoke manual do PO declinado no fechamento** — trade-off assumido explicitamente (registro em `validation/report.md` seção "Decisão do PO"). Não é mudança de processo, é risco a monitorar.

**3. Um experimento concreto para o próximo sprint:**

> **STORY-021 (spike Arquiteto cross-tenant) entra na primeira janela disponível**, **em paralelo** com STORY-019 (App shell EPIC-004). Justificativa: spike de Arquiteto é independente de implementação do EPIC-004; rodando os dois em paralelo testa se o time pequeno consegue de fato paralelizar trabalho de papéis distintos (PO+Arquiteto numa frente, Programador noutra). Se funcionar, vira padrão.

### Ajustes para o próximo sprint

- **Recalibrar capacidade**: usar dado real desta sprint (8 estórias S+M em ~5 dias úteis) em vez de estimativa conservadora. Próxima sprint pode comportar **EPIC-004 inteiro (STORY-019+020) + STORY-021 + 1-2 débitos** sem stress.
- **Tamanho de sprint**: considerar **1 semana** em vez de 2-3 (`sprint-mechanics.md` sugere 1 semana para time muito pequeno + MVP cedo). Validar na próxima abertura.
- **Status report mais leve durante execução** (não no fechamento): "olhada no índice + chat" diária quase desnecessária no modo agente focado — quase tudo aparece em uma sessão. Manter ritual mínimo apenas para acompanhamento de débitos abertos / decisões aguardando humano.

### Métricas finais da sprint

| Métrica | Valor | Meta |
|---|---|---|
| Estórias `done` | **8** | 8 |
| Estórias entregues / planejadas | **100%** | — |
| Cobertura geral média (estórias EPIC-001) | **~96%** | ≥ 80% |
| Cobertura `app/Domain` | **100%** | ≥ 98% |
| Suíte Pest (fim da sprint) | **265 testes / ~830 asserções** | — |
| Suíte Dusk (fim da sprint) | **12 testes** | — |
| Pipelines `release-homolog` verdes | **5/5** (v0.5 → v0.8) | 100% |
| Bloqueios reportados | **0** | — |
| Mudanças de escopo no meio | **3** (STORY-018, IDR-007, EPIC-004) | — |
| Veredito da validação do épico | **APPROVED** (após análise PO) | approved |
| Velocidade real (estórias/dia útil) | **~1.6** | — |
| Sprint encerrado | **19 dias antes** da end_date | — |

### Comemoração explícita (cultura)

EPIC-001 fechado em ~5 dias úteis efetivos com 8 estórias entregues, dois novos provedores externos integrados (Resend + cnpja), primeira instância de eventos de produto reais consumindo o EventLogger do EPIC-000, multi-tenancy via Global Scope funcionando em produção homol, e qualidade técnica acima de todas as metas. 🚀 A WAVE-2026-01 está adiantada em relação à `target_end_date` (26/08/2026): 2 de 5 épicos `done` em ~5 dias úteis de execução real.

> "Sprint bem conduzido entrega mais que estórias — entrega ritmo, previsibilidade e aprendizado contínuo." (`sprint-mechanics.md`)
