---
adr_id: ADR-001
slug: stack
title: Stack do DEFOnline — Laravel 13 + Livewire 4 + PostgreSQL 18 + Pest + Dusk
status: accepted
decided_at: 2026-05-20
decided_by: arquiteto
approved_by: Alexandro
supersedes: null
superseded_by: null
related_adrs: []
related_pdrs: ["PDR-001"]
related_epics: ["EPIC-000"]
created_at: 2026-05-20
updated_at: 2026-05-20
type: stack
---

# ADR-001 — Stack do DEFOnline pós-reset

## Contexto

O reset técnico de 19/05/2026 zerou as decisões de stack do projeto pré-reset (NestJS 10 + Prisma + React/Vite + Next.js + Turborepo). A regra de negócio (especificação V2.5) permanece válida, mas a fundação técnica precisa ser refeita. O EPIC-000 não fecha sem stack ratificada por ADR, e todos os épicos da WAVE-2026-01 (EPIC-001 cadastro, EPIC-002 diagnóstico, EPIC-003 histórico) ficam bloqueados até esta decisão estar `accepted`.

Restrições funcionais e não-funcionais relevantes (`especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §§1, 4, 9):

- **Performance:** p95 < 1,5 s no login, < 3 s no envio de quiz + motor, < 2 s no relatório.
- **Capacidade:** 300 usuários concorrentes no MVP, 50 execuções concorrentes do motor, escalável para 1.500 sem rearquitetura.
- **Segurança:** sessão com cookie HttpOnly/Secure/SameSite=Lax (§4.3), rate limit em endpoints sensíveis, hash moderno de senha, isolamento rígido por `usuario_id`, audit log.
- **Compatibilidade:** Chrome/Edge/Firefox/Safari atuais + N-1; responsivo desktop/tablet/mobile.
- **Time:** muito pequeno — PO + agentes (1 dev humano em prática, com IA como força multiplicadora).
- **Restrição de hire-ability local:** PO confirmou familiaridade com PHP/Laravel; nenhuma familiaridade comparável com Ruby, Python ou Elixir.
- **Decisões herdadas (a ratificar formalmente nesta ADR):** PostgreSQL como banco principal; TDD + E2E como exigência inegociável.

A decisão precisa ser tomada agora porque destrava STORY-007 (hello world em homologação) e todas as histórias dos épicos seguintes da onda.

## Forças (drivers) da decisão

- **F1 — Aderência ao princípio #4 (frameworks opinativos)** — **Alto**. O pré-reset usou NestJS, que é parcialmente opinativo mas exige escolher Prisma, Passport, validador, admin separado, FE separado. A complexidade resultante foi um dos motivos do reset.
- **F2 — FE + BE no mesmo framework opinativo (sub-princípio do #4)** — **Alto**. Hotsite público + área logada na mesma stack reduz drasticamente a complexidade de monorepo, build, deploy, autenticação compartilhada.
- **F3 — Compatibilidade com TDD + E2E em browser real (princípio #10)** — **Alto**. Inegociável por herança do PO (`quality-standards.md`); 8 fluxos críticos E2E em RNF §9.3.
- **F4 — Postgres-first (princípio #3)** — **Alto**. Herança ratificada. Queue, sessão, cache devem viver no Postgres no MVP, sem adicionar Redis/RabbitMQ prematuramente.
- **F5 — Funcionamento 100% local em ≤ 1 comando (princípio #6)** — **Alto**. Não-negociável.
- **F6 — Hire-ability/familiaridade do time** — **Alto**. Time pequeno + PO com familiaridade declarada em PHP/Laravel torna esta dimensão decisiva, mesmo sendo "média" no peso genérico do método.
- **F7 — Simplicidade (princípio #1)** — **Alto**. O pré-reset falhou justamente por excesso de peças (Turborepo + 3 apps + Prisma + NestJS + React); a nova stack deve resistir ativamente a essa complexidade.
- **F8 — Custo recorrente (princípio #11)** — **Médio**. SaaS para MPE; runtime deve rodar em VPS comum (R$ 50–200/mês), não exigir serviços PaaS caros.
- **F9 — Reversibilidade (princípio #7)** — **Médio**. Linguagem é decisão cara/quase irreversível na prática. Mitigação: o domínio é direto (CRUD + cálculos + relatório) e portável conceitualmente entre frameworks.
- **F10 — Maturidade e comunidade BR** — **Médio**. Time pequeno depende de StackOverflow, conteúdo em português, libs maduras para problemas comuns (PDF, e-mail, gateway de pagamento BR).
- **F11 — "Modernidade"** — **Baixo**. Bônus quando vier; não decisor.

## Opções consideradas

### Opção A — Laravel 13 + Livewire 4 + Pest 4 + Laravel Dusk 8 (PHP 8.5)

- **Resumo:** framework opinativo de máxima cobertura (princípio #4): Eloquent ORM, migrations, validation, Form Requests, Mail, Notifications, Queue (driver `database` no Postgres), Schedule, sessões cookie HttpOnly nativas, Authentication, Authorization (Gates/Policies), Storage, Broadcasting, Resource controllers, Blade templating, Vite asset pipeline. **Livewire 4** entrega FE reativo SPA-like server-rendered no MESMO framework — área logada e hotsite vivem no mesmo app, mesmo deploy, mesma sessão. **Pest 4** para testes (sintaxe expressiva sobre PHPUnit). **Laravel Dusk 8** para E2E em browser Chromium real (não Headless Chrome puro — Dusk é o omakase do Laravel, integra com factories, RefreshDatabase, fluxos autenticados). Runtime: PHP 8.5 + servidor único (artisan serve em dev, php-fpm + nginx em produção). Gerenciador de dependências: Composer 2.
- **Como atende aos princípios**:
  - ✅ **#1 Simplicidade:** 1 framework, 1 processo, 1 deploy. Quantidade de peças móveis dramaticamente menor que o pré-reset.
  - ✅ **#2 Monolito:** Laravel é desenhado para monolito modular. Service Providers + Domain modules (futuro IDR) cabem nativamente.
  - ✅ **#3 Postgres-first:** drivers `pgsql` nativos em sessão, cache, queue. Validado no spike (`select now()` passou). Suporte a extensões (`pgvector`, `pg_trgm`, `pgcrypto`) via migrations cruas.
  - ✅ **#4 Opinativo:** referência da categoria. Auth, ORM, admin (via Filament gratuito se necessário), migrations, validação, jobs, scheduler — tudo nativo.
  - ✅ **#5 Coesão/acoplamento:** estrutura de pastas opinada; Livewire components isoláveis e testáveis; pode evoluir para módulos de domínio sem refatoração grande.
  - ✅ **#6 Local total:** spike provou `./up.sh` → app + Postgres em 1 comando, sem internet (após primeira execução).
  - ✅ **#7 Reversibilidade:** PHP é portátil; Laravel é mantido pela Laravel Holdings (financiamento privado + ecossistema comercial saudável); migrar lógica de cálculo do motor para outra linguagem seria viável se necessário.
  - ✅ **#8 Observabilidade:** Pail (tail estruturado), Logging por contexto, Telescope (dev). Para produção, OpenTelemetry tem driver oficial (decisão fica para ADR de Observabilidade — STORY-006).
  - ✅ **#9 Automatizável:** Laravel Pint (lint), Larastan (análise estática), `php artisan test --parallel` em CI.
  - ✅ **#10 TDD + E2E:** Pest 4 (provado no spike, 4/4 passing) + Dusk (provado no spike, real Chromium, 1/1 passing).
  - ✅ **#11 Custo:** roda em VPS Linux comum. PHP 8.5 + nginx + Postgres = ~1 GB RAM ocioso. Pode subir em VPS de R$ 50–80/mês inicialmente.
  - ✅ **#12 Restrições:** documentado nesta ADR o que está fora de escopo.
- **Prós concretos:**
  - Auth padrão (Laravel Breeze) entrega login + register + forgot-password + email verification em uma instalação.
  - Livewire elimina a necessidade de SPA separado, hotsite separado, build chain JS pesado. Hotsite vira rota pública Blade; área logada vira rota com middleware `auth`.
  - PHP/Laravel é a comunidade mais densa no Brasil — perguntas no Slack/Reddit/Stack Overflow respondidas rápido em português.
  - Laravel tem Long-Term Support: v11 (Mar/2024) + futuras maintenance releases. Atualização major a cada ~1 ano.
- **Contras concretos:**
  - PHP carrega estigma cultural em alguns nichos — possível dificuldade para contratar dev sênior **em outras linguagens**, mas trivial para contratar dev Laravel BR.
  - Livewire 4 é jovem (lançado 2023) — maduro mas não tão battle-tested quanto Hotwire/Rails. Spike validou a integração de testes.
  - PHP performance bruta é inferior a Go/Elixir/Rust; **aceitável** porque NFRs do DEFOnline são tranquilos para PHP (300 concurrent users com FPM são triviais; 1.500 exigem dimensionamento padrão).

### Opção B — Rails 7 + Hotwire (Turbo + Stimulus) + RSpec/Minitest + Capybara/Playwright (Ruby 3.3)

- **Resumo:** o "outro omakase" — opinativo no mesmo nível de Laravel. ActiveRecord ORM, ActiveJob (com `good_job` no Postgres), Devise para auth, asset pipeline (Propshaft), Hotwire para SPA-like sem JS pesado, Action Mailer/Cable, sistema de filas robusto.
- **Como atende aos princípios:**
  - ✅ #1–#10 todos atendidos em nível equivalente a Laravel.
  - ⚠️ #11 Custo: Ruby/Rails em produção exige um pouco mais de RAM que PHP-FPM, mas diferença pequena no MVP.
- **Prós:** Hotwire é arguably o gold standard de "FE+BE no mesmo framework". Ecossistema Postgres-first muito maduro (`good_job`, `pg_search`, etc.).
- **Contras concretos:**
  - **Hire-ability BR:** mercado de Ruby/Rails brasileiro é muito menor que PHP. Conteúdo em português escasso.
  - **PO não tem familiaridade.** Curva de aprendizado real estimada em 2–4 semanas para um dev experiente em outra linguagem.
  - Rolê irreversível: trocar Ruby por outra linguagem custa caro.

### Opção C — Django 5 + HTMX + pytest + Playwright (Python 3.12)

- **Resumo:** Django entrega admin sensacional (atalho enorme para back-office de SaaS), ORM, migrations, auth, sessions. HTMX adiciona reatividade server-rendered sem SPA. pytest + factory_boy + Playwright para testes.
- **Como atende aos princípios:**
  - ✅ #1–#10 todos atendidos.
  - ⚠️ #4 Opinativo: Django é opinativo no core, mas integrações (jobs assíncronos: Celery+Redis vs django-q+Postgres vs dramatiq) exigem decisão extra que Laravel já tem nativa.
- **Prós:** Python permite ML/análise financeira no futuro (linha pós-v1 de medianas setoriais — `roadmap-pos-v1.md`). Admin do Django é o melhor da categoria.
- **Contras concretos:**
  - **Hire-ability:** Python comunidade BR é grande, mas Django especificamente menor (PHP > Django no mercado BR).
  - **PO não tem familiaridade.**
  - HTMX é mais "junta peças" que Livewire (princípio #4: cada decisão não tomada vira decisão futura).

### Opção D — Status quo: NestJS 10 + Prisma + React/Vite + Next.js + Turborepo (a stack do pré-reset)

- **Consequência se mantivermos:** reabrir a complexidade que motivou o reset técnico em 19/05/2026. Memory institucional já tem evidência de problemas: bind mount inode no Docker, Prisma 7 TS-native client breaking changes, monorepo com Turbo + pnpm com pitfalls de CI/cache.
- **Custo de adiar:** alto — todas as histórias dos épicos seguintes ficam bloqueadas. Status quo não atende princípio #4 (NestJS é menos opinativo que Laravel/Rails/Django) nem o sub-princípio "FE + BE no mesmo framework opinativo".
- **Decisão sobre o status quo:** **rejeitado**. O reset técnico foi explicitamente feito para sair desta stack.

### Opções pré-descartadas (sem análise profunda — princípio "filtrar antes de comparar")

- **Phoenix/Elixir:** mercado BR muito pequeno; PO sem familiaridade; viola F6 de forma irrecuperável.
- **Spring Boot/Java:** opinativo, mas overkill para SaaS MPE; tempo de cold start e consumo de RAM hostis a VPS barato (F8).
- **Express + N libs avulsas:** viola princípio #4 frontalmente.
- **Frameworks JS modernos (Remix, Nuxt, SvelteKit):** menos opinativos que Laravel, ecossistema fragmentado para domínio de back-office.

## Matriz comparativa

| Critério (força)                                 | Peso  | A — Laravel + Livewire     | B — Rails + Hotwire        | C — Django + HTMX          | D — Status quo (NestJS)    |
|---|---|---|---|---|---|
| F1 — Aderência ao princípio #4                   | Alto  | ✅ máxima                  | ✅ máxima                  | ✅ alta                    | ⚠️ parcial                  |
| F2 — FE + BE no mesmo framework                  | Alto  | ✅ Livewire nativo         | ✅ Hotwire nativo          | ⚠️ HTMX é add-on          | ❌ FE separado obrigatório  |
| F3 — TDD + E2E browser real                      | Alto  | ✅ Pest + Dusk (provado)   | ✅ RSpec + Capybara        | ✅ pytest + Playwright    | ✅ Jest + Playwright        |
| F4 — Postgres-first                              | Alto  | ✅ drivers nativos         | ✅ ecossistema robusto     | ✅ ORM excelente           | ✅ Prisma ok                |
| F5 — Local em ≤ 1 comando                        | Alto  | ✅ Docker spike provou     | ✅ viável                  | ✅ viável                  | ⚠️ histórico de pitfalls   |
| F6 — Familiaridade do PO                         | Alto  | ✅ declarada               | ❌ nenhuma                 | ❌ nenhuma                 | ⚠️ tentou e travou         |
| F7 — Simplicidade                                | Alto  | ✅ 1 framework, 1 app      | ✅ 1 framework, 1 app      | ✅ 1 framework, 1 app      | ❌ 4 apps + monorepo        |
| F8 — Custo recorrente (VPS R$/mês)              | Médio | ✅ ~R$ 50–80              | ⚠️ ~R$ 80–150             | ⚠️ ~R$ 80–150             | ⚠️ ~R$ 100–200             |
| F9 — Reversibilidade                             | Médio | ⚠️ caro mas portável      | ⚠️ caro mas portável      | ⚠️ caro mas portável      | ⚠️ caro                    |
| F10 — Comunidade BR                              | Médio | ✅ maior do mercado        | ❌ muito pequena           | ⚠️ média                   | ✅ grande                   |

Notas qualitativas: ✅ atende plenamente; ⚠️ atende com ressalva; ❌ não atende ou atende mal.

## Decisão proposta

> **Optamos pela Opção A — Laravel 13 + Livewire 4 + PostgreSQL 18 + Pest 4 + Laravel Dusk 8, com PHP 8.5 em runtime e Composer 2 como gerenciador.**

A combinação entrega o maior alinhamento simultâneo com os 6 princípios centrais do Arquiteto e com a familiaridade declarada do PO. **Livewire 4** resolve o sub-princípio "FE + BE no mesmo framework opinativo" sem precisar de SPA separado ou hotsite separado: área logada (com `middleware auth`), hotsite público e back-office (potencialmente via Filament) podem viver no MESMO app Laravel. **Pest 4 + Laravel Dusk 8** garantem TDD e E2E em browser real desde o primeiro endpoint — ambos foram validados em spike funcional (ver "Plano de verificação" abaixo).

## Ratificação de decisões herdadas (CA-3)

Esta ADR ratifica formalmente, sem reabrir, as duas decisões técnicas que sobreviveram ao reset de 19/05/2026:

- **PostgreSQL como banco principal.** Versão piso: **PostgreSQL 18** (versão estável majoritária em vigor; o spike utiliza `postgres:18-alpine`). O driver `pgsql` do Laravel é nativo e suporta as extensões que provavelmente usaremos (`pg_trgm`, `pgcrypto`, eventualmente `pgvector`). Detalhes do modelo de dados, multi-tenancy e migrations ficam para a STORY-003 (spike de persistência) — **esta ADR não invade aquele escopo**.
- **TDD + E2E como exigência inegociável da stack.** A escolha de **Pest 4** (sintaxe expressiva sobre PHPUnit 12 — substrato PSR-compatível) e **Laravel Dusk 8** (E2E em browser Chromium real, integração nativa com Laravel) garantem que toda nova história tenha contrato testável **antes** da implementação (TDD) e que os 8 fluxos críticos definidos no RNF §9.3 possam ser cobertos sem heroísmo. Cada fórmula do motor (14 indicadores) tem suporte direto em Pest para os ≥10 casos de teste exigidos. **Observação:** Pest 4 também inclui um runner de browser testing nativo (concorrente do Dusk); a spike usou Dusk por ser o omakase histórico do Laravel, mas a transição para o runner Pest 4 fica em aberto como IDR do Programador caso ele queira simplificar a suíte E2E numa única ferramenta.

## Autenticação padrão do framework (CA-4)

Esta ADR adota o **estágio inicial padrão do Laravel** para autenticação. Detalhes:

- **Pacote oficial:** **Laravel Breeze** — instalação `composer require laravel/breeze --dev && php artisan breeze:install blade`. Entrega login, register, forgot-password, email-verification, dashboard mínimo, todos em Blade + Livewire (opção `breeze:install livewire`). É o **mínimo opinativo do Laravel** — sem MFA, sem social login, sem WebAuthn.
- **Sessão server-side via cookie** (não Bearer/JWT). Cookie `HttpOnly`, `Secure` em produção, `SameSite=Lax` (aderência ao RNF §4.3). Store de sessão: `database` driver no Postgres (consistente com princípio #3 e com o spike).
- **Hashing de senha:** **bcrypt** (default do Laravel, custo 12), trivialmente trocável para argon2id via `config/hashing.php` se houver requisito formal. O default do Laravel é seguro por design.
- **Expiração:** sessão de **120 minutos** (default Laravel). Configurável em `config/session.php` por NFR posterior. "Lembrar-me" via cookie longo separado.
- **Recuperação básica:** fluxo `forgot-password` do Breeze, que envia link com token assinado (validade 60 min, default), uso único. Por hora, o e-mail vai para `log` driver em dev (visível em `storage/logs/laravel.log`) e para `mailpit` se quisermos visual local — provedor real de e-mail será decidido em ADR posterior.
- **Rate limit em endpoints sensíveis:** Laravel possui middleware `throttle:login` nativo (5 tentativas/min por IP+e-mail). Atende RNF §4.4 (bloqueio temporário após 5 tentativas) sem código adicional.

**Explicitamente fora do escopo desta ADR (roadmap pós-v1 conforme STORY pede):**

- MFA / 2FA.
- Login social (Google, GitHub).
- SSO corporativo (OAuth/OIDC com provedor externo).
- Passwordless / magic link / WebAuthn.
- Senha sem expiração ou rotation policy avançada.

Quando alguma dessas virar requisito, **vira ADR separada**, supersedendo a parte de autenticação desta ADR.

## Justificativa

A Opção A ganhou por convergência **simultânea** das forças de maior peso:

1. **F4 (Postgres-first)** e **F3 (TDD/E2E)** foram **provadas em spike funcional** — não confiamos em promessa de documentação; o teste Pest tocou o Postgres real e Dusk subiu Chromium real. Spike é a moeda de honestidade do princípio #7.
2. **F6 (familiaridade do PO)** é **decisor** num time desta dimensão. Rails e Django são tecnicamente equivalentes a Laravel em F1–F5, mas exigiriam ramp-up de 2–4 semanas que esta wave não comporta.
3. **F2 (FE + BE mesmo framework)** com Livewire é o que evita repetir o erro do pré-reset (NestJS + React + Next.js + Turborepo) — concretamente, **um único projeto em vez de quatro**.

Trade-offs honestamente reconhecidos da Opção A:

- **PHP carrega estigma cultural.** Não é argumento técnico, mas a percepção pode atrapalhar contratações sênior fora do mundo PHP. Mitigação: o Laravel BR é mainstream profissional; recrutamento de dev Laravel é rápido.
- **Livewire 4 é mais jovem que Hotwire/Rails.** Em ~5 anos de vida pública (v1 em 2020, v2 em 2021, v3 em 2023, v4 em 2025 — versão estável atual com suporte oficial a Laravel 13), com adoção forte e v4 já consolidada. Risco mitigado pelo fallback: se Livewire fracassar em algum ponto, podemos cair de volta em Blade + JS leve sem reescrever o backend.
- **PHP perde em concorrência de I/O para Elixir/Go.** Não relevante para o NFR alvo (300 → 1.500 concurrent users). Se virar gargalo, é refator de hotspot, não troca de stack.

## Plano de verificação

### Spike de prova de conceito (CA-5) — **EXECUTADO**

Branch: **`spike/STORY-001-stack`** — commit `3fc1bb4`.

Diretório: `spike-stack/` no repo. Não é código de produção — é descartável.

**Comando reproduzível (de uma máquina limpa com Docker instalado):**

```bash
git checkout spike/STORY-001-stack
cd spike-stack
./up.sh           # primeira execução: ~3–6 min (download de imagens + composer)
./test.sh         # executa Pest (4 testes) + Dusk (1 teste E2E em Chromium real)
```

**Resultados observados nesta execução:**

- `./up.sh` orquestrou: build da imagem PHP 8.5-alpine com extensões (`pdo_pgsql`, `zip`, `bcmath` — outras já são built-in em 8.5) + Chromium + ChromeDriver; `composer create-project laravel/laravel:^13.0`; `composer require livewire/livewire:^4.0 pestphp/pest:^4.0 pestphp/pest-plugin-laravel:^4.0 laravel/dusk:^8.0 -W`; `php artisan dusk:install`; configurou `.env` para `DB_CONNECTION=pgsql DB_HOST=db`; gerou `APP_KEY`; rodou migrations criando `users`, `cache`, `jobs` no PostgreSQL 18 real.
- App acessível em `http://localhost:8090` retornou HTTP 200 servindo o componente Livewire `Counter`.
- **Versões efetivamente instaladas e exercitadas:** Laravel Framework **13.11.2**, Livewire **4.3.0**, Pest **4.7.0** (+ `pest-plugin-laravel` 4.1.0), Laravel Dusk **8.6.0**, PostgreSQL **18.4**, PHP **8.5.6**.
- **Pest 4 (Feature suite) — 4 testes, 7 asserções, 0,34s:**
  - ✓ Counter renders at 0 by default
  - ✓ Counter increments on call('increment') (Livewire test runner)
  - ✓ DB connects and `select now()` returns a non-null timestamp from PostgreSQL real
  - ✓ Application returns 200
- **Laravel Dusk (E2E em Chromium 148 headless) — 1 teste, 5 asserções, 0,87s:**
  - ✓ Browser real abre `/`, vê texto "DEFOnline", clica no botão `+1` duas vezes, observa contador `0 → 1 → 2`, valida que `config('database.default') === 'pgsql'`

### Como verificar conformidade (a cobrar em ADRs/IDRs futuros)

- **Linter arquitetural:** Larastan no nível 6+ em CI (decisão de IDR do Programador).
- **Lint de PHP:** Laravel Pint em pre-commit / CI.
- **Suíte de testes:** `php artisan test` (Pest) e `php artisan dusk` (E2E) rodam em cada PR. Regressão bloqueia merge (cruza com RNF §9.2).
- **Cobertura mínima das fórmulas do motor:** ≥10 casos por indicador (RNF §9.2) — automatizado em Pest com datasets.

### Sinais de revisão (quando reabrir esta decisão)

1. **Performance:** p95 de qualquer endpoint crítico ultrapassa NFR §1.2 por > 30% em produção por > 1 semana sem causa de produto/dado.
2. **Capacidade:** sob carga de 300 concurrent users em homologação, vazão cai abaixo do RNF §1.3.
3. **Livewire 4 abandonado / breaking change que invalida o design** — mudar Livewire OU mudar de framework são caminhos separados.
4. **Laravel descontinuado / fork de comunidade** (cenário muito remoto).
5. **Hire-ability:** se ao longo da WAVE-2026-02 não conseguirmos contratar ninguém Laravel disponível, reabrir F6.

### Estimativa de custo recorrente

- **Hospedagem (princípio #11):** VPS Linux Brasil (compatível com LGPD §1.1) — Hetzner Helsinki ou Magalu Cloud ou Locaweb VPS, ~R$ 80–150/mês por ambiente (homologação + produção iniciais).
- **Licença:** Laravel = MIT, gratuito. Livewire = MIT, gratuito. Pest = MIT, gratuito. Dusk = MIT, gratuito. Composer = MIT.
- **PHP/Linux:** gratuito.
- **Banco:** PostgreSQL 18 (self-hosted no VPS no início) — gratuito; eventual migração para gerenciado (Neon, RDS, Crunchy Bridge) é decisão futura — STORY-004 (Infra).
- **Total inicial estimado (2 ambientes):** ~**R$ 200/mês**, ordem de magnitude, sem incluir CDN, e-mail transacional, gateway de pagamento (decisões separadas).

## Consequências

### Positivas (o que ganhamos)

- **Velocidade de entrega imediata:** Laravel Breeze + Livewire fazem o primeiro endpoint logado funcionar no dia 1. Sem `composer require` de 12 libs avulsas; sem decidir validation lib, sem decidir ORM, sem decidir queue.
- **Coerência de stack:** um único `composer.lock`, um único processo, um único deploy. Nada de "depende de qual app o bug é".
- **TDD ergonômico:** Pest com sintaxe expressiva (`it('does X', fn() => ...)`) torna escrever teste agradável — disciplina TDD vira hábito, não fricção.
- **E2E em browser real desde o dia 1:** Laravel Dusk integrado, sem stack JS separada para isso.
- **LGPD/segurança:** Laravel tem hashing seguro padrão, cookies seguros padrão, CSRF nativo, rate limit nativo, validation extensiva. Defaults seguros (princípio #4).
- **Reutilização do reset:** **derrubamos** o pesadelo do monorepo Turborepo + 3 apps + Prisma + NestJS. Agora é 1 app, 1 framework.

### Negativas / trade-offs aceitos

- **Lock-in em PHP/Laravel.** Migrar a lógica de cálculo do motor para outra linguagem em ~2 anos custaria semanas; é trade-off explícito.
- **PHP "image problem".** Pode dificultar contratação de dev sênior em outras stacks; não é problema técnico, é cultural. Aceito.
- **Livewire 4 maturidade < Hotwire.** Aceito por F6 (familiaridade do PO).
- **Sessão server-side custa cookie + lookup no DB.** Aceito porque sessão server-side é o RNF §4.3 default e Postgres dá conta tranquilamente neste volume.

### Neutras (mudanças que precisam ser notadas)

- **Não há FE separado.** Toda equipe (PO + dev + IA) trabalha no MESMO codebase. Não há "time de FE" nem "time de BE" — não cabe no tamanho atual.
- **Filament é uma opção futura para back-office administrativo do EB Parcerias** (cadastro de plans, settings, etc) — não é parte desta ADR; vira IDR quando houver necessidade real.

### Para o time

- **Impacto em estórias existentes:**
  - STORY-002 (Topologia macro) — recebe esta decisão como input: monolito modular Laravel.
  - STORY-003 (Persistência) — recebe Eloquent + Postgres 18 como input. Decide multi-tenancy, audit log, soft/hard delete.
  - STORY-004 (Infra) — recebe PHP-FPM + nginx + Postgres como artefatos a containerizar.
  - STORY-005 (CI/CD) — recebe `php artisan test` + `php artisan dusk` + Pint + Larastan como gates.
  - STORY-006 (Observabilidade) — recebe Pail/Logging/Telescope (dev) como hooks; decide ferramenta de produção.
  - STORY-007 (Hello world deployado) — **destravada** quando esta ADR for `accepted`.

- **ADRs/PDRs relacionados que esta decisão limita ou destrava:**
  - **Destrava:** ADRs Topológica, Persistência, Infra, CI/CD, Observabilidade (STORY-002 a 006).
  - **Limita:** qualquer ADR futura que proponha "vamos adicionar um serviço em outra linguagem" precisa argumentar contra Postgres-first (#3) **e** contra a uniformidade da stack (#4 + Coerência).

- **Necessidade de spike de validação:** **sim, e já está EXECUTADA** — branch `spike/STORY-001-stack`, ver "Plano de verificação".

## Aprovação humana

> Esta seção é o registro formal do aceite.

- **Status final:** ✅ aceita
- **Aprovado por:** Alexandro
- **Data:** 2026-05-20
- **Forma do aceite:** aprovado em chat (sessão de 2026-05-20, após bump para versões mais recentes da stack).
- **Condicionantes do aceite:** nenhuma. PO solicitou apenas que a ADR usasse as versões `latest stable` em vigor em 2026-05-20 (Laravel 13, PHP 8.5, PostgreSQL 18, Livewire 4, Pest 4), o que foi atendido antes do aceite.

### Em caso de rejeição

- **Motivo:** —
- **Próximos passos sugeridos:** —

---

## Histórico

- 2026-05-20 — criada como `proposed` pelo Arquiteto (STORY-001 SPIKE de stack). Primeiro spike funcional em `spike/STORY-001-stack` commit `1510a43` (Laravel 11 + Livewire 3 + Pest 3 + PHP 8.3 + Postgres 16).
- 2026-05-20 — PO solicitou versões mais recentes; ADR atualizada para Laravel 13 + Livewire 4 + Pest 4 + PHP 8.5 + Postgres 18; spike re-validada em commit `3fc1bb4` (Pest 4/4 + Dusk 1/1 verde).
- 2026-05-20 — aceita pelo PO Alexandro em chat; status `proposed` → `accepted`.
