---
epic_id: EPIC-001
slug: cadastro-minimo
title: Cadastro mínimo de Usuário e Empresa Analisada
wave: WAVE-2026-01
status: ready
owner_role: po
created_at: 2026-05-20
updated_at: 2026-05-22
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

Decompostas em 7 estórias verticais (Fluxo B do PO, 2026-05-22). Princípio: **walking skeleton primeiro**; em seguida, camadas independentes paralelizáveis (Termo, email, empresa-manual); RFB layered em cima de empresa-manual; tela "Minhas Empresas" + eventos como ponto de junção; validação ao final.

- [ ] **STORY-011** (implementation, programador, `ready`, M) — Walking skeleton: cadastro de Usuário (CPF + email + senha + telefone) + login + home autenticada. Vertical slice mínimo end-to-end. **Sem** Termo, **sem** confirmação de email — entram nas próximas. Destrava todas as outras.
- [ ] **STORY-012** (implementation, programador, `ready`, S) — Termo de Adesão + consentimento LGPD (aceites obrigatórios) + opt-in de marketing (opcional). **Texto entra como placeholder PT-BR genérico** porque a revisão jurídica foi postergada (decisão PO 2026-05-22); substituição do texto definitivo quando jurídico voltar vira bugfix curto (STORY futura, fora do escopo deste épico). Tabela `TermAcceptance` persistida. Bloqueada por STORY-011.
- [ ] **STORY-013** (implementation, programador, `ready`, M) — Confirmação de email por link assinado. Notification Laravel + signed URL. **Conta inativa até confirmar** (login bloqueado com mensagem clara). Reenvio com throttling. Bloqueada por STORY-011.
- [ ] **STORY-014** (implementation, programador, `ready`, M) — Cadastro de Empresa Analisada por preenchimento **manual**. Documento (CNPJ ou CPF da empresa autônoma), razão social, nome fantasia, CNAE, município, UF, situação cadastral. Vincula à conta do Usuário logado. Validações de formato. Sem RFB ainda. Bloqueada por STORY-011.
- [ ] **STORY-015** (implementation, programador, `ready`, M) — Enriquecimento via API RFB. **Inicia com mock/sandbox** (NRF §3.1: provedor real `[A DEFINIR]`, não bloqueia kickoff). Cliente HTTP isolado em Repository + fallback transparente para o formulário manual da STORY-014 quando a API falha (timeout, indisponibilidade, CNPJ inexistente). Aviso explícito ao usuário no fallback. Monitoramento (alarme >5% erro em janela 10 min). Bloqueada por STORY-014. Quando provedor real for escolhido, registrar IDR.
- [ ] **STORY-016** (implementation, programador, `ready`, S) — Tela "Minhas Empresas": lista a empresa cadastrada para o Usuário logado, com placeholder "Iniciar diagnóstico" desabilitado (EPIC-002 ativa). Emite eventos `usuario_cadastrado` e `empresa_cadastrada` via `EventLogger` (já existe do EPIC-000), com schema fixado em ADR-004. Bloqueada por STORY-012, STORY-013, STORY-014.
- [ ] **STORY-017** (validation, validador, `draft`, M) — Validação final do EPIC-001. Promovida para `ready` quando STORY-016 estiver `in_review`. Inclui criação de `validation/checklist.md` (a primeira est-ria desta validação cria; subsequente apenas executa). Mesmo padrão da STORY-008 do EPIC-000.

**Paralelismo:** após STORY-011, podem rodar em paralelo: STORY-012, STORY-013, STORY-014. STORY-015 entra depois da 014. STORY-016 é o ponto de junção.

**Decisões abertas que NÃO bloqueiam abertura do épico:**
- Texto definitivo do Termo de Adesão e LGPD — depende do jurídico (postergado pelo PO em 2026-05-22). Entra como bugfix futuro.
- Provedor real da API RFB — `[A DEFINIR]` na NRF §3.1; STORY-015 entra com mock e o provedor real pode entrar via IDR ou PDR posterior.
- DPO formal — `[DECIDIR]` na NRF §7.4 (indicar pessoa EBP ou contratar DPO-as-a-service). Não bloqueia desenvolvimento; precisa estar definido **antes** do go-live em produção (onda 2).

## Validação final

Critérios em `validation/checklist.md` (a criar). Relatório do validador em `validation/report.md`.

**Definição de épico concluído:** todas as estórias `done` + relatório do validador `approved` + Roberto em homologação executando o fluxo completo em ≤ 5 minutos no celular.

## Histórico

- 2026-05-20 — Criado como draft junto com a abertura da WAVE-2026-01.
- 2026-05-22 — EPIC-000 fechou aprovado (relatório `epics/EPIC-000-foundation/validation/report.md`, segundo passe). PO decidiu **postergar a revisão jurídica do Termo / LGPD** (ação fora do time de dev) e **promover EPIC-001 para `ready`** com Fluxo B de decomposição em 7 estórias (STORY-011 a STORY-017). STORY-011 entra como walking skeleton vertical (cadastro Usuário + login + home autenticada); STORY-012 a 014 paralelizáveis após 011; STORY-015 (RFB) inicia com mock conforme NRF §3.1; STORY-016 emite eventos `usuario_cadastrado` e `empresa_cadastrada`; STORY-017 valida.
