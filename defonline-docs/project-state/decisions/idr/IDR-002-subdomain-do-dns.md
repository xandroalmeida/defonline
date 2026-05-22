---
idr_id: IDR-002
slug: subdomain-do-dns
title: Subdomínio defonline.xandrix.com.br no DNS DigitalOcean (em vez de defonline.com.br/Cloudflare)
status: accepted
decided_at: 2026-05-22
decided_by: programador
owner_agent: programador (claude-opus-4-7)
related_story: STORY-007
related_adrs: ["ADR-005"]
related_idrs: []
supersedes: null
superseded_by: null
created_at: 2026-05-22
updated_at: 2026-05-22
---

# IDR-002 — Subdomínio em zona existente do PO + DNS DigitalOcean

## Contexto

ADR-005 §5.1/§5.2 prevê:
- Domínio próprio `defonline.com.br` registrado no Registro.br (R$ 40/ano).
- DNS authoritative na **Cloudflare** (plano free).
- Registros: `homolog.defonline.com.br` e `app.defonline.com.br` apontando para as VPSs.

No fechamento de Phase 3 da STORY-007 (2026-05-22), o PO indicou que **prefere reaproveitar** uma zona DNS que já controla — `xandrix.com.br` — em vez de registrar `defonline.com.br` agora. E o DNS dessa zona está no **DigitalOcean** (não Cloudflare).

Sub-domínios alvos passam a ser:
- **Homologação:** `defonline.xandrix.com.br` (já apontado).
- **Produção (quando promover):** a definir — provavelmente `app.defonline.xandrix.com.br` ou registro de `defonline.com.br` no momento da promoção.

## Decisão

> **Decidi usar `defonline.xandrix.com.br` como subdomínio da homologação e DigitalOcean como provedor de DNS, em vez do `defonline.com.br` + Cloudflare previsto na ADR-005.**

A escolha de provedor DNS fica **variável no inventário Ansible** (`infra/ansible/inventories/homolog/group_vars/all.yml` carrega `app_domain` e `dns_provider` como vars), preservando reversibilidade barata para o esquema da ADR-005 quando o PO comprar o domínio próprio.

## Por quê

- **Custo zero hoje vs. R$ 40/ano** — registrar `defonline.com.br` adiciona custo sem benefício imediato. Pode esperar até a entrada em produção real.
- **DigitalOcean DNS é gerenciado, ergonômico e gratuito** para qualquer cliente DO. Funcionalmente equivalente a Cloudflare para o uso atual (registros A; sem proxy/DDoS na borda).
- **PO já controla a zona** `xandrix.com.br` — propagação imediata, sem onboarding em provedor novo.
- **Princípio #1 (simplicidade) + #11 (custo)**: aproveitar o que já existe satisfaz o critério "URL pública acessível em homol" sem cerimônia adicional.
- **Reversibilidade preservada** (princípio #7): trocar de `xandrix.com.br` para `defonline.com.br` exige apenas (a) registrar o domínio, (b) atualizar `app_domain` em `group_vars` + rodar playbook `dns.yml` (ou manual), (c) Caddy renova certificado automaticamente para o novo nome.

## Alternativas consideradas

- **Comprar `defonline.com.br` agora** — fiel à ADR-005, mas R$ 40/ano e ~24h de propagação inicial sem benefício imediato. Decisão de marketing/produto, não de Phase 3.
- **Cloudflare DNS apontando para `xandrix.com.br`** — exige migrar zona do DO para Cloudflare. Atrito operacional grande para benefício marginal (proxy DDoS está fora do MVP).
- **Subdomínio sob `defonline.com.br` direto** — descartado pelo motivo acima (sem domínio).

## Consequências

### Para outros agentes
- **Variáveis Ansible canonicas** para domínio e DNS provider:
  ```yaml
  # infra/ansible/inventories/homolog/group_vars/all.yml
  app_domain: defonline.xandrix.com.br
  dns_provider: digitalocean
  ```
  Qualquer playbook que precise do domínio deve ler `{{ app_domain }}` — nunca hard-coding.
- **Caddyfile** (`infra/caddy/Caddyfile.j2`) usa `{{ app_domain }}` no bloco de site.
- **TLS via Let's Encrypt** funciona normalmente — Caddy negocia certificado para o domínio entregue.
- **Smoke test do `release-homolog.yml`** usa `https://{{ app_domain }}/health` — atualmente `https://defonline.xandrix.com.br/health`.

### Para o projeto
- ADR-005 §5 fica parcialmente divergente do código real. **Não é supersede** — é apenas uma escolha operacional dentro do espaço de "DNS gerenciado + subdomínio público com TLS". A ADR descreve uma instância possível; este IDR descreve a instância adotada na Phase 3 da STORY-007.
- Quando o PO decidir comprar `defonline.com.br`, basta atualizar `app_domain` + criar IDR-002 superseder (ou ADR de supersede se a decisão tiver impacto maior).

### Trade-offs aceitos
- Subdomínio "soa" menos institucional que `defonline.com.br` para uso público real.
- Quando comprar o domínio próprio, exige redirect/365 + atualização de bookmarks dos beta users.

## Como verificar

- `host defonline.xandrix.com.br` resolve para o IPv4 da VPS de homol.
- Smoke test do CI (`curl -fsSL https://defonline.xandrix.com.br/health`) retorna 200 + JSON.
- TLS válido (Let's Encrypt) — `openssl s_client -connect defonline.xandrix.com.br:443` mostra cadeia confiável.
