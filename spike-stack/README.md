# Spike STORY-001 — Stack POC (Laravel 13 + Livewire 4 + PostgreSQL 18 + Pest 4 + Dusk 8)

Prova de conceito da decisão de stack do EPIC-000 (ver `defonline-docs/project-state/decisions/adr/ADR-001-stack.md`).

**Versões alvo (latest stable em 2026-05-20):**

- PHP **8.5** (8.5.6 instalado)
- Laravel **13** (13.11.2 instalado)
- Livewire **4** (4.3.0)
- PostgreSQL **18** (18.4 alpine)
- Pest **4** (4.7.0) com `pest-plugin-laravel` 4.1.0
- Laravel Dusk **8** (8.6.0)
- Composer **2**, Node + npm (para asset pipeline futuro)
- Chromium 148 + ChromeDriver para Dusk

## O que esta spike demonstra

1. **Framework sobe localmente em ≤ 1 comando** — `./up.sh`.
2. **Conexão com PostgreSQL 18 real** — driver `pgsql`, `migrations` rodam, `select now()` retorna do banco.
3. **Teste de feature (Pest 4)** — componente Livewire `Counter` testado isoladamente.
4. **Teste E2E em browser real (Laravel Dusk 8)** — Chromium headless interage com a página renderizada via Livewire.

A spike é **descartável**: o código aqui não vira produção. O entregável real é o aprendizado documentado na ADR-001.

## Pré-requisitos no host

- Docker + Docker Compose (qualquer versão moderna).
- Internet apenas na **primeira** execução (para baixar imagens e dependências Composer/npm).

## Comandos

```bash
# Primeira execução — scaffolda Laravel, instala Livewire/Pest/Dusk, migra e sobe.
./up.sh

# Rodar suíte de testes (Pest 4 + Dusk).
./test.sh

# Derrubar tudo.
docker compose down
```

A app fica em <http://localhost:8090>. O Postgres em `localhost:5436` (db `defonline_spike`, user/pass `defonline`).

## Arquitetura da POC

```
spike-stack/
├── docker-compose.yml      # 2 serviços: app (PHP 8.5) + db (postgres:18-alpine)
├── Dockerfile              # PHP 8.5 + ext (pdo_pgsql, zip, bcmath) + Chromium + Node + Composer
├── up.sh                   # bootstrap "1 comando"
├── test.sh                 # rodar Pest + Dusk
└── stubs/                  # arquivos que o up.sh copia para o Laravel scaffolded
    ├── Counter.php             # componente Livewire de exemplo
    ├── counter.blade.php       # view do componente
    ├── welcome.blade.php       # página inicial usando <livewire:counter />
    ├── Pest.php                # bootstrap Pest 4 → Laravel TestCase em Feature/Unit
    ├── CounterTest.php         # 3 testes Pest (componente + Postgres)
    ├── DuskTestCase.php        # patch p/ chromedriver externo (Alpine ARM)
    ├── CounterBrowserTest.php  # 1 teste Dusk (browser real)
    └── env.dusk.local          # APP_URL interno do container para Dusk
```

## Mapeamento aos princípios arquiteturais

| Princípio | Como esta POC valida |
|---|---|
| #1 Simples é o belo | 2 containers, 1 framework opinativo. Sem orquestradores, sem mensageria adicional. |
| #2 Monolito | Tudo em um único processo Laravel. |
| #3 Postgres-first | Driver `pgsql`, `select now()` provado, sessão+cache+queue todos usam Postgres (config no compose). |
| #4 Framework opinativo | Laravel default: ORM, migrations, validation, auth, sessões, jobs, queue, console — tudo built-in. |
| #5 Coesão/acoplamento | Componente Livewire isolado e testável independentemente. |
| #6 Local total | Docker Compose; sem dependência de serviço externo. |
| #10 TDD + E2E | Pest 4 (unit/feature) e Dusk 8 (browser real) ambos demonstrados rodando. |

## Notas técnicas (gotchas resolvidas)

- **PHP 8.5** já traz built-in: `opcache`, `mbstring`, `intl`, `pcntl`, `exif`. Apenas `pdo_pgsql`, `zip`, `bcmath` precisam ser instalados via `docker-php-ext-install`.
- **PostgreSQL 18** mudou a convenção: o volume agora monta em `/var/lib/postgresql` (não `/var/lib/postgresql/data`). Volumes herdados de PG ≤ 17 precisam ser recriados.
- **Pest 4 + Laravel 13:** flag `-W` no `composer require` é necessária porque Pest 4.7 requer `phpunit ^12.5.24`, abaixo do que Laravel 13 trava por default — o downgrade é trivial.
- **Dusk no Alpine ARM:** `DuskTestCase::prepare()` precisa ser sobrescrito para NÃO chamar `startChromeDriver()` (ele tenta binário Linux x86); rodamos `chromedriver` separado em background dentro do container.
- **Chrome dentro de container:** flags `--no-sandbox --disable-dev-shm-usage` obrigatórias.
- **APP_URL p/ Dusk:** `.env` aponta para `http://localhost:8090` (host externo). `.env.dusk.local` aponta para `http://localhost:8000` (porta interna onde `artisan serve` escuta).

## Limpeza

```bash
docker compose down -v   # remove containers + volume do Postgres
rm -rf app/              # remove o Laravel scaffolded
```
