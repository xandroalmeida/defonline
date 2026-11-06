---
story_id: STORY-072
slug: cupons-no-backoffice
title: "Backoffice — criação e concessão de cupons"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: done
owner_agent: programador
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-072 — Backoffice — criação e concessão de cupons

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

O PDR-020 criou cupons de desconto parcial ou total. Os usuários do beta recebem o benefício por concessão direta.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.5
  - `defonline-docs/especificacao/V2/arquitetura-tecnica.md` §4.1 (Coupon, CouponGrant/CouponRedemption)

## O quê

Permitir que um admin crie, edite, desative e conceda cupons, e veja o uso de cada um.

## Por quê

A equipe faz promoções e cumpre a promessa ao beta sem deploy.

## Critérios de aceite

- [ ] **CA-1:** Tela e ações exigem a permissão nova `cupons.gerenciar`; sem ela, 404 e nenhum item no menu.
- [ ] **CA-2:** O admin cria cupom com código único, desconto percentual de 1 a 100 ou valor fixo, vigência, limite total de usos, itens cobertos e duração em ciclos ou toda a assinatura.
- [ ] **CA-3:** Códigos duplicados (sem diferenciar maiúsculas), percentual fora da faixa, valor fixo menor ou igual a zero e vigência invertida são recusados com mensagem por campo.
- [ ] **CA-4:** O admin concede o cupom a contas específicas ou a todas as contas cadastradas até uma data, que é o caso do beta. A tela mostra quantas contas serão afetadas antes de confirmar.
- [ ] **CA-5:** Conceder de novo à mesma conta não cria duplicidade.
- [ ] **CA-6:** Desativar impede novos usos e concessões, sem afetar descontos já aplicados.
- [ ] **CA-7:** A tela lista, por cupom, concessões e usos.
- [ ] **CA-8:** Criação, alteração, desativação e concessão vão para `audit_logs`.

## Fora de escopo

- Aplicar o cupom na compra (STORY-083).
- Excluir cupom: só desativar.

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-071
- **Bloqueia:** STORY-083, STORY-087

## Decisões já tomadas (não as reabra)

- PDR-020: catálogo em tela, trial, Asaas substituível, Termos `v2`, DRM em planos pagos e no trial, e cupons.

## Liberdade técnica

Você decide estrutura de código, tabelas, bibliotecas dentro dos ADRs e desenho dos testes. Não decide critérios de aceite nem padrões de qualidade. Se faltar uma decisão arquitetural, pare e registre em "Notas do agente".

## Definição de Pronto

- [ ] Todos os critérios de aceite têm teste e passam.
- [ ] `scripts/verificar.sh` verde.
- [ ] Commits em `main` local; push, PR e tag só com pedido do PO.
- [ ] IDR registrado se houve decisão técnica com impacto futuro.
- [ ] `index.json` e "Notas do agente" atualizados; status `in_review`.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Ao iniciar, atualize status e `owner_agent` aqui e no `index.json`. Se travar, use `status: blocked` e descreva o bloqueio.

## Notas do agente

### Decisões tomadas
- Tabelas `cupons`, `cupom_itens`, `cupom_concessoes` e `cupom_usos` (migration `2026_11_05_200010_create_cupons`), com CHECKs de tipo, faixa (percentual 1 a 100, valor fixo >= 1 centavo) e vigência, e índices únicos (código; cupom+usuário em concessões e usos; item repetido).
- Itens cobertos por id: `cupom_itens` aponta para `planos.id` (com ciclo `mensal`/`anual`) ou `pacotes_credito.id`. Nenhum código fixo; o teste arquitetural da STORY-071 continua passando.
- Código do cupom normalizado para maiúsculas (3 a 40 caracteres: letras, números, `-`, `_`); a unicidade ignora caixa porque o armazenado é sempre maiúsculo. Corrida entre dois admins cai no índice único e vira erro de campo.
- Regras em `app/Domain/Cobranca` para a STORY-083 reutilizar: `TipoDesconto::desconto()` (percentual com arredondamento de meio centavo para cima, valor fixo; nunca passa do preço nem fica negativo), `CupomRegras::errosDaDefinicao()` e `CupomRegras::avaliarUso()` (ativo, vigência por dia inclusive, limite total, cobertura, uso anterior), `MotivoRecusaCupom`. Ponto de entrada para o checkout: `Cupom::recusaPara($usuarioId, $planoId, $ciclo, $pacoteId, $agora)` e `Cupom::cobre(...)`.
- "Todas as contas cadastradas até uma data" interpretado como contas com `created_at` até o fim do dia informado, excluindo anonimizadas e excluídas. Contas específicas são identificadas por e-mail (um por linha, vírgula ou ponto e vírgula); e-mail sem conta bloqueia a concessão e lista quais são.
- A concessão copia `ciclos_duracao` do cupom para `ciclos_restantes`. A tela exige "Ver quantas contas" (prévia com total, novas e já tinham) antes de oferecer "Confirmar"; qualquer mudança no formulário descarta a prévia. Insere com `insertOrIgnore` em lotes de 500 (CA-5 garantido pelo índice).
- Auditoria: `cupons.cupom_criado/alterado/desativado/reativado/concedido`, antes/depois e autor. Na concessão por contas vão os ids das novas; na em massa, só modo, data e contagens. Sem mudança real, edição não grava nem audita.
- Reativar um cupom desativado é permitido (como em planos), auditado; o CA só diz que desativar impede novos usos e concessões.
- Ao menos um item coberto é obrigatório. O limite de usos não pode ficar abaixo dos usos já feitos.
- `cupom_usos` nasce aqui só para a tela mostrar o consumo e a regra "uma vez por usuário"; quem grava é a STORY-083 (pode acrescentar colunas, como fatura/assinatura).

### Descobertas
- `<input type="date">` do Chromium segue o idioma do navegador (mm/dd/aaaa) e não aceita apagar e redigitar: no Dusk, as datas são definidas por script com valor ISO.
- `AuditLog::before/after` voltam como `ArrayObject`; em teste, comparar com `->toArray()`.

### Bloqueios encontrados
Nenhum.

### IDRs criados
Nenhum.

### Cobertura final
Suíte All 1438 testes passando; cobertura total 98,4%; CupomRegras, TipoDesconto, MotivoRecusaCupom e os 4 modelos 100%; Livewire Cupons 99,6%.

### Evidências
`tests/Feature/Livewire/Backoffice/CuponsTest.php` (CA-1 a CA-8, itens e regra de uso), `tests/Unit/Domain/Cobranca/CupomRegrasTest.php` (regras de domínio), `tests/Browser/CuponsBackofficeBrowserTest.php` (E2E). Pint e PHPStan limpos.
