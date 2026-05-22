---
adr_id: ADR-005
slug: infra
title: Infra do DEFOnline — VPS BR genérica + Ansible + Caddy + Docker Compose, homologação real e produção como código
status: accepted
decided_at: 2026-05-21
decided_by: arquiteto
approved_by: Alexandro
supersedes: null
superseded_by: null
related_adrs: ["ADR-001", "ADR-002", "ADR-003", "ADR-004"]
related_pdrs: ["PDR-001"]
related_epics: ["EPIC-000", "EPIC-001", "EPIC-002", "EPIC-003"]
related_stories: ["STORY-004"]
created_at: 2026-05-21
updated_at: 2026-05-21
type: infra
---

# ADR-005 — Infra do DEFOnline

## Contexto

ADR-001 fixou Laravel 13 + PostgreSQL 18 (self-hosted no MVP). ADR-002 fixou a topologia **um codebase / uma imagem Docker / três modos de processo** (`web` + `worker` + `scheduler`) com Postgres como fonte única de estado durável, **volume nomeado** (lição aprendida: bind mount inode quebra com `git reset --hard`). ADR-003 fixou audit log e mascaramento de PII. ADR-004 fixou `/health` + `/ready`, métricas e eventos em Postgres, alertas via Telegram, cold storage 12 meses para logs como **dependência desta ADR de Infra**.

Esta ADR decide as **camadas de hospedagem** que faltavam para destravar a STORY-007 (hello world deployado em homologação) e cobrir o critério "entrega em produção desde dia 1" do PO:

1. **Provedor cloud / VPS** + região + serviços principais.
2. **Ferramenta de IaC / automação** — princípio "automação por padrão" do PO impede cliques manuais.
3. **Três ambientes** (local, homologação, produção): o que cada um oferece, o que difere, e por quê.
4. **Reverse proxy / TLS termination** na borda.
5. **Storage de objetos** (PDFs do diagnóstico, cold storage de log) + **backup off-site** (Postgres dump diário) — RNF §2.3, §2.4, §5.1.
6. **DNS e domínios** públicos.
7. **Runbook automatizado para recriar do zero** qualquer ambiente.
8. **Custo recorrente** por ambiente e total.

Restrições estruturais herdadas (não reabrir):

- **Residência de dados em Brasil** (RNF §1.1) — provedor cujo único datacenter seja fora do Brasil exige TIA documentado, evitado no MVP.
- **3 ambientes desde dia 1** (RNF §2.1, princípio do PO).
- **Uptime 99,5% mensal** no MVP (RNF §1.1) — não exige multi-AZ, mas exige restart automático e monitoramento.
- **RPO 24h, RTO ~4h** (RNF §5.2) — backup diário 03:00 BRT off-site é suficiente.
- **TLS válido em URLs públicas, HSTS habilitado** (RNF §2.1).
- **PDFs regeneráveis** a partir do quiz + diagnosis no banco — perda de storage de PDF é perda de cache (RNF §2.3).
- **Storage off-site em provedor distinto do que hospeda o banco** (RNF §2.2, boa prática).
- **Sem orçamento de unicórnio** — princípio #11 do Arquiteto.

Direção estrutural confirmada pelo PO via `AskUserQuestion` em 2026-05-21:

1. **Provedor:** **VPS genérica BR provider-agnóstica** — a ADR fixa o **perfil técnico** da VPS (Ubuntu LTS, 4 GB RAM, IPv4 público, snapshot disponível, residência BR), mas **não amarra a um provedor específico**. Trocar de Magalu para Locaweb para Hostinger é mudar inventário Ansible.
2. **Provisionamento atual:** **só homologação real**; produção é código Ansible pronto, sem `playbook run` até gatilho de promoção.
3. **IaC:** **Ansible** — config management agnóstico ao provedor, YAML, leve, integra com SSH puro. Substitui Terraform/OpenTofu pelo trade-off de simplicidade + reversibilidade de provedor.

A decisão precisa ser tomada agora porque destrava STORY-007 (hello world em homologação por URL) e informa a STORY-005 (CI/CD) sobre **o que** o pipeline faz deploy.

## Forças (drivers) da decisão

- **F1 — Princípio do PO "entrega em produção desde dia 1" + RNF §2.1** — **Alto**. Homologação real, acessível por URL, ao fim do EPIC-000. Não-negociável.
- **F2 — Princípio do PO "automação por padrão" + RNF §10.x** — **Alto**. IaC sempre; nada de cliques manuais; reconstruir ambiente do zero em runbook executável.
- **F3 — Residência BR (RNF §1.1) + LGPD sem TIA** — **Alto**. Provedor 100% fora do Brasil exige cláusula DPA + TIA na Política de Privacidade — atrito jurídico que time pequeno paga. Fica desqualificado no MVP.
- **F4 — Princípio #1 (simplicidade) + #11 (custo) + time muito pequeno** — **Alto**. Cada peça extra (Kubernetes, Terraform Cloud, vault remoto, multi-AZ) é caminho extra para incidente. Ferramenta que cabe em "1 dev humano + IA" ganha.
- **F5 — Princípio #7 (reversibilidade) — provedor-agnóstico** — **Alto**. Stack que amarra a um provedor (Terraform + AWS-only provider, lambdas, Cloud Run) cobra preço caro se VPS precisar mudar. Tratar VPS como "qualquer Linux com SSH" preserva opção.
- **F6 — Princípio #6 (local total = produção)** — **Alto**. Topologia local Docker Compose (ADR-002) **é** a topologia de produção. Diferenças entre ambientes são variáveis (.env, domínio, recursos), não arquitetura.
- **F7 — RNF §5 (backup diário, RPO 24h, RTO 4h, restore trimestral)** — **Alto**. Backup off-site em provedor distinto, encriptado, com runbook de restore automatizado e exercitado.
- **F8 — RNF §2.1 (TLS + HSTS + reverse proxy + rate limit em borda)** — **Alto**. Reverse proxy na borda com TLS automatizado (Let's Encrypt) é não-negociável.
- **F9 — Compatibilidade com ADR-002 (Docker Compose multi-process)** — **Alto**. O `docker-compose.yml` da topologia tem que rodar identicamente em local, homologação e produção. Sem PaaS que exija "1 processo por container declarado fora do compose".
- **F10 — Princípio #4 (opinativo)** — **Médio**. Ansible é o YAML opinativo da família config-mgmt. Terraform/OpenTofu é opinativo também — escolha entre os dois é de pegada operacional, não de princípio.
- **F11 — Custo recorrente concreto (princípio #11)** — **Médio**. Orçamento mental: R$80–150/mês por ambiente, ~R$200/mês total no MVP, escalonando para ~R$300–500/mês quando produção ativar.

## Opções consideradas

### Opção A — VPS BR genérica (provider-agnóstico) + Ansible + Docker Compose + Caddy

- **Resumo:** infra abstrai o provedor de VPS — qualquer VPS Linux Ubuntu LTS 24.04 com 4 GB RAM, IPv4 público, snapshot disponível e residência BR atende. Playbooks Ansible idempotentes provisionam tudo a partir de um host limpo: usuário não-root + chaves SSH, hardening UFW + fail2ban + unattended-upgrades, Docker Engine + Compose plugin, Caddy reverso (TLS automático Let's Encrypt), clone do repo, build e subida do `docker-compose.yml` (ADR-002), `php artisan migrate --force`, scheduler de backup. Secrets cifrados com Ansible Vault no próprio repo. **Provedor concreto** (Magalu Cloud / Locaweb / HostGator BR / Hostinger BR / outro) **é decisão operacional** — o PO escolhe na hora da contratação, dentro do perfil técnico fixado. **Trocar provedor é trocar IP no inventário Ansible** + rodar `ansible-playbook` na nova VPS.

- **Componentes concretos:**

  | Camada | Decisão |
  |---|---|
  | **VPS** | Qualquer provedor BR aceito que atenda perfil técnico (4 GB RAM / 2 vCPU / 80 GB SSD / Ubuntu 24.04 LTS / IPv4 público / snapshot manual ou agendado). Preferência por presença em SP. |
  | **Sistema operacional** | Ubuntu 24.04 LTS (suporte até abr/2029) |
  | **Container runtime** | Docker Engine ≥ 27 + Compose plugin (sem Docker Swarm, sem K8s) |
  | **Orquestração** | `docker compose up -d` direto no host (1 host, 5 containers, mesma topologia da ADR-002) |
  | **Reverse proxy / TLS** | **Caddy 2** em container, TLS automático Let's Encrypt, HSTS habilitado, rate limit em `/login`, `/forgot`, `/cadastro` |
  | **Config management** | **Ansible 2.16+** com inventários por ambiente + Ansible Vault para secrets |
  | **DNS** | Cloudflare DNS (gratuito, BR-friendly, API estável) — proxy off no MVP; `homolog.defonline.com.br` e `app.defonline.com.br` apontam direto para o IPv4 da VPS |
  | **Storage de objetos (PDFs)** | Volume Docker persistente no MVP (`pdfs:/var/www/storage/app/pdfs`); migrar para S3-compat quando volume justificar — RNF §2.3 diz "PDFs são regeneráveis" |
  | **Backup off-site (pg_dump)** | Diário 03:00 BRT → encripta com `gpg --symmetric` → upload **Backblaze B2** (S3-compatible, ~US$0,005/GB/mês, datacenter US/EU; **provedor distinto da VPS** — atende RNF §2.2) |
  | **Cold storage de log 12m (ADR-004)** | Mensal: compacta `storage/logs/laravel-YYYY-MM-*.log` (já redigidos por LogSanitizer) → upload Backblaze B2, mesmo bucket diferente prefixo |
  | **Secrets em runtime** | `.env` no host (não commitado), gerado pelo playbook Ansible a partir de templates Jinja + Vault decifrado |
  | **Monitor de uptime** | UptimeRobot plano free (50 monitores, 5min intervalo) pingando `/health` — dispara Telegram (ADR-004) via webhook |

- **3 ambientes:**

  | Ambiente | Existe agora? | Onde roda | Domínio | RAM | Custo/mês |
  |---|---|---|---|---|---|
  | **local** | ✅ desde STORY-007 | máquina do dev | `localhost:8090` + Caddy opcional via `local.defonline.test` (dnsmasq) | 4 GB host | R$ 0 |
  | **homologação** | ✅ desde STORY-007 | 1 VPS BR (provedor a contratar) | `homolog.defonline.com.br` | 4 GB | ~R$ 80–120 |
  | **produção** | ❌ código Ansible pronto, sem `playbook run` | outra VPS BR (provedor a contratar quando promover) | `app.defonline.com.br` | 8 GB | ~R$ 120–180 (quando ativar) |

- **Diferenças entre ambientes (princípio #6 — local = produção topologicamente):**

  | Aspecto | local | homologação | produção (prevista) |
  |---|---|---|---|
  | Topologia (ADR-002) | 5 containers | 5 containers + Caddy | 5 containers + Caddy |
  | Mailpit | ✅ container | ❌ container removido; SMTP real | ❌ SMTP real |
  | Domínio | `localhost` ou `*.defonline.test` | `homolog.defonline.com.br` | `app.defonline.com.br` |
  | TLS | desligado (HTTP em dev) | Let's Encrypt produção (Caddy) | Let's Encrypt produção (Caddy) |
  | `APP_ENV` | `local` | `staging` | `production` |
  | `APP_DEBUG` | `true` | `false` | `false` |
  | Log level mínimo | `debug` | `debug` (RNF §6.1) | `info` (RNF §6.1) |
  | Pail / Telescope | habilitado | desabilitado | desabilitado |
  | Telegram bot (ADR-004) | canal `log` (sem internet) | bot real, chat de homol | bot real, chat de prod |
  | Backup diário | desabilitado | habilitado, retenção 7 dias | habilitado, retenção 30 dias + mensal 12m |
  | Snapshot de VPS | n/a | semanal (princípio #11) | diário (RNF §5.1) |
  | Recursos | 4 GB RAM host | 4 GB / 2 vCPU / 80 GB | 8 GB / 4 vCPU / 160 GB |

- **Como atende aos princípios** (`references/architecture-principles.md`):
  - ✅ **#1 Simplicidade:** 1 host, 1 docker compose, ~5 playbooks Ansible. Sem K8s, sem Terraform, sem service mesh.
  - ✅ **#2 Monolito:** consome a topologia de ADR-002 sem adicionar peça.
  - ✅ **#3 Postgres-first:** Postgres self-hosted no mesmo host inicialmente. Migrar para Postgres gerenciado (Neon/Magalu Postgres/RDS) é supersede futuro **se** custo de operação self-hosted virar dor.
  - ✅ **#4 Opinativo:** Ansible é o opinativo do mundo config-mgmt; Caddy é o opinativo do mundo reverse-proxy-com-TLS; Docker Compose é o opinativo do mundo "vários containers em 1 host".
  - ✅ **#5 Coesão:** playbooks por responsabilidade — `bootstrap.yml` (host limpo), `docker.yml` (runtime), `app.yml` (deploy), `backup.yml` (cron + upload), `restore.yml` (recovery testado).
  - ✅ **#6 Local total:** mesma topologia ADR-002 em local, homol, prod — só `.env` muda.
  - ✅ **#7 Reversibilidade:** **trocar provedor é trocar IP no inventário**. Sem Terraform-provider lock-in.
  - ✅ **#8 Observabilidade:** Caddy expõe access log JSON consumido pelo log stack (ADR-004); `/health` + `/ready` monitorados por UptimeRobot → Telegram.
  - ✅ **#9 Automatizável:** todo passo é playbook idempotente. `ansible-playbook -i inventories/homolog deploy.yml` reconstrói tudo.
  - ✅ **#10 TDD + E2E:** ambiente homol roda `php artisan dusk` no CI contra `homolog.defonline.com.br` antes de promover. Compatível.
  - ✅ **#11 Custo:** ~R$ 100/mês na onda; ~R$ 250/mês com produção ativa.
  - ✅ **#12 Restrições:** explícitas em "Fora de escopo".

- **Prós concretos:**
  - **Sem lock-in de provedor.** Magalu hoje, Locaweb amanhã, qualquer VPS BR depois.
  - **Sem aprender Terraform/OpenTofu.** YAML do Ansible + shell + Docker Compose é dialeto que o time pequeno já domina (memória do projeto já tem deploy via shell + docker compose).
  - **Reverse proxy zero-config TLS.** Caddy negocia Let's Encrypt automaticamente — sem `certbot --renew` cron.
  - **Backup encriptado off-site automatizado.** Cron + `pg_dump` + `gpg` + `b2 upload` em 1 playbook + 1 cron job.
  - **Reconstruir do zero em ~30 min** — provisionar VPS no painel + `ansible-playbook` + restaurar último dump.
  - **Memória do projeto reaproveitada.** Lições aprendidas no deploy pré-reset (volume nomeado, health check via porta interna, DATABASE_URL no compose) cabem nativamente.

- **Contras concretos:**
  - **Criação da VPS é manual** (clicar em painel do provider) — uma vez por ambiente. **Aceitação:** anotado no runbook; documentado nas Notas; criar VPS via API de provedor BR é IDR futuro se virar fricção.
  - **Sem alta disponibilidade.** 1 host = 1 ponto de falha. RTO de 4h cobre (rebuild via Ansible em ~30 min + restore de backup). Sinal de revisão registrado.
  - **Backup em B2 (EUA/EU)** — o **backup do banco do DEFOnline** sai do Brasil. **Análise jurídica:** dump é **encriptado em repouso** com chave GPG só em poder do administrador; provedor B2 não tem acesso. RNF §1.1 trata residência **do banco operacional**; backup encriptado off-site é boa prática (RNF §2.2 pede "provedor distinto"). Documentado na Política de Privacidade na ADR jurídica futura.
  - **Sem painel "bonito" de infra.** Estado vive em `git log` dos playbooks + `ansible-inventory --list`. Aceitação: princípio #1.

### Opção B — Magalu Cloud + Terraform/OpenTofu + Docker Compose + Caddy

- **Resumo:** mesma topologia operacional (1 host, docker compose, Caddy) mas com Terraform/OpenTofu gerenciando o ciclo de vida da VPS no Magalu Cloud (criar/destruir/redimensionar via `tofu apply`). Ansible ainda configuraria o host. Provedor concreto **amarrado** ao Magalu (provider Terraform oficial existente).
- **Como atende aos princípios:**
  - ✅ #2 Automação total (até criação da VPS).
  - ⚠️ #7 Reversibilidade: trocar de Magalu para Locaweb exige reescrever `.tf` (provider Locaweb não existe oficialmente — voltaria a Ansible para Locaweb).
  - ⚠️ #1 Simplicidade: 2 ferramentas IaC (Terraform + Ansible) em vez de 1.
  - ⚠️ #4 Opinativo: opinativo nos dois mundos, mas dobra superfície de aprendizado.
- **Prós:** criação de VPS é parte do `tofu apply`. Estado de infra em `.tfstate` (vantagem: drift detection).
- **Contras concretos:** lock-in no provedor onde o Terraform provider existe (Magalu, AWS, Hetzner); dobra ferramentas para time pequeno; ganho marginal sobre Opção A (criar VPS manualmente é 1 clique a cada 6 meses).
- **Veredicto:** rejeitada pelo trade-off lock-in + complexidade vs. simplicidade de 1 só ferramenta. Pode virar supersede se o time crescer ou se trocar de VPS virar operação frequente (sinal explícito abaixo).

### Opção C — AWS São Paulo (EC2 + RDS + S3 + ALB) + Terraform

- **Resumo:** infra gerenciada AWS — EC2 t4g.small (~R$120/mês) para a app, RDS db.t4g.micro Postgres (~R$200/mês), S3 para PDFs e logs, ALB para TLS, Terraform provisiona tudo.
- **Como atende aos princípios:**
  - ✅ #2 Automação total.
  - ✅ #7 Backup automático e PITR via RDS.
  - ❌ #11 Custo: ~R$ 400+/mês mínimo só com homologação; ~R$ 700+/mês com produção. **Triplica** o orçamento da ADR-001.
  - ⚠️ #7 Reversibilidade: lock-in AWS profundo. Sair custa semanas.
  - ⚠️ #1 Simplicidade: ergonomia premium **se** o dev conhece AWS; curva real de aprendizado se não.
- **Prós:** PITR do RDS atinge RPO de minutos (vs 24h do dump diário); S3 é canônico para storage.
- **Contras concretos:** custo 3-4× acima do necessário no MVP; complexidade que não justifica time muito pequeno; lock-in caro.
- **Veredicto:** rejeitada por custo e por violar princípio #1 e #11 sem ganho proporcional. Reabrir se DEFOnline ultrapassar ~500 MPEs ativas e RTO/RPO virarem restrição comercial concreta.

### Opção D — PaaS (Render / Railway / Fly.io)

- **Resumo:** entregar `git push` + Dockerfile, o PaaS provisiona container + Postgres gerenciado + storage + TLS + monitoramento.
- **Como atende aos princípios:**
  - ✅ #1 Operacionalmente o mais simples.
  - ⚠️ #2 Automação: provider-side, mas IaC pelo time é tipicamente shell + `render.yaml`.
  - ❌ #3 + #1.1 RNF: Render/Railway/Fly não têm região BR (Fly oferece GRU mas Postgres gerenciado deles é US/EU). Viola RNF §1.1.
  - ❌ #7 Reversibilidade: sair de PaaS exige rebuild de toda automação.
  - ⚠️ #9 Compose multi-process: cada PaaS tem sua própria sintaxe; alguns não permitem 3 processos do mesmo codebase trivialmente.
- **Veredicto:** rejeitada por RNF §1.1 e por divergir do docker-compose da ADR-002.

### Opção E — Status quo / não decidir agora

- **Consequência:** STORY-007 não tem alvo de deploy; "entrega em produção desde dia 1" do PO viola; EPIC-000 não fecha.
- **Veredicto:** rejeitada. Adiamento custaria o critério de pronto da onda.

## Matriz comparativa

| Critério (força) | Peso | A — VPS genérica + Ansible | B — Magalu + Tofu + Ansible | C — AWS + Tofu | D — PaaS | E — Status quo |
|---|---|---|---|---|---|---|
| F1 — Homol real ao fim do EPIC-000 | Alto | ✅ | ✅ | ✅ | ✅ | ❌ |
| F2 — Automação por padrão / IaC | Alto | ✅ Ansible | ✅ Tofu + Ansible | ✅ Tofu | ⚠️ provider-side | ❌ |
| F3 — Residência BR (LGPD sem TIA) | Alto | ✅ qualquer VPS BR | ✅ Magalu SP | ✅ sa-east-1 | ❌ sem PaaS BR cabível | ❌ |
| F4 — Simplicidade + time pequeno | Alto | ✅ 1 ferramenta IaC | ⚠️ 2 ferramentas | ⚠️ AWS surface área | ✅ se conhece PaaS | ❌ |
| F5 — Reversibilidade de provedor | Alto | ✅ trocar IP no inventário | ❌ lock-in Magalu via provider | ❌ AWS lock-in | ❌ PaaS lock-in | n/a |
| F6 — Local = produção topologicamente | Alto | ✅ docker compose idêntico | ✅ idem | ⚠️ ALB ≠ Caddy local | ❌ runtime diferente | n/a |
| F7 — Backup off-site RPO 24h / RTO 4h | Alto | ✅ B2 + Ansible | ✅ idem | ✅ RDS PITR | ✅ gerenciado | ❌ |
| F8 — TLS + HSTS + rate limit | Alto | ✅ Caddy automático | ✅ idem | ✅ ALB + WAF | ✅ gerenciado | ❌ |
| F9 — Compatibilidade docker-compose ADR-002 | Alto | ✅ direto | ✅ direto | ⚠️ exige ECS ou EC2 puro | ❌ exige reescrever | n/a |
| F10 — Opinativo (princípio #4) | Médio | ✅ Ansible YAML | ✅ HCL + YAML | ✅ HCL | ✅ render.yaml | n/a |
| F11 — Custo no MVP | Médio | ✅ R$ 100/mês | ✅ R$ 120/mês | ❌ R$ 400+/mês | ⚠️ R$ 150-300/mês | n/a |

Notas: ✅ atende plenamente; ⚠️ atende com ressalva; ❌ não atende.

## Decisão proposta

> **Optamos pela Opção A — VPS BR genérica (provider-agnóstico) + Ansible + Docker Compose + Caddy 2.**
>
> A ADR fixa o **perfil técnico** da VPS (Ubuntu 24.04 LTS, 4 GB RAM, 2 vCPU, 80 GB SSD, IPv4 público, snapshot disponível, residência BR), **não amarra a provedor específico**. Trocar de Magalu para Locaweb para Hostinger é mudar inventário Ansible.
>
> **Homologação real** é provisionada agora em VPS BR (provedor concreto escolhido pelo PO na hora da contratação, dentro do perfil). **Produção é código Ansible pronto, sem `ansible-playbook` ainda** — gatilho de promoção fixado abaixo. Domínios: `homolog.defonline.com.br` (homol) e `app.defonline.com.br` (prod).
>
> **Reverse proxy Caddy 2** em container com TLS automático Let's Encrypt + HSTS + rate limit em endpoints sensíveis. **Backup off-site** via `pg_dump` diário 03:00 BRT, encriptado com GPG, enviado para **Backblaze B2** (S3-compat, provedor distinto da VPS; backup do banco operacional permanece no Brasil — o backup encriptado off-site não viola residência LGPD, documentado abaixo). **DNS Cloudflare** gratuito, proxy off. **Monitor de uptime UptimeRobot** plano free pingando `/health` → Telegram.
>
> **Postgres provisionado via Docker** (container `postgres:18-alpine` no compose, volume nomeado `pgdata`, `postgresql.conf` customizado via directory mount, 3 roles separados (`postgres`/`defonline_app`/`defonline_backup`), extensões `pgcrypto`/`pg_trgm`/`citext`/`pg_stat_statements` habilitadas via migration Laravel, timezone `America/Sao_Paulo`, GRANTs restritos em `audit_logs` e `evento_produto`, senhas geradas pelo Ansible Vault no first boot). Detalhes na Decisão 7.
>
> **Custo recorrente:** ~R$ 100/mês durante a WAVE-2026-01 (só homologação ativa); ~R$ 250/mês quando produção for promovida.

## Decisão 1 — Provedor e VPS (CA-2, CA-4)

### 1.1 Perfil técnico mínimo da VPS (provider-agnóstico)

| Atributo | Valor mínimo | Justificativa |
|---|---|---|
| Sistema operacional | Ubuntu 24.04 LTS (Noble Numbat) | LTS até abr/2029; pacotes Docker + Ansible nativos |
| CPU | 2 vCPU | docker compose (web nginx+fpm + worker + scheduler + db + mailpit/caddy) cabe em 2 vCPU no MVP |
| RAM | 4 GB (homol) / 8 GB (prod) | Postgres 18 + PHP-FPM + 1 worker + Caddy ≈ 1,5 GB ocioso; resto para queries + cache |
| Disco | 80 GB SSD (homol) / 160 GB (prod) | Postgres + logs 90d + PDFs locais cabem; expansível |
| Rede | IPv4 público estático + 1 Gbps | Caddy precisa de IPv4 público para Let's Encrypt; IPv6 opcional |
| Snapshot | manual ou agendado | RNF §5.1: snapshot semanal (homol), diário (prod) |
| Residência | Brasil (preferência: São Paulo) | RNF §1.1 |

### 1.2 Provedores **aceitos** (pré-validados pelo perfil)

A ADR **não escolhe um**; o PO contrata no momento, escolhendo entre:

- **Magalu Cloud** — DC SP, BR-nativo, R$ 60–120/mês 4 GB, snapshot incluso.
- **Locaweb VPS** — BR tradicional, R$ 90–150/mês 4 GB, snapshot pago à parte.
- **Hostinger Brasil VPS** — DC SP, R$ 50–100/mês 4 GB, snapshot incluso (KVM).
- **HostBrasil / UOL Host / KingHost** — opções secundárias, mesmo perfil.

**Critérios de desempate para o PO** (não decisão do Arquiteto, é operacional): preço, qualidade de painel/API, suporte BR responsivo, snapshot integrado.

### 1.3 O que NÃO é aceito sem ADR de supersede

- Provedor cujo **único** datacenter seja fora do Brasil (Hetzner Cloud EU, DigitalOcean sem BR, Vultr sem BR) — viola RNF §1.1 sem TIA documentado.
- Provedor sem **snapshot** de VM — viola RNF §5.1 (snapshot semanal/diário).
- Provedor sem **Ubuntu LTS** disponível — viola perfil técnico.

### 1.4 Trocar de provedor

**Trocar é uma operação Ansible:**

1. Contratar nova VPS no novo provedor (perfil técnico §1.1).
2. Editar `inventories/homolog/hosts.yml` com novo IPv4.
3. `ansible-playbook -i inventories/homolog site.yml` provisiona a nova VPS.
4. Restaurar último dump (`playbooks/restore.yml`).
5. Atualizar DNS Cloudflare (API) — opcional automação via Ansible community module `community.general.cloudflare_dns`.
6. Destruir VPS antiga.

**Tempo estimado:** ~1h, exercitado em sinal de revisão (qualquer fricção operacional grande com o provedor atual).

## Decisão 2 — IaC com Ansible (CA-2)

### 2.1 Stack de automação

| Camada | Decisão |
|---|---|
| Linguagem | YAML (playbooks) + Jinja2 (templates) |
| Runner | Ansible 2.16+ no laptop do dev, executando via SSH na VPS |
| Coleções | `community.general`, `community.docker`, `community.crypto`, `cloudflare_dns` |
| Inventário | YAML em `infra/ansible/inventories/<ambiente>/hosts.yml` |
| Secrets | Ansible Vault (arquivo `infra/ansible/group_vars/<ambiente>/vault.yml` cifrado; senha em `~/.ansible-vault-pass` ou via prompt) |
| Versionamento | Tudo em `infra/ansible/` no repo principal (monorepo de código) |

### 2.2 Estrutura de playbooks

```
infra/ansible/
├── ansible.cfg
├── requirements.yml                # community.general, community.docker, etc
├── inventories/
│   ├── homolog/
│   │   ├── hosts.yml               # IP da VPS de homol
│   │   ├── group_vars/
│   │   │   ├── all.yml             # vars públicas (domínio, etc)
│   │   │   └── vault.yml           # cifrado (DB_PASSWORD, TELEGRAM_BOT_TOKEN, etc)
│   └── producao/
│       ├── hosts.yml               # IP da VPS de prod (vazio até promover)
│       ├── group_vars/
│       │   ├── all.yml
│       │   └── vault.yml
├── playbooks/
│   ├── site.yml                    # tudo (bootstrap + docker + app + backup)
│   ├── bootstrap.yml               # user, SSH key, UFW, fail2ban, unattended-upgrades
│   ├── docker.yml                  # Docker Engine + Compose plugin
│   ├── app.yml                     # clone repo + .env + docker compose up + migrate
│   ├── deploy.yml                  # git pull + build + restart + migrate (deploy contínuo)
│   ├── backup.yml                  # cron pg_dump + GPG + B2 upload
│   ├── restore.yml                 # baixa dump + GPG decrypt + psql restore
│   └── teardown.yml                # destrói containers + remove dados (apenas dev/test)
└── roles/                          # opcional, se algum playbook ficar grande
```

### 2.3 Idempotência

Todo playbook **roda quantas vezes for necessário sem efeito colateral**. Verificado por:

- `ansible-lint` no CI (STORY-005) — gate.
- `--check --diff` em PR — diff Ansible aparece em comentário do PR.
- Testes manuais trimestrais: rodar `ansible-playbook site.yml` em uma VPS já provisionada não pode quebrar nada.

### 2.4 Como funciona o ciclo

| Operação | Comando |
|---|---|
| Provisionar VPS limpa | `ansible-playbook -i inventories/homolog site.yml --ask-vault-pass` |
| Deploy de nova versão | `ansible-playbook -i inventories/homolog playbooks/deploy.yml` (chamado pelo CI/CD — STORY-005) |
| Dry-run (drift) | `ansible-playbook -i inventories/homolog site.yml --check --diff` |
| Restaurar backup | `ansible-playbook -i inventories/homolog playbooks/restore.yml -e dump_date=YYYY-MM-DD` |
| Editar segredo | `ansible-vault edit inventories/homolog/group_vars/vault.yml` |

### 2.5 Estado e drift

**Ansible é stateless** (vs. Terraform que tem `.tfstate`). Trade-off aceito:

- ✅ Sem arquivo de estado para sincronizar entre devs (`.tfstate` em S3 com lock é complexidade que time pequeno paga).
- ⚠️ **Drift detection é por `--check --diff` manual** ou no CI. Não há "o que mudou desde minha última execução".
- **Mitigação:** o host **é** o estado. Snapshot semanal do provedor permite rollback de configuração se necessário.

## Decisão 3 — Reverse proxy Caddy 2 (CA-2)

**Caddy 2** roda em container ao lado do web/worker/scheduler, na borda:

```yaml
# conceitual no docker-compose.yml
caddy:
  image: caddy:2-alpine
  ports: ["80:80", "443:443"]
  volumes:
    - ./infra/caddy/Caddyfile:/etc/caddy/Caddyfile:ro    # directory mount evita lição inode
    - caddy_data:/data                                    # certs Let's Encrypt persistentes
    - caddy_config:/config
  depends_on: [web]
```

**Caddyfile conceitual** (forma final é IDR do Programador):

```
homolog.defonline.com.br {
    tls admin@defonline.com.br
    encode zstd gzip

    @sensitive path /login /forgot-password /cadastro /api/cadastro
    rate_limit @sensitive {
        zone sensitive
        events 5
        window 1m
    }

    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options nosniff
        X-Frame-Options DENY
        Referrer-Policy strict-origin-when-cross-origin
        -Server
    }

    handle /health {
        reverse_proxy web:80
    }
    handle /ready {
        reverse_proxy web:80
    }

    handle {
        reverse_proxy web:80 {
            header_up X-Request-Id {http.request.header.X-Request-Id}
            header_up X-Forwarded-For {remote_host}
            header_up X-Forwarded-Proto {scheme}
        }
    }

    log {
        output file /var/log/caddy/access.log {
            roll_size 100MB
            roll_keep 5
        }
        format json
    }
}
```

**Pontos fixados:**

- **TLS automático Let's Encrypt** — Caddy renova certificados sozinho; sem `certbot --renew` cron.
- **HSTS habilitado** com `preload` (RNF §2.1, §4.3).
- **Rate limit** em `/login`, `/forgot-password`, `/cadastro`, `/api/cadastro` (5 req/min — RNF §2.1, §4.4).
- **Cabeçalhos de segurança** (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`) automáticos.
- **`X-Request-Id` propagado** — consumido pela ADR-002 (`AssignRequestId` respeita header do proxy).
- **Access log JSON** em arquivo (rotacionado a cada 100 MB, mantém 5) — consumido por agregador de log da ADR-004 se necessário.

**Por que Caddy e não Nginx ou Traefik:**

| Critério | Caddy 2 | Nginx | Traefik |
|---|---|---|---|
| TLS automático | ✅ nativo, zero-config | ⚠️ certbot externo + cron | ✅ nativo |
| Sintaxe de config | ✅ Caddyfile (legível) | ⚠️ verbosa | ⚠️ YAML + labels |
| Princípio #4 (opinativo) | ✅ batterias incluídas | ⚠️ DIY | ✅ |
| Memória do projeto | n/a | já usado (com lição inode) | n/a |
| Tamanho da imagem | ~50 MB | ~30 MB | ~100 MB |

**Caddy vence em ergonomia e ausência de cron de renewal.** Nginx perdeu pela lição aprendida (bind-mount inode quebra reload) — Caddy também usa **directory mount** para o Caddyfile (mesmo cuidado aplicado).

## Decisão 4 — Storage de objetos e backup (CA-2)

### 4.1 PDFs do diagnóstico

- **MVP:** volume Docker persistente no host (`pdfs:/var/www/storage/app/pdfs`). Reside na mesma VPS do app.
- **Justificativa:** RNF §2.3 declara que **PDFs são regeneráveis** a partir do quiz + diagnosis no Postgres. Perda do volume é perda de **cache**, não de dado original. Volume + snapshot semanal cobre cenários normais.
- **URLs assinadas** com validade 15 min (Laravel `Storage::temporaryUrl()`) — RNF §2.3.
- **Sinal de revisão:** quando volume de PDFs ultrapassar 20 GB no disco do host **ou** quando precisarmos servir PDFs de múltiplas instâncias, migrar para S3-compat (Backblaze B2 ou outro). IDR do Programador implementa o driver — `filesystems.php` muda de `local` para `s3` sem refator de código.

### 4.2 Backup do PostgreSQL

**Política (RNF §5.1):**

| Item | Configuração |
|---|---|
| Frequência | Diário, **03:00 BRT** |
| Mecanismo | `docker compose exec -T db pg_dump --format=custom --no-owner --no-acl defonline` |
| Encriptação | `gpg --symmetric --cipher-algo AES256 --passphrase-file ~/.backup-gpg-pass` |
| Provedor off-site | **Backblaze B2** (S3-compatible, datacenter US-West/EU-Central, **provedor distinto da VPS** conforme RNF §2.2) |
| Bucket | `defonline-backups-<ambiente>` (private, lifecycle policy) |
| Path | `postgres/<ambiente>/YYYY/MM/dump-YYYYMMDD-HHMM.sql.gz.gpg` |
| Retenção | **30 dias rolantes** + **12 cópias mensais** (lifecycle B2: `keep last 30 daily + 12 monthly`) |
| Restore testado | **Trimestralmente em homologação**, com dump real anonimizado — runbook `playbooks/restore.yml` |
| PITR | **Fora do MVP** (RNF §5.1 declara "desejável; entra quando infra suportar WAL streaming") |

**Por que Backblaze B2 (e não AWS S3 ou Cloudflare R2):**

- **Custo:** US$ 0,006/GB/mês de storage + US$ 0,01/GB de egress. Para 5 GB de backup mantido ao longo de 12 meses (~60 GB), ~US$ 0,40/mês ≈ R$ 2,00/mês.
- **S3-compatible:** `aws s3 cp` funciona, providers Ansible já suportam.
- **Provedor distinto da VPS:** atende RNF §2.2.

**Por que o backup encriptado off-site (US/EU) não viola RNF §1.1 (residência BR):**

- O **banco operacional** vive 100% na VPS BR — atendimento direto da RNF.
- O **backup é dado em repouso encriptado com chave simétrica AES-256** que **só o administrador do DEFOnline possui**. Backblaze não tem acesso ao conteúdo do dump — apenas armazena bytes opacos.
- Equiparável a guardar um HD encriptado em cofre fora do país: o dado **lógico** continua sob controle BR; o substrato físico de armazenamento de backup, não.
- **A Política de Privacidade (ADR jurídica futura)** declara explicitamente que **backups off-site encriptados** são armazenados em provedor estrangeiro como parte da política de continuidade — base legal: legítimo interesse (segurança do tratamento, Art. 7º IX).

### 4.3 Cold storage de log 12 meses (consumido de ADR-004)

ADR-004 fixou retenção de log 90 dias online (canal `daily` do Laravel) + 12 meses cold storage.

**Esta ADR fixa o mecanismo:**

- Cron mensal `playbooks/archive-logs.yml`: compacta `storage/logs/laravel-YYYY-MM-*.log` em tar.zst, encripta GPG, upload Backblaze B2 em `logs/<ambiente>/YYYY/laravel-YYYY-MM.tar.zst.gpg`.
- **Lifecycle B2:** retenção 12 meses, depois deleta automaticamente.
- **Custo:** ~R$ 1/mês para volume MVP.

## Decisão 5 — DNS e domínios (CA-6)

### 5.1 Registro do domínio

Domínio **`defonline.com.br`** registrado em **Registro.br** (pelo PO ou EB Parcerias). Custo: R$ 40/ano.

### 5.2 DNS authoritative

**Cloudflare DNS** — plano free:

- **Por que Cloudflare:** painel ergonômico, API estável (consumida por Ansible se quisermos), propagação rápida, DDoS protection grátis na borda (proxy opcional, **desligado no MVP** porque Caddy faz TLS direto — proxy Cloudflare exige modo "full strict" e adiciona um hop).
- **Modo proxy:** **off** no MVP (DNS only). Reabrir se DDoS virar problema concreto.

### 5.3 Registros

| Hostname | Tipo | Aponta para | Ambiente |
|---|---|---|---|
| `defonline.com.br` | A | (futuro: hotsite produção; no MVP, redireciona) | — |
| `app.defonline.com.br` | A | IPv4 da VPS de produção | produção (quando ligar) |
| `homolog.defonline.com.br` | A | IPv4 da VPS de homologação | homologação |
| `mail.defonline.com.br` | MX/TXT | provedor SMTP (decisão futura) | transacional |

### 5.4 Atualização de DNS

- **MVP:** **manual no painel Cloudflare** quando promover ambiente ou trocar VPS. Aceita por baixa frequência (~mensal).
- **Pós-MVP:** automatizar via Ansible `community.general.cloudflare_dns` se virar fricção — IDR.

## Decisão 6 — Recursos de cada ambiente (CA-2)

### Local (dev)

- Já decidido em ADR-002 — `docker compose up` em laptop do dev.
- Esta ADR adiciona: `caddy` container opcional com `local.defonline.test` via dnsmasq (princípio #6 — paridade com homol/prod).
- Custo: R$ 0.

### Homologação

- **Provisionada agora** (parte do critério de pronto da STORY-007).
- VPS BR (provedor a contratar), perfil §1.1 mínimo (4 GB / 2 vCPU / 80 GB).
- Domínio: `homolog.defonline.com.br`.
- Caddy TLS Let's Encrypt produção.
- Backup diário B2, retenção 7 dias (homol não precisa de 30+12, mas o pipeline é o mesmo).
- Snapshot semanal do provedor.
- Telegram bot dedicado a homol (chat separado de prod) — ADR-004.
- UptimeRobot pingando `/health` a cada 5 min.
- **Custo:** ~R$ 80–120/mês.

### Produção

- **NÃO provisionada agora** — código Ansible pronto, sem `ansible-playbook` aplicado.
- VPS BR (provedor a contratar quando promover), perfil §1.1 estendido (8 GB / 4 vCPU / 160 GB).
- Domínio: `app.defonline.com.br`.
- Caddy TLS, Backup B2 retenção 30d + 12 mensais, Snapshot diário do provedor.
- Telegram bot dedicado a prod, UptimeRobot pingando `/health` a cada 5 min.
- **Custo (quando ativar):** ~R$ 120–180/mês.

### Gatilho de promoção da produção

Produção sai do "código pronto" para "ambiente vivo" quando **uma das condições** for atingida:

1. **EPIC-001 (Cadastro mínimo)** estiver `done` em homologação validado pelo PO.
2. **PO decidir abrir beta fechado** para 20 MPEs (marco do north star) — independentemente de qual EPIC estiver pronto.

Quando o gatilho disparar: o PO contrata a VPS de produção (perfil §1.1 estendido), o Programador (ou Arquiteto na sessão) edita `inventories/producao/hosts.yml` com o IP, e executa `ansible-playbook -i inventories/producao site.yml --ask-vault-pass`.

## Decisão 7 — Configuração do container Postgres (provisionamento via Docker)

Postgres roda como container do `docker-compose.yml` (ADR-002), não como serviço bare-metal no host. Esta seção fixa **as decisões estruturais** de configuração desse container — tuning, extensões, roles, locale, credenciais e GRANTs — para que o Programador apenas implemente, sem reabrir decisão arquitetural.

### 7.1 Imagem e volume

| Item | Decisão |
|---|---|
| Imagem | `postgres:18-alpine` (ADR-001 / ADR-002 já fixaram a versão piso 18) |
| Volume de dados | **volume nomeado** `pgdata` (lição inode da ADR-002), montado em `/var/lib/postgresql/data` (lembrando que **PG 18 mudou** a convenção de mount herdada de ≤ 17 — volumes antigos não sobem) |
| Volume de config | **directory mount** `./infra/postgres/conf.d:/etc/postgresql/conf.d:ro` (`postgresql.conf` da imagem oficial faz `include_dir '/etc/postgresql/conf.d'` quando definido pela `command:`) |
| Init scripts | **directory mount** `./infra/postgres/initdb:/docker-entrypoint-initdb.d:ro` — só executados quando volume `pgdata` está vazio (first boot); idempotência preservada |
| Healthcheck do container | `pg_isready -U $POSTGRES_USER -d $POSTGRES_DB` com `interval: 10s`, `timeout: 3s`, `retries: 5`, `start_period: 30s` |

### 7.2 Tuning de `postgresql.conf` (por perfil de VPS)

Aplicado via arquivo `infra/postgres/conf.d/10-defonline.conf` (e perfil específico em `20-<perfil>.conf` montado por ambiente). Imagem oficial Postgres 18 aceita `-c config_file=...` ou `include_dir`.

**Valores fixados (perfil homologação — 4 GB / 2 vCPU / 80 GB SSD):**

| Parâmetro | Valor | Justificativa |
|---|---|---|
| `shared_buffers` | `1GB` | ~25% da RAM (recomendação clássica do Postgres) |
| `effective_cache_size` | `2GB` | ~50% da RAM (estimativa do que o SO cacheia) |
| `work_mem` | `16MB` | sort/hash por operação por conexão; conservador para evitar OOM com 100 conexões teóricas |
| `maintenance_work_mem` | `128MB` | `VACUUM`, `CREATE INDEX`, restore mais rápido |
| `max_connections` | `100` | default Postgres; web (~30) + worker (~10) + scheduler (~5) + manutenção = folgado |
| `random_page_cost` | `1.1` | SSD (default 4.0 assume HDD) |
| `effective_io_concurrency` | `200` | SSD |
| `wal_compression` | `on` | reduz tamanho do WAL ~50%, custo CPU baixo |
| `checkpoint_completion_target` | `0.9` | smoothing de I/O |
| `default_statistics_target` | `100` | default; aumentar quando query planner errar |
| `log_min_duration_statement` | `1000ms` | log de slow query (consumido pela observabilidade da ADR-004) |
| `log_line_prefix` | `'%m [%p] %q%u@%d '` | timestamp + pid + user@db nos logs do Postgres |
| `log_timezone` | `'America/Sao_Paulo'` | logs do Postgres em BRT |
| `timezone` | `'America/Sao_Paulo'` | `NOW()`, `current_timestamp` em BRT — alinhado com `created_at` da app |
| `password_encryption` | `scram-sha-256` | já é default no PG 18, fixado por defesa em profundidade |

**Perfil produção (8 GB / 4 vCPU / 160 GB SSD):**

| Parâmetro | Valor delta |
|---|---|
| `shared_buffers` | `2GB` |
| `effective_cache_size` | `5GB` |
| `work_mem` | `32MB` |
| `maintenance_work_mem` | `256MB` |
| `max_connections` | `150` |

**Demais parâmetros idênticos a homologação.** Quando promover a produção, copia-se `20-prod.conf` no lugar de `20-homol.conf`; tudo o mais é o mesmo arquivo `10-defonline.conf` versionado.

**Sinais de revisão de tuning:**

- p95 de query > 100 ms sustentado → revisar `work_mem` e `shared_buffers`.
- `pg_stat_statements` revelando queries com `temp_blks_written > 0` frequente → aumentar `work_mem`.
- Conexões aproximando-se de `max_connections` → adicionar `pgbouncer` (IDR ou supersede).

### 7.3 Extensões habilitadas

**Extensões habilitadas via migration Laravel** — não via init script. Justificativa: versionamento + testabilidade (Pest roda migrations) + auditabilidade (cada `CREATE EXTENSION` aparece em `database/migrations/` com data e justificativa).

```php
// database/migrations/2026_05_22_000000_enable_postgres_extensions.php (exemplo)
public function up(): void
{
    DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');   // hashing/cripto in-DB (ADR-003)
    DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');    // similarity search (ADR-003)
    DB::statement('CREATE EXTENSION IF NOT EXISTS citext');     // emails case-insensitive (ADR-003)
    // pgvector: habilitado quando STORY de busca semântica entrar (não no MVP)
}
```

**Lista canônica do MVP:** `pgcrypto`, `pg_trgm`, `citext`. Demais extensões (`pg_partman`, `pgvector`, `pg_stat_statements`) entram via migration nova quando a estória que precisar delas justificar — **sem ADR de supersede**, basta migration Laravel.

**`pg_stat_statements`** — recomendado **habilitar desde o MVP** para observabilidade de queries (ADR-004 não dependeu dela, mas é gratuito): adicionar `shared_preload_libraries = 'pg_stat_statements'` em `10-defonline.conf` + `CREATE EXTENSION pg_stat_statements` em migration. **Decisão:** **habilitar desde a STORY-007**.

### 7.4 Roles separados (defesa em profundidade)

**Três roles** criados no first boot via init script SQL `infra/postgres/initdb/00-roles.sql`:

| Role | Permissões | Quem usa |
|---|---|---|
| `postgres` (superuser) | tudo (criado pela imagem via `POSTGRES_USER=postgres`) | apenas migrations DDL via Ansible/playbook; **não** usado pela app em runtime |
| `defonline_app` | `CONNECT` no DB; `USAGE` no schema `public`; `SELECT/INSERT/UPDATE/DELETE` em tabelas de domínio; **NÃO** tem `SUPERUSER`, `CREATEROLE`, `CREATEDB`, nem `BYPASSRLS` | conexão da app em runtime (web/worker/scheduler) — `.env` `DB_USERNAME=defonline_app` |
| `defonline_backup` | `CONNECT` no DB; `SELECT` em todas as tabelas; `pg_read_all_data` (PG 14+) | cron de `pg_dump` (ADR-005 §4.2) — não pode escrever, defesa em profundidade contra `DROP TABLE` por erro do script de backup |

**Por que não usar o superuser para a app:**

- **Princípio do menor privilégio** (`security-architecture.md`). App comprometida com superuser = atacante pode `DROP DATABASE`, ler/alterar `audit_logs` (que deveria ser INSERT-only), criar extensões maliciosas.
- **Defesa em profundidade:** mesmo que SQL injection passe pela validação Eloquent, o estrago é limitado ao que `defonline_app` pode fazer.

**Por que usuário dedicado para backup:**

- `pg_dump` com superuser é desnecessariamente perigoso (script de backup comprometido = `DROP`).
- `pg_read_all_data` (`GRANT pg_read_all_data TO defonline_backup`) cobre o caso "ler tudo sem mutação".

### 7.5 GRANTs restritos em tabelas append-only

Aplicado via **migration Laravel** após criação das tabelas `audit_logs` (ADR-003) e `evento_produto` (ADR-004):

```php
// na migration que cria audit_logs:
DB::statement('REVOKE UPDATE, DELETE ON audit_logs FROM defonline_app');
DB::statement('GRANT INSERT, SELECT ON audit_logs TO defonline_app');

// idem para evento_produto:
DB::statement('REVOKE UPDATE, DELETE ON evento_produto FROM defonline_app');
DB::statement('GRANT INSERT, SELECT ON evento_produto TO defonline_app');
```

**Resultado:** mesmo que código da app tente `EventoProduto::find(...)->update([...])`, o Postgres rejeita com `permission denied`. Defesa em profundidade que **complementa** o override de `update`/`delete` no model Eloquent fixado em ADR-003 e ADR-004.

**`request_metrics`, `job_metrics`, `business_metrics`** (ADR-004) **não** recebem GRANT restrito — são append-only por convenção, mas leitura/agregação eventual via SQL ad-hoc pode usar `UPDATE` (ex.: corrigir status de job retroativamente em incidente). Aceita.

### 7.6 Locale e encoding do cluster

| Parâmetro | Valor | Justificativa |
|---|---|---|
| Encoding | `UTF8` | default da imagem, único valor sensato em 2026 |
| `LC_COLLATE` / `LC_CTYPE` | `C.UTF-8` | a imagem `postgres:18-alpine` **não** vem com pt_BR.UTF-8 (musl libc); `C.UTF-8` é Unicode-aware sem comportamento BR específico |
| `timezone` (cluster) | `America/Sao_Paulo` | `NOW()`, `created_at` em BRT — alinha com a app |
| `log_timezone` | `America/Sao_Paulo` | logs do Postgres em BRT — facilita correlação com logs da app |

**Sorting com `COLLATE "pt-BR-x-icu"` ad-hoc** quando uma query precisar de ordem alfabética PT (raro no MVP — listagens são tipicamente por data). Postgres 18 traz suporte a `provider = icu` que pode ser usado nas migrations quando necessário, **sem** mudar o locale do cluster.

**Se rebuild de Alpine com pt_BR virar requisito** (algum sorting em massa por nome), a alternativa é trocar para `postgres:18` (Debian-based, ~80 MB maior) ou Dockerfile customizado que adiciona musl-locales. Sinal de revisão registrado.

### 7.7 Credenciais — geração e armazenamento

**No first boot da homologação** (1ª execução de `ansible-playbook site.yml`):

1. Ansible verifica se `inventories/homolog/group_vars/vault.yml` já tem `postgres_superuser_password`, `defonline_app_password`, `defonline_backup_password`.
2. Se **não tem** (first boot): Ansible gera **3 senhas aleatórias de 64 chars** (`{{ lookup('password', '/dev/null length=64 chars=ascii_letters,digits') }}`), **grava no Vault** (cifrado), e usa.
3. Se **tem** (boots seguintes): usa as senhas já gravadas. Idempotência total.
4. Ansible renderiza `.env` da app com `DB_USERNAME=defonline_app` e `DB_PASSWORD={{ defonline_app_password }}` a partir de template Jinja.
5. Init script SQL `00-roles.sql` é renderizado por Jinja **antes** de ser copiado para `/docker-entrypoint-initdb.d/`, inserindo as senhas geradas no `CREATE ROLE ... WITH PASSWORD '...'`.

**Rotação:** manual no MVP (editar Vault + rodar playbook que faz `ALTER ROLE ... WITH PASSWORD ...` + atualiza `.env` + restart da app). **Frequência:** anual ou após incidente. Automatização via cron Ansible é IDR futuro quando o time crescer.

**O `.env` no host** (gerado pelo playbook) é `chmod 600 root:root` — apenas `root` lê.

### 7.8 Verificação de conformidade (princípio #9)

- **Teste Pest** `Tests\Architectural\PostgresRoleSeparationTest`: tenta conectar como `defonline_app` e executar `DROP TABLE`, `CREATE EXTENSION`, `UPDATE audit_logs SET ...` — asserta que **todas** falham com `permission denied`. Bloqueia merge.
- **Teste Pest** `Tests\Architectural\PostgresExtensionsEnabledTest`: `SELECT extname FROM pg_extension` deve conter `pgcrypto`, `pg_trgm`, `citext`, `pg_stat_statements`.
- **Teste Pest** `Tests\Architectural\PostgresTuningTest`: `SHOW shared_buffers` retorna valor esperado por ambiente (smoke test do `postgresql.conf` montado).
- **Playbook Ansible `verify.yml`**: roda os 3 testes acima contra o ambiente alvo após `site.yml`. Falha = deploy abortado (CI/CD da STORY-005 consome).

## Diagrama (infra macro)

```mermaid
flowchart LR
  user[👤 Roberto<br/>navegador]
  letsencrypt[(Let's Encrypt<br/>ACME)]
  cf_dns[(Cloudflare DNS<br/>authoritative)]
  uptimerobot[(UptimeRobot<br/>monitor /health)]
  telegram[(📲 Telegram<br/>bot homol + prod)]
  b2[(Backblaze B2<br/>backup + cold log<br/>encriptado GPG)]
  rfb[(API RFB<br/>CNPJ — externo)]

  subgraph dev["Local (laptop dev)"]
    devcomp["docker compose<br/>web + worker + scheduler<br/>+ db + mailpit"]
  end

  subgraph homol["Homologação (VPS BR 4GB) — ATIVA"]
    direction TB
    caddyH["Caddy 2<br/>:80/:443<br/>TLS auto + HSTS<br/>+ rate limit"]
    composeH["docker compose<br/>web + worker + scheduler<br/>+ db + caddy"]
    cronH["cron 03:00 BRT<br/>pg_dump + gpg + b2 upload"]
  end

  subgraph prod["Produção (VPS BR 8GB) — IaC PRONTO, NÃO PROVISIONADA"]
    direction TB
    caddyP["Caddy 2<br/>:80/:443"]
    composeP["docker compose<br/>(idêntico a homol)"]
    cronP["cron 03:00 BRT<br/>pg_dump + gpg + b2 upload"]
  end

  ansible["Ansible<br/>(laptop dev / CI)<br/>SSH"]

  user -->|HTTPS<br/>homolog.defonline.com.br| caddyH
  user -.->|HTTPS<br/>app.defonline.com.br<br/>(quando ativar)| caddyP
  caddyH --> composeH
  caddyP --> composeP
  caddyH <-->|ACME challenge| letsencrypt
  caddyP <-.->|ACME challenge| letsencrypt
  cf_dns -.->|A record| caddyH
  cf_dns -.->|A record| caddyP
  composeH -->|SMTP / HTTPS| rfb
  composeP -.->|SMTP / HTTPS| rfb

  cronH -->|encrypted dump| b2
  cronP -.->|encrypted dump| b2

  uptimerobot -->|GET /health| caddyH
  uptimerobot -.->|GET /health| caddyP
  uptimerobot -->|webhook| telegram

  ansible -->|SSH| caddyH
  ansible -.->|SSH| caddyP

  devcomp -.- ansible

  classDef external fill:#eee,stroke:#999,stroke-dasharray:5
  classDef inactive fill:#fafafa,stroke:#bbb,stroke-dasharray:3
  class user,letsencrypt,cf_dns,uptimerobot,telegram,b2,rfb external
  class prod,caddyP,composeP,cronP inactive
```

**Legenda:**

- Caixas com borda **tracejada** = não ativas no momento (produção é código Ansible pronto, sem `playbook run`).
- Setas tracejadas = caminhos válidos quando o respectivo ambiente estiver ativo.
- Linhas sólidas = caminhos em uso na WAVE-2026-01.

## Justificativa

A Opção A vence pela convergência simultânea de F3 + F4 + F5 + F6 + F11:

1. **F3 (residência BR) + F5 (reversibilidade):** ao definir o provedor pelo **perfil técnico** em vez do nome, atendemos LGPD sem amarrar a um único provider — Magalu hoje, Locaweb amanhã, qualquer VPS BR depois. Princípio #7 honrado.
2. **F4 + F11 (simplicidade + custo):** 1 ferramenta IaC (Ansible) + 1 reverse proxy (Caddy) + 1 storage off-site (B2) + 1 monitor (UptimeRobot free). Total operacional cabe em 1 dev humano + IA. ~R$ 100/mês na onda.
3. **F6 (local = produção):** o `docker-compose.yml` de ADR-002 roda **idêntico** em laptop, homol e prod. Diferenças = `.env` + recursos. Bug "só em prod" perde uma origem clássica.
4. **F1 + F2 (homol real + automação):** Ansible idempotente reconstrói qualquer ambiente em ~30 min a partir de host limpo. Critério de pronto cumprido.

Trade-offs honestamente reconhecidos:

- **Criação de VPS é manual (1 vez por ambiente).** Aceita; documentada no runbook.
- **Sem alta disponibilidade.** 1 host = 1 SPOF. RTO 4h cobre via Ansible + restore. Sinal de revisão registrado.
- **Backup off-site sai do Brasil.** Encriptado GPG; dado lógico segue sob controle BR; Política de Privacidade documenta. Trade-off jurídico aceito.
- **Sem `terraform plan` para drift detection.** `ansible-playbook --check --diff` é mecanismo equivalente, manual ou via CI. Aceita.
- **PaaS pronto-pra-usar (Render etc.) ficou de fora.** Cumpriria F1, F4 mais rápido, mas viola F3 (sem BR) e F5 (lock-in profundo) — drivers de peso maior.

## Plano de verificação

### Spike de validação proposto

**Não há spike específica desta ADR.** A STORY-007 absorve a validação natural:

- VPS contratada conforme perfil §1.1.
- `ansible-playbook -i inventories/homolog site.yml` provisiona do zero (timer cronometrado).
- `homolog.defonline.com.br` resolve, Caddy serve TLS válido, `/health` retorna 200.
- `php artisan migrate` rodou; tabela `users` existe.
- 1 endpoint do hello world disparou job (e-mail via SMTP de homol — provedor real escolhido em STORY-007 ou IDR).
- Backup diário 03:00 BRT gerou primeiro dump em B2.
- UptimeRobot pinga `/health`; teste de "derrubar VPS por 5 min" dispara Telegram.
- `playbooks/restore.yml` restaura último dump em ambiente efêmero (validação de RTO).

### Como verificar conformidade

- **`ansible-lint`** em CI (STORY-005) — gate.
- **`ansible-playbook ... --check --diff`** rodando em PR — drift no host vira comentário.
- **Restore de backup exercitado trimestralmente** (RNF §5.1) — agendado em calendário operacional.
- **Snapshot de provedor restaurável** verificado uma vez por trimestre.
- **TLS expira-monitorado** automaticamente pelo Caddy (ele renova); alerta Telegram se renovação falhar (UptimeRobot pega o erro indireto).
- **Custo recorrente revisado mensalmente** — se ultrapassar R$ 200/mês no MVP (sem produção), reabrir esta ADR.

### Sinais de revisão (quando reabrir esta ADR via supersede)

1. **Volume cresce a ponto que 1 VPS não cabe** (CPU > 70% sustentado, RAM saturada, disco > 80%): vertical primeiro (subir o plano da VPS — princípio #1); horizontal só depois.
2. **Provedor atual virou fricção operacional** (downtime > SLO, suporte ruim, painel quebrado): playbook Ansible em provedor novo. Sem reescrever IaC.
3. **Provisionamento manual de VPS vira fricção** (provider trocando > 2× por trimestre): automatizar via Ansible + API do provedor escolhido como IDR; se persistir, considerar Terraform/OpenTofu com provider específico (supersede).
4. **RTO 4h não cobre** algum cenário comercial concreto (cliente pagante exige uptime > 99,9%): considerar Postgres gerenciado com PITR (Neon, Magalu Postgres gerenciado, RDS) como supersede da decisão "Postgres self-hosted".
5. **Backup em B2 cai > 1× no trimestre** ou fica > 24h offline: trocar de provedor S3-compat (Cloudflare R2, Wasabi). Sem refator.
6. **PDFs ultrapassam 20 GB no disco do host:** migrar para S3-compat (driver `s3` no Laravel filesystems) — IDR do Programador, sem ADR de supersede.
7. **Cloudflare DNS gratuito** virar limitação (mais que 100 hostnames, ou exigência de DNSSEC avançado): migrar para Cloudflare pago ou Registro.br DNS — IDR.

### Estimativa de custo recorrente

| Item | Homologação | Produção | Comum | Total MVP (sem prod ativa) |
|---|---|---|---|---|
| VPS BR (4 GB / 8 GB) | R$ 80–120 | R$ 120–180 | — | R$ 80–120 |
| Snapshot do provedor | incluso ou ~R$ 5 | incluso ou ~R$ 10 | — | ~R$ 5 |
| Backblaze B2 (5 GB inicial → 60 GB ano) | — | — | R$ 2–5 | R$ 2–5 |
| Cold log storage B2 | — | — | R$ 1 | R$ 1 |
| Cloudflare DNS | — | — | R$ 0 (free) | R$ 0 |
| Domínio `defonline.com.br` | — | — | R$ 40/ano ≈ R$ 4/mês | R$ 4 |
| UptimeRobot free | — | — | R$ 0 | R$ 0 |
| Telegram bot | — | — | R$ 0 | R$ 0 |
| **Subtotal MVP (só homol)** | | | | **~R$ 95–135/mês** |
| **Subtotal pós-promoção** | | | | **~R$ 220–320/mês** |

Custo confortável dentro do orçamento mental fixado em ADR-001 (~R$ 200/mês para 2 ambientes).

## Consequências

### Positivas (o que ganhamos)

- **Homologação real ao fim da STORY-007**, em URL `homolog.defonline.com.br` com TLS válido, ~R$ 100/mês.
- **Sem lock-in de provedor.** Trocar é editar inventário Ansible + 1 hora de playbook.
- **IaC declarativo e versionado.** Reconstruir homol ou produção do zero é 1 comando + senha do Vault.
- **TLS automático sem cron.** Caddy renova sozinho; uma fonte clássica de incidente eliminada.
- **Backup encriptado off-site automatizado** — RPO 24h cumprido com custo de centavos.
- **Local = produção topologicamente.** Bug "só em prod" perde uma origem clássica.
- **Princípio "entrega em produção desde dia 1" cumprido pela homologação real**, com produção como código pronto sob gatilho de promoção explícito.

### Negativas / trade-offs aceitos

- **Criação de VPS manual (clicar no painel) uma vez por ambiente.** Aceita; ~5 min por evento.
- **Sem HA / failover.** 1 host por ambiente; SPOF. RTO 4h cobre via Ansible + restore. Sinal de revisão registrado.
- **Postgres self-hosted, sem PITR.** RPO 24h documentado e aceito (RNF §5.2). Migração para gerenciado é supersede quando comercial exigir.
- **Backup off-site em provedor estrangeiro** (encriptado GPG). Posicionamento jurídico documentado; Política de Privacidade cobre.
- **Sem drift detection automático** (Ansible stateless). `--check --diff` é mecanismo manual; aceita pelo trade-off de simplicidade.
- **Manutenção do host** (atualizações de SO, Docker Engine, snapshots) é responsabilidade nossa, não do provedor.

### Neutras (mudanças que precisam ser notadas)

- **Domínio `defonline.com.br`** precisa estar registrado em nome da EB Parcerias antes da STORY-007 começar. Se ainda não está, é pré-requisito operacional da estória.
- **Cold storage de log mensal** começa a gerar gasto em B2 a partir do mês 1 — orçamento previsto.
- **Telegram bot precisa ser criado e configurado** em `@BotFather` antes da STORY-007 — runbook detalhado lá.
- **Mailpit é só local.** Em homol e prod, SMTP é provedor real (decisão da STORY-007 ou IDR do Programador).
- **Caddy access log em arquivo** — não precisa de processamento adicional no MVP; futura ADR/IDR pode encaminhar para `business_metrics` ou agregação.

### Para o time

- **Impacto em estórias existentes:**
  - **STORY-001 (Stack):** sem impacto.
  - **STORY-002 (Topologia):** consumida — `docker-compose.yml` é a topologia provisionada.
  - **STORY-003 (Persistência):** consumida — `pg_dump --format=custom` no backup; restore via `pg_restore`.
  - **STORY-004 (Infra):** essa.
  - **STORY-005 (CI/CD):** **input fixado** — pipeline chama `ansible-playbook deploy.yml`; alvo de deploy = VPS de homol identificada por inventário.
  - **STORY-006 (Observabilidade):** consumida — `/health`/`/ready` expostos por Caddy → web; UptimeRobot ping → Telegram via webhook; cold storage de log = B2 + Ansible cron.
  - **STORY-007 (Hello world):** **destravada parcialmente** por esta ADR + ADR-001/002/003/004. Implementação inclui contratar VPS, rodar `ansible-playbook site.yml`, validar URL homol.
- **ADRs/PDRs relacionados que esta decisão limita ou destrava:**
  - **Destrava:** STORY-005, STORY-007.
  - **Limita:** qualquer proposta de migrar para K8s, ECS, Cloud Run, Lambda precisa argumentar contra simplicidade + custo + reversibilidade desta ADR com evidência. Qualquer proposta de provedor sem BR exige TIA documentado primeiro.
- **Necessidade de spike de validação:** **não específica** — STORY-007 absorve.

## Fora de escopo (princípio #12 — restrições são informação)

Decisões deliberadamente **não** tomadas nesta ADR:

- **Provedor concreto de VPS** (Magalu / Locaweb / outro) — operacional, PO contrata.
- **Provedor SMTP transacional** (Mailgun, Postmark, Brevo, Amazon SES) — IDR do Programador na STORY-007 ou ADR específica se acoplar a marketing/transacional separadamente.
- **CDN para assets estáticos** (RNF §2.4) — Caddy serve no MVP; CDN (Cloudflare proxy on, ou BunnyCDN) entra em supersede quando volume justificar.
- **Kubernetes / Swarm / Nomad** — desqualificado; reabrir só com sinal de revisão.
- **Postgres gerenciado** (Neon, Magalu Postgres, RDS) — desqualificado no MVP; supersede futuro.
- **PITR de Postgres** — RNF §5.1 declara "desejável; entra quando infra suportar". Supersede futuro.
- **DR cross-region** — fora; 1 região BR suficiente no MVP.
- **VPN para acesso SSH** — fora; SSH key + UFW restrito a porta 22 com IP allowlist do dev é suficiente no MVP. Reabrir se o time crescer.
- **Image registry privado** — fora; build local com Ansible + tag git SHA é o caminho.
- **Vault remoto (HashiCorp Vault, Doppler, Infisical)** — fora; Ansible Vault no repo é o caminho. Reabrir se time crescer ou compliance exigir.
- **PaaS de DB** (Neon, Supabase) — fora; supersede futuro.
- **Modo proxy do Cloudflare** (DDoS protection) — desligado no MVP; reabrir se ataque concreto.

---

## Aprovação humana

> Esta seção é o registro formal do aceite.

- **Status final:** ✅ aceita
- **Aprovado por:** Alexandro
- **Data:** 2026-05-21
- **Forma do aceite:** aprovado em chat (sessão de 2026-05-21), após adição da Decisão 7 sobre configuração do container Postgres.
- **Condicionantes do aceite:** nenhuma.

### Em caso de rejeição

- **Motivo:** —
- **Próximos passos sugeridos:** —

---

## Histórico

- 2026-05-21 — criada como `proposed` pelo Arquiteto (STORY-004 SPIKE de infra). Três escolhas estruturais (provedor agnóstico via VPS genérica, IaC com Ansible, só homologação real agora) confirmadas pelo PO via `AskUserQuestion` antes da redação.
- 2026-05-21 — adicionada **Decisão 7 — Configuração do container Postgres** (tuning por perfil de VPS, extensões via migration, 3 roles separados, locale, geração de credenciais via Ansible Vault, GRANTs restritos em `audit_logs`/`evento_produto`, testes Pest de conformidade) após o PO pedir esclarecimento sobre cobertura de Postgres na ADR.
- 2026-05-21 — aceita pelo PO Alexandro em chat; status `proposed` → `accepted`.
