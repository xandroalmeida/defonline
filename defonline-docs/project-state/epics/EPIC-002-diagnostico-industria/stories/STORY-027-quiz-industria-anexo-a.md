---
story_id: STORY-027
slug: quiz-industria-anexo-a
title: Quiz de Indústria — formulário com 23 campos do Anexo A (rascunho persistido)
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: done
owner_agent: claude-programador
approved_by: Alexandro
approved_at: 2026-05-25
closed_at: 2026-05-25
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: L
---

# STORY-027 — Quiz de Indústria (23 campos do Anexo A)

> **Para o agente programador:** esta estória entrega a **porta de entrada do EPIC-002**: a partir da tela `Minhas Empresas`, Roberto seleciona uma empresa e abre o quiz para fazer um novo diagnóstico. O quiz tem 23 campos organizados em 4 blocos lógicos, com máscaras, validações de tipo e **rascunho persistido** entre sessões.
>
> Esta estória **depende de STORY-026** (precisa do modelo `Diagnostico` e do schema). Você reusa o app shell e os componentes da STORY-019 (button, input, label, link, layout).

## Contexto

O Anexo A da spec V2 (`especificacao/V2/anexos/anexo-A-campos-quiz.md`) lista os campos do quiz por setor. Para Indústria, o conjunto é:

- **Bloco 1 — Identificação:** Q01 setor (já preenchido a partir da Empresa Analisada).
- **Bloco 2 — DRE/Operação (9 campos):** Q08 compras, Q09 vendas, Q14 custos fixos, Q15 custos variáveis, Q16 despesas financeiras, Q10 PMC, Q11 PME, Q12 PMR, Q13 inadimplência.
- **Bloco 3 — Balanço (6 campos):** Q02 caixa, Q03 clientes, Q04 estoque, Q05 patrimônio, Q06 dívidas, Q07 fornecedores.
- **Bloco 4 — Contexto / Captação (7 campos):** Q17 necessita captar (sim/não), Q18 valor, Q19 endividamento existente, Q20–Q23 cartão/CPFs sócios.

Tempo-alvo de preenchimento: **15 minutos** (epic.md). Rascunho persistido a cada navegação entre blocos — Roberto pode parar e voltar dias depois.

## O quê

1. **Rota `/empresas/{empresa}/diagnosticos/novo`** (item de menu `Diagnósticos` deixa de ser disabled — ajustar app shell da STORY-019; quando clicado sem empresa selecionada, abre seletor de empresa).
2. **Formulário multi-step** com 4 blocos. Componente Livewire (decisão do programador: 1 componente com 4 steps OU 4 componentes navegáveis — preferência: 1 com steps).
3. **Máscaras de entrada:**
   - Campos monetários (Q02..Q09, Q14..Q16, Q18, Q19): máscara R$, aceita decimais com vírgula.
   - Campos de prazo em dias (Q10, Q11, Q12): inteiro positivo, sufixo "dias".
   - Campos de percentual (Q13): número com sufixo "%", aceita decimais.
   - Campo booleano (Q17): radio Sim/Não.
   - Campos de texto (Q20–Q23): CPF mascarado (000.000.000-00).
4. **Validações de tipo client-side** (Livewire wire:model.live) + **server-side** no `submit`. Erros exibidos abaixo do campo (componente `<x-input-error>` do design system).
5. **Rascunho persistido (atualizado 2026-05-25 conforme IDR-010 + confirmação do PO no chat):** ao mudar de bloco (next/back) ou após X segundos de inatividade, payload parcial é salvo em uma **tabela separada `quiz_rascunhos`** — esquema definido por esta estória (ver CA-6 abaixo). **Não** em `diagnosticos`. A tabela `diagnosticos` armazena exclusivamente diagnósticos concluídos (snapshot imutável — IDR-010). Roberto que volta dias depois vê os dados que digitou no último bloco. Rascunho expira em 90 dias (espec §6.4). Esquema mínimo de `quiz_rascunhos`: `id` UUID, `usuario_id` UUID FK, `empresa_analisada_id` UUID FK, `quiz_payload` JSONB (parcial, sem canonicalização — é trabalho do motor), `ultimo_bloco_preenchido` smallint, `expires_at` timestamptz, timestamps. UNIQUE parcial `(usuario_id, empresa_analisada_id) WHERE deleted_at IS NULL` para garantir 1 rascunho ativo por empresa.
6. **Validação cruzada não-bloqueante** entre blocos (DRE×Balanço) é **fora desta estória** — STORY-034 cobre. Aqui só validações de tipo/obrigatório/faixa por campo.
7. **Submissão final:** ao clicar `Calcular diagnóstico` no bloco 4, o quiz é submetido, o motor calcula (STORY-028), e o usuário vai para `/diagnosticos/{id}`.
8. **Evento `quiz_iniciado`** disparado no primeiro `Próximo` (transição bloco 1 → 2). Detalhes em STORY-035; aqui apenas chama o listener.

## Por quê

Sem o quiz, não há input para o motor. Esta é a UX mais densa do produto.

## Critérios de aceite

- [x] **CA-1 (Rota e navegação):** Item `Diagnósticos` da sidebar deixa de ser disabled. Clicar leva a uma tela "Selecione uma empresa para diagnosticar" se nenhuma estiver selecionada, ou direto para `/empresas/{id}/diagnosticos/novo` se Roberto chegou via card de empresa.
- [x] **CA-2 (4 blocos navegáveis):** Quiz tem 4 blocos com botões `Próximo` / `Voltar`. Progresso visual claro (1/4, 2/4, etc.).
- [x] **CA-3 (23 campos com labels do Anexo A):** todos os labels e helps copiados literalmente do Anexo A. Mudança de copy requer PO.
- [x] **CA-4 (Máscaras funcionando):** R$ (com decimais), dias (inteiro), %, CPF. Programador escolhe lib (alpinejs-mask, imask, mask própria — preferência: alpine js + livewire).
- [x] **CA-5 (Validações por campo):** obrigatoriedade, faixa numérica plausível (não negativo onde não faz sentido), tipo. Mensagens em PT-BR claras.
- [x] **CA-6 (Rascunho persistido em `quiz_rascunhos` — atualizado 2026-05-25):** ao apertar `Próximo`, payload parcial é salvo em uma tabela nova **`quiz_rascunhos`** (migration criada nesta estória; schema mínimo descrito no item 5 acima). **Não** em `diagnosticos`. Voltar à tela após 1h mostra os dados preenchidos. UNIQUE parcial `(usuario_id, empresa_analisada_id) WHERE deleted_at IS NULL` — 1 rascunho ativo por (Roberto, empresa).
- [x] **CA-7 (Expiração do rascunho — §6.4):** rascunho expira em 90 dias (default da spec) via coluna `expires_at`. Cron de purge de rascunhos expirados fica como **débito explícito** desta estória (não bloqueia DoD — só listar em "Notas do agente" ao final). Listagem de "Em rascunho" só mostra registros com `expires_at > now()`.
- [x] **CA-8 (Submit final):** click em `Calcular diagnóstico` → chama o motor (STORY-028) → redireciona para `/diagnosticos/{id}`. Loading state durante cálculo (skeleton ou spinner).
- [x] **CA-9 (Anti-duplo-submit — atualizado 2026-05-25 conforme IDR-010):** **não** há dedup no banco. Idempotência do **cálculo** é garantida pelo motor (golden hashes — STORY-028). Para evitar duplo submit acidental (clique duplo, F5), o botão `Calcular diagnóstico` é desabilitado após o primeiro clique até o redirect; uso de Livewire `wire:loading.attr="disabled"` ou equivalente. Se Roberto refresh-ar a tela do resultado e clicar `Refazer diagnóstico`, isso **é** uma nova emissão consciente — dois registros em `diagnosticos` com mesmo `payload_hash` são aceitáveis (IDR-010 §sub-decisão 2).
- [x] **CA-10 (Acessibilidade):** todos os campos com `<label>` associado; navegação por teclado funciona; foco visível.
- [x] **CA-11 (Mobile):** layout em viewport 360x800 sem scroll horizontal; campos ocupam largura total; teclado numérico aparece para campos monetários (input type number ou inputmode="decimal").
- [x] **CA-12 (Testes):** cobertura ≥ 80%. Pest: 1 teste por bloco (renderização + validação + persistência de rascunho); Dusk smoke: preencher quiz inteiro com dados válidos e ver redirect.
- [x] **CA-13 (Evento `quiz_iniciado`):** chamado no primeiro `Próximo`. Detalhes em STORY-035.

## Fora de escopo

- **Validações cruzadas DRE×Balanço** — STORY-034.
- **Tooltips/box de explicação** por campo — STORY-033.
- **Setores Comércio e Serviços** — onda 2.
- **Edição do quiz após cálculo** (corrigir dado errado em diagnóstico já finalizado) — pode entrar como follow-up se sobrar; fora do MVP.
- **Importação de dados de planilha** — roadmap pós-v1.
- **Upload de balanço PDF para extração automática** — roadmap §2.4.

## Dependências

- **Bloqueada por:** STORY-026 (schema), EPIC-001 (modelo Empresa), EPIC-004 (app shell + componentes).
- **Bloqueia:** STORY-028 (precisa do payload para calcular), STORY-029 (precisa de diagnóstico para mostrar relatório), STORY-033 (tooltips se ancoram nos campos), STORY-034 (validações cruzadas se ancoram nos campos), STORY-035 (eventos consomem o submit).

## Decisões já tomadas

- Quiz com 4 blocos sequenciais; **não** carrossel, **não** wizard com edição livre.
- Rascunho persistido em `diagnosticos` (mesma tabela do diagnóstico final, com `status`).
- Expiração de rascunho: 90 dias (default da spec §6.4).
- Submit duplicado é idempotente (CA-9).
- Indústria-only nesta estória.

## DoD

- CA-1 a CA-13 passam.
- Pre-push verde, pipeline CI verde, deploy em homologação validado por screencast (PO grava).
- Story commit + tag `rc.W25S1.1` (sub-tag opcional dentro da Semana 1).
- `index.json` atualizado.

## Protocolo do agente

Padrão `agent-task-format.md`. Avisar PO quando entrar em `in_review`.

## Notas do agente

### Entrega (2026-05-25 — claude-programador)

**Arquivos novos**

- `database/migrations/2026_05_25_000020_create_quiz_rascunhos_table.php` — schema do CA-6 (UUID PK, usuario_id/empresa_analisada_id FK, quiz_payload jsonb, ultimo_bloco_preenchido smallint com CHECK 1..4, expires_at, timestamps + deleted_at, índice `(usuario_id, expires_at)`, UNIQUE parcial `(usuario_id, empresa_analisada_id) WHERE deleted_at IS NULL`).
- `app/Models/QuizRascunho.php` — HasUuids + SoftDeletes + BelongsToUsuarioScope, cast `AsArrayObject` em `quiz_payload`, scope `ativos()`, helper `paraEmpresa()`.
- `app/Livewire/Diagnostico/Quiz.php` + `resources/views/livewire/diagnostico/quiz.blade.php` + 4 partials (`campo-brl`, `campo-dias`, `campo-pct`, `campo-cpf`) — núcleo do quiz, 4 blocos, máscaras Alpine inline, validação Laravel por bloco, persistência de rascunho a cada `Próximo`.
- `app/Livewire/Diagnostico/SelecionarEmpresa.php` + view — tela "Selecione uma empresa" quando Roberto entra pelo menu sem ter clicado em card.
- `app/Events/QuizIniciado.php` — evento placeholder (CA-13). Listener detalhado fica em STORY-035.
- `resources/views/diagnostico/stub-resultado.blade.php` — destino temporário do redirect; STORY-029 substitui pelo relatório minimalista.
- `tests/Feature/Livewire/Diagnostico/QuizTest.php` — 24 testes Pest cobrindo CA-1..CA-13.

**Arquivos editados**

- `routes/web.php` — três rotas autenticadas: `/diagnosticos/novo` (seletor), `/empresas/{empresa}/diagnosticos/novo` (quiz), `/diagnosticos/{diagnostico}` (stub que STORY-029 vai substituir).
- `resources/views/components/app-nav.blade.php` — item "Diagnósticos" deixou de ser disabled e aponta para `diagnosticos.selecionar`.
- `resources/views/livewire/home/minhas-empresas.blade.php` — botão "Iniciar diagnóstico" do card vira link primário para `diagnosticos.novo`.

**Divergências da STORY-027 vs. estado canônico (já formalizadas no briefing)**

- Rascunho **NÃO** em `diagnosticos` — está em `quiz_rascunhos` (IDR-010 vence).
- **Sem dedup no banco** — anti-duplo-submit é UX (`wire:loading.attr="disabled"`).
- `EmpresaAnalisada` ainda não tem coluna `setor` — Q01 é hardcoded `"industria"` (EPIC-002 só Indústria); quando outros setores entrarem, o setor migra para a empresa.

**Decisões do programador (preferências em aberto da estória)**

- 1 componente Livewire com 4 steps (preferido pela estória) — viewmodel único, `bloco_atual: int`.
- Máscaras com **Alpine.js inline** (mesmo padrão da STORY-014 — CNPJ/CPF) — sem lib externa nova; mantém footprint do bundle igual.
- Decimais BR → canônico (`number_format($float, 2, '.', '')`) só no momento do submit; properties ficam em formato BR ("1.234,56") até lá — mantém o que Roberto digitou ao retomar rascunho.
- Botão "Calcular diagnóstico" usa `wire:loading.attr="disabled"` + texto "Calculando…" (UX) — CA-9 cumprido sem hash em banco.

**Cobertura e qualidade**

- Pre-push verde: Pint ✓, Larastan ✓, Pest 533 testes / 1460 asserções / 96.0% cobertura geral (gate ≥80%).
- Domínio (`app/Domain/`) preservado em 100% (não foi tocado nesta estória — motor já entregue pela STORY-028).
- Quiz especificamente: 24 testes Pest cobrindo CA-1 (autenticação, rota, nav), CA-2 (navegação entre blocos), CA-5 (validações de tipo/faixa, regra Q20<Q09, CPF inválido), CA-6 (rascunho persistido + retomada + multi-tenant), CA-7 (expiração + banner), CA-8 (submit feliz com Diagnostico criado), CA-9 (caminho de erro do motor não persiste), CA-13 (QuizIniciado disparado 1× só na primeira transição 1→2).

**Débito explícito (CA-7 prevê)**

- **Cron de purge de rascunhos expirados** — não entregue nesta estória (a estória explicita "fica como débito explícito"). Listagem de "rascunhos ativos" já filtra via `QuizRascunho::ativos()` (expires_at > now()), então rascunhos vencidos ficam invisíveis ao Roberto. Recomendação: estória `S` no próximo backlog para criar comando `php artisan rascunhos:purgar` rodando diariamente via scheduler (soft delete dos `expires_at < now()`), com retenção de N dias antes do force-delete para diagnóstico de bugs. Pode entrar no EPIC-003 (histórico) ou virar débito do EPIC-002.

**Pendências em aberto (esperando outras estórias)**

- `Route::get('/diagnosticos/{diagnostico}')` é stub temporário renderizando `resources/views/diagnostico/stub-resultado.blade.php`. **STORY-029** substitui pelo relatório minimalista. Sem ação requerida neste momento.
- Listener real de `QuizIniciado` (escreve em `eventos_produto`) é responsabilidade da **STORY-035**.
- Tooltips por campo (§6.8) — fora de escopo nesta estória, virá pela **STORY-033**.
- Validações cruzadas DRE × Balanço (§6.6) — **STORY-034**.
- Aviso "valor atípico" para PMC/PME/PMR > 365 dias — não bloqueante, fora desta estória; o motor V1 (STORY-028) já classifica via matriz mesmo com valor > 365.

**Coordenação com STORY-029**

- O redirect final do quiz aponta para `route('diagnosticos.show', $diagnostico)` — STORY-029 reusa esse nome ao implementar o relatório minimalista. O stub atual exibe os metadados (motor_version, matrix_version, setor, gerado_em, count de indicadores) e linka de volta para `/home`.

— Programador (claude-opus-4-7)
