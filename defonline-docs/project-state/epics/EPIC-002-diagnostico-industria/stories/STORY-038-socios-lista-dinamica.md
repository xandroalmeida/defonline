---
story_id: STORY-038
slug: socios-lista-dinamica
title: Sócios como lista dinâmica (1+) — add/edit/remove
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-06-02
updated_at: 2026-06-02
estimated_session_size: M
related_devolutiva: "WhatsApp Edvan #1 — 'poderíamos tornar obrigatório só o primeiro campo... no teste que fiz, repeti meu cpf em todos os campos'"
---

# STORY-038 — Sócios como lista dinâmica (1+)

> Substitui a Q23 fixa de 3 CPFs (Q23.1/Q23.2/Q23.3 obrigatórios) por uma lista dinâmica com **1 sócio obrigatório** e quantos adicionais o usuário quiser.

## Contexto

Na devolutiva da EBC (Edvan, 2026-05-27), o analista financeiro testou o quiz e relatou que muitas empresas LTDA do nosso público-alvo (MPEs/EPPs Indústria) são unipessoais ou têm um único sócio relevante. O quiz atual (STORY-027) exige 3 CPFs válidos e o Edvan foi forçado a **repetir o próprio CPF nos três campos** para conseguir submeter — comportamento que invalida o dado capturado. O PO refinou a proposta: **lista dinâmica** com 1 sócio obrigatório, demais opcionais e adicionáveis livremente.

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- Devolutiva integral: `epics/EPIC-002-diagnostico-industria/devolutivas/2026-05-27-EBC/`
- Documentos canônicos a ler ANTES de codificar:
  - `defonline-docs/especificacao/V2/anexos/anexo-A-quiz-industria.md` (Q23)
  - `decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md` (canonicalização `payload_hash`)
  - `defonline-docs/especificacao/V2/design-system.md` (componentes de input, padrão mobile)

## O quê

Implementar uma lista dinâmica de sócios no Bloco 4 do quiz Indústria, com mínimo de 1 sócio obrigatório e cap interno de 10, substituindo os campos fixos `cpf_socio_1/2/3` por um array `socios[]` no `quiz_payload`.

## Por quê

Destrava uso real do MVP por empresas unipessoais ou de poucos sócios (perfil dominante do Roberto). Sem isso, o quiz força dado inválido (CPF duplicado) ou impede submissão — bloqueando a entrega de valor (diagnóstico).

## Critérios de aceite

- [ ] **CA-1 (submeter com 1):** Dado quiz preenchido com exatamente 1 CPF válido no Bloco 4, quando o usuário clica `Calcular diagnóstico`, então o submit é aceito e o diagnóstico é gerado.
- [ ] **CA-2 (adicionar):** Dada lista com 1 sócio, quando o usuário clica `+ Adicionar sócio`, então uma nova linha vazia aparece e recebe foco automático para o input do CPF.
- [ ] **CA-3 (remover):** Dada lista com ≥2 sócios, quando o usuário clica no ícone de remover de qualquer linha, então a linha some e o restante é renumerado. **Quando há 1 sócio na lista, o botão de remover está desabilitado** (não é possível ficar com 0 sócios).
- [ ] **CA-4 (validação por linha):** Dado um CPF inválido digitado em qualquer linha, então a mensagem `CPF inválido — confira os dígitos.` aparece apenas naquela linha; outras linhas ficam intocadas.
- [ ] **CA-5 (duplicidade):** Dada uma lista onde 2 ou mais linhas têm o **mesmo CPF válido**, quando o usuário tenta submeter, então o submit é impedido com mensagem `CPF duplicado na lista — cada sócio só pode aparecer uma vez.` apontando para a 2ª ocorrência em diante.
- [ ] **CA-6 (cap interno):** Dada lista com 10 sócios, então o botão `+ Adicionar sócio` desabilita e mostra mensagem `Limite de 10 sócios por empresa.` (cap de sanidade, não regra de negócio externa — usuário real não esbarra).
- [ ] **CA-7 (linhas vazias descartadas):** Dadas linhas vazias entre preenchidas, quando o usuário submete, então as linhas vazias são silenciosamente descartadas antes da persistência (não geram erro); o `quiz_payload.socios[]` contém apenas os CPFs preenchidos.
- [ ] **CA-8 (rascunho persiste):** Dado um rascunho com lista de N sócios, quando o usuário fecha e reabre o quiz, então a lista volta com os mesmos N sócios na mesma ordem.
- [ ] **CA-9 (canonicalize):** O `payload_hash` da IDR-010 considera `socios[]` **ordenado alfabeticamente pelo CPF** (independente da ordem de digitação) — testado com 3 permutações da mesma lista produzindo o mesmo hash.
- [ ] **CA-10 (cobertura):** ≥ 80% no pacote do quiz; ≥ 95% na classe `ValidaSocios` (hot path); 2 testes Dusk: (a) submeter com 1 sócio; (b) adicionar 3, remover 1, submeter.
- [ ] **CA-11 (mobile):** Layout em stack vertical em ≤ 768px (linhas empilhadas, lixeira à direita, botão "Adicionar sócio" largura total abaixo); auditado em Chrome DevTools 375×667 (iPhone SE) e 390×844 (iPhone 14).

## Fora de escopo

- Cargo/percentual de participação dos sócios (Anexo A só pede CPF).
- Enriquecimento via Receita Federal por CPF (não está na spec; STORY-018 é CNPJ).
- Migration de conversão de rascunhos antigos `cpf_socio_1/2/3` → `socios[]`: **descartar rascunhos existentes em homol** (TTL 90d da IDR-010 §6.4 já é curto; em homol o impacto é nulo). Documentar na release note.
- Diagnósticos legados em `1.0.0`/`1.1.0`/`1.2.0`: snapshot imutável (IDR-010) — não alterar.

## Padrões de qualidade exigidos

Esta estória segue os padrões em `defonline-docs/skills/po/references/quality-standards.md`. Resumo aplicável:

- **Cobertura de testes unitários:** ≥ 80% no código novo; ≥ 95% na classe `ValidaSocios` (regra de negócio nova).
- **Testes E2E (Dusk):** dois cenários (CA-10).
- **Acessibilidade:** botões de remover com `aria-label="Remover sócio N"`; botão `+ Adicionar` com `aria-label`; erros com `aria-describedby`.
- **Sem código não testado.**

## Dependências

- **Bloqueada por:** nada.
- **Bloqueia:** nada da sprint atual; pode rodar 1º.
- **Pré-requisitos de ambiente:** homol operante; rascunhos em `quiz_rascunhos` em homol serão limpos (PO autoriza explicitamente).

## Decisões já tomadas (não as reabra)

- **Ordem de canonicalização do `socios[]`:** alfabética pelo CPF (PO + Arquiteto, 2026-06-02 — registrar IDR se houver consequência futura).
- **Mínimo:** 1 sócio. **Máximo:** 10 (cap interno de sanidade).
- **Schema:** `quiz_payload.socios` é `array<string CPF mascarado>`. Não usa objeto por enquanto (sem cargo/percentual).
- **Rascunhos antigos:** descartar em homol; release note. Sem migration de conversão.
- **IDR-010** continua valendo — `payload_hash` muda quando o esquema do payload muda; bump de `motor_version` **não é necessário** porque CPF não entra em cálculo.

## Liberdade técnica do agente

Você (Programador) decide:
- Componente Livewire (filho `<livewire:quiz.socios-list>` ou estado no componente pai com array reativo).
- Como tratar foco automático na nova linha (`wire:after` / JS mínimo).
- Estrutura do canonicalizer — pode ser método estático em `QuizPayload::canonicalize()` ou helper isolado.
- Layout exato da lista (alinhamento, espaçamento, ícone da lixeira — respeitando tokens v1 do design system).
- Estratégia de teste E2E (Dusk) — pode usar fixtures ou seeder dedicado.

Você (Programador) NÃO decide:
- Mínimo/máximo da lista (PO já decidiu: 1+ / 10).
- Ordem de canonicalização (PO já decidiu: alfabética pelo CPF).
- Bloqueante vs alerta para CPF duplicado (PO já decidiu: bloqueante).

## Definição de Pronto (DoD)

- [ ] Todos os critérios de aceite passam.
- [ ] Testes unitários ≥ 80% pacote / ≥ 95% `ValidaSocios`.
- [ ] 2 Dusk verdes em homol.
- [ ] CI verde no PR.
- [ ] Deploy em homol verificado (tag rc.W26.X).
- [ ] Release note menciona "rascunhos de quiz pré-W26 foram descartados".
- [ ] `index.json` atualizado: `status: done`.
- [ ] Seção "Notas do agente" preenchida.

## Notas do agente (preenchido durante/após execução)

### Decisões tomadas
- 

### Descobertas
- 

### Bloqueios encontrados
- 

### IDRs criados
- 

### Cobertura final
- Unitários: 
- E2E: 

### Links de evidência
- PR: 
- Pipeline: 
- Deploy de homologação: 
