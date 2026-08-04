---
epic_id: EPIC-010
type: validation-checklist
created_at: 2026-08-04
---

# Checklist de validação — EPIC-010 (DRM)

> Para o **validador**: execute em ordem, registre `pass | fail | n/a` com **evidência primária** para cada item (log, screenshot, saída de pipeline, consulta). Não invente resultado. Encontrou falha, **não conserte** — registre e devolva ao PO. Limitação de acesso é declarada como tal, nunca convertida em falha nem em aprovação silenciosa.

## 1. Critérios de aceite das estórias

- [ ] Todas as estórias STORY-045 a STORY-056 com `status: done` no `index.json`.
- [ ] Cada CA de cada estória exercido por ao menos um teste automatizado.
- [ ] Decisões da STORY-045 e da STORY-053 publicadas, `accepted` e indexadas.
- [ ] Pacote de conteúdo da STORY-052 publicado, ou defaults aplicados e registrados por escrito.

## 2. Correção aritmética — **bloqueante**

O item mais importante deste checklist. Um erro aqui contamina toda a série histórica do usuário.

- [ ] Para **um lançamento em cada setor**, o valor de cada linha exibido na tela confere com o cálculo manual a partir de `design/estrutura-drm.md` §1. Anexar a conferência.
- [ ] Os percentuais conferem com o cálculo manual sobre a Receita Bruta.
- [ ] Um mesmo conjunto de entradas produz, na configuração equivalente, os mesmos valores da planilha `Demonstração de Resultado Mensal - DRM.xlsx` (mapeamento no Anexo K §K.11).
- [ ] Grupo em modo agregado e o mesmo grupo em modo detalhado, com itens somando o mesmo total, produzem demonstração idêntica.
- [ ] Golden tests do motor presentes e passando; alterar a saída do motor os quebra.

## 3. Comportamento em casos de borda

- [ ] Mês sem faturamento: percentuais indisponíveis com motivo semântico, **valores nominais presentes**, nenhuma exceção, nenhuma divisão por zero.
- [ ] Mês com prejuízo: exibido com clareza e neutralidade.
- [ ] Competência não lançada: aparece como lacuna na série, **nunca como zero**.
- [ ] Série com granularidades mistas: comparativo de item detalhado indisponível nos meses agregados, sem inferir valor a partir do total.
- [ ] Comparativos indisponíveis nas condições de `design/estrutura-drm.md` §6 (sem mês anterior; menos de 3 competências).
- [ ] Mês atípico sinalizado e **incluído** nos cálculos.

## 4. Retificação e integridade histórica

- [ ] Retificar uma competência preserva a versão anterior de forma recuperável.
- [ ] A retificação é sinalizada na demonstração e na série.
- [ ] Retificar um mês ajusta os comparativos dos meses seguintes, sem valor obsoleto em cache.
- [ ] **Nenhuma competência não retificada é recalculada.**
- [ ] Cada retificação registrada em audit log com autor e momento.

## 5. Adaptação por setor

- [ ] Indústria, Comércio e Serviços exibem exatamente os campos de `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` §K.4 — nem mais, nem menos.
- [ ] Rótulo do grupo de custo correto em cada setor.
- [ ] Campo suprimido gravado como nulo, não como zero.

## 6. Atrito e mecanismos anti-abandono

Os três mecanismos do PDR-003, decisão 6. Sem eles, a métrica primária do épico não se sustenta.

- [ ] Escolha entre modo agregado e detalhado disponível em cada um dos quatro grupos, alternável depois do lançamento salvo.
- [ ] Pré-preenchimento a partir da competência anterior funcionando, com marcação visual e confirmação em lote.
- [ ] Rascunho salvo automaticamente e retomável em outra sessão.
- [ ] Com todos os grupos em modo agregado, o lançamento se resolve em 7 campos monetários.
- [ ] Percurso cronometrado de um 2º lançamento com pré-preenchimento: **≤ 5 minutos**. Registrar o tempo medido.

## 7. Validações e alertas

- [ ] Regras bloqueantes B1 a B4 do Anexo K §K.6 impedem o fechamento, cada uma com mensagem específica.
- [ ] Alertas A1 a A6 sinalizam e permitem prosseguir mediante aceite explícito, registrado no lançamento.
- [ ] Alerta aceito aparece como nota de contexto na demonstração.

## 8. Cobertura de testes

- [ ] Cobertura geral ≥ **80%** (evidência: relatório do CI).
- [ ] Cobertura de núcleo e regras de negócio ≥ **98%** — motor, comparativos e classificação de farol.
- [ ] Teste arquitetural garante a pureza do motor (sem banco, sem relógio, sem sessão).
- [ ] E2E em browser real cobrindo: lançamento completo, lançamento → demonstração, lançar → retificar → conferir sinalização, dois lançamentos consecutivos → comparativo.

## 9. Automação e pipeline

- [ ] Pipeline verde na branch principal após o épico.
- [ ] Deploy para homologação automatizado e verificado.
- [ ] Migrações reversíveis e testadas em homologação.
- [ ] Nenhum passo manual novo introduzido pelo épico.

## 10. Funcionalidade observável em homologação

Os sete itens do `epic.md`, um a um:

- [ ] Usuário escolhe empresa e lança a competência do mês.
- [ ] Demonstração exibida com valor nominal **e** percentual em cada linha.
- [ ] Usuário corrige um mês lançado; a tela sinaliza a retificação sem perder histórico.
- [ ] A partir do 2º lançamento, variação vs. mês anterior; a partir do 4º, comparação com a média.
- [ ] Cada linha traz farol e leitura em português simples — ou, onde não há faixa validada, a ausência de farol é deliberada e explicável.
- [ ] Exportação em planilha e em PDF (PDF pode ser `n/a` se o ADR da STORY-053 o adiou — verificar a decisão).
- [ ] Um lançamento em cada setor produz demonstração correta, com campos irrelevantes ausentes.

## 11. Instrumentação e métricas do épico

- [ ] As três métricas do `epic.md` são calculáveis a partir dos eventos emitidos. Registrar a consulta usada.
- [ ] Nenhum evento carrega valor financeiro absoluto, nome, documento ou dado identificável.
- [ ] Eventos idempotentes; reenvio não duplica.

## 12. Qualidade transversal

- [ ] Nenhum aviso crítico de segurança novo introduzido pelo épico.
- [ ] Cross-tenant retorna 404 silente em todas as rotas novas (IDR-009).
- [ ] LGPD respeitada conforme `requisitos-nao-funcionais-e-juridicos.md`.
- [ ] Acessibilidade AA nas telas novas: contraste, alvo de toque, farol nunca só por cor, navegação por teclado no formulário longo.
- [ ] Nenhum hex literal de design system fora dos tokens (IDR-008).
- [ ] Nenhuma alteração no motor, no `payload_hash` ou nas versões do Diagnóstico.

## 13. Documentação

- [ ] ADRs, IDRs e DDRs criados durante o épico indexados no `index.json`.
- [ ] Notas do agente preenchidas em todas as estórias.
- [ ] Divergências entre o implementado e o Anexo K ou `design/estrutura-drm.md` registradas — ou inexistentes.

## 14. Veredito

- [ ] **APROVADO** — todos os itens `pass` ou `n/a` justificado.
- [ ] **REPROVADO** — ao menos um `fail`. Liste quais e proponha estórias de correção.

> Falha na seção 2 (correção aritmética) é **sempre bloqueante**, independentemente do resto.

Preencha `report.md` com o que você observou aqui.
