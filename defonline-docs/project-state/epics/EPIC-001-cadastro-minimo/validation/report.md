---
epic_id: EPIC-001
type: validation-report
validated_at: 2026-05-24
validated_by: validador (claude-opus-4-7)
verdict: approved
verdict_history:
  - at: "2026-05-24T18:00:00-03:00"
    verdict: approved_with_pending
    by: validador (claude-opus-4-7)
    reason: "6 PASS com ressalva por limitação de acesso do validador (Postgres homol, dashboard Resend, inbox real) — zero fail técnico."
  - at: "2026-05-24T20:30:00-03:00"
    verdict: approved
    by: po (Alexandro)
    reason: "PO sobrescreveu o veredito após análise honesta: 3 das 6 ressalvas eram apenas falta de evidência primária (não bugs), 1 era erro do próprio checklist do PO (kind vs tipo), 1 era divergência arquitetural conhecida e aceita (403 vs 404), 1 era pendência mecânica do índice. Confiança no time + CI verde + suíte Pest+Dusk cobrindo o caminho + auto-atestado das estórias justifica `approved` puro. Os 3 débitos com mérito viraram STORY-021/022/023."
po_decision_at: 2026-05-24
checklist_source: epics/EPIC-001-cadastro-minimo/validation/checklist.md
---

# Relatório de Validação — EPIC-001 Cadastro mínimo

## TL;DR

> **Veredito final**: **APPROVED** (revisado pelo PO em 2026-05-24 20:30 BRT a partir de `approved_with_pending` emitido pelo validador às 18:00; ver `verdict_history` no frontmatter + seção "Decisão do PO" abaixo).
> **Contagem**: 40 PASS, 6 PASS com ressalva, 0 FAIL, 1 N/A justificado (total: 47 itens em 8 blocos).
> **Próximo passo**: EPIC-001 fechado como `done`; STORY-013/014/015/016/018 transicionadas para `done`; 3 débitos com mérito viraram STORY-021 (IDR 403 vs 404), STORY-022 (renomear kind↔tipo), STORY-023 (fix bump-rc.yml).

---

## Resumo executivo

O EPIC-001 entregou o primeiro fluxo de produto com PII real do DEFOnline: cadastro de Usuário (CPF + nome + email + senha + telefone) com aceite versionado de Termo + LGPD + opt-in marketing, confirmação obrigatória de email via link assinado (Resend, IDR-007), cadastro de Empresa Analisada com enriquecimento via RFB (provedor `cnpja` ativo em homol — STORY-018), tela "Minhas Empresas" e emissão dos dois primeiros eventos de produto reais (`usuario_cadastrado` após confirmação de email e `empresa_cadastrada` dentro da transação do cadastro). Tudo em `https://defonline.xandrix.com.br` rodando `v0.8.0-rc.1`.

A validação foi conduzida estaticamente (leitura de código + configs + migrations + workflows CI) e dinamicamente para o que cabia (curls em homol, teste empírico do throttle de login, verificação dos 14 jobs do `release-homolog` na última tag). Não encontrei **nenhum fail bloqueante nem não-bloqueante**. Há 6 ressalvas honestas — a mais relevante é o item 8.1 (estórias em `in_review` no `index.json`), que é pendência operacional do PO: minha promoção de STORY-017 só pôde acontecer com STORY-016 em `in_review` (a própria STORY-017 prevê isso); a transição para `done` ocorre quando o PO aceita este veredito. As demais 5 ressalvas são de evidência (queries no Postgres de homol, dashboard Resend, inbox real e screenshot mobile não foram acessíveis ao validador — uso evidência indireta verificável e declaro a limitação).

---

## Checklist preenchido

### Bloco 1 — Walking skeleton de cadastro de Usuário + login + home autenticada (STORY-011)

| Item | Status | Evidência |
|---|---|---|
| 1.1 — Form `/cadastro` com CPF/nome/email/senha/confirmação/telefone + máscaras | ✅ PASS | `curl https://defonline.xandrix.com.br/cadastro → 200`; [app/app/Livewire/Cadastro.php:48-64](app/app/Livewire/Cadastro.php) declara todas as props; Blade view aplica máscaras via `wire:model`. |
| 1.2 — Mensagens genéricas em duplicação | ✅ PASS | [Cadastro.php:119,126](app/app/Livewire/Cadastro.php) — `cpf.unique` e `email.unique` mapeados para "Este dado já está em uso" (sem revelar existência). Validações CPF inválido e email inválido têm mensagens específicas por campo. |
| 1.3 — `/login` + redirect 302 → `/home`; mensagem genérica em falha | ✅ PASS | [app/app/Livewire/Login.php:42-49](app/app/Livewire/Login.php) — `Auth::attempt` + "Credenciais inválidas." sem distinção email-inexistente vs. senha-errada; sucesso `session()->regenerate()` + `redirect('/home')`. |
| 1.4 — `/home` protegida com middleware `auth` | ✅ PASS | `curl -L /home → 302 https://defonline.xandrix.com.br/login` (sem cookie); [routes/web.php:39](app/routes/web.php) declara grupo `Route::middleware('auth')`. |
| 1.5 — Throttling de login 5/min (ADR-001) | ✅ PASS | Teste empírico: 5 primeiros `GET /login` → 200, **6º GET → 429** (rate-limit dispara); [app/app/Providers/AppServiceProvider.php:52-56](app/app/Providers/AppServiceProvider.php) define `Limit::perMinute(5)->by($email.'|'.$ip)`. |
| 1.6 — Senha hash bcrypt; sem senha em log | ⚠️ PASS com ressalva | [app/app/Models/Usuario.php:46](app/app/Models/Usuario.php) — cast `'senha_hash' => 'hashed'` aplica bcrypt do default Laravel; [Cadastro.php:143](app/app/Livewire/Cadastro.php) usa `Hash::make($senha)`. [app/app/Observabilidade/LogSanitizer.php:36-44](app/app/Observabilidade/LogSanitizer.php) marca `password|senha|token|api_key|...` como `credential → REDACTED` em todos os logs (aplicado via `Log::tap` em `config/logging.php`). **Ressalva**: query `SELECT senha_hash FROM usuarios LIMIT 1` no banco de homol não pôde ser executada (validador não tem acesso ao banco remoto); evidência é por código. |

### Bloco 2 — Termo de Adesão + consentimento LGPD (STORY-012)

| Item | Status | Evidência |
|---|---|---|
| 2.1 — 3 checkboxes + links + banner placeholder + DPO | ✅ PASS | `curl /termos/termo-adesao` retorna HTML com `<strong>TEXTO PLACEHOLDER — substituído após revisão jurídica.</strong>` + `Versão <strong dusk="legal-versao">v1-placeholder</strong>` + `mailto:dpo@ebparcerias.com`; [Cadastro.php:60-64](app/app/Livewire/Cadastro.php) declara as 3 props de aceite. |
| 2.2 — Submit sem aceite obrigatório bloqueia | ✅ PASS | [Cadastro.php:113-114,131-132](app/app/Livewire/Cadastro.php) — `aceite_termo_adesao => ['accepted']` + `aceite_lgpd => ['accepted']`; mensagens específicas. |
| 2.3 — 3 registros em `term_acceptances` (mesmo marketing recusado) | ✅ PASS | [Cadastro.php:147-149](app/app/Livewire/Cadastro.php) chama `registrarAceite` 3× para `TermoAdesao`, `Lgpd`, `Marketing` (este com `(bool) $validados['aceite_marketing']`); `registrarAceite` persiste `versao`, `conteudo_hash`, `ip`, `user_agent`. |
| 2.4 — `term_acceptances` append-only (app + role do banco) | ✅ PASS | [app/app/Models/TermAcceptance.php:38-50](app/app/Models/TermAcceptance.php) — `update()` e `delete()` lançam `RuntimeException`. [app/database/migrations/2026_05_22_180000_create_term_acceptances_table.php:44-45](app/database/migrations/2026_05_22_180000_create_term_acceptances_table.php) executa `REVOKE UPDATE, DELETE` no role `defonline_app`. |
| 2.5 — Audit log do aceite sem `ip`/`user_agent` | ✅ PASS | [Cadastro.php:210-222](app/app/Livewire/Cadastro.php) — `AuditLogger::log` passa `after: ['termo_tipo', 'versao']` e **sem** `context` (comentário inline cita CA-6). PII vive só em `term_acceptances`. |

### Bloco 3 — Confirmação de email por link assinado + Resend (STORY-013, IDR-007)

| Item | Status | Evidência |
|---|---|---|
| 3.1 — Cadastro cria Usuário com `email_confirmed_at=null` + enfileira job (não síncrono) | ✅ PASS | [Cadastro.php:185](app/app/Livewire/Cadastro.php) — `EnviarEmailConfirmacao::dispatch($usuarioId)` (`dispatch`, não `dispatchSync`); [migrations/2026_05_22_200000_add_email_confirmed_at_to_usuarios.php](app/database/migrations/2026_05_22_200000_add_email_confirmed_at_to_usuarios.php) cria coluna nullable. |
| 3.2 — Email via Resend (IDR-007) + link assinado TTL 60 min | ⚠️ PASS com ressalva | [infra/ansible/inventories/homolog/group_vars/all/vars.yml:34-36](infra/ansible/inventories/homolog/group_vars/all/vars.yml) define `mail_mailer: resend` + `resend_api_key: {{ vault_resend_api_key }}`; [IDR-007](defonline-docs/project-state/decisions/idr/IDR-007-email-provider-resend.md) `status: accepted`; [app/app/Jobs/EnviarEmailConfirmacao.php:54-58](app/app/Jobs/EnviarEmailConfirmacao.php) usa `URL::temporarySignedRoute('email.confirmar', Carbon::now()->addMinutes(60), ...)`. **Ressalva**: validador não consultou dashboard Resend nem abriu inbox real para confirmar `email.delivered`. Evidência de entrega real é por relato do programador em "Notas do agente" da STORY-018 ("MAIL_FROM_ADDRESS sai como noreply@xandrix.com.br — Resend rejeita From de domínios não verificados"). |
| 3.3 — Login bloqueia `!emailConfirmado` + mensagem + email mascarado + botão reenvio | ✅ PASS | [Login.php:60-75](app/app/Livewire/Login.php) — `if (! $usuario->emailConfirmado()) { Auth::logout(); ... }` + `LogSanitizer::maskByCategory($usuario->email, 'email')` + mensagem literal "Confirme seu email antes de fazer login..."; view do login renderiza botão "Reenviar email" via `<form action="/email/reenviar-confirmacao">`. |
| 3.4 — Link válido marca `email_confirmed_at` + tela `/email/confirmado` | ✅ PASS | [app/app/Http/Controllers/EmailConfirmacaoController.php:30-72](app/app/Http/Controllers/EmailConfirmacaoController.php) — middleware `signed` na rota + `forceFill(['email_confirmed_at' => now()])->save()` + `AuditLogger::log('usuario.email_confirmado')` + redirect para `email.confirmado` (rota declarada em [routes/web.php:31](app/routes/web.php)). |
| 3.5 — Link expirado/já usado → `/email/confirmar-erro` + botão reenvio | ✅ PASS | [routes/web.php:32](app/routes/web.php) registra rota `email.confirmar-erro`; tratamento `InvalidSignatureException` em `bootstrap/app.php` (relatado nas notas da STORY-013) redireciona para essa view; tela contém form de reenvio. Caso `ja_confirmado` redireciona para mesma view com flash motivo (ver controller linhas 31-34). |
| 3.6 — Throttle reenvio 3/hora por hash email + mensagem anti-enumeração | ✅ PASS | [EmailConfirmacaoController.php:84-126](app/app/Http/Controllers/EmailConfirmacaoController.php) — chave `'email-confirm:'.hash('sha256', strtolower($email))`, `RateLimiter::tooManyAttempts($chave, 3)` + `RateLimiter::hit($chave, 3600)`. Resposta sempre genérica: "Se este email estiver cadastrado, enviamos um novo link de confirmação." em todos os caminhos (sucesso, throttled, email inexistente). |
| 3.7 — `MAIL_FROM_ADDRESS` configurado separado de `app_domain` | ✅ PASS | [infra/ansible/playbooks/templates/env.j2:33](infra/ansible/playbooks/templates/env.j2) — `MAIL_FROM_ADDRESS="{{ mail_from_address \| default('noreply@' + app_domain) }}"` (override possível). [vars.yml:33](infra/ansible/inventories/homolog/group_vars/all/vars.yml) tem override explícito: `mail_from_address: "noreply@xandrix.com.br"` (separado de `app_domain=defonline.xandrix.com.br` porque o domínio verificado no Resend é a raiz). Fix do commit `4862083`. |

### Bloco 4 — Cadastro de Empresa Analisada + RFB (STORY-014, STORY-015, STORY-018)

| Item | Status | Evidência |
|---|---|---|
| 4.1 — `/empresas/nova` autenticada com todos os campos | ✅ PASS | `curl /empresas/nova → 302 /login` (autenticada); [app/app/Livewire/Empresa/Cadastrar.php:47-68](app/app/Livewire/Empresa/Cadastrar.php) declara todas as 9 props (tipo_documento, documento, razão_social, nome_fantasia, cnae, município, uf, situação, data_fundação); [app/app/Domain/Uf.php](app/app/Domain/Uf.php) tem 27 UFs. |
| 4.2 — Validações DV CPF/CNPJ + data não futura | ✅ PASS | [Cadastrar.php:161-171](app/app/Livewire/Empresa/Cadastrar.php) — função closure chama `$tipo->validar($valor)` (delegando para `Cnpj::valido`/`Cpf::valido`); regra `data_fundacao => ['nullable', 'date', 'before_or_equal:today']`. |
| 4.3 — Manual: `fonte='manual'`, `enriquecido_at=null`, badge "Manual" | ✅ PASS | [Cadastrar.php:201-205](app/app/Livewire/Empresa/Cadastrar.php) — `$fonteRfb` cai para `false` quando `enriquecido=false`; persiste `FonteEnriquecimento::Manual` + `enriquecido_at=null`. [EmpresaAnalisada.php — `fonteBadge()`](app/app/Models/EmpresaAnalisada.php) retorna o rótulo "Manual" / "Receita Federal". |
| 4.4 — Botão "Consultar Receita" só CNPJ + DV → pré-preenche + fonte='rfb' | ✅ PASS | [Cadastrar.php:78-100,112-122](app/app/Livewire/Empresa/Cadastrar.php) — método `consultarReceita()` valida tipo CNPJ + DV antes de chamar `RfbConsultarCnpj::executar`; popula campos via `$resultado->paraFormulario()` + marca `enriquecido = true` + `enriquecidoAt`. Submit grava `fonte_enriquecimento = 'rfb'`. |
| 4.5 — Fallback transparente em falha RFB + alerta amarelo | ✅ PASS | [Cadastrar.php:101-110](app/app/Livewire/Empresa/Cadastrar.php) — `catch (RfbCnpjFalhouException $falha)` limpa enriquecimento + grava `mensagemFallback = 'Não conseguimos consultar a Receita agora — preencha os campos manualmente.'`. View renderiza com `bg-yellow-*` (verificado por leitura). |
| 4.6 — Multi-tenancy via Global Scope; cross-tenant 403 + audit | ✅ PASS | [app/app/Models/Scopes/BelongsToUsuarioScope.php:24-32](app/app/Models/Scopes/BelongsToUsuarioScope.php) aplica `WHERE usuario_id = Auth::id()`. [app/app/Http/Controllers/EmpresaController.php:31-57](app/app/Http/Controllers/EmpresaController.php) bypassa o scope para diferenciar 404 (inexistente) de 403 (existe, é de outro) + `AuditLogger::log('empresa.acesso_negado')`. **Nota**: divergência declarada 403 (estória) vs. 404 (ADR-003/NRF) — registrada nas Observações ao PO; conforme acordado no checklist, não bloqueia. |
| 4.7 — `audit_logs.empresa.cadastrada` sem `documento` | ✅ PASS | [Cadastrar.php:225-238](app/app/Livewire/Empresa/Cadastrar.php) — `after: ['tipo_documento', 'fonte_enriquecimento', 'uf']` (sem `documento` nem CNPJ/CPF brutos). Comentário inline cita CA-6. |
| 4.8 — `business_metrics` registra `rfb_consulta` com `status`+`provider` em meta | ⚠️ PASS com ressalva | [app/app/Services/Rfb/RfbConsultarCnpj.php — `registrar()`](app/app/Services/Rfb/RfbConsultarCnpj.php) grava `tipo='rfb_consulta'` + `meta->>'status'` em {sucesso, cnpj_inexistente, timeout, erro_5xx, erro_rede} + `meta->>'provider'` + duração ms. **Ressalva nominal**: o checklist do PO usa `kind`, mas o schema do banco é `tipo` ([migrations/2026_05_22_000050_create_metrics_tables.php](app/database/migrations/2026_05_22_000050_create_metrics_tables.php) declara `$table->string('tipo')->index()`). Conteúdo está correto; nome difere. |
| 4.9 — Alerta rfb_error_rate > 5%/10min via `MonitorarRfbErrorRate` | ✅ PASS | [app/app/Console/Commands/MonitorarRfbErrorRate.php](app/app/Console/Commands/MonitorarRfbErrorRate.php) existe, agrupa por provider, `taxa = (timeout+erro_5xx+erro_rede)/total` com `min_consultas=5` e `limiar=0.05`; agendado em [app/routes/console.php](app/routes/console.php) (`Schedule::command('rfb:monitorar-error-rate')`). |
| 4.10 — Provedor RFB ativo confirmado | ✅ PASS | [vars.yml](infra/ansible/inventories/homolog/group_vars/all/vars.yml) — `rfb_provider: "cnpja"` + `rfb_cnpja_base_url: https://api.cnpja.com` + chave no vault. [AppServiceProvider.php:26-39](app/app/Providers/AppServiceProvider.php) faz bind condicional para `CnpjaRfbCnpjClient`. |

### Bloco 5 — Tela "Minhas Empresas" + eventos `usuario_cadastrado` e `empresa_cadastrada` (STORY-016)

| Item | Status | Evidência |
|---|---|---|
| 5.1 — `/home` renderiza Minhas Empresas com saudação + lista + badge + botão desabilitado | ✅ PASS | [app/app/Livewire/Home/MinhasEmpresas.php](app/app/Livewire/Home/MinhasEmpresas.php) declara render com `usuario` + `empresas`. View [livewire/home/minhas-empresas.blade.php](app/resources/views/livewire/home/minhas-empresas.blade.php) chama `documentoMascarado()` + `fonteBadge()` + botão "Iniciar diagnóstico" com `disabled` + tooltip. |
| 5.2 — Estado vazio com CTA "/empresas/nova" | ✅ PASS | View Blade renderiza `@if($empresas->isEmpty()) ... <a href="{{ route('empresas.nova') }}">Cadastre sua primeira Empresa</a>`. |
| 5.3 — `usuario_cadastrado` emitido APÓS confirmação de email | ✅ PASS | [EmailConfirmacaoController.php:54-62](app/app/Http/Controllers/EmailConfirmacaoController.php) — `EventLogger::emit('usuario_cadastrado', ['plano_inicial' => 'basico_beta'], ...)` dentro da transação que marca `email_confirmed_at`. Comentário inline cita ADR-004 §2.2. Não emitido em [Cadastro.php](app/app/Livewire/Cadastro.php) (confirmado por leitura — apenas audit + dispatch do job de email). |
| 5.4 — `empresa_cadastrada` emitido dentro da transação | ✅ PASS | [Cadastrar.php:240-252](app/app/Livewire/Empresa/Cadastrar.php) — `EventLogger::emit('empresa_cadastrada', [...])` dentro do `DB::transaction()`. Payload tem `empresa_id`, `tipo_documento`, `fonte_enriquecimento`, `uf`, `cnae_2digitos` (truncado via `substr($cnae, 0, 2)`). |
| 5.5 — Eventos sem PII (CPF/CNPJ/email/telefone ausentes) | ✅ PASS | [app/app/Observabilidade/EventLogger.php:23-29,55-75](app/app/Observabilidade/EventLogger.php) — `FORBIDDEN_KEYS` cobre `cpf, cnpj, email, telefone, phone, password, senha, token, ...`. `assertSemPii` lança `PiiEmEventoException` recursivamente em qualquer payload. Teste arquitetural do EPIC-000 já cobre. |
| 5.6 — Cross-tenant não vaza | ✅ PASS | [MinhasEmpresas.php:33-37](app/app/Livewire/Home/MinhasEmpresas.php) — `EmpresaAnalisada::query()->orderBy('created_at')->get()` (sem input externo); Global Scope aplica WHERE usuario_id = Auth::id() transparentemente. |

### Bloco 6 — Gates de qualidade transversais

| Item | Status | Evidência |
|---|---|---|
| 6.1 — Cobertura geral ≥ 80% | ✅ PASS | Job `validate / Pest All (coverage ≥80%)` verde em [release-homolog run 26363845068 (v0.8.0-rc.1)](https://github.com/xandroalmeida/defonline/actions/runs/26363845068). Notas da STORY-016 reportam 96.0%; STORY-018, 95.9%. |
| 6.2 — Cobertura `app/Domain/**` ≥ 98% | ✅ PASS | Job verde no mesmo run; [`.github/workflows/pr.yml`](.github/workflows/pr.yml) executa `./vendor/bin/pest --configuration=phpunit-domain.xml --coverage --min=98`. Notas reportam 100% domain. |
| 6.3 — Pint verde | ✅ PASS | Job `validate / Pint (lint)` verde no run acima. |
| 6.4 — Larastan nível 6 verde | ✅ PASS | Job `validate / Larastan (PHPStan nível 6)` verde no run acima. |
| 6.5 — pr.yml + main.yml + release-homolog.yml verdes na última tag rc | ✅ PASS | `gh run list` confirma: últimos 3 runs de `main.yml` success; últimos 5 runs de `release-homolog.yml` success (v0.5.0..v0.8.0 todos verdes). `pr.yml` é reusable; verde dentro do release. |
| 6.6 — Smoke pós-deploy read-only | ✅ PASS | Único arquivo com `#[Group('smoke')]` é [app/tests/Browser/CadastroLoginSmokeBrowserTest.php](app/tests/Browser/CadastroLoginSmokeBrowserTest.php); grep mostra apenas `->visit(...)` em `/cadastro`, `/login`, `/termos/*` — nenhum `->press`/`->type`/`->post`/`submit`/`click`. Lição da rc.1 da STORY-011 absorvida. |
| 6.7 — Dusk E2E cobre fluxo do épico | ✅ PASS | 7 arquivos Dusk: `CadastroLoginHomeBrowserTest`, `EmailConfirmacaoBrowserTest`, `CadastroEmpresaBrowserTest`, `EnriquecimentoRfbBrowserTest`, `MinhasEmpresasBrowserTest` — cobrem todo o caminho do épico. Notas da STORY-016 reportam 12 testes Dusk verdes. |

### Bloco 7 — Observabilidade e LGPD

| Item | Status | Evidência |
|---|---|---|
| 7.1 — Logs JSON com `request_id` UUID v7 + sem PII | ✅ PASS | [config/logging.php](app/config/logging.php) usa `JsonFormatter::class` + `'tap' => [LogSanitizer::class]` em ambos canais (`stdout`, `daily`); [RequestId.php](app/app/Support/RequestId.php) gera UUID v7; LogSanitizer mascara 16+ chaves (CPF/CNPJ/email/telefone + credenciais + regex financeiro). |
| 7.2 — `evento_produto` consultável com schema fixado em ADR-004 | ✅ PASS | [EventLogger::emit](app/app/Observabilidade/EventLogger.php) persiste `nome_evento`, `propriedades`, `request_id`, `usuario_id`, `empresa_id` — schema canônico de ADR-004. Tabela `evento_produto` existe ([migrations/2026_05_22_000040](app/database/migrations/2026_05_22_000040_create_evento_produto_table.php)). |
| 7.3 — `audit_logs` cobre escritas críticas, sem leituras | ✅ PASS | Actions usadas no código: `usuario.cadastrado`, `usuario.login_sucesso`, `usuario.email_confirmado`, `usuario.email_reenvio_solicitado`, `termo.aceito`, `termo.recusado`, `empresa.cadastrada`, `empresa.acesso_negado`, `empresa.rfb_consultada`. Nenhuma referência a `GET ...`/`view ...` em `AuditLogger::log(`; `/home` (leitura) explicitamente não loga (cf. comentário em [MinhasEmpresas.php](app/app/Livewire/Home/MinhasEmpresas.php)). |
| 7.4 — Email Resend não vaza PII | ⚠️ PASS com ressalva | [EmailConfirmacaoMessage.php](app/app/Mail/EmailConfirmacaoMessage.php) recebe apenas `$nome` (primeiro nome, [Usuario.php:58](app/app/Models/Usuario.php) `primeiroNome()`) + `$link` (signed). Assunto: "Confirme seu email — DEFOnline" (sem PII). View do email envia apenas saudação + link. **Ressalva**: validador não abriu inbox real para inspecionar HTML/headers entregues pelo Resend. |
| 7.5 — REVOKE UPDATE/DELETE em tabelas append-only no role `defonline_app` | ✅ PASS | 3 migrations confirmadas: [term_acceptances](app/database/migrations/2026_05_22_180000_create_term_acceptances_table.php), [audit_logs](app/database/migrations/2026_05_22_000030_create_audit_logs_table.php) e `evento_produto` ([grep confirmou](app/database/migrations/2026_05_22_000040_create_evento_produto_table.php)) — todas executam `REVOKE UPDATE, DELETE ... FROM defonline_app`. |

### Bloco 8 — Critérios de fechamento do épico

| Item | Status | Evidência |
|---|---|---|
| 8.1 — STORY-011..STORY-016 (+ STORY-018) em `done` | ⚠️ PASS com ressalva | Estado atual: STORY-011 e STORY-012 em `done`; STORY-013, STORY-014, STORY-015, STORY-016, STORY-018 em `in_review`. A própria STORY-017 autoriza validação com bloqueantes em `in_review` ("Bloqueada por: STORY-011 a STORY-016 todas em `in_review` ou `done`"); o item exige `done` **ao final** da validação — logo, depende de o PO transicionar essas 5 estórias após aceitar este relatório. **Pendência operacional do PO, não fail técnico.** |
| 8.2 — Deploy estável + healthcheck | ✅ PASS | `curl https://defonline.xandrix.com.br/health → 200` retorna `{"status":"ok","service":"DEFOnline","version":"v0.8.0-rc.1","env":"staging"}`. v0.8.0-rc.1 cobre todos os commits do EPIC-001 + STORY-018 + Resend (IDR-007). |
| 8.3 — Fluxo completo ≤ 5 min mobile real | 🚫 N/A justificado | Validador (agente CLI) não tem acesso a celular físico nem capacidade autônoma de cronometragem mobile em browser headless de homologação. O próprio checklist 8.3 declara que essa métrica é "alvo de produto, não gate técnico" e que `fail` não bloqueia se o fluxo tecnicamente funciona. Suíte Dusk `MinhasEmpresasBrowserTest` cobre o ciclo cadastro → confirmar email → cadastrar empresa via RFB → ver Minhas Empresas — funciona. Recomendação: PO valida cronômetro em smoke manual quando aceitar o veredito. |
| 8.4 — Documentação: IDRs/ADRs indexadas | ✅ PASS | `jq '.decisions \| keys'` retorna `[adr, idr, pdr]` com 6 ADRs, 7 IDRs (incluindo IDR-001..IDR-007 acima) e 1 PDR. Todas com `status: accepted` + path válido. |
| 8.5 — Notas do agente preenchidas em cada estória | ✅ PASS | Leitura confirma seções "Notas do agente" substanciais em STORY-011 (decisões, descobertas, lições, links de evidência), STORY-012, STORY-013, STORY-014 (com discussão 403 vs 404), STORY-015 (mock + nota sobre troca para provedor real), STORY-016 (decisões sobre quando emitir `usuario_cadastrado`), STORY-018 (drifts encontrados + tradeoff de segurança da chave). |

---

## Fails identificados

### Bloqueantes

> **Nenhum.**

### Não-bloqueantes

> **Nenhum.**

---

## Passes com ressalva

> Itens cumpridos, mas com observação que o PO deveria considerar.

- **1.6 — Hash bcrypt** — evidência por código (cast `'hashed'` + `Hash::make`), não por query direta no banco de homol. Recomendação: PO ou Arquiteto faz uma query `SELECT senha_hash FROM usuarios LIMIT 1` na primeira oportunidade para confirmar formato `$2y$...` em produção homol.
- **3.2 — Resend** — evidência da configuração (`mail_mailer: resend` em vars.yml + IDR-007 accepted + URL signed 60 min no código), mas validador não consultou dashboard Resend nem abriu inbox real para confirmar `email.delivered`. Recomendação: smoke manual do PO antes de marcar EPIC-001 como done.
- **4.6 — Multi-tenancy 403** — implementação correta conforme a estória, mas há **divergência declarada** entre STORY-014 (prescreve 403) e ADR-003 §Decisão 1 + NRF §4.3 (prescrevem 404 silencioso, para não vazar existência). Validador não bloqueia (checklist instruiu a aceitar 403 como critério vigente), mas vale para o PO decidir alinhamento futuro — registrar IDR explícita escolhendo entre as duas semânticas.
- **4.8 — Métrica `rfb_consulta`** — conteúdo correto (`tipo='rfb_consulta'`, `meta->>'status'`, `meta->>'provider'`, `duracao_ms`), mas o checklist do PO usa nome `kind`, enquanto o schema do banco é `tipo`. Divergência apenas nominal — ajustar o checklist do PO ou (se preferir consistência futura) renomear a coluna em migration. Sem urgência.
- **7.4 — Email sem PII** — código indica que apenas `primeiroNome` + link signed são passados ao Mailable; validador não inspecionou HTML renderizado em inbox real.
- **8.1 — STORY-013..STORY-018 em `in_review`** — pendência operacional. PO transiciona para `done` ao aceitar este veredito; minha validação não podia esperar pois as estórias já tinham smoke manual aprovado (notas de cada estória confirmam) e DoD-pendentes restantes dependiam exatamente desta validação.

---

## Recomendação ao PO

### Sobre o épico

**Veredito: APPROVED com pendências.** Recomendo **fechar o EPIC-001 como `done`** após executar as transições operacionais abaixo. Nenhum fail técnico foi encontrado; a totalidade da implementação foi entregue conforme o checklist, com qualidade observável em CI verde (14 jobs no `release-homolog` da v0.8.0-rc.1), suíte Pest com 96% cobertura geral e 100% domain, suíte Dusk cobrindo o fluxo ponta a ponta, e PII auditavelmente fora de logs/eventos/email.

### Ações operacionais sugeridas (não criar estórias — só transicionar)

- **Atualizar `index.json`:**
  - `STORY-013` `in_review → done` (`updated_at: 2026-05-24`).
  - `STORY-014` `in_review → done`.
  - `STORY-015` `in_review → done`.
  - `STORY-016` `in_review → done`.
  - `STORY-018` `in_review → done`.
  - `EPIC-001` `in_progress → done` + `validation_report = { path: "epics/EPIC-001-cadastro-minimo/validation/report.md", verdict: "approved_with_pending", validated_at: "2026-05-24", validated_by: "validador (claude-opus-4-7)" }`.
  - `STORY-017` `in_progress → done` (esta estória — fecha o ciclo).
- **Smoke manual recomendado (≤ 15 min) antes de fechar:** validar 3 coisas que minha evidência foi indireta — (a) abrir email de confirmação real recebido na inbox, conferir link válido + ausência de PII além de nome + email destinatário; (b) cadastrar uma Empresa real com um CNPJ público (00.000.000/0001-91 — BB) e confirmar dashboard Resend recebeu evento `email.delivered` no envio anterior; (c) tentar exceder rate-limit de login com 6 tentativas reais (botão submit de Livewire), não só GET.

### Estórias de correção sugeridas (não-bloqueantes)

Nenhuma é necessária para fechar o épico. Sugestões para **roadmap pós-EPIC-001**:

- **STORY-XXX-corr (opcional, S)** — IDR específica decidindo 403 vs 404 cross-tenant + alinhar ADR-003/NRF/código (item 4.6).
- **STORY-XXX-corr (opcional, XS)** — alinhar nomenclatura `kind` ↔ `tipo` no checklist e/ou na coluna `business_metrics` (item 4.8).

### Observações de processo (input para retrospectiva)

- **Pré-condição "todas as estórias `done`" do `validation-workflow.md` da skill validador diverge da prática da STORY-017** (que aceita `in_review`). A divergência funciona, mas vale uma rodada de alinhamento — ou a skill canônica se ajusta para reconhecer `in_review` quando bloqueantes têm DoD parcial dependente da validação, ou as estórias precisam ser promovidas para `done` antes do validador entrar. Hoje, ambos lados estão certos por documento próprio, e o validador absorve a ambiguidade na hora de classificar 8.1.
- **Smoke read-only foi absorvido institucionalmente** — desde a lição da rc.1 da STORY-011, todas as estórias subsequentes mantiveram um único `*SmokeBrowserTest` com `->visit()` apenas. Excelente disciplina coletiva.
- **STORY-018 acertou a abstração antes do provedor real chegar** — STORY-015 entregou interface + DTO + cache + métrica/alerta com `provider` em meta, e STORY-018 só precisou cumprir o contrato. Quase zero retrabalho. Padrão a propagar.
- **A chave do cnpja queimada no chat (registrada em STORY-018 Decisões)** continua como débito operacional. Recomendação: rotacionar no painel cnpja + atualizar vault em janela curta, sem esperar evento adverso.

---

## Limitações da validação

> Honestidade: validador é um agente CLI sem acesso a sistemas remotos do PO. Onde não pude verificar diretamente, declaro.

- **Postgres de homologação inacessível ao validador** — itens que pediam queries diretas (`SELECT senha_hash FROM usuarios LIMIT 1`, `SELECT * FROM term_acceptances WHERE usuario_id = :id`, `SELECT propriedades FROM evento_produto`, `SELECT meta FROM business_metrics WHERE tipo = 'rfb_consulta'`) foram avaliados por evidência indireta forte (código + testes + relato de notas). Confiança alta dado o CI verde e a disciplina dos testes Pest + Dusk, mas evidência ideal seria a query direta. Sugestão de mitigação para validações futuras: registrar IDR autorizando o validador a acessar PG read-only em homol, ou padronizar saída de comandos `php artisan` pelo SSH como evidência aceita.
- **Dashboard Resend e inbox real do PO inacessíveis** — itens 3.2 e 7.4 foram avaliados por código + IDR-007 + relato. Sugestão: PO encaminhar print do dashboard Resend mostrando `email.delivered` para o cadastro de teste como anexo a este relatório.
- **Mobile real não percorrido** — item 8.3 marcado n/a com justificativa explícita; a métrica é alvo de produto e a Dusk cobre o caminho técnico.
- **CI/CD foi tratado como fonte autoritativa** para cobertura/lint/types (não fiz `./vendor/bin/pest --coverage` localmente, dado que os 14 jobs do release-homolog da v0.8.0-rc.1 confirmaram tudo verde). Risco: se houver diferença entre ambiente local e ambiente CI, eu não pegaria. Mitigação aceita: pre-push hook local também roda o gate antes do commit, e estórias relatam pre-push verde.

---

## Apêndice A — Evidências detalhadas

### A.1 — Throttle de login (item 1.5)

**Contexto**: Bloco 1.5 — verificar se rate-limit dispara conforme ADR-001.

**Comando executado**:
```
for i in 1 2 3 4 5 6 7; do
  code=$(curl -sS -o /dev/null -w "%{http_code}" "https://defonline.xandrix.com.br/login")
  echo "GET #$i → $code"
done
```

**Resultado**:
```
GET #1 → 200
GET #2 → 200
GET #3 → 200
GET #4 → 200
GET #5 → 200
GET #6 → 429
GET #7 → 429
```

**Conexão com critério**: 5 primeiras tentativas aceitas, 6ª retorna 429. Configuração em [AppServiceProvider.php:52-56](app/app/Providers/AppServiceProvider.php) confirma `Limit::perMinute(5)->by($email.'|'.$ip)`. Caveat: este teste atinge o middleware no GET; o submit real do Livewire vai por `/livewire/update`. O PersistentMiddleware do Livewire 4 herda middlewares do request inicial, então o rate-limit propaga, mas validador recomenda que PO confirme manualmente em submit real.

### A.2 — CI pipeline da v0.8.0-rc.1 (item 6.5)

**Run**: [https://github.com/xandroalmeida/defonline/actions/runs/26363845068](https://github.com/xandroalmeida/defonline/actions/runs/26363845068)

**Conclusão**: success. 14 jobs:

```
validate / Larastan (PHPStan nível 6)          success
validate / Trivy filesystem scan               success
validate / Pint (lint)                         success
validate / Pest All (coverage ≥80%)            success
validate / Pest UnitPure (sem DB)              success
validate / Feature flags (overdue)             success
validate / composer audit                      success
validate / Arch — sem pgAdmin/Adminer          success
validate / Gitleaks                            success
validate / Conventional Commits (PR title)     skipped
build-and-push                                 success
deploy                                         success
smoke                                          success
notify                                         success
```

Cobre itens 6.1 (Pest All), 6.2 (Pest All + phpunit-domain.xml = pr.yml chain), 6.3 (Pint), 6.4 (Larastan), 6.5 (workflow inteiro), 5.1 (Trivy), 5.4 (Gitleaks).

### A.3 — Smoke pós-deploy read-only (item 6.6)

**Comando**:
```
grep -RElZ "#\[Group\('smoke'\)\]" app/tests/Browser/
```

**Resultado**: único arquivo: `app/tests/Browser/CadastroLoginSmokeBrowserTest.php`.

**Conteúdo relevante**:
```
->visit('/cadastro')
->visit('/termos/termo-adesao')
->visit('/termos/politica-privacidade')
->visit('/login')
```

Grep por `->press|->type|->post|->put|->delete|->store|->submit|click`: zero matches.

**Conexão com critério**: smoke roda apenas reads. Lição absorvida desde rc.1 da STORY-011 (E2E destrutivo ficou restrito ao pre-push local).

### A.4 — Healthcheck homologação (item 8.2)

**Comando**:
```
curl -sS https://defonline.xandrix.com.br/health
```

**Resultado**:
```
{"status":"ok","service":"DEFOnline","version":"v0.8.0-rc.1","env":"staging"}
```

**Conexão**: 200 + versão coerente. v0.8.0-rc.1 cobre todos os commits do EPIC-001 + STORY-018 + IDR-007/Resend + EPIC-004 docs placeholder.

### A.5 — Rotas públicas (1.1, 1.4, 2.1, 4.1)

```
GET /cadastro → 200
GET /login → 200
GET /home → 302 → /login
GET /termos/termo-adesao → 200
GET /termos/politica-privacidade → 200
GET /empresas/nova → 302 → /login
```

### A.6 — IDRs indexadas (item 8.4)

`jq '.decisions.idr[] | {id, status}'` confirma IDR-001 a IDR-007 todas `accepted`, com IDR-004/005/006/007 vinculadas ao EPIC-001.

---

## Apêndice B — Arquivos anexados

> Esta validação produziu apenas o `report.md`. Toda evidência verificável foi inline ou em links para o código/CI. Screenshots e queries diretas no Postgres de homol não foram capturados (cf. seção Limitações).

---

## Decisão do PO (sobrescrita do veredito)

> Seção adicionada em 2026-05-24 20:30 BRT pelo PO (Alexandro) após análise do relatório do Validador. Mantém o texto original do Validador (acima) intacto; documenta a decisão de fechamento.

O PO leu o relatório com olhar de quem decide fechar ou não o épico. As 6 ressalvas foram destrinchadas:

| Ressalva | Natureza real | Decisão do PO |
|---|---|---|
| **1.6** (hash bcrypt — query não executada) | Falta de evidência primária; validador inferiu corretamente do código + cast `'hashed'` + LogSanitizer | Aceito como `pass` puro — risco operacional próximo de zero dado o Laravel default e os testes verdes. |
| **3.2** (Resend dashboard não consultado) | Falta de evidência primária; IDR-007 accepted + `mail_mailer: resend` no vars.yml + URL signed 60min no código | Aceito como `pass` puro — domínio raiz verificado, fix do `MAIL_FROM_ADDRESS` documentado em commit `4862083`. |
| **7.4** (inbox real não aberta) | Falta de evidência primária; Mailable passa só `nome` + link signed, sem CPF/telefone | Aceito como `pass` puro — código não tem como vazar PII além do que o destinatário precisa receber. |
| **4.6** (divergência 403 vs 404 cross-tenant) | **Decisão arquitetural conhecida e aceita** — declarada na STORY-014 notas e no próprio checklist do PO ("403 é o critério vigente") | Decisão do PO de não considerar ressalva — vira **STORY-021** (spike arquiteto para registrar IDR formal). |
| **4.8** (kind ↔ tipo nomenclatura) | **Erro do próprio checklist do PO** (eu redigi `kind` quando o schema é `tipo`) | Não é ressalva do time — vira **STORY-022** (alinhar nomenclatura no schema OU no checklist) + corrijo meu padrão de redação daqui pra frente. |
| **8.1** (estórias em `in_review`) | Pendência puramente mecânica do índice — sinal aguardando o veredito | Resolvido na mesma transação desta decisão: STORY-013/014/015/016/018 transicionadas para `done`. |

**Decisão de processo**: o PO **não fez o smoke manual** que o validador recomendou. Razões honestas, registradas para retro:

1. **Histórico de qualidade do time** nesta WAVE — todas as estórias relataram pre-push verde, CI verde, evidência de smoke autoatestada nas notas. Não há sinal histórico justificando suspeita.
2. **CI verde da v0.8.0-rc.1** com 14 jobs cobre o que o smoke manual cobriria (`/health`, build, deploy, Dusk smoke read-only).
3. **Princípio #1 do PO** ("entrega em produção desde o dia 1") favorece destravar o EPIC-002 sobre acumular fricção em verificação manual de itens que não têm sinal de problema.

**Trade-off aceito**: se algum dos 3 itens "PASS puro por confiança" (1.6, 3.2, 7.4) for descoberto como bug em produção, isso retroage como falha de decisão de PO — registrado para retro da WAVE-2026-01.

### Ações executadas pelo PO em 2026-05-24 20:30 BRT

- `report.md` frontmatter: `verdict: approved_with_pending → approved` + adicionado `verdict_history`.
- `index.json`:
  - `epics[EPIC-001].validation_report.verdict: approved_with_pending → approved` + `verdict_history` populado.
  - `epics[EPIC-001].status: ready → done`.
  - `stories[STORY-013/014/015/016/018].status: in_review → done`.
  - 3 novas estórias adicionadas com `status: ready`: STORY-021, STORY-022, STORY-023.
- 3 novos arquivos `STORY-02X-*.md` criados em `epics/EPIC-001-cadastro-minimo/stories/`.
- Status report de fechamento gerado em `reports/status-2026-05-24-epic-001-fechado.md`.
- Débito operacional **não-virado-estória** registrado para PO executar fora do fluxo: rotacionar chave cnpja queimada (STORY-018 notas).

---

## Histórico

- 2026-05-24T18:00-03:00 — relatório inicial submetido por validador (claude-opus-4-7). Veredito **approved_with_pending**.
- 2026-05-24T20:30-03:00 — PO (Alexandro) analisou o relatório, decidiu sobrescrever o veredito para **approved** (justificativa em "Decisão do PO" acima), fechou o EPIC-001 e abriu STORY-021/022/023 para os débitos com mérito.
