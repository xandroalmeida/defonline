---
epic_id: EPIC-001
type: validation-checklist
created_at: 2026-05-24
---

# Checklist de validação — EPIC-001 Cadastro mínimo

> **Para o validador:** execute cada item em ordem. Para cada um, registre status `pass | fail | n/a` e evidência (link, screenshot, log, query SQL, comando reproduzível). Não invente resultados. Em caso de falha, **não tente consertar** — registre e devolva para o PO.

**Nota sobre o EPIC-001:** este épico entrega o **primeiro fluxo de produto com PII real** (Usuário com CPF/email/telefone, Empresa Analisada com CNPJ/CPF), o **primeiro consumidor real dos eventos de produto** (`usuario_cadastrado`, `empresa_cadastrada`), o **primeiro provedor de email transacional real em homologação** (Resend, via IDR-007) e a **primeira integração externa** (RFB via abstração `cnpja` + `receitaws`, IDR-004/005/006, com mock determinístico autorizado por NRF §3.1 e wiring real entregue pela STORY-018). O checklist abaixo cobre cada um desses pontos com a granularidade do precedente `EPIC-000-foundation/validation/checklist.md`.

A STORY-018 entregou os provedores reais da RFB, mas **não bloqueia esta validação** — IDR-004 mantém `mock` como default em homologação até decisão operacional explícita. Itens da RFB validam contra o provedor que estiver ativo em homol (mock por padrão).

## 1. Walking skeleton de cadastro de Usuário + login + home autenticada (STORY-011)

- [ ] **1.1** Rota pública `/cadastro` renderiza form com **CPF mascarado, nome, email, senha, confirmação de senha, telefone WhatsApp mascarado** em homologação.
  - **Como verificar:** abrir https://defonline.xandrix.com.br/cadastro em navegador, inspecionar formulário.
  - **Evidência esperada:** screenshot do form renderizado com todos os campos visíveis e máscaras ativas em CPF/telefone.
  - **Critério de aprovação:** todos os campos exigidos pelo CA-1 da STORY-011 presentes e funcionais.

- [ ] **1.2** Submit com CPF inválido, email inválido, CPF duplicado ou email duplicado retorna mensagem específica por campo (e mensagem genérica "este dado já está em uso" para duplicação — sem vazar inscritos).
  - **Como verificar:** tentar 4 submits em homol: (a) CPF `111.111.111-11`; (b) email `xyz` sem @; (c) CPF de Usuário pré-existente; (d) email de Usuário pré-existente.
  - **Evidência esperada:** screenshots ou cURL output mostrando as 4 mensagens.
  - **Critério de aprovação:** cada caso mostra a mensagem correta sem revelar existência de cadastro anterior por inferência.

- [ ] **1.3** Rota `/login` autentica credenciais válidas e gera sessão Laravel; credenciais inválidas mostram mensagem genérica "credenciais inválidas".
  - **Como verificar:** logar com usuário pré-existente confirmado; tentar login com email inexistente e com senha errada; observar redirect para `/home` no caso válido.
  - **Evidência esperada:** screenshot ou trace de cookie de sessão + redirect 302 para `/home`; screenshot da mensagem genérica nos dois casos de falha.
  - **Critério de aprovação:** distinção entre "email inexistente" e "senha errada" **não** aparece na UI.

- [ ] **1.4** Rota `/home` é protegida por middleware `auth` — acesso sem sessão redireciona para `/login`.
  - **Como verificar:** `curl -I https://defonline.xandrix.com.br/home` sem cookie; tentar via browser anônimo.
  - **Evidência esperada:** HTTP 302 `Location: /login` na resposta sem sessão.
  - **Critério de aprovação:** zero acesso à home sem autenticação.

- [ ] **1.5** Throttling de login (5/min por IP+email, ADR-001) ativo em homologação.
  - **Como verificar:** disparar 6 tentativas seguidas de login em 60s a partir do mesmo IP+email; observar HTTP 429 na 6ª.
  - **Evidência esperada:** log da 6ª tentativa retornando 429.
  - **Critério de aprovação:** rate-limit dispara conforme ADR-001 §Auth.

- [ ] **1.6** Senha persistida com hash bcrypt/argon2 (jamais em texto claro nem em log).
  - **Como verificar:** consultar `SELECT senha_hash FROM usuarios LIMIT 1` em homol; grep "password" / "senha" em logs do container web nas últimas 24h.
  - **Evidência esperada:** hash `$2y$...` na coluna; logs sem senha em texto claro.
  - **Critério de aprovação:** zero ocorrência de senha em log; hash bcrypt no banco.

## 2. Termo de Adesão + consentimento LGPD (STORY-012)

- [ ] **2.1** Form de `/cadastro` exibe 3 checkboxes — Termo de Adesão (obrigatório), Política de Privacidade/LGPD (obrigatório), opt-in marketing (opcional, default desmarcado) — com links para as páginas de texto.
  - **Como verificar:** abrir `/cadastro` em homol; inspecionar checkboxes; clicar nos links e ver banner "TEXTO PLACEHOLDER" + versão `v1-placeholder`.
  - **Evidência esperada:** screenshot do form com 3 checkboxes; screenshots de `/termos/termo-adesao` e `/termos/politica-privacidade`.
  - **Critério de aprovação:** ordem correta + banner placeholder visível + canal `dpo@ebparcerias.com` presente.

- [ ] **2.2** Submit sem aceite de Termo ou LGPD falha com mensagem específica por campo.
  - **Como verificar:** preencher form sem marcar Termo; preencher sem marcar LGPD; observar bloqueio.
  - **Evidência esperada:** screenshots das duas mensagens.
  - **Critério de aprovação:** ambos os submits bloqueados, sem criação de Usuário (verificar `usuarios` no Postgres pós-tentativa).

- [ ] **2.3** Submit válido cria 3 registros em `term_acceptances` (mesmo para marketing recusado — `aceito=false`).
  - **Como verificar:** completar um cadastro novo de teste em homol; `SELECT termo_tipo, aceito, versao, conteudo_hash FROM term_acceptances WHERE usuario_id = :id ORDER BY aceito_at`.
  - **Evidência esperada:** 3 linhas — `termo_adesao=true`, `lgpd=true`, `marketing=false` (default) com `versao='v1-placeholder'` e `conteudo_hash` SHA-256 preenchido.
  - **Critério de aprovação:** 3 rows + `ip` + `user_agent` preservados na tabela.

- [ ] **2.4** Tabela `term_acceptances` é **append-only** (update/delete bloqueados na app e no role do banco).
  - **Como verificar:** tentar `UPDATE term_acceptances SET aceito = true WHERE id = :id` via psql como role `defonline_app` em homol; rodar teste arquitetural Pest local.
  - **Evidência esperada:** `ERROR: permission denied for table term_acceptances` no UPDATE; teste arquitetural verde.
  - **Critério de aprovação:** banco rejeita; aplicação rejeita; teste arquitetural cobre.

- [ ] **2.5** Aceite gera `audit_logs` (`action: termo.aceito` ou `termo.recusado`) com `termo_tipo` + `versao`, **sem `ip`/`user_agent` no log** (esses ficam só na tabela `term_acceptances`).
  - **Como verificar:** após cadastro de teste, `SELECT action, context FROM audit_logs WHERE usuario_id = :id ORDER BY created_at DESC LIMIT 5`.
  - **Evidência esperada:** 3 entradas (2 `termo.aceito` + 1 `termo.recusado` para marketing) com `context.termo_tipo` + `context.versao`; ausência das chaves `ip` e `user_agent` em `context`.
  - **Critério de aprovação:** minimização de PII em log respeitada.

## 3. Confirmação de email por link assinado + provedor Resend em homologação (STORY-013, IDR-007)

- [ ] **3.1** Cadastro novo cria Usuário com `email_confirmed_at = null` e **enfileira** (não envia síncrono) job `EnviarEmailConfirmacao`.
  - **Como verificar:** completar cadastro em homol; `SELECT email_confirmed_at FROM usuarios WHERE id = :id` + `SELECT payload FROM jobs WHERE queue = 'default' ORDER BY id DESC LIMIT 1`.
  - **Evidência esperada:** `email_confirmed_at NULL`; job enfileirado com o usuário.
  - **Critério de aprovação:** assincronismo confirmado; submit do cadastro não bloqueia esperando SMTP.

- [ ] **3.2** Email é entregue via **Resend** em homologação (IDR-007) com link assinado de TTL 60 min.
  - **Como verificar:** dashboard Resend (https://resend.com) → eventos recentes; abrir email recebido na caixa real configurada; inspecionar link.
  - **Evidência esperada:** evento `email.delivered` no Resend para o cadastro de teste; URL do link contém `signature=` e `expires=`; TTL = 60 min a partir de envio.
  - **Critério de aprovação:** Resend entregando + link assinado válido. **Se Resend ainda não estiver com domínio verificado em homol no momento da validação:** registrar como `fail` ou `n/a justificado` (IDR-007 documenta que a chave estava em provisionamento; consultar PO antes de decidir).

- [ ] **3.3** Login bloqueia Usuário com `email_confirmed_at = null` com mensagem explícita "Confirme seu email antes de fazer login" + email mascarado + botão de reenvio.
  - **Como verificar:** tentar logar com Usuário recém-cadastrado (sem clicar no link); observar mensagem.
  - **Evidência esperada:** screenshot da mensagem com email mascarado (`a***@i***.example`) + botão "Reenviar email".
  - **Critério de aprovação:** login bloqueado mesmo com credenciais corretas; mensagem específica (CA-4 da STORY-013).

- [ ] **3.4** Clique no link assinado válido marca `email_confirmed_at = now()` e mostra tela `/email/confirmado`.
  - **Como verificar:** clicar no link recebido por email; observar redirect.
  - **Evidência esperada:** screenshot da tela `/email/confirmado`; `SELECT email_confirmed_at FROM usuarios WHERE id = :id` retorna timestamp.
  - **Critério de aprovação:** ativação efetiva; login passa a funcionar após confirmação.

- [ ] **3.5** Link expirado ou já usado mostra `/email/confirmar-erro` com botão "Reenviar email".
  - **Como verificar:** forçar expiração (esperar 60 min ou usar URL com timestamp passado); clicar.
  - **Evidência esperada:** screenshot da tela de erro com botão de reenvio.
  - **Critério de aprovação:** UX clara em erro + caminho de recuperação.

- [ ] **3.6** Throttling de reenvio (3/hora por hash do email) ativo + mensagem genérica que não enumera cadastros.
  - **Como verificar:** disparar 4 reenvios seguidos para o mesmo email em homol; tentar reenvio para email inexistente.
  - **Evidência esperada:** mesma mensagem "Se este email estiver cadastrado, enviamos um novo link" em todos os casos (existente, throttled, inexistente, já confirmado); 4ª chamada throttled.
  - **Critério de aprovação:** anti-enumeração respeitado (`security-discipline.md`).

- [ ] **3.7** `MAIL_FROM_ADDRESS` configurado separadamente de `app_domain` em homol (fix commit `4862083`).
  - **Como verificar:** `docker exec defonline-web env | grep MAIL_FROM_ADDRESS` em homol; conferir `vars.yml` no playbook Ansible.
  - **Evidência esperada:** `MAIL_FROM_ADDRESS` aponta para domínio verificado em Resend; não é derivado automático do `APP_URL`.
  - **Critério de aprovação:** envio passa pela verificação de domínio do Resend sem rejeição.

## 4. Cadastro de Empresa Analisada — manual + RFB com fallback (STORY-014, STORY-015, STORY-018)

- [ ] **4.1** Rota autenticada `/empresas/nova` renderiza form com todos os campos da STORY-014 (tipo_documento, documento, razão social, nome fantasia, CNAE, município, UF, situação cadastral, data fundação).
  - **Como verificar:** logar em homol; abrir `/empresas/nova`; inspecionar form.
  - **Evidência esperada:** screenshot do form completo com 27 UFs no dropdown.
  - **Critério de aprovação:** todos os campos do CA-2 STORY-014 presentes; máscara muda conforme tipo (CNPJ vs CPF).

- [ ] **4.2** Validações de DV de CPF e CNPJ bloqueiam documentos inválidos.
  - **Como verificar:** submeter CNPJ `00.000.000/0000-00`; CPF `123.456.789-00`; observar erros.
  - **Evidência esperada:** screenshots das mensagens por campo.
  - **Critério de aprovação:** DV validado tanto local quanto em homol; data futura também rejeitada.

- [ ] **4.3** Cadastro manual cria registro com `fonte_enriquecimento = 'manual'` e `enriquecido_at = null`; tela read-only mostra badge "Manual".
  - **Como verificar:** cadastrar empresa manualmente em homol; `SELECT fonte_enriquecimento, enriquecido_at FROM empresas_analisadas WHERE id = :id`; abrir `/empresas/:id`.
  - **Evidência esperada:** row no Postgres + screenshot da tela read-only com badge "Manual".
  - **Critério de aprovação:** persistência correta + UI consistente.

- [ ] **4.4** Botão "Consultar Receita" da STORY-015 habilita só com `tipo_documento = cnpj` + DV válido; sucesso pré-preenche campos e marca `enriquecido = true`.
  - **Como verificar:** em homol, escolher tipo CNPJ, digitar CNPJ de teste válido (provedor mock se ainda padrão; ou CNPJ real se cnpja ativo); clicar "Consultar Receita".
  - **Evidência esperada:** screenshot do form pré-preenchido + screenshot da tela read-only pós-submit com badge "Receita Federal".
  - **Critério de aprovação:** caminho feliz funcional; `fonte_enriquecimento = 'rfb'` na linha gravada.

- [ ] **4.5** Falha RFB (timeout, erro 5xx, CNPJ inexistente) cai em fallback transparente — mantém form manual com alerta amarelo "Não conseguimos consultar a Receita agora — preencha os campos manualmente."
  - **Como verificar:** em homol com provedor mock, usar CNPJ com prefixo `99` (timeout) e `00` (cnpj_inexistente) — DVs gerados pela `EmpresaAnalisadaFactory::cnpjComRaiz()`. Se provedor real cnpja, usar CNPJ inexistente.
  - **Evidência esperada:** screenshots dos dois casos com alerta amarelo e form em branco.
  - **Critério de aprovação:** fallback respeita NRF §3.1.

- [ ] **4.6** Multi-tenancy via Global Scope (ADR-003) — Usuário só vê suas Empresas.
  - **Como verificar:** logar como Usuário A; tentar `GET /empresas/:id` onde `:id` pertence a Usuário B; observar resposta.
  - **Evidência esperada:** HTTP 403 (per STORY-014 CA-4) + entrada `audit_logs.action = 'empresa.acesso_negado'`.
  - **Critério de aprovação:** isolamento de tenant respeitado. **Nota:** a estória prescreve 403, ADR-003/NRF §4.3 prescrevem 404 (divergência registrada na nota do `index.json` de STORY-014). Para esta validação, **403 é o critério vigente** — validador apenas registra a discrepância em "Observações úteis ao PO" sem reprovar por isso.

- [ ] **4.7** `audit_logs` registra `empresa.cadastrada` **sem documento** (CNPJ/CPF é PII).
  - **Como verificar:** após cadastro de empresa, `SELECT action, context FROM audit_logs WHERE action = 'empresa.cadastrada' ORDER BY created_at DESC LIMIT 1`.
  - **Evidência esperada:** `context` contém `empresa_id`, `tipo_documento`, `fonte_enriquecimento`, mas **não** contém `documento`.
  - **Critério de aprovação:** PII fora do log; documento vive só em `empresas_analisadas` + `audit_logs` jurídico (se aplicável).

- [ ] **4.8** `business_metrics` registra `rfb_consulta` com `status` em `sucesso | cnpj_inexistente | timeout | erro_5xx | erro_rede` + `provider` em `meta` JSONB.
  - **Como verificar:** disparar 3 consultas em homol (sucesso + timeout + inexistente); `SELECT tipo, sucesso, meta->>'status' AS status, meta, duracao_ms FROM business_metrics WHERE tipo = 'rfb_consulta' ORDER BY inserido_em DESC LIMIT 10`.
  - **Evidência esperada:** linhas com `tipo = 'rfb_consulta'`, `meta->>'provider'` populado, latência > 0.
  - **Critério de aprovação:** observabilidade ativa.

- [ ] **4.9** Alerta de monitoramento `rfb_error_rate > 5% em janela 10 min` configurado via `MonitorarRfbErrorRate` (CA-5 STORY-015).
  - **Como verificar:** rodar `php artisan rfb:monitor` localmente após injetar 6 falhas; observar comando emite alerta (Telegram ou `Log::warning('rfb.alert', ...)` se Telegram não configurado, conforme `RfbAlerter`).
  - **Evidência esperada:** log/Telegram com alerta.
  - **Critério de aprovação:** comando funcional; scheduler registrado.

- [ ] **4.10** Provedor RFB ativo em homologação confirmado (`mock | cnpja | receitaws`).
  - **Como verificar:** `docker exec defonline-web php artisan config:show services.rfb` em homol.
  - **Evidência esperada:** registro do provider ativo; se for `cnpja` ou `receitaws`, chave API presente no env (via Ansible Vault — IDR-006).
  - **Critério de aprovação:** valor coerente com decisão operacional do momento (mock por padrão até decisão explícita — STORY-018 nota).

## 5. Tela "Minhas Empresas" + eventos de produto `usuario_cadastrado` e `empresa_cadastrada` (STORY-016)

- [ ] **5.1** Rota `/home` renderiza componente "Minhas Empresas" com saudação compacta + lista da Empresa cadastrada pelo Usuário.
  - **Como verificar:** logar em homol (Usuário com 1 Empresa); abrir `/home`.
  - **Evidência esperada:** screenshot mostrando "Olá, {primeiro_nome}", card da empresa com nome fantasia/razão, documento mascarado (`12.***.***\/0001-**` ou `***.123.***-**`), município/UF, badge da fonte, botão "Iniciar diagnóstico" desabilitado com tooltip "Em breve — onda 2".
  - **Critério de aprovação:** UI conforme CA-1 STORY-016 + ausência do botão "Adicionar empresa" (epic declara).

- [ ] **5.2** Estado vazio (Usuário sem Empresa) mostra CTA "Cadastre sua primeira Empresa" → link para `/empresas/nova`.
  - **Como verificar:** criar Usuário novo + confirmar email + logar sem cadastrar Empresa; abrir `/home`.
  - **Evidência esperada:** screenshot do estado vazio com CTA visível.
  - **Critério de aprovação:** caminho coberto.

- [ ] **5.3** Evento `usuario_cadastrado` emitido **após confirmação de email** (não no submit do cadastro — nota de decisão STORY-016 alinhada com ADR-004 §2.2).
  - **Como verificar:** cadastrar Usuário novo em homol; **antes** de clicar no link de confirmação, `SELECT nome_evento, propriedades FROM evento_produto WHERE usuario_id = :id` — esperado 0 rows. **Após** confirmação, mesma query — esperada 1 row `usuario_cadastrado` com `propriedades = {"plano_inicial": "basico_beta"}`.
  - **Evidência esperada:** queries antes/depois + payload completo.
  - **Critério de aprovação:** evento emitido no momento certo; payload mínimo (sem PII).

- [ ] **5.4** Evento `empresa_cadastrada` emitido após cadastro de Empresa, dentro da transação (CA-4 STORY-016).
  - **Como verificar:** cadastrar Empresa em homol (manual e via RFB); `SELECT nome_evento, propriedades FROM evento_produto WHERE nome_evento = 'empresa_cadastrada' ORDER BY ocorrido_em DESC LIMIT 5`.
  - **Evidência esperada:** linhas com `propriedades = {"empresa_id": "...", "tipo_documento": "cnpj|cpf", "fonte_enriquecimento": "rfb|manual", "uf": "PR", "cnae_2digitos": "31"}`.
  - **Critério de aprovação:** schema conforme ADR-004 + CNAE truncado a 2 dígitos + sem documento bruto.

- [ ] **5.5** Eventos **não contêm PII** — CPF/CNPJ/email/telefone ausentes de qualquer payload.
  - **Como verificar:** `SELECT propriedades::text FROM evento_produto WHERE propriedades::text ~ '[0-9]{11,14}' OR propriedades::text ~ '@'`.
  - **Evidência esperada:** zero rows.
  - **Critério de aprovação:** mascaramento + teste arquitetural do EPIC-000 efetivos.

- [ ] **5.6** Cross-tenant não vaza Empresas na listagem (Global Scope cobre `/home` também).
  - **Como verificar:** Usuário A logado vê apenas suas Empresas; manipular query string/URL não muda lista.
  - **Evidência esperada:** screenshot + (opcional) trace de query SQL via Laravel Debugbar local.
  - **Critério de aprovação:** isolamento respeitado.

## 6. Gates de qualidade transversais

- [ ] **6.1** Cobertura unitária geral do código novo do EPIC-001 ≥ **80%** (gate STORY-010 ativo).
  - **Como verificar:** relatório do CI da última tag rc do EPIC-001 (ex.: pipeline release-homolog que rodou após STORY-016); aba "Coverage" do `pr.yml`.
  - **Evidência esperada:** percentual reportado + link para o run.
  - **Critério de aprovação:** ≥ 80% sem regressão (STORY-016 reportou 96.0%).

- [ ] **6.2** Cobertura em `app/Domain/**` ≥ **98%** (gate `phpunit-domain.xml`).
  - **Como verificar:** `pre-push.sh` local em commit recente; saída do `pest --configuration=phpunit-domain.xml --min=98`.
  - **Evidência esperada:** saída do gate com percentual.
  - **Critério de aprovação:** ≥ 98% nas pastas `app/Domain/Cpf`, `app/Domain/Cnpj`, `app/Domain/Rfb/**`, `app/Domain/Uf`, `app/Domain/TermoTipo`.

- [ ] **6.3** Lint (Pint) verde no branch principal.
  - **Como verificar:** último run de `pr.yml` ou `main.yml` no GitHub Actions.
  - **Evidência esperada:** job `lint` verde.
  - **Critério de aprovação:** zero violações de Pint.

- [ ] **6.4** Type-check (Larastan nível 6) verde no branch principal.
  - **Como verificar:** último run de `pr.yml`/`main.yml`.
  - **Evidência esperada:** job `static-analysis` verde.
  - **Critério de aprovação:** zero erros Larastan.

- [ ] **6.5** Pipeline `pr.yml`, `main.yml` e `release-homolog.yml` verdes na última tag rc do EPIC-001.
  - **Como verificar:** acessar https://github.com/xandroalmeida/defonline/actions; conferir o run mais recente de cada workflow para a tag rc que cobre a STORY-016.
  - **Evidência esperada:** 3 runs verdes (validate + build-and-push + deploy + smoke + notify).
  - **Critério de aprovação:** verde end-to-end.

- [ ] **6.6** Smoke pós-deploy contra URL pública de homologação é **read-only** (lição da STORY-011 rc.1 vs rc.2).
  - **Como verificar:** abrir `tests/Browser/*SmokeBrowserTest.php` no repo; conferir que grupo `smoke` só visita rotas + assert presente, sem submit em form de produção.
  - **Evidência esperada:** listagem dos testes do grupo `smoke` + diff que prova read-only.
  - **Critério de aprovação:** zero teste do grupo `smoke` faz POST/PUT/DELETE em homol.

- [ ] **6.7** Testes E2E em browser real (Dusk) cobrem os fluxos do épico (cadastro → login → home; cadastro completo → confirmação email → cadastro empresa → Minhas Empresas).
  - **Como verificar:** rodar `php artisan dusk` localmente; conferir cobertura de cenários.
  - **Evidência esperada:** suíte Dusk verde (STORY-016 reportou 12 testes); cenário do fluxo completo presente (`MinhasEmpresasBrowserTest`).
  - **Critério de aprovação:** Dusk cobre o caminho do épico ponta a ponta.

## 7. Observabilidade e LGPD

- [ ] **7.1** Logs estruturados JSON com `request_id` UUID v7 + `usuario_id` + sem PII (CPF/email/telefone/CNPJ mascarados via `LogSanitizer`).
  - **Como verificar:** `docker exec defonline-web tail -n 200 storage/logs/laravel.log` em homol após fluxo de cadastro completo; grep por CPF puro / email puro.
  - **Evidência esperada:** entradas JSON com `request_id` presente; ausência de PII em texto plano.
  - **Critério de aprovação:** mascaramento ativo em todas as camadas (ADR-004 §1.1).

- [ ] **7.2** Eventos de produto (`evento_produto`) consultáveis via SQL com schema fixado em ADR-004.
  - **Como verificar:** `SELECT nome_evento, COUNT(*) FROM evento_produto GROUP BY nome_evento` em homol após fluxo de teste.
  - **Evidência esperada:** linhas para `usuario_cadastrado` e `empresa_cadastrada`.
  - **Critério de aprovação:** consumidor real funcional (primeiro do projeto).

- [ ] **7.3** Auditoria (`audit_logs`) registra escritas críticas (`usuario.cadastrado`, `usuario.login_sucesso`, `usuario.email_confirmado`, `termo.aceito`, `termo.recusado`, `empresa.cadastrada`, `empresa.acesso_negado`) **sem leituras**.
  - **Como verificar:** `SELECT DISTINCT action FROM audit_logs ORDER BY action` em homol.
  - **Evidência esperada:** ações de escrita conforme listadas; ausência de ação correspondente a `GET /home`, `GET /empresas/:id`.
  - **Critério de aprovação:** ruído de leitura ausente; escritas críticas presentes.

- [ ] **7.4** Email transacional (Resend) **não vaza PII em corpo nem em assunto** indevidamente.
  - **Como verificar:** abrir email de confirmação recebido; conferir que CPF não aparece (email + nome próprio são esperados — PII funcional).
  - **Evidência esperada:** screenshot do email.
  - **Critério de aprovação:** apenas dados estritamente necessários ao propósito (LGPD Art. 6º — minimização).

- [ ] **7.5** Acesso ao banco em homol via role `defonline_app` respeita REVOKE em tabelas append-only (`term_acceptances`, `audit_logs`, `evento_produto`).
  - **Como verificar:** `docker exec defonline-postgres psql -U defonline_app -d defonline -c "DELETE FROM evento_produto WHERE id = (SELECT id FROM evento_produto LIMIT 1)"`.
  - **Evidência esperada:** `ERROR: permission denied`.
  - **Critério de aprovação:** ADR-005 §7.5 efetivo.

## 8. Critérios de fechamento do épico

- [ ] **8.1** Todas as estórias `STORY-011` a `STORY-016` (e STORY-018) com `status: done` no `index.json` ao final desta validação.
  - **Como verificar:** `jq '.stories[] | select(.epic_id == "EPIC-001") | {id, status}' defonline-docs/project-state/index.json`.
  - **Evidência esperada:** todas em `done`.
  - **Critério de aprovação:** **100%** das implementações fechadas. STORY-017 entra em `done` apenas após este checklist ser executado.

- [ ] **8.2** Deploy em homologação estável — `https://defonline.xandrix.com.br/health` responde 200 com versão = última tag rc do EPIC-001.
  - **Como verificar:** `curl https://defonline.xandrix.com.br/health`.
  - **Evidência esperada:** JSON com `status: OK` + versão.
  - **Critério de aprovação:** healthcheck verde + versão coerente.

- [ ] **8.3** Entregável visível do épico (`epic.md`): Roberto consegue percorrer o fluxo `cadastro → Termo+LGPD → confirmação email → cadastro Empresa (RFB ou manual) → Minhas Empresas` **em ≤ 5 minutos no celular**, em homologação real.
  - **Como verificar:** validador executa manualmente em browser mobile (ou DevTools mobile emulation) com cronômetro.
  - **Evidência esperada:** timestamp inicial + final + screenshots dos 5 marcos.
  - **Critério de aprovação:** ≤ 5 minutos sem bloqueios funcionais. Se >5 min, registrar como `fail` em "Funcionalidade observável" mas considerar não-bloqueante se o fluxo tecnicamente funciona (a métrica é alvo de produto, não gate técnico; PO decide).

- [ ] **8.4** Documentação atualizada — `index.json` reflete estados finais; ADRs e IDRs do épico indexados (IDR-004, IDR-005, IDR-006, IDR-007 + ADRs 001-006 que continuam vigentes).
  - **Como verificar:** abrir `index.json` e conferir bloco `decisions.idr` com 7 entradas.
  - **Evidência esperada:** todas as IDRs do EPIC-001 presentes com paths válidos.
  - **Critério de aprovação:** índice coerente com `decisions/idr/` no disco.

- [ ] **8.5** Notas do agente preenchidas em cada estória do EPIC-001 (cobertura final, decisões locais, descobertas, links de evidência).
  - **Como verificar:** abrir cada `STORY-011` a `STORY-016` (e `STORY-018`) e conferir seções "Notas do agente".
  - **Evidência esperada:** seção preenchida em todas as estórias.
  - **Critério de aprovação:** nenhuma estória `done` com notas vazias.

## 9. Veredito

- [ ] **APROVADO** — todos os itens acima `pass` ou `n/a justificado`. Atualizar `index.json`: EPIC-001 `status: done` + `validation_report` apontando para `validation/report.md`; STORY-017 `status: done`.
- [ ] **APROVADO COM PENDÊNCIAS** — itens `fail` não-bloqueantes registrados; relatório lista cada um e propõe estórias de correção; EPIC-001 pode ser fechado com débito declarado (decisão do PO após ler o relatório).
- [ ] **REPROVADO** — pelo menos um `fail` bloqueante. Listar no relatório quais e propor estórias de correção. EPIC-001 fica `in_review` até nova validação.

Preencha o relatório final em `report.md` usando o que você observou aqui, seguindo o template em `defonline-docs/skills/validador/templates/validation-report.md`.
