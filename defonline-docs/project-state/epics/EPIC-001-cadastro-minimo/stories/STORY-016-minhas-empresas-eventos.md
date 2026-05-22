---
story_id: STORY-016
slug: minhas-empresas-eventos
title: Tela "Minhas Empresas" + emissão de eventos `usuario_cadastrado` e `empresa_cadastrada`
epic_id: EPIC-001
sprint_id: null
type: implementation
target_role: programador
status: ready
owner_agent: null
created_at: 2026-05-22
updated_at: 2026-05-22
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

- [ ] **CA-1:** Rota autenticada `/home` (redefinida da STORY-011) renderiza componente Livewire "Minhas Empresas". Conteúdo: saudação compacta "Olá, {primeiro_nome}" + lista das empresas do Usuário (sempre 1 nesta onda — modelo suporta N, UI exibe N renderizando array vazio se nenhuma cadastrada ainda). Cada item da lista mostra: nome fantasia (ou razão social se não houver), documento mascarado (`12.***.***\/0001-**` para CNPJ; `***.123.***-**` para CPF), município/UF, badge da fonte (Receita Federal / Manual), botão **"Iniciar diagnóstico"** **desabilitado** com tooltip "Em breve — onda 2". Botão "Adicionar empresa" **não aparece** nesta onda (epic declara — entra no roadmap pós-v1).
- [ ] **CA-2:** Caso o Usuário não tenha Empresa cadastrada (logo após confirmar email mas antes de cadastrar empresa), a `/home` renderiza estado vazio com CTA visível "Cadastre sua primeira Empresa para começar" → link para `/empresas/nova` (STORY-014/STORY-015). Cobre o caso Pro do espec §3.3 ("o passo pode ser pulado e retomado depois") — implementação genérica.
- [ ] **CA-3:** Refatorar a STORY-011 (no mesmo PR desta estória): após criar Usuário com sucesso, emitir evento `usuario_cadastrado` via `EventLogger::emit()`. Schema conforme ADR-004: `nome = 'usuario_cadastrado'`, `request_id` (já disponível em context), `usuario_id`, `propriedades = ['plano_inicial' => 'basico_beta']`. Latência alvo <1s (já é — EventLogger persiste síncrono em Postgres). **Teste arquitetural existente do EPIC-000 vai recusar PII se você cair na cilada de incluir CPF/email/telefone em `propriedades`**.
- [ ] **CA-4:** Refatorar a STORY-014 + STORY-015: após criar Empresa Analisada com sucesso, emitir `empresa_cadastrada`. Schema: `nome = 'empresa_cadastrada'`, `request_id`, `usuario_id`, `propriedades = ['empresa_id' => $id, 'tipo_documento' => 'cnpj'|'cpf', 'fonte_enriquecimento' => 'rfb'|'manual', 'uf' => 'PR', 'cnae_2digitos' => '31']` (CNAE truncado para 2 dígitos = setor agregado — útil para análise sem ser identificável). **Documento (CNPJ/CPF) não vai no payload.**
- [ ] **CA-5:** A lista de Minhas Empresas respeita Global Scope multi-tenant da STORY-014 — Usuário só vê suas Empresas. Tentativa de manipular URL para acessar `/home?usuario=X` ou similar: ignorada (Livewire usa sempre `auth()->user()`, não input). Auditoria: acesso à `/home` **não** gera entrada em `audit_logs` (ruído sem valor; espec exige logs para escrita, não leitura).
- [ ] **CA-6:** Estilo da tela segue o design system da plataforma (system-ui, paleta `#1f2937/#2563eb/#f9fafb`, cards `#f9fafb` com borda `#e5e7eb`, pills 999px para badges de fonte). Layout responsivo — funciona no mobile primeiro (largura 360px+). Persona é Roberto digitando no celular no caminho da feira (epic).
- [ ] **CA-7:** Testes: Feature cobrindo CA-1 a CA-5 (lista renderiza para Usuário com empresa; estado vazio para Usuário sem empresa; eventos emitidos com schema correto e sem PII; cross-tenant não vaza); 1 Dusk fluxo completo `cadastrar Usuário → confirmar email → cadastrar empresa via RFB mock → ver Minhas Empresas com a empresa listada e badge "Receita Federal"`. Cobertura ≥ 80%.

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

- [ ] CAs passam.
- [ ] Pre-push verde.
- [ ] Pipeline CI verde.
- [ ] Deploy em homologação validado: percorrer o fluxo completo do EPIC-001 (cadastrar Usuário → confirmar email → cadastrar empresa via RFB → ver Minhas Empresas) em ≤ 5 minutos no celular — esse é o **entregável visível do épico** (declarado em `epic.md`).
- [ ] Eventos `usuario_cadastrado` e `empresa_cadastrada` aparecem em `evento_produto` na homologação após o teste manual do PO.
- [ ] `index.json` `done`.
- [ ] "Notas do agente" preenchidas.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`. **Ao terminar, avise o Validador no chat** — STORY-017 vai promover de `draft` para `ready` e o Validador entra com `validation/checklist.md` + execução.

## Notas do agente

### Decisões tomadas
- <data> — <decisão>

### Descobertas
- <data> — <gotcha>

### Bloqueios encontrados
- <data> — <bloqueio>

### IDRs criados
- IDR-XXX — <título>

### Cobertura final
- Geral: <%>

### Eventos verificados em homologação
- `usuario_cadastrado` em `evento_produto` após cadastro: <evidência — query, screenshot, log>
- `empresa_cadastrada` em `evento_produto` após cadastro: <evidência>

### Links de evidência
- PR: <url>
- Pipeline: <url>
- Tag rc.N: <vX.Y.Z-rc.N>
- Screenshot Minhas Empresas em mobile: <anexar>
