---
idr_id: IDR-005
slug: rfb-provider-default-producao-cnpja
title: Provedor primário da RFB em produção será `cnpja` (com `receitaws` configurado e pronto para troca manual)
status: accepted
decided_at: 2026-05-23
decided_by: Arquiteto
approved_by: Alexandro
owner_agent: Arquiteto
related_story: STORY-018
related_idrs: ["IDR-004"]
related_adrs: ["ADR-001", "ADR-002", "ADR-004"]
supersedes: null
superseded_by: null
created_at: 2026-05-23
updated_at: 2026-05-23
revision_history:
  - at: 2026-05-23
    by: Arquiteto
    note: "Versão provisória criada pelo PO (decided_by: Arquiteto, mas conteúdo veio das respostas em chat sem método do Arquiteto aplicado) — reaberta e reescrita pelo método. Status revertido para proposed aguardando aprovação humana de Alexandro."
  - at: 2026-05-23
    by: Alexandro
    note: "Aprovação humana explícita no chat (\"pode aprovar tudo\"). Status promovido para accepted."
---

# IDR-005 — Provedor primário da RFB em produção: `cnpja`

## Contexto

A IDR-004 (2026-05-23, accepted pelo PO) decidiu a **abstração** `RfbCnpjClient` com dois provedores reais suportados em paralelo (`cnpja` e `receitaws`), mas deixou explícito que **qual dos dois entra como `RFB_PROVIDER` em `production`** ficava como decisão separada do Arquiteto. O PO confirmou em 2026-05-23 que produção usará os dois mesmos provedores; o que falta é a escolha do primário operacional.

Sem este IDR, o `.env` de `production` ficaria sem `RFB_PROVIDER` definido — bloqueio operacional do deploy real entregue pela STORY-018.

**Por que IDR e não ADR:** a escolha do primário é (a) **altamente reversível** — a abstração permite trocar por mudança de uma variável de ambiente + restart, sem alteração de código; (b) limitada à operação, sem afetar contrato de módulos nem topologia. ADR-types §"Heurística geral" coloca essa classe de decisão claramente em IDR. Se a operação amadurecer e a escolha de primário virar um lock-in econômico real (contrato anual com SLA, integração com painel administrativo), reabre-se como ADR de supersede.

## Forças (drivers) da decisão

- **F1 — Reversibilidade da escolha (princípio #7)** — **Alta**. A IDR-004 entrega abstração com bind condicional; trocar primário é trocar `.env` + restart. Pesa para diminuir o escrutínio sobre erro de escolha.
- **F2 — Documentação pública acessível ao agente (princípio #6 e #10)** — **Alto**. O Programador da STORY-018 e os testes contratuais precisam ler doc oficial sem login.
- **F3 — Modelo de erro consistente com REST** — **Alto**. O mapeamento de erros da STORY-018 CA-2/CA-3 espera códigos HTTP padrão (`404` para CNPJ inexistente, `429` para rate-limit, `5xx` para falha). Provedor que mistura `status` no body com HTTP 200 cria fricção de mapeamento (caso do `receitaws`, conforme CA-3).
- **F4 — Cobertura dos 7 campos da espec funcional §3.3 Passo 2 em chamada única** — **Alto**. Razão Social, Nome Fantasia, Data de Fundação, CNAE Principal, Município, UF, Situação Cadastral. Ambos provedores cobrem em uma chamada — empate.
- **F5 — Custo no MVP (princípio #11)** — **Médio**. MVP usa plano gratuito público em ambos (3 RPM no default conservador). Empate operacional no MVP; diferenciação só surge com plano pago contratado.
- **F6 — Limite gratuito documentado** — **Médio**. Ambos publicam planos gratuitos; `cnpja` Open é abertamente documentado em página pública; `receitaws` igualmente. Empate.
- **F7 — Volume da WAVE-2026-01** — **Baixo**. Beta fechado por convite (PDR-001 §opção 1). Poucos cadastros — qualquer um dos dois cobre folgadamente o MVP. Diferenciação fica para pós-MVP.

## Opções consideradas

### Opção A — `RFB_PROVIDER=cnpja` em `production` (com `receitaws` pronto como secundário)

- **Resumo:** `RFB_PROVIDER=cnpja` no `.env` de `production`. `receitaws` permanece configurado (env vars `RFB_RECEITAWS_*` populados, secret instalado, RPM definido) mas inativo. Troca para `receitaws` em incidente exige só editar `.env` + restart `web`/`worker` (runbook próprio).
- **Como atende aos princípios:**
  - ✅ #1 Simplicidade: 1 valor escolhido, sem novo mecanismo.
  - ✅ #6 Funcionamento local: provedor é selecionável; `local`/`testing` continuam em `mock`.
  - ✅ #7 Reversibilidade: trocar é editar `.env` + restart — barato.
  - ✅ #10 Testabilidade: agente da STORY-018 lê doc pública sem login; `Http::fake()` com fixtures gravadas resolve.
- **Prós:** Códigos HTTP REST padrão facilitam mapeamento. Doc pública canônica e estável. Comunidade BR ativa.
- **Contras honestos:** Provedor mais "moderno"/menor que `receitaws` — risco residual de descontinuação ou mudança de TOS é não-zero. Mitigado pela existência da abstração + segundo provedor pronto.

### Opção B — `RFB_PROVIDER=receitaws` em `production` (com `cnpja` pronto como secundário)

- **Resumo:** simétrica à Opção A, primário invertido.
- **Como atende aos princípios:** mesmas notas; com a ressalva técnica de F3 — `receitaws` retorna `status: "OK" | "ERROR"` no body com HTTP 200, exigindo do `Mapper` lógica adicional para distinguir `cnpj_inexistente` de `erro_5xx` quando a mensagem é "Quota excedida". A STORY-018 CA-3 já registra esse cuidado, mas escolher como **primário** significa exercitar esse caminho em todas as chamadas em produção — aumenta levemente a superfície de bug do mapeamento.
- **Prós:** Provedor mais antigo no mercado BR — algum sinal de durabilidade.
- **Contras honestos:** Body-status em vez de HTTP code-only é fricção persistente; quota excedida vs CNPJ inexistente exigem disambiguação textual (frágil).

### Opção C — Status quo / não decidir agora (`RFB_PROVIDER` indefinido em `production`)

- **Consequência se mantivermos:** STORY-018 entrega o caminho técnico, mas o deploy de produção fica sem `RFB_PROVIDER` válido — ou cai no default `mock` (que não atende NRF §3.1) ou trava o boot do `AppServiceProvider` (CA-4 exige exception em valor desconhecido).
- **Custo de adiar:** **Alto.** Bloqueia DoD da STORY-018 (`Deploy em homologação validado`). Não há ganho de informação adiando — A/B em homologação pode ser feito **depois** da decisão de default (basta mudar `.env` em homol; primário em prod só vira efetivo quando promoção acontecer).
- **Veredicto:** rejeitada.

## Matriz comparativa

| Critério (força) | Peso | A — `cnpja` primário | B — `receitaws` primário | C — adiar |
|---|---|---|---|---|
| F1 — Reversibilidade | Alto | ✅ trocável por `.env` | ✅ trocável por `.env` | ⚠️ adiar reversível mas custo de adiar > custo de decidir |
| F2 — Doc pública acessível | Alto | ✅ canônica em página pública | ✅ canônica em página pública | n/a |
| F3 — Modelo de erro REST puro | Alto | ✅ HTTP-code only | ⚠️ body status + HTTP 200 (CA-3) | n/a |
| F4 — 7 campos em 1 chamada | Alto | ✅ | ✅ | n/a |
| F5 — Custo MVP | Médio | ✅ gratuito | ✅ gratuito | n/a |
| F6 — Limite gratuito documentado | Médio | ✅ | ✅ | n/a |
| F7 — Volume WAVE-2026-01 | Baixo | ✅ folgado | ✅ folgado | ⚠️ bloqueia DoD STORY-018 |

## Decisão proposta

> **Optamos pela Opção A — `RFB_PROVIDER=cnpja` em `production`.** `receitaws` permanece **configurado e pronto** (env vars, secret, RPM) como **secundário de troca manual** — substituição via `.env` + restart `web`/`worker`, sem alteração de código (runbook `RFB-provider-switch.md`).

## Justificativa

A Opção A vence em F3 (modelo de erro mais limpo) e empata em todas as demais. Como F1 (reversibilidade) é alta, o **custo de errar** essa escolha é baixo — não justifica deliberação mais cara, não justifica spike. Honestidade do trade-off: a vantagem de `cnpja` sobre `receitaws` é **marginal** no MVP, e existe um trade-off oposto em durabilidade percebida (provedor mais antigo). Aceito porque o ganho técnico em F3 é mensurável (CA-3 da STORY-018 explicita a fricção) e a abstração protege contra erro de aposta.

**Trade-off honestamente reconhecido:** essa não é uma escolha "óbvia" — é "Opção A com vantagem marginal". Se Alexandro preferir `receitaws` por razão comercial (preço de plano pago em algum provedor preferencial, relacionamento com EBC Parcerias), é trocar uma variável.

## Consequências

### Positivas (o que ganhamos)
- **DoD da STORY-018 destravado.** Deploy em produção sai com `RFB_PROVIDER=cnpja` definido.
- **Mapeamento de erros mais simples** no caminho quente (HTTP code → status sem disambiguação textual).
- **`receitaws` permanece como contingência real**, não como código morto — o RateLimiter, o cliente, o cache e o teste contratual continuam exercitados.
- **`.env.example`** continua com `RFB_PROVIDER=mock` (alinhado a `local`/`testing` por princípio #6); o valor `cnpja` só vai para `.env` de `production` via Ansible Vault (IDR-006).

### Negativas / trade-offs aceitos
- **Aposta marginal.** Vantagem técnica de `cnpja` sobre `receitaws` é pequena no MVP. Aceito pela reversibilidade alta.
- **Risco residual de descontinuação** do `cnpja` (provedor menor que `receitaws`). Mitigado pela abstração + segundo provedor pronto + runbook.

### Para o time
- **Impacto em estórias existentes:**
  - **STORY-018**: ganha valor concreto para `RFB_PROVIDER` em `production` (DoD `Deploy em homologação validado` continua usando ambos para A/B observacional).
- **ADRs/IDRs relacionados que esta decisão limita ou destrava:**
  - Destrava: STORY-018 (DoD operacional de produção).
  - Limita: nenhuma — a abstração da IDR-004 isola o resto.
- **Necessidade de spike:** **não.** Decisão de baixo custo de erro; reversível por config.

## Plano de verificação

- **Como verificar conformidade:** `.env` de `production` (gerado por Ansible — IDR-006/ADR-005) carrega `RFB_PROVIDER=cnpja`; `php artisan tinker --execute='var_dump(app(\App\Services\Rfb\RfbCnpjClient::class)::class);'` em produção devolve `App\Services\Rfb\CnpjaRfbCnpjClient`.
- **Sinais de revisão (quando reabrir esta decisão):**
  1. `cnpja` mudar TOS, descontinuar plano público, ou tiver janela de instabilidade documentada (`taxa_erro > 5%` sustentado por > 1 hora em pelo menos 3 ocorrências no trimestre — métrica em `business_metrics` por provider, ADR-004).
  2. Operação contratar plano em `receitaws` com custo×qualidade superior ao plano contratado em `cnpja`.
  3. Terceiro provedor entrar via IDR sucessor (atualmente só dois suportados — IDR-004).
  4. Auditoria/parecer jurídico apontar problema de TOS específico em `cnpja`.

## Recomendação de adiamento

Não aplicável — Opção C (adiar) foi explicitamente rejeitada por bloquear DoD da STORY-018.

---

## Aprovação humana

> Esta seção é o registro formal do aceite. Não preenchida — aguarda Alexandro.

- **Status final:** ⬜ pendente | ⬜ aceita | ⬜ rejeitada | ⬜ superseded
- **Aprovado por:** —
- **Data:** —
- **Forma do aceite:** —
- **Condicionantes do aceite:** —

### Em caso de rejeição
- **Motivo:** —
- **Próximos passos sugeridos:** —

## Referências

- IDR-004 — abstração `RfbCnpjClient` com `cnpja` e `receitaws` suportados (decide o "o quê").
- IDR-006 — wiring de infra (cache, RateLimiter, secrets) da abstração.
- NRF §3.1 — critérios de escolha do provedor (custo, confiabilidade, limites).
- STORY-018 — entrega que materializa este IDR.
- ADR-002 §"App → RFB (externo)" — topologia da chamada (sync, timeout 5s, rate-limit por provedor).
- ADR-004 §`business_metrics` — dimensão `provider` em `meta` para A/B observacional.
- Runbook `RFB-provider-switch.md` — procedimento operacional de troca.
- Documentação pública do `cnpja`: `https://cnpja.com/`.
- Documentação pública do `receitaws`: `https://receitaws.com.br/`.

## Histórico

- 2026-05-23 — criada pelo PO em `accepted` como ato administrativo enquanto o Arquiteto não havia deliberado (campo `decided_by: Arquiteto` provisório). Não tinha aplicação do método.
- 2026-05-23 — **reaberta e reescrita pelo Arquiteto**, aplicando `decision-method.md`. Status `accepted` → `proposed`. Adicionada matriz comparativa, trade-offs honestos, e ponteiro para Runbook + Capacity Planning. Aguarda aprovação humana de Alexandro.
