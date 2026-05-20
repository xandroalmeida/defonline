# Spike STORY-001 — Stack POC (Laravel 11 + Livewire 3 + Postgres 16)

Prova de conceito da decisão de stack do EPIC-000 (ver `defonline-docs/project-state/decisions/adr/ADR-001-stack.md`).

## O que esta spike demonstra

1. **Framework sobe localmente em ≤ 1 comando** — `./up.sh`.
2. **Conexão com Postgres 16 real** — driver `pgsql`, `migrations` rodam, `select now()` retorna do banco.
3. **Teste unitário (Pest)** — componente Livewire `Counter` testado isoladamente.
4. **Teste E2E em browser real (Laravel Dusk)** — Chromium headless interage com a página renderizada via Livewire.

A spike é **descartável**: o código aqui não vira produção. O entregável real é o aprendizado documentado na ADR-001.

## Pré-requisitos no host

- Docker + Docker Compose (qualquer versão moderna).
- Internet apenas na **primeira** execução (para baixar imagens e dependências Composer/npm).

## Comandos

```bash
# Primeira execução — scaffolda Laravel, instala Livewire/Pest/Dusk, migra e sobe.
./up.sh

# Rodar suíte de testes (unit + E2E).
./test.sh

# Derrubar tudo.
docker compose down
```

A app fica em <http://localhost:8090>. O Postgres em `localhost:5436` (db `defonline_spike`, user/pass `defonline`).

## Arquitetura da POC

```
spike-stack/
├── docker-compose.yml      # 2 serviços: app (PHP 8.3) + db (postgres:16-alpine)
├── Dockerfile              # PHP 8.3 + extensões + Chromium + Node + Composer
├── up.sh                   # bootstrap "1 comando"
├── test.sh                 # rodar Pest + Dusk
└── stubs/                  # arquivos que o up.sh copia para o Laravel scaffolded
    ├── Counter.php             # componente Livewire de exemplo
    ├── counter.blade.php       # view do componente
    ├── welcome.blade.php       # página inicial usando <livewire:counter />
    ├── CounterTest.php         # 3 testes Pest (componente + Postgres)
    └── CounterBrowserTest.php  # 1 teste Dusk (browser real)
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
| #10 TDD + E2E | Pest (unit/feature) e Dusk (browser real) ambos demonstrados rodando. |

## Limpeza

```bash
docker compose down -v   # remove containers + volume do Postgres
rm -rf app/              # remove o Laravel scaffolded
```
