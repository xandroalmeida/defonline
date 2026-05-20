# Requisitos não-funcionais como decisões arquiteturais

NFRs (Non-Functional Requirements) são frequentemente tratados como "depois que escalarmos" — o que é uma armadilha. Para um SaaS financeiro como DEFOnline, **NFRs moldam decisão estrutural cedo**. Disponibilidade, latência, recovery, custo, capacidade — cada um vira ou ADR dedicada ou seção em ADRs maiores.

Esta reference cobre **como tratar NFRs no nível arquitetural**: definir, quantificar, expressar em ADR, validar. O PO especifica requisitos no nível de produto (`defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md`); o Arquiteto **decide arquitetura** para atendê-los.

---

## A mentalidade

- **NFRs não são "depois".** Decisão arquitetural sem NFR explícito é decisão no escuro — vai descobrir o problema em produção.
- **Quantifique.** "Rápido" não é NFR; "p95 < 500ms" é. O quantificável é defensável.
- **Reconheça que NFRs trade-off.** Disponibilidade máxima cobra custo; latência mínima cobra complexidade. Você escolhe o ponto.
- **Comece modesto.** SLO de 99.999% (cinco noves) é caro e raramente necessário. 99.5% pode ser razoável no MVP.

---

## SLO, SLI, SLA — o vocabulário

| Termo | Significa | Quem usa |
|---|---|---|
| **SLO** (Service Level Objective) | Meta interna de qualidade ("disponibilidade 99.9%") | Time |
| **SLI** (Service Level Indicator) | Métrica que mede o SLO ("% de requests com 2xx em janela de 30 dias") | Time |
| **SLA** (Service Level Agreement) | Contrato com cliente (com penalidade se não cumprir) | Comercial / legal |

Para o DEFOnline no MVP, foque em **SLOs e SLIs** internos. SLA com cliente é decisão comercial — vem depois.

---

## Disponibilidade

**Pergunta arquitetural:** que SLO de disponibilidade vamos perseguir, e o que ele exige?

### Calibração de expectativa

| SLO | Downtime mensal aceitável | Implicações típicas |
|---|---|---|
| 99% | ~7h | Sistema simples, deploys com janela curta de downtime OK |
| 99.5% | ~3.6h | Deploys precisam ser rápidos e cuidadosos |
| 99.9% | ~43min | Redundância em pontos críticos, deploy sem downtime |
| 99.95% | ~22min | Multi-AZ, failover automático |
| 99.99% | ~4.4min | Multi-region, observabilidade pesada |
| 99.999% | ~26s | Apenas para sistemas de missão crítica |

**Para DEFOnline (sugestão para discussão):** 99.5% no MVP, evoluindo para 99.9% conforme adoção. Custo vs valor pesa demais cinco noves para SaaS de MPE no início.

### O que o ADR de disponibilidade define

- SLO escolhido + janela de medição (diário, mensal, trimestral).
- SLI: como medimos. Tipicamente: `(requests com sucesso) / (requests totais)` em janela X, ou `tempo em serviço / tempo total`.
- Estratégia de redundância (multi-AZ? multi-region?).
- Estratégia de deploy (zero-downtime sempre? janela de maintenance aceita?).
- Resposta a incidentes (quem é notificado? por qual canal?).
- Trade-off de custo aceito.

---

## Latência

**Pergunta arquitetural:** quais endpoints têm que ser rápidos, e quão rápidos?

Latência é p**ercentual**, não média. Use percentis:

- **p50** (mediana): metade das requests termina antes.
- **p95**: 95% terminam antes. Ponto onde latência "doe".
- **p99**: 99% terminam antes. Cauda da distribuição — onde reclamações concentram.

**Diferenças importantes entre endpoints:**

- **API interativa** (FE chama, usuário espera resposta): p95 < 500ms é razoável para SaaS. < 200ms é ótimo.
- **Operação assíncrona** (job de processamento, importação de planilha): latência fim-a-fim pode ser segundos a minutos — usuário não espera no FE.
- **Webhook entrante**: precisa ser **muito rápido** (< 1s) — fornecedor pode timeoutar se você demora.
- **Endpoint de health check**: muito rápido (< 100ms) — não causa falso alarme.

### O que o ADR de latência define

- SLO de latência por classe de endpoint (interativo, assíncrono, etc).
- Como medimos (percentis, janela).
- Estratégia para alcançar (cache, indexação, otimização, lazy loading — decisões derivadas).
- Como alertar quando degradação acontece.

---

## Throughput e capacidade

**Pergunta arquitetural:** quantos requests por unidade de tempo o sistema precisa atender? Como cresce?

Para SaaS de MPE no início, throughput não é problema — você tem dezenas, talvez centenas de empresas. Mas **estime explicitamente** para não ser pego de surpresa:

- Quantas empresas ativas previstas em 6 meses? 12 meses?
- Quantos requests/segundo por empresa típica?
- Picos? (Final de mês para empresas faz mais cálculo? Ano fiscal?)

### Capacity planning leve

ADR de capacidade define:

- Volume esperado (ordem de magnitude, não precisão).
- Capacidade provisionada inicialmente (1 instância da app? 2?).
- Como detectar quando precisa escalar (métricas, alertas).
- Estratégia de escalar (vertical primeiro? horizontal?).
- **Princípio simplicidade**: vertical (máquina maior) antes de horizontal (várias máquinas) — princípio #1.

**Para DEFOnline (sugestão para discussão):** 1 instância de app + 1 worker no início, escala vertical até cliente onde horizontal justifica. Postgres único (com read replica eventualmente).

---

## RTO e RPO — recuperação após incidente

Quando o sistema cair (vai cair), quanto tempo até voltar, e quanto dado podemos perder?

| Termo | Significa | Pergunta concreta |
|---|---|---|
| **RTO** (Recovery Time Objective) | Tempo máximo de downtime após incidente | "Após desastre, em quanto tempo o sistema volta?" |
| **RPO** (Recovery Point Objective) | Volume de dado que aceitamos perder | "Qual a defasagem máxima do último backup que aceitamos?" |

Calibração típica:

| Cenário | RTO | RPO |
|---|---|---|
| Sistema crítico (banco, saúde) | < 1h | < 5min |
| SaaS típico business | 4-12h | 1-24h |
| Aplicação interna não-crítica | 24-48h | 24h |

**Para DEFOnline (sugestão para discussão):** RTO 4h, RPO 1h no MVP. Backup contínuo do Postgres (WAL archiving) atinge RPO baixo facilmente.

### O que o ADR de recovery define

- RTO + RPO escolhidos + justificativa.
- Estratégia de backup (frequência, ferramenta, destino).
- Estratégia de restore (procedimento testado — é ESSENCIAL testar restore periodicamente).
- Disaster recovery: se cloud/região cai inteira, qual o plano?
- Runbook: passos automatizados ou bem documentados.

**Princípio essencial:** backup que nunca foi testado para restore é fantasia. Restore deve ser **exercitado** ao menos no início do projeto e periodicamente.

---

## Confiabilidade — graceful degradation

Sistema confiável não é sistema que nunca falha. É sistema que **falha graciosamente** quando partes caem.

### Padrões de design

- **Circuit breaker** (cruza com `error-handling.md` do programador): no nível arquitetural, decida onde quebrar circuitos para integrações externas pesadas.
- **Bulkhead**: isolar pools de recurso para que falha de uma parte não afoga as outras.
- **Timeout em toda chamada externa** — sem exceção.
- **Retry com backoff/jitter** onde faz sentido.
- **Fallback**: se serviço externo cai, o que mostramos ao usuário? Tela de erro decente, dado em cache, modo degradado?

### O que o ADR de confiabilidade define

- Componentes externos identificados com criticidade (crítico para o fluxo? auxiliar?).
- Estratégia por componente (circuit breaker? cache? fallback?).
- Como o usuário percebe degradação (mensagem específica, não 500 mudo).

---

## Performance budgets — para frontend web

Específico para FE: tempo até página utilizável, tamanho de bundle, frames por segundo.

| Métrica | Bom | Aceitável | Ruim |
|---|---|---|---|
| **First Contentful Paint (FCP)** | < 1.8s | < 3s | > 3s |
| **Largest Contentful Paint (LCP)** | < 2.5s | < 4s | > 4s |
| **Time to Interactive (TTI)** | < 3.8s | < 7.3s | > 7.3s |
| **Bundle size (gzip)** | < 200KB | < 500KB | > 500KB |
| **Cumulative Layout Shift (CLS)** | < 0.1 | < 0.25 | > 0.25 |

### O que o ADR de FE performance define

- Budgets por métrica.
- Como medimos em CI (Lighthouse CI, etc).
- Estratégias arquiteturais (code splitting, lazy loading, SSR/SSG/CSR — decisão).
- Política para reverter PR que ultrapassa budget.

---

## Custo como NFR

Princípio arquitetural #11. Toda ADR que adiciona custo recorrente nomeia o custo.

### Categorias

- **Cloud / hospedagem**: cálculo direto.
- **Licenças**: serviços SaaS de apoio (observabilidade, monitoramento, etc).
- **Tempo de pessoa**: operação manual = custo (mesmo que oculto).
- **Custo de incidente**: indisponibilidade = dinheiro/confiança.

### Calibração para SaaS de MPE no início

ADR pode definir uma **régua mensal aproximada** do custo total da plataforma. Por exemplo:

| Categoria | Faixa típica MVP | Sinal se ultrapassa |
|---|---|---|
| Cloud (app + DB) | R$X-Y/mês | Pricing model errado ou subscale errado |
| Observabilidade | R$Z/mês | Logando demais (cruza com `observability-discipline.md`) |
| Integrações externas | R$W/mês | Renegociar contrato ou trocar fornecedor |

Sem números reais a colocar agora — esse vira ADR explícita quando dados de uso reais aparecerem.

---

## LGPD como NFR

Direitos do titular (acesso, correção, portabilidade, eliminação) viram **NFRs do sistema**: o sistema **precisa ser capaz de** atender cada um. Veja também `security-architecture.md`.

### NFRs derivados de LGPD

- **Capacidade de exportar** todos os dados pessoais de um titular em formato estruturado, em prazo razoável.
- **Capacidade de eliminar** dados sob solicitação, distinguindo soft delete (operacional) de hard delete (LGPD).
- **Capacidade de auditoria** — quem acessou, alterou ou exportou dado pessoal.
- **Retenção automática** — dados são purgados após prazo, automaticamente.
- **Consentimento rastreável** — base legal de cada dado pessoal é registrável.

ADRs específicas implementam cada um. O Arquiteto pensa nisso **cedo** — não no fim.

---

## Como expressar NFRs em ADR

Cada ADR que adiciona componente / mexe em estrutura **deve responder** aos NFRs aplicáveis:

```markdown
## Plano de verificação (seção do template)

- **Disponibilidade**: este componente contribui para SLO de 99.5% — depende de [explicar].
- **Latência**: p95 alvo < 500ms — verificado em testes de carga ou benchmark.
- **Capacidade**: dimensionado para N requests/s — sinal de revisão: ultrapassar Y.
- **Custo**: estimado R$X/mês — sinal de revisão: ultrapassar 30%.
- **LGPD**: dados pessoais tocados: [lista]. Tratamento conforme [referência].
- **Observabilidade**: métricas RED, log estruturado, trace ID propagado.
```

Não é seção decorativa — é o que torna o ADR **verificável**.

---

## Quando NFR é PDR vs ADR

- **PDR** (decisão de produto, PO): "queremos atender 99.9% de disponibilidade" — decisão de **ambição/valor**.
- **ADR** (decisão arquitetural, Arquiteto): "para atingir 99.9%, vamos com multi-AZ + failover automático + observabilidade em X" — decisão de **arquitetura para implementar**.

Em geral: PDR define o NFR. ADR define a arquitetura que cumpre.

Para o Arquiteto: se você está propondo um nível de NFR (não só como atender), considere se isso é decisão sua ou se é assunto de PO. **Em dúvida, conversa com o PO antes**.

---

## Resumo operacional

Para decisões arquiteturais relevantes, pergunte:

- [ ] Que SLO de disponibilidade esta decisão precisa atender?
- [ ] Que latência esperamos? Em quais endpoints?
- [ ] Qual o throughput esperado? Como cresce?
- [ ] RTO/RPO aplicáveis?
- [ ] Componente pode falhar — qual a degradação graciosa?
- [ ] Performance budgets (se FE)?
- [ ] Custo recorrente estimado?
- [ ] LGPD: impacto, capacidade de atender direitos do titular?

NFRs não nomeados são NFRs ignorados. Nomeie-os.
