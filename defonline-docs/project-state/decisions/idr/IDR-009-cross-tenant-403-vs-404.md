---
idr_id: IDR-009
slug: cross-tenant-403-vs-404
title: Acesso cross-tenant em rotas autenticadas — 404 silente via Global Scope (sem audit log dedicado)
status: accepted
decided_at: 2026-05-24
decided_by: arquiteto (claude-opus-4-7)
owner_agent: arquiteto (claude-opus-4-7)
related_story: STORY-021
related_stories: ["STORY-014", "STORY-021"]
related_adrs: ["ADR-003"]
related_idrs: []
supersedes: null
superseded_by: null
created_at: 2026-05-24
updated_at: 2026-05-24
---

# IDR-009 — Acesso cross-tenant em rotas autenticadas: 404 silente

## Contexto

A STORY-014 (cadastro de Empresa Analisada — done) prescreveu em CA-4/CA-6/DoD que acesso cross-tenant à rota `GET /empresas/{id}` retornasse **HTTP 403** + entrada em `audit_logs` com `action: 'empresa.acesso_negado'`. O Programador implementou exatamente isso em [EmpresaController::show](app/app/Http/Controllers/EmpresaController.php:29-67), bypassando o Global Scope com `withoutGlobalScope(BelongsToUsuarioScope::class)` para conseguir distinguir "não existe" (404) de "existe e não é seu" (403).

Porém:

- **ADR-003 §Decisão 1 / Opção 1A** (Decisão de multi-tenancy, `status: accepted`) prescreve: _"Quando o resource não pertence ao tenant atual, controller retorna **404** (não 403) — `RNF §4.3`."_
- **NRF §4.3** prescreve: _"Isolamento rígido por `usuario_id` em todas as queries autenticadas — testes automatizados de autorização cruzada (usuário A não acessa dado de usuário B → **404**) obrigatórios em cada endpoint que retorne recurso pertencente a usuário."_

A divergência foi declarada nas notas da STORY-014 ("403 vs 404 não bloqueante; sigo a estória"), listada pelo PO no `index.json`, aceita pelo Validador no item 4.6 do checklist ("Para esta validação, 403 é o critério vigente") e identificada novamente como Observação no `validation/report.md`. Sem IDR formal, o EPIC-002 (que abrirá `/diagnosticos/{id}`, `/relatorios/{id}` e similares) herdaria a inconsistência ou decidiria por omissão.

Esta IDR fecha o ponto **antes** do EPIC-002 ser aberto.

## Decisão

> **Acesso autenticado a rota detail/edit/delete com objeto pertencente a outro tenant retorna HTTP 404 silente, via Global Scope, sem entrada dedicada em `audit_logs`.**

Concretamente:

1. **Route Model Binding obedece o Global Scope** (`BelongsToUsuarioScope`) — nada de `withoutGlobalScope` em controllers de domínio. A query do binding já retorna `null` para objeto de outro tenant; o framework converte em `NotFoundHttpException` (404) automaticamente.
2. **Nenhum audit log dedicado** é emitido para a tentativa cross-tenant. Forense de "tentativa de acesso negado" fica no log estruturado da aplicação (request log já captura `usuario_id`, `request_id`, `ip`, `user_agent`, `path` por padrão — ADR-002 + ADR-004). Audit log jurídico (`audit_logs`) registra **eventos consumados sobre dado do próprio tenant** (CRUD), não tentativas frustradas contra dado alheio.
3. **Policies (`view/update/delete`) seguem como segunda camada de defesa em profundidade** — caso um controller futuro esqueça o Global Scope (ou explicitamente bypasse por motivo administrativo), a Policy ainda autoriza/nega; nesse caso, **lançar `NotFoundHttpException`** (não `AuthorizationException`), preservando a invariante "uma só superfície de resposta para cross-tenant".
4. **Princípio para EPIC-002 e além:** toda nova rota autenticada que receba ID de recurso tenant-scoped via Route Model Binding **não bypassa** o Global Scope. A semântica única "objeto não existe **para você**" cobre os dois casos ("não existe no banco" + "existe em outro tenant") com a mesma resposta 404.

A estória de implementação que aplica esta decisão (refactor do `EmpresaController::show` + revisão do uso de `empresa.acesso_negado` em `audit_logs`) é proposta no fim desta IDR, **não implementada** nesta sessão.

## Por quê

- **Alinhamento com decisões já tomadas.** ADR-003 (decidida pelo Arquiteto, aprovada pelo PO) e NRF §4.3 (canon do projeto, base do RNF) ambas dizem 404. Manter 403 exigiria emitir **dois aditivos** (ADR-003 + NRF) que contrariam o próprio ADR-003 — custo de documentação maior do que o custo de uma única estória de refactor.
- **Princípio anti-enumeração mantido por defesa em profundidade, não por necessidade absoluta.** Os IDs do projeto são **UUID v7** (ADR-003 §Decisão 3 — `Str::uuid7()`), que não são enumeráveis por incremento. O ganho prático de não vazar existência é pequeno _quando o atacante não tem o ID_. Mas:
  - Quando o ID **vaza por outro canal** (link compartilhado, URL em screenshot, cookie de sessão antiga, leak de logs), 404 nega o oracle "esse ID existe no sistema". 403 confirma — o atacante agora sabe que o ID é válido em outra conta e pode pivotar (phishing direcionado, engenharia social).
  - Princípio é de **defesa em profundidade**: o custo é zero (Global Scope já faz isso por padrão), o ganho é não-zero. Aceitar o ganho é gratuito.
- **Custo de manutenção do código atual.** A implementação 403 atual em [EmpresaController::show](app/app/Http/Controllers/EmpresaController.php) precisa:
  - Bypassar Global Scope explicitamente (`withoutGlobalScope(BelongsToUsuarioScope::class)`) — quebra a invariante "Global Scope é universal".
  - Distinguir `find($id) === null` vs `$model->usuario_id !== $usuarioId` manualmente.
  - Emitir audit log manualmente em cada controller (escala mal: 6+ controllers em EPIC-002, cada um precisaria do mesmo boilerplate).
  - 30+ linhas em cada controller só para responder cross-tenant. Com 404 silente, são **0 linhas** — o framework já faz.
- **Precedente para EPIC-002.** EPIC-002 vai abrir `/diagnosticos/{id}`, `/relatorios/{id}`, possivelmente `/quizzes/{id}` editáveis. Multiplicar o padrão 403 + audit para cada um é dívida de boilerplate desnecessária; padronizar 404 é dívida zero.
- **Auditoria jurídica preservada por outro caminho.** O audit log do ADR-003 §Decisão 4 cobre **operações sobre dado do próprio tenant** (CRUD, exportação, mudança de permissão). Tentativa frustrada de acesso cross-tenant **não consumou nada** — não há "antes/depois" para registrar, não há subject que pertence ao tenant. O sinal forense (alguém com ID X tentou tocar recurso Y em outro tenant) fica no **log estruturado da aplicação** (request log já captura `usuario_id`, `request_id`, `ip`, `user_agent`, `route`, `status=404`), suficiente para incident response. Se o futuro evidenciar necessidade de detecção ativa (correlação de muitos 404s do mesmo IP, ataque de enumeração via brute force de UUID — improvável dado o keyspace), abre-se IDR específica de instrumentação.
- **LGPD não exige audit de tentativa frustrada.** A obrigação legal é registrar **acesso efetivo a dado pessoal** (LGPD Art. 37 §1º — registro de operações de tratamento). Tentativa que retorna 404 sem servir o objeto não é "operação de tratamento" — é miss de roteamento do ponto de vista do dado.

## Alternativas consideradas

### Opção B — Manter 403 + audit log (status quo da implementação atual)

Manteria o código de [EmpresaController::show](app/app/Http/Controllers/EmpresaController.php) como está e exigiria:
- **Aditivo formal em ADR-003 §Decisão 1** rescindindo a sentença "controller retorna 404 (não 403)" e justificando defesa em profundidade.
- **Aditivo formal em NRF §4.3** reescrevendo "→ 404" como "→ 403 + audit log".
- **Adoção do padrão por todas as rotas detail/edit/delete do EPIC-002** — escalando o boilerplate.

**Por que rejeitada:**
- Inverte a hierarquia documental — IDR sobrepondo decisão de ADR + NRF é tecnicamente possível (IDR pode justificar desvio local), mas para um padrão **transversal a 6+ rotas futuras** o lugar certo é amendar o ADR, não emitir IDR-recorrência. E nesse caso o custo de documentação supera o custo de refactor.
- Custo de boilerplate: cada controller precisaria de 20-30 linhas para distinguir 404 de 403 + emitir audit. Multiplicado por 6+ rotas no EPIC-002 = 150+ linhas só de "responder cross-tenant".
- Argumento de "audit jurídico de tentativa" é fraco: log estruturado da aplicação já captura o evento (com mais contexto, inclusive); audit_logs jurídico é desenhado para CRUD do próprio dado, não para tentativa.
- O argumento "se algum dia atacarmos UUIDs por brute force" não justifica manter 403 — justifica detecção ativa em outro lugar (rate-limit por IP, alerta em log).

### Opção C — Híbrido (404 nas GET, 403 nas POST/PUT/DELETE)

Diferenciar pela semântica HTTP: GET 404 (sem leak), mutação 403 (atacante já conhece o ID).

**Por que rejeitada:**
- A premissa ("em mutação o atacante já conhece o ID") **não é universalmente verdadeira**: forms forjados, IDs em hidden inputs vazados, redirect com `id` em querystring — atacante pode tentar PUT/DELETE sem necessariamente ter "confirmado" o ID antes via GET.
- Dupla regra em controllers gera bug de roteamento: programador esquece e mistura semântica. Anti princípio #1 (simplicidade) e #5 (fronteira clara).
- Inconsistência cliente-side: o frontend recebe 404 em GET e 403 em DELETE para a mesma situação semântica — ergonomia ruim.
- A defesa real contra mutação cross-tenant é a Policy + Global Scope, não o código de status — ambos rejeitam antes de aplicar mutação. O código de status é só o que o cliente vê.

### Opção D (criada pelo Arquiteto, registrada e rejeitada) — 404 silente + audit log preservado com action neutro

Variante da Opção A: retornar 404 ao cliente, mas ainda emitir entrada em `audit_logs` com action `auth.cross_tenant_attempt` (ou similar), capturando o `subject_id` que foi tentado.

**Por que rejeitada:**
- Exigiria continuar bypassando o Global Scope em todo controller (para conseguir popular `subject_id` real e ainda assim retornar 404) — anula o ganho de simplicidade da Opção A.
- Polui a tabela jurídica `audit_logs` com eventos que não são "tratamento de dado pessoal" (LGPD), apenas "alguém tentou".
- Mesma capacidade forense já existe no log estruturado (request log), sem novo schema, sem nova convenção.
- Se necessidade aparecer (improvável no MVP), abre-se IDR específica para instrumentação ativa de detecção (rate-limit + alerta em logs).

## Consequências

### Para outros agentes

- **Padrão transversal para rotas autenticadas com Route Model Binding tenant-scoped:** confiar no Global Scope; **não usar `withoutGlobalScope(BelongsToUsuarioScope::class)`** em controllers de domínio. Bypass do scope continua reservado para uso administrativo futuro (não MVP) e, mesmo lá, exige IDR específica.
- **Quando Policy negar `view/update/delete`** em controller que (por motivo legítimo, ex.: admin) bypassou o scope: lançar `NotFoundHttpException`, **não** `AuthorizationException`. Mesma superfície de resposta.
- **Audit log (`AuditLogger::log`)** continua para eventos consumados (CRUD, login, exportação, mudança de permissão — ADR-003 §Decisão 4). **Não** introduzir nova action para "tentativa negada cross-tenant" sem nova IDR.
- **Testes de autorização cruzada** continuam obrigatórios em cada endpoint (NRF §4.3 + §9; STORY-014 CA-7) — apenas a asserção muda: `->assertStatus(404)` em vez de `->assertStatus(403)`.

### Para o projeto

- **Reduz dívida de documentação:** ADR-003 §Decisão 1 e NRF §4.3 permanecem como estão (404). Esta IDR é a fonte canônica que documenta a divergência da STORY-014 como **erro local da estória** (não da decisão).
- **Estória de correção sugerida ao PO (CA-3 de STORY-021):** refactor de [EmpresaController::show](app/app/Http/Controllers/EmpresaController.php) para remover `withoutGlobalScope`, remover bloco de emissão de `empresa.acesso_negado`, simplificar para route binding padrão; e revisão de `audit_logs` em homologação para decidir se entradas históricas com `action='empresa.acesso_negado'` ficam (legado, não anonimizadas — append-only) ou se a action é rebaixada/anotada como descontinuada em documento. Sugestão de tamanho: S (~30min). **Não** entra na presente IDR; entra como estória nova no próximo sprint (W24 ou W25 a critério do PO).
- **Testes da STORY-014** que assertam `assertStatus(403)` em cross-tenant precisam ser atualizados para `assertStatus(404)` na mesma estória de refactor.

### Trade-offs aceitos

- **Perda do sinal forense formal em `audit_logs`.** Aceitável: o log estruturado captura o mesmo evento com mais contexto e é a fonte natural para detecção. Se for preciso transformar isso em alerta ativo (e.g., "mais de 5 cross-tenant 404 do mesmo IP em 5min"), abre-se IDR de observabilidade.
- **Cliente não distingue "ID não existe no sistema" de "ID existe mas não é seu".** É o ponto. Aceitável e desejado (defesa em profundidade).
- **Bug de roteamento que devolva 404 para recurso do próprio tenant** vira indistinguível do "objeto não existe" para o usuário leigo. Mitigação: testes de feature por endpoint asseguram o caminho feliz (`actingAs($dono)` → 200). É o mesmo trade-off do default Laravel.

## Como verificar

- **Pest feature test arquitetural** (sugestão para próxima estória de refactor): asserta que `EmpresaController::show` (e qualquer controller futuro tenant-scoped) **não chama** `withoutGlobalScope(BelongsToUsuarioScope::class)`. Pode ser via Larastan custom rule ou via teste de estrutura (`Pest\Arch`).
- **Pest feature test por endpoint** (já existe na STORY-014; precisa ajuste): cross-tenant em `GET /empresas/{id}` → `assertStatus(404)`. Replicar para todo endpoint tenant-scoped do EPIC-002 quando ele nascer.
- **Smoke pós-deploy** continua chamando endpoints próprios (não cross-tenant) — não muda.
- **Sinais de revisão (quando reabrir esta IDR):**
  1. Incidente real onde 404 silente atrasou investigação porque não havia entrada em `audit_logs` para correlacionar.
  2. Auditoria externa (DPO ou similar) exigir audit_logs jurídico de tentativa frustrada — improvável dado o framework legal, mas registrado.
  3. Migração para IDs incrementais (BIGSERIAL) em alguma entidade — não previsto, contradiz ADR-003 §Decisão 3.
  4. Detecção ativa de ataque de enumeração se tornar requisito — abrir IDR de observabilidade, não reabrir esta.

## Tipo

- [x] **Padrão transversal**: define o comportamento default para todas as rotas autenticadas tenant-scoped detail/edit/delete do projeto.
- [ ] **Workaround**: contornar bug/limitação documentado.
- [ ] **Convenção interna**: padrão de código local que precisa ser seguido.
- [ ] **Otimização**: mudança feita por motivo de performance.
- [ ] **Refatoração estrutural**: mudança que afeta vários módulos por motivo de qualidade.

---

## Histórico

- 2026-05-24 — criada como `accepted` pelo Arquiteto (claude-opus-4-7) em sessão de SPIKE (STORY-021). Decisão tomada considerando 3 opções da estória + 1 opção autoral (Opção D, rejeitada). Saída: **Opção A — 404 silente via Global Scope, sem audit log dedicado**. Estória de refactor de [EmpresaController::show](app/app/Http/Controllers/EmpresaController.php) e revisão de `audit_logs.action='empresa.acesso_negado'` proposta ao PO como follow-up (não implementada nesta sessão).
