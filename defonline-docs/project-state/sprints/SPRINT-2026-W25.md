---
sprint_id: SPRINT-2026-W25
wave: WAVE-2026-01
status: planned
start_date: 2026-05-25
end_date: 2026-07-03
goal: "Entregar o EPIC-002 (Diagnóstico Econômico-Financeiro para Indústria) pronto-para-beta sob ótica técnica — quiz com os 23 campos do Anexo A aplicáveis a Indústria, motor com os 14 indicadores (incluindo NCG absoluto informativo sem farol), Resumo Executivo determinístico §4.7.1, recomendações da matriz DEZ/2025 filtradas por Indústria, tooltips/box de explicação no quiz, validações cruzadas DRE×Balanço, persistência idempotente do diagnóstico, eventos analíticos (quiz_iniciado / diagnostico_concluido), validação externa do motor aprovada (NRF §9.3) e validação interna independente aprovada. p95 do relatório ≤ 3s. Cobertura do motor ≥ 98%. Recrutamento de Robertos do beta, coleta de NPS e onboarding comercial ficam fora desta sprint — planejados com comercial/implantação em conversa separada."
goal_achieved: null
---

# SPRINT-2026-W25

## Decisão de formato: sprint longa (5 semanas)

Esta sprint foge da cadência semanal das três anteriores (W22/W23/W24) **por decisão explícita do PO em 2026-05-25**. O EPIC-002 é estimado em 5–6 semanas (current-wave.md) e a própria `epic.md` registra o sinal de quebra "estimativa do épico exceder 6 semanas" — o que coloca o épico **no limite** do que cabe num único ciclo. Em vez de fatiar o épico em 5 sprints semanais (alternativa preferida pelo PO em condições normais), a sessão de planejamento escolheu **uma sprint longa única** cobrindo todas as fatias verticais do épico, com **5 checkpoints semanais internos** que preservam o feedback curto do ciclo anterior.

**Trade-off aceito conscientemente:**

- ❌ Perdemos previsibilidade fina (não há "fechamento de sprint" semanal observável pelos stakeholders).
- ❌ Risco de inflar escopo é maior — sem deadline curto pressionando, é mais fácil decisão de quebra deslizar.
- ✅ Ganhamos coesão narrativa do épico — um único relatório de fechamento descreve todo o ciclo cadastro→quiz→relatório.
- ✅ Reduzimos overhead de abertura/fechamento × 5 (≈ 4 horas de PO economizadas no agregado).
- ✅ Permitimos planejamento integrado da validação externa (NRF §9.3) sem ter que coordenar entre sprints.

**Mecanismos compensatórios** (formalizados na Ordem de execução abaixo):

1. **5 checkpoints semanais** no fim de cada sexta — PO escreve um mini-status (≤ 300 palavras) atualizando: estórias `done` desde o último checkpoint, riscos materializados, decisão de continuar/replanejar/abortar (gate kill-switch explícito).
2. **Goal incremental por checkpoint** (não só goal único de 5 semanas) — checkpoint 1 entrega valor mínimo já observável (relatório com 7 indicadores), e cada checkpoint subsequente é "demonstrável" em homologação.
3. **Gate kill-switch após o Checkpoint 2** (final da 2ª semana) — se motor de 14 indicadores não estiver `done`, PO abre PDR de replanejamento (fatiar em 2 ondas: V1+V2 nesta sprint, V3+V4+V5 em sprint seguinte).

> **Nota sobre o nome da sprint:** SPRINT-2026-W25 mantém a convenção sequencial herdada (não estritamente ISO). Convenção formalizada quando o time consolidar sprints de duração variável como padrão (ver retro da W24 — também levantou tema).

## Objetivo do sprint

Ao fim de 2026-07-03, em homologação (`https://defonline.xandrix.com.br`):

- **Roberto autenticado** (conta de teste do time interno), com uma Empresa Analisada já cadastrada (entregue por EPIC-001), seleciona a empresa, abre o item de menu "Diagnósticos" (que neste sprint deixa de ser disabled), inicia um novo diagnóstico e responde **o quiz com os 23 campos do Anexo A** aplicáveis a Indústria (rascunho persistido entre sessões).
- Submete o quiz e recebe, em **≤ 3 segundos (p95)**, o **relatório web com os 14 indicadores**, semáforo verde/amarelo/vermelho por indicador, **NCG absoluto exibido como indicador informativo sem farol** (mensagem em 3 faixas semânticas), **Resumo Executivo gerado pelo algoritmo determinístico §4.7.1**, **recomendações textuais por indicador** filtradas da matriz DEZ/2025 (Anexo F) coluna Indústria, e **glossário inline** consumindo Anexo I.
- **Tooltips/box de explicação** disponíveis por campo no quiz para reduzir erro de entrada (§6.8 e Anexo A §A.6).
- **Validações cruzadas DRE × Balanço** ativas com mensagens de erro **acionáveis** (não bloqueantes, conforme §6.6) quando dados inconsistentes são digitados.
- **Eventos `quiz_iniciado` e `diagnostico_concluido`** emitidos com `empresa_id`, `usuario_id`, `motor_version`, `matrix_version`, `timestamp` — feeding the north star (D2 Ativação).
- **Diagnóstico persistido** com chave de versionamento (`motor_version` + `matrix_version`) garantindo **idempotência** — mesmo input + mesma versão = mesmo output, pré-requisito para o EPIC-003 (histórico).
- **Validação externa do motor APROVADA** por especialista financeiro (NRF §9.3) com parecer registrado em `epics/EPIC-002-.../validation/external-review.md` — gate técnico de qualidade exigido pela spec **antes** do produto ser liberado para uso por usuário real.
- **Validação independente interna do EPIC-002** com veredito `approved` em `epics/EPIC-002-.../validation/report.md`.
- **Pacote de handoff para comercial/implantação** pronto: link de homologação, instruções de criação de contas de teste, smoke E2E documentado, evidências de p95 ≤ 3s, parecer externo do motor, lista de feature flags ativas, contato de suporte técnico para o beta.
- **EPIC-002 promovido para `done`** no `index.json` sob a ótica técnica (validação interna + externa aprovadas + handoff entregue). O critério **"≥ 10 Robertos do beta com diagnóstico concluído + NPS ≥ 30"** registrado na `epic.md` permanece como meta de validação de hipótese da WAVE-2026-01, executada por comercial/implantação após o handoff — esse status entra nos reports da onda, não no fechamento desta sprint.

## Estórias incluídas

São **12 estórias** organizadas em **5 fatias verticais** (V1→V5), seguindo o princípio explícito da `epic.md`: *"dividir verticalmente — primeira estória entrega quiz + motor com ~7 indicadores essenciais + relatório minimalista; estórias seguintes completam para 14 indicadores, adicionam Resumo Executivo, refinam recomendações da matriz DEZ/2025, adicionam tooltips e validações cruzadas."*

### Fatia V1 — Vertical mínima de valor (Semana 1)

Goal incremental: ao fim do Checkpoint 1, Roberto em homologação responde um quiz reduzido e vê um relatório com 7 indicadores essenciais + semáforo + glossário minimalista. Sem matriz, sem Resumo Executivo, sem tooltips — o caminho feliz ponta-a-ponta funciona.

| ID | Título | Épico | Tamanho | Status | Bloqueada por |
|---|---|---|---|---|---|
| STORY-026 | SPIKE/ADR — Estratégia de versionamento do motor + persistência idempotente do diagnóstico | EPIC-002 | S-M | draft | — |
| STORY-027 | Quiz de Indústria — formulário com 23 campos do Anexo A (validações de tipo, máscaras R$/%/dias, rascunho persistido) | EPIC-002 | L | draft | STORY-026 (decisão de persistência) |
| STORY-028 | Motor de cálculo — 7 indicadores essenciais (Margem Bruta, Margem Líquida, Dívida Líquida/EBITDA, NCG/Vendas, PMR, PMC, Ciclo Financeiro) + NCG absoluto informativo + semáforo Indústria | EPIC-002 | L | draft | STORY-026 |
| STORY-029 | Relatório web minimalista — tabela dos 7 indicadores + semáforo + glossário inline + roteamento `/diagnosticos/{id}` dentro do app shell | EPIC-002 | M | draft | STORY-028, EPIC-004 |

**Checkpoint 1 — Sexta 2026-05-29:** caminho feliz vivo em homologação. Demo gravada em vídeo (PO faz screencast). Critério kill-switch: se STORY-027 e STORY-028 não estiverem `done`, PO abre PDR de replanejamento na segunda 2026-06-01.

### Fatia V2 — Completar 14 indicadores e Resumo Executivo (Semana 2)

Goal incremental: ao fim do Checkpoint 2, o relatório está **completo em indicadores** (14) e tem o **Resumo Executivo** no topo — ainda sem matriz de recomendações DEZ/2025, sem tooltips, sem validações cruzadas.

| ID | Título | Épico | Tamanho | Status | Bloqueada por |
|---|---|---|---|---|---|
| STORY-030 | Motor — completar 7 indicadores restantes (Margem EBITDA, Despesas Financeiras/EBITDA, Fontes PC/PL, Giro do Ativo, PME, Inadimplência, Ciclo Operacional) + ajuste de farol por indicador para setor Indústria conforme spec §4.5 | EPIC-002 | L | draft | STORY-028 |
| STORY-031 | Resumo Executivo — implementar algoritmo determinístico §4.7.1 (contagem proporcional + veredito por limiares + até 2 negativos / 1 positivo + truncamento ~80 chars + mensagem fixa quando ≥70% indicadores indisponíveis) | EPIC-002 | M | draft | STORY-030 |

**Checkpoint 2 — Sexta 2026-06-05 (GATE KILL-SWITCH):** 14 indicadores + Resumo Executivo vivos em homologação. Se motor ≠ `done`, PO **abre PDR de replanejamento** — opções: (a) fatiar sprint em duas (V1+V2 aqui, V3+V4+V5 em SPRINT-2026-W30), (b) cortar fatia V3 (matriz DEZ/2025) e aceitar relatório sem recomendações texto-livre para esta sprint, (c) reforçar time (mais Programador) e seguir.

### Fatia V3 — Camada qualitativa: matriz DEZ/2025 + tooltips (Semana 3)

Goal incremental: ao fim do Checkpoint 3, Roberto recebe **mensagens textuais por indicador** vindas da matriz DEZ/2025 (não só semáforo) e tem **box de explicação** por campo no quiz reduzindo erro de entrada.

| ID | Título | Épico | Tamanho | Status | Bloqueada por |
|---|---|---|---|---|---|
| STORY-032 | Matriz DEZ/2025 — recomendações por indicador × farol filtradas para Indústria (Anexo F), com versionamento `matrix_version` no payload do diagnóstico + fallback gracioso quando faltar texto | EPIC-002 | M | draft | STORY-031 |
| STORY-033 | Tooltips/box de explicação por indicador no quiz (§6.8 e Anexo A §A.6) — conteúdo curto por campo (~50 palavras), redução de erro de entrada | EPIC-002 | S | draft | STORY-027 |

**Checkpoint 3 — Sexta 2026-06-12:** relatório qualitativo completo em homologação. Início do trabalho de validação externa em paralelo (Validador externo recebe acesso à homol).

### Fatia V4 — Robustez: validações cruzadas + instrumentação (Semana 4)

Goal incremental: ao fim do Checkpoint 4, o produto está **pronto para validação externa formal e abertura de beta** — dados ruins do Roberto disparam alerta acionável, eventos analíticos rodam, p95 do relatório ≤ 3s confirmado.

| ID | Título | Épico | Tamanho | Status | Bloqueada por |
|---|---|---|---|---|---|
| STORY-034 | Validações cruzadas DRE × Balanço + mensagens de erro acionáveis (§6.6 — alerta não-bloqueante: Q16×12 > Q06×2; custos anuais > vendas; passivo > ativo; outras regras a fechar com PO no Dia 1 da semana 4) | EPIC-002 | M | draft | STORY-027 |
| STORY-035 | Eventos analíticos — emitir `quiz_iniciado` e `diagnostico_concluido` com `empresa_id`, `usuario_id`, `motor_version`, `matrix_version`, `timestamp`; validar captura no pipeline definido por ADR de captura de eventos (EPIC-000) | EPIC-002 | S | draft | STORY-031 |

**Checkpoint 4 — Sexta 2026-06-19:** produto pronto para validação externa formal. PO confirma com Validador externo a janela da Semana 5 e roda o **dry-run do pacote de handoff** com 1 representante de implantação para coletar lacunas antes do fechamento.

### Fatia V5 — Validação externa + handoff técnico (Semana 5)

Goal incremental: ao fim do Checkpoint 5, o EPIC-002 está `done` **sob ótica técnica** — validação externa do motor `approved`, validação independente interna `approved`, e pacote de handoff entregue para comercial/implantação rodar o beta.

| ID | Título | Épico | Tamanho | Status | Bloqueada por |
|---|---|---|---|---|---|
| STORY-036 | Validação externa do motor (NRF §9.3) — especialista financeiro contratado revisa ≥ 10 casos canônicos por fórmula, parecer escrito em `validation/external-review.md` com veredito `approved` / `approved_with_pending` / `blocked` | EPIC-002 | M-L (não código) | draft | STORY-030 (motor completo) |
| STORY-037 | Validação final EPIC-002 (interna) + smoke E2E em homologação + pacote de handoff para comercial/implantação (link homol, instruções de criação de conta de teste, evidências de p95, parecer externo, feature flags ativas, contato de suporte) | EPIC-002 | M | draft | todas anteriores |

**Checkpoint 5 — Sexta 2026-07-03 (FECHAMENTO):** EPIC-002 `done` sob ótica técnica. Pacote de handoff entregue. O time de comercial/implantação assume daqui — recrutamento de Robertos, convites, coleta de NPS e operação do beta de 60 dias rodam fora do escopo desta sprint.

**Total estimado:** 1 spike S-M + 3 L + 4 M + 1 S + 1 M-L (não-código) + 1 M = ~38–46h efetivas de implementação + ~10h de validação externa + ~6h de validação interna ≈ **~55–62h efetivas em 25 dias úteis.** Velocidade implícita: ~2.4h/dia útil, com folga generosa para retrabalho, débitos imprevistos e o overhead inerente a um épico denso.

**Comparação com velocidade histórica:** W22 entregou 8 estórias S/M em ~5 dias úteis (~1.6 estórias/dia). 12 estórias em 25 dias úteis = 0.48 estórias/dia — folga de 3.3× para absorver complexidade técnica do motor e validações externas que não cabem na velocidade naïve.

## Fora de escopo desta sprint (handoff para comercial/implantação)

Esta sprint **não inclui** as atividades abaixo, que são responsabilidade do time de comercial/implantação e serão planejadas em conversa separada após o handoff técnico. O EPIC-002 fecha como `done` pela ótica técnica quando o pacote de handoff (STORY-037) é entregue; a meta de "≥ 10 Robertos + NPS ≥ 30" da `epic.md` permanece viva como objetivo da WAVE-2026-01 e aparece nos status reports da onda.

- **Recrutamento dos Robertos do beta fechado.** Lista de candidatos, qualificação, abordagem, agendamento. Comercial.
- **Operação do convite e da relação durante o beta.** Envio de convites, acompanhamento de onboarding, suporte humano ao Roberto durante os primeiros usos. Implantação.
- **Coleta estruturada de NPS** após o 1º diagnóstico (formulário, lembrete, consolidação). Implantação + Produto.
- **Entrevistas qualitativas** estruturadas com cada Roberto (mitigação do "Risco de hipótese" registrado em `current-wave.md`). PO + Implantação.
- **Janela de observação de 60 dias** do beta, com leitura semanal das métricas D2 (Ativação) e D3 (Recorrência inicial). PO + Comercial.
- **Comunicação pública** sobre o lançamento do beta (e-mail interno EBP, redes, newsletter). Comercial + Marketing.

Tech entrega: produto pronto, validado externamente, instrumentado com eventos analíticos, com pacote de handoff. Tech fica disponível para suporte técnico reativo durante o beta (bugs, dúvidas de pipeline, ajustes de feature flag), mas não conduz a operação do beta.

## Compromisso visível ao fim do sprint

Ao fim de 2026-07-03, em homologação:

- ✅ Usuário autenticado (conta de teste interna) loga, navega para `/diagnosticos` (item de menu agora ativo, não mais "Em breve"), seleciona uma Empresa Analisada e clica `Novo diagnóstico`.
- ✅ Quiz com 23 campos do Anexo A (Indústria) é apresentado em blocos lógicos (Identificação / DRE / Balanço / Contexto-Captação), com máscaras de R$, % e dias funcionando, tooltips em cada campo, rascunho salvo a cada navegação entre blocos.
- ✅ Validações cruzadas DRE×Balanço disparam mensagens de erro acionáveis (não-bloqueantes) quando há inconsistência conforme §6.6.
- ✅ Submissão do quiz gera o relatório em **≤ 3 segundos no p95** (medido em 50 submissões em homol).
- ✅ Relatório exibe Resumo Executivo (§4.7.1) + 14 indicadores com semáforo (NCG absoluto sem farol, mensagem informativa em 3 faixas) + recomendações textuais por indicador (matriz DEZ/2025 filtrada Indústria) + glossário inline (Anexo I) + rodapé com versão do motor, versão da matriz e aviso legal.
- ✅ Diagnóstico persiste com `motor_version` + `matrix_version` no payload, idempotência verificável (mesmo quiz + mesma versão → mesmo relatório).
- ✅ Eventos `quiz_iniciado` e `diagnostico_concluido` capturados no pipeline definido pelo ADR de eventos (EPIC-000), confirmáveis em consulta SQL ou dashboard.
- ✅ Parecer externo do Validador especialista anexado em `validation/external-review.md` com veredito `approved` (ou `approved_with_pending` com follow-ups documentados não-bloqueantes para o beta).
- ✅ Validação independente interna em `validation/report.md` com veredito `approved`.
- ✅ **Pacote de handoff para comercial/implantação** publicado em `epics/EPIC-002-.../handoff/README.md` com: link do ambiente de homologação, instruções de criação de conta de teste e Empresa Analisada, smoke E2E descrito passo-a-passo, evidências do p95 ≤ 3s, parecer externo, feature flags ativas, escopo coberto (Indústria) e fora de escopo (Comércio/Serviços/PDF/Captação), contato de suporte técnico durante o beta.
- ✅ EPIC-002 promovido para `done` no `index.json` (ótica técnica). Critério da `epic.md` de "≥ 10 Robertos + NPS" mantém-se como meta da onda, executada por comercial/implantação, e aparece nos status reports da WAVE-2026-01.
- ✅ EPIC-003 (Histórico básico) destravado para abertura na SPRINT-2026-W30.

**Métricas de qualidade técnica (gates herdados + novos):**

- Cobertura **≥ 98% no motor de cálculo** (gate da regra de núcleo conforme `quality-standards.md`).
- Cobertura geral **≥ 80%** (gate herdado).
- ≥ 10 casos de teste por fórmula (NRF §9.2) — total ≥ 140 casos para os 14 indicadores.
- Zero regressão nos testes Pest/Dusk dos EPICs 000/001/004.
- Pipeline `release-homolog.yml` verde end-to-end com cada release-candidate semanal (rc.W25S1 a rc.W25S5).

## Capacidade e premissas

- **Time:** Alexandro (PO + revisão + Validador interno) + agentes Claude (Programador para STORY-027 a STORY-035, Arquiteto para STORY-026, Validador para STORY-037) + **especialista financeiro externo a contratar** para STORY-036 (NRF §9.3).
- **Cadência esperada:** velocidade histórica W22 = ~1.6 estórias S/M por dia útil. 12 estórias em 25 dias úteis = 0.48 estórias/dia — folga de 3.3× absorve a complexidade extra do motor e da validação externa.
- **Sem feriado nacional na janela** (2026-05-25 a 2026-07-03). Verificar: Corpus Christi cai em 2026-06-04 (não é feriado nacional, mas é ponto facultativo em alguns estados/órgãos federais — não impacta time interno mas pode atrasar resposta de Validador externo).
- **Cobertura ≥ 80% obrigatória** + cobertura motor **≥ 98%**.
- **Ambiente de homologação `https://defonline.xandrix.com.br`** estável (premissa de continuidade pós EPIC-000/001/004).
- **Validador externo contratado até 2026-06-19** (Checkpoint 4) — PO inicia processo na **semana 1**.
- **Beta fechado:** convite ao primeiro Roberto na Semana 5; lista completa de 10+ Robertos do beta a fechar pelo PO em paralelo durante semanas 1–4.

## Riscos identificados na abertura

| Risco | Probabilidade | Impacto | Mitigação | Owner |
|---|---|---|---|---|
| Motor (STORY-028 + STORY-030) estoura tempo estimado por complexidade das 14 fórmulas + 140+ casos de teste | alta | alto | Fatiamento V1 (7 indicadores essenciais) entrega valor no Checkpoint 1 mesmo se V2 atrasar. Gate kill-switch no Checkpoint 2 dispara PDR de replanejamento se motor incompleto. Validador externo recebe motor parcial na Semana 4 (não Semana 5) para paralelizar revisão. | Programador + Arquiteto + PO |
| Matriz DEZ/2025 (Anexo F) tem ambiguidades ou inconsistências quando filtrada por Indústria | média | médio | Programador faz leitura inicial do Anexo F na **Semana 1** (não Semana 3) e reporta inconsistências ao PO em backlog específico. PO consolida ambiguidades com EB (autor da matriz) em paralelo. Fallback gracioso (STORY-032 CA) garante que faltas pontuais não quebrem o relatório. | Programador + PO |
| Validador externo (STORY-036) atrasa parecer porque o contrato/escopo não fecha a tempo | média | alto | PO inicia processo de contratação na **Semana 1** com 3 candidatos shortlisted; meta de assinar contrato até 2026-06-05 (Checkpoint 2). Fallback: PO solicita parecer de um conselheiro informal (não substitui §9.3 mas destrava beta com ressalva documentada). | PO |
| Validações cruzadas (STORY-034) explodem em complexidade porque regras §6.6 são incompletas na spec | média | médio | PO **fecha lista canônica** de validações cruzadas no Dia 1 da Semana 4 com o Arquiteto — não na Semana 1 (evita over-engineering antecipado). Default: implementar APENAS as 3 regras citadas na epic.md (Q16×12 > Q06×2; custos anuais > vendas; passivo > ativo); outras regras viram backlog. | PO + Arquiteto |
| Decisões abertas em §6 da spec (6.4 expiração rascunho, 6.6 validações, 6.7 aviso PL, 6.8 tooltips, 6.10 trial) bloqueiam estórias da V3/V4 | média | médio | PO consolida lista das 5 decisões em IDR única ANTES da Semana 3 (target: fim da Semana 2). Defaults explícitos: 6.4 = 90 dias (espec); 6.7 = 30% do Ativo (espec); 6.8 = tooltip inline curto; 6.10 não entra (cobrança é WAVE-2026-02). | PO |
| Sprint longa relaxa disciplina de fechamento e épico estoura para 6+ semanas | alta | alto | **Gate kill-switch explícito no Checkpoint 2.** Mini-status semanal de PO (≤ 300 palavras) com decisão go/no-go formal. Cada checkpoint tem **release-candidate tag** (rc.W25S1 a rc.W25S5) — não sair de uma fatia sem rc verde. | PO |
| p95 ≤ 3s não é atingido em homologação (cálculo + render do relatório com 14 indicadores) | baixa | alto | Medição de baseline já na fatia V1 (7 indicadores) — se baseline > 1.5s, alarme. STORY-029 inclui medição p95 como CA. Otimizações típicas (cache de matriz em memória, lazy loading de glossário) viram subtasks se necessário. | Programador |
| Cobertura ≥ 98% no motor impossível de atingir porque algumas faixas têm divisão por zero / valores extremos | baixa | médio | Spike STORY-026 inclui catálogo dos casos extremos (NCG quando vendas = 0; PMR quando recebimento = 0; etc.) e define política de retorno (`null` + mensagem "indisponível"). Casos extremos cobertos por testes específicos. | Arquiteto + Programador |
| Acúmulo de débitos paralelos (STORY-023 ainda no backlog, F-NB-2 da W23) não cabe na sprint | baixa | baixo | Débitos **não entram** nesta sprint por decisão explícita — foco total no épico. STORY-023 e F-NB-2 ficam no backlog para SPRINT-2026-W30 (pós EPIC-002). | PO |
| Pacote de handoff (STORY-037) sai genérico demais e comercial/implantação não consegue operar o beta sozinhos | baixa | médio | Template de `handoff/README.md` revisado em par com pelo menos 1 representante de implantação na Semana 4 (não fim da Semana 5). PO coleta perguntas/dúvidas reais antes do fechamento. | PO |
| Cobertura ≥ 80% geral cai porque infra de testes do motor não foi pensada para 140+ casos | baixa | médio | Spike STORY-026 dimensiona estratégia de fixtures (CSV ou YAML de casos canônicos, lidos por test provider). Validador interno (STORY-037) confere o número de casos no parecer. | Programador |

## Decisões pendentes que podem afetar o sprint

- **STORY-026 (spike):** estratégia de versionamento do motor (semver inteiro vs hash de fórmulas vs date-version) — Arquiteto propõe em IDR no Dia 2, PO decide em ≤ 4h. Default: semver inteiro (`motor_version = "1.0.0"`, `matrix_version = "dez-2025"`) — pragmático e legível.
- **Decisões abertas §6 da spec:** PO consolida em IDR única ao fim da Semana 2 (ver risco). Defaults explícitos listados acima.
- **Contratação do Validador externo (STORY-036):** PO inicia na Semana 1, contrato fechado até Checkpoint 2.
- **Layout do relatório (densidade da tabela de 14 indicadores em mobile):** Programador propõe em IDR-XXX dentro da STORY-029, PO decide ≤ 4h. Default: card-per-indicator em mobile, tabela densa em desktop (≥ 1024px).
- **Cap. de rascunho do quiz (expiração — decisão §6.4):** Default 90 dias conforme spec. PO confirma no Dia 1 da Semana 1 (não bloqueia STORY-027, mas precisa fechar antes de `done`).

## Mudanças no escopo do sprint

> Toda alteração no conjunto de estórias após esta abertura registra aqui.

| Data | O que mudou | Motivo | Custo |
|---|---|---|---|
| 2026-05-25 | **STORY-035** realinhada ao **ADR-004 §Decisão 2** (eventos): payload obrigatório passa a ser `{quiz_id, quiz_versao}` para `quiz_iniciado` e `{quiz_id, diagnostico_id, duracao_preenchimento_seg, setor, porte}` para `diagnostico_concluido`; tabela `evento_produto` (não `eventos`); helper `EventLogger::emit` síncrono na transação; removido sink externo assíncrono; `request_id` gravado pelo helper. | Redação original divergia do ADR-004 aprovado em 2026-05-21 — risco de implementar contrato errado de eventos e quebrar o north star. | Zero re-trabalho de dev (estória ainda `draft`). Recupera-se aderência ao ADR. |
| 2026-05-25 | **STORY-034** corrigida: R2 vira `Q14×12 + Q15×12 > Q09×12` (Q14 anualizado) e R3 vira `Q02+Q03+Q04+Q05 < Q06+Q07` (Q05 incluído no ativo). Default mínimo promovido a lista canônica (não esperar Semana 4). `alertas_aceitos[]` declarado fora do canonicalize do `payload_hash` (IDR-010). Textos default das mensagens adicionados. | Fórmulas R2/R3 da estória divergiam da spec V2 §6.6 (autoritativa). Desbloqueio do início — não há mais decisão pendente para Semana 4. | Zero re-trabalho (estória `draft`). |
| 2026-05-25 | **STORY-032** alinhada à **IDR-010**: nome do campo no snapshot trocado para `mensagem_detalhada` (estava `recomendacao`); CA-7 sobre snapshot vs lookup removido (decisão já está em IDR-010); fixado arquivo PHP versionado em `config/motor/matriz-{matrix_version}-industria.php` (sem opção "ou banco"); referências explícitas ao Anexo F com path e à EBC. | Schema do snapshot e decisão arquitetural já estavam em IDR-010 aprovada 2026-05-25 — estória precisava herdar. | Zero re-trabalho (estória `draft`). |
| 2026-05-25 | **STORY-037** corrigida: IDR-007 → **IDR-009** (cross-tenant 404, não 403); pendências do PO promovidas a seção explícita (checklist até 2026-06-26; representante de implantação até Checkpoint 3 com fallback definido). | IDR-007 é "email-provider-resend" (não tem nada com cross-tenant). Tirou bloqueador implícito do Validador interno. | Zero re-trabalho. |
| 2026-05-25 | **STORY-033** ajustada: §6.8 da spec promovida de `[DECIDIR]` a oficial (tooltip inline com click no ícone `?`); gate do PO formalizado em CA-8 (23 textos publicados até 2026-06-05); referência ao Design System v1 (IDR-008 + STORY-019) adicionada; click uniforme em mobile/desktop (sem hover). | §6.8 estava aberta e a estória não citava tokens — risco de tooltip "fora do shell visual" do EPIC-004. | Zero re-trabalho. |
| 2026-05-25 | **STORY-036** explicitada como **coordenação humana (não hand-off ao dev)**. Adicionado CA-0 (3 candidatos shortlisted até Checkpoint 1, não Checkpoint 2). Documentado Plano B (conselheiro informal + ressalva no handoff) se contrato não fechar até 2026-06-05. | Risco "Validador externo atrasa parecer" já listado na seção de riscos da sprint — agora com gate de mitigação antecipado (Checkpoint 1). | Aumento da governança — sem custo de execução. |
| 2026-05-25 | **STORY-033 CA-8 destravado**: 23 textos de tooltip publicados em `app/config/quiz/help-industria.php` v1.0.0 e em `defonline-docs/especificacao/V2/anexos/anexo-A-campos-quiz.md §A.6` (tabela 1.0). Q02–Q16 extraídos da `DEFweb.net - QUIZ.xlsx` (autoria EBC) e expandidos no formato §6.8; Q01 e Q17–Q23 redigidos pela equipe (marcados `rascunho a confirmar EBC`, não bloqueiam dev). | Gate original era 2026-06-05; antecipação destrava o início da estória sem esperar Checkpoint 2 e elimina risco de atraso de conteúdo em V3. | Zero — feito em ~30min usando a planilha-fonte. |
| 2026-05-25 | **Validação independente da STORY-031** publicada em `validation/report-STORY-031.md` com veredito `approved_with_pending`. 2 pendências não-bloqueantes: P-001 (formalizar decisões locais de severidade em IDR-011 ou adendum da IDR-010) e P-002 (evidência arquivada de coverage + screencast — entra no checklist da STORY-037). | PO solicitou validação pontual em sessão pós-fechamento. Não substitui validação final do épico (STORY-037). | Zero. |
| 2026-05-25 | **STORY-032 re-realinhada** após análise do schema real do código + Anexo F: schema do snapshot continua `indicadores_calculados[*].mensagem` (singular) — sem campo `mensagem_detalhada`, sem componente `<x-recomendacao>`; o que muda é o **conteúdo** de `mensagem` (vai do placeholder genérico para texto literal do Anexo F). Anexo F (autoria EBC) tem 1 texto por par. IDR-010 reserva `mensagem_detalhada` para futuro mas não obriga preenchimento. Bump recomendado: motor MINOR 1.2.0 → 1.3.0. | A correção anterior (mesma data, mais cedo) tinha materializado `mensagem_detalhada` como obrigatório — leitura excessiva da IDR-010 sem cruzar com o Anexo F vigente. Realinhamento agora elimina sobre-engenharia e simplifica a estória do dev. | Zero re-trabalho (estória ainda `draft`). |
| 2026-05-25 | **Briefing STORY-032** publicado em `briefings/STORY-032-abertura.md` no padrão STORY-031 (~280 linhas). Cobre: estado atual (motor 1.2.0), bump recomendado (MINOR 1.3.0), contrato da matriz (Anexo F transcrito para `config/motor/matriz-dez-2025-industria.php`), mismatch NCG abs (3 mensagens vs 2 cenários do Anexo F — decisão de manter 3), pegadinhas (schema intacto, fallback `MensagensFarol` preservado, idempotência do auto-append), 18 arquivos a tocar (3 criar + 13 indicadores editar + 2 editar pontual), contrato de saída, comandos locais. | Padrão do projeto exige briefing antes do hand-off ao Programador (STORY-027 a 031 todas têm). | Trabalho do PO antes do dev pegar a estória — ~45min. |
| 2026-05-25 | **Briefing STORY-032 reescrito** no padrão PO enxuto (~35 linhas) substituindo a versão tech de ~280 linhas. Crítica do PO: PO não decide arquitetura (paths, classes, bump de versão, comandos) — define problema, valor, fontes autoritativas, decisões de produto, decisões abertas, critério de sucesso visível, fora de escopo, e devolve o "como" ao Programador/Arquiteto. **Padrão para briefings da V3 em diante:** PO-level enxuto. Os briefings 027-031 (já fechados) ficam como referência histórica do padrão anterior — não revisitar retroativamente. | Briefings tech invadem responsabilidade do dev/arquiteto, anulam juízo de design e envelhecem mal quando stack/estrutura mudar. | Reescrita ~10min; ganho de fôlego em cada futuro briefing. |
| 2026-05-25 | **STORY-032 aprovada e promovida a `done`** após validação independente (`validation/report-STORY-032.md` — veredito `approved`) + validação visual em homol local (motor v1.3.0 vivo). 13 indicadores exibindo texto literal do Anexo F Indústria; NCG abs preservando MSG_FOLGA; Resumo Executivo no topo. 2 pendências não-bloqueantes (P-001 path do matriz-lacunas.md, P-002 cosmético) vão para backlog do EPIC-002 — resolver até STORY-036. Próximo passo: Programador commita e empurra para homologação. | Conclusão da Fatia V3 — matriz qualitativa entregue. | Zero — caminho crítico da sprint mantido. |
| 2026-05-26 | **STORY-033 e STORY-034 validadas independentemente** (`validation/report-STORY-033.md` e `report-STORY-034.md` — veredito `approved` nas duas). STORY-033 já está `done` no `index.json` (commit `e7b96fb`). STORY-034 com `status: in_review` no front-matter mas commitada (`58865b5` + `abcf09b`) — pronta para PO promover a `done` no `index.json`. Validação visual de R3 disparou banner inline corretamente; "Continuar mesmo assim" persistiu em `alertas_aceitos`; diagnóstico subsequente marcou Fontes/PL como Indisponível (coerência semântica entre validação cruzada e cálculo). Cobertura geral 99.6%; `ValidacoesCruzadas` e `Alerta` 100%. | Fatia V4 (validações cruzadas + eventos) entregue. | Zero. |
| 2026-05-26 | **STORY-035 reconciliada para `done`**. Commit `1249195` (Alexandro + Claude Opus 4.7) tinha marcado a estória como `done` mas o `index.json` ainda mostrava `draft`. Reconciliação após Validador identificar a defasagem ao tentar abrir a STORY-037. Implementação alinhada ao ADR-004 §2.2 (`EventLogger::emit` síncrono na transação, tabela `evento_produto`, propriedades canônicas por evento incluindo `quiz_id`, `quiz_versao`, `duracao_preenchimento_seg`, `setor`, `porte`). | Pré-condição da STORY-037 satisfeita. | Zero. |
| 2026-05-26 | **Pré-condições da STORY-037 publicadas pelo PO:** (a) `validation/checklist.md` v1.0 com 60+ itens em 9 blocos (A: métricas técnicas, B: entregáveis epic.md, C: CAs por estória, D: decisões arquiteturais preservadas, E: compat retroativa, F: fora de escopo preservado, G: validação externa, H: handoff, I: promoção do épico); (b) decisão sobre STORY-036 e CA-6 da STORY-037 (ver linha seguinte). | Destrava o Validador a abrir a STORY-037. | ~45 min de PO. |
| 2026-05-26 | **Duas decisões formais do PO sobre fechamento do EPIC-002:** (1) STORY-036 (validação externa NRF §9.3) **adiada** para após o fechamento técnico do épico — EPIC-002 fecha como `done_under_review` (não `done` puro); **beta fechado não pode rodar até parecer externo voltar `approved`/`approved_with_pending`**. (2) STORY-037 CA-6 **sem dry-run interno** — handoff é leitura direta para comercial/implantação; risco de "lacunas só visíveis no uso real" registrado explicitamente. STORY-036 e STORY-037 atualizadas com a decisão; STORY-036 sai do caminho de contratação imediata (CA-0/CA-1 anulados); validador externo vira épico/estória de débito na onda seguinte. | Trade-off consciente: cronograma > rigor formal §9.3 imediato. Risco operacional do beta documentado e gerenciado. | Adiamento de validação externa formal — custo de tempo e dinheiro postergado para depois do fechamento técnico. |

## Mini-status semanal (preenchido pelo PO)

> Cada sexta, ≤ 300 palavras. Formato: (1) estórias `done` desde último checkpoint, (2) riscos materializados, (3) decisão go/no-go.

### Checkpoint 1 — 2026-05-29

*A preencher pelo PO ao fim da Semana 1.*

### Checkpoint 2 — 2026-06-05 (GATE KILL-SWITCH)

*A preencher pelo PO. Se motor (V1+V2) ≠ `done`, PO abre PDR de replanejamento na segunda 2026-06-08.*

### Checkpoint 3 — 2026-06-12

*A preencher pelo PO.*

### Checkpoint 4 — 2026-06-19

*A preencher pelo PO. Confirmar janela do Validador externo para Semana 5.*

### Checkpoint 5 — 2026-07-03 (FECHAMENTO)

*A preencher pelo PO como retro final da sprint.*

## Fechamento do sprint

**Fechado em 2026-05-26** — 38 dias antes do `end_date` planejado (2026-07-03). Goal técnico do épico atingido com folga; o gate regulatório (validação externa NRF §9.3) foi adiado conscientemente pelo PO e vira pré-requisito do beta, não do fechamento técnico.

### O que foi entregue

10 das 12 estórias do épico promovidas a `done` (STORY-026 a 035). EPIC-002 promovido a **`done_under_review`** no `index.json` após validação independente em 2026-05-25 (`validation/report.md`, veredito `approved_with_pending`, 0 falhas bloqueantes).

Em homologação (após deploy do rc — ver pendências) Roberto autenticado:

- Cadastra Empresa Analisada Indústria, abre `/diagnosticos/novo`, preenche os 23 campos do Anexo A (com tooltips por campo da STORY-033 + validações cruzadas DRE × Balanço da STORY-034) e recebe em ≤ 3 s o relatório com 14 indicadores + faróis + Resumo Executivo determinístico (§4.7.1) + recomendações da matriz DEZ/2025 coluna Indústria (Anexo F) + glossário inline (Anexo I).
- Eventos `quiz_iniciado` e `diagnostico_concluido` emitidos com payload ADR-004 §2.2 (sem PII, com `request_id` UUID v7 e `EventLogger::emit` síncrono na transação).
- Diagnóstico persistido com `motor_version = "1.3.0"` + `matrix_version = "dez-2025"` + `payload_hash` SHA-256 (idempotência IDR-010).
- Cross-tenant retorna HTTP 404 (IDR-009).
- Pacote de handoff técnico publicado em `epics/EPIC-002-diagnostico-industria/handoff/README.md` para comercial/implantação.

Métricas de qualidade técnica passaram com folga (ver tabela abaixo).

### O que ficou para trás (e por quê)

- **STORY-036 (validação externa NRF §9.3)** — decisão consciente do PO em 2026-05-26 de **adiar a contratação** do especialista externo para após o fechamento técnico do épico. EPIC-002 fechou como `done_under_review` em vez de `done`. **Beta fechado bloqueado** até parecer voltar `approved`/`approved_with_pending`. Registrado em `backlog/POS-EPIC-002-validacao-externa.md` com janela target de 30 dias.
- **STORY-037 dry-run interno do handoff** — decisão PO 2026-05-26 de sem dry-run; handoff é leitura direta. Risco "lacunas só visíveis no uso real" registrado em `handoff/README.md` §Riscos R-2.
- **Tag `v1.0.0`** ainda não criada (F-NB-3 do relatório). Última tag é `v0.9.1-rc.1`. Pendente para release final.
- **Smoke read-only em homol** ainda não rodado (F-NB-1). A validação independente rodou em `localhost:8090` (dev). Tech ops confirma deploy do rc + re-roda smoke em homol antes do primeiro convite ao Roberto.
- **`SnapshotImutabilidadeTest`** arquitetural ainda não existe (F-NB-5). Imutabilidade hoje em schema + convenção; teste de regressão para evitar update acidental futuro entra como débito S — `backlog/POS-EPIC-002-snapshot-imutabilidade-test.md`.

### Mudanças no escopo durante o sprint

Consolidado das 12 entradas da tabela "Mudanças no escopo do sprint" acima. Destaques:

- **Bloco PO de correções pré-hand-off (2026-05-25):** 6 estórias re-alinhadas com IDR/ADR/spec (035 com ADR-004, 034 fórmulas vs §6.6, 032 com IDR-010, 037 IDR-009, 033 design system, 036 coordenação humana).
- **Antecipação de gates de PO:** 23 textos de tooltip (CA-8 STORY-033) entregues 11 dias antes do gate; lista canônica de validações cruzadas fechada 21 dias antes do gate; matriz DEZ/2025 transcrita junto com a entrega da STORY-032.
- **Aprendizado de processo:** padrão de briefing PO-level enxuto (~35 linhas) substitui o padrão tech anterior (~280 linhas) — vigente da V3 em diante.
- **Decisões formais finais (2026-05-26):** STORY-036 adiada + STORY-037 sem dry-run + EPIC-002 fecha como `done_under_review`.

### Aprendizados (retro por escrito)

**Experimento "sprint longa com checkpoints semanais":** **viável e aprovado para repetição** em épicos densos do tamanho do EPIC-002, com as adaptações abaixo:

1. **Aceleração brutal vs. estimativa de 5 semanas.** Goal técnico fechou em ~2 dias úteis efetivos (25–26/mai) graças à parceria Programador-agente + PO-agente em sessões intensas. Velocidade real >> velocidade planejada (38 dias de janela usados, fechado em ~2). **Sinal:** estimativas conservadoras de planejamento podem ser refeitas para épicos similares.

2. **"Sem dry-run" e "sem validação externa" foram trade-offs conscientes, não negligência.** O Validador apontou as duas como `fail (não-bloqueante)` e o épico fechou como `done_under_review`, exatamente como esperado. Modelo de fechamento gradual (`done_under_review` → `done` após gate externo) **funciona** para destravar trabalho sem violar a régua regulatória.

3. **Briefings tech demais invadem responsabilidade do dev.** Primeiro briefing da STORY-032 saiu com 280 linhas (paths, classes, bumps, comandos) — PO devolveu para refazer em ~35 linhas (problema, fonte, decisões abertas, critério de sucesso). Padrão novo vigente da V3 em diante.

4. **Reconciliação de doc vs index.json continua escapando.** STORY-035 fechou no commit (1249195) mas o arquivo da estória ficou `draft` até a reconciliação de 26/mai. F-NB-6 do relatório do Validador. **Ação para retro:** lembrete no `done-checklist.md` do Programador cobre arquivo + index + report (não só commit).

5. **Handoff como deliverable formal não pode ser última coisa.** O `handoff/README.md` ficou para o fim e quase virou bloqueador do `done` da STORY-037. **Ação para retro:** próxima sprint que tiver handoff trata como entregável de "fatia paralela" desde a V1, não como tarefa de fechamento.

6. **Pa11y formal ficou de fora.** Cobertura funcional de teclado/ARIA via Dusk é suficiente para a entrega, mas a auditoria automatizada é evidência arquivada barata que vale ter. **Ação para retro:** adicionar `pa11y` ao pipeline de CI quando rc subir.

### Métricas finais da sprint

| Métrica | Valor | Meta | Status |
|---|---|---|---|
| Estórias `done` (técnica) | 10 (STORY-026..035) | 10 (técnicas, sem STORY-036/037) | ✅ |
| Estórias entregues / planejadas | 10/12 técnicas + STORY-037 parcial (handoff publicado, tag pendente) | 100% | ⚠️ — STORY-036 adiada por decisão PO |
| Cobertura geral | **97.3%** | ≥ 80% | ✅ |
| Cobertura motor | **99.6%** | ≥ 98% | ✅ |
| Casos de teste por fórmula | **204** (média 14/indicador, mín 11) | ≥ 10 | ✅ |
| p95 relatório (50 amostras) | **20 ms** (em localhost dev — re-medir em homol) | ≤ 3 s | ✅ (com ressalva F-NB-1) |
| Validação externa | **pendente (adiada — backlog)** | `approved` | ⚠️ — decisão PO 2026-05-26 |
| Validação interna | **`approved_with_pending`** | `approved` | ✅ (8 F-NB não-bloqueantes) |
| Pacote de handoff entregue | **sim** (`handoff/README.md` v1.0) | sim | ✅ |
| Eventos `quiz_iniciado` capturados | confirmado em SQL (`B-10-eventos-sql.txt`) | ≥ 1 | ✅ |
| Eventos `diagnostico_concluido` capturados | confirmado em SQL com payload ADR-004 §2.2 completo | ≥ 1 | ✅ |
| Suíte Pest | 787 verdes (2424 assertions) | 0 falhas | ✅ |
| Suíte Dusk | 17 verdes (105 assertions) | 0 falhas | ✅ |

### Comemoração (cultura)

EPIC-002 era o épico-mãe da WAVE-2026-01 — o que prova ou refuta a hipótese do Roberto. Fechamos a parte técnica em uma fração do tempo planejado, com cobertura acima dos gates regulatórios e zero falhas bloqueantes. **Próxima dúvida vital — "o Roberto vai usar?" — depende agora de validação externa + comercial, não mais de tech.** Bom trabalho do trio PO-Programador-Validador (agentes Claude) em ritmo sustentado.

## Comunicação ao stakeholder (registro de I-4)

| Data | Para | Canal | Conteúdo |
|---|---|---|---|
| 2026-05-26 | Alexandro (PO/stakeholder de si próprio + futuro time comercial/implantação) | Registro nesta sprint + entry no handoff | EPIC-002 fechado como `done_under_review`. **Beta não pode rodar até STORY-036 (validação externa NRF §9.3) voltar `approved`.** Handoff técnico publicado em `epics/EPIC-002-diagnostico-industria/handoff/README.md`. Backlog pós-épico em `epics/EPIC-002-diagnostico-industria/backlog/`. Tag de release e deploy do rc em homol são tarefas tech imediatas. |
