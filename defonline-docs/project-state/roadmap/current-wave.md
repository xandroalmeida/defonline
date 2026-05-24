---
wave_id: WAVE-2026-01
slug: hipotese-do-roberto
title: Hipótese do Roberto
status: active
start_date: 2026-05-20
target_end_date: 2026-08-26
goal: Validar que o DEFOnline entrega valor real ao dono de pequena indústria, entregando em homologação o ciclo cadastro → diagnóstico → histórico no setor Indústria, sem cobrança.
related_pdr: PDR-001
---

# WAVE-2026-01 — Hipótese do Roberto

## Hipótese central

> O dono de pequena indústria, perante a decisão concreta de investir, captar ou reajustar preço, extrai valor real de um diagnóstico econômico-financeiro automatizado em 15 minutos — a ponto de voltar para um segundo diagnóstico em até 6 meses.

A onda termina com Roberto-real (não simulação) tendo concluído pelo menos um diagnóstico em homologação. A confirmação ou rejeição da hipótese vem do beta fechado por convite e da janela de validação de cada épico.

## Persona alvo

Roberto, dono de marcenaria sob medida (EPP, indústria), 12 funcionários, decisões trimestrais sobre investimento/captação/preço. Detalhamento canônico em `product/personas.md`.

## Restrições da onda

Estas três restrições orientam decisões internas a cada épico:

- **Não quebrar a Joana.** O motor e o relatório precisam continuar fazendo sentido no setor Comércio, mesmo sem cobertura ativa nesta onda. Decisões que **inviabilizem** a expansão para Comércio na onda 2 exigem PDR.
- **Não travar o caminho do Pro.** O modelo de domínio nasce com Usuário → N Empresas Analisadas (espec V2.5 §1.5.2). Decisões que **acoplem** funcionalidade ao limite de 1 Empresa por Usuário precisam ser revistas.
- **Não antecipar cobrança.** Nenhum épico desta onda toca em planos, créditos, cartão, Pix, trial ou paywall. Isso é da onda 2. Cobrança "só pra deixar o stub" é dispersão.

## Épicos da onda

Cinco épicos, cada um terminando com uma estória de validação assumida pela skill `validador`. EPIC-000 é pré-requisito de todos os outros; EPIC-001 → EPIC-004 → EPIC-002 → EPIC-003 fluem em ordem natural de dependência. EPIC-004 (App shell + design system v1) foi acrescentado em 2026-05-24 entre o EPIC-001 e o EPIC-002 para entregar o chassi visual da aplicação antes que as telas densas do diagnóstico (quiz, relatório com 14 indicadores, semáforo) e do histórico nasçam com drift de paleta.

### EPIC-000 — Foundation

**Propósito.** Estabelecer a fundação técnica do projeto pós-reset. Não entrega funcionalidade ao Roberto; entrega ao time a base sobre a qual tudo o mais vai pousar.

**Outcome.** Merge na branch principal dispara deploy automático em ambiente de homologação acessível por URL; ambiente local de desenvolvimento sobe com um comando documentado; ADRs essenciais (stack, hospedagem, banco PostgreSQL ratificado, CI/CD, observabilidade, testes, autenticação, captura de eventos para north star) estão `accepted`.

**Critério de pronto observável.** Há uma página viva ("hello DEFOnline") rodando em `homolog.defonline.com.br` (ou equivalente decidido pelo Arquiteto), gerada pelo pipeline automatizado a partir da main. A página exibe a versão deployada e um indicador de healthcheck.

**Decomposição em estórias.** Detalhada no Fluxo B desta sessão. Princípio: uma ADR = uma spike, ADRs heterogêneas vão como spikes paralelas executáveis simultaneamente pelo Arquiteto. Lista de candidatas em PDR-001.

**Estimativa orientativa.** 4–5 semanas.

### EPIC-001 — Cadastro mínimo de Usuário e Empresa Analisada

**Propósito.** Permitir que Roberto crie conta, cadastre a marcenaria como Empresa Analisada, e veja a empresa listada na conta dele. Esta é a porta de entrada do produto.

**Outcome.** Ao fim do EPIC-001, Roberto (em homologação, com convite) consegue: criar Usuário com CPF + email + senha + telefone WhatsApp, aceitar Termo de Adesão e consentimentos LGPD básicos, cadastrar a primeira Empresa Analisada por CNPJ (enriquecido via API da Receita Federal, com fallback manual quando a API falhar), e visualizar a empresa na lista da conta dele.

**Critério de pronto observável.** Roberto loga, cadastra a marcenaria com CNPJ válido, recebe os dados enriquecidos (razão social, CNAE, município, UF) preenchidos automaticamente, confirma e vê a empresa em "Minhas Empresas".

**Métrica primária do épico.** Taxa de conclusão do cadastro: ≥ 80% dos convidados que chegam à tela de cadastro completam o fluxo até ver a Empresa na lista. Janela: D+14 após o épico ir para homologação. Conecta com driver de **Aquisição** (D1) da árvore do north star.

**Fora de escopo do épico.** Edição de Usuário (apenas leitura); edição/exclusão de Empresa Analisada; cadastro de várias Empresas Analisadas (suporte ao modelo N nativo está no domínio, mas a UI da onda 1 expõe uma); recuperação de senha (entra na onda 2); 2FA/MFA (roadmap pós-v1).

**Referências da especificação.** `defonline-docs/especificacao/V2/especificacao-funcional.md` §1.5.2, §3.2, §3.3; `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §3.1, §7.

**Estimativa orientativa.** 3 semanas.

### EPIC-004 — App shell + materialização do design system v1

**Propósito.** Entregar o chassi visual da aplicação: layout Blade base, header global, navegação principal responsiva (sidebar no desktop / drawer no mobile), breadcrumb, footer, e materialização em código (CSS vars centralizadas) dos tokens já especificados em `especificacao/V2/design-system.md`. Refatorar as quatro telas existentes do EPIC-001 (`/cadastro`, `/login`, `/home`, `/empresas/nova`) para consumirem o novo layout, corrigindo o drift de paleta confirmado entre o design system oficial (`#0A2540/#635BFF/#F6F9FC`) e as STORY-011/STORY-016 (`#1f2937/#2563eb/#f9fafb`).

**Outcome.** Ao fim do EPIC-004, toda rota autenticada do DEFOnline renderiza dentro de um shell único com header (logo + nome do usuário + dropdown "Conta" com Sair), navegação principal com itens **Minhas Empresas / Adicionar Empresa** (ativos) e **Diagnósticos / Histórico / Conta** (desabilitados com tooltip "Em breve"), breadcrumb em telas internas e footer com versão deployada + links institucionais. Tokens do design system materializados em arquivo único — alteração de cor primária se propaga em uma só edição.

**Critério de pronto observável.** Roberto, em homologação, loga e navega entre `/home` (Minhas Empresas) e `/empresas/nova` pelo menu (não por URL direta), tanto em mobile (drawer + hamburger) quanto em desktop (sidebar fixa). Paleta Stripe-like aplicada em todas as telas. `design-system.md` promovido de `alpha` para `v1`. IDR de framework CSS aceita.

**Métrica primária do épico.** Qualidade percebida em entrevista qualitativa do beta: 4 de 5 Robertos descrevem o produto como "parece profissional" / "parece um sistema sério" sem provocação. Janela: D+14 após deploy em homologação. Métrica de cobertura visual: 100% das rotas autenticadas existentes consumindo o novo layout; zero telas com paleta divergente do design system oficial.

**Fora de escopo do épico.** Página `/conta` com edição de Usuário (onda 2); rotas reais para Termo / Política de Privacidade (placeholders nesta onda; conteúdo definitivo pendente do jurídico); dark mode; auditoria WCAG AAA (alvo é AA básico); tutorial onboarding de 3 slides; hotsite público (entra em EPIC-009 da onda 2 — renumerado em 2026-05-24); telas do EPIC-002.

**Referências da especificação.** `defonline-docs/especificacao/V2/design-system.md` (fonte da verdade — tokens, componentes-base, do's and don'ts); `defonline-docs/especificacao/V2/especificacao-funcional.md` §3.3 (painel principal — base mental do shell); ADR-001 (stack Laravel 13 + Livewire 4) — restringe escolha de framework CSS a alternativas compatíveis; ADR-002 (monolito modular).

**Dependências.** Bloqueado por EPIC-001 (precisa das telas existentes para refatorar; começa quando STORY-016 fechar `done`). Bloqueia EPIC-002 e EPIC-003 (chassi precisa estar pronto antes das telas densas do diagnóstico e do histórico).

**Estimativa orientativa.** 1–2 semanas (1 estória implementation `M-L` + 1 validação `M`). Entra na próxima sprint após SPRINT-2026-W22 fechar — não infla a sprint ativa do EPIC-001.

### EPIC-002 — Diagnóstico para Indústria

**Propósito.** Entregar o coração do produto: a partir do quiz da marcenaria, Roberto recebe relatório web com 14 indicadores, semáforo visual e recomendações específicas para o setor Indústria. Esta é a estória que valida a hipótese central da onda.

**Outcome.** Ao fim do EPIC-002, Roberto preenche o quiz da marcenaria (campos da planilha de Estrutura aplicáveis a Indústria), aciona o cálculo, e recebe um relatório web — em poucos segundos a um minuto — com os 14 indicadores, semáforo verde/amarelo/vermelho, recomendações da matriz de DEZ/2025 filtradas pelo setor Indústria, glossário acessível e Resumo Executivo conforme algoritmo determinístico da spec V2.5 §4.7.1.

**Critério de pronto observável.** Roberto vê em homologação um relatório legível de ponta a ponta, com semáforo, recomendações textuais por indicador, glossário inline e Resumo Executivo. O relatório é gerado em ≤ 3 segundos (p95) para dataset típico.

**Métrica primária do épico.** Ativação (D2 da árvore do north star): ≥ 60% dos Usuários cadastrados concluem o 1º diagnóstico em até 7 dias. Métrica de qualidade: NPS médio do relatório ≥ 30 entre os primeiros 20 Robertos no beta fechado. Janela: D+30 após deploy em homologação.

**Fora de escopo do épico.** Setor Comércio e Serviços (entram na onda 2); exportação em PDF (onda 2); solicitação de captação (onda 2); compartilhamento do relatório (roadmap); feedback 👍/👎 por recomendação (roadmap §1.4); medianas setoriais (roadmap §2.1).

**Referências da especificação.** `especificacao-funcional.md` §4.1, §4.5, §4.7, §4.7.1; Anexos A, D, E, F (matriz DEZ/2025 — restrita à coluna Indústria); `requisitos-nao-funcionais-e-juridicos.md` §1, §9.2.

**Dependências.** Bloqueado por EPIC-001 (precisa de Empresa Analisada cadastrada).

**Estimativa orientativa.** 5–6 semanas. É o épico mais denso; pode justificar quebra interna em 2 incrementos verticais durante o Fluxo B.

### EPIC-003 — Histórico básico

**Propósito.** Permitir que Roberto retorne ao DEFOnline meses depois e compare o estado da marcenaria entre diagnósticos. Sem histórico, recorrência morre — e recorrência é o driver D3 do north star.

**Outcome.** Ao fim do EPIC-003, Roberto vê a lista cronológica de diagnósticos da marcenaria nos últimos 12 meses rolantes, abre qualquer diagnóstico anterior na íntegra, e visualiza um comparativo lado a lado de dois diagnósticos escolhidos (mesmo conjunto de 14 indicadores, com indicação visual de melhoria/piora).

**Critério de pronto observável.** Em homologação, Roberto consegue (após ter feito ao menos 2 diagnósticos em datas diferentes) ver a lista, reabrir o diagnóstico antigo idêntico ao que viu no dia, e ver comparativo de 2 datas com setas/cores indicando direção da variação.

**Métrica primária do épico.** Recorrência (D3 da árvore do north star): ≥ 40% dos Usuários ativados retornam para 2º+ diagnóstico em 180 dias. Janela: D+180 após cadastro do primeiro diagnóstico — não fecha dentro desta onda; aparece nos status reports da onda 2/3.

**Fora de escopo do épico.** Gráficos de tendência de 6/12 meses (roadmap §1.2); alertas de deterioração (roadmap §1.2); metas por indicador (roadmap §1.2); exportação do histórico em CSV/Excel (roadmap).

**Referências da especificação.** `especificacao-funcional.md` §4.8.

**Dependências.** Bloqueado por EPIC-002 (precisa do motor e do relatório).

**Estimativa orientativa.** 2–3 semanas.

## Sequência e justificativa

A ordem segue dependência natural: sem Foundation, nada deploya; sem Cadastro, não há Empresa Analisada para vincular diagnóstico; sem chassi visual (EPIC-004), as telas densas do EPIC-002 (quiz + relatório com 14 indicadores + semáforo) e do EPIC-003 (lista + comparativo) nasceriam com drift de paleta e gerariam retrabalho; sem Diagnóstico, não há nada para a Recorrência reapresentar. EPIC-002 é o coração do valor e merece a fatia maior do tempo da onda. EPIC-003 é menor mas indispensável — sem ele, a métrica de recorrência (D3) nunca pode ser observada. EPIC-004 é fundação de UX (1–2 semanas) e roda entre EPIC-001 e EPIC-002, sem competir pelo orçamento de tempo do EPIC-002.

## Janela orientativa

Início: 20/05/2026. Alvo de término: 26/08/2026 (~14 semanas, com folga de 2 semanas sobre a estimativa otimista). Datas exatas não são compromisso; servem ao Alexandro como referência de planejamento próprio. Replanejamento formal no fim de cada épico, com status report.

## Métricas que esta onda deixa instrumentadas

Para alimentar o north star desde o primeiro diagnóstico em produção, os épicos da onda 1 já entregam captura de eventos para:

- `usuario_cadastrado` (alimenta D1 — Aquisição).
- `empresa_cadastrada` (alimenta D1).
- `quiz_iniciado` e `diagnostico_concluido` (alimentam D2 — Ativação e o próprio north star).
- `diagnostico_visualizado` e `comparativo_aberto` (alimentam D3 — Recorrência).

A instrumentação é parte do escopo das estórias relevantes — não fica para depois. ADR de captura de eventos sai como spike no EPIC-000.

## Riscos identificados na abertura

- **Risco de hipótese:** Roberto talvez precise de cobrança/captação para se engajar de verdade — beta fechado sem cobrança pode dar falso positivo. Mitigação: pesquisa qualitativa estruturada com cada um dos primeiros Robertos.
- **Risco de RFB:** API da Receita Federal pode estar fora do ar ou ser instável. Mitigação: fallback manual já no escopo do EPIC-001.
- **Risco de motor:** matriz DEZ/2025 pode trazer recomendações inadequadas em casos extremos do setor Indústria. Mitigação: validação externa antes do beta, conforme NRF §9.3.
- **Risco de ciclo:** EPIC-002 pode estourar a estimativa por subdimensionamento. Mitigação: dividir verticalmente no Fluxo B se ultrapassar 6 semanas (ex.: separar "Motor + relatório com 7 indicadores essenciais" + "Completar os 14 indicadores e Resumo Executivo").
- **Risco de stack/ADR:** spikes do Foundation podem tomar mais tempo que o estimado, atrasando o cronograma global. Mitigação: spikes paralelizáveis (heterogêneas) e timeboxed; PDR de revisão se exceder.

## Histórico

- 2026-05-20 — Criada com PDR-001 aceito.
- 2026-05-24 — **EPIC-004 (App shell + materialização do design system v1)** acrescentado entre EPIC-001 e EPIC-002 pelo PO após análise de gap no backlog: nenhum item priorizado previa app shell, menu de navegação, breadcrumb ou materialização do design system em código. Drift de paleta já confirmado entre `especificacao/V2/design-system.md` (oficial: `#0A2540/#635BFF/#F6F9FC`) e STORY-011/STORY-016 (`#1f2937/#2563eb/#f9fafb`). Inserir o chassi **antes** do início efetivo do EPIC-002 evita retrabalho de UI nas telas densas do diagnóstico (14 indicadores + semáforo + Resumo Executivo) e do histórico (lista cronológica + comparativo). STORY-019 (implementação) e STORY-020 (validação) entram na **próxima sprint** após SPRINT-2026-W22 fechar — não inflam a sprint ativa do EPIC-001. Numeração da WAVE-2026-02 deslocada (EPIC-004 placeholder de "Cobrança" virou EPIC-005, com cascata).
