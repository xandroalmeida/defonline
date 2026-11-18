---
story_id: STORY-103
slug: crm-de-captacao
title: "CRM básico das demandas de captação: quadro Kanban"
epic_id: EPIC-011
sprint_id: null
type: feature
target_role: programador
requires_design: false
status: in_review
owner_agent: claude-opus-5-5
created_at: 2026-11-18
updated_at: 2026-11-18
estimated_session_size: M
---

# STORY-103 — CRM básico das demandas de captação

Origem: pedido do PO em 2026-11-18, depois da STORY-102: a tela Captação só listava as demandas, sem nenhuma ação. Contexto em `decisions/pdr/PDR-021-demanda-de-captacao.md`.

## Critérios de aceite

- [x] **CA-1:** A tela Captação vira um quadro com as colunas Nova, Em análise, Respondida e Encerrada. Toda demanda nasce em Nova.
- [x] **CA-2:** O cartão muda de etapa arrastando ou pelos botões de etapa anterior/próxima (teclado e celular). Também pode voltar etapa.
- [x] **CA-3:** O cartão abre um detalhe com contato, valor, prazo, etapa, responsável, campo de nota e histórico.
- [x] **CA-4:** O responsável é escolhido entre quem tem acesso à captação. Pode ficar sem responsável.
- [x] **CA-5:** Mudanças de etapa e de responsável entram sozinhas no histórico, com autor e hora. Notas da equipe também. Nada é editado nem apagado.
- [x] **CA-6:** Uma demanda em aberto (Nova ou Em análise) com o prazo de 5 dias úteis vencido fica destacada como atrasada. No dia limite ela ainda não conta como atrasada.
- [x] **CA-7:** Os filtros são busca por empresa ou CNPJ/CPF e responsável (todos, meus, sem responsável, um colega). As demandas sem autorização de contato ficam ocultas, salvo se o filtro pedir.
- [x] **CA-8:** A coluna Encerrada mostra as 30 mais recentes e o total.
- [x] **CA-9:** O link do e-mail da equipe abre o cartão da demanda.
- [x] **CA-10:** Tudo é auditado. A auditoria da nota guarda só o id, sem o texto.

## Fora de escopo

- Notificação ao responsável.
- Motivo de encerramento estruturado (ganha/perdida).
- Relatórios do funil.

## Testes

- `tests/Domain/Captacao/StatusDemandaTest.php`
- `tests/Feature/Captacao/QuadroCaptacaoTest.php`
- `tests/Browser/DemandaCaptacaoBrowserTest.php` (arrastar, botões, detalhe, nota e responsável)

## Notas do agente

- O arrastar usa HTML5 drag-and-drop com Alpine, sem biblioteca nova. O Dusk dispara os eventos `DragEvent` por script, porque o WebDriver não gera os eventos HTML5.
- `wire:ignore.self` no `<dialog>` e no `<details>`: sem isso, o morph do Livewire remove o atributo `open` a cada atualização.
- As ações ficam em `App\Services\Captacao\GerirDemandaCaptacao`. Cada ação grava o evento e a auditoria na mesma transação.
