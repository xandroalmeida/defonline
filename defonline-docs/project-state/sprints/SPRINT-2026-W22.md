---
sprint_id: SPRINT-2026-W22
wave: WAVE-2026-01
status: active
start_date: 2026-05-25
end_date: 2026-06-12
goal: "Entregar o EPIC-001 inteiro: ciclo cadastro Usuário → Termo+LGPD → confirmação de email → cadastro Empresa Analisada (manual + RFB via mock com fallback) → Minhas Empresas → eventos de produto, com validação independente aprovada."
---

# SPRINT-2026-W22

## Objetivo do sprint

Fechar o EPIC-001 ("Cadastro mínimo de Usuário e Empresa Analisada") inteiro nesta janela. Ao fim do sprint, Roberto deve conseguir, em homologação, percorrer o ciclo completo do cadastro em **≤ 5 minutos no celular** — esse é o entregável visível declarado em `epics/EPIC-001-cadastro-minimo/epic.md`. Métrica primária do épico (driver D1 — Aquisição): ≥ 80% dos convidados que chegam à tela de cadastro completam o fluxo até ver a Empresa listada. Esta métrica só pode ser medida em D+14 após o deploy de homologação — o que importa neste sprint é entregar o fluxo funcionando, observável e instrumentado.

Janela de 3 semanas escolhida com honestidade: o caminho crítico é STORY-011 → STORY-014 → STORY-015 → STORY-016 → STORY-017 (5 estórias em série), mas STORY-012, STORY-013 paralelizam com STORY-014 após STORY-011, então o tempo total de parede é menor que a soma. Estimativa otimista 11h efetivas; 3 semanas comportam o ruído (bugs surfacing, deploy de homologação encontrando coisa nova, contexto de Multi-tenancy via Global Scope sendo aplicado pela primeira vez).

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

**Total estimado:** 5 M + 2 S ≈ ~11h efetivas + buffer para deploys/bugs/contexto novo.

## Ordem sugerida de execução

Caminho crítico em série, com 3 estórias paralelizáveis no meio:

```
Semana 1 (25-31/05)
  Seg-Ter: STORY-011 (walking skeleton)                          ← desbloqueia 012, 013, 014
  Qua-Sex: STORY-012 // STORY-013 // STORY-014 (em paralelo)     ← três PRs independentes

Semana 2 (01-07/06)
  Seg-Qua: STORY-015 (RFB enrichment com mock)                   ← desbloqueia 016
  Qui-Sex: STORY-016 (Minhas Empresas + eventos)                 ← desbloqueia 017

Semana 3 (08-12/06) — buffer e fechamento
  Seg: promover STORY-017 para `ready` (PO escreve validation/checklist.md primeiro)
  Ter-Qua: STORY-017 (validação independente do Validador)
  Qui-Sex: buffer para correções de F-NB ou fechamento
```

## Compromisso visível ao fim do sprint

Em homologação, ao fim de 2026-06-12:

- ✅ Tela `/cadastro` em `https://defonline.xandrix.com.br/cadastro` aceita CPF + email + senha + telefone + 3 aceites (2 obrigatórios + 1 opt-in).
- ✅ Email de confirmação chega no inbox real (SMTP de homol), link assinado expira em 60 min.
- ✅ Login bloqueado até confirmar; tela `/home` substituída por "Minhas Empresas".
- ✅ Cadastro de Empresa Analisada em `/empresas/nova` aceita CNPJ (com botão "Consultar Receita" pré-preenchendo via mock) ou CPF (manual). Fallback transparente se mock simular erro.
- ✅ "Minhas Empresas" lista a empresa cadastrada com badge "Receita Federal" ou "Manual" + botão "Iniciar diagnóstico" desabilitado (EPIC-002 ativa).
- ✅ Eventos `usuario_cadastrado` e `empresa_cadastrada` aparecem na tabela `evento_produto` em homologação, com schema da ADR-004 e sem PII.
- ✅ Métrica `business_metrics` registra cada consulta RFB com `status` correto; comando agendado de monitoramento dispara alerta Telegram se taxa de erro > 5% em 10 min.
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
| Mock de RFB "esconde" complexidade que vai aparecer só com provedor real | média | médio | Mock cobre todos os cenários previstos no IDR/PDR futuro (sucesso, timeout, cnpj_inexistente, erro_5xx, erro_rede). Cache + timeout + monitoramento já entram nesta sprint, não na troca | PO + Arquiteto (quando provedor for escolhido) |
| SMTP de homologação rejeita emails de teste por reputação / DNS de domínio | média | médio | Validar no início da STORY-013 que SMTP de homol entrega para ao menos 1 domínio de teste (Gmail/inbox próprio). Mailpit local cobre desenvolvimento sem fricção | Programador |
| Multi-tenancy via Global Scope tem armadilha pega só em produção (vazamento cross-tenant) | média | alto | Feature test arquitetural verifica que listagem de Empresas filtra por `auth()->id()` sempre. Validação do EPIC-001 inclui teste cross-tenant explícito (item do checklist a escrever) | Programador + Validador |
| Cobertura cai abaixo de 80% no acumulado por código novo de regra de negócio menos testado | média | médio | Gate da STORY-010 bloqueia push, então o problema vira fricção, não regressão. Programador escreve teste antes de relaxar o gate | Programador |
| Validação do EPIC-001 (STORY-017) encontra fail bloqueante e estende o sprint | média | médio | Janela de 3 semanas já tem buffer para 1 ciclo de correção (paralelo ao precedente do EPIC-000 que precisou de STORY-010 corretiva). Se exceder, STORY-017 fica para SPRINT-2026-W25 | PO + Validador |

## Decisões pendentes que podem afetar o sprint

- **Provedor RFB real** — não bloqueia (STORY-015 entra com mock). Se PO/Arquiteto decidirem antes do fim do sprint, troca pode entrar como bugfix sem comprometer cronograma.
- **Texto definitivo do Termo de Adesão / Política de Privacidade / DPO formal** — não bloqueia (postergado em 2026-05-22; placeholder cobre).
- **Provedor SMTP de homologação** — já configurado no EPIC-000 (Phase 3). Premissa: continua funcionando. Risco coberto na tabela acima.

## Mudanças no escopo do sprint

> Toda alteração no conjunto de estórias após esta abertura registra aqui.

| Data | O que mudou | Motivo | Custo |
|---|---|---|---|
| — | — | — | — |

## Fechamento do sprint (preencher no encerramento)

### O que foi entregue
- (a preencher em 12/06/2026)

### O que ficou para trás (e por quê)
- (a preencher em 12/06/2026)

### Aprendizados
- (a preencher em 12/06/2026)

### Ajustes para o próximo sprint
- (a preencher em 12/06/2026)
