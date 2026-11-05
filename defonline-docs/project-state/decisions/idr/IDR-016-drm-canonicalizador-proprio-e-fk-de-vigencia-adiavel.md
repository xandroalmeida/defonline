---
idr_id: IDR-016
slug: drm-canonicalizador-proprio-e-fk-de-vigencia-adiavel
title: DRM com canonicalizador próprio (isolado do Diagnóstico) e FK do ponteiro de vigência adiável
status: accepted
decided_at: 2026-11-05
decided_by: programador (claude-sonnet-5-5)
related_stories: [STORY-047, STORY-050]
related_pdrs: [PDR-003]
related_adrs: [ADR-007]
related_idrs: [IDR-009, IDR-010]
---

# IDR-016 — Canonicalizador próprio do DRM e FK de vigência adiável

## Contexto

A STORY-047 deixou duas decisões de baixo nível para o Programador: (1) como garantir que o DRM não consiga alterar `diagnosticos.payload_hash` (ADR-007, pergunta 3); (2) nada previa a ordem das operações da retificação diante do índice único parcial de vigência — o achado veio dos testes de persistência.

## Decisões

1. **Dois canonicalizadores independentes, sem código compartilhado.** `App\Domain\Drm\LancamentoCanonicalizer` reimplementa as cinco regras do IDR-010 (chaves ordenadas, decimal de casas fixas, vazio vira `null`, booleano literal, NFC) em ~40 linhas. O `QuizPayloadCanonicalizer` do Diagnóstico não foi tocado.
   - **Por quê:** a restrição do ADR-007 passa a valer por construção — nenhuma linha do DRM roda no caminho do hash do Diagnóstico. Um canonicalizador genérico compartilhado faria cada ajuste do DRM depender dos golden hashes do Diagnóstico como rede de segurança; o custo de duplicar é pequeno e as regras são fixas (IDR-010).
   - **Verificação:** `LancamentoCanonicalizerArchTest` reprova qualquer referência de `App\Domain\Drm` ao motor, à action ou aos models do Diagnóstico, e reprova o canonicalizador do Diagnóstico conhecer o DRM. Os golden hashes do Diagnóstico seguem rodando no mesmo CI.
   - **Reversível:** se um terceiro módulo precisar do mesmo contrato, extrai-se a classe genérica e os golden hashes viram o estopim obrigatório.

2. **A FK `drm_lancamentos.substituido_por_id` é `DEFERRABLE INITIALLY DEFERRED`.** O ADR-007 descreve a retificação como "INSERT da versão N+1, depois UPDATE do ponteiro da N". Com o índice único parcial `(empresa, competência) WHERE substituido_por_id IS NULL`, o INSERT da N+1 enquanto a N ainda é vigente viola o índice no ato — e índice único não é adiável.
   - **Ordem correta, para a STORY-050:** na transação, gerar o `id` da N+1; `UPDATE` da N apontando para esse id; `INSERT` da N+1. A FK adiada só é conferida no `COMMIT`, quando a N+1 já existe.
   - **Verificação:** `PersistenciaDrmTest` (`trocarVersao`) exercita exatamente essa ordem; o modelo `DrmLancamento` recusa alterar o ponteiro depois de definido e qualquer coluna de conteúdo.

## Consequências

- Reedições do ADR-007 devem ler "UPDATE do ponteiro → INSERT da nova versão".
- `demonstracao` nasce `[]` na STORY-047 (o motor é a STORY-048) e, sendo imutável, não pode ser preenchida depois por UPDATE: antes de o módulo ter lançamentos reais, a STORY-048 precisa passar a gravar o snapshot no fechamento (ou fazer um backfill por migration, exceção deliberada à imutabilidade).

## Aditivo — STORY-050 (2026-11-05): quem perde a corrida do `FOR UPDATE` volta vazio

O `SELECT … FOR UPDATE` da vigente (`WHERE substituido_por_id IS NULL`) tem uma pegadinha de `READ COMMITTED`: a sessão que esperava o lock reavalia o `WHERE` sobre a linha que o concorrente acabou de substituir; como o ponteiro agora está preenchido, a linha deixa de casar e a consulta devolve **zero linhas**. Lido como "competência nunca lançada", o perdedor da corrida receberia 404/erro genérico em vez do conflito de concorrência otimista. Achado por `RetificacaoConcorrenciaTest` (dois processos, COMMIT real).

- **Decisão:** `RetificarLancamento` trata "vigente ausente" assim: se ainda existe alguma versão não apagada da competência, é `RetificacaoConflitante`; só quando não existe nenhuma é `CompetenciaNaoLancada`.
- **Efeito sobre o ADR-007:** nenhum. A sequência (lock → conferir `base_versao` → conferir hash → UPDATE do ponteiro → INSERT) é a mesma; apenas o ramo "vazio" ganha significado.
