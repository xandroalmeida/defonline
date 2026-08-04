---
story_id: STORY-039
slug: inadimplencia-2-casas-decimais
title: Validação do campo Inadimplência aceita 2 casas decimais (0,00 ≤ x ≤ 100,00)
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: bugfix
target_role: programador
requires_design: false
status: done
owner_agent: programador (claude-opus-4-8)
created_at: 2026-06-02
updated_at: 2026-08-04
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

- [x] **CA-1 (valor válido com vírgula):** Dado o input `3,46`, quando o usuário sai do campo (blur), então não há mensagem de erro e o valor persiste no rascunho como `3.46` (canônico) ou `"3,46"` (string mascarada) — consistente com os outros campos % do quiz.
- [x] **CA-2 (limites inclusivos):** `0,00` e `100,00` são aceitos.
- [x] **CA-3 (acima de 100):** `100,01` é rejeitado com mensagem `Inadimplência deve ser entre 0,00% e 100,00%.`
- [x] **CA-4 (negativo):** `-1` ou `-0,01` é rejeitado com a mesma mensagem.
- [x] **CA-5 (3 ou mais decimais):** `3,461` é rejeitado ou truncado para `3,46` (consistente com a máscara) — Programador decide qual UX é melhor (truncar silenciosamente é amigável; rejeitar é mais purista). Default: **truncar para 2 decimais** (alinhado com a máscara). → **truncado** (não arredondado) em `pctCanonico`; `3,469 → 3.46`.
- [x] **CA-6 (não-numérico):** `abc` é rejeitado com mensagem genérica.
- [x] **CA-7 (cobertura):** ≥ 3 testes unitários cobrindo CA-1..CA-6 (parametrized se possível); 1 Dusk submetendo quiz com `3,46` e vendo diagnóstico.
- [x] **CA-8 (regressão):** nenhum outro campo % do quiz quebra (Margem Bruta, Margem EBITDA, etc — auditar lista). → **Q13 é o único campo `%` do quiz** (1 só uso de `regraPercentual`/`maskPct`); R$ e dias inalterados. Dusk de regressão verde.

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

- [x] CA-1..CA-8 passam.
- [x] Testes unitários e 1 Dusk verdes.
- [x] CI verde. → run [30941074737](https://github.com/xandroalmeida/defonline/actions/runs/30941074737) em `main@5d7d192` (2026-08-04): Pest com cobertura ≥80%, Dusk E2E, Larastan, Pint, `composer audit`, Gitleaks e Trivy verdes.
- [x] Deploy em homol verificado. → `v0.11.0-rc.1` no ar (`/health` reporta a versão); CA-1/CA-2/CA-3 reverificados no navegador contra a URL pública. Ver "Verificação em homologação".
- [x] `index.json` atualizado.
- [x] "Notas do agente" preenchidas.

## Notas do agente

### Decisões tomadas
- **A causa-raiz NÃO era o servidor** — era a máscara JS estilo "centavos". Decisão (com aprovação do PO em chat 2026-06-02): trocar `$maskPct` por **máscara decimal natural BR** e registrar **[[IDR-011]]**. A estória marcava a máscara como fora de escopo; o PO autorizou incluí-la ao ver a evidência.
- **Mensagem** alinhada à CA-3/CA-4: `Inadimplência deve ser entre 0,00% e 100,00%.`
- **CA-5: truncar (não arredondar)** — `pctCanonico()` faz `floor(round(x*100,4))/100` → `3,469 → 3.46`. `round(_,4)` neutraliza ruído de float.
- **Ponto digitado vira vírgula** na máscara `%` (percentual não tem separador de milhar) → `3.46` colado também funciona.

### Descobertas
- `parseDecimal("3,46")` já devolvia `3.46` desde a STORY-027; `regraPercentual` (único validador `%`) já aceitava `3,46`/`0,00`/`100,00`. Provado por teste Livewire ponta-a-ponta **antes** de qualquer mudança.
- A máscara antiga transformava `100 → 1,00` e `3,4 → 34`; re-digitar para "corrigir" produzia valores >100 (ex.: `343,46`) → daí o alerta "entre 0 e 100" que o Edvan viu.
- **Q13 é o único campo `%` do quiz** — não há Margem Bruta/EBITDA como input `%` no quiz (esses são calculados pelo motor, não digitados). CA-8 sem outros campos para quebrar.

### Bloqueios encontrados
- Nenhum. (PHPStan exige `--memory-limit=512M` no container — default 128M estoura; não relacionado à estória.)

### Cobertura final
- Feature parametrizada em `QuizTest.php` (STORY-039): CA-1..CA-6 + truncamento CA-5.
- Dusk: `tests/Browser/InadimplenciaDecimalBrowserTest.php` (digita `3,46`, submete, `quiz_payload['Q13']==='3.46'`).
- `vendor/bin/pint --test` e `phpstan` (Quiz.php) limpos. Suíte Pest completa: **823 passed**. Dusk quiz (SociosLista, ValidacoesCruzadas, QuizTooltips + novo): verdes.

### Links de evidência
- Código: `app/resources/views/livewire/diagnostico/quiz.blade.php` (`$maskPct`), `app/app/Livewire/Diagnostico/Quiz.php` (`regraPercentual`, `pctCanonico`).
- Decisão: `decisions/idr/IDR-011-mascara-percentual-decimal-natural.md`.

### Verificação em homologação — 2026-08-04 (release `v0.11.0-rc.1`)

Fechamento do Bloco 1 da SPRINT-2026-W32. `main` empurrada (`5d7d192`), CI verde, tag
`v0.11.0-rc.1` criada e empurrada, `release-homolog.yml`
([run 30941264096](https://github.com/xandroalmeida/defonline/actions/runs/30941264096)) verde nas 4
etapas (build → deploy → smoke → notify). `/health` e `/ready` de
`https://defonline.xandrix.com.br` reportam `v0.11.0-rc.1`.

Os CAs pendentes de deploy foram reverificados **em navegador real contra a URL pública**, pela UI,
sem factory e sem `loginAs` (conta criada pelo fluxo de cadastro; email confirmado por URL assinada
gerada no servidor — nenhuma escrita direta no banco de homologação):

- **CA-3 ✅** — `100,01` no Q13 é recusado com a mensagem canônica
  `Inadimplência deve ser entre 0,00% e 100,00%.` e o quiz **não** avança de bloco.
- **CA-2 ✅** — `100,00` é aceito: o campo mantém `100,00` e o bloco avança.
- **CA-1 ✅** — `3,46` é aceito, o campo mantém `3,46` formatado, sem mensagem de erro, e o
  diagnóstico é gerado (relatório renderizado com os 15 indicadores).

**Observação de UX registrada (não é regressão, não bloqueia o CA):** depois de um valor recusado,
a mensagem de erro do Q13 **permanece na tela até a próxima tentativa de avançar** — o componente
não tem hook `updated` que limpe o error bag, então corrigir o valor não apaga o aviso na hora.
Vale em qualquer ambiente (é do componente, não do deploy) e o CA-1 não exige limpeza ao vivo. Fica
como candidato a polimento, para o PO decidir.
