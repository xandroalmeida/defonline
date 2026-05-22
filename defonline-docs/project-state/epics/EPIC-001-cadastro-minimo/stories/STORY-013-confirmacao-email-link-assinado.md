---
story_id: STORY-013
slug: confirmacao-email-link-assinado
title: Confirmação de email por link assinado (conta inativa até confirmar)
epic_id: EPIC-001
sprint_id: null
type: implementation
target_role: programador
status: ready
owner_agent: null
created_at: 2026-05-22
updated_at: 2026-05-22
estimated_session_size: M
---

# STORY-013 — Confirmação de email por link assinado

> **Para o agente programador:** confirmação de email é exigência **obrigatória** da espec (§3.3 Passo 1: "O e-mail recebe link de confirmação — obrigatório para ativar a conta"). Esta estória entrega o mecanismo completo: envio na criação do cadastro, link assinado de uso único, ativação na tela "/email/confirmado", bloqueio de login até confirmar, e reenvio com throttling.

## Contexto (por que esta estória existe)

Email não confirmado = identidade não comprovada. Sem isso:
- Aceitar Termo de Adesão com email errado é juridicamente frágil (não tem como notificar o titular).
- Recuperação de senha (onda 2) não funciona se o email original era inválido.
- Comunicações transacionais futuras (confirmação de assinatura, fatura) vão pro vazio.
- E o pior: aumenta o risco de cadastros fraudulentos / robôs no beta fechado.

A espec é explícita: **obrigatório para ativar a conta**.

- Épico: `epics/EPIC-001-cadastro-minimo/epic.md`
- Documentos canônicos:
  - `especificacao-funcional.md` §3.3 Passo 1 (link de confirmação obrigatório)
  - `requisitos-nao-funcionais-e-juridicos.md` §3 (provedor de email — Mailpit local, SMTP real em homol via ADR-005)
  - ADR-002 — fila Postgres para o envio do email (não enviar síncrono no submit do cadastro — UX pior + risco se o provedor cair)
  - ADR-004 — emissão de log estruturado, mascaramento de email em log

## O quê (objetivo desta estória)

Implementar o ciclo: cadastro cria Usuário com `email_confirmed_at = null` → job dispara email com link assinado de uso único → usuário clica no link → endpoint valida assinatura, marca `email_confirmed_at = now()` e mostra "/email/confirmado" → login passa a funcionar.

## Por quê (valor para o usuário)

Para Roberto, é a sensação de que o produto se importa em saber que é ele mesmo no email — confiança. Para a EBP, é a base legal mínima para confiar nos contatos cadastrados.

## Critérios de aceite

- [ ] **CA-1:** A migration da STORY-011 (ou uma migration desta estória) inclui na tabela `usuarios` uma coluna `email_confirmed_at` (timestamp nullable). Cadastros novos a partir desta estória entram com `email_confirmed_at = null`. Backfill **não é exigido** (só houve cadastros locais de teste até aqui).
- [ ] **CA-2:** Ao concluir um cadastro válido (STORY-011 + STORY-012), o sistema **enfileira** (não envia síncrono) um job `EnviarEmailConfirmacao` na fila Postgres. O job envia email com link assinado Laravel (`URL::signedRoute('email.confirmar', ['usuario' => $id])`) com **TTL de 60 minutos**. Conteúdo do email: assunto curto ("Confirme seu email — DEFOnline"), corpo com saudação personalizada, link clicável, aviso de expiração de 60 min. Falha de envio: o job tenta 3 vezes (backoff exponencial padrão Laravel) e, se persistir, marca `business_metrics.status = 'failed'`.
- [ ] **CA-3:** Rota `GET /email/confirmar/{usuario}` com middleware `signed` (Laravel built-in) valida assinatura. Sucesso: marca `email_confirmed_at = now()`, emite log estruturado + entrada em `audit_logs` (`action: 'usuario.email_confirmado'`), redireciona para `/email/confirmado` com mensagem amigável "Email confirmado. Você já pode fazer login.". Link expirado, inválido ou de Usuário já confirmado: tela `/email/confirmar-erro` explicando o que aconteceu + botão "Reenviar email" (CA-5).
- [ ] **CA-4:** **Login bloqueia** Usuários com `email_confirmed_at = null`. Tentativa de login mostra mensagem específica: "Confirme seu email antes de fazer login. Verifique sua caixa de entrada — enviamos um link para {email_mascarado}." + botão "Reenviar email". Não fazer redirect para `/home` mesmo com credenciais corretas.
- [ ] **CA-5:** Reenvio: rota `POST /email/reenviar-confirmacao` aceita o email do Usuário (não exige login), re-enfileira o job e mostra mensagem genérica "Se este email estiver cadastrado, enviamos um novo link" (mesmo se o email não existir — evita enumeração de cadastros, em conformidade com `security-discipline.md`). **Throttling**: máximo 3 reenvios por hora por email (Laravel `RateLimiter` em chave `email-confirm:{email_hash}`).
- [ ] **CA-6:** Em ambiente local, emails caem no Mailpit (`http://localhost:8025`) — já configurado no EPIC-000. Em homologação, vai pelo SMTP configurado em `.env` da homologação. Teste manual de fluxo possível tanto local quanto em homol.
- [ ] **CA-7:** Testes: UnitPure para validação de TTL/assinatura helper se houver; Feature cobrindo CA-1 a CA-5 (job enfileirado ao cadastrar; link valida; link expirado falha; login bloqueia sem confirmação; reenvio com throttling); 1 Dusk percorrendo `cadastro → ver email no Mailpit → clicar no link → confirmar → login OK`. Cobertura ≥ 80%.

## Fora de escopo

- **Verificação de existência do email (DNS MX, ZeroBounce, etc.)** — onda 2 ou nunca; espec não exige.
- **2FA via email** — fora de onda 1.
- **Mudança de email do Usuário** — fora do MVP (Usuário só lê).
- **Customização HTML rica do email** (templates Mailchimp, etc.) — texto simples + 1 link, sem branding pesado nesta primeira versão. Pode ganhar polimento em estória futura.

## Padrões de qualidade exigidos

- Cobertura ≥ 80% no código novo.
- **Email é PII** — sempre mascarado em log via `LogSanitizer` (ex: `a***@i***.example`).
- Job **na fila**, não síncrono. Cadastro não pode travar esperando SMTP responder.

## Dependências

- **Bloqueada por:** STORY-011 (precisa do Usuário). Tecnicamente independente de STORY-012, mas faz sentido fluir junto.
- **Bloqueia:** STORY-016.
- **Pré-requisitos de ambiente:** Mailpit local (já no docker-compose desde EPIC-000); SMTP real configurado em homologação (já configurado pela STORY-007 Phase 3).

## Decisões já tomadas

- **Link assinado Laravel** (não JWT custom, não hash em DB) — built-in, idiomático.
- **TTL 60 min** — equilíbrio entre conveniência (cliente pode demorar para abrir email) e segurança.
- **Fila para envio** (ADR-002 — worker já existe).
- **Throttling reenvio** (security-discipline + boa prática anti-spam).

## Liberdade técnica do agente

Você decide:
- Nome do job (`EnviarEmailConfirmacao`, `EnviarConfirmacaoEmail`, etc.).
- Estrutura do template Blade do email.
- Mensagem exata das telas `/email/confirmado` e `/email/confirmar-erro`.
- Como medir `business_metrics` para email enviado (sugestão: status `email_confirmacao_enfileirado`, `_enviado`, `_falhou` — reuso da tabela existente do EPIC-000).

Você **não** decide:
- Que tem confirmação obrigatória (espec).
- Que login bloqueia sem confirmação (espec + segurança).
- Que email é PII em log.

## Definição de Pronto (DoD)

- [ ] CAs passam.
- [ ] Pre-push verde.
- [ ] Pipeline CI verde.
- [ ] Deploy em homologação validado: cadastrar via formulário, ver email chegando no SMTP de homol, clicar, confirmar, logar.
- [ ] `index.json` `done`.
- [ ] "Notas do agente" preenchidas.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`.

## Notas do agente

### Decisões tomadas
- <data> — <decisão>

### Descobertas
- <data> — <gotcha>

### Bloqueios encontrados
- <data> — <bloqueio>

### IDRs criados
- IDR-XXX — <título>

### Cobertura final
- Geral: <%>

### Links de evidência
- PR: <url>
- Pipeline: <url>
- Tag rc.N: <vX.Y.Z-rc.N>
- Screenshot do email no Mailpit: <anexar>
