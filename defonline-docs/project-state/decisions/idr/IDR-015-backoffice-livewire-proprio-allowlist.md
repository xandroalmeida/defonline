---
idr_id: IDR-015
slug: backoffice-livewire-proprio-allowlist
title: Backoffice em telas Livewire próprias (sem Filament), com allowlist em config e middleware `backoffice`
status: accepted
decided_at: 2026-11-03
decided_by: programador (claude-opus-5-5)
related_stories: [STORY-065]
related_pdrs: [PDR-011]
related_adrs: [ADR-001, ADR-003]
related_idrs: [IDR-009]
---

# IDR-015 — Backoffice em Livewire próprio, allowlist em config

## Contexto

A STORY-065 (PDR-011) pede uma lista só-leitura de usuários, restrita à equipe. A ADR-001 cita o Filament como opção futura para o back-office e deixa a escolha para uma IDR.

## Decisões

1. **Telas Livewire próprias, sem Filament.** Hoje o escopo é uma tabela com busca e paginação. O Filament traria um painel inteiro (outro tema, outro auth guard e um pacote grande para manter atualizado) e nenhum ganho para uma tela só. A tela usa o shell do app (`components.layouts.app`) e os componentes do design system. Reavaliar quando o backoffice ganhar CRUD (escopo v1.2, §6.1).
2. **Allowlist em `config/backoffice.php`**, vinda de `BACKOFFICE_EMAILS` (separada por vírgula, normalizada em minúsculas). Vazia bloqueia todo mundo. Sem papéis no banco: o PDR-011 não pede gestão de papéis.
3. **Middleware `backoffice`** (`App\Http\Middleware\SomenteEquipeBackoffice`), depois de `auth`, sobre o grupo `/backoffice/*`, com o nome de rota `backoffice.*`. Quem está fora da lista recebe 404 (IDR-009). Novas telas do backoffice entram nesse grupo.
4. **Leitura cross-tenant por subquery em `DB::table`**, e não por relação Eloquent. `EmpresaAnalisada` e `Diagnostico` têm o Global Scope de tenant, que filtraria pelo membro da equipe logado. As contagens leem as tabelas direto (excluindo soft-deleted), o que mantém a página em número fixo de queries.

## Consequências

- Adicionar alguém à equipe exige alterar `BACKOFFICE_EMAILS` no `.env.prod` cifrado e fazer redeploy.
- Se o backoffice crescer para escrita, uma IDR/ADR nova reavalia o Filament e os papéis.
