---
story_id: STORY-097
slug: minha-conta
title: "Minha conta: editar perfil e ver plano, créditos, cupons e cobranças"
epic_id: EPIC-004
sprint_id: null
type: feature
target_role: programador
requires_design: false
status: in_review
owner_agent: claude-opus-5-5
created_at: 2026-11-06
updated_at: 2026-11-06
estimated_session_size: M
---

# STORY-097 — Minha conta: editar perfil e ver plano, créditos, cupons e cobranças

Origem: pedido do PO em 2026-11-06. Materializa a página `/conta` prevista no EPIC-004 (nome, senha e telefone).

## Critérios de aceite

- [x] **CA-1:** `/conta` exige login e abre pelo item "Conta" do menu e por "Editar perfil" no cabeçalho.
- [x] **CA-2:** Nome e telefone vêm preenchidos e podem ser alterados, com as regras do cadastro. O novo primeiro nome aparece no cabeçalho.
- [x] **CA-3:** E-mail e CPF (mascarado) aparecem só para leitura, com a situação da confirmação do e-mail.
- [x] **CA-4:** A troca de senha exige a senha atual, segue as regras do cadastro e não aceita repetir a atual. Depois de 5 erros, bloqueia por 15 minutos.
- [x] **CA-5:** Trocar a senha encerra as outras sessões da conta e mantém a atual.
- [x] **CA-6:** Com a cobrança ligada, a tela mostra plano e situação, cota, vencimento, cupom do plano, créditos utilizáveis com o vencimento mais próximo, cupons disponíveis, cupons usados e as 3 últimas cobranças. Com ela desligada, nada disso aparece e a tela continua funcionando.
- [x] **CA-7:** No "Logar como" a tela é só leitura, inclusive no servidor.
- [x] **CA-8:** A conta nunca mostra crédito ou cupom de outra pessoa.

## Fora de escopo

- Alterar e-mail ou CPF pelo próprio usuário: o EPIC-004 limita a edição a nome, senha e telefone.
- Recuperação de senha, que continua sem tela.
- Contratar, cancelar e trocar cartão, que seguem em Meu plano.
- Exclusão de conta.

## Decisões tomadas

- O resumo só lê os dados. "Crédito utilizável" passou a ser o escopo `LoteCredito::disponiveis`, usado também por Meu plano.
- A máscara de telefone do cadastro virou o componente `<x-input-telefone>`, usado nas duas telas.
- Cupom disponível usa `CupomRegras::avaliarUso` sem conferir o item comprado, que só é conhecido no checkout.

## Testes

- `tests/Feature/Livewire/Conta/MinhaContaTest.php`
- `tests/Browser/MinhaContaBrowserTest.php`
