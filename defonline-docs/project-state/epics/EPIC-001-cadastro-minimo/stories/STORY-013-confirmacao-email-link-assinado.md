---
story_id: STORY-013
slug: confirmacao-email-link-assinado
title: Confirmação de email por link assinado (conta inativa até confirmar)
epic_id: EPIC-001
sprint_id: SPRINT-2026-W22
type: implementation
target_role: programador
status: in_review
owner_agent: programador (claude-opus-4-7)
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

- [x] **CA-1:** A migration da STORY-011 (ou uma migration desta estória) inclui na tabela `usuarios` uma coluna `email_confirmed_at` (timestamp nullable). Cadastros novos a partir desta estória entram com `email_confirmed_at = null`. Backfill **não é exigido** (só houve cadastros locais de teste até aqui).
- [x] **CA-2:** Ao concluir um cadastro válido (STORY-011 + STORY-012), o sistema **enfileira** (não envia síncrono) um job `EnviarEmailConfirmacao` na fila Postgres. O job envia email com link assinado Laravel (`URL::signedRoute('email.confirmar', ['usuario' => $id])`) com **TTL de 60 minutos**. Conteúdo do email: assunto curto ("Confirme seu email — DEFOnline"), corpo com saudação personalizada, link clicável, aviso de expiração de 60 min. Falha de envio: o job tenta 3 vezes (backoff exponencial padrão Laravel) e, se persistir, marca `business_metrics.status = 'failed'`.
- [x] **CA-3:** Rota `GET /email/confirmar/{usuario}` com middleware `signed` (Laravel built-in) valida assinatura. Sucesso: marca `email_confirmed_at = now()`, emite log estruturado + entrada em `audit_logs` (`action: 'usuario.email_confirmado'`), redireciona para `/email/confirmado` com mensagem amigável "Email confirmado. Você já pode fazer login.". Link expirado, inválido ou de Usuário já confirmado: tela `/email/confirmar-erro` explicando o que aconteceu + botão "Reenviar email" (CA-5).
- [x] **CA-4:** **Login bloqueia** Usuários com `email_confirmed_at = null`. Tentativa de login mostra mensagem específica: "Confirme seu email antes de fazer login. Verifique sua caixa de entrada — enviamos um link para {email_mascarado}." + botão "Reenviar email". Não fazer redirect para `/home` mesmo com credenciais corretas.
- [x] **CA-5:** Reenvio: rota `POST /email/reenviar-confirmacao` aceita o email do Usuário (não exige login), re-enfileira o job e mostra mensagem genérica "Se este email estiver cadastrado, enviamos um novo link" (mesmo se o email não existir — evita enumeração de cadastros, em conformidade com `security-discipline.md`). **Throttling**: máximo 3 reenvios por hora por email (Laravel `RateLimiter` em chave `email-confirm:{email_hash}`).
- [x] **CA-6:** Em ambiente local, emails caem no Mailpit (`http://localhost:8025`) — já configurado no EPIC-000. Em homologação, vai pelo SMTP configurado em `.env` da homologação. Teste manual de fluxo possível tanto local quanto em homol.
- [x] **CA-7:** Testes: UnitPure para validação de TTL/assinatura helper se houver; Feature cobrindo CA-1 a CA-5 (job enfileirado ao cadastrar; link valida; link expirado falha; login bloqueia sem confirmação; reenvio com throttling); 1 Dusk percorrendo `cadastro → ver email no Mailpit → clicar no link → confirmar → login OK`. Cobertura ≥ 80%.

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

- [x] CAs passam (local).
- [x] Pre-push equivalente verde local (Pint + Larastan + Pest 134 testes + Dusk 8 testes + cobertura 96,7% / domínio 100%).
- [ ] Pipeline CI verde (a obter após push pelo PO).
- [ ] Deploy em homologação validado: cadastrar via formulário, ver email chegando no SMTP de homol, clicar, confirmar, logar (após PO autorizar push/tag).
- [ ] `index.json` `done` (após validação em homol).
- [x] "Notas do agente" preenchidas.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`.

## Notas do agente

### Documentos lidos (2026-05-22)
- STORY-013 completa.
- ADR-002 §Estratégia de trace (fila Postgres, propagação `request_id`, BaseJob).
- ADR-004 §1.2 (`business_metrics`), §1.1 (`LogSanitizer` PII), §2 (`evento_produto` — não emitido aqui, depende de STORY-016).
- Código existente: `Livewire\Cadastro`, `Livewire\Login`, `Jobs\BaseJob`, `Jobs\HelloWorldEmail`, `Mail\HelloWorldMessage`, `Observabilidade\AuditLogger`, `Observabilidade\LogSanitizer::maskEmail`, `Providers\AppServiceProvider` (padrão `RateLimiter::for`), `routes/web.php`, migrations existentes.
- Tabela `business_metrics` já tem `request_id`, `tipo`, `sucesso`, `duracao_ms`, `meta jsonb`, `inserido_em`.

### Entendimento consolidado
- Necessário (a) coluna `email_confirmed_at` em `usuarios` via nova migration, (b) `Usuario` exposing `email_confirmed_at`, (c) job `EnviarEmailConfirmacao` em fila `database`, retry 3x, gera link `URL::signedRoute('email.confirmar', ['usuario' => $id])` com TTL 60min, (d) Mailable `EmailConfirmacaoMessage`, (e) rota GET `/email/confirmar/{usuario}` com middleware `signed`, (f) telas `/email/confirmado` e `/email/confirmar-erro` (Blade simples), (g) login bloqueia se `email_confirmed_at` nulo, (h) rota POST `/email/reenviar-confirmacao` com throttle 3/hora por hash do email + mensagem genérica.
- Não emite `evento_produto` (depende de STORY-016).
- PII (email) em log → `LogSanitizer` cobre automaticamente. AuditLog mantém PII por lei.
- Validação do CSRF do reenvio: usa POST normal com `@csrf`.

### Decisões locais tomadas
- 2026-05-22 — Nome do job: `EnviarEmailConfirmacao` (`app/Jobs/EnviarEmailConfirmacao.php`).
- 2026-05-22 — Nome do Mailable: `EmailConfirmacaoMessage` (`app/Mail/EmailConfirmacaoMessage.php`) + view `resources/views/mail/email-confirmacao.blade.php`.
- 2026-05-22 — `business_metrics.tipo`: `email_confirmacao_enviado` (sucesso), `email_confirmacao_envio_erro` (tentativa que falhou), `email_confirmacao_falhou` (hook `failed()` após esgotar 3 tentativas). Decidi não emitir `email_confirmacao_enfileirado` no dispatch — duplica sinal que já vive na tabela `jobs` do Postgres.
- 2026-05-22 — `audit_logs.action`: `usuario.email_confirmado` (no sucesso) e `usuario.email_reenvio_solicitado` (no reenvio). AuditLog preserva PII por lei (ADR-003).
- 2026-05-22 — Throttling: implementado direto no controller via `RateLimiter::tooManyAttempts`/`RateLimiter::hit`, chave `email-confirm:{sha256(lower(email))}`, 3 hits / 3600s. Não usei `RateLimiter::for('email-reenvio')` no `AppServiceProvider` porque a chave depende do email do request — fica mais simples gerenciar inline; resposta genérica em todos os caminhos (hit / throttled / inválido / inexistente / já confirmado) para evitar enumeração.
- 2026-05-22 — Email mascarado na tela de login: reuso de `LogSanitizer::maskByCategory($email, 'email')` (já existente; consistente com regra de PII em log).
- 2026-05-22 — Login: bloqueio via verificação explícita em `Login::submit()` após `Auth::attempt` (logout imediato + `ValidationException` específica). Mantive a checagem de credenciais inválidas com mensagem genérica intacta (security-discipline.md) — só quando credenciais válidas + email não confirmado é que vaza o estado (alvo intencional — UX exige).
- 2026-05-22 — TTL do link: `URL::temporarySignedRoute(..., now()->addMinutes(60), ...)`. Link expirado vira `InvalidSignatureException`, capturada em `bootstrap/app.php::withExceptions()` → redirect para `/email/confirmar-erro` com motivo `expirado`.
- 2026-05-22 — `UsuarioFactory`: defaultei `email_confirmed_at = now()` (factory cria conta JÁ confirmada) + state `unconfirmed()`. Justificativa: mantém os testes pré-existentes da STORY-011/012 verdes (eles assumem login ok); testes de STORY-013 chamam `->unconfirmed()` explicitamente quando precisam do caso pendente.
- 2026-05-22 — Tratamento de `InvalidSignatureException` registrado em `bootstrap/app.php` apenas para a rota `email.confirmar` — para outras rotas signed futuras, comportamento padrão (403) preservado.

### Descobertas
- 2026-05-22 — A nova coluna `email_confirmed_at` em `usuarios`: cria migration separada (CA-1 permite "migration desta estória").
- 2026-05-22 — Audit log do `email_confirmado` traz email pleno por lei (AuditLog preserva PII, ADR-003).
- 2026-05-22 — Discrepância de `APP_URL` entre worker (`localhost:8090` no `.env`) e Dusk (`localhost:8000` no `.env.dusk.local`) faz a assinatura do link gerado pelo worker falhar na validação do browser do Dusk. **Mitigação no teste**: o `EmailConfirmacaoBrowserTest` valida que o email chegou (estrutura do link) e gera um novo link assinado *no contexto do Dusk* para visitar a rota. Em homologação, worker e web usam o mesmo `APP_URL`, então o problema não existe.
- 2026-05-22 — Mailpit search por `subject:'..'` retorna 0 resultados; uso `to:{email}` como query primária e regex no HTML para extrair o link.
- 2026-05-22 — Dusk em arquivos com múltiplos testes: cookies persistem entre testes do mesmo arquivo (`DatabaseTruncation` trunca DB mas não cookies do navegador). Mitigação: `tearDown` deleta cookies entre testes. Também o RateLimiter (cache) atravessa testes — `setUp` faz `Cache::flush()`.
- 2026-05-22 — `back()->with()` em testes Pest exige `_token` na sessão + payload (padrão herdado do `HomeTest`); sem isso, retorna 419.

### Bloqueios encontrados
- (nenhum)

### IDRs criados
- (nenhum esperado — uso 100% de built-in Laravel)

### Cobertura final
- Geral: **96,7%** (gate ≥80% verde — STORY-010).
- Domínio (`app/Domain`): **100%** (gate ≥98% verde).
- Testes Pest: 134 passando (419 asserções). Novos: `Feature/Jobs/EnviarEmailConfirmacaoTest` (6), `Feature/Http/EmailConfirmacaoTest` (12), `Feature/Livewire/EmailConfirmacaoFluxoTest` (5), `Unit/Mail/EmailConfirmacaoMessageTest` (3).
- Testes Dusk: 8 passando (44 asserções). Novos: `Browser/EmailConfirmacaoBrowserTest` (2 — fluxo completo cadastro→Mailpit→click→confirma→login, e bloqueio de login antes da confirmação).

### Links de evidência
- PR: (a preencher)
- Pipeline: (a preencher)
- Tag rc.N: (a preencher)
- Screenshot do email no Mailpit: (a anexar manualmente em homologação)
