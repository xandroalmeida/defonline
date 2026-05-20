---
epic_id: EPIC-XXX
type: validation-report
validated_at: YYYY-MM-DD
validated_by: validador (sessão <id>)
verdict: pending  # approved | rejected | approved_with_pending
checklist_source: epics/EPIC-XXX-<slug>/validation/checklist.md
---

# Relatório de Validação — EPIC-XXX

## TL;DR

> **Veredito**: <APPROVED | REJECTED | APPROVED com pendências>.
> **Contagem**: X passes, Y passes com ressalva, Z fails (W bloqueantes, V não-bloqueantes), U n/a justificados.
> **Próximo passo recomendado**: <em uma frase>.

---

## Resumo executivo

<1-2 parágrafos. Contexto curto do épico (1 frase para quem não lembra de cabeça), o que foi entregue, achados principais, conexão com o veredito. Tom factual. Veja `reporting-craft.md` para padrão.>

---

## Checklist preenchido

### Bloco 1 — Critérios de aceite das estórias

| Item | Status | Evidência |
|---|---|---|
| 1.1 — Todas estórias com `status: done` no `index.json` | ✅/⚠️/❌/🚫 | <link/hash/observação> |
| 1.2 — CA-1 da STORY-XXX cobre o que se propõe | ✅/⚠️/❌/🚫 | <evidência> |
| 1.3 — CA-2 da STORY-YYY ... | ... | ... |
| ... | ... | ... |

### Bloco 2 — Cobertura de testes

| Item | Status | Evidência |
|---|---|---|
| 2.1 — Cobertura geral ≥ 80% | ✅/⚠️/❌/🚫 | <%, link CI> |
| 2.2 — Cobertura núcleo/regras ≥ 98% | ✅/⚠️/❌/🚫 | <%, link> |
| 2.3 — Testes E2E presentes em fluxos tocados | ✅/⚠️/❌/🚫 | <cenários, link> |
| 2.4 — FE web: E2E em browser real | ✅/⚠️/❌/🚫 | <ferramenta, log> |
| 2.5 — Testes cobrem caminho feliz + casos inválidos + exceções + bordas | ✅/⚠️/❌/🚫 | <amostragem> |

### Bloco 3 — Automação

| Item | Status | Evidência |
|---|---|---|
| 3.1 — Setup local automatizado (um comando) | ✅/⚠️/❌/🚫 | <comando, observação> |
| 3.2 — Pipeline CI verde no branch principal | ✅/⚠️/❌/🚫 | <últimos runs> |
| 3.3 — Deploy automático para homologação após merge | ✅/⚠️/❌/🚫 | <link, observação> |
| 3.4 — Deploy para produção automatizado | ✅/⚠️/❌/🚫 | <link, observação> |
| 3.5 — Provisionamento de ambientes via IaC | ✅/⚠️/❌/🚫 | <link, repo> |

### Bloco 4 — Funcionalidade observável

| Item | Status | Evidência |
|---|---|---|
| 4.1 — Entregável listado em `epic.md` acessível em homologação | ✅/⚠️/❌/🚫 | <URL, screenshot> |
| 4.2 — Fluxo end-to-end percorrido manualmente | ✅/⚠️/❌/🚫 | <descrição, screenshots> |
| 4.3 — Logs e métricas básicas coletados | ✅/⚠️/❌/🚫 | <link dashboard> |

### Bloco 5 — Qualidade transversal

| Item | Status | Evidência |
|---|---|---|
| 5.1 — Scanner de segurança sem alerta crítico introduzido | ✅/⚠️/❌/🚫 | <log scanner> |
| 5.2 — Migrações reversíveis e testadas | ✅/⚠️/❌/🚫 | <arquivos> |
| 5.3 — LGPD: dados pessoais novos alinhados com PO | ✅/⚠️/❌/🚫 | <PR comment / PDR> |
| 5.4 — Segredos: nenhum no código (scanner) | ✅/⚠️/❌/🚫 | <log scanner> |
| 5.5 — Logs sem PII/segredos | ✅/⚠️/❌/🚫 | <amostragem> |

### Bloco 6 — Documentação

| Item | Status | Evidência |
|---|---|---|
| 6.1 — README/documentação atualizada | ✅/⚠️/❌/🚫 | <links> |
| 6.2 — ADRs/IDRs do épico indexados em `index.json` | ✅/⚠️/❌/🚫 | <links> |
| 6.3 — "Notas do agente" preenchidas em cada estória | ✅/⚠️/❌/🚫 | <links> |
| 6.4 — Diagramas atualizados (quando aplicável) | ✅/⚠️/❌/🚫 | <links> |

---

## Fails identificados

### Bloqueantes

> Itens cujo `fail` deveria impedir o épico de ser declarado `done`. Veja `verdict-criteria.md`.

#### F-B-1 — <título curto do fail>
- **Bloco**: <bloco e item, ex: Bloco 1.3>
- **Critério esperado**: <citação curta do que era pra ser>
- **O que verifiquei**: <descrição factual>
- **Por que é bloqueante**: <conexão com critério do PO/Arquiteto>
- **Sugestão (não-vinculante)**: <ex: nova estória pequena cobrindo X, Y, Z>
- **Evidência**: ver Apêndice <A.X>

#### F-B-2 — ...
- ...

### Não-bloqueantes

> Itens cujo `fail` pode virar pendência sem impedir fechamento — decisão final do PO.

#### F-NB-1 — <título>
- **Bloco**: <ref>
- **Descrição**: <factual>
- **Sugestão**: <ex: estória de correção pequena>
- **Evidência**: ver Apêndice <A.Y>

#### F-NB-2 — ...
- ...

---

## Passes com ressalva

> Itens cumpridos mas com observação que o PO/Arquiteto pode querer considerar — não são fails, mas merecem nota.

- **<bloco e item>**: <ressalva> (Ex: "Bloco 2.1 — Cobertura geral 80.2%, atende mínimo de 80% mas no limite; sugiro reforço em próximos sprints.")
- ...

---

## Recomendação ao PO

### Sobre o épico

<Veredict + sugestão de próximo passo. Ex: "REJECTED por F-B-1; sugiro não fechar até correção. F-NB-1 e F-NB-2 podem virar estórias do próximo sprint.">

### Estórias de correção sugeridas (decisão final do PO)

- **STORY-XXX-corr** (sugerido): <título curto> — endereça F-B-1. Tamanho estimado: S.
- **STORY-YYY-corr** (sugerido): <título curto> — endereça F-NB-1. Tamanho estimado: S.
- ...

### Observações de processo (input para retrospectiva)

> Sinais não-bloqueantes que podem virar aprendizado de processo — sem culpar pessoa, foco em hábito.

- <Ex: "F-B-1 (CA sem teste cobrindo) sugere que `done-checklist.md` do Programador não pegou. Vale lembrança em próximo sprint.">
- <Ex: "Cobertura próxima ao limite mínimo — considerar meta interna de 85% para margem.">

---

## Limitações da validação

> Liste o que **não foi possível verificar**, com motivo. Honestidade > simulação.

- <Ex: "Não foi possível obter log do fornecedor externo X em sandbox; observação manual usada como evidência limitada (item 4.2 ressalva).">

---

## Apêndice A — Evidências detalhadas

### A.1 — <item / fail / ressalva>

**Contexto**: <referência ao bloco e item>

**O que verifiquei**:
- <passo 1, com comando / link / observação>
- <passo 2>
- <passo 3>

**Reprodução**:
- Commit hash em validação: `<hash>`
- Branch: `<branch>`
- Comandos usados:
  ```
  <comandos>
  ```

**Resultado observado**:
<output, screenshot referência, log com timestamp>

**Conexão com critério**:
<por que isso é pass/fail/n-a em relação ao critério do checklist>

### A.2 — ...

### A.3 — ...

---

## Apêndice B — Arquivos anexados

> Lista de evidência pesada anexada em `epics/EPIC-XXX-*/validation/evidence/`.

- `bloco-2-coverage-report.txt` — relatório de cobertura completo.
- `bloco-4-homolog-cadastro-sucesso.png` — screenshot do fluxo principal em homologação.
- `bloco-5-security-scan.log` — log do scanner de segurança.
- ...

---

## Histórico

- YYYY-MM-DD — relatório inicial submetido por validador (sessão <id>).
- <data> — <mudança / correção / complemento, se houver após inicial>.
