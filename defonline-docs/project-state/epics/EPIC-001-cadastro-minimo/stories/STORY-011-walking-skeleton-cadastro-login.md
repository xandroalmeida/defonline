---
story_id: STORY-011
slug: walking-skeleton-cadastro-login
title: Walking skeleton — cadastro de Usuário + login + home autenticada
epic_id: EPIC-001
sprint_id: SPRINT-2026-W22
type: implementation
target_role: programador
status: done
owner_agent: programador (claude-opus-4-7)
created_at: 2026-05-22
updated_at: 2026-05-22
estimated_session_size: M
---

# STORY-011 — Walking skeleton: cadastro de Usuário + login + home autenticada

> **Para o agente programador:** leia esta estória por inteiro antes de começar. Ela é a **primeira camada vertical** do EPIC-001 — atravessa form Livewire → validação → tabela `usuarios` → sessão → home autenticada. **Sem** Termo de Adesão, **sem** confirmação de email — entram nas STORY-012 e STORY-013. Pequenas, em paralelo, em cima desta.

## Contexto (por que esta estória existe)

Roberto não tem porta de entrada. Sem cadastro persistido e sessão funcionando, nada do que ele fizer no DEFOnline sobrevive entre visitas, e nenhuma feature subsequente (cadastrar empresa, fazer quiz) tem como existir.

Esta estória entrega o **mínimo vertical observável**: alguém cria conta, faz login, vê uma tela autenticada com nome próprio. É o equivalente da página "hello DEFOnline" do EPIC-000, mas com identidade. Camadas (Termo, LGPD, confirmação de email, cadastro de Empresa) entram nas estórias seguintes — todas dependem desta para ter "Usuário" em quem pendurar.

- Épico: `epics/EPIC-001-cadastro-minimo/epic.md`
- Documentos canônicos:
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §1.5.2 (entidades) e §3.3 Passo 1 (Cadastro do Usuário)
  - ADR-001 (`decisions/adr/ADR-001-stack.md`) — auth padrão Laravel
  - ADR-003 (`decisions/adr/ADR-003-persistencia.md`) — multi-tenancy via FK + Global Scope, audit log, soft delete
  - ADR-004 (`decisions/adr/ADR-004-observabilidade.md`) — log JSON, request_id, mascaramento PII

## O quê (objetivo desta estória)

Implementar cadastro persistido do Usuário (CPF + nome + email + senha + telefone), login com email + senha gerando sessão Laravel, e uma home autenticada que exibe nome do usuário e endpoint de logout. Tudo em browser real, em homologação real.

## Por quê (valor para o usuário)

Sem esta estória, nada do EPIC-001 tem chão. Com esta estória, Roberto consegue dizer "eu existo no DEFOnline" — base sobre a qual aceite de Termo, confirmação de email, cadastro de Empresa e tudo o que vem depois pode ser empilhado.

## Critérios de aceite

- [ ] **CA-1:** Existe rota pública `/cadastro` que renderiza componente Livewire com os campos: **CPF** (mascarado, validação CPF), **nome completo**, **email**, **senha** (mínimo 8 caracteres com regra de força do Laravel — `Password::min(8)->letters()->numbers()`), **confirmação de senha**, **telefone WhatsApp** (mascarado, validação de formato BR). Submit válido cria registro em `usuarios` e redireciona para `/login` com mensagem de sucesso.
- [ ] **CA-2:** Submit com **CPF inválido**, **email inválido**, **CPF já existente** ou **email já existente** retorna ao formulário com mensagem de erro específica por campo (sem expor "já existe" como vazamento de inscritos — usar mensagem genérica "este dado já está em uso" em conformidade com `security-discipline.md`).
- [ ] **CA-3:** Existe rota pública `/login` que renderiza form Livewire com email + senha. Credenciais válidas geram sessão (`Auth::login()` padrão Laravel) e redirecionam para `/home`. Credenciais inválidas retornam ao login com mensagem genérica "credenciais inválidas" (sem distinguir email-inexistente vs. senha-errada).
- [ ] **CA-4:** Rota `/home` é **protegida por middleware `auth`**. Acesso sem sessão redireciona para `/login`. Acesso com sessão renderiza saudação "Olá, {primeiro_nome}" + link/botão de logout que invalida a sessão e redireciona para `/login`.
- [ ] **CA-5:** Senha é armazenada com hash bcrypt/argon2 padrão Laravel (jamais em texto claro; nunca em log). Migration cria coluna `senha` (ou `password` — padrão Laravel) e índices únicos em `cpf` (CITEXT/normalizado) e `email` (CITEXT).
- [ ] **CA-6:** Cadastro e login emitem **log JSON estruturado** via Monolog com `request_id`, **sem PII** (CPF/email/telefone mascarados pelo `LogSanitizer` do EPIC-000). Auditoria: cada cadastro e cada login bem-sucedido geram entrada em `audit_logs` (`action: 'usuario.cadastrado'` / `'usuario.login_sucesso'`).
- [ ] **CA-7:** Testes Pest: pelo menos 1 UnitPure (validação de CPF helper, se houver), Feature cobrindo CA-1 a CA-6 (form de cadastro, validações, login, middleware auth, logout, mascaramento em log), e **1 Dusk E2E** em browser real percorrendo o fluxo `cadastro → login → home → logout`. Cobertura total do código novo desta estória ≥ 80% (gate da STORY-010 vai aplicar; código novo provavelmente ≥ 95%).

## Fora de escopo

- **Termo de Adesão, consentimento LGPD, opt-in marketing** — STORY-012.
- **Confirmação de email obrigatória** — STORY-013. Nesta estória, a conta criada já permite login direto.
- **Recuperação de senha** — onda 2 (fora do EPIC-001).
- **MFA, login social** — roadmap (`requisitos-nao-funcionais-e-juridicos.md` §7.1).
- **Cadastro de Empresa Analisada** — STORY-014.
- **Tela `/home` final** — esta estória entrega uma home **mínima** ("Olá, {nome} · sair"). A versão com "Minhas Empresas" entra na STORY-016.
- **Edição de Usuário** — fora do MVP (declarado no epic).
- **Política de senha mais sofisticada** (rotação, complexidade extra) — usar default Laravel; ajustes podem virar bugfix futuro.

## Padrões de qualidade exigidos

Esta estória segue `defonline-docs/skills/po/references/quality-standards.md`. Resumo aplicável:

- **Cobertura unitária ≥ 80%** no código novo (geral); **≥ 98%** em qualquer lógica que entre em `app/Domain/**` (validações de domínio puras, se você isolar lá — decisão sua). Gate da STORY-010 vai bloquear o push se cair abaixo.
- **Sem PII em log/evento** — toda emissão passa pelo `LogSanitizer` + `EventLogger` do EPIC-000. Teste arquitetural já existe e vai pegar se vazar.
- **E2E em browser real** — Dusk contra a rota local + smoke contra homologação após deploy (já cabeado pelo `release-homolog.yml`).
- **TDD + E2E** — escreva o teste antes ou em paralelo, não depois.

## Dependências

- **Bloqueada por:** nenhuma (primeira estória do EPIC-001).
- **Bloqueia:** STORY-012, STORY-013, STORY-014 (todas dependem da entidade Usuário + sessão).
- **Pré-requisitos de ambiente:** Docker rodando localmente (`./up.sh`), homologação acessível.

## Decisões já tomadas (não as reabra)

- **ADR-001** — Laravel 13 + Livewire 4 + PostgreSQL 18 + Pest 4 + Dusk 8 + Auth padrão Laravel (`Auth` facade + middleware `auth`). Não monte sistema de auth próprio.
- **ADR-003** — multi-tenancy via FK + Global Scope (a partir da STORY-014 vai usar); audit log via `app/Observabilidade/AuditLogger` (já existe do EPIC-000); soft delete + T+30d anonimização LGPD (entra completo no EPIC futuro de exclusão de conta).
- **ADR-004** — log JSON, `request_id` UUID v7, mascaramento PII via `LogSanitizer` (já existe).
- **Especificação §1.5.2** — Usuário = pessoa física identificada por CPF + email + senha; **CPF do Usuário ≠ CPF da Empresa Analisada** (se Empresa for autônomo). Não confunda os dois (entra na STORY-014).

## Liberdade técnica do agente

Você decide:
- Estrutura de pastas: `app/Livewire/Cadastro`, `app/Livewire/Login`, `app/Models/Usuario`, etc. — sua decisão de organização.
- Usar Laravel Fortify, Breeze, ou montar form Livewire direto (recomendação minha: Fortify é overengineered para o MVP; Livewire direto é mais limpo dado o stack Livewire 4).
- Como validar CPF (regex + verificador digitos): se for usar package, escolha mantido (`brazanation/documents`, `laravelLegends/pt-br-validator`, etc.) — registre em IDR se a escolha for não-óbvia.
- Estrutura de testes (factory de `Usuario`, traits, etc.).

Você **não** decide:
- Que existe Usuário com CPF + email + senha (espec §1.5.2).
- Que tem sessão Laravel padrão (ADR-001).
- Padrões de qualidade (PO).
- Critérios de aceite (PO).

## Definição de Pronto (DoD)

- [ ] Todos os CAs passam.
- [ ] Migration aplicada localmente e no deploy de homologação (sem intervenção manual).
- [ ] Pre-push hook verde (Pint + Larastan + Pest All com `--coverage --min=80` + Dusk).
- [ ] Pipeline CI verde no PR.
- [ ] Deploy em homologação realizado via tag `vX.Y.Z-rc.N` (próxima sequência de rc.N após rc.5) e validado por smoke pós-deploy.
- [ ] `index.json` atualizado: status = `done`.
- [ ] "Notas do agente" preenchidas (decisões locais, IDRs se houver, cobertura final).
- [ ] PR mergeado e tag rc disparada (você não precisa mergear se quiser deixar para o PO revisar antes; combine no chat).

## Protocolo do agente (obrigatório)

Siga `agent-task-format.md`:

1. **Ao iniciar:** `status: in_progress`, `owner_agent: <seu id/sessão>`, `updated_at: <hoje>`. Atualize `index.json` também.
2. **Durante:** TaskList interna; commits pequenos nomeados `feat(STORY-011): <ação>`; rode `./vendor/bin/pint --test` antes de cada commit.
3. **Se travar:** `status: blocked`, descreva em "Notas do agente". Não invente decisão de produto/arquitetura.
4. **Decisões técnicas de baixo nível** com impacto futuro vão em IDR.
5. **Ao terminar:** preencha "Notas do agente", `status: in_review`, atualize `index.json`, abra PR. PO valida cadastro/login manualmente em homologação antes de marcar `done`.

## Notas do agente (preenchido durante/após execução)

### Documentos lidos (2026-05-22)
- Estória inteira (CA-1..CA-7, fora de escopo, decisões já tomadas, DoD, protocolo).
- ADR-001 (auth padrão Laravel + bcrypt 12 + throttle `login` 5/min) — não montar auth próprio.
- ADR-003 §Decisão 1 (multi-tenancy via FK `usuario_id` + Global Scope — entra a partir da STORY-014, não aqui), §Decisão 3 (UUID v7 em PK via `Str::uuid7()`), §Decisão 4 (audit_logs append-only via `AuditLogger::log()`), §Decisão 6 (CPF em claro, mascarado em log via `LogSanitizer`), agregado `Usuario`.
- ADR-004 §1.1 (log JSON + `Log::withContext(request_id, user_id, …)` via `EnrichLogContext`), §2 (eventos de produto — `usuario_cadastrado` emitido **só após confirmação de email**, ou seja, STORY-013; nesta estória emitimos apenas em audit).
- Espec V2 §1.5.2 (Usuário = pessoa física CPF + nome + email + senha + telefone), §3.3 Passo 1 (Cadastro do Usuário — ≤5 min, mobile-first).
- `references/reading-discipline.md`, `coding-principles.md`, `testing-discipline.md`, `library-discipline.md`, `security-discipline.md`.
- Código existente: tabela `usuarios` (migration `2026_05_22_000020`), `User` esqueleto Laravel apontando ao não-existente `users`, `LogSanitizer`/`AuditLogger`/`EventLogger`/`RequestId`, middlewares `AssignRequestId`+`EnrichLogContext`+`MeasureRequest`, pre-push hook (Pint + Larastan + Pest --min=80 + Dusk).

### Entendimento consolidado
Entrega a primeira camada vertical do EPIC-001: rota `/cadastro` (Livewire) cria Usuário em `usuarios` (CPF + nome + email + senha + telefone) usando hash bcrypt 12 da ADR-001; `/login` autentica com Auth padrão; `/home` autenticada renderiza "Olá, {primeiro_nome}" + logout. **Sem** termo, **sem** confirmação de email, **sem** evento de produto `usuario_cadastrado` (esse depende de confirmação — STORY-013); aqui só audit log (`usuario.cadastrado`, `usuario.login_sucesso`). Multi-tenancy via Global Scope ainda não aplica (entra com `EmpresaAnalisada` na STORY-014).

### Decisões locais tomadas
- **Helper puro de CPF** em `app/Domain/Cpf` (não package). Justificativa: princípio #1 + library-discipline; cálculo de DV cabe em ~30 LoC; cobre CA-7 (UnitPure). Dispara branch de gate ≥98% sobre `app/Domain` no pre-push (estrutura já existe — STORY-010).
- **Model `Usuario`** em `app/Models/Usuario.php` mapeando `usuarios`. Sobrescreve `getAuthPasswordName()` para `senha_hash` (coluna já existente — migration ADR-003 §ER). `auth.php` aponta para `Usuario`. `User` esqueleto Laravel é removido (não usado em lugar nenhum).
- **Login** com `Auth::attempt(['email' => …, 'password' => …])` — Laravel mapeia `password` para `getAuthPasswordName()`.
- **CPF é armazenado só com dígitos** (`preg_replace('/\D+/', '', $cpf)`). Máscara é UI; banco normaliza.
- **Mensagem genérica em duplicação**: "este dado já está em uso" no campo (CPF ou email).
- **Throttle de login**: middleware `throttle:login` (5/min IP+email, ADR-001) na rota `/login`.
- **Sem `users` table renomeada** — a migration original já criou `usuarios`. A `UserFactory` vira `UsuarioFactory`.
- **Senha em mensagens de erro** nunca exposta — `Password::min(8)->letters()->numbers()` do Laravel; mensagens traduzidas pelo `lang/pt_BR`.

### Plano (5 passos)
1. Helper `App\Domain\Cpf` + UnitPure tests.
2. Model `Usuario` + factory + ajuste `auth.php` + remoção do `User`.
3. Rota `/cadastro` + Livewire `Cadastro` + Blade + Feature tests (CA-1, CA-2, CA-5, CA-6).
4. Rota `/login` + Livewire `Login` + Blade + Feature tests (CA-3, CA-6) + middleware `throttle:login`.
5. Rota `/home` (middleware `auth`) + logout + Feature tests (CA-4) + Dusk E2E (CA-7).

Cada passo: red → green → refactor + commit `feat(STORY-011): …`. Suíte completa entre passos.

### Decisões tomadas
- 2026-05-22 — Sem lib externa de CPF (helper puro em `app/Domain/Cpf`).
- 2026-05-22 — Renomear model `User`→`Usuario` (consistência com domínio + tabela).
- 2026-05-22 — Manter coluna `senha_hash` (já existe); override em `getAuthPasswordName()`.

### Descobertas
- 2026-05-22 — `app/Models/User.php` estava excluído da cobertura no `phpunit.xml`. Após a renomeação para `Usuario`, removi essa exclusão (código nosso, deve contar — agora 100%).
- 2026-05-22 — `phpunit-domain.xml` (STORY-010) tinha `--` (double-hyphen) em comentários XML, o que faz `libxml` falhar com "Comment must not contain '--'". Nunca foi acionado porque `app/Domain/` não existia. Ao criar `app/Domain/Cpf`, o gate ≥98% disparou e o arquivo precisou ser higienizado (substituí os `--` por palavras). Sem isso, o pre-push falhava silenciosamente.
- 2026-05-22 — `assertSeeLivewire()` não existe nativamente no TestResponse do Laravel 13 + Livewire 4 — substituí por `assertSee('CPF')`. Anotação para futuras estórias.
- 2026-05-22 — Logout via POST exige CSRF. Em Feature tests, `withSession(['_token' => …])->post('/logout', ['_token' => …])` é o caminho idiomático (sem desligar middleware).
- 2026-05-22 — `throttle:login` exige um `RateLimiter::for('login', …)` definido em `AppServiceProvider::boot()`. Sem ele a rota retorna 500. Implementado com `Limit::perMinute(5)->by(email|ip)` conforme ADR-001 §Auth.

### Bloqueios encontrados
- (nenhum)

### IDRs criados
- (nenhum — decisões locais; sem impacto transversal que exija registro)

### Cobertura final
- Geral: **95.5%** (gate ≥80% verde) — código novo desta estória (`Cadastro` 100%, `Login` 94.4%, `HomeController` 100%, `Usuario` 100%).
- Domínio (`app/Domain/**`): **100%** (gate ≥98% verde) — `App\Domain\Cpf`.

### Resumo de testes
- Pest UnitPure + Feature: **91 testes / 238 asserções** verdes (era 69 antes).
  - `Tests\Unit\Domain\CpfTest` — 13 testes (válido/inválido/sequência trivial/tamanho/normalização/formatação).
  - `Tests\Feature\Livewire\CadastroTest` — 11 testes (CA-1, CA-2, CA-5, CA-6).
  - `Tests\Feature\Livewire\LoginTest` — 7 testes (CA-3, CA-6).
  - `Tests\Feature\Http\HomeTest` — 4 testes (CA-4).
- Dusk E2E: **3 testes / 11 asserções** verdes incluindo o novo `CadastroLoginHomeBrowserTest` que percorre o ciclo cadastro → login → home → logout em Chromium real (CA-7).
- Pint, Larastan nível 6 e gate de cobertura 80% + 98% domínio: todos verdes via `pre-push.sh`.

### Links de evidência
- PR: n/a — trunk-based, commits direto em `main` (preferência do PO registrada em memória).
- Pipeline rc.1: https://github.com/xandroalmeida/defonline/actions/runs/26310189543 — **falhou no smoke** (lição abaixo).
- Pipeline rc.2: https://github.com/xandroalmeida/defonline/actions/runs/26310602091 — **verde end-to-end** (validate + build-and-push + deploy + smoke + notify).
- Tag de homologação: `v0.2.0-rc.2` (rc.1 deployou a feature mas falhou no smoke; rc.2 corrigiu o smoke sem mudança funcional).
- Smoke pós-deploy: `CadastroLoginSmokeBrowserTest` (read-only, /cadastro + /login) verde em homol.
- Probe manual pós-deploy: `/cadastro` 200, `/login` 200, `/home` 302, `/health` 200 em https://defonline.xandrix.com.br (2026-05-22).

### Aprovação
- 2026-05-22 — **PO aprovou após teste manual local** em http://localhost:8090 (caminho feliz + ajustes: mensagens pt-BR, máscaras de CPF e telefone).
- 2026-05-22 — **Deploy em homologação concluído** via tag `v0.2.0-rc.2`; smoke pós-deploy verde; probe manual dos endpoints `/cadastro`, `/login`, `/home`, `/health` confirmam disponibilidade.

### Lição registrada (rc.1 falha)
A rc.1 falhou no smoke pós-deploy porque o teste E2E completo (`CadastroLoginHomeBrowserTest`) estava marcado `#[Group('smoke')]` e roda contra a URL pública real — tentou submeter o form de cadastro em produção. Smoke pós-deploy precisa ser **read-only por design** para não contaminar dados de homologação. Corrigido na rc.2 com `CadastroLoginSmokeBrowserTest` (apenas visita + assertPresent). Princípio a propagar para estórias futuras: novos endpoints autenticados ganham um smoke read-only correspondente; E2E destrutivo fica restrito ao pre-push local.
