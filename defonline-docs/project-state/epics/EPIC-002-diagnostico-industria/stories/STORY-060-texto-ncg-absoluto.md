---
story_id: STORY-060
slug: texto-ncg-absoluto
title: "Texto do NCG absoluto — levar a intenção editorial da EBC para as três faixas e tratar a entrada inerte da matriz"
epic_id: EPIC-002
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: in_review
owner_agent: programador (agente Claude — sessão W32 fila 4.2)
created_at: 2026-08-04
updated_at: 2026-08-06
estimated_session_size: S
origem: "Escalação do CA-7 da STORY-042, decidida no PDR-006 §2"
---

# STORY-060 — Texto do NCG absoluto

## Contexto

A `STORY-042` levou o texto revisado da EBC para a entrada `ncg_absoluto` da matriz dez-2025. O texto está lá, mas **nenhum diagnóstico o exibe**: o indicador `NcgAbsoluto` não consome a matriz.

Isso não é bug. A `STORY-032` migrou 13 indicadores para a matriz e deixou o NCG absoluto de fora com justificativa registrada: o Anexo F tem 2 cenários, o motor entrega 3 faixas semânticas (folga / moderado / alto), e a granularidade maior é mais útil ao usuário.

O `PDR-006` §2 marcou o CA-7 como `n/a` — ele foi escrito sobre a premissa errada de que o indicador lê a matriz. Fica o resíduo legítimo que esta estória resolve: **a intenção editorial da EBC de fato não chegou ao usuário**, e a entrada da matriz virou configuração inerte que vai confundir quem passar por ela.

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- Ler antes: `decisions/pdr/PDR-006-escalacoes-sprint-w32.md` §2; o docblock do indicador `NcgAbsoluto`, que documenta a decisão da STORY-032; `especificacao/V2/anexos/anexo-F-matriz-recomendacoes-dez2025.md` §F.13; `decisions/idr/IDR-010-*`

## O quê

Incorporar a intenção editorial do texto revisado da EBC às três mensagens de faixa que o produto realmente exibe, e resolver a entrada inerte da matriz.

## Por quê

O usuário com NCG positivo recebe hoje uma mensagem que a EBC quis melhorar. A melhoria existe, escrita e revisada — só está no lugar errado.

## Critérios de aceite

- [x] **CA-1 (Intenção editorial nas faixas):** as mensagens das faixas **moderado** e **alto** incorporam a orientação do texto revisado da EBC — que a elevação do NCG seja acompanhada, na mesma proporção, pelo aumento das fontes de financiamento de longo prazo, preferencialmente fornecedores. A mensagem de **folga** é revisitada contra o cenário "negativa" do Anexo F.
- [x] **CA-2 (Granularidade preservada):** continuam sendo **três** faixas. A decisão da `STORY-032` não é revertida — não se troca três mensagens por duas.
- [x] **CA-3 (Bump de versão do motor):** as mensagens fazem parte da saída do motor. A versão sobe conforme o padrão, os golden hashes são reemitidos deliberadamente, e o diff dos hashes é conferido e registrado — a mudança tem que atingir exatamente os cenários de NCG e nenhum outro.
- [x] **CA-4 (Diagnósticos legados intocados):** diagnósticos gerados antes desta mudança preservam o texto com que foram gerados. `IDR-010`, sem exceção. Teste dedicado.
- [x] **CA-5 (Entrada inerte da matriz):** a entrada `ncg_absoluto` em `config/motor/matriz-dez-2025-industria.php` deixa de ser uma armadilha. Duas saídas aceitáveis, à sua escolha, justificada nas notas: remover a entrada, ou mantê-la com marcação explícita de que é referência editorial não consumida em runtime. Se mantida, o teste arquitetural da matriz precisa saber que essa chave é a exceção conhecida — e não passar a acusá-la.
- [x] **CA-6 (Rastreabilidade):** o docblock do indicador continua explicando por que ele não consome a matriz, agora apontando também para esta estória e para o `PDR-006`.
- [x] **CA-7 (Testes):** unitários cobrindo as três faixas com os textos novos; teste de legado; golden hashes reemitidos e verdes.
- [x] **CA-8 (Cobertura):** ≥ 98% — é núcleo do motor.

## Fora de escopo

- Reverter a decisão da `STORY-032`.
- Fazer o `NcgAbsoluto` consumir a matriz.
- Mexer em qualquer outro indicador ou em qualquer outra entrada da matriz.
- Recalcular diagnósticos existentes.

## Dependências

- **Bloqueada por:** `STORY-042` (o texto revisado precisa estar na matriz para servir de fonte).
- **Bloqueia:** nada.

## Decisões já tomadas (não as reabra)

- `STORY-032` — o NCG absoluto não consome a matriz, e são três faixas.
- `PDR-006` §2 — o CA-7 da 042 é `n/a`; o produto não muda para satisfazer critério mal formulado.
- `IDR-010` — sem recálculo retroativo.

## Definição de Pronto

- [x] CA-1 a CA-8 passam.
- [x] Diff dos golden hashes conferido e registrado nas notas.
- [x] Suíte completa verde local (926 testes, 2819 asserções); Pint e PHPStan limpos. **Deploy em homologação pendente** — depende de tag, que só o PO cria (ver "Bloqueios encontrados").
- [x] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. A redação final das três mensagens é conteúdo editorial: proponha, registre nas notas, e sinalize ao PO para revisão antes do merge.

## Notas do agente

### Plano inicial (registrado antes de codar)

**Documentos lidos:** a estória inteira; `PDR-006` §2; docblock de `App\Domain\Motor\Indicadores\NcgAbsoluto`; `anexo-F-matriz-recomendacoes-dez2025.md` §F.13 e §F.14; `IDR-010`; `config/motor.php`; `config/motor/matriz-dez-2025-industria.php`; `MatrizRecomendacoes.php`; os testes `NcgAbsolutoTest`, `MatrizNcgPositivoTest`, `MatrizArchTest`, `GoldenHashesTest` e `DiagnosticoShowTest`.

**Entendimento consolidado.** O texto revisado da EBC (§F.13, cenário "NCG positiva", coluna Indústria) mora na matriz mas ninguém o lê: `NcgAbsoluto` tem três mensagens em constantes de classe e não chama `MatrizRecomendacoes`. Isso é decisão da `STORY-032`, não bug. O trabalho é (a) trazer a **intenção** daquele texto para as constantes das faixas MODERADO e ALTO — que são o que o usuário efetivamente lê —, revisitando FOLGA contra o cenário "NCG negativa"; e (b) desarmar a entrada da matriz, que hoje parece configuração ativa e não é.

**Dúvidas:** nenhuma bloqueante. A redação final é conteúdo editorial — proposta abaixo, sinalizada ao PO conforme o Protocolo do agente.

**Plano:**

- Reescrever `MSG_FOLGA`, `MSG_MODERADO` e `MSG_ALTO` incorporando a orientação da EBC; três faixas continuam três.
- Marcar a entrada `ncg_absoluto` da matriz como referência editorial não consumida em runtime (CA-5, opção "manter com marcação"), com teste arquitetural que conhece a exceção em vez de acusá-la.
- Atualizar o docblock do indicador apontando para esta estória e para o `PDR-006` (CA-6).
- Bump de `motor.version` + reemissão dos golden hashes, com diff conferido isolando o bump da mudança de texto (CA-3).
- Teste dedicado de legado (CA-4): diagnóstico persistido com as mensagens antigas renderiza as antigas.

**Testes previstos:** três faixas com os textos novos (`NcgAbsolutoTest`); bordas já existentes (NCG=0, NCG=10% exato, vendas null/zero, componentes ausentes) revalidadas contra os textos novos; legado renderizando texto de snapshot (`DiagnosticoShowTest`); arquitetural da exceção da matriz (`MatrizArchTest`); golden hashes (`GoldenHashesTest`).

### Decisões tomadas

**1. Redação das três faixas (CA-1) — proposta, pendente de revisão do PO.**

| Faixa | Texto entregue |
|---|---|
| FOLGA (`NCG ≤ 0`) | *"Folga operacional: o prazo que você tem para pagar fornecedores cobre o ciclo de estoques e de recebimento dos clientes. Na prática, o giro do negócio não exige dinheiro seu para funcionar — preserve os prazos que produzem essa folga."* |
| MODERADO (`0 < NCG ≤ 10% Vendas`) | *"NCG positivo moderado: patamar gerenciável. Se ele crescer — por aumento das vendas ou do prazo concedido a clientes —, o crescimento precisa vir acompanhado, na mesma proporção, do aumento das fontes de financiamento de longo prazo, preferencialmente o prazo de pagamento a fornecedores. Acompanhe seus prazos médios (PMC, PME e PMR)."* |
| ALTO (`NCG > 10% Vendas`) | *"NCG positivo alto: pressão sobre o caixa. Antes de ampliar vendas a prazo ou estoques, aumente na mesma proporção as fontes de financiamento de longo prazo, preferencialmente o prazo de pagamento a fornecedores. Sem isso, reveja seus prazos médios ou estruture captação de longo prazo."* |

Três escolhas editoriais que o PO precisa ratificar ou derrubar:

- **A orientação da EBC entra nas duas faixas positivas, não só na ALTO.** O texto do Anexo F fala da *elevação* da NCG, e quem está em MODERADO é justamente quem ainda pode crescer sem quebrar. Colocá-la só na faixa ALTO seria chegar tarde.
- **Ela não entra na faixa FOLGA.** Quem tem NCG negativo não precisa financiar giro nenhum; repetir a orientação ali seria conselho certo no cenário errado. Há teste dedicado impedindo que volte.
- **O termo "IOG" foi deixado de fora.** O cenário "negativa" do Anexo F fecha com *"NCG negativa significa Investimento Operacional em Giro (IOG)"* — sigla que o leitor do relatório não conhece e que, sozinha, não muda nenhuma decisão dele. A substância ("a empresa não necessita de recursos para o giro") está dita em linguagem direta. Se a EBC quiser a sigla explicitada, o lugar é o glossário do relatório, não o card.

Os testes checam **os elementos** da orientação (`na mesma proporção`, `longo prazo`, `fornecedores`), não a frase inteira — o PO pode reescrever sem quebrar a suíte, desde que os três elementos sobrevivam. Reescrita implica novo bump e novo re-emit dos hashes.

**2. Entrada inerte da matriz (CA-5) — mantida, com marcação, não removida.** A matriz é transcrição literal do Anexo F e serve de fonte de auditoria editorial; apagar a entrada perderia isso. Ela desceu para uma seção `_referencia_editorial` no fim do arquivo, sob uma chave que **nenhum código procura**. Três consequências verificadas por teste: a chave `ncg_absoluto` não é mais de primeiro nível; qualquer chave nova de primeiro nível que não seja indicador com farol é acusada pelo teste arquitetural (a exceção é uma lista declarada, não um `if` escondido); e chamar `MatrizRecomendacoes::texto('ncg_absoluto', …)` agora cai no fallback e vira lacuna registrada, em vez de devolver em silêncio um texto que o produto nunca exibiu — que era exatamente a armadilha descrita na estória.

**3. PATCH, não MINOR (CA-3).** `motor_version` 1.3.1 → 1.3.2, pelo mesmo critério da STORY-041: texto de saída muda, fórmula não. Nenhum valor, farol, ordenação de destaque ou veredito é afetado. `matrix_version` **não** sobe — nenhum texto da matriz foi editado e a entrada movida nunca foi consumida.

### Descobertas

**O diff dos golden hashes foi conferido por reconstrução, e o método vale para o próximo re-emit.** Comparar "hash mudou / não mudou" não prova o que o CA-3 pede — prova só que algo mudou. O que foi feito: com `motor.version` fixado de volta em 1.3.1, recalculei os 5 fixtures e substituí, no JSON de saída, cada mensagem nova de NCG pela mensagem exata de 1.3.1. **Os 5 hashes voltaram bit a bit aos congelados anteriores.** Se qualquer outro campo tivesse mudado junto, a reconstrução não fecharia. Quatro fixtures trocam uma mensagem cada; `70pct_indisponivel` não tem NCG calculável e só muda por causa do `motor_version` no payload.

| Fixture | Hash 1.3.1 | Hash 1.3.2 |
|---|---|---|
| `saudavel` | `9eaa789e…16a9` | `840deb65…648c` |
| `atencao` | `71ce9a88…3829` | `9ecd69df…6579` |
| `alerta` | `9d89a89c…b64c` | `1d6a4ff2…fcb2` |
| `ncg_negativo` | `5a3a855d…5b10b` | `67d4ef83…c05f` |
| `70pct_indisponivel` | `a858410d…bafa8` | `79b7c237…d2f3` |

**A regra do aditivo da STORY-041 valia mais do que o texto dizia.** Aquele aditivo do `IDR-010` listava as constantes de `ResumoExecutivo` como o que exige bump. A mecânica, porém, é a mesma para **qualquer** constante de mensagem de indicador: ela entra no JSONB imutável e no payload hasheado. O `IDR-010` foi estendido com essa generalização (aditivo de 2026-08-06) para que o próximo agente não precise redescobrir.

**O teste da STORY-042 (`MatrizNcgPositivoTest`) tinha uma escalação pendente registrada no próprio arquivo.** Ela foi decidida no `PDR-006` §2 e está agora fechada no cabeçalho do arquivo — o teste continua provando o que provava, e as asserções apontam para o novo caminho da entrada editorial.

### Bloqueios encontrados

**Deploy em homologação não executado — falta de mandato, não de trabalho.** A DoD pede deploy verificado. O deploy nasce do push de uma tag `rc`, e criar/empurrar tag é ato do PO (padrão do projeto: agente não empurra tag nem abre PR sem pedido explícito). O trabalho está commitado em `main` local, com suíte, lint e análise estática verdes. **A estória fica em `in_review`** até o PO rodar o release e conferir o card de NCG em homologação — que é o único critério que sobra.

Nada mais bloqueou.

### Cobertura final

- Suíte completa: **926 testes, 2819 asserções, verde**.
- Cobertura geral (`--testsuite=All`): **97,4%** (piso do PO: 80%).
- Cobertura do domínio (`phpunit-domain.xml`): **99,6%** (piso do PO: 98% — CA-8).
- `App\Domain\Motor\Indicadores\NcgAbsoluto`: **100%**.
- Pint: 243 arquivos, limpo. PHPStan: 108 arquivos, sem erros.
- Testes novos: 6 em `Indicadores/NcgAbsolutoTest.php` (CA-1, CA-2), 3 em `MatrizArchTest.php` (CA-5), 5 em `Feature/Http/DiagnosticoNcgLegadoTest.php` (CA-4).
