---
artifact: epic-validation-checklist
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
written_by: po (alexandro)
written_at: 2026-05-26
status: published
related_validation_report: validation/report.md
---

# Checklist de validação final — EPIC-002 (Diagnóstico Indústria)

> **Instruções ao Validador.** Roda os 60 itens abaixo em ordem. Para cada item, registra a evidência em `validation/evidence/{id}.{ext}` (screenshot, SQL output, link, etc.) e marca `[x]`. Se algum item falhar e a falha for crítica (gate técnico ou entregável visível), pausa e abre a falha na seção "Falhas" do `validation/report.md`. Se a falha for não-crítica, registra como follow-up e segue. **Não preencha o veredito final — isso é responsabilidade sua, registrada no report.md.**
>
> **Inputs autoritativos:** spec V2 funcional (§4.5, §4.7, §4.7.1, §6.6, §6.8, §9.2, §9.3); Anexos A, D, E, F, I; epic.md desta pasta; IDR-010, ADR-002, ADR-004; STORY-026 a 035 (todas done); briefings de abertura; reports individuais já publicados (`report-STORY-031.md`, `-032.md`, `-033.md`, `-034.md`).
>
> **Ambiente:** homologação `https://defonline.xandrix.com.br` (com rc da sprint W25 deployado). Se ainda não houver deploy do rc, usar `http://localhost:8090` e registrar a defasagem como follow-up F-NB-1.

## Bloco A — Métricas de qualidade técnica (epic.md)

| ID | Verificar | Como | Esperado | Evidência |
|---|---|---|---|---|
| A-1 | Cobertura ≥ 98% no pacote `App\Domain\Motor\` | `pest -c phpunit-domain.xml --coverage --min=98` no container web | Saída "OK" com cobertura ≥ 98% no Motor; ≥ 80% geral | `evidence/A-1-coverage.txt` |
| A-2 | ≥ 10 casos de teste por fórmula do motor (NRF §9.2) | Contagem de cenários por indicador em `tests/Domain/Motor/Indicadores/*Test.php` | ≥ 10 por indicador × 14 indicadores = ≥ 140 cenários | `evidence/A-2-cobertura-por-formula.txt` |
| A-3 | p95 do relatório ≤ 3 segundos em homol | 50 navegações sequenciais de `/diagnosticos/{id}` num diagnóstico real, medir RTT | p95 ≤ 3.0s | `evidence/A-3-p95-grafico.png` |
| A-4 | Pint + Larastan zero erro | `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse` | Saída limpa em ambos | `evidence/A-4-lint.txt` |
| A-5 | Suíte completa verde (Pest + Dusk) | `pest --parallel && pest --filter=Browser` | 0 falhas, 0 erros | `evidence/A-5-suite.txt` |

## Bloco B — Entregáveis visíveis (epic.md §"Entregável visível")

| ID | Verificar | Como | Esperado | Evidência |
|---|---|---|---|---|
| B-1 | Roberto preenche quiz da marcenaria e recebe relatório em homol | Smoke E2E completo: criar conta de teste → cadastrar Empresa Analisada Indústria → preencher 23 campos → submeter | Relatório renderiza com sucesso, sem erros JS | `evidence/B-1-smoke-screencast.mov` |
| B-2 | Tempo de resposta do relatório ≤ 3s p95 | (= A-3) | (= A-3) | (link A-3) |
| B-3 | Relatório exibe 14 indicadores com semáforo | Inspecionar relatório de B-1 | 13 com farol + NCG abs informativo (sem farol) + Ciclo Operacional informativo | `evidence/B-3-tabela-indicadores.png` |
| B-4 | Recomendações textuais por indicador vêm da matriz DEZ/2025 Indústria | Inspecionar mensagens no relatório de B-1 | Texto literal do Anexo F coluna Indústria; **NÃO** "Faixa verde/amarela/vermelha" placeholder | `evidence/B-4-recomendacoes-matriz.png` |
| B-5 | Resumo Executivo no topo do relatório | Inspecionar topo do relatório de B-1 | Veredito (Saudável/Precisa atenção/Em alerta/Fallback) + destaques + linha 5 fixa, conforme §4.7.1 | `evidence/B-5-resumo-executivo.png` |
| B-6 | NCG absoluto sem farol (decisão V2.5 §4.5) | Inspecionar card informativo no relatório de B-1 | Card próprio + 3 mensagens semânticas (Folga/Moderado/Alto) + sem farol verde/amarelo/vermelho | `evidence/B-6-ncg-abs.png` |
| B-7 | Validações cruzadas DRE × Balanço com mensagens acionáveis | Preencher quiz com payload R3 (passivo > ativo) e ver banner | Banner inline ao sair do Bloco 3 com R3 disparada; "Continuar mesmo assim" persiste em `alertas_aceitos` | `evidence/B-7-validacao-cruzada.png` |
| B-8 | Tooltips/box de explicação no quiz por campo (§6.8 + Anexo A §A.6) | Abrir quiz, click no ícone `?` ao lado de qualquer label | Popover (desktop ≥ 1024px) ou bottom-sheet (mobile) com texto do `config/quiz/help-industria.php` | `evidence/B-8-tooltip.png` |
| B-9 | Glossário inline (Anexo I) | Rolar até fim do relatório de B-1 | Seção "Glossário" com acordeon expansível por termo | `evidence/B-9-glossario.png` |
| B-10 | Eventos `quiz_iniciado` e `diagnostico_concluido` emitidos | `SELECT nome_evento, propriedades FROM evento_produto WHERE nome_evento IN ('quiz_iniciado','diagnostico_concluido') ORDER BY ocorrido_em DESC LIMIT 4;` | Linhas para os 2 eventos com payload ADR-004 §2.2 (`quiz_id`, `quiz_versao` em `quiz_iniciado`; `quiz_id`, `diagnostico_id`, `duracao_preenchimento_seg`, `setor`, `porte` em `diagnostico_concluido`); `request_id` UUID v7 populado | `evidence/B-10-eventos-sql.txt` |

## Bloco C — CAs por estória (STORY-026 a 035)

> Validador confere que cada CA da estória está fechado (`[x]` no front-matter). Se algum estiver aberto, levanta como falha. Os reports individuais `report-STORY-031.md`, `-032.md`, `-033.md`, `-034.md` cobrem a maioria — Validador apenas reconfirma.

| ID | Estória | Itens a reconferir | Evidência |
|---|---|---|---|
| C-026 | STORY-026 — Spike versionamento + persistência | IDR-010 aprovada; `motor.version` carimbado no snapshot; `payload_hash` SHA-256 do canonical | `evidence/C-026.txt` (cita IDR-010 + migration) |
| C-027 | STORY-027 — Quiz 23 campos | 23 campos persistidos; máscaras BR; rascunho 90 dias; `quiz_iniciado` na transição `null→rascunho` | `evidence/C-027.txt` |
| C-028 | STORY-028 — Motor V1 7 indicadores | 7 fórmulas + NCG abs informativo; ≥ 10 casos por fórmula; faixas `faroes-industria.php` | `evidence/C-028.txt` |
| C-029 | STORY-029 — Relatório minimalista | Rota `/diagnosticos/{id}`; tabela + cards mobile; glossário; baseline p95 medido | `evidence/C-029.txt` |
| C-030 | STORY-030 — Motor V2 14 indicadores | 14 fórmulas completas; Ciclo Operacional informativo; bump 1.1.0 | `evidence/C-030.txt` |
| C-031 | STORY-031 — Resumo Executivo §4.7.1 | (já validado em `report-STORY-031.md` — veredito `approved_with_pending`) | (link report) |
| C-032 | STORY-032 — Matriz DEZ/2025 | (já validado em `report-STORY-032.md` — veredito `approved`) | (link report) |
| C-033 | STORY-033 — Tooltips quiz | (já validado em `report-STORY-033.md` — veredito `approved`) | (link report) |
| C-034 | STORY-034 — Validações cruzadas DRE × Balanço | (já validado em `report-STORY-034.md` — veredito `approved`) | (link report) |
| C-035 | STORY-035 — Eventos analíticos | Payload ADR-004 §2.2; tabela `evento_produto`; `EventLogger::emit` síncrono na transação; teste arquitetural | `evidence/C-035.txt` |

## Bloco D — Decisões arquiteturais preservadas (IDR-010 + ADR-004)

| ID | Verificar | Como | Esperado | Evidência |
|---|---|---|---|---|
| D-1 | `motor_version` semver + `matrix_version` datada no snapshot | `SELECT motor_version, matrix_version FROM diagnosticos ORDER BY gerado_em DESC LIMIT 5;` | Linhas com `1.x.y` e `dez-2025` | `evidence/D-1-versionamento.txt` |
| D-2 | Snapshot imutável (`indicadores_calculados` + `resumo_executivo` not null e nunca update) | Teste arquitetural existente `Tests\Architectural\SnapshotImutabilidadeTest` continua verde | OK | `evidence/D-2-imutabilidade.txt` |
| D-3 | `payload_hash` é SHA-256 do canonical | Reprocessar 1 fixture → mesmo input → mesmo hash | Match bit-exato | `evidence/D-3-determinismo.txt` |
| D-4 | `alertas_aceitos` fora do hash (STORY-034) | 2 diagnósticos com mesmo input, um com `alertas_aceitos` populado → mesmo `payload_hash` | Hashes idênticos | `evidence/D-4-hash-invariante.txt` |
| D-5 | `EventLogger::emit` é a única porta para `evento_produto` | Teste arquitetural `EmissaoEventoArchTest` (STORY-035) verde | OK | `evidence/D-5-arch-event.txt` |
| D-6 | Sem PII em `propriedades` dos eventos | Grep `evento_produto.propriedades::text` por padrões CNPJ/CPF/email/telefone | Zero matches | `evidence/D-6-pii-eventos.txt` |
| D-7 | `request_id` UUID v7 gravado em `evento_produto` | `SELECT request_id FROM evento_produto LIMIT 5;` | 5 UUIDs v7 válidos | `evidence/D-7-request-id.txt` |
| D-8 | Cross-tenant retorna 404 (IDR-009) | Logout, login com outra conta, acessar diagnóstico anterior por URL | HTTP 404 (não 403) | `evidence/D-8-cross-tenant.txt` |

## Bloco E — Compatibilidade retroativa

| ID | Verificar | Como | Esperado | Evidência |
|---|---|---|---|---|
| E-1 | Diagnósticos antigos em `motor_version` 1.0.0–1.2.x abrem sem erro | Selecionar 1 diagnóstico de cada versão (5 ao todo) e abrir em browser | 200 OK; relatório renderiza com texto da época (placeholder "Faixa verde" para 1.1.0, etc.); sem PHP fatal | `evidence/E-1-snapshots-legados.png` |
| E-2 | Snapshot legado com `resumo_executivo` placeholder não renderiza bloco | Abrir um diagnóstico 1.1.0 | Bloco do resumo silencia (componente `<x-relatorio.resumo-executivo>` só renderiza se `veredito` está presente) | `evidence/E-2-resumo-legado.png` |

## Bloco F — Fora de escopo preservado

> Validador confere que NADA dos itens "fora de escopo" da epic.md vazou para o produto.

| ID | Verificar | Esperado | Evidência |
|---|---|---|---|
| F-1 | Comércio e Serviços não selecionáveis na criação de Empresa Analisada | Apenas Indústria habilitada (ou se selecionáveis, mostram aviso "ondas futuras") | `evidence/F-1.png` |
| F-2 | PDF do relatório NÃO oferecido | Nenhum botão/link "Exportar PDF" no relatório | `evidence/F-2.png` |
| F-3 | Solicitar análise de captação NÃO disponível (botão ausente) | Nenhum CTA "Solicitar análise de captação" em homol | `evidence/F-3.png` |
| F-4 | Compartilhamento por link público NÃO oferecido | Nenhum botão "Compartilhar" | `evidence/F-4.png` |
| F-5 | Feedback 👍/👎 por recomendação NÃO oferecido | Ausente do relatório | `evidence/F-5.png` |
| F-6 | Edição do quiz após cálculo NÃO permitida | Diagnóstico já gerado é read-only | `evidence/F-6.png` |

## Bloco G — Validação externa (STORY-036) — **pendente por decisão PO**

> **Decisão PO 2026-05-26.** Validação externa formal NRF §9.3 **adiada** para após o fechamento técnico do EPIC-002 (ver justificativas em `stories/STORY-036-validacao-externa-motor.md` §"Decisão do PO em 2026-05-26"). EPIC-002 fecha como `done_under_review` em vez de `done` puro. **Beta fechado não pode rodar até a STORY-036 voltar `approved` ou `approved_with_pending`.**

| ID | Verificar | Esperado | Evidência |
|---|---|---|---|
| G-1 | STORY-036 registrada como `pending_external_validation` no `index.json` (não `done`, não `blocked`) | Status na linha da STORY-036 = `draft` com tag explícita de pendência | `evidence/G-1-status.txt` |
| G-2 | Condição explícita no handoff (Bloco H-1 / `handoff/README.md`) de que **beta fechado não roda** até parecer externo | Frase obrigatória no handoff: "Pré-requisito para abrir o beta: STORY-036 (validação externa NRF §9.3) com veredito `approved` ou `approved_with_pending`." | (link H-1) |
| G-3 | Validador externo entra como **épico/estória de débito** com janela e responsável definidos antes do beta começar | Item no backlog pós-handoff registrado pelo PO | `evidence/G-3-debito.md` |

## Bloco H — Pacote de handoff (STORY-037 CA-6)

## Bloco H — Pacote de handoff (STORY-037 CA-6)

| ID | Verificar | Esperado | Evidência |
|---|---|---|---|
| H-1 | `handoff/README.md` cobre os 9 pontos do CA-6 + **pré-requisito explícito do Bloco G-2** | Link homol + credenciais teste; passo a passo conta+empresa; roteiro smoke E2E; evidências p95; parecer externo (G-1) ou nota de pendência; feature flags; escopo coberto/fora; contato suporte; decisões abertas conhecidas; **frase obrigatória sobre validação externa pré-beta** | `evidence/H-1-handoff.md` (link direto) |
| H-2 | ~~Dry-run com 1 representante de implantação~~ → **Decisão PO 2026-05-26:** sem dry-run interno. Handoff é leitura direta. | Risco de "lacunas só visíveis no uso real" registrado no `handoff/README.md` §Riscos | `evidence/H-2-decisao-sem-dry-run.txt` |
| H-3 | Tag `v1.0.0` (ou equivalente) criada e empurrada | `git tag --list 'v*'` mostra a tag | `evidence/H-3-tag.txt` |

## Bloco I — Promoção do épico

| ID | Verificar | Esperado | Evidência |
|---|---|---|---|
| I-1 | `index.json` — EPIC-002 promovido para **`done_under_review`** (não `done` puro) — gate da validação externa ainda pendente | `epic.status == "done_under_review"` (ou equivalente convencionado pelo PO) | `evidence/I-1-index.json` |
| I-2 | `index.json` — EPIC-003 destravado para SPRINT-2026-W30 (independente da validação externa, que afeta o BETA, não a próxima estória técnica) | `epic.status == "ready"` | (link I-1) |
| I-3 | Sprint W25 — registro de fechamento na seção "Fechamento do sprint" | Preenchido com entregas, métricas finais, retro, **lista de pendências de release** (incluindo STORY-036) | (sprints/SPRINT-2026-W25.md) |
| I-4 | Comunicação ao stakeholder (PO ao próprio Alexandro + comercial/implantação) com **alerta explícito do pré-requisito de validação externa antes do beta** | E-mail / mensagem registrada | `evidence/I-4-comunicacao.txt` |

## Falhas observadas (preencher só se houver — formato livre)

*Vazio no momento da publicação do checklist. Validador adiciona itens conforme rodar.*

## Follow-ups conhecidos (não-bloqueantes — vão para backlog pós-handoff)

- **P-001 (STORY-032):** path default de `matriz_lacunas_path` aponta para fora do volume Docker → `is_dir()` retorna false, auto-append silencia. Log::warning continua funcionando. Defeito morto enquanto matriz não tiver lacunas. Resolver: montar volume adicional no `docker-compose.yml` ou mover arquivo para `app/storage/`.
- **P-002 (STORY-032):** 6 comentários `// F.X` no dataset de `MatrizRecomendacoesTest.php` com numeração trocada (textos esperados estão corretos). PR de polimento.
- **P-001 (STORY-033):** auditoria Pa11y formal não rodada. Cobertura funcional via Dusk OK. Rodar Pa11y na URL do quiz + arquivar relatório.
- **P-001 (STORY-031):** decisões locais de severidade do amarelo + amplitude da faixa vermelha (ResumoExecutivo) não estão na spec literal. Formalizar como adendum à IDR-010 ou IDR-011.

## Histórico do checklist

| Versão | Data | Autor | Mudança |
|---|---|---|---|
| 1.0 | 2026-05-26 | PO (Alexandro) | Primeira versão publicada — destrava STORY-037 pré-requisito 1 da skill do Validador. |
