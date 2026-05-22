---
story_id: STORY-004
slug: spike-infra
title: SPIKE — Decisão de infra (cloud, IaC, 3 ambientes, Docker, custo)
epic_id: EPIC-000
sprint_id: null
type: spike
target_role: arquiteto
status: done
owner_agent: arquiteto (claude-opus-4-7)
created_at: 2026-05-20
updated_at: 2026-05-21
estimated_session_size: M
---

# STORY-004 — SPIKE de infra

> **Para o agente Arquiteto:** spike → ADR `accepted`, sem código de produção. Aqui se decide onde tudo roda e como tudo se cria do zero.

## Contexto

O DEFOnline precisa nascer com três ambientes: dev local, homologação acessível por URL, e plano de produção (não obrigatório provisionar produção real nesta onda, mas a topologia precisa estar prevista). Princípios aplicáveis: entrega em produção desde dia 1 (PO), Docker local com paridade alta com produção (#6 e #8 do Arquiteto), IaC sempre (princípio de automação do PO), custo recorrente em ordem de magnitude (#11 do Arquiteto).

- Épico: `epics/EPIC-000-foundation/epic.md`
- Documentos canônicos:
  - `defonline-docs/skills/arquiteto/SKILL.md` e `architecture-principles.md`
  - `defonline-docs/skills/arquiteto/references/adr-types.md` — Tipo 5 Infra (mini-checklist)
  - `defonline-docs/skills/arquiteto/references/nfr-architecture.md` (NFRs de infra — uptime, latência)
  - `defonline-docs/skills/arquiteto/templates/adr.md`
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §1, §2, §3 (NFRs de hospedagem e segurança)

## O quê

Produzir **ADR `proposed`** com `type: infra` decidindo: provedor cloud + região + serviços principais; ferramenta de IaC (Terraform, Pulumi, ou justificativa para outra escolha); definição dos três ambientes (local, homologação, produção) — o que cada um oferece, o que difere, e por quê; estratégia de Docker para dev local (princípios #6 e #8); estimativa de custo recorrente mensal por ambiente; runbook automatizado para recriar do zero qualquer ambiente.

Para esta onda, **homologação precisa estar real e funcionando** (ela é parte do critério de pronto do EPIC-000); produção pode ser apenas "previsto na IaC, ainda não aplicado" se o custo justificar.

## Por quê

Sem decisão de infra, STORY-005 (CI/CD) não tem alvo de deploy. STORY-007 (hello world) não tem onde subir. Princípio "entrega em produção desde dia 1" do PO exige homologação acessível por URL ao fim do EPIC-000 — esta spike define como isso vai existir.

## Critérios de aceite

- [ ] **CA-1:** ADR em `decisions/adr/ADR-XXX-infra.md`, `type: infra`.
- [ ] **CA-2:** ADR cobre integralmente o mini-checklist do `adr-types.md` Tipo 5: provedor + região + serviços principais nomeados; IaC definida; três ambientes configurados desde dia 1; diferenças entre ambientes nomeadas e justificadas; estimativa de custo recorrente por ambiente; como recriar do zero; backup + restore com runbook automatizado.
- [ ] **CA-3:** ADR inclui diagrama de infra (Mermaid: componentes, rede, edge) — recomendado pelo `adr-types.md`.
- [ ] **CA-4:** Mínimo 2 alternativas reais de provedor cloud + status quo, com prós e contras objetivos (preferencialmente baseados em custo, lock-in, ergonomia de IaC, presença regional Brasil).
- [ ] **CA-5:** ADR explicita custo orientativo dos 3 ambientes (ordem de magnitude — não cotação exata) e o custo da homologação real que vai operar durante a WAVE-2026-01.
- [ ] **CA-6:** ADR explicita o domínio público que homologação vai usar (`homolog.defonline.com.br` ou outro decidido pelo Arquiteto).
- [ ] **CA-7:** ADR submetida ao PO. `index.json` atualizado.

## Fora de escopo

- Pipeline CI/CD detalhado — STORY-005 (mas pode acoplar leve com infra).
- Observabilidade — STORY-006.
- Decisão de backup/DR formal de produção — referência ao NFR é suficiente; aprofundamento entra quando produção real for ativada na onda 2.
- LGPD avançada (DPA, transferência internacional) — pode entrar como restrição na escolha do provedor, mas não como sub-spike própria.

## Padrões de qualidade exigidos

Spike — mesma exceção declarada em STORY-001.

## Dependências

- **Bloqueada por:** nada (paralelizável com STORY-001, 002, 003, 005, 006).
- **Bloqueia:** STORY-007 (precisa de homologação acessível).
- **Acoplamento leve com STORY-005 (CI/CD):** o provedor cloud pode influenciar a escolha de pipeline; sinalizar nas duas spikes em caso de tensão.

## Decisões já tomadas

- PDR-001 (escopo da onda).
- Princípio "entrega em produção desde dia 1" — homologação acessível por URL é mandatório.
- Princípio "automação por padrão" — IaC, não cliques manuais.

## Liberdade técnica do agente Arquiteto

Você decide cloud, IaC, ambientes, rede. Você **não decide** stack, topologia, persistência, CI/CD, observabilidade — cada um em sua spike.

## Definição de Pronto

- [ ] CAs satisfeitos. ADR `proposed`. Diagrama renderiza. `index.json` atualizado. Notas preenchidas. `status: in_review`.

## Protocolo do agente

Siga `agent-task-format.md`. Igual à STORY-001.

## Notas do agente

### Decisões tomadas

- **2026-05-21** — Provedor cloud: **VPS BR genérica (provider-agnóstico)**. A ADR fixa o **perfil técnico** (Ubuntu 24.04 LTS, 4 GB RAM, 2 vCPU, 80 GB SSD, IPv4 público, snapshot, residência BR), **não amarra provedor**. Provedores pré-validados: Magalu Cloud, Locaweb, Hostinger BR, HostBrasil, UOL Host. Trocar provedor = editar inventário Ansible. Princípio #7 (reversibilidade) preservado ao extremo. Confirmado pelo PO via `AskUserQuestion` (PO substituiu Magalu específico por "VPS genérica, via SSH/Shell/Ansible").
- **2026-05-21** — IaC: **Ansible 2.16+** com playbooks YAML + Jinja2, inventários por ambiente, Ansible Vault para secrets. Sem Terraform/OpenTofu (1 ferramenta a menos para time pequeno; ganho de drift-detection não justifica). Confirmado pelo PO via `AskUserQuestion`.
- **2026-05-21** — Ambientes: **homologação real agora**, **produção como código Ansible pronto sem `playbook run`**. Gatilho de promoção explícito: EPIC-001 done OU PO decide abrir beta fechado. Confirmado pelo PO via `AskUserQuestion`.
- **2026-05-21** — Reverse proxy: **Caddy 2** em container, TLS automático Let's Encrypt, HSTS, rate limit em `/login`/`/forgot-password`/`/cadastro` (RNF §2.1 e §4.4). Caddyfile via **directory mount** (lição aprendida: bind-mount inode quebra com reload).
- **2026-05-21** — Backup off-site: **`pg_dump --format=custom` diário 03:00 BRT + GPG --symmetric AES256 + upload Backblaze B2** (S3-compat, provedor distinto da VPS — RNF §2.2). Retenção 30d rolantes + 12 cópias mensais (prod); 7d em homol. Restore testado trimestralmente via `playbooks/restore.yml`. PITR fora do MVP (RNF §5.1 declara desejável).
- **2026-05-21** — Storage de PDFs: **volume Docker persistente no MVP**. RNF §2.3 declara PDFs regeneráveis. Migrar para S3-compat quando volume justificar (driver `s3` do Laravel filesystems — IDR do Programador, sem ADR de supersede).
- **2026-05-21** — Cold storage de log 12 meses (input herdado de ADR-004): **cron mensal `playbooks/archive-logs.yml`** compacta `storage/logs/laravel-YYYY-MM-*.log` em tar.zst, GPG, upload B2.
- **2026-05-21** — DNS: **Cloudflare DNS plano free**, proxy off no MVP. Registros: `homolog.defonline.com.br` e `app.defonline.com.br` (CA-6). Atualização manual no painel no MVP; automação via Ansible `cloudflare_dns` é IDR futuro.
- **2026-05-21** — Monitor de uptime: **UptimeRobot plano free** (50 monitores, 5min) pingando `/health` em cada ambiente → webhook para Telegram bot (ADR-004).
- **2026-05-21** — **Decisão 7 adicionada à ADR-005** após pergunta do PO sobre cobertura de Postgres. **Configuração do container Postgres** fixada normativamente: imagem `postgres:18-alpine` + volume `pgdata`; `postgresql.conf` customizado via directory mount com tuning por perfil (4 GB / 8 GB); **3 roles separados** (`postgres` superuser só para migrations, `defonline_app` sem SUPERUSER para runtime, `defonline_backup` read-only via `pg_read_all_data` para `pg_dump`); extensões `pgcrypto`/`pg_trgm`/`citext`/`pg_stat_statements` habilitadas via migration Laravel; locale `C.UTF-8` + timezone `America/Sao_Paulo`; senhas geradas pelo **Ansible Vault no first boot** (64 chars random) e idempotentes nos boots seguintes; **GRANTs restritos em `audit_logs` e `evento_produto`** (REVOKE UPDATE/DELETE) como defesa em profundidade contra o override de model Eloquent de ADR-003/004; 3 testes Pest arquiteturais bloqueiam merge (`PostgresRoleSeparationTest`, `PostgresExtensionsEnabledTest`, `PostgresTuningTest`).

### Descobertas

- **2026-05-21** — A combinação "VPS genérica + Ansible" libera o **princípio #7 (reversibilidade) ao extremo**: trocar de provedor BR é editar 1 linha em `inventories/<env>/hosts.yml` + rodar playbook. Compare com Opção B (Magalu+Terraform): trocar de Magalu para Locaweb exigia reescrever `.tf` (provider Locaweb não existe). Trade-off claro a favor de Ansible no MVP.
- **2026-05-21** — Backup off-site no **Backblaze B2 (datacenter US/EU)** poderia parecer violar RNF §1.1 (residência BR), mas o **dado lógico** segue sob controle BR — B2 recebe apenas bytes opacos encriptados com chave GPG só em poder do administrador. Equiparável a guardar HD encriptado em cofre fora do país. Política de Privacidade vai documentar como **legítimo interesse** (Art. 7º IX) na ADR jurídica futura.
- **2026-05-21** — Caddy vence Nginx em ergonomia (TLS automático sem cron, sintaxe legível) e cabe melhor no princípio #4 (opinativo). Memória do projeto pré-reset usou Nginx (com lição inode do bind-mount). Caddy também usa **directory mount** para Caddyfile — lição aplicada.
- **2026-05-21** — `ansible-playbook --check --diff` substitui razoavelmente o `terraform plan` para drift detection, ao custo de ser manual (ou rodado em CI). Time pequeno topa o trade-off pela ausência de `.tfstate` em S3 com lock.
- **2026-05-21** — Custo recorrente confortável: **~R$ 95–135/mês na WAVE-2026-01** (só homologação ativa); **~R$ 220–320/mês quando produção for promovida**. Dentro do orçamento mental da ADR-001 (~R$ 200/mês para 2 ambientes — produção passa um pouco por causa do plano 8 GB, mas é o preço justo).

### Bloqueios encontrados

- Nenhum bloqueio que exigisse escalonamento. Três escolhas estruturais (provedor agnóstico via VPS genérica, IaC com Ansible, só homologação real agora) foram confirmadas pelo PO via `AskUserQuestion` antes da redação da ADR.

### ADR produzida

- **ADR-005 — Infra do DEFOnline — VPS BR genérica + Ansible + Caddy + Docker Compose, homologação real e produção como código** — `decisions/adr/ADR-005-infra.md` (status: `proposed`).

### Links de evidência

- ADR cobre integralmente o mini-checklist Tipo 5 de `adr-types.md` (CA-2): provedor (perfil técnico §1.1) + região (BR) + serviços principais (VPS Linux, Caddy, Docker, B2, Cloudflare, UptimeRobot); IaC (Ansible 2.16+ com estrutura de playbooks §2.2); 3 ambientes desde dia 1 (§6); diferenças entre ambientes (tabela §6); estimativa de custo recorrente por ambiente (tabela "Estimativa de custo recorrente"); como recriar do zero (`ansible-playbook site.yml`); backup + restore com runbook automatizado (§4.2 + `playbooks/backup.yml` e `playbooks/restore.yml`).
- ADR inclui diagrama Mermaid de infra macro (CA-3) cobrindo: usuário, Caddy, docker compose, Postgres, Cloudflare DNS, Let's Encrypt, UptimeRobot, Telegram, Backblaze B2, RFB. Ambiente de produção desenhado em tracejado (não ativo).
- ADR considera 5 alternativas reais (CA-4): A (escolhida — VPS genérica + Ansible), B (Magalu + Tofu + Ansible), C (AWS São Paulo + Tofu), D (PaaS Render/Railway/Fly), E (status quo). Todos com prós/contras objetivos baseados em custo, lock-in, ergonomia de IaC, presença regional BR.
- ADR explicita custo orientativo dos 3 ambientes (CA-5): tabela com VPS + snapshot + B2 + DNS + domínio + monitor; total MVP ~R$ 95–135/mês, total pós-promoção de produção ~R$ 220–320/mês.
- ADR explicita domínios públicos (CA-6): `homolog.defonline.com.br` (homologação) e `app.defonline.com.br` (produção). DNS authoritative Cloudflare.
- `index.json` atualizado com entrada `ADR-005` em `decisions.adr[]` (CA-7).

### Aprovação humana

- **2026-05-21** — ADR-005 **aceita pelo PO Alexandro em chat**, após adição da Decisão 7 (configuração do container Postgres) pedida pelo PO. Status `proposed` → `accepted`. Estória fechada como `done`. Sem condicionantes.
