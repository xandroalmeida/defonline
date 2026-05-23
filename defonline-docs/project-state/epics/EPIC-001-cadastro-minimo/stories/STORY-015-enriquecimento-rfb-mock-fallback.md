---
story_id: STORY-015
slug: enriquecimento-rfb-mock-fallback
title: Enriquecimento de Empresa via API RFB (mock inicial + fallback transparente)
epic_id: EPIC-001
sprint_id: null
type: implementation
target_role: programador
status: ready
owner_agent: null
created_at: 2026-05-22
updated_at: 2026-05-23
estimated_session_size: M
---

# STORY-015 — Enriquecimento de Empresa via API RFB (mock + fallback)

> **Para o agente programador:** os provedores reais da API RFB já foram decididos pelo PO — **`cnpja` (https://cnpja.com/) e `receitaws` (https://receitaws.com.br/)**, ambos suportados em paralelo via abstração (IDR-004 de 2026-05-23). A ativação dos provedores reais ficou na **STORY-018**. Esta estória continua entregando o **caminho técnico completo da abstração** (interface `RfbCnpjClient`, DTO, fallback transparente, monitoramento, cache) implementado contra um mock determinístico — a NRF §3.1 explicitamente autoriza começar com mock. Quando STORY-018 for executada, **nenhuma mudança de código desta estória é necessária**; apenas troca de configuração.

## Contexto (por que esta estória existe)

Espec §3.3 Passo 2 diz: "Para CNPJ, o sistema consulta a API da RFB e pré-preenche Razão Social, Nome Fantasia, Data de Fundação, CNAE Principal, Município, UF e Situação Cadastral; o Usuário apenas confirma." É **dramaticamente menos atritado** que digitar 7 campos no celular. Mas a espec também exige: "Falha na API não impede o cadastro (preenchimento manual com aviso)" — porque API externa falha; ponto.

Esta estória entrega o caminho feliz (CNPJ → consulta → pré-preenche) **e** o fallback (API falhou → cai no form manual da STORY-014, com aviso explícito).

- Épico: `epics/EPIC-001-cadastro-minimo/epic.md`
- Documentos canônicos:
  - `especificacao-funcional.md` §3.3 Passo 2 (consulta RFB + pré-preenchimento)
  - `requisitos-nao-funcionais-e-juridicos.md` §3.1 (RFB com fallback robusto, dois provedores reais via abstração + rate-limit por provedor, monitoramento >5% erro)
  - **IDR-004** — abstração `RfbCnpjClient` com `cnpja` e `receitaws` selecionáveis via config + rate-limit por provedor (decisão que substitui o antigo `[A DEFINIR]`).
  - ADR-002 — síncrono (consulta na hora do usuário) ou enfileirado (worker resolve em background)? Você decide — recomendação minha: **síncrono com timeout curto + fallback** (UX exige resposta rápida).
  - ADR-004 — `business_metrics` para `rfb_consulta_sucesso` / `_falhou` / `_timeout`.

## O quê (objetivo desta estória)

Adicionar à tela `/empresas/nova` (STORY-014) um caminho extra: ao digitar um CNPJ válido (14 dígitos com DV), botão "Consultar Receita" dispara consulta. Sucesso: pré-preenche os campos textuais (razão social, nome fantasia, CNAE, município, UF, situação cadastral, data fundação) e marca `fonte_enriquecimento = 'rfb'` + `enriquecido_at = now()` no submit final. Falha (timeout, erro 5xx, CNPJ inexistente): mantém os campos vazios, mostra aviso visível "Não conseguimos consultar a Receita agora — preencha os campos manualmente." e segue o fluxo da STORY-014.

## Por quê (valor para o usuário)

Para Roberto: ele digita 14 dígitos do CNPJ e o resto aparece preenchido. **5 minutos viram 30 segundos.** Métrica primária do EPIC-001 (≥80% de conclusão do cadastro) sobe muito quando o fluxo encurta.

## Critérios de aceite

- [ ] **CA-1:** Existe interface `App\Services\Rfb\RfbCnpjClient` (PHP interface) com método `consultarCnpj(string $cnpj): RfbCnpjResult` (DTO com `razao_social`, `nome_fantasia`, `cnae`, `municipio`, `uf`, `situacao_cadastral`, `data_fundacao`, `fonte_provedor`, `consultado_at`). Implementação default `App\Services\Rfb\MockRfbCnpjClient` que devolve dados sintéticos a partir do CNPJ (regras simples — você decide, sugestão: hash do CNPJ vira seed determinístico; pelo menos um CNPJ retorna "erro" e outro retorna "timeout" para teste). Implementação real fica para futura troca via `config/services.php` + `AppServiceProvider`.
- [ ] **CA-2:** Form Livewire de `/empresas/nova` ganha botão "Consultar Receita" ao lado do campo CNPJ. Habilitado apenas quando `tipo_documento = 'cnpj'` e DV do CNPJ está válido. Click dispara método Livewire que: (i) valida DV; (ii) chama `RfbCnpjClient::consultarCnpj()` com timeout de **5 segundos** (Laravel HTTP `timeout(5)` ou equivalente); (iii) em sucesso, preenche campos do form e marca flag `enriquecido = true`; (iv) em qualquer falha, mantém campos como estão, mostra alerta amarelo "Não conseguimos consultar a Receita agora — preencha os campos manualmente."
- [ ] **CA-3:** Submit do form com flag `enriquecido = true` salva `fonte_enriquecimento = 'rfb'` e `enriquecido_at = $resultado->consultado_at`. Submit sem a flag (ou após fallback) salva `'manual'`/`null`. Tela read-only `/empresas/{id}` exibe badge **"Fonte: Receita Federal"** ou **"Fonte: preenchimento manual"** conforme o caso.
- [ ] **CA-4:** Cada consulta emite **métrica em `business_metrics`** (tabela existente do EPIC-000) com `kind: 'rfb_consulta'` e `status: 'sucesso' | 'cnpj_inexistente' | 'timeout' | 'erro_5xx' | 'erro_rede'`. Latência (ms) registrada. **Sem CNPJ em log** (PII conforme `security-discipline.md`); apenas o hash SHA-256 do CNPJ no `audit_logs` se necessário para correlação.
- [ ] **CA-5:** **Alerta de monitoramento** (Telegram via ADR-004) dispara quando taxa de erro > 5% em janela de 10 min com no mínimo 5 consultas. Job/comando agendado (sugestão: `MonitorarRfbErrorRate` como artisan command rodado pelo scheduler a cada 5 min) faz a checagem. Implementação simples: query agregada em `business_metrics` + chamada ao canal Telegram já configurado pelo EPIC-000.
- [ ] **CA-6:** Configuração: `config/services.php` ganha bloco `rfb` com `provider` (default `'mock'`; valores aceitos `mock | cnpja | receitaws` — conforme IDR-004), `timeout` (default `5`), `cache_ttl` (default `300` — 5 minutos; mesmo CNPJ consultado 2× em 5 min usa cache para evitar custo no provedor real). Cache key `rfb:cnpj:{sha256(cnpj)}`. `MockRfbCnpjClient` ignora cache (sempre retorna fresh) para facilitar testes; clientes reais (entregues pela STORY-018) consultam o cache. O bloco já deve **prever o sub-bloco `providers.<provider>` com `base_url`, `api_key` e `rate_limit_per_minute`** mesmo que vazio nesta estória — schema completo está descrito na IDR-004; isso evita rework na STORY-018.
- [ ] **CA-7:** Testes: UnitPure do contrato do DTO e do mock (cenários sucesso/timeout/inexistente); Feature cobrindo CA-2 (botão dispara consulta, sucesso pré-preenche, falha mostra alerta), CA-3 (`fonte_enriquecimento` correto após submit), CA-4 (métrica registrada), CA-5 (comando de monitoramento detecta taxa >5%); 1 Dusk percorrendo `cadastrar empresa com CNPJ → clicar Consultar Receita → ver pré-preenchimento → submit → ver badge "Receita Federal"`. Cobertura ≥ 80% geral; ≥ 98% se isolar lógica em `app/Domain/Documentos` ou `app/Domain/Rfb`.

## Fora de escopo

- **Implementação dos provedores reais (`cnpja`, `receitaws`)** — decididos no IDR-004 (2026-05-23) e entregues pela **STORY-018**. Esta estória entrega só a abstração + mock + caminho técnico.
- **Rate-limit por provedor** — schema do `config/services.php` é previsto aqui (CA-6), mas o uso do `RateLimiter` em cima da abstração fica para a STORY-018 (sem provedor real, não há o que limitar).
- **Consulta de CPF da Empresa autônoma na RFB** — sem API pública gratuita confiável; mantém preenchimento manual sempre para `tipo_documento = 'cpf'`.
- **Retry com backoff exponencial** — não, fallback transparente é melhor UX. Se quiser retry, fica como bugfix futuro.
- **Cache em Redis** — usar cache default Laravel (Postgres ou file, conforme ADR-005). Redis fica para roadmap se ficar gargalo.
- **Webhooks de atualização da Receita** — fora do MVP.

## Padrões de qualidade exigidos

- Cobertura ≥ 80% geral; ≥ 98% em qualquer lógica isolada em `app/Domain/**`.
- **CNPJ é PII** — nunca em log/evento sem mascarar. Hash SHA-256 ok para correlação.
- **Timeout curto** (≤ 5s) — UX não tolera mais; espec implícita "cadastro em ≤ 5 min no celular".
- **Fallback transparente** — falha não pode parar o cadastro.

## Dependências

- **Bloqueada por:** STORY-014 (form manual existe e funciona).
- **Bloqueia:** STORY-016 (lista usa Empresa enriquecida; emissão de evento `empresa_cadastrada` carrega `fonte_enriquecimento` no payload).
- **Pré-requisitos de ambiente:** mesmos do EPIC-000 + EPIC-001.

## Decisões já tomadas

- **Começar com mock** (NRF §3.1 autoriza).
- **Provedores reais decididos:** `cnpja` (https://cnpja.com/) e `receitaws` (https://receitaws.com.br/), ambos suportados em paralelo via abstração — IDR-004 (2026-05-23). Ativação dos clientes reais entregue pela STORY-018.
- **Seleção do provedor via configuração** (`config/services.php` → `rfb.provider`) — IDR-004.
- **Rate-limit por provedor configurável** (`config/services.php` → `rfb.providers.<provider>.rate_limit_per_minute`) — IDR-004. Implementação do RateLimiter fica para a STORY-018.
- **Fallback transparente para preenchimento manual** (NRF §3.1).
- **Monitoramento >5% erro / 10 min, com dimensão `provider` em `business_metrics`** (NRF §3.1, IDR-004).
- **Telegram para alertas** (ADR-004).

## Liberdade técnica do agente

Você decide:
- Estrutura: `app/Services/Rfb/`, `app/Domain/Rfb/` (se quiser puxar lógica de domínio puro lá), DTO como classe simples ou Carbon `Spatie\LaravelData`.
- Como gerar dados sintéticos no mock (regras simples; cenários de erro deterministicamente acionáveis por CNPJ específico em teste).
- Estrutura do comando de monitoramento.
- TTL exato do cache, dentro do bom senso (300s parece razoável; mais que 1h é arriscado por mudanças de situação cadastral).

Você **não** decide:
- Que tem fallback manual (NRF).
- Que tem monitoramento (NRF).
- Quais são os provedores reais suportados (IDR-004: `cnpja` e `receitaws`).
- Que rate-limit é por provedor (IDR-004).
- Schema do bloco `config/services.php → rfb` (IDR-004 prescreve `provider`, `timeout`, `cache_ttl`, `providers.<provider>.base_url`, `providers.<provider>.api_key`, `providers.<provider>.rate_limit_per_minute`).

## Definição de Pronto (DoD)

- [ ] CAs passam.
- [ ] Pre-push verde.
- [ ] Pipeline CI verde.
- [ ] Deploy em homologação validado: clicar "Consultar Receita" com CNPJ que o mock conhece (pré-preenche), com CNPJ que dispara timeout no mock (alerta amarelo, form em branco), submeter ambos os casos, ver badges corretos.
- [ ] `index.json` `done`.
- [ ] "Notas do agente" preenchidas + nota explícita sobre como trocar de mock para provedor real (uma frase).

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`.

## Notas do agente

### Decisões tomadas
- <data> — <decisão>

### Descobertas
- <data> — <gotcha>

### Bloqueios encontrados
- <data> — <bloqueio>

### IDRs criados
- IDR-XXX — <título>

### Como trocar de mock para provedor real (CA-6 nota)
- Os provedores reais (`cnpja`, `receitaws`) e o schema de configuração já estão decididos no **IDR-004** e serão **implementados na STORY-018** — não nesta estória.
- Esta estória precisa apenas deixar o bloco `config/services.php → rfb` no formato canônico da IDR-004 (incluindo o sub-bloco `providers.<provider>` mesmo que vazio).
- O bind condicional em `AppServiceProvider` pode ser implementado já apontando para `MockRfbCnpjClient` em todas as opções (`mock | cnpja | receitaws`) — a STORY-018 substitui os dois últimos.

### Cobertura final
- Geral: <%>
- `app/Domain/**`: <%>

### Links de evidência
- PR: <url>
- Pipeline: <url>
- Tag rc.N: <vX.Y.Z-rc.N>
