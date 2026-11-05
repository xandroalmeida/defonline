---
story_id: STORY-048
slug: motor-drm
title: "Motor do DRM — estrutura canônica em três setores, valores nominais e percentuais"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: false
status: in_review
owner_agent: programador (claude-sonnet-5-5)
created_at: 2026-08-04
updated_at: 2026-11-05
estimated_session_size: L
decisoes: [PDR-003, PDR-004, PDR-008]
---

# STORY-048 — Motor do DRM

## Contexto

O coração do módulo, e a parte mais simples dele: somas e subtrações encadeadas sobre até 36 entradas, produzindo 11 linhas com valor nominal e percentual sobre a receita. A dificuldade não é o cálculo — é o rigor. Este motor produz o número que o usuário vai usar para decidir preço e contratação, e que vai alimentar a série histórica pelos próximos anos. Erro aqui é erro que se propaga e que ninguém percebe.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `epics/EPIC-010-*/design/estrutura-drm.md` (**referência aritmética canônica — é o contrato desta estória**); `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md`; **`decisions/adr/ADR-007-modelo-dados-drm-versionamento-retificacao.md`** (decisão da STORY-045; ver §*Para o time — impacto por estória*)
- Código existente a estudar: o motor de Diagnóstico e seus indicadores. Ele já resolveu pureza, aritmética exata, versionamento, resultado indisponível com motivo semântico e teste arquitetural de pureza. **Siga o mesmo padrão.**

## O quê

Implementar o motor que transforma um lançamento mensal na Demonstração de Resultado Mensal, com valor nominal e percentual em cada linha, funcionando nos três setores.

## Por quê

É o que responde a pergunta que dá nome ao épico. E é o pedido literal do Key User: *"registrar no relatório também o valor nominal junto a cada indicador apurado"*.

## Critérios de aceite

- [ ] **CA-1 (Estrutura exata):** o motor produz as linhas de `design/estrutura-drm.md` §1, com as fórmulas exatamente como especificadas, na ordem especificada. A ordem faz parte do contrato.
- [ ] **CA-1b (Dois modos):** o motor entrega os dois modos de apresentação do pró-labore — §1.1 (`fora`, default) e §1.2 (`dentro`) —, com os rótulos, o número de linhas e a ordem de cada um. **O modo é aplicado na leitura, não no cálculo persistido:** o snapshot guarda os valores que permitem derivar ambos, e alternar o modo não recalcula nem migra nada.
- [ ] **CA-1c (Invariante entre modos — obrigatório):** teste dedicado prova que o **Lucro Líquido do Mês é idêntico nos dois modos**, sobre um conjunto amplo de entradas. Divergência aqui é bug grave: a resposta a "meu negócio deu lucro?" não pode depender de uma preferência de exibição.
- [ ] **CA-1d (Linhas não operacionais):** L7b (`D41 − D42`) implementada nos dois modos, posicionada após o Resultado Operacional. **Omitida quando ambos os campos são zero** — sem linha zerada na tela.
- [ ] **CA-1e (Linha auto-descritiva — `tipo` e `destaque`):** cada linha da `demonstracao` carrega, além de código, nível, rótulo, nominal e percentual, mais dois campos: **`tipo`** (`total | deducao | subtotal | item`) e **`destaque`** (bool). Valem para os dois modos de pró-labore e são persistidos no snapshot como qualquer outro campo da linha. Os três destaques da tela (quanto vendeu / sobrou antes / sobrou depois) e o ponto em que um card de trecho fecha no mobile passam a ser **propriedade do dado**, não configuração no código — mesmo fundamento com que o `ADR-007` mandou persistir os rótulos. Decidido no `PDR-008` §1; proposta original no spec `SCREEN-STORY-046-demonstracao-mes` §9.
- [ ] **CA-2 (Nominal e percentual):** cada linha carrega valor nominal **e** percentual sobre a Receita Bruta. Percentual com 1 casa decimal na apresentação.
- [ ] **CA-3 (Três setores):** o mesmo motor atende Indústria, Comércio e Serviços. Campos suprimidos por setor entram como ausentes, não como zero, e não alteram a estrutura de linhas.
- [ ] **CA-4 (Modo agregado):** grupo em modo agregado usa o total informado; em modo detalhado, a soma dos itens. Ambos produzem a mesma estrutura de saída.
- [ ] **CA-5 (Sem faturamento):** com Receita Bruta zero, todos os percentuais retornam indisponíveis com motivo `indisponivel:sem_faturamento` e mensagem semântica; **os valores nominais continuam presentes**. Nenhuma exceção, nenhuma divisão por zero, nenhum zero disfarçado de percentual.
- [ ] **CA-6 (EBITDA padrão):** o motor calcula `L5 − (L6 + pró-labore)`, que é o Resultado Operacional do modo `dentro`. Com o `PDR-004`, este valor **deixou de ser um cálculo paralelo oculto** e passou a ser exibido quando o modo `dentro` está ativo — é o mesmo número, agora com uso.
- [ ] **CA-7 (Pureza):** o motor não lê banco, não usa relógio, não usa sessão nem usuário autenticado. Competência e carimbo de tempo entram como parâmetro. Garantido por **teste arquitetural**, no mesmo padrão já usado pelo motor de Diagnóstico.
- [ ] **CA-8 (Aritmética exata):** sem ponto flutuante. Arredondamento apenas na formatação. Resultado bit-exato e reprodutível.
- [ ] **CA-9 (Versionamento):** o motor tem versão semântica própria, independente do motor de Diagnóstico, gravada em cada snapshot. Diagnósticos e lançamentos antigos **nunca** são recalculados. Conforme o `ADR-007`: campo `motor_drm_version`, `config/drm.php` com `version = '1.0.0'`, **contador independente** (bumpar um nunca bumpa o outro) e **sem `matrix_version`** — o DRM não usa a matriz do Diagnóstico.
- [ ] **CA-10 (Golden tests):** conjunto de casos congelados cobrindo, no mínimo: um mês saudável por setor, um mês com prejuízo, um mês sem faturamento, um mês com todos os grupos agregados, um mês com todos detalhados, um mês misto, e cada um dos dois modos de pró-labore. Alteração de saída deve quebrar o teste — é esse o objetivo.
- [ ] **CA-11 (Conferência com a planilha original):** teste que reproduz, para um conjunto de entradas, exatamente os valores que a planilha `Demonstração de Resultado Mensal - DRM.xlsx` produziria, célula a célula, conforme o mapeamento do Anexo K §K.11. Evidência anexada nas notas.
- [ ] **CA-12 (Cobertura):** ≥ 98% — é núcleo de regra de negócio.

## Fora de escopo

- Exibir qualquer coisa (STORY-049) — inclusive a UI de troca de modo, que é da tela. O motor só precisa **entregar** os dois modos.
- Faróis e leitura em prosa (STORY-054). Comparativos históricos (STORY-051).
- Recomendações textuais por faixa — o DRM **não** usa a matriz do Diagnóstico.
- Provisão de rubricas sazonais — sob spike na STORY-058. O motor trata férias e 13º como despesa do mês em que foram pagas.

## Desbloqueio

Esta estória estava `blocked` por `[DECIDIR-B]` e `[DECIDIR-E]`. **Ambas resolvidas em 04/08/2026 pelo `PDR-004`:** o pró-labore é parametrizável com default `fora`, e as linhas não operacionais foram aprovadas. A estória subiu de `M` para `L` por causa dos dois modos.

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`. Cobertura de núcleo ≥ 98%, sem exceção.

## Dependências

- **Bloqueada por:** STORY-045; STORY-047 (formato do lançamento); `[DECIDIR-B]`; `[DECIDIR-E]`.
- **Bloqueia:** STORY-049, STORY-051, STORY-054, STORY-055.

## Decisões já tomadas (não as reabra)

- `design/estrutura-drm.md` v1.1 é o contrato aritmético. Divergiu, é bug.
- PDR-004 — dois modos de pró-labore (default `fora`), percentual em todas as linhas, rótulo "antes do pró-labore", linhas não operacionais aprovadas.
- PDR-003; Anexo K v1.1; **ADR-007** (decisão da STORY-045); **PDR-008 §1** (`tipo` e `destaque` na linha — CA-1e); IDR-010 (padrão snapshot, não recálculo — estendido pelo ADR-007, não revogado).

**Do `ADR-007`, o que muda para você:** a `demonstracao` que você devolve é **auto-descritiva** (lista de linhas com código, nível, rótulo, nominal, percentual e motivo) e carrega **os dois modos de pró-labore já resolvidos, com rótulos** — `demonstracao.linhas.fora[]` e `demonstracao.linhas.dentro[]`. Persistir os rótulos é obrigatório, não conveniência: é a lição do aditivo de 04/08/2026 do IDR-010, quando trocar uma palavra num texto de veredito se revelou output do motor. Com isso, o CA-1b sai do motor e a troca de modo da tela vira escolher o outro array. Nominais serializados como string com casas fixas.

**Do `PDR-008` §1, o que se acrescenta:** a linha ganha `tipo` e `destaque` (CA-1e). É extensão do mesmo princípio — a linha se explica sozinha, e quem lê o snapshot daqui a três anos não precisa da configuração vigente hoje para renderizá-lo. **A janela para isso é agora:** snapshot é imutável (`ADR-007`, `IDR-010`), então meses já fechados nunca ganhariam os campos, e a configuração paralela viraria permanente — o beco em que o `IDR-013` já entrou uma vez.

## Liberdade técnica do agente

Você decide como estruturar as linhas em código, como representar a saída, e o desenho dos testes. Você **não** decide fórmulas, ordem de linhas, rótulos, nem se uma linha existe.

## Definição de Pronto

- [ ] CA-1 a CA-12 passam (inclusive o CA-1e, acrescentado pelo `PDR-008`).
- [ ] Golden tests congelados e commitados.
- [ ] Evidência de CA-11 nas notas.
- [ ] Pipeline verde; `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

Entendimento e plano (2026-11-05): lidos a estória, `estrutura-drm` v1.1, Anexo K v1.1, ADR-007, PDR-008 §1, DDR-004, IDR-010/016, nota da STORY-047, o motor do Diagnóstico e o pacote de rótulos (`config/drm/rotulos.php`). Plano: (1) `App\Domain\Drm\MotorDrm` puro, centavos inteiros, devolve a `demonstracao` auto-descritiva com os dois modos; (2) `Percentual` (4 casas truncadas + formatação a 1 casa); (3) `FecharLancamento` grava o snapshot; migration de backfill + CHECK para nenhum mês fechado ficar sem snapshot; (4) testes: fórmulas por linha, invariante L11 em 2000 casos, borda, golden, planilha célula a célula, arquitetura, versão.

### Decisões tomadas

- **`App\Domain\Drm\MotorDrm`** (puro) recebe `lancamento_payload` canônico + `Setor` + `Competencia` + carimbo e devolve a `demonstracao`: cabeçalho (`motor_drm_version`, `competencia`, `setor`, `gerado_em` em UTC, `modos`) e `linhas.fora[]` / `linhas.dentro[]`. Cada linha: `codigo, nivel, rotulo, tipo, destaque, nominal, percentual, motivo, mensagem, motivo_nominal, mensagem_nominal`. Rótulos vêm de `config/drm/rotulos.php` (pacote validado da STORY-052); rótulo ausente é `LogicException`, nunca `null` gravado em coluna imutável.
- **Aritmética exata:** centavos inteiros; nominal serializado `"1234.56"` (negativo `"-25.50"`); percentual por quociente/resto inteiros (`Percentual`), **truncado em direção a zero em 4 casas** (`"13.3333"`). Truncar (e não arredondar) é o que garante que o arredondamento da tela (1 casa, meio para cima em módulo, `Percentual::formatar`) dê o mesmo que arredondar o valor exato — arredondar duas vezes erraria em casos como 13,34996 (teste dedicado). `Dinheiro::decimal/deDecimal` da STORY-047 não suportavam negativo (`-5` virava `"0.-5"`); corrigi (comportamento para não negativos inalterado) porque prejuízo é o caso de uso.
- **`tipo` e `destaque` (CA-1e):** `total` = linhas "= …" (L3, L5, L7, L9, L11); `deducao` = nível 1 com "(–)"/"(±)" (L2, L4, L6, L7b, L8, L10); `subtotal` = nível 2 (L6a, L6b, L8a, L8b); `item` = a âncora L1 e o nível 3 (L6c). Um card de trecho fecha em cada `total` (DDR-004 item 4), então L1 **não** é `total` (abre o card "Receita" e fecha em L3). `destaque`: `fora` = L1, L9, L11; `dentro` = L1, L7 (EBITDA), L11 (DDR-004 item 3 e spec §"2º destaque acompanha"). **Interpretação minha, pedir confirmação do PO/Designer na STORY-049:** L1 como `item` e L7b como `deducao`.
- **Níveis e existência de linha:** o contrato §1 lista L8a/L8b (nível 2) sem condição e L6c (nível 3) "quando o grupo administrativo está em modo detalhado". Segui literalmente: nível 2 existe sempre — com despesas financeiras **agregadas**, L8a/L8b saem com `nominal`/`percentual` nulos e motivo `indisponivel:grupo_agregado` (CA-4 "mesma estrutura"; nunca se infere item de total, ADR-007); nível 3 (L6c) só no modo `dentro` com administrativas detalhado. L7b é omitida quando D41 e D42 são **ambos zero/ausentes** (se valem o mesmo e se anulam, a linha aparece com 0,00: houve lançamento).
- **Motivos:** `motivo`/`mensagem` descrevem o **percentual** (CA-5: `indisponivel:sem_faturamento` quando D01 = 0, em todas as linhas); `motivo_nominal`/`mensagem_nominal` descrevem a ausência do **nominal** (`grupo_agregado`). Dois pares porque os dois podem coexistir (item agregado em mês sem faturamento) e CA-5 exige `sem_faturamento` em todos os percentuais.
- **Campo ausente:** `null` (suprimido pelo setor / não coletado) entra como 0 na soma e não muda a estrutura; valor em campo suprimido pelo setor (dado fora do contrato) é ignorado porque a soma usa `Grupo::itens($setor)`. Payload fora do contrato (decimal malformado, negativo, `modos` ausente/ inválido) é `InvalidArgumentException` — o motor não adivinha.
- **Fechamento:** `FecharLancamento` roda o motor sobre o payload canônico (o mesmo do hash) e grava `demonstracao` + `motor_drm_version` (lido do próprio snapshot) + `gerado_em` com o mesmo `$agora`, na mesma transação.
- **"Mês fechado sem snapshot não pode existir":** migration `2026_11_05_100020_exige_demonstracao_no_snapshot_do_drm` (1) faz o **backfill** de linhas com `demonstracao` sem `linhas` (herança da STORY-047: `[]`), com o motor sobre o `lancamento_payload` gravado e o `gerado_em` original como carimbo — **exceção declarada à imutabilidade**, só para esses meses, e atualiza `motor_drm_version` junto; (2) instala o `CHECK drm_lancamentos_demonstracao_check` (os dois arrays existem e `demonstracao.motor_drm_version = motor_drm_version`). O `COALESCE` é necessário: CHECK deixa passar `NULL` e `jsonb_typeof` de caminho inexistente é `NULL` (sem ele `[]` passaria — achado do teste). A `DrmLancamentoFactory` passou a gerar o snapshot pelo motor.
- **Versão:** `config/drm.php` `motor_drm_version` 0.0.0 → **1.0.0**, sem `matrix_version`, independente de `motor.version`, `motor.matrix_version` e `drm.conteudo_version` (teste muda os três e o carimbo não se mexe).
- **Golden:** 10 cenários em `tests/Fixtures/Drm/golden/*.json` (entrada congelada + saída completa dos dois modos, comparação `===`). Gerados uma vez por script descartável e **conferidos à mão** nos totais de quatro cenários (saudável_comercio — contas no comentário de `MotorDrmTest` —, prejuízo, todos_agregados, sem_faturamento). Não há modo de regeneração automática.

### Descobertas

- **A CA-11 foi feita sobre a planilha real**, não sobre a descrição: extraí as fórmulas de `sheet1.xml` do `.xlsx` (mapeamento em `tests/Fixtures/Drm/planilha-drm.json`, só fórmulas, sem dado de cliente). Divergências entre a planilha e o Anexo K §K.11: (a) o §K.11 aponta células de **rótulo** para alguns campos (ex.: D40 → `B48`, que é o texto; o valor está em `D48`; D01 → `C3`, valor em `D3`) — a coluna de valores é D, e foi ela que usei; (b) a planilha só tem `%` em 10 linhas (E7, E12, E13, E14, E21, E43, E44, E47, E48, E49); L1, L2, L3 e L8a/L8b ganham `%` pelo PDR-004 com a mesma fórmula `D<n>/D$2*100`. **Sugiro corrigir o §K.11** (célula errada na tabela, não no motor).
- **Evidência CA-11:** `MotorDrmPlanilhaTest` avalia as fórmulas da planilha (inclusive `SUM(D22:D42)`) sobre entradas aleatórias com semente fixa — 300 lançamentos × 15 linhas × 3 setores = 13.500 células conferidas no modo `fora`, em centavos inteiros (igualdade exata) e `%` contra `D/D$2*100` em ponto flutuante (diferença < 0,0001 ponto, que é a precisão do truncamento); mais 200 casos do modo `dentro` (L6 = D13 + pró-labore, L7 = D12 − L6, L11 = D49 da planilha). Exemplo congelado (`saudavel_comercio`): receita 100.000,00 → L5 58.000,00 (58,0000%) → L7 35.199,25 (35,1992%) → L9 34.349,25 → L11 26.349,25 (26,3492%); `dentro`: L7 (EBITDA) 27.199,25 (27,1992%), L11 igual.
- **CA-1c:** `MotorDrmInvarianteTest` — 2000 lançamentos aleatórios (semente fixa; garante > 200 lucros e > 200 prejuízos): L11 idêntico nos dois modos em nominal **e** percentual, e igual ao `Lancamento::lucroLiquido()` da STORY-047 (a conta do alerta A3 concorda com o motor). Mais 500 casos nas relações entre linhas (L6 dentro = L6 fora + L10; L7 dentro = L7 fora − L10). O mesmo invariante é asserido sobre o dado persistido (`SnapshotDrmTest`) e em cada golden.
- **Mutação:** 5 mutações manuais (sinal de L8 em L9; pró-labore fora de L6b; L5 a partir de L1; arredondar em vez de truncar; tipo de L8a) derrubaram 11–20 testes cada. Restauradas.
- **Descompasso de fuso no ambiente de desenvolvimento (fora do meu escopo):** o `docker-compose.yml` sobe o Postgres em `America/Sao_Paulo` com o app em UTC; o Laravel grava o horário de parede sem offset, então qualquer `timestamptz` (inclusive `diagnosticos.gerado_em`) fica deslocado em 3 h no banco de dev. Em produção (Postgres em UTC, sem `TZ` no compose do Zalk) não ocorre. O motor emite `gerado_em` em UTC; os testes do snapshot fixam a sessão em UTC (`SET LOCAL TIME ZONE`). Vale um ajuste de conexão (`'timezone' => 'UTC'`) numa estória própria.
- **Teste de outra estória alterado:** `ConteudoDrmArchTest` "conteudo_version é separada de motor_drm_version" provava a separação por os **valores** diferirem (0.0.0 × 1.0.0); com o motor em 1.0.0 (exigido pelo ADR-007 e pela CA-9) os dois valem 1.0.0 por coincidência. Troquei a prova por independência real (ambas semver; o motor não referencia `conteudo_version`; `MotorDrmArchTest` muda as três versões e verifica o carimbo). Também: `LancamentoTest` (Livewire) esperava `0.0.0` e passou a `1.0.0`; `PersistenciaDrmTest::trocarVersao` usava payload fake `['x' => '1.00']`, que o motor corretamente recusa — agora parte do payload da versão anterior com um campo alterado.
- `ConteudoDrm::indisponibilidade()` foi acrescentado (3 linhas) — a mensagem do motivo já estava em `rotulos.php` sem acessor.
- Cobertura do motor não depende de config provisória: `MotorDrm` só lê `rotulos` (validado), então não toca o arquivo de lacunas.

### Bloqueios encontrados

Nenhum. Pendências de decisão para o PO (nenhuma bloqueia a entrega): (1) `tipo` de L1 (`item`) e L7b (`deducao`); (2) L6c só com administrativas detalhado vs. sempre (segui a letra do §1.2); (3) origem do setor (`Setor::doCnae()` continua provisório, herdado da STORY-047 — o motor recebe o setor por parâmetro e não depende da regra).

### IDRs criados

Nenhum. As decisões acima são locais ao motor (formato do snapshot dentro do que o ADR-007/PDR-008 já fixaram). Se o PO preferir registrar o percentual truncado em 4 casas como convenção do snapshot, vira IDR curto.

### Cobertura final

- Suíte `All`: **1560 passed** (43.497 assertions); `--coverage --min=80`: total **98,1%**. `MotorDrm`, `Percentual`, `Dinheiro` e `FecharLancamento`: **100%** (gate de `app/Domain`: 98%).
- Dusk `DrmLancamentoBrowserTest` (fechamento passa pelo motor): 5 passed. Pint e PHPStan limpos.
- **Não verificado:** suíte Dusk completa (só a do lançamento DRM, que é a que passa pelo fechamento); deploy/homologação (fora da pista); a tela que lê o snapshot (STORY-049).

