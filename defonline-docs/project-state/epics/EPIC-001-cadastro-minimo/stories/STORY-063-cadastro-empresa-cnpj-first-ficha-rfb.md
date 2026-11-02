---
story_id: STORY-063
slug: cadastro-empresa-cnpj-first-ficha-rfb
title: Cadastro de empresa pelo CNPJ — ficha completa da Receita, só-leitura, com observações do usuário
epic_id: EPIC-001
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-11-02
updated_at: 2026-11-02
estimated_session_size: L
---

# STORY-063 — Cadastro de empresa pelo CNPJ: ficha completa da Receita

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto (por que esta estória existe)

Hoje `/empresas/nova` abre um formulário de 9 campos. "Consultar Receita" é opcional, pré-preenche 7 campos e o usuário pode sobrescrever tudo. O PDR-010 inverte isso. O usuário informa **só o CNPJ**, o sistema traz a ficha completa da RFB e o usuário confirma. Dado oficial não é editável, e o usuário pode acrescentar observações próprias.

- Épico: `defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/epic.md` (evolução pós-fechamento; o épico não reabre)
- Ler ANTES de codificar:
  - `defonline-docs/project-state/decisions/pdr/PDR-010-cadastro-empresa-cnpj-first-dados-rfb-imutaveis.md` (**a regra**; prevalece sobre a espec onde conflitar)
  - `defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/stories/STORY-014-cadastro-empresa-analisada-manual.md`, `STORY-015-enriquecimento-rfb-mock-fallback.md` e `STORY-018-rfb-providers-reais-cnpja-receitaws.md` (o que existe hoje)
  - `defonline-docs/project-state/decisions/idr/IDR-004-rfb-provider-abstraction-cnpja-receitaws.md`, `IDR-005-rfb-provider-default-producao-cnpja.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §1.5.2 e §3.3 Passo 2 (para CPF continuam valendo)
  - `defonline-docs/project-state/database-schema.md`

## O quê

Reescrever o fluxo de `/empresas/nova` para começar pelo CNPJ. A consulta à RFB passa a trazer a ficha completa: CNAEs secundários, endereço, sócios e regime MEI/Simples. A ficha é persistida e exibida só-leitura no cadastro e em `/empresas/{id}`, com um campo de observações editável pelo usuário.

## Por quê

Ter a identidade da empresa confiável é pré-requisito de um diagnóstico confiável. O regime MEI/Simples é o recorte do DRM (EPIC-010). E o cadastro por CNPJ cai de 9 campos digitados para 1 campo e uma confirmação, o que pesa na métrica de conclusão de cadastro do EPIC-001.

## Critérios de aceite

- [ ] **CA-1 — Só CNPJ na entrada.** Ao abrir `/empresas/nova`, a tela mostra apenas o campo CNPJ (com máscara) e a ação "Consultar". Nenhum outro campo de dados da empresa aparece antes da consulta.
- [ ] **CA-2 — Validação local.** Um CNPJ com DV inválido não dispara consulta e mostra "Informe um CNPJ válido." CNPJ já cadastrado pelo mesmo usuário mostra "Você já cadastrou esta empresa." e um link para ela, também sem consulta.
- [ ] **CA-3 — Ficha completa.** Uma consulta bem-sucedida exibe, só-leitura: razão social; nome fantasia; situação cadastral e a data dela; data de abertura; natureza jurídica; porte; capital social; CNAE principal (código + descrição); **todos** os CNAEs secundários (código + descrição); endereço completo (logradouro, número, complemento, bairro, CEP, município, UF); sócios e administradores (nome, qualificação, data de entrada e documento como a RFB publica); regime **MEI/SIMEI** (optante sim/não + data de opção + data de exclusão se houver); regime **Simples Nacional** (idem). Um campo ausente na resposta aparece como "Não informado pela Receita", nunca como vazio silencioso.
- [ ] **CA-4 — Nada da RFB é editável.** Não existe input para nenhum dado do CA-3, nem no cadastro nem em `/empresas/{id}`. Um teste prova que um payload Livewire/HTTP forjado, tentando gravar ou alterar campos RFB, não altera nada no banco.
- [ ] **CA-5 — Observações.** O cadastro tem o campo opcional "Observações" (texto livre, até 2.000 caracteres), visualmente separado da ficha oficial. Em `/empresas/{id}` o usuário edita e salva as observações a qualquer momento. A alteração gera audit log sem o conteúdo do texto.
- [ ] **CA-6 — Confirmar grava.** "Confirmar e cadastrar" persiste a empresa com `fonte_enriquecimento = 'rfb'`, o carimbo da consulta e a ficha completa, e redireciona para `/empresas/{id}`. O evento `empresa_cadastrada` e o audit log continuam como hoje (STORY-016), sem PII.
- [ ] **CA-7 — Falha bloqueia.** Timeout, erro do provedor ou erro de rede mostram "Não conseguimos consultar a Receita agora. Tente novamente em alguns minutos." e **não** liberam preenchimento manual. CNPJ inexistente mostra "Não encontramos este CNPJ na Receita. Confira os números." As métricas `rfb_consulta_*` continuam sendo emitidas (STORY-015 CA-4).
- [ ] **CA-8 — Situação não ativa.** Uma empresa com situação cadastral diferente de ativa pode ser cadastrada, mas a ficha exibe um aviso destacado com a situação (ex.: "Empresa BAIXADA na Receita desde dd/mm/aaaa").
- [ ] **CA-9 — CPF segue manual.** O link "Não tenho CNPJ (sou autônomo)" leva ao formulário manual de CPF, que funciona como hoje (STORY-014), com os campos editáveis. A partir desse formulário dá para voltar para o fluxo por CNPJ.
- [ ] **CA-10 — Provedores.** O mock, `cnpja` e `receitaws` mapeiam todos os campos do CA-3 que o provedor oferece. O mock gera fichas determinísticas com 0, 1 e vários CNAEs secundários e sócios, e também um MEI, um optante do Simples e um não optante. Um campo que um provedor real não oferece é listado em IDR e cai na regra "Não informado pela Receita" do CA-3.
- [ ] **CA-11 — E2E.** Testes de browser cobrem: CNPJ → ficha → observação → confirmar → tela da empresa mostrando ficha e observação; falha de consulta bloqueando; caminho por CPF.

## Fora de escopo

- Ação "Atualizar dados da Receita" e empresas legadas: **STORY-064**.
- Pré-preencher os sócios do quiz (Q21–Q23) com o quadro da RFB (PDR-010: o CPF vem mascarado).
- Usar o regime MEI/Simples no DRM ou no diagnóstico (só fica disponível; quem consome é outra estória).
- Reconsulta em lote; webhooks da Receita; troca de provedor ou plano pago.

## Padrões de qualidade exigidos

Segue `defonline-docs/skills/po/references/quality-standards.md`:

- Cobertura ≥ 80% no código novo; ≥ 98% no mapeamento dos provedores e no que estiver em `app/Domain/**`.
- E2E (Dusk) para os fluxos do CA-11.
- **PII:** CNPJ e dados de sócios nunca em log ou evento sem máscara (LogSanitizer).
- Migração automatizada e reversível. Empresas existentes continuam abrindo em `/empresas/{id}` sem erro, mesmo sem a ficha nova.

## Dependências

- **Bloqueada por:** nada.
- **Bloqueia:** STORY-064.
- **Design:** `requires_design: true`. O Designer entrega o spec da tela CNPJ → ficha → confirmação e da ficha em `/empresas/{id}` (mobile-first, DS v1.1) em paralelo, conforme PDR-002.

## Decisões já tomadas (não as reabra)

- PDR-010 inteiro: CNPJ primeiro, RFB imutável, bloqueio na falha, CPF manual, observações.
- IDR-004/IDR-005: abstração de provedores, `cnpja` como default em produção, rate-limit por provedor.
- ADR-003: multi-tenancy, soft delete e audit.

## Liberdade técnica do agente

Você decide: o modelo de dados da ficha (colunas, tabelas filhas ou JSON), a evolução do DTO `RfbCnpjResult` e o desenho dos testes. Você **não** decide: quais campos compõem a ficha (CA-3), o que é editável (CA-4/CA-5) nem o comportamento na falha (CA-7).

## Definição de Pronto (DoD)

- [ ] CAs passam; pre-push e CI verdes.
- [ ] Deploy em homologação verificado com CNPJ real (provedor `cnpja`).
- [ ] `database-schema.md` regenerado (skill `/analisar-banco`).
- [ ] IDR para os campos não oferecidos por algum provedor, se houver.
- [ ] `index.json` atualizado; "Notas do agente" preenchidas.

## Protocolo do agente (obrigatório)

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### IDRs criados
### Cobertura final
### Links de evidência
