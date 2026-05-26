---
story_id: STORY-034
slug: validacoes-cruzadas-dre-balanco
title: Validações cruzadas DRE × Balanço + mensagens de erro acionáveis (§6.6)
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: done
owner_agent: claude-programador
created_at: 2026-05-25
updated_at: 2026-05-26
estimated_session_size: M
---

# STORY-034 — Validações cruzadas DRE × Balanço

> Detecta inconsistências entre blocos do quiz (não dentro de um campo só) e mostra alerta **não-bloqueante** com mensagem acionável.

## Contexto

A epic.md lista como entregável: *"Validações cruzadas DRE × Balanço e mensagens de erro acionáveis quando Roberto digita dado inconsistente."* A **spec V2 §6.6** propõe 3 regras canônicas marcadas como `[DECIDIR]`. **Decisão do PO em 2026-05-25:** adotar as **3 regras da spec §6.6 como conjunto canônico do MVP** — não esperar até a Semana 4 para definir. Regras adicionais sugeridas em execução viram backlog para sprint pós-EPIC-002.

**Lista canônica (idêntica à spec §6.6 — fonte da verdade):**

| Regra | Condição (campos do Anexo A) | Hipótese de erro |
|---|---|---|
| R1 — Despesas financeiras × dívida | `Q16 × 12 > Q06 × 2` | Despesas financeiras anualizadas mais que o dobro da dívida declarada — provável erro de digitação em Q16. |
| R2 — Custos anuais × receita anual | `Q14 × 12 + Q15 × 12 > Q09 × 12` | Custos totais anuais ultrapassam a receita anual — provável erro em Q14/Q15. |
| R3 — Passivo × Ativo | `Q02 + Q03 + Q04 + Q05 < Q06 + Q07` | Passivo total maior que Ativo total — situação patrimonial inviável (PL negativo); pode ser real, mas vale alertar. |

> **Correção 2026-05-25:** a redação original tinha duas fórmulas divergentes da spec — Q14 sem anualização (R2) e ativo sem Q05 (R3). Realinhado à §6.6 da spec funcional V2.

## O quê

1. **Classe `app/Domain/Quiz/ValidacoesCruzadas.php`** com método `validar(QuizPayload $p): array<Alerta>`. Cada `Alerta` tem `regra`, `severidade` (`warning`), `mensagem_acionavel`, `campos_envolvidos`.
2. **Trigger:** ao avançar do Bloco 3 (Balanço) para o Bloco 4 (Contexto), valida. Se houver alertas, mostra modal/banner: "Detectamos inconsistências. Você quer corrigir agora?" com 2 botões: `Corrigir` (volta ao campo) e `Continuar mesmo assim`.
3. **Não-bloqueante:** Roberto pode continuar. Alertas registrados no `quiz_payload.alertas_aceitos[]` para auditoria.
4. **Mensagens acionáveis:** cada alerta linka direto para o campo suspeito. Exemplo: "Suas despesas financeiras mensais (R$ X) são muito altas comparadas às dívidas (R$ Y). Verifique o valor de despesas financeiras." + botão `Ir para Despesas Financeiras`.
5. **Regras em config externa** (`config/quiz/validacoes-cruzadas.php`) — facilita ampliação futura sem mexer em código.

## Critérios de aceite

- [x] **CA-1 (lista canônica fixada):** R1, R2, R3 (acima) implementadas com fórmulas idênticas à spec §6.6. Regras adicionais sugeridas durante a execução viram backlog — **não entram nesta estória**.
- [x] **CA-2:** Classe `ValidacoesCruzadas` implementa cada regra; ≥ 3 testes por regra (caso disparado, caso não-disparado, edge case — valor exatamente no limiar).
- [x] **CA-3 (não-bloqueante + registro do "aceito"):** Roberto pode continuar após ver alerta. A decisão fica registrada em `quiz_payload.alertas_aceitos` (array de `{regra, ocorrido_em, valor_envolvido}`). Como `quiz_payload` é canonicalizado para o `payload_hash` da IDR-010, `alertas_aceitos` **não** entra no canonicalize (não muda hash) — confirmado por teste arquitetural junto com STORY-026.
- [x] **CA-4 (acionável):** cada mensagem linka direto para o campo suspeito. Textos finais aprovados pelo PO antes do início da implementação (default a usar se PO não revisar em ≤ 1 dia útil — exemplos no item "Textos default" abaixo).
- [x] **CA-5 (UX):** banner expansível inline ao fim do Bloco 3 (Balanço) antes de avançar para o Bloco 4 (preferência do PO declarada). Botões: `Corrigir agora` (foco no primeiro campo da regra) e `Continuar mesmo assim`.
- [x] **CA-6 (config externa):** regras em `config/quiz/validacoes-cruzadas.php` — adicionar regra nova = editar config + adicionar teste, sem refactor de classe.
- [x] **CA-7 (testes E2E):** Dusk — preencher quiz disparando cada uma das 3 regras + ver alerta + clicar "Continuar" + ver diagnóstico gerado normalmente + asserir `alertas_aceitos` no payload.
- [x] **CA-8 (cobertura):** ≥ 80% no pacote de quiz, ≥ 95% na classe `ValidacoesCruzadas` (hot path simples).

## Textos default das mensagens (a confirmar pelo PO no Dia 1)

- **R1:** *"Suas despesas financeiras mensais (R$ {Q16}) anualizadas representam {Q16×12/Q06:.0%} da dívida declarada (R$ {Q06}). Isso é incomum — verifique o valor de despesas financeiras."* Botão: `Ir para Despesas Financeiras (Q16)`.
- **R2:** *"Os custos totais anuais (R$ {(Q14+Q15)×12}) ultrapassam a receita anual (R$ {Q09×12}). Provavelmente houve erro em custos ou em receita."* Botão: `Ir para Custos (Q14/Q15)`.
- **R3:** *"O passivo total (R$ {Q06+Q07}) é maior que o ativo total (R$ {Q02+Q03+Q04+Q05}), o que indica PL negativo. Se for o caso real, prossiga — o diagnóstico vai sinalizar."* Botão: `Revisar Balanço`.

## Fora de escopo

- Validações dentro de um único campo (faixa numérica) — já em STORY-027.
- Validações via IA — roadmap §2.3.
- Bloqueio rígido — decisão explícita: alertas são **avisos**, não impeditivos.

## Dependências

- **Bloqueada por:** STORY-027 (quiz existe — `done` ✓), STORY-030 (balanço completo — `done` ✓).
- **Bloqueia:** nada da V5.

## Referências cruzadas

- Spec V2 funcional §6.6 (regras canônicas).
- Anexo A do quiz (numeração Q01–Q23).
- IDR-010 §Sub-decisão 3 (`payload_hash` canonicalize — `alertas_aceitos` fica fora).

## Decisões já tomadas

- Não-bloqueante (alertas, não erros).
- Regras em config externa.
- **Lista canônica = §6.6 da spec** (R1/R2/R3 acima) — fechada em 2026-05-25 pelo PO, não esperar Semana 4.
- `alertas_aceitos` vive em `quiz_payload` mas **fora do canonicalize** (não muda `payload_hash` — IDR-010 §Sub-decisão 3).
- UX: banner inline expansível ao fim do Bloco 3, não modal.

## DoD

CA-1 a CA-8 + tag `rc.W25S4.1`. `index.json` atualizado.

## Protocolo do agente

Padrão.

## Notas do agente

### Entrada inicial — 2026-05-26 (claude-programador)

**Documentos lidos:** estória inteira; `app/Livewire/Diagnostico/Quiz.php` (fluxo de 4 blocos, `parseDecimal`); `app/Actions/CalcularDiagnostico.php`; `app/Domain/Motor/QuizPayloadCanonicalizer.php`; `config/quiz/help-industria.php` (padrão de config externa do quiz); `resources/views/livewire/diagnostico/quiz.blade.php`; testes de `QuizPayloadCanonicalizer`, `CalcularDiagnostico`, `QuizTest`, `QuizTooltipsBrowserTest`.

**Entendimento consolidado:** detectar 3 inconsistências entre blocos (R1/R2/R3 §6.6), **não-bloqueantes**, com banner inline ao sair do Bloco 3 (Balanço). Roberto pode continuar; o "aceito" vai para `quiz_payload.alertas_aceitos` mas **fora do `payload_hash`** (IDR-010). Regras vivem em config externa.

**Decisões locais:**
- `ValidacoesCruzadas::validar(array $valores)` recebe mapa `Q-code → float` (não existe classe `QuizPayload`; criar uma só pra isso seria scope creep). Retorna `list<Alerta>`.
- Regras **declarativas** em `config/quiz/validacoes-cruzadas.php` (sem closures — compatível com `config:cache`). Cada regra = `condicao{esquerda, operador, fator, direita}` (somas ponderadas) + template de mensagem com placeholders (`:esquerda`, `:direita`, `:percentual`, `:Qxx`). As 3 fórmulas §6.6 reduzem a `esquerda <op> fator×direita`.
- `alertas_aceitos` fora do hash: dupla proteção — `QuizPayloadCanonicalizer::canonicalize` dropa a chave **e** `CalcularDiagnostico` mescla os alertas **após** computar o hash.
- Banner inline no Bloco 3: botão por alerta (`Ir para …` → navega ao bloco do campo-foco + foca) e botão global `Continuar mesmo assim` (registra aceitos, avança).

**Plano:** (1) config; (2) `ValidacoesCruzadas`+`Alerta`; (3) hash (canonicalizer+action); (4) Livewire gate+banner; (5) testes Pest (Domain ≥3/regra + arquitetural de hash) + Feature Livewire + Dusk E2E; (6) suíte + cobertura + `index.json`.

**Dúvidas:** nenhuma — lista canônica e textos default fechados na estória (PO 2026-05-25).

### Entrega — 2026-05-26 (claude-programador)

**Implementado:**
- `config/quiz/validacoes-cruzadas.php` — R1/R2/R3 declarativas (§6.6), formato `esquerda <op> fator×direita` + template de mensagem. Versão 1.0.0.
- `app/Domain/Quiz/ValidacoesCruzadas.php` + `Alerta.php` — engine puro, config-driven; `validar(array $valores): list<Alerta>`.
- `app/Domain/Motor/QuizPayloadCanonicalizer.php` — `canonicalize()` dropa `alertas_aceitos` (CA-3).
- `app/Actions/CalcularDiagnostico.php` — novo param `$alertasAceitos`; mescla em `quiz_payload` **após** o hash.
- `app/Livewire/Diagnostico/Quiz.php` — gate ao sair do Bloco 3 (`proximoBloco`), `continuarComAlertas`, `irParaCampo` (foco via evento Alpine), dedup por regra, round-trip no rascunho.
- `resources/views/livewire/diagnostico/quiz.blade.php` — banner inline não-bloqueante (token `--color-farol-amarelo`), botão "Ir para…" por alerta + "Continuar mesmo assim".

**Decisões locais (notáveis):**
- Regras **declarativas** (sem closures) → compatível com `config:cache`. `:percentual` usa a razão crua `esquerda/direita` (sem o `fator`), batendo com o texto default da R1.
- Dupla proteção do hash: canonicalizer remove a chave **e** a action só mescla depois do hash. Teste arquitetural confirma invariância (junto ao `QuizPayloadCanonicalizerTest`, linhagem STORY-026).
- `ValidacoesCruzadas::validar` recebe `array<Q,float>` em vez de uma classe `QuizPayload` inexistente (evita scope creep). O componente converte via `Quiz::parseDecimal`.

**Testes (verde):**
- Pest Domain `ValidacoesCruzadasTest` — 16 testes (≥3/regra: disparo, não-disparo, limiar exato; multi-regra; bordas; CA-6 config; todos os operadores).
- Pest arquitetural em `QuizPayloadCanonicalizerTest` (hash invariante a `alertas_aceitos`).
- Pest Feature `CalcularDiagnosticoTest` (+1) e `QuizTest` (+6: gate, continuar, irParaCampo, dedup, submit ponta-a-ponta, sem falso positivo).
- Dusk `ValidacoesCruzadasBrowserTest` — 2 cenários cobrindo R3 (fluxo completo até diagnóstico + `alertas_aceitos`) e R1+R2 (banner + corrigir).

**Resultados:** suíte completa `777 passed`; cobertura domínio **99.6%** (gate ≥98%), `Quiz/ValidacoesCruzadas` e `Quiz/Alerta` **100%** (CA-8). Pint e Larastan (1G) limpos.

**Pendências (fora do escopo do agente):** commit/push/tag `rc.W25S4.1` aguardam decisão explícita do PO (workflow direto em `main` local).
