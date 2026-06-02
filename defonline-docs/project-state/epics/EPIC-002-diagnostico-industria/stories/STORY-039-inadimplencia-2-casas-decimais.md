---
story_id: STORY-039
slug: inadimplencia-2-casas-decimais
title: Validação do campo Inadimplência aceita 2 casas decimais (0,00 ≤ x ≤ 100,00)
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: bugfix
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-06-02
updated_at: 2026-06-02
estimated_session_size: XS
related_devolutiva: "WhatsApp Edvan #2 — 'inadimplência exata, em balanço, é 3,46%... como o padrão é a plataforma considerar 2 casas decimais, neste caso não aceitou, mostrando alerta de que só admite informação entre ZERO e CEM'"
---

# STORY-039 — Inadimplência aceita 2 casas decimais

> O campo `Inadimplência` (Q22 do Anexo A — Bloco 4) recusa valores como `3,46%` com mensagem "só admite informação entre 0 e 100". Provável bug na validação que trata o input antes da máscara percentual ser aplicada.

## Contexto

Devolutiva do Edvan (mensagem WhatsApp 2026-05-27): testou com uma indústria real cuja inadimplência declarada em balanço é **3,46%** e o campo recusou o valor. A máscara visual mostra `0,00 %` (suporta 2 decimais), mas a validação compara `valor > 100` antes de tratar a vírgula decimal — provável conversão `(int)"3,46"` virando algo inesperado ou `(float)"3,46"` retornando `3.0` em locale errado.

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- Spec: Anexo A Q22 (Bloco 4 — Inadimplência, em %).
- Print de evidência: foto enviada pelo Edvan em 2026-05-27 (campo "Inadimplência" com `0,00 %` e nota "Percentual médio de atraso dos clientes").

## O quê

Corrigir a validação do campo `Inadimplência` para aceitar qualquer valor decimal entre `0,00` e `100,00` (inclusive) com até 2 casas decimais, alinhando ao padrão BR (vírgula como separador).

## Por quê

Sem isso, o quiz rejeita dado real (qualquer % com vírgula) — bloqueia uso por analistas e empresas reais.

## Critérios de aceite

- [ ] **CA-1 (valor válido com vírgula):** Dado o input `3,46`, quando o usuário sai do campo (blur), então não há mensagem de erro e o valor persiste no rascunho como `3.46` (canônico) ou `"3,46"` (string mascarada) — consistente com os outros campos % do quiz.
- [ ] **CA-2 (limites inclusivos):** `0,00` e `100,00` são aceitos.
- [ ] **CA-3 (acima de 100):** `100,01` é rejeitado com mensagem `Inadimplência deve ser entre 0,00% e 100,00%.`
- [ ] **CA-4 (negativo):** `-1` ou `-0,01` é rejeitado com a mesma mensagem.
- [ ] **CA-5 (3 ou mais decimais):** `3,461` é rejeitado ou truncado para `3,46` (consistente com a máscara) — Programador decide qual UX é melhor (truncar silenciosamente é amigável; rejeitar é mais purista). Default: **truncar para 2 decimais** (alinhado com a máscara).
- [ ] **CA-6 (não-numérico):** `abc` é rejeitado com mensagem genérica.
- [ ] **CA-7 (cobertura):** ≥ 3 testes unitários cobrindo CA-1..CA-6 (parametrized se possível); 1 Dusk submetendo quiz com `3,46` e vendo diagnóstico.
- [ ] **CA-8 (regressão):** nenhum outro campo % do quiz quebra (Margem Bruta, Margem EBITDA, etc — auditar lista).

## Fora de escopo

- Mudar a máscara visual (já está em 2 decimais).
- Mudar locale do app (BR fixo).
- Adicionar texto explicativo extra no campo.

## Padrões de qualidade exigidos

- **Cobertura:** ≥ 95% no validator/rule alterado.
- **Sem regressão** em outros campos %.

## Dependências

- **Bloqueada por:** nada.
- **Bloqueia:** nada.

## Decisões já tomadas (não as reabra)

- **Range:** `[0,00 ; 100,00]` inclusivo.
- **3+ decimais:** **truncar** silenciosamente para 2 (default; Programador pode rejeitar se enxergar consequência — registrar IDR se desviar).

## Liberdade técnica do agente

Você decide:
- Onde mora o validator (FormRequest / Livewire rule / Domain).
- Parser do valor mascarado (regex / NumberFormatter / função própria).
- Se introduz uma rule reutilizável `PercentualBr` para uso em outros campos %.

## Definição de Pronto

- [ ] CA-1..CA-8 passam.
- [ ] Testes unitários e 1 Dusk verdes.
- [ ] CI verde.
- [ ] Deploy em homol verificado.
- [ ] `index.json` atualizado.
- [ ] "Notas do agente" preenchidas.

## Notas do agente

### Decisões tomadas
- 

### Descobertas
- 

### Bloqueios encontrados
- 

### Cobertura final
- 

### Links de evidência
- 
