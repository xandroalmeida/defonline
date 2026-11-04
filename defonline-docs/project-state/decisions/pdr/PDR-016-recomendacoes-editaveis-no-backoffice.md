---
pdr_id: PDR-016
slug: recomendacoes-editaveis-no-backoffice
title: Matriz de recomendações mantida pela equipe no backoffice
status: accepted
decided_at: 2026-11-04
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-011]
related_adrs: []
related_idrs: [IDR-010]
---

# PDR-016 — Recomendações editáveis no backoffice

## Contexto

O PO pediu para editar, na Administração, as recomendações por indicador, segmento e faixa. Até aqui os textos eram um arquivo imutável no código, e só os 39 de Indústria existiam no produto. O Anexo F já previa a matriz versionada no banco.

## Decisão

1. Os 117 textos (13 indicadores × 3 segmentos × 3 faixas) vão para a tabela `recomendacoes`: Indústria com o texto que o motor usava, Comércio e Serviços com o Anexo F dez/2025. Todos são editáveis, inclusive os avisos de "não se aplica" do PME em Serviços.
2. A tela de Recomendações exige a permissão própria "Manter recomendações". Mostra um indicador por vez, escolhido em abas, com os 9 textos sempre editáveis: um card por segmento, com as 3 faixas alinhadas entre os cards. As alterações ficam pendentes ao trocar de indicador e são salvas juntas, ou nenhuma, se alguma for inválida.
3. Só o texto muda, com até 300 caracteres (limite do Anexo F) e sem texto vazio. A faixa numérica aparece só como informação: alterá-la mudaria o farol, que é cálculo.
4. A edição vale na hora para os próximos diagnósticos. Os já emitidos mantêm o texto e a versão da época, no snapshot (IDR-010).
5. A versão da matriz é por segmento e passa a ser o mês da última edição (`mes-aaaa`). Sem edição, segue `dez-2025`. Toda alteração gera audit log com antes, depois e a versão.
6. O motor continua sem banco em `app/Domain`: lê por uma interface, implementada com a tabela fora do domínio. Os testes de domínio e os golden hashes usam a matriz dez-2025 congelada em `tests/Fixtures`, e um teste garante que ela é igual à migration.
7. O NCG absoluto fica de fora: não tem farol, e seus textos seguem no código.

## Consequências

- O Anexo F deixa de ser a origem do texto em produção. Quem edita é responsável por manter a validação da EB Parcerias.
- Duas edições do mesmo segmento no mesmo mês têm a mesma versão. O que distingue uma da outra é o audit log, e o texto de cada diagnóstico fica no snapshot.
- Editar texto não altera `motor_version` nem os golden hashes.
- Comércio e Serviços podem ser editados, mas não aparecem em relatório até o EPIC-006: o motor só calcula Indústria. A tela não avisa, por decisão do PO.
- Altera a IDR-010 (matriz em arquivo imutável, nova versão só por deploy) e o Anexo F §F.99 (processo de atualização).
