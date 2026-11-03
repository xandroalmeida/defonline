---
story_id: STORY-065
slug: backoffice-lista-usuarios
title: Backoffice — lista de usuários cadastrados (só-leitura, restrita à equipe)
epic_id: EPIC-011
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: in_review
owner_agent: programador (claude-opus-5-5)
created_at: 2026-11-03
updated_at: 2026-11-03
estimated_session_size: M
---

# STORY-065 — Backoffice: lista de usuários

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause.

## Contexto

Hoje não existe área administrativa. A equipe só vê quem se cadastrou consultando o banco. O PDR-011 antecipa uma fatia mínima, só-leitura, do backoffice previsto para a v1.2.

- Ler ANTES: `defonline-docs/project-state/decisions/pdr/PDR-011-backoffice-minimo-somente-leitura.md`, `defonline-docs/project-state/decisions/idr/IDR-009-cross-tenant-403-vs-404.md`, `defonline-docs/project-state/database-schema.md` (tabelas `usuarios`, `empresas_analisadas`, `diagnosticos`, `audit_logs`).

## O quê

Uma área interna (ex.: `/backoffice/usuarios`) com a lista de usuários cadastrados, acessível só por membros da equipe.

## Por quê

Permite acompanhar o beta e dar suporte sem abrir o banco de produção.

## Critérios de aceite

- [ ] **CA-1. Acesso.** Só um usuário logado cujo e-mail está na allowlist da equipe (configuração por ambiente, sem tela de gestão) acessa a área. Anônimo vai para o login. Um usuário logado fora da allowlist recebe **404** (IDR-009), e a área não aparece em nenhum menu para ele. A allowlist vazia ou ausente bloqueia todo mundo.
- [ ] **CA-2. Colunas.** Nome, e-mail, CPF mascarado (`***.456.789-**`), telefone, e-mail confirmado (sim/não + data), data de cadastro, último login (do audit log `usuario.login_sucesso`; "—" se nunca logou), nº de empresas e nº de diagnósticos.
- [ ] **CA-3. Situação.** Usuário excluído (`deleted_at`) aparece marcado como "Excluído". Usuário anonimizado (`anonimizado_at`) aparece como "Anonimizado", sem nenhum dado pessoal.
- [ ] **CA-4. Navegação.** Ordenação padrão: cadastro mais recente primeiro. Busca por nome ou e-mail (parcial, sem diferenciar maiúsculas). Paginação de 50. Total de usuários (e quantos confirmaram o e-mail) no topo.
- [ ] **CA-5. Só-leitura.** Não há nenhuma ação de escrita. A tela não faz N+1: o número de queries não cresce com o tamanho da página (teste cobre).
- [ ] **CA-6. Auditoria.** Cada acesso gera audit log `backoffice.usuarios_listados` com o ator e o termo de busca, sem PII de terceiros. Nada de PII em logs de aplicação.
- [ ] **CA-7. E2E.** Dusk: membro da equipe vê a lista e busca por e-mail; um usuário comum recebe 404 na mesma URL.

## Fora de escopo

Lista de empresas (próxima estória do EPIC-011); detalhe do usuário; exportação CSV; qualquer escrita (editar, bloquear, excluir, reenviar e-mail, impersonar); papéis/permissões gerenciáveis.

## Padrões de qualidade exigidos

Segue `defonline-docs/skills/po/references/quality-standards.md`: cobertura ≥ 80% (≥ 98% na regra de acesso), E2E do CA-7, PII mascarada.

## Dependências

- **Bloqueada por:** nada.
- **Design:** sem spec própria. Usar os componentes do design system v1 (EPIC-004), com layout de tabela simples.

## Decisões já tomadas (não as reabra)

PDR-011 (só-leitura, allowlist, 404), IDR-009 (404 para acesso indevido), ADR-003 (audit). A ferramenta (Filament ou telas Livewire próprias) é escolha do Programador e deve ser registrada em IDR.

## Definição de Pronto (DoD)

- [ ] CAs passam; `scripts/verificar.sh` verde.
- [ ] Variável da allowlist adicionada ao `.env.prod` cifrado (`scripts/segredos.sh push` + commit de `secrets/`).
- [ ] Deploy em homologação verificado: equipe vê a lista e uma conta comum recebe 404.
- [ ] `index.json` atualizado; "Notas do agente" preenchidas.

## Protocolo do agente (obrigatório)

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
- Telas Livewire próprias, sem Filament; allowlist `BACKOFFICE_EMAILS` → `config/backoffice.php`; middleware `backoffice` (404). Detalhes no IDR-015.
- Busca por envio de formulário, e não por digitação ao vivo: cada busca gera exatamente uma linha de audit (CA-6). O termo também vai na URL (`?q=`).
- A data de confirmação do e-mail aparece só como data (sem hora) por causa da descoberta abaixo.

### Descobertas
- **[BUG pré-existente, fora do escopo] fuso nas colunas `timestamptz` gravadas pelo app.** A sessão do Postgres roda em `America/Sao_Paulo` e o Laravel grava `now()` (UTC) sem offset. Por isso `email_confirmed_at` (e outras `timestamptz` preenchidas pelo PHP) fica 3h no futuro. As colunas com default do banco (`audit_logs.occurred_at`) estão corretas. Já está documentado em `RfbConsultarCnpj` para `business_metrics`. Proposta ao PO: estória para fixar o `timezone` da conexão `pgsql` em UTC e corrigir os dados.
- Uma relação Eloquent para `EmpresaAnalisada`/`Diagnostico` aplicaria o Global Scope do ator (o membro da equipe), por isso as contagens usam subquery em `DB::table`.

### Bloqueios encontrados
- Nenhum.

### IDRs criados
- IDR-015.
### Cobertura final
- `scripts/verificar.sh` em 2026-11-03: Pint e Larastan limpos; Pest com 1068 testes verdes; cobertura geral de 97,9% e de 99,7% em `app/Domain`; `SomenteEquipeBackoffice`, `Livewire/Backoffice/Usuarios` e `Models/Usuario` com 100%; Dusk com 37 testes verdes; gitleaks e trivy limpos.

### Pendências do DoD
- ~~Allowlist em `.env.prod`~~: feito em 2026-11-03, com `BACKOFFICE_EMAILS=xandroalmeida@gmail.com` (admin definido pelo PO), também no `app/.env` local.
- Deploy em homologação (release) e verificação com uma conta da equipe e uma conta comum.

### Links de evidência
- Testes: `app/tests/Feature/Livewire/Backoffice/UsuariosTest.php` (CA-1 a CA-6) e `app/tests/Browser/BackofficeUsuariosBrowserTest.php` (CA-7).
