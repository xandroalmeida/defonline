---
story_id: STORY-100
slug: recuperacao-de-senha
title: "Recuperação de senha pelo e-mail"
epic_id: EPIC-004
sprint_id: null
type: feature
target_role: programador
requires_design: false
status: in_review
owner_agent: claude-opus-5-5
created_at: 2026-11-06
updated_at: 2026-11-06
estimated_session_size: S
---

# STORY-100 — Recuperação de senha pelo e-mail

Origem: pedido do PO em 2026-11-06. O requisito está em `especificacao/V2/especificacao-funcional.md` §4.12: link válido por 30 minutos.

## Critérios de aceite

- [x] **CA-1:** O login tem o link "Esqueci minha senha", e a tela abre sem conta.
- [x] **CA-2:** O e-mail da conta recebe um link de redefinição. A URL leva o id do usuário, nunca o e-mail.
- [x] **CA-3:** E-mail sem conta, ou de conta excluída, recebe a mesma resposta na tela e nenhum envio.
- [x] **CA-4:** Depois de 5 pedidos em 15 minutos por e-mail e IP, nenhum outro envio sai, com a mesma resposta na tela.
- [x] **CA-5:** Salvar a nova senha encerra as sessões abertas da conta, invalida o link e leva ao login.
- [x] **CA-6:** Link vencido, errado ou de outra conta não troca a senha e oferece pedir um novo.
- [x] **CA-7:** A nova senha segue as regras do cadastro.

## Fora de escopo

- Alterar o e-mail da conta.
- Confirmar o e-mail da conta ao redefinir a senha.

## Testes

- `tests/Feature/Senha/RecuperacaoDeSenhaTest.php`
