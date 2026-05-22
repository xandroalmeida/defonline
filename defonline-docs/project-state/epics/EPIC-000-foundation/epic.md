---
epic_id: EPIC-000
slug: foundation
title: Foundation técnica do projeto pós-reset
wave: WAVE-2026-01
status: ready
owner_role: po
created_at: 2026-05-20
updated_at: 2026-05-22
target_completion: 2026-06-24
---

# EPIC-000 — Foundation técnica do projeto pós-reset

## Por que existimos (problema do usuário)

Não é um épico de funcionalidade. É a fundação técnica que **todos os outros épicos da WAVE-2026-01 exigem para existir**. Sem pipeline em verde, ambiente de homologação acessível e ADRs essenciais ratificadas, qualquer trabalho subsequente do time de implementação fica fingindo: código sem destino reprodutível, deploy artesanal, decisões arquiteturais implícitas que apodrecem ao longo do projeto.

O "usuário" deste épico é o próprio time de implementação (PO + Arquiteto + Programador + Validador). O valor entregue é: a partir daqui, qualquer estória futura tem um trilho técnico em que rodar — pre-push hook local cobrando testes pesados → CI leve em verde → tag rc.N dispara deploy automático em homologação → tag semver dispara deploy em produção com gate humano de 1 clique. Observabilidade básica, testes automatizados e ambiente local subindo com um comando.

## Resultado esperado (outcome)

Ao fim deste épico, a criação de uma tag git `vX.Y.Z-rc.N` (release candidate) dispara deploy automático de uma página viva em ambiente de homologação acessível por URL pública (`homolog.defonline.com.br` ou equivalente decidido pelo Arquiteto via ADR), e todas as ADRs essenciais (stack, topologia, persistência, infra, observabilidade, CI/CD) estão `accepted` e referenciadas neste épico. O critério "merge em main = deploy em homologação" foi substituído por "tag rc.N = deploy em homologação" pela ADR-006 — promoção é ato explícito, não efeito colateral de merge.

## Métrica de sucesso (como saberemos que funcionou)

- **Métrica primária:** "tag rc.N faz deploy" — observável: criação da tag `vX.Y.Z-rc.N` aciona deploy automático em homologação em menos de 10 minutos, sem intervenção manual (ADR-006).
- **Métrica de qualidade:** pre-push hook local cobra cobertura de testes (gate 80% geral / 98% núcleo `app/Domain/**` conforme `quality-standards.md §1.1`) e impede `git push` quando vermelho; CI remoto leve bloqueia merge em main quando lint/audit/trivy/gitleaks/build falham.
- **Métrica de fundação técnica:** todas as ADRs candidatas listadas em PDR-001 estão em status `accepted` (ou explicitamente justificadas como "não necessárias para a WAVE-2026-01") no `index.json`.

## Entregável visível no fim do épico

- [ ] Página viva ("hello DEFOnline") rodando em URL pública de homologação, exibindo número da versão deployada e indicador de healthcheck.
- [ ] Repositório de código no estado em que **qualquer agente programador da skill `programador`** consegue subir o ambiente local com um comando documentado e rodar a suíte de testes.
- [ ] Pipeline CI/CD ativo: pre-push hook local cobrando Pest/Dusk/cobertura, e CI remoto (GitHub Actions) bloqueando merges com lint/audit/trivy/gitleaks/build vermelhos (ADR-006).
- [ ] ADRs essenciais publicadas em `decisions/adr/` com status `accepted`.
- [ ] Eventos básicos (`usuario_cadastrado`, `empresa_cadastrada`, `quiz_iniciado`, `diagnostico_concluido`, `diagnostico_visualizado`, `comparativo_aberto`) com instrumentação definida em ADR — sem precisar estar emitindo ainda, mas com o caminho técnico de captação pronto para o EPIC-001 começar a emitir.

## Fora de escopo (explicitamente)

- Qualquer funcionalidade voltada a Usuário final (cadastro, quiz, relatório): essas vivem em EPIC-001 a EPIC-003.
- Decisões de UI/UX: não é o momento. ADR de stack pode ter implicação em framework de front, mas detalhes de design system não.
- Infraestrutura para alta disponibilidade ou autoscaling agressivo: homologação enxuta basta.
- Setup de produção: produção real entra na onda 2 com a comercialização.
- Termo de Adesão e fluxo jurídico de LGPD: entra no EPIC-001 (cadastro).
- Backup e DR formais: ficam para depois de produção real (mas a ADR de hospedagem pode pré-decidir).

## Referências da especificação

- `defonline-docs/especificacao/V2/arquitetura-tecnica.md` — leitura panorâmica obrigatória pelo Arquiteto antes das spikes (regras de negócio preservadas; decisões de stack a serem refeitas).
- `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` — restrições de NFR aplicáveis (uptime, latência, segurança, LGPD, observabilidade).
- `defonline-docs/skills/arquiteto/references/architecture-principles.md` — princípios que regem todas as ADRs.
- `defonline-docs/skills/arquiteto/references/adr-types.md` e `adr-lifecycle.md` — método das ADRs.
- `decisions/pdr/PDR-001-escopo-wave-2026-01.md` — lista de ADRs candidatas e racional de escopo.

## Dependências

- **Bloqueia:** EPIC-001, EPIC-002, EPIC-003 (todos dependem da fundação técnica para começar).
- **Bloqueado por:** nada. Este é o primeiro épico do projeto pós-reset.
- **Decisões arquiteturais necessárias:** todas as candidatas estão em PDR-001 e serão decompostas em spikes individuais no Fluxo B desta sessão. Princípio: uma ADR = uma spike; ADRs heterogêneas vão em spikes separadas executáveis em paralelo.

## Estórias

Decompostas no Fluxo B em 6 spikes paralelizáveis (todas `ready`) + 1 implementação (STORY-007, `draft` até as 6 spikes terem ADRs `accepted`) + 1 estória de ergonomia de dev (STORY-009, `ready` após Phase 1 de STORY-007) + 1 validação (STORY-008, `draft` até STORY-007 e STORY-009 irem para `in_review`).

- [ ] **STORY-001** (spike, arquiteto, `ready`) — Stack (linguagem + framework + runtime + ORM + testes + auth padrão; ratifica PostgreSQL e TDD+E2E).
- [ ] **STORY-002** (spike, arquiteto, `ready`) — Topologia macro do sistema.
- [ ] **STORY-003** (spike, arquiteto, `ready`) — Persistência (migrations, multi-tenancy, audit, LGPD).
- [ ] **STORY-004** (spike, arquiteto, `ready`) — Infra (cloud, IaC, 3 ambientes, Docker, custo).
- [ ] **STORY-005** (spike, arquiteto, `ready`) — CI/CD (pipeline, branching, rollback, gates).
- [ ] **STORY-006** (spike, arquiteto, `ready`) — Observabilidade + captura de eventos de produto.
- [x] **STORY-007** (implementation, programador, `ready`) — Hello world deployado em homologação via pipeline (tag rc.N). Destravada em 2026-05-21: todas as 6 ADRs prerrequisitas (ADR-001 a ADR-006) estão `accepted`.
- [ ] **STORY-009** (implementation, programador, `ready`) — PhpPgAdmin exclusivo do ambiente local de desenvolvimento. Ferramenta de conveniência para o dev local; **nunca** em homologação ou produção (ADR-005 §1.1, §6, §7.5). Paralelizável com Phases 2/3 da STORY-007.
- [ ] **STORY-008** (validation, validador, `draft`) — Validação final do EPIC-000. Promove para `ready` quando STORY-007 e STORY-009 estiverem `in_review`. Ganha item de checklist verificando que PhpPgAdmin não aparece em playbooks Ansible de homol/prod.

## Validação final

Critérios em `validation/checklist.md` (criado no Fluxo B). Relatório do validador em `validation/report.md`.

**Definição de épico concluído:** todas as estórias `done` + relatório de validação `approved` + página hello rodando em homologação + ADRs essenciais `accepted` no índice.

## Histórico

- 2026-05-20 — Criado como draft junto com a abertura da WAVE-2026-01.
- 2026-05-21 — Métrica primária e critérios ajustados pela ADR-006 (`accepted`): tag rc.N substitui merge em main como gatilho de deploy em homologação; pre-push hook local entra no fluxo. STORY-001..006 todas `done`; STORY-007 promovida para `ready`.
- 2026-05-22 — STORY-009 adicionada pelo PO: PhpPgAdmin exclusivo do dev local (ergonomia do desenvolvedor), proibido em homologação e produção. STORY-008 (validação) ganhará um item de checklist verificando a ausência da ferramenta em playbooks Ansible de homol/prod.
