---
story_id: STORY-074
slug: trial-gratuito
title: "Trial gratuito"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: done
owner_agent: programador (pista B)
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-074 — Trial gratuito

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

O PDR-020 ativou o trial: 7 dias com 1 análise, valores configuráveis na STORY-071. Não exige meio de pagamento.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.3, §3.4 e §4.12 (notificações)
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §4.4

## O quê

Permitir que o usuário ative o trial uma única vez e usar o limite de dias e análises configurado.

## Por quê

Roberto vê o valor do produto antes de pagar. É o início do funil medido pelo épico.

## Critérios de aceite

- [ ] **CA-1:** Dado um Usuário elegível, quando ativa o trial, ele recebe os dias e as análises configurados, sem informar meio de pagamento.
- [ ] **CA-2:** O mesmo CPF não ativa o trial duas vezes. O mesmo CNPJ não recebe análise de trial em contas diferentes. A mensagem explica o motivo e oferece planos.
- [ ] **CA-3:** Mudanças na configuração do trial não alteram trials em andamento.
- [ ] **CA-4:** Com o trial desativado, a oferta não aparece e a ativação é recusada.
- [ ] **CA-5:** Ao fim do prazo, as funções pagas são bloqueadas sem nenhuma cobrança, e os diagnósticos seguem visíveis na janela de retenção.
- [ ] **CA-6:** O usuário recebe e-mail em D-2 e em D-0 com convite para assinar.
- [ ] **CA-7:** Mais trials do mesmo IP do que o limite configurável em 30 dias geram alerta à equipe, sem bloquear, porque escritórios compartilham IP.
- [ ] **CA-8:** Assinar durante o trial encerra o trial, sem somar a análise restante à cota.
- [ ] **CA-9:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Fora de escopo

- Bloqueio de novos trials por IP.
- Sequência de onboarding (roadmap §1.3).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-070, STORY-073
- **Bloqueia:** STORY-084, STORY-086, STORY-087

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

### Plano (antes de codar)
- Lido: estória, spec `STORY-070-trial`, PDR-020, espec §2.3, §3.4, §4.12, RNF §4.4, notas das STORY-071/073/075, `Carteira`, `RegrasConsumo`, `TextoBloqueio`.
- Regras em `app/Domain/Cobranca` (`ElegibilidadeTrial`, `Trial`, `AvisoTrial`, `DocumentoHash`), a `Carteira` ganha a regra do CNPJ e o encerramento do trial ao assinar, tela `Trial\Ativar`, aviso e oferta na Home, comando de e-mail D-2/D-0 e campo do limite de IP no backoffice.

### Decisões tomadas
- O trial é concedido por `Carteira::concederCota(..., trial: true)` (assinatura com `trial = true`, `plano_id` nulo, STORY-073), com dias e análises lidos da `TrialConfig` no instante da ativação. A assinatura guarda cota e vencimento, então mudar a configuração não mexe em trial em andamento (CA-3). O fim do prazo é derivado do vencimento (já bloqueia por `TesteEncerrado`), sem job de expiração.
- Uma ativação por CPF: tabela `trial_ativacoes` com o CPF só em HMAC-SHA256 (chave da aplicação, `DocumentoHash`), índice único e `usuario_id` anulável para sobreviver à exclusão da conta. O índice também resolve duas abas ao mesmo tempo. Se a chave da aplicação for rotacionada, os hashes antigos deixam de casar (risco aceito; registrar na rotação).
- Uma análise de trial por CNPJ: `trial_empresas` (CNPJ em HMAC, único). A empresa só é registrada quando a análise do trial é gasta, dentro da transação do consumo; o índice serializa duas contas que analisem a mesma empresa ao mesmo tempo. A conferência também roda ao abrir o quiz (`Carteira::verificar($usuarioId, $empresaId)`). O trial bloqueado pelo CNPJ cai no crédito comprado, se houver; plano pago nunca é afetado. Novo `MotivoBloqueio::TesteEmpresaJaAnalisada` com o texto da spec (§4, erro/inelegível). A regra vale só para empresa de CNPJ (espec: "o mesmo CNPJ").
- CA-7: IP gravado em `trial_ativacoes`; acima do limite em 30 dias o alerta sai pelo `RfbAlerter` (Telegram se configurado, senão log estruturado), sem bloquear. Reutilizei o `RfbAlerter` por ser o único canal de alerta existente; o nome é de outra estória e a STORY-086 pode generalizá-lo. O limite é a coluna `trial_config.limite_ip_30d` (padrão 3), editável na tela de planos (campo novo no formulário do trial).
- CA-8: `Carteira::concederCota` agora, ao conceder uma assinatura que não é trial sobre um trial vigente, marca o trial como `expirada` e cria a nova com a própria cota (nada soma). Qualquer outra concessão sobre assinatura vigente continua recusada.
- A ativação é uma ação Livewire (POST) na página `GET /teste-gratis` (rota `trial.iniciar`, o nome que `TextoBloqueio` e a página de planos já esperavam). Abrir a página não ativa nada. Desvio da spec: a spec descreve "um toque", mas o bloqueio e a página de planos levam à página por link, então há um clique a mais nesses dois caminhos; a oferta da Home também leva à página.
- Mensagens de recusa conforme a spec (CPF e CNPJ). Para quem já tem plano vigente, texto próprio (a spec não cobre): "Você já tem um plano ativo, então não precisa do teste gratuito."
- E-mails D-2 e D-0: `trial:avisar-fim` (agendado de hora em hora, `onOneServer`), idempotente por `aviso_d2_em`/`aviso_d0_em`, dias contados em Brasília, não envia no dia da ativação nem depois do vencimento, só com a cobrança ligada. HTML e texto, mesmo texto e CTA da Home. A view usa as cores literais da paleta como o e-mail de confirmação (clientes de e-mail ignoram CSS vars) e entrou na exclusão do `DesignTokensTest`.
- Aviso na Home (`AvisoTrial`, pura): andamento, D-2/D-1, D-0, análise usada e encerrado (bloqueio). Encerrado não aparece para quem tem crédito válido. A oferta aparece na Home só para quem pode ativar. Tudo desaparece com a cobrança desligada (CA-9); a página de ativação e o comando também.

### Descobertas
- O Postgres de desenvolvimento roda em `America/Sao_Paulo` e a aplicação em UTC: Laravel grava `timestamptz` sem offset, então no dev os instantes gravados saem 3 horas deslocados (ex.: `now()` 10:00 UTC grava 10:00 de Brasília = 13:00 UTC). Nos testes que cruzam o prazo usei margem de um dia. Vale conferir o fuso do banco em homologação e produção (fora do escopo).
- `Livewire::test` monta a própria requisição e não enxerga a sessão da personificação; o teste da recusa para admin usa o endpoint de update do Livewire, como o `PersonificacaoTest`.
- `Trial` e `ElegibilidadeTrial` não são `final` só para o teste simular a corrida e a falha inesperada.

### Bloqueios encontrados
Nenhum.

### Pendências e dúvidas
- "Meu plano" (STORY-081) ainda não existe: os avisos de D-2/D-0/encerrado só aparecem na Home e nos e-mails. Quando a tela nascer, ela pode usar `Carteira::avisoDoTeste()`.
- Eventos de produto do funil do trial (ativação, conversão) ficam para a STORY-086.
- Pergunta ao PO: o teste exige e-mail confirmado (spec §9 adota "não")? Implementado sem exigir.

### IDRs criados
Nenhum.

### Cobertura final
Suíte All: 2010 testes passando; cobertura total 98,4%; Carteira, Trial, ElegibilidadeTrial, AvisoTrial, DocumentoHash e RegrasConsumo 100%; Trial\Ativar, Home\MinhasEmpresas e FimDoTrialMessage 100%; AvisarFimDoTrial 97,4%. Dusk completo: 56 passando. Pint e PHPStan limpos.

### Evidências
`tests/Feature/Cobranca/TrialTest.php` (CA-1 a CA-9, corridas), `tests/Domain/Cobranca/AvisoTrialTest.php` e `RegrasConsumoTest.php` (regra do CNPJ), `tests/Feature/Livewire/Trial/AtivarTest.php`, `tests/Feature/Livewire/Home/TrialNaHomeTest.php`, `tests/Feature/Console/AvisarFimDoTrialTest.php` (CA-6), `tests/Feature/Livewire/Backoffice/PlanosTest.php` (limite de IP), `tests/Browser/TrialBrowserTest.php` (E2E).
