# Tipos de ADR — sub-orientação e exemplos

ADRs não são todas iguais. Uma decisão de **stack** tem perguntas diferentes de uma decisão de **contrato**, que tem perguntas diferentes de uma decisão de **infra**. Esta reference dá guidance específica por tipo: o que cobrir, armadilhas comuns, exigências adicionais (diagrama, spike), e quando uma decisão é grande o bastante para merecer ADR vs. ser um IDR ou só comentário no PR.

## Heurística geral: é ADR, IDR, ou PR comment?

Antes de escrever ADR, decida o nível certo. Três níveis:

| Escala da decisão | Onde mora | Quem decide | Aprovação |
|---|---|---|---|
| **Estrutural — afeta múltiplos módulos, múltiplos agentes, ou contrato público** | **ADR** em `project-state/decisions/adr/` | Arquiteto | Humano explícito |
| **Local mas com impacto futuro — vira padrão de um módulo, ou workaround durável** | **IDR** em `project-state/decisions/idr/` | Programador | Sem aprovação humana formal — confia no critério dele |
| **Local sem impacto futuro — estilo, nome, organização dentro de função** | **Comentário no PR** | Programador | Code review |

**Perguntas para classificar:**

1. **Afeta mais de um módulo ou mais de um agente?** Sim → ADR.
2. **Limita decisões futuras de outros agentes?** Sim → ADR.
3. **Contradiz ou substitui ADR existente?** Sim → ADR (supersede).
4. **Vira convenção transversal do projeto?** Sim → IDR (mas se for muito impactante, ADR).
5. **Só afeta um pedaço local de código e ninguém vai precisar saber depois?** PR comment.

**Quando em dúvida**: prefira IDR a "deixar pra lá". É mais barato registrar e descobrir que não precisava do que não registrar e perder o histórico.

---

## Tipo 1 — Stack

> Escolha de linguagem(s), framework(s) principais, runtime, banco.

**O que essa ADR cobre:**

- Linguagem(s) escolhida(s) — incluindo separação FE/BE se houver.
- Framework opinativo principal (princípio arquitetural #4).
- Runtime / plataforma de execução.
- Banco (Postgres já fixado — mas versão, configurações importantes).
- ORM ou query layer.
- Ferramenta de testes (unit + E2E).
- Gerenciador de dependências.

**Perguntas centrais:**

- Qual linguagem? Por que ela e não as alternativas óbvias?
- Qual framework opinativo? Que defaults ele te dá (auth, ORM, admin, migrations, etc)?
- Maturidade da combinação: tem 5+ anos? Tem comunidade ativa? Tem hire-ability local (encontrar dev/agente que conhece)?
- Compatibilidade com TDD e E2E em browser real (princípio arquitetural #10)?
- Compatibilidade com funcionamento 100% local com Docker (princípio #6)?
- Como o ambiente local sobe? Idealmente em 1 comando após clone.

**Armadilhas comuns:**

- Escolher tech "moderna" sem maturidade comprovada — risco de virar exótica.
- Underestimar curva de aprendizado.
- Não considerar custo operacional da plataforma.
- Stack com 30 dependências individuais em vez de framework opinativo (viola princípio #4).
- Escolher por gosto pessoal sem critério honesto.

**Exigências adicionais:**

- **Spike de "hello world deploy"** antes de aceitar — não basta papel. Princípio: decisão irreversível na prática (princípio #7) — exige validação.
- **Diagrama**: opcional (a decisão é mais textual que topológica).
- **Estimativa de custo** ordem de magnitude (princípio #11).
- **Plano de verificação**: como sabemos que a stack atende SLOs (latência, throughput) em ambiente real.

**Mini-checklist:**
- [ ] Linguagem + framework + runtime nomeados e justificados.
- [ ] Alternativas reais consideradas (mínimo 2 + status quo).
- [ ] Como atende cada um dos 6 princípios centrais.
- [ ] Como E2E em browser real funciona (se FE web).
- [ ] Como ambiente local sobe em 1 comando.
- [ ] Estimativa de custo recorrente.
- [ ] Spike proposto (ou justificativa para não ter spike).

**Exemplo ilustrativo de decisão (não real do projeto):**

> "Adotamos **Django (Python 3.12)** como framework principal do backend. Alternativas rejeitadas: Rails (preferimos ecossistema Python pelo viés de manipulação de dados financeiros e ML futuro); FastAPI (pouco opinativo, exigiria reconstrução de admin, validation, migrations); NestJS (igualmente válido, perdeu pelo viés Python). Django entrega: auth, admin, ORM, migrations, validation, ORM, testing — defaults seguros e maduros. Compatível com TDD via `pytest` + factory_boy, E2E via Playwright integrado. Roda em Docker em <30s; deploy em qualquer cloud de Python."

---

## Tipo 2 — Topológico

> Como os componentes do sistema se organizam — monolito vs separação, sync vs async, fronteiras de processo.

**O que essa ADR cobre:**

- Estrutura de processos: monolito modular (princípio #2), separação técnica natural (FE/BE/worker), eventual microsserviço (com critério forte).
- Comunicação sync vs async entre componentes.
- Onde fica cada responsabilidade.
- Borda do sistema (reverse proxy, edge, etc).

**Perguntas centrais:**

- Por que esta topologia e não monolito mais simples? (Princípio #2.)
- Qual a fronteira de cada componente — pela razão única de mudar (princípio #5)?
- Cada comunicação entre componentes: sync, async, ou ambos?
- Qual o impacto em latência, disponibilidade, debugging?
- Como tudo isso roda local em Docker Compose (princípio #6)?

**Armadilhas comuns:**

- Quebrar monolito sem evidência (viola princípio #2 — central).
- Microsserviços sem necessidade real, com complexidade que time pequeno não opera.
- Comunicação async sem ferramentas de observação (sem trace, sem fila monitorada).
- Bordas baseadas em camadas técnicas (controllers, services) em vez de razão de negócio (princípio #5).

**Exigências adicionais:**

- **Diagrama Mermaid obrigatório** — texto sozinho é ambíguo para topologia. Veja `diagrams.md`.
- **Plano de observabilidade**: como rastrear request que cruza componentes (trace ID).
- **Como roda local**: descrever o `docker-compose.yml` conceitual.

**Mini-checklist:**
- [ ] Componentes nomeados pela razão de negócio, não camada técnica.
- [ ] Diagrama de topologia incluído (Mermaid).
- [ ] Comunicação entre componentes especificada (sync/async + protocolo).
- [ ] Justificativa explícita se foge do monolito (princípio #2 é central).
- [ ] Plano de funcionamento local com Docker.
- [ ] Estratégia de trace/correlação entre componentes.

**Exemplo ilustrativo:**

> "Adotamos **monolito modular único** com módulos: `cadastro`, `diagnostico`, `importacao`, `auth`. Comunicação interna: chamadas síncronas de função entre módulos via interfaces explícitas. Worker assíncrono via job queue em Postgres (`SKIP LOCKED`) para processamento de planilha — único componente fora do request HTTP. Reverse proxy na borda (decisão de infra, ADR separada). Local: 3 containers (app, worker, postgres) sobem com `docker compose up`."

---

## Tipo 3 — Contrato

> Formato de comunicação entre componentes ou com o exterior — REST, gRPC, GraphQL, eventos, payloads, versionamento.

**O que essa ADR cobre:**

- Protocolo (HTTP REST, gRPC, GraphQL, WebSocket, etc).
- Formato de payload (JSON, Protobuf, etc).
- Convenção de naming (snake_case vs camelCase, etc).
- Versionamento (URL-path, header, content-type).
- Estratégia de evolução (compatibilidade retroativa, deprecation).
- Documentação do contrato (OpenAPI, schemas, etc).
- Tratamento de erro padronizado (códigos, formato de body de erro).
- Paginação.
- Autenticação no canal.

**Perguntas centrais:**

- Quem são os consumidores? (FE próprio, mobile, parceiros externos?)
- O contrato é público (clientes terceiros) ou interno?
- Como evolui? (Versão major em URL? Header? Backward compat sempre?)
- Como documentamos? Geração automática (OpenAPI) ou manual?
- Como testamos contrato? (Schema validation, contract testing?)

**Armadilhas comuns:**

- Pular versionamento ("vamos resolver depois") — depois é tarde.
- Não padronizar formato de erro — cada endpoint inventa o seu.
- Misturar verbos no path (`/api/getEmpresa`) em vez de seguir REST corretamente.
- Quebrar contrato publicado sem deprecation.
- Aceitar campos extras silenciosamente (vulnerável) vs rejeitar com clareza.

**Exigências adicionais:**

- **Diagrama obrigatório** se a decisão é de **contrato entre serviços** — sequência ou fluxo.
- **Exemplo concreto** de request/response no ADR.
- **Plano de versionamento** explícito.
- **Mecanismo de validação** automatizada (OpenAPI checker, contract test).

**Mini-checklist:**
- [ ] Protocolo + formato definidos.
- [ ] Convenção de naming explícita.
- [ ] Estratégia de versionamento documentada com exemplo.
- [ ] Tratamento de erro padronizado (códigos HTTP + formato body).
- [ ] Documentação automática (OpenAPI ou equivalente) configurada.
- [ ] Exemplo de request/response no ADR.
- [ ] Contract test ou validação de schema em CI.

**Exemplo ilustrativo:**

> "APIs HTTP REST com JSON. Convenção: paths em kebab-case, campos em snake_case (consistente com Postgres). Versionamento via path: `/api/v1/...`. Mudanças incompatíveis exigem v2 (v1 continua atendendo por 6 meses após deprecation anunciada). Erros seguem padrão: HTTP code apropriado (4xx/5xx) + body `{error: {code, message, details}}`. OpenAPI gerado automaticamente pelo framework, validado em CI."

---

## Tipo 4 — Persistência

> Modelo de dados macro, estratégia de evolução de schema, abordagem para armazenamento.

**O que essa ADR cobre:**

- Banco (Postgres já fixado — mas detalhes: versão, extensões adotadas).
- Modelo macro: agregados, entidades principais, relações.
- Estratégia de migrations (ferramenta, padrão).
- Multi-tenancy (separação por empresa — ver `security-architecture.md`).
- Audit log (tabela, triggers, app-side).
- Estratégia de backup e recovery (referência ao NFR-architecture).
- Quando JSON / jsonb / vector / time-series.

**Perguntas centrais:**

- Quais os agregados de negócio (princípio #5 aplicado a dados)?
- Como tenants são isolados? (Coluna tenant_id + filtro? Row-level security?)
- Como migrations rodam em produção (zero-downtime, etc)?
- Que extensões do Postgres usaremos? (pgvector, PostGIS, TimescaleDB, etc — princípio #3.)
- Auditoria: o que vira audit log, como?
- Soft delete vs hard delete (cruzando com LGPD direito de eliminação).

**Armadilhas comuns:**

- Modelo orientado a tabela em vez de a agregado (perde fronteira de transação clara).
- Esquecer multi-tenancy desde o início (retrofit é doloroso).
- Adicionar armazenamento extra sem provar que Postgres não atende (princípio #3 — central).
- Migrations não-reversíveis sem aviso.
- Hard-delete onde soft seria correto (perde rastro, atrapalha LGPD).

**Exigências adicionais:**

- **Diagrama ER ou de agregados** (princípio: topologia de dados é território de diagrama). Veja `diagrams.md`.
- **Plano de migração**: ferramenta, processo, estratégia para volume.
- **Justificativa Postgres-first** quando extensões/recursos forem usados — não é desvio, é uso pleno.

**Mini-checklist:**
- [ ] Agregados identificados pela razão de negócio.
- [ ] Diagrama ER ou equivalente.
- [ ] Multi-tenancy: mecanismo + automação de filtro.
- [ ] Migrations: ferramenta + padrão (reversíveis, idempotentes, sem downtime).
- [ ] Audit log no modelo.
- [ ] Extensões do Postgres explicitamente listadas (e por quê).
- [ ] Estratégia de soft vs hard delete + LGPD.

**Exemplo ilustrativo:**

> "Modelo orientado a agregado: `Empresa` (root), `Diagnostico` (filho), `Indicador` (parte de Diagnostico). Multi-tenancy via coluna `empresa_id` (FK) + ORM scope global que filtra automaticamente. Migrations via Alembic, todas reversíveis. Audit log em tabela `audit_log` append-only, popular via triggers Postgres para tabelas core. Extensão `pg_trgm` para busca por similaridade, `pgcrypto` para criptografar coluna de dado financeiro. Soft delete (coluna `deleted_at`) para entidades de negócio; hard delete via job de purga LGPD."

---

## Tipo 5 — Infra

> Onde e como o sistema roda — cloud, IaC, ambientes, rede.

**O que essa ADR cobre:**

- Provedor cloud / hospedagem.
- IaC: ferramenta (Terraform, Pulumi, etc — quando aplicável).
- Ambientes: dev local, homologação, produção (princípio entrega desde dia 1).
- Estratégia de deploy (CI/CD).
- Rede: VPC, subnets, regras.
- Observabilidade básica (sinais coletados, ferramentas — cruza com `nfr-architecture.md`).
- Backup, restore, recovery (cruza com `nfr-architecture.md`).

**Perguntas centrais:**

- Onde roda? (Provedor, região.)
- Como provisionamos do zero? (IaC ou processo manual documentado — IaC ganha.)
- Custo recorrente? (Princípio #11.)
- Como funcionam os 3 ambientes (local, homologação, produção)?
- O que difere entre eles e por quê?

**Armadilhas comuns:**

- Clicar manualmente no painel da cloud — viola princípio "automatização" do PO.
- Ambiente local divergindo de produção (viola princípio #6 e #8).
- Lock-in profundo sem reconhecer o trade-off.
- Custo subestimado por falta de orçamento explícito.

**Exigências adicionais:**

- **Diagrama de infra** (Mermaid: componentes, rede, edge) — recomendado.
- **Estimativa de custo** mensal por ambiente (ordem de magnitude).
- **Como ambiente é recriado do zero** (idealmente IaC + processo documentado).

**Mini-checklist:**
- [ ] Provedor + região + serviços principais nomeados.
- [ ] IaC (ou justificativa para não ter — sempre tem que ter no DEFOnline, princípio do PO).
- [ ] 3 ambientes (local, homologação, produção) configurados desde dia 1.
- [ ] Diferenças entre ambientes nomeadas e justificadas.
- [ ] Estimativa de custo recorrente por ambiente.
- [ ] Como recriar do zero.
- [ ] Backup + restore com runbook automatizado.

---

## Tipo 6 — Observabilidade

> Que sinais coletamos, como, com quais ferramentas, com quais alertas.

**O que essa ADR cobre:**

- Ferramenta de logs (centralizadora).
- Ferramenta de métricas.
- Ferramenta de tracing (se aplicável).
- Formato de log estruturado padrão do projeto.
- Métricas básicas (RED — Rate, Errors, Duration).
- Métricas de negócio relevantes.
- Health checks (liveness, readiness).
- Alertas (o que dispara alerta, para quem).
- Retenção de logs e métricas.

**Perguntas centrais:**

- Onde os logs vão? (Cloud do provedor, serviço dedicado, self-hosted?)
- Onde as métricas vão?
- Custo recorrente da observabilidade — alto comparado ao app?
- Alertas: para quem? Como? (Telegram? E-mail? PagerDuty?)
- Retenção: quanto tempo?

**Armadilhas comuns:**

- Logar tudo e pagar caro por ruído (anti-padrão `observability-discipline.md`).
- Sem alerta — incidente descoberto pelo cliente.
- Sem trace ID — debug em produção vira arqueologia.
- Logar dado sensível (cruza com `security-architecture.md`).

**Mini-checklist:**
- [ ] Stack de observabilidade definida (logs, métricas, tracing).
- [ ] Formato de log estruturado padrão do projeto definido.
- [ ] Health checks definidos (liveness, readiness).
- [ ] Métricas RED automaticamente coletadas em endpoints.
- [ ] Política de mascaramento de PII em log (automatizada).
- [ ] Alertas mínimos: serviço down, taxa de erro alta, latência ruim.
- [ ] Estimativa de custo.

---

## Tipo 7 — Política de evolução

> Como o código e o sistema evoluem ao longo do tempo — branching, releases, feature flags.

**O que essa ADR cobre:**

- Modelo de branching (trunk-based, GitFlow, etc).
- Estratégia de release (continuous deployment, releases agendadas).
- Feature flags: ferramenta, padrão de uso.
- Versionamento do produto (semver, calver, ou não-versionado).
- Migração contínua vs janela de manutenção.
- Política de hotfix.

**Perguntas centrais:**

- Como código entra em produção? (PR mergeado → deploy automático, ou gate manual?)
- Releases continuas ou batched?
- Feature flags: usamos? Para o quê (rollout gradual, A/B, kill switch)?
- Como reverter um deploy ruim?
- Janela de manutenção é aceitável?

**Armadilhas comuns:**

- Branching complexo demais para time pequeno (GitFlow em time de 2 pessoas é overkill).
- Releases batched sem necessidade — atrasa entrega.
- Feature flags sem mecanismo de remoção — viram dívida.
- Sem mecanismo de rollback fácil.

**Mini-checklist:**
- [ ] Modelo de branching simples e claro.
- [ ] Deploy automático para homologação a cada merge.
- [ ] Deploy para produção: automatizado, possivelmente gated por aprovação.
- [ ] Estratégia de rollback documentada.
- [ ] Feature flags: ferramenta e política de remoção quando não mais necessárias.

---

## Quando seu ADR não cabe em nenhum tipo

Tipos são guia, não camisa de força. Se sua decisão não cabe limpa em um tipo:

- Pergunte: ela combina elementos de tipos diferentes? Use o tipo predominante e mencione os outros.
- É uma decisão **meta**? (Ex: "vamos revisar o princípio X" — ADR `type: meta`.)
- É um **enabling decision** que destrava várias outras? Continua sendo ADR — só descreva a abrangência.

---

## Resumo: como escolher o tipo

| Sua decisão é sobre… | Tipo provável |
|---|---|
| Que linguagem/framework/runtime usar | Stack |
| Quantos processos, monolito vs separação, sync vs async | Topológico |
| Formato de API, protocolo, payload, versionamento | Contrato |
| Modelo de dados, multi-tenancy, migrations, extensões Postgres | Persistência |
| Provedor cloud, IaC, ambientes, rede | Infra |
| Logs, métricas, alertas, tracing | Observabilidade |
| Branching, deploy, feature flags, releases | Política de evolução |
| Revisar este próprio documento ou um princípio | `type: meta` |
