---
epic_id: EPIC-001
slug: cadastro-minimo
title: Cadastro mínimo de Usuário e Empresa Analisada
wave: WAVE-2026-01
status: draft
owner_role: po
created_at: 2026-05-20
updated_at: 2026-05-20
target_completion: 2026-07-15
---

# EPIC-001 — Cadastro mínimo de Usuário e Empresa Analisada

## Por que existimos (problema do usuário)

Roberto precisa de uma porta de entrada — sem cadastro persistido, não há histórico, não há recorrência, e nada do que ele faz no DEFOnline sobrevive entre sessões. O cadastro também é a primeira pegada do produto: se for atritado, longo ou cheio de termos contábeis, ele desiste antes do quiz.

A persona alvo é o dono de pequena indústria sem formação financeira. O cadastro precisa funcionar para alguém que sabe o CNPJ de cor mas não tem o cartão CNPJ aberto, que digita pelo celular no caminho da feira, que aceita Termo de Adesão sem ler — esse é o realismo de produto.

## Resultado esperado (outcome)

Ao fim do EPIC-001, Roberto cria a conta dele (CPF + email + senha + telefone WhatsApp), aceita Termo de Adesão e consentimentos LGPD básicos, cadastra a marcenaria como Empresa Analisada via CNPJ enriquecido pela API da Receita Federal (com fallback manual quando a API falhar), e vê a empresa listada em "Minhas Empresas". A experiência tudo isso em menos de 5 minutos no celular.

## Métrica de sucesso (como saberemos que funcionou)

- **Métrica primária (driver D1 — Aquisição da árvore do north star):** ≥ 80% dos convidados que chegam à tela de cadastro completam o fluxo até ver a Empresa listada. Janela: D+14 após deploy em homologação.
- **Métrica de qualidade:** taxa de erro no fluxo de cadastro ≤ 5% (falhas técnicas, não desistência); zero perdas de dado no submit.

## Entregável visível no fim do épico

- [ ] Roberto entra em homologação (por convite), cria Usuário com CPF + email + senha + telefone, aceita Termo + LGPD.
- [ ] Roberto cadastra a marcenaria por CNPJ; a API RFB preenche razão social, nome fantasia, CNAE, município, UF, situação cadastral.
- [ ] Quando a API RFB falha (timeout, indisponibilidade, CNPJ inexistente), Roberto preenche os campos manualmente sem perder o cadastro.
- [ ] A Empresa Analisada aparece em "Minhas Empresas" e é selecionável para um futuro quiz (preparado para EPIC-002).
- [ ] Eventos `usuario_cadastrado` e `empresa_cadastrada` são emitidos com os campos definidos pelo Arquiteto na ADR de captura de eventos.

## Fora de escopo (explicitamente)

- Edição de Usuário (apenas leitura nesta onda — alteração de senha entra na onda 2 com recuperação).
- Edição ou exclusão de Empresa Analisada (visualização apenas).
- Cadastro simultâneo de várias Empresas Analisadas — o modelo de domínio suporta N:1, mas a UI da onda 1 expõe uma. Sem retrabalho de banco.
- Recuperação de senha por email (onda 2).
- Autenticação multifator MFA (roadmap §7.1, v1.2).
- Login com Google/Apple/social (não previsto na espec V2.5).
- Verificação de CPF/CNPJ contra base externa para detecção de fraude além da RFB.
- Plano e cobrança — nesta onda, o cadastro abre conta gratuita do beta fechado.

## Referências da especificação

- `defonline-docs/especificacao/V2/especificacao-funcional.md` §1.3 (personas), §1.5.2 (entidades), §3.2 e §3.3 (jornada de cadastro).
- `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §3.1 (RFB com fallback), §7 (LGPD).

## Dependências

- **Bloqueia:** EPIC-002 (quiz precisa de Empresa Analisada).
- **Bloqueado por:** EPIC-000 (precisa de pipeline e homologação).
- **Decisões arquiteturais necessárias:** padrão de autenticação/sessão (ADR sai do EPIC-000); integração com API RFB (pode demandar ADR específica se a estratégia técnica não couber no padrão geral — Arquiteto decide).

## Estórias

(A ser detalhado quando o EPIC-000 estiver `in_progress` avançado. Princípios: vertical slicing — cada estória atravessa o stack inteiro; primeira estória entrega "Usuário criado + Empresa cadastrada com dados mínimos digitados manualmente"; estória seguinte adiciona enriquecimento via RFB; estória de Termo de Adesão e LGPD em separado; última estória é validação assumida pela skill `validador`.)

## Validação final

Critérios em `validation/checklist.md` (a criar). Relatório do validador em `validation/report.md`.

**Definição de épico concluído:** todas as estórias `done` + relatório do validador `approved` + Roberto em homologação executando o fluxo completo em ≤ 5 minutos no celular.

## Histórico

- 2026-05-20 — Criado como draft junto com a abertura da WAVE-2026-01.
