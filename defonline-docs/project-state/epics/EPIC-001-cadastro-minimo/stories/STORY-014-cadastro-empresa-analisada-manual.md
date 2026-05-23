---
story_id: STORY-014
slug: cadastro-empresa-analisada-manual
title: Cadastro de Empresa Analisada por preenchimento manual (sem RFB)
epic_id: EPIC-001
sprint_id: SPRINT-2026-W22
type: implementation
target_role: programador
status: in_review
owner_agent: programador (claude-opus-4-7)
created_at: 2026-05-22
updated_at: 2026-05-23
estimated_session_size: M
---

# STORY-014 — Cadastro de Empresa Analisada por preenchimento manual (sem RFB)

> **Para o agente programador:** esta estória entrega o cadastro **funcional** da Empresa Analisada digitando os campos. O enriquecimento via API RFB vem na STORY-015 e empilha em cima do form que você cria aqui. Modele o form pensando que ele vai ganhar um "consultar CNPJ" depois — o mesmo form serve para a fallback.

## Contexto (por que esta estória existe)

Roberto precisa cadastrar a marcenaria — sem isso, não há sujeito para o quiz do EPIC-002 nem para o histórico do EPIC-003. A espec exige dois caminhos: CNPJ enriquecido por RFB **e** preenchimento manual quando a RFB falha. Esta estória entrega o **caminho manual** primeiro, porque:

1. É o fallback obrigatório do caminho RFB (NRF §3.1 — "Falha na API não impede o cadastro").
2. Cobre o caso de autônomo (CPF da Empresa Analisada — espec §1.5.2 nota terminológica).
3. Destrava a STORY-015 (que adiciona pré-preenchimento sem reescrever o form).

- Épico: `epics/EPIC-001-cadastro-minimo/epic.md`
- Documentos canônicos:
  - `especificacao-funcional.md` §1.5.2 (entidade Empresa Analisada), §3.3 Passo 2 (Cadastro da primeira Empresa)
  - `requisitos-nao-funcionais-e-juridicos.md` §3.1 (RFB fallback robusto)
  - ADR-003 — multi-tenancy via FK + Global Scope (Empresa pertence a UM Usuário, FK + Policy)
  - ADR-004 — emissão futura de `empresa_cadastrada` (event entra na STORY-016, mas a entidade nasce aqui)

## O quê (objetivo desta estória)

Implementar form Livewire `/empresas/nova` (autenticado, Usuário logado) que aceita preenchimento manual de uma Empresa Analisada. Cria registro persistido vinculado ao Usuário logado via FK. Redireciona para `/empresas/{id}` mostrando os dados confirmados.

## Por quê (valor para o usuário)

Para Roberto: "agora a marcenaria existe no DEFOnline e tem identidade própria — separada de mim como pessoa." Para a EBP: a entidade alvo de todas as análises futuras está modelada e persistida corretamente.

## Critérios de aceite

- [ ] **CA-1:** Migration cria tabela `empresas_analisadas` com colunas mínimas: `id`, `usuario_id` (FK obrigatória, índice), `tipo_documento` (enum `'cnpj' | 'cpf'`), `documento` (string com normalização — só dígitos), `razao_social` (text), `nome_fantasia` (text nullable), `cnae` (string nullable; código de 7 dígitos), `municipio` (string), `uf` (char(2)), `situacao_cadastral` (enum `'ativa' | 'inapta' | 'baixada' | 'suspensa' | 'nao_informada'`; default `'nao_informada'`), `data_fundacao` (date nullable), `fonte_enriquecimento` (enum `'manual' | 'rfb'`; **manual** nesta estória — RFB entra na STORY-015), `enriquecido_at` (timestamp nullable), `created_at`, `updated_at`, `deleted_at` (soft delete — ADR-003). Índice único parcial em `(usuario_id, documento)` (cada Usuário pode ter no máximo 1 empresa com mesmo documento; outros Usuários podem ter mesma empresa — multi-tenancy).
- [ ] **CA-2:** Rota autenticada `GET /empresas/nova` renderiza form Livewire com campos: tipo_documento (radio CNPJ/CPF), documento (mascarado conforme tipo), razão_social, nome_fantasia, CNAE (com helper "código de 7 dígitos, opcional"), município, UF (dropdown 27 estados), situação cadastral (dropdown), data de fundação (date picker, opcional). Submit válido cria registro com `fonte_enriquecimento = 'manual'` e `enriquecido_at = null`, redireciona para `/empresas/{id}`.
- [ ] **CA-3:** Validações: documento conforme tipo (CNPJ 14 dígitos válido com DV; CPF 11 dígitos válido com DV); razão social obrigatória (mínimo 2 caracteres); município obrigatório; UF obrigatória (lista fixa válida); CNAE opcional mas se preenchido valida formato `\d{7}` ou `\d{4}-?\d/?\d{2}` (Você decide normalizar para 7 dígitos puros); data de fundação opcional, se preenchida não pode ser futura. Erros voltam ao form com mensagens por campo.
- [ ] **CA-4:** **Multi-tenancy via Global Scope** (ADR-003): qualquer query em `EmpresaAnalisada::query()` em contexto de Usuário autenticado retorna apenas as empresas do próprio Usuário. Bypass do scope só via método explícito (`->withoutGlobalScope()`) — para uso administrativo futuro, não nesta estória. Policy `EmpresaAnalisadaPolicy` nega `view/update/delete` se `empresa.usuario_id !== auth()->id()`. Tentativa cross-tenant retorna 403.
- [ ] **CA-5:** Rota autenticada `GET /empresas/{empresa}` (com Route Model Binding + Policy `view`) renderiza tela read-only com todos os campos preenchidos, badge "Fonte: preenchimento manual" e botão "Voltar para Minhas Empresas" (a tela "Minhas Empresas" entra na STORY-016 — pode ser link inerte aqui). **Não há edição nesta onda** (declarado no epic — "Edição ou exclusão de Empresa Analisada (visualização apenas)").
- [ ] **CA-6:** Cadastro de Empresa gera entrada em `audit_logs` (`action: 'empresa.cadastrada'`, contendo `empresa_id`, `tipo_documento`, `fonte_enriquecimento`, **sem documento em log** — documento é PII). Tentativa de acesso cross-tenant que cai em 403 gera `audit_logs.action = 'empresa.acesso_negado'`.
- [ ] **CA-7:** Testes: UnitPure para validadores de CPF/CNPJ (DV), Feature cobrindo CA-1 a CA-6 (criação, validações, multi-tenancy bloqueando cross-tenant, soft delete não impactando criação de empresa com mesmo documento por outro Usuário, audit log gerado, sem PII em log), 1 Dusk percorrendo `cadastrar empresa manualmente → ver tela read-only`. Cobertura ≥ 80%. Se você isolar lógica de validação de documento em `app/Domain/Documentos/` ou similar, **gate de 98%** dispara automaticamente para essa pasta (STORY-010 já cabeou `phpunit-domain.xml`).

## Fora de escopo

- **Pré-preenchimento via API RFB** — STORY-015.
- **Edição de empresa** — fora do MVP (epic declara).
- **Exclusão de empresa** — fora do MVP (mas soft delete está na coluna — operacional via admin/Tinker no MVP se necessário).
- **Cadastrar várias empresas no MVP** — modelo permite, UI da onda 1 expõe uma. Tela "Minhas Empresas" lista a única empresa (STORY-016); botão "Adicionar empresa" fica para roadmap pós-v1.
- **Compartilhamento entre Usuários (N:M)** — roadmap pós-v1 (espec §1.5.2).

## Padrões de qualidade exigidos

- Cobertura ≥ 80% geral; ≥ 98% em validadores de DV de documento (se isolar em `app/Domain/**`).
- Documento (CNPJ/CPF) é PII — mascarado em log; nunca em evento de produto.
- Multi-tenancy é **segurança**, não conveniência — falha de Global Scope é bug crítico (não-bloqueante se pego em validação, bloqueante em produção).
- E2E Dusk no fluxo de cadastro.

## Dependências

- **Bloqueada por:** STORY-011 (precisa de Usuário autenticado).
- **Bloqueia:** STORY-015 (enriquecimento RFB layered em cima deste form); STORY-016 (lista depende da entidade existir).
- **Pré-requisitos de ambiente:** mesmos do EPIC-000.

## Decisões já tomadas

- **ADR-003** — multi-tenancy FK + Global Scope + Policy (não RLS).
- **Espec §1.5.2** — Empresa Analisada não tem login; CPF da Empresa Analisada (autônomo) ≠ CPF do Usuário do passo 1.
- **Espec §3.3** — pré-preenchimento RFB com fallback manual (fallback é o que esta estória entrega).
- **Soft delete + anonimização T+30d** (ADR-003) — a coluna está aqui; a anonimização entra quando exclusão de conta for implementada.

## Liberdade técnica do agente

Você decide:
- Estrutura de pastas (sugestão: `app/Models/EmpresaAnalisada`, `app/Livewire/Empresa/Cadastrar`, `app/Policies/EmpresaAnalisadaPolicy`, `app/Domain/Documentos/{Cnpj,Cpf}.php` se quiser modelar valor — bom candidato a `app/Domain/**` para o gate de 98%).
- Como modelar enum (PostgreSQL nativo, PHP enum + cast Eloquent).
- Helper de máscara de documento no front (Livewire).
- Quais 27 UFs aparecem na dropdown (lista fixa, hardcoded ou tabela — sua escolha; lista fixa é suficiente).

Você **não** decide:
- Que tem multi-tenancy via Global Scope (ADR-003).
- Que documento é PII (LGPD + arquitetura).
- Que cada Usuário tem N empresas no banco mas UI expõe 1 nesta onda (epic).
- Que campos existem (espec §3.3).

## Definição de Pronto (DoD)

- [x] CAs passam (CA-1..CA-7 cobertos por Pest + Dusk, suíte verde local).
- [x] Pre-push verde (Pint + Larastan 0 erros + Pest 185 testes / 574 asserções + Pest Domain 100% / gate 98% + Pennant + Dusk 9 testes / 51 asserções).
- [x] Pipeline CI verde (run 26332847355, v0.4.0-rc.2 — validate 10 jobs + build + deploy + smoke + notify).
- [ ] Deploy em homologação validado: cadastrar uma empresa manualmente (CNPJ + CPF dois casos), ver tela read-only, tentar acessar empresa de outro Usuário (cross-tenant) e ver 403 — aguarda smoke manual do PO.
- [ ] `index.json` `done` — após validação em homologação.
- [x] "Notas do agente" preenchidas.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`.

## Notas do agente

### Documentos lidos (2026-05-23)
- STORY-014 inteira (frontmatter, contexto, CA-1..CA-7, fora de escopo, decisões, DoD, protocolo).
- Espec V2 §1.5.2 (entidade Empresa Analisada) e §3.3 (cadastro em 2 passos).
- NRF §3.1 (RFB fallback) e §4.3 (multi-tenancy + isolamento).
- ADR-003 (FK + Global Scope + Policy + soft delete).
- ADR-004 (eventos de produto — fora desta estória, mas a entidade nasce aqui).
- `programador/SKILL.md`, `reading-discipline.md`, `testing-discipline.md`, `database-discipline.md`.
- Código existente: `App\Domain\Cpf`, `App\Models\Usuario`, `App\Livewire\Cadastro`, `App\Observabilidade\AuditLogger`, migrations existentes, factories e padrões de testes (Pest + Dusk).

### Entendimento consolidado
- Form Livewire `/empresas/nova` (autenticado) cria `EmpresaAnalisada` com `fonte_enriquecimento='manual'` para o Usuário logado. Tela read-only em `/empresas/{empresa}` com Policy.
- Domínio puro de validação CNPJ vive em `app/Domain/Cnpj.php` (dispara o gate ≥98% de `phpunit-domain.xml`); CPF reutiliza `app/Domain/Cpf.php`.
- Multi-tenancy via Global Scope no model + Policy. Cross-tenant na rota `/empresas/{empresa}` precisa retornar **403** com audit log (CA-4/CA-6/DoD), por escolha explícita da estória — diverge de ADR-003 §Decisão 1 e NRF §4.3, que prescrevem 404 (ver "Decisões tomadas" abaixo).
- Audit logs: `empresa.cadastrada` (sem documento — PII) e `empresa.acesso_negado` para tentativa cross-tenant.
- Testes: UnitPure para `Cnpj`, Feature cobrindo CA-1..CA-6, Dusk para cadastrar manualmente → ver read-only.

### Plano em 5 bullets
1. Domain `App\Domain\Cnpj` (normalizar/valido/formatar) + UnitPure test ≥98%.
2. Migration `empresas_analisadas` com FK + enums + índice único parcial `(usuario_id, documento)` + soft delete.
3. Model `EmpresaAnalisada` com Global Scope `BelongsToUsuarioScope`, factory, enums; Policy `EmpresaAnalisadaPolicy` (view/update/delete); registro do policy via Laravel autodiscover + AppServiceProvider.
4. Livewire `App\Livewire\Empresa\Cadastrar` + view + rota `/empresas/nova`; Controller `EmpresaController@show` (route model binding sem global scope + `authorize('view')` → 403 com audit log) + rota `/empresas/{empresa}`; menu/link "Voltar para Minhas Empresas" inerte.
5. Suite de testes: UnitPure Cnpj; Feature: criação CNPJ + CPF, validações, multi-tenant 403, soft delete + outro usuário pode cadastrar mesmo documento, audit log sem documento, audit log acesso_negado; Dusk: cadastrar manualmente → ver tela read-only. Rodar Pint + Larastan + Pest (com cobertura) + Dusk.

### Decisões tomadas
- 2026-05-23 — **403 vs 404 (não bloqueante; sigo a estória)**. STORY-014 CA-4/CA-6/DoD prescrevem **403** em acesso cross-tenant + emissão de audit log `empresa.acesso_negado`. NRF §4.3 e ADR-003 §Decisão 1 prescrevem **404** (para não vazar existência). Sigo a estória como contrato local, mas registro para o PO decidir: (a) alinhar ADR-003/NRF para 403 + audit em rotas detail/edit/delete autenticadas; ou (b) corrigir a estória para 404 + audit silencioso. Implemento 403 nesta estória. Listagem (Global Scope) continua sem leak — só o detail/edit/delete responde 403.
- 2026-05-23 — Validador de CNPJ vai em `app/Domain/Cnpj.php` (espelhando `Cpf.php`). Gate ≥98% sobre `app/Domain/**`.
- 2026-05-23 — Enums PHP (`TipoDocumento`, `SituacaoCadastral`, `FonteEnriquecimento`) com `string` backing + cast Eloquent. PG armazena `text` com CHECK (mais simples que `CREATE TYPE` que tem dor em alterações futuras).
- 2026-05-23 — Lista de UFs hardcoded em `app/Domain/Uf.php` (enum) — 27 valores, simples, sem precisar tabela.
- 2026-05-23 — Documento normalizado para dígitos puros antes de gravar — exatamente como `Usuario.cpf`.

### Descobertas
- 2026-05-23 — `LogSanitizer` (ADR-003/004) mascara `cpf`/`cnpj` por nome de chave, mas não `documento` — campo canônico da `EmpresaAnalisada`. Solução: o `Log::info('empresa.cadastrada', …)` **não inclui** o campo `documento` (PII fica em `empresas_analisadas` + `audit_logs` jurídico). Se essa convenção pegar tração, futuro programador pode estender `LogSanitizer::SENSITIVE_KEYS` com `'documento' => 'cnpj'|'cpf'` polimórfico — fora de escopo aqui.
- 2026-05-23 — Índice único `(usuario_id, documento)` precisa ser **parcial** (`WHERE deleted_at IS NULL`); senão soft delete bloqueia recadastro mesmo após excluir. Coberto por teste explícito.
- 2026-05-23 — Laravel 13 com atributos PHP: `#[ScopedBy([...])]` + `#[Fillable([...])]` substituem `$fillable`/`booted` — adotei para consistência com `Usuario` (que usa `#[Fillable]`/`#[Hidden]`).
- 2026-05-23 — `Scope<TModel>` exige assinatura `Builder<covariant TModel>` no `apply()` para satisfazer Larastan nível 6. Documentado no PHPDoc do scope.

### Bloqueios encontrados
- (nenhum)

### IDRs criados
- (nenhum — todas as decisões locais e cabem no PR/Notas)

### Cobertura final
- Geral: **97.2%** (PCOV, 185 testes Pest, 574 asserções)
- `app/Domain/**`: **100%** (gate 98% atendido)
- Dusk E2E: 9 testes (51 asserções) — `CadastroEmpresaBrowserTest` + 8 pré-existentes verdes

### Links de evidência
- PR: trunk-based (commits direto em main)
- Pipeline: https://github.com/xandroalmeida/defonline/actions/runs/26332847355 (verde)
- Tag rc.N: v0.4.0-rc.2 (rc.1 já existia no remote apontando para a STORY-013; pulamos para rc.2 para evitar force-push)
- Homologação: https://defonline.xandrix.com.br/empresas/nova (302 para /login — esperado)
