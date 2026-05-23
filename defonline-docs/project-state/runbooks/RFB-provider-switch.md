---
runbook_id: RFB-provider-switch
slug: rfb-provider-switch
title: Troca manual do provedor RFB em produção (`cnpja` ↔ `receitaws`)
owner: Arquiteto
related_idrs: ["IDR-004", "IDR-005", "IDR-006"]
related_adrs: ["ADR-002", "ADR-004", "ADR-005"]
related_stories: ["STORY-018"]
created_at: 2026-05-23
updated_at: 2026-05-23
---

# Runbook — Troca manual de provedor RFB

## Quando executar este runbook

Use este procedimento quando **qualquer** das condições abaixo for verdadeira:

1. **Alerta A2 disparou para `tipo='rfb_consulta'` e `provider=<primário>`** (taxa de erro > 5% em 10 min — ADR-004) e o evento persiste por mais de 2 ciclos de alerta (60 min) sem sinal de retomada do provedor.
2. **Provedor primário publicou janela de manutenção** afetando a operação.
3. **Mudança de TOS / preço** no provedor primário forçar troca operacional imediata.
4. **Decisão operacional** (Alexandro/EBC Parcerias) de avaliar o secundário sob carga real por um período definido.

**Não execute** este runbook para falhas transitórias (< 30 min) — o fallback transparente para preenchimento manual (NRF §3.1, STORY-015 CA-4) já absorve o impacto na UX.

## Pré-requisitos

- Acesso SSH ao host da VPS de `production` (Ansible inventory `inventories/producao`).
- Senha do Ansible Vault de `producao`.
- Telegram do alerta A2 confirmando o problema.

## Procedimento

### 1. Confirmação do estado atual

Antes de trocar, registre o estado base. No host de produção:

```bash
# valor atual
grep RFB_PROVIDER /opt/defonline/.env

# últimas 50 consultas e seu provider/status
docker compose exec web php artisan tinker --execute='
DB::table("business_metrics")
  ->where("tipo", "rfb_consulta")
  ->orderByDesc("inserido_em")
  ->limit(50)
  ->get(["inserido_em", "sucesso", "duracao_ms", "meta"])
  ->each(fn($r) => print($r->inserido_em." ".$r->meta."\n"));
'
```

### 2. Atualização do `.env`

**Método correto — via Ansible Vault** (não edite `.env` direto no host; o playbook sobrescreve no próximo deploy):

```bash
# laptop do operador
cd infra/ansible

# abre o vault de produção
ansible-vault edit inventories/producao/group_vars/vault.yml

# Mude:
#   rfb_provider: cnpja
# Para:
#   rfb_provider: receitaws

# Salva e fecha.

# Renderiza o .env no host e aplica
ansible-playbook -i inventories/producao playbooks/app.yml --ask-vault-pass --tags env
```

O playbook `app.yml --tags env` renderiza o template `.env.j2` no host e termina sem restart. **Restart é o passo 3.**

### 3. Restart dos processos `web` e `worker`

```bash
# no host de produção (via ansible ad-hoc)
ansible producao -i inventories/producao -m shell \
  -a 'cd /opt/defonline && docker compose restart web worker' \
  --ask-vault-pass
```

**Scheduler não precisa restart** — não usa `RfbCnpjClient` (consulta RFB acontece no `web` durante o cadastro de Empresa Analisada).

### 4. Validação pós-troca

```bash
# health
curl -fsS https://app.defonline.com.br/health | jq .
curl -fsS https://app.defonline.com.br/ready  | jq .

# confirmar bind do novo cliente
ansible producao -i inventories/producao -m shell \
  -a 'cd /opt/defonline && docker compose exec -T web php artisan tinker --execute="echo get_class(app(App\\Services\\Rfb\\RfbCnpjClient::class));"'
# Esperado: App\Services\Rfb\ReceitawsRfbCnpjClient   (ou Cnpja..., conforme troca)

# aguardar 5 min e validar que novas consultas estão indo no provedor correto
docker compose exec web php artisan tinker --execute='
DB::table("business_metrics")
  ->where("tipo", "rfb_consulta")
  ->where("inserido_em", ">", now()->subMinutes(5))
  ->selectRaw("meta->>\"provider\" as provider, count(*) total, sum(case when sucesso then 1 else 0 end) ok")
  ->groupBy("provider")
  ->get();
'
```

**Critério de sucesso:**
- `/health` e `/ready` continuam 200.
- Bind aponta para o **novo** cliente (verificação acima).
- Em janela de 10 min após o restart, pelo menos 3 consultas registram `meta->>'provider'` igual ao novo primário, com pelo menos 1 sucesso.
- Alerta A2 não dispara para o novo provider em 30 min.

### 5. Registro da operação

- Edite ou crie um arquivo `project-state/runbooks/log/RFB-provider-switch-YYYY-MM-DD.md` com: motivo da troca, IDs dos alertas que dispararam, horário do restart, métricas pós-troca, decisão de retorno (se permanente ou janela de avaliação).
- Notifique no canal Telegram do time: "RFB primário trocado: `cnpja` → `receitaws` às HH:MM BRT — motivo: <…>".

## Rollback

Se a validação do passo 4 falhar (bind errado, `/ready` 503, ou alerta A2 persiste no **novo** provedor):

```bash
# reverte o vault para o valor anterior
ansible-vault edit inventories/producao/group_vars/vault.yml
# (mudar rfb_provider de volta)

ansible-playbook -i inventories/producao playbooks/app.yml --ask-vault-pass --tags env
ansible producao -i inventories/producao -m shell \
  -a 'cd /opt/defonline && docker compose restart web worker' \
  --ask-vault-pass
```

Tempo de rollback: ~5 min.

## O que NÃO fazer

- **Não edite `.env` direto no host** — o próximo deploy sobrescreve via Ansible. Use sempre o Vault.
- **Não troque o primário durante janela de pico** de cadastros, exceto se o primário atual estiver causando incidente — restart causa ~10s de indisponibilidade na consulta RFB (cadastros não-RFB seguem normais; UX da NRF §3.1 mostra fallback manual durante o restart, o que é o caminho não-feliz aceitável).
- **Não desabilite o provedor secundário** removendo as env vars `RFB_<other>_*` — deixá-las populadas mantém a opção de rollback rápido.

## Referências

- IDR-005 — `cnpja` é o primário default em `production`.
- IDR-006 — wiring (cache, RateLimiter, secrets via Vault).
- ADR-005 §7.7 — geração de credenciais e `.env` no host.
- NRF §3.1 — critérios, fallback, monitoramento.
- ADR-004 §1.5 — alerta A2 (taxa de erro 5xx > 5% em 10min).
