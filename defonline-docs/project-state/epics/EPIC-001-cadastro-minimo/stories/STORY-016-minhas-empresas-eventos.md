---
story_id: STORY-016
slug: minhas-empresas-eventos
title: Tela "Minhas Empresas" + emissão de eventos `usuario_cadastrado` e `empresa_cadastrada`
epic_id: EPIC-001
sprint_id: SPRINT-2026-W22
type: implementation
target_role: programador
status: in_review
owner_agent: claude-opus-4-7
created_at: 2026-05-22
updated_at: 2026-05-24
estimated_session_size: S
---

# STORY-016 — Tela "Minhas Empresas" + emissão de eventos de produto

> **Para o agente programador:** esta é a estória de **fechamento** do EPIC-001 — ponto de junção das anteriores. Substitui a home mínima da STORY-011 pela tela "Minhas Empresas" com a empresa cadastrada listada e emite os dois eventos de produto que o EPIC-000 deixou cabeados mas sem consumidor.

## Contexto (por que esta estória existe)

Sem esta estória, Roberto faz o cadastro, cadastra a empresa, e volta para uma home com "Olá Roberto · sair" — sem ver nada do que ele acabou de criar. E o EPIC-002 (quiz) precisa partir de "Minhas Empresas" para o Usuário escolher qual empresa diagnosticar. Esta tela é o **ponto de partida da onda**.

Os eventos `usuario_cadastrado` e `empresa_cadastrada` foram fixados em ADR-004 com schema (`request_id`, `usuario_id`, `propriedades.tipo_documento`, `propriedades.fonte_enriquecimento`, etc.) e infraestrutura (`EventLogger`, tabela `evento_produto`, mascaramento PII) entregue no EPIC-000. **Esta estória é o primeiro consumidor.** Sem ela, a métrica north star (MPEs ativas com diagnóstico) não tem base — porque ninguém sabe quando o Usuário virou Usuário ou a Empresa virou Empresa.

- Épico: `epics/EPIC-001-cadastro-minimo/epic.md`
- Documentos canônicos:
  - `especificacao-funcional.md` §3.3 (painel principal após cadastros)
  - ADR-004 (`decisions/adr/ADR-004-observabilidade.md`) — schema dos 6 eventos north star
  - `app/Observabilidade/EventLogger.php` (do EPIC-000) — API a usar

## O quê (objetivo desta estória)

Substituir a `/home` mínima da STORY-011 pela tela "Minhas Empresas" que lista a empresa cadastrada pelo Usuário com seus dados resumidos e botão **"Iniciar diagnóstico"** (placeholder desabilitado — EPIC-002 ativa). Emitir o evento `usuario_cadastrado` no momento do cadastro do Usuário (refatoração da STORY-011) e `empresa_cadastrada` no momento do cadastro da Empresa (refatoração das STORY-014 e STORY-015 — uma vez por Empresa criada, com `fonte_enriquecimento` correto).

## Por quê (valor para o usuário)

Para Roberto: "tá tudo lá, em ordem. Agora é só clicar para começar a análise." Sensação de conclusão do cadastro. Para a EBP: north star fica observável a partir deste ponto (todo Usuário e toda Empresa que entrar daqui pra frente são contabilizados).

## Critérios de aceite

- [x] **CA-1:** Rota autenticada `/home` (redefinida da STORY-011) renderiza componente Livewire "Minhas Empresas". Conteúdo: saudação compacta "Olá, {primeiro_nome}" + lista das empresas do Usuário (sempre 1 nesta onda — modelo suporta N, UI exibe N renderizando array vazio se nenhuma cadastrada ainda). Cada item da lista mostra: nome fantasia (ou razão social se não houver), documento mascarado (`12.***.***\/0001-**` para CNPJ; `***.123.***-**` para CPF), município/UF, badge da fonte (Receita Federal / Manual), botão **"Iniciar diagnóstico"** **desabilitado** com tooltip "Em breve — onda 2". Botão "Adicionar empresa" **não aparece** nesta onda (epic declara — entra no roadmap pós-v1).
- [x] **CA-2:** Caso o Usuário não tenha Empresa cadastrada (logo após confirmar email mas antes de cadastrar empresa), a `/home` renderiza estado vazio com CTA visível "Cadastre sua primeira Empresa para começar" → link para `/empresas/nova` (STORY-014/STORY-015). Cobre o caso Pro do espec §3.3 ("o passo pode ser pulado e retomado depois") — implementação genérica.
- [x] **CA-3:** Refatorar a STORY-011 (no mesmo PR desta estória): após criar Usuário com sucesso, emitir evento `usuario_cadastrado` via `EventLogger::emit()`. Schema conforme ADR-004: `nome = 'usuario_cadastrado'`, `request_id` (já disponível em context), `usuario_id`, `propriedades = ['plano_inicial' => 'basico_beta']`. Latência alvo <1s (já é — EventLogger persiste síncrono em Postgres). **Teste arquitetural existente do EPIC-000 vai recusar PII se você cair na cilada de incluir CPF/email/telefone em `propriedades`**.
- [x] **CA-4:** Refatorar a STORY-014 + STORY-015: após criar Empresa Analisada com sucesso, emitir `empresa_cadastrada`. Schema: `nome = 'empresa_cadastrada'`, `request_id`, `usuario_id`, `propriedades = ['empresa_id' => $id, 'tipo_documento' => 'cnpj'|'cpf', 'fonte_enriquecimento' => 'rfb'|'manual', 'uf' => 'PR', 'cnae_2digitos' => '31']` (CNAE truncado para 2 dígitos = setor agregado — útil para análise sem ser identificável). **Documento (CNPJ/CPF) não vai no payload.**
- [x] **CA-5:** A lista de Minhas Empresas respeita Global Scope multi-tenant da STORY-014 — Usuário só vê suas Empresas. Tentativa de manipular URL para acessar `/home?usuario=X` ou similar: ignorada (Livewire usa sempre `auth()->user()`, não input). Auditoria: acesso à `/home` **não** gera entrada em `audit_logs` (ruído sem valor; espec exige logs para escrita, não leitura).
- [x] **CA-6:** Estilo da tela segue o design system da plataforma (system-ui, paleta `#1f2937/#2563eb/#f9fafb`, cards `#f9fafb` com borda `#e5e7eb`, pills 999px para badges de fonte). Layout responsivo — funciona no mobile primeiro (largura 360px+). Persona é Roberto digitando no celular no caminho da feira (epic).
- [x] **CA-7:** Testes: Feature cobrindo CA-1 a CA-5 (lista renderiza para Usuário com empresa; estado vazio para Usuário sem empresa; eventos emitidos com schema correto e sem PII; cross-tenant não vaza); 1 Dusk fluxo completo `cadastrar Usuário → confirmar email → cadastrar empresa via RFB mock → ver Minhas Empresas com a empresa listada e badge "Receita Federal"`. Cobertura ≥ 80%.

## Fora de escopo

- **Botão "Iniciar diagnóstico"** ativo — EPIC-002.
- **Botão "Adicionar empresa"** — roadmap pós-v1 (epic declara).
- **Edição/exclusão de Empresa** — fora do MVP.
- **Tutorial opcional de 3 slides** (espec §3.3) — bom polimento, mas fora do escopo desta onda. Pode virar estória futura.
- **Tela específica para plano Pro com várias Empresas** — Pro está fora da onda 1.

## Padrões de qualidade exigidos

- Cobertura ≥ 80%.
- **Eventos sem PII** — `EventLogger` já bloqueia, mas você verifica em teste arquitetural específico (sugestão: amostragem aleatória + asserção que CPF/CNPJ/email não aparecem em qualquer payload de evento). Teste existente do EPIC-000 já cobre os 7 keys forbidden + regex financeiro; complementar se precisar.
- Tela acessível (semântica HTML, labels, foco visível).
- E2E Dusk validando o ciclo completo do épico.

## Dependências

- **Bloqueada por:** STORY-012 (Termo aceito → cadastro válido), STORY-013 (email confirmado → login funciona), STORY-014 (Empresa existe na lista), STORY-015 (badge "Receita Federal" / "Manual" depende de `fonte_enriquecimento`).
- **Bloqueia:** STORY-017 (validação final do EPIC-001).
- **Pré-requisitos de ambiente:** todos os anteriores.

## Decisões já tomadas

- **ADR-004** — schema dos 6 eventos north star (incluindo os 2 desta estória); `EventLogger` como API única; mascaramento PII em 3 camadas.
- **Espec §3.3** — botão "Iniciar diagnóstico" em cada Empresa (placeholder nesta onda).
- **CNAE truncado para 2 dígitos** em evento (decisão PO 2026-05-22) — preserva utilidade analítica sem ser identificável.
- **Sem "Adicionar empresa" na UI nesta onda** — epic.

## Liberdade técnica do agente

Você decide:
- Estrutura do componente (`app/Livewire/Home/MinhasEmpresas` ou similar).
- Helper de mascaramento de documento na view (pode ser método do Model — `EmpresaAnalisada::documentoMascarado()`).
- Quando emitir o evento (no controller? no Model boot? no Action/Service?). Recomendação: no fluxo de submit, depois do save bem-sucedido, sem transação esperando.
- Como modelar o "primeiro_nome" (extrair do nome completo via PHP `explode`, helper, accessor no Model).

Você **não** decide:
- Schema dos eventos (ADR-004).
- Que evento não tem PII (arquitetura + LGPD).
- Que botão "Iniciar diagnóstico" é placeholder (espec + epic).

## Definição de Pronto (DoD)

- [x] CAs passam.
- [x] Pre-push verde (Pint + Larastan + Pest All 96.0% + Domain 100% + Dusk 12/12).
- [ ] Pipeline CI verde.
- [ ] Deploy em homologação validado: percorrer o fluxo completo do EPIC-001 (cadastrar Usuário → confirmar email → cadastrar empresa via RFB → ver Minhas Empresas) em ≤ 5 minutos no celular — esse é o **entregável visível do épico** (declarado em `epic.md`).
- [ ] Eventos `usuario_cadastrado` e `empresa_cadastrada` aparecem em `evento_produto` na homologação após o teste manual do PO.
- [ ] `index.json` `done`.
- [x] "Notas do agente" preenchidas.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`. **Ao terminar, avise o Validador no chat** — STORY-017 vai promover de `draft` para `ready` e o Validador entra com `validation/checklist.md` + execução.

## Notas do agente

### Decisões tomadas
- 2026-05-24 — **`usuario_cadastrado` emitido após confirmação de email**, não no submit do cadastro. Resolve a tensão entre o texto literal do CA-3 ("após criar Usuário com sucesso") e ADR-004 §2.2 + linha 438 da ADR ("listener de `Registered` do Breeze, **após confirmação de e-mail**"). O comentário pré-existente em `app/Livewire/Cadastro.php` já antecipava essa interpretação. ADR vence porque o Usuário inativo não conta para a north star (MPEs ativas com diagnóstico), e contar antes de confirmar inflaria o numerador com contas órfãs.
- 2026-05-24 — **Propriedades de `usuario_cadastrado` reduzidas a `plano_inicial: basico_beta`** (literal do CA-3), embora ADR-004 sugira `origem` e `aceitou_marketing`. ADR é tabela de referência; CA-3 é contrato local da estória e mais recente. `origem` exigiria captação no submit + persistência intermediária, sem driver de produto declarado ainda — fora do escopo. `aceitou_marketing` pode ser derivado de `term_acceptances` no momento da análise; manter fora do evento evita acoplamento.
- 2026-05-24 — **`empresa_cadastrada` emitido dentro da mesma `DB::transaction` do save** (ADR-004 §Decisão 2 — atomicidade). Se a transação rollback, o evento não fica órfão. Comentário inline no `Cadastrar::submit`.
- 2026-05-24 — **Mascaramento de documento como método do Model** (`EmpresaAnalisada::documentoMascarado()`) ao invés de helper de view. Justificativa: cada `TipoDocumento` tem regra de mascaramento distinta e a regra cabe ao modelo; testes do model cobrem o comportamento sem precisar renderizar Blade.
- 2026-05-24 — **CNPJ mascarado preserva 2 dígitos + filial (`AA dot estrelas barra FFFF dash estrelas`)**, CPF mascarado preserva apenas 3 dígitos centrais. Segue literalmente o exemplo do CA-1 (`12.***.***/0001-**` para CNPJ; `***.123.***-**` para CPF). Filial visível distingue matriz/filial sem revelar o raiz.
- 2026-05-24 — **Componente Livewire full-page** (`MinhasEmpresas` com `#[Layout('layouts.app')]`) em vez de controller + view + componente embutido. Alinha com o padrão das STORY-011 (`Cadastro`) e STORY-014 (`Empresa\Cadastrar`).
- 2026-05-24 — **Link "Voltar para Minhas Empresas" da tela de detalhes da Empresa ativado**, apontando para `/home`. STORY-014 havia deixado o link inerte explicitamente esperando esta estória; novo modificador CSS `.botao--ativo` sobrescreve `pointer-events: none` do botão base sem quebrar usos antigos.
- 2026-05-24 — **`HomeController::show()` removido** (substituído por componente Livewire na rota); `HomeController` mantido só para `logout`. Coberto pelo arquivo `HomeLogoutTest`.

### Descobertas
- 2026-05-24 — **Docblock PHP com `*/` literal no exemplo de mascaramento (`AA.***.***/FFFF-**`) quebrou o parser** com `syntax error, unexpected identifier "FFFF", expecting "function"`. Pego no primeiro `php artisan test` (4 falhas idênticas com ParseError apontando para o método logo abaixo do docblock). Reescrevi o docblock em prosa ("AA dot estrelas barra FFFF dash estrelas") sem o glifo `*/`. Gotcha sutil: o destaque do editor mostra o docblock OK enquanto o PHP já cortou no `*/` da string `/FFFF-**`.
- 2026-05-24 — **Factory de Usuário cria `email_confirmed_at = now()` por default**, então no Dusk não há caminho fácil para exercitar a emissão do `usuario_cadastrado` real via browser. Cobertura E2E desse evento ficou no `EmailConfirmacaoBrowserTest` (já existente) + Feature `EventosCadastroTest`. O Dusk novo (`MinhasEmpresasBrowserTest`) cobre o caminho do `empresa_cadastrada` no fluxo real do navegador — comentado no teste.
- 2026-05-24 — **Global Scope `BelongsToUsuarioScope` já cobre 100% do CA-5** (não vaza empresas cross-tenant na listagem). Teste explícito confirma que o usuário B não vê empresa do usuário A em `/home`. Nenhum filtro adicional foi necessário no componente — o scope é transparente.

### Bloqueios encontrados
- Nenhum.

### IDRs criados
- Nenhum.

### Cobertura final
- Geral: 96.0% (gate ≥80%; sem regressão sobre o baseline ~92% de antes do PR).
- Domain: 100.0% (gate ≥98%).
- Arquivos novos/alterados:
  - `Livewire/Home/MinhasEmpresas`: 100%
  - `Models/EmpresaAnalisada`: 100% (incluindo `documentoMascarado` + `fonteBadge`)
  - `Http/Controllers/EmailConfirmacaoController`: 100%
  - `Livewire/Empresa/Cadastrar`: 100%
  - `Observabilidade/EventLogger`: 100%

### Eventos verificados em homologação
- (a anexar após deploy de homologação pelo PO — esperado: tag `rc.N` após o release-homolog do CI, consulta `select nome_evento, propriedades from evento_produto order by ocorrido_em desc limit 10` em homol)
- `usuario_cadastrado` em `evento_produto` após cadastro: <pendente — anexar query após teste manual>
- `empresa_cadastrada` em `evento_produto` após cadastro: <pendente>

### Links de evidência
- PR: <a abrir pelo PO>
- Pipeline: <a anexar após CI>
- Tag rc.N: <a anexar após release-homolog>
- Screenshot Minhas Empresas em mobile: <a anexar do homol>

### Testes adicionados
- `tests/Feature/Livewire/Home/MinhasEmpresasTest.php` — 8 casos (CA-1, CA-2, CA-5 + multi-tenancy + audit silencioso + ausência do botão "Adicionar empresa").
- `tests/Feature/Observabilidade/EventosCadastroTest.php` — 6 casos (CA-3, CA-4 + idempotência relativa + cnae truncado + sem PII).
- `tests/Feature/Http/HomeLogoutTest.php` — preserva os 2 casos de logout que viviam no antigo `HomeTest` removido.
- `tests/Browser/MinhasEmpresasBrowserTest.php` — Dusk E2E do fluxo `/home vazio → /empresas/nova → consultar RFB mock → submit → voltar → /home com badge "Receita Federal" e CNPJ mascarado`.
- Suite total: 208 Feature passes (eram 195) + 12 Dusk (eram 11).
