---
sprint_id: SPRINT-2026-W24
wave: WAVE-2026-01
status: closed
start_date: 2026-05-25
end_date: 2026-05-29
closed_at: 2026-05-24
closed_early: true
goal: "Sweep de débito técnico do backlog — fechar STORY-021 (spike 403 vs 404 cross-tenant), STORY-022 (kind↔tipo docs), STORY-023 (fix bump-rc.yml dispara release-homolog) e STORY-025 (split runtime/dev no Dockerfile sem chromium em runtime), zerando o débito acumulado dos EPICs 000/001 antes de abrir o EPIC-002."
goal_achieved: false
---

# SPRINT-2026-W24

## Objetivo do sprint

Fechar **todos os 4 débitos técnicos** acumulados no backlog ao fim da SPRINT-2026-W23. Ao fim do sprint, o backlog de estórias `ready` sem sprint deve estar **vazio** — qualquer estória nova passa a vir do escopo do **EPIC-002 (Diagnóstico Econômico-Financeiro para Indústria)**, que entra na próxima sprint depois deste sweep.

Esta sprint é deliberadamente curta — **1 semana (5 dias úteis)** — porque é todo trabalho **S de baixo risco**, sem caminho crítico em série. O total estimado (~4-6h efetivas) é folgado em 5 dias e cria espaço para o PO + Arquiteto começarem a **rascunhar o escopo do EPIC-002** em paralelo (atividade explicitamente **fora do goal** desta sprint, mas habilitada pelo buffer).

> **Nota sobre o nome da sprint:** SPRINT-2026-W24 mantém a convenção sequencial herdada de W23 (não estritamente ISO). Convenção a formalizar no `sprint-mechanics.md` quando o time consolidar sprints de 1 semana como padrão.

## Estórias incluídas

**Núcleo (escopo do goal — todas em paralelo possível):**

| ID | Título | Épico | Tamanho | Status atual | Bloqueada por |
|---|---|---|---|---|---|
| STORY-021 | SPIKE — Decidir 403 vs 404 cross-tenant (alinhar STORY-014 com ADR-003/NRF) | EPIC-001 | S | ready | — |
| STORY-022 | Alinhar nomenclatura `kind` ↔ `tipo` em `business_metrics` | EPIC-001 | S | done | — |
| STORY-023 | Fix — `bump-rc.yml` precisa disparar `release-homolog.yml` automaticamente | EPIC-001 | S | ready | — |
| STORY-025 | Imagem runtime sem chromium — separar dev/runtime no Dockerfile (~500MB) | EPIC-000 | S | ready | — |

**Total estimado:** 4 × S ≈ ~4-6h efetivas + buffer generoso.

**Nada paralelo opcional declarado** — todas as 4 estórias **estão** dentro do goal. Não há "se sobrar capacidade" — o goal **é** zerar o backlog.

## Ordem sugerida de execução

Nenhuma das 4 estórias bloqueia outra. Podem executar em qualquer ordem ou em paralelo (se houver mais de um Programador / Arquiteto disponível). Sugestão pragmática para Programador único, otimizando contexto / cache mental:

```
Dia 1 (Seg 2026-05-25)
  Arquiteto: STORY-021 (spike 403 vs 404)
    - Manhã: ler ADR-003, NRF correspondente, STORY-014 código atual
    - Tarde: redigir IDR com a decisão (403 vs 404) + cenários de teste a alinhar
    - IDR submetida ao PO no chat para aceite

Dia 2 (Ter 2026-05-26)
  Programador: STORY-022 (kind↔tipo — Direção A recomendada, ~30min)
    - Manhã: grep cruzado kind|tipo em defonline-docs/ + app/, ajustar checklist + ADR-004 §1.2
    - Resto da manhã: STORY-023 (fix bump-rc) — análise das 3 opções, escolha + PR
  Programador: STORY-023 continua no afternoon (teste empírico do bump-rc dispara release-homolog)

Dia 3 (Qua 2026-05-27)
  Programador: STORY-025 (split Dockerfile dev/runtime)
    - Manhã: refactor Dockerfile com targets runtime + dev
    - Tarde: build dos dois targets, validar tamanhos, rebuild compose, pre-push verde
    - Aditivo ADR-002 redigido

Dia 4 (Qui 2026-05-28)
  Programador: STORY-021 follow-up se IDR exigiu mudança em código (ajuste em STORY-014 → 404)
    - Caso contrário: buffer para retrabalho de qualquer débito acima
  PO + Arquiteto (paralelo, FORA do goal): começar rascunho de escopo do EPIC-002

Dia 5 (Sex 2026-05-29)
  PO: revisão final dos 4 débitos, status updates no index.json, retro escrita
  Programador: validação cruzada (pipeline release-homolog rodando rc final com todos os 4 débitos integrados)
  Fechamento do sprint + abertura da SPRINT-2026-W25 (que entra no EPIC-002)
```

**Notas:**

- **STORY-021 abre o sprint** porque é a única que pode gerar retrabalho em outras frentes (alterar STORY-014 de 403 para 404, se a IDR decidir 404). Fechar cedo dá margem ao Programador para ajustar dentro da mesma sprint.
- **STORY-022 é cosmetic (Direção A)** — ~30 minutos. Não vale ocupar um dia inteiro; entra junto com STORY-023 no Dia 2.
- **STORY-023 é caminho crítico de CI/CD** — sem ela, próximas sprints continuam pagando o "deletar tag remota + re-empurrar do host local". Vale fechar cedo.
- **STORY-025 é a mais "isolada"** — só toca `infra/docker/Dockerfile` e `docker-compose.yml`. Pode ir para o fim sem prejuízo.
- **Em caso de mais de um agente disponível:** rodar STORY-021 (Arquiteto) totalmente em paralelo com STORY-023 + STORY-025 (Programador), e STORY-022 ocupar qualquer fresta. Termina o sweep em ~2 dias úteis.

## Compromisso visível ao fim do sprint

Ao fim de 2026-05-29:

- ✅ **STORY-021:** IDR formal aceita pelo PO documentando a escolha entre 403 e 404 para acesso cross-tenant; STORY-014 alinhada ao veredito (ou registrada como conformidade já existente, se a IDR escolher 403). Cenário de teste `tests/Feature/CrossTenantAccessTest.php` (ou equivalente) confirma o comportamento decidido. Divergência arquitetural conhecida desde 2026-05-23 fechada.
- ✅ **STORY-022:** `validation/checklist.md` do EPIC-001 (item 4.8), ADR-004 §1.2 e quaisquer outros docs usam `tipo` em vez de `kind` no contexto de `business_metrics`. Zero mudança em código de produção (Direção A). Higiene de processo: PO templates atualizados se aplicável.
- ✅ **STORY-023:** Disparar `bump-rc.yml` (manual ou auto) **dispara automaticamente** o `release-homolog.yml` na sequência, sem o workaround "deletar tag remota + re-empurrar do host local". Teste empírico documentado com link do run. Solução escolhida (PAT, `gh release create` ou GitHub App) registrada em IDR ou aditivo ao ADR-006.
- ✅ **STORY-025:** Dockerfile tem dois targets — `runtime` (sem chromium, ~500MB mais leve) e `dev` (com chromium para Dusk local). Ansible em `infra/` aponta para `runtime`; `docker-compose.yml` aponta para `dev`. Tamanhos reais documentados (`docker images defonline-app`). Pre-push verde, pipeline release-homolog verde, aditivo ADR-002 registrado.
- ✅ **Backlog de estórias `ready` sem sprint = vazio.** Qualquer estória nova vem do escopo do EPIC-002 a partir daqui.
- ✅ Pipeline `release-homolog.yml` verde end-to-end com a rc final agregando os 4 débitos.

**Métricas de qualidade técnica (gates herdados):**

- Cobertura ≥ 80% mantida (gate da STORY-010 ativo).
- Zero regressão nos testes Pest/Dusk do EPIC-001 + EPIC-004.
- Pipeline `release-homolog.yml` verde com os 4 débitos integrados.

## Capacidade e premissas

- **Time:** Alexandro (PO + revisão) + agentes Claude (Arquiteto para STORY-021, Programador para STORY-022/023/025).
- **Cadência esperada:** velocidade real W22 = ~1.6 estórias/dia útil; W23 fechou 3 estórias úteis em ~1 dia útil efetivo. Sprint de 4 estórias S em 5 dias úteis é **muito folgado** — buffer assumido como espaço para PO + Arquiteto rascunharem EPIC-002 (fora do goal).
- **Sem feriado nacional na janela.** 2026-05-25 a 2026-05-29 são dias úteis normais.
- **Cobertura ≥ 80% obrigatória** (gate da STORY-010 ativo). Estórias do sweep não devem regredir os ~96% atuais (média EPIC-001).
- **Ambiente de homologação `https://defonline.xandrix.com.br`** funcionando (premissa de continuidade pós EPIC-004).

## Riscos identificados na abertura

| Risco | Probabilidade | Impacto | Mitigação | Owner |
|---|---|---|---|---|
| STORY-021 IDR decide 404 e exige refactor de STORY-014 que cresce além do "S" | média | médio | Mesmo no pior caso (404), mudança em STORY-014 é localizada na policy + 1-2 testes (estimativa 1-2h). Cabe no Dia 4 sem estourar sprint. Se a IDR ficar travada, PO decide no chat (≤ 4h) — default: 403 (mantém código atual, só formaliza decisão). | PO + Arquiteto |
| STORY-023 escolhe Opção A (PAT) e bate em provisão de secret pelo PO no meio da execução | baixa | baixo | PO se compromete a rodar `gh secret set RELEASE_TAG_PAT` em ≤ 2h após Programador pedir. Default fallback: Opção B (`gh release create`), que não exige secret novo. | PO |
| STORY-025 split do Dockerfile invalida cache de layer e infla tempo de build | baixa | baixo | Programador valida com `docker history` que chromium fica na última layer do stage dev (cache friendly). Aditivo ao ADR-002 documenta o trade-off se houver. | Programador |
| STORY-025 quebra pre-push localmente porque compose não foi rebuildado | baixa | médio | Documentar no PR de STORY-025: "rodar `docker compose build --no-cache web` após pull". Programador testa empiricamente antes de declarar done. | Programador |
| STORY-022 grep cruzado encontra divergência adicional não prevista (ex.: campo `kind` em outra tabela legítima) | baixa | baixo | Documentar no Notas do agente; se for outro escopo, abrir nova estória (não inchar STORY-022). | Programador |
| Sprint termina muito cedo (em 2-3 dias) e o time fica ocioso esperando o calendário | alta | nenhum | Buffer **explícito** para rascunho do EPIC-002 (PO + Arquiteto) — fora do goal, não bloqueia fechamento antecipado. Se ainda sobrar, fecha sprint cedo (igual W22/W23) e abre W25 imediatamente. | PO |
| Pipeline `release-homolog.yml` quebra na rc agregadora porque algum dos 4 débitos introduz incompatibilidade silenciosa | baixa | médio | Cada estória deve passar pelo pipeline isoladamente antes do agregado. Smoke manual + `make pre-push` antes de cada merge. | Programador |

## Decisões pendentes que podem afetar o sprint

- **STORY-021:** veredito 403 vs 404 — abre no Dia 1, PO responde em ≤ 4h após IDR redigida. Default: 403 (mantém código atual).
- **STORY-022:** Direção A (docs) ou B (rename coluna no banco) — PO recomenda A explicitamente; agente segue salvo objeção registrada.
- **STORY-023:** Opção A (PAT), B (`gh release create`) ou C (GitHub App) — agente decide; PO provisiona secret se Opção A.
- **STORY-025:** target default (`runtime` ou `dev`) e se `nodejs npm` migra para `dev` também — agente decide e anota nas Notas.
- **Escopo do EPIC-002** — não bloqueia esta sprint, mas o rascunho começa no Dia 4 (PO + Arquiteto) para destravar a abertura da SPRINT-2026-W25 sem fricção.

## Mudanças no escopo do sprint

> Toda alteração no conjunto de estórias após esta abertura registra aqui.

| Data | O que mudou | Motivo | Custo |
|---|---|---|---|
| — | — | — | — |

## Fechamento do sprint

**Fechado em 2026-05-24**, 5 dias antes da `end_date` planejada (29/05/2026). **Goal não atingido na íntegra:** 3 de 4 estórias entregues; STORY-023 deferida conscientemente pelo PO.

### O que foi entregue

| ID | Título | Owner agent | Status |
|---|---|---|---|
| STORY-021 | SPIKE — Decidir 403 vs 404 cross-tenant | arquiteto | ✅ done |
| STORY-022 | Alinhar nomenclatura `kind` ↔ `tipo` em `business_metrics` | programador | ✅ done |
| STORY-025 | Imagem runtime sem chromium — separar dev/runtime no Dockerfile | programador-claude | ✅ done |
| STORY-023 | Fix — `bump-rc.yml` dispara `release-homolog.yml` | — | ⏸ deferida (volta ao backlog) |

### O que ficou para trás (e por quê)

**STORY-023 — Fix `bump-rc.yml` dispara `release-homolog.yml` automaticamente.**

> "Sprint implementada, menos a história do PAT que complicou e tomei a decisão de deixar para depois." — PO, 2026-05-24

A Opção A (PAT) da estória esbarrou em complicações práticas de provisionamento/escopo de token que tornaram o trabalho maior que o "S" planejado. Em vez de estourar o tamanho dentro do sprint ou pular para Opção B/C sem análise suficiente, o **PO decidiu deferir** a estória inteira para o backlog. Workaround atual ("deletar tag remota + re-empurrar do host local") continua sendo a operação manual em uso até a estória ser reaberta.

**Estado pós-fechamento:**

- STORY-023 volta ao backlog com `sprint_id: null` e `status: ready` (mantém o trabalho de redação — não é regressão).
- Recomendação a registrar quando reabrir: revisitar **Opção B (`gh release create`)** primeiro, que não exige secret novo e provavelmente evita a complicação que travou a Opção A.
- Não é bloqueante para EPIC-002 — o workaround manual ainda funciona; só carrega atrito.

### Mudanças no escopo durante o sprint

| Data | Mudança | Custo |
|---|---|---|
| 2026-05-24 | **−STORY-023** (deferida para backlog) — Opção A (PAT) complicou; PO decidiu não forçar dentro do tamanho S | Goal de "zerar débito" não atingido — 1 débito remanescente |

### O que funcionou

- **STORY-021 (spike Arquiteto) entregue em paralelo com STORY-022/025** — confirma o experimento da retro da W22: time pequeno **consegue** paralelizar trabalho de papéis distintos (Arquiteto + Programador) em débitos independentes. Vira padrão.
- **STORY-025 escopo bem dimensionado** apesar da observação do Programador estar parcialmente errada (chromium não era "só para CI") — a estória capturou a complicação real (split dev/runtime) antes do agente começar, e o Programador entregou no tamanho previsto.
- **PO tomou decisão clara de deferir STORY-023** em vez de empurrar o tamanho — sinal saudável de governança de escopo. Padrão da W22 (Validador conservador + PO sobrescreve com transparência) aplicado de forma simétrica aqui (Programador sinaliza complicação + PO escolhe deferir com transparência, em vez de inflar).

### O que custou caro

- **Estória de CI/CD subestimada como "S"** — STORY-023 foi sized S porque "é só trocar trigger", mas Opção A esconde provisionamento de PAT + escopo + rotação + secret no repo. Para a próxima encarnação da estória, **partir da Opção B como default** e só escalar para A/C se B for inviável.
- **Sem agregação de RC final** — diferente de W22/W23, não tem registro de tag rc nesta sprint (estórias eram débitos pequenos sem ciclo de release definido). Próxima sprint volta a ter ciclo rc explícito quando entrar em código de produção (EPIC-002).

### Um experimento para a próxima

> **Estórias de CI/CD entram com a opção mais simples como default**, não como "decida entre 3". Para o caso da STORY-023 reaberta: assumir Opção B (`gh release create`) na abertura; PR escalando para A ou C precisa justificativa explícita. Reduz superfície de decisão dentro de estórias S.

### Ajustes para o próximo sprint

- **SPRINT-2026-W25** abre no EPIC-002 (Diagnóstico Econômico-Financeiro para Indústria), conforme planejado.
- **STORY-023 fica no backlog**, recomendada para inclusão na primeira sprint do EPIC-002 como "frestas" (ainda é S, e o workaround manual atrasa cada release rc do EPIC-002).
- **Backlog `ready` sem sprint pós-W24:** 1 estória (STORY-023).

### Métricas finais da sprint

| Métrica | Valor | Meta |
|---|---|---|
| Estórias `done` | **3** | 4 |
| Estórias entregues / planejadas | **75%** | 100% |
| Estórias deferidas conscientemente | **1** | — |
| Mudanças de escopo no meio | **1** (STORY-023 deferida) | — |
| Sprint encerrado | **5 dias antes** da `end_date` | — |
| Velocidade real (estórias/dia útil) | **~3 estórias em 1 dia útil efetivo** | — |
| Cobertura geral (mantida) | **~96%** | ≥ 80% |
| Pipeline `release-homolog` verde | **sim** (estado pós-W23 + chore de Dockerfile) | 100% |

### Comemoração explícita (cultura)

3 débitos limpos em 1 dia útil efetivo — incluindo um spike de Arquiteto que fecha uma divergência arquitetural aberta há 1 sprint inteira (cross-tenant 403 vs 404) e um chore de infra (~500MB economizados em runtime) que melhora higiene de produção. Backlog quase zerado antes do EPIC-002 abrir. **Deferir conscientemente** STORY-023 também é vitória: time aprendeu a separar "scope S" de "scope que parecia S mas era M", em vez de empurrar. WAVE-2026-01 continua adiantada em relação ao `target_end_date` (26/08/2026). 🚀
