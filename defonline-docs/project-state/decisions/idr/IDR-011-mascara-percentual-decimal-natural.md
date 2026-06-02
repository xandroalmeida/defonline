---
idr_id: IDR-011
slug: mascara-percentual-decimal-natural
title: Campo percentual usa máscara decimal natural (não "centavos") — correção da causa-raiz da inadimplência recusada
status: accepted
decided_at: 2026-06-02
decided_by: programador (claude-opus-4-8)
approved_by: Alexandro
approved_at: 2026-06-02
owner_agent: programador (claude-opus-4-8)
related_story: STORY-039
related_stories: ["STORY-027", "STORY-014"]
related_adrs: []
related_idrs: ["IDR-010"]
supersedes: null
superseded_by: null
created_at: 2026-06-02
updated_at: 2026-06-02
---

# IDR-011 — Campo percentual usa máscara decimal natural (não "centavos")

## Contexto

A STORY-039 nasceu de uma devolutiva do Edvan (WhatsApp #2, 2026-05-27): o campo
**Inadimplência** (Q13 no código; Q22 do Anexo A) teria recusado `3,46%` com o
alerta de que *"só admite informação entre ZERO e CEM"*. O briefing do PO e a
própria estória hipotetizaram um **bug de parsing no servidor** — algo como
`(int)"3,46"` ou comparação `valor > 100` **antes** de trocar a vírgula por ponto
— e mandaram procurar por `between:0,100` / `numeric|max:100`.

Ao executar a estória, reproduzi o fluxo real (Livewire + container) e descobri
que **o bug descrito não existe no código atual**:

- `Quiz::parseDecimal("3,46")` retorna `3.46` corretamente (introduzido na STORY-027).
- `regraPercentual` (único validador `%` do quiz, aplicado só ao Q13) **aceita**
  `3,46`, `0,00`, `100,00`; rejeita `100,01`, `-1`, `abc`. Provado por teste
  Livewire ponta-a-ponta antes de qualquer mudança.
- Não há `max:100` / `between` em FormRequest nem rule string — só a Closure.

A causa-raiz provável do que o Edvan viu é a **máscara JS** do campo `%`
(`$maskPct` na `quiz.blade.php`), que era **estilo "centavos"** (igual à máscara
de R$): interpretava **todo dígito como casa decimal**. Consequências reais:

- Digitar `100` (querendo 100%) virava **`1,00`** (1%). Para 100% era preciso
  digitar `10000`.
- Digitar `3,4` virava `34` (34%).
- Um usuário confuso, re-digitando para "consertar", facilmente produzia algo
  como `343,46` → aí sim o servidor recusava com *"entre 0 e 100"* — exatamente
  o sintoma relatado.

Ou seja: o sintoma era **real**, mas o diagnóstico da estória apontava para o
lugar errado. O servidor já estava correto; a armadilha estava na UX da máscara.
A máscara, porém, fora marcada como **fora de escopo** na estória
("Mudar a máscara visual — já está em 2 decimais").

## Decisão

> **O campo percentual do quiz passa a usar uma máscara decimal natural BR
> (o usuário digita `3,46` → fica `3,46`; `100` → `100`; `3,4` → `3,4`), em vez
> da máscara estilo "centavos". Parte inteira até 3 dígitos, 1 vírgula, até 2
> decimais; ponto digitado é tratado como vírgula (percentual não tem milhar).
> A validação de range `[0,00 ; 100,00]` continua autoritativa no servidor.
> 3+ casas decimais (só alcançáveis por bypass da máscara) são **truncadas**
> (não arredondadas) para 2 casas na canonicalização (`pctCanonico`).**

Convenção transversal: **máscaras estilo "centavos" (multiplicar por 10⁻²
implícito) são apropriadas para valores monetários (R$), mas NÃO para campos
percentuais ou de quantidade**, onde o usuário pensa no número inteiro/decimal
diretamente. Campos `%` futuros devem reusar o padrão de máscara decimal natural.

## Por quê

- **Resolve a causa-raiz**, não o sintoma: o Edvan não volta a cair na armadilha
  de `100 → 1,00`.
- **Menor surpresa**: a máscara decimal natural espelha como a pessoa digita um
  percentual ("três vírgula quarenta e seis"), sem ordem de digitação obrigatória.
- **Servidor continua a fonte de verdade**: a máscara é conveniência de UX; o
  range é garantido por `regraPercentual` (testado), então afrouxar a máscara não
  cria risco de dado inválido persistido.
- **Truncar (não arredondar)** segue o default explícito da estória (CA-5) e é o
  comportamento menos surpreendente para "2 casas decimais": `3,469 → 3,46`.

## Escopo e o que NÃO muda

- **Só o campo `%` (Q13)** muda de máscara. As máscaras de **R$** (`$maskBrl`) e
  de **dias** (`$maskDias`) permanecem como estão — para R$ o estilo "centavos" é
  desejado e os testes Dusk dependem dele (digita-se `5000000` para `50.000,00`).
- **Q13 é o único campo `%` do quiz** (1 só uso de `regraPercentual`/`maskPct`) —
  então CA-8 ("auditar outros campos %") não encontrou outros campos para quebrar.
- **Mensagem de erro** alinhada ao texto literal da CA-3/CA-4:
  `Inadimplência deve ser entre 0,00% e 100,00%.` (antes: *"O percentual deve
  estar entre 0 e 100."*).

## Consequências

- Divergência consciente do escopo escrito da STORY-039 ("máscara fora de
  escopo"): o PO foi consultado em chat (2026-06-02) com a evidência de que o
  servidor já estava correto e de que a máscara era a causa-raiz, e **aprovou
  incluir o conserto da máscara** nesta estória.
- A estória deixa de ser um "fix de validação servidor" e passa a ser um "fix de
  máscara UX + alinhamento de mensagem + cobertura". O resultado funcional ainda
  satisfaz todos os CAs (CA-1..CA-8).
- Sem impacto em `motor_version`/`matrix_version` (IDR-010): nada no cálculo muda;
  o canônico do Q13 só ganhou truncamento defensivo para 3+ casas (inatingível
  pela UI normal).

## Como verificar

- **Feature (Livewire)**: `tests/Feature/.../QuizTest.php` — testes parametrizados
  STORY-039 cobrindo CA-1 (3,46 / 3.46 / 0 / 100 aceitos), CA-2 (limites
  inclusivos), CA-3/CA-4 (fora do range → mensagem literal), CA-5 (3,469 → `3.46`
  truncado no payload), CA-6 (não-numérico → mensagem genérica).
- **Dusk**: `tests/Browser/InadimplenciaDecimalBrowserTest.php` — digita `3,46` no
  navegador real, confirma `assertInputValue('@quiz-Q13','3,46')`, submete e checa
  `quiz_payload['Q13'] === '3.46'`.
- **Regressão Dusk**: SociosLista, ValidacoesCruzadas e QuizTooltips seguem verdes
  (digitam Q13 com 1 dígito → idêntico nas duas máscaras).

## Tipo

- [x] **Workaround / correção de causa-raiz**: a estória apontava o servidor; o
  bug real estava na máscara JS.
- [x] **Convenção interna**: máscara "centavos" só para R$; percentuais/quantidades
  usam máscara decimal natural.
- [ ] **Padrão transversal**
- [ ] **Otimização**
- [ ] **Refatoração estrutural**

---

## Histórico

- 2026-06-02 — criada pelo Programador (claude-opus-4-8) durante a STORY-039 ao
  descobrir que o diagnóstico da estória estava impreciso (servidor já aceitava
  `3,46`) e que a causa-raiz era a máscara `%` estilo "centavos". PO (Alexandro)
  consultado em chat com a evidência e escolheu **"Corrigir máscara também"** —
  IDR registrada conforme o hand-off do briefing ("se descobrir padrão, registre
  IDR").

## Aceite do PO

- [x] PO (Alexandro) aprova incluir o conserto da máscara na STORY-039 — chat 2026-06-02.
- [x] `status: accepted`; `approved_by: Alexandro`, `approved_at: 2026-06-02`.
