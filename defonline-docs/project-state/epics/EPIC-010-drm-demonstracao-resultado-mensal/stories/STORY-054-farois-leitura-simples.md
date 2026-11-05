---
story_id: STORY-054
slug: farois-leitura-simples
title: "Faróis e leitura em linguagem simples — o que este mês está dizendo"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: programador (agente Claude)
created_at: 2026-08-04
updated_at: 2026-11-05
estimated_session_size: M
checkpoint: CP3
---

# STORY-054 — Faróis e leitura em linguagem simples

## Contexto

O microempresário não sabe se 12% de margem bruta é bom. Ele precisa que alguém diga. Esta estória entrega a camada de interpretação: um farol por linha e um parágrafo curto que traduz a demonstração para português comum, usando o que o mês mostra e o que os meses anteriores mostraram.

O padrão já existe no produto — o relatório de Diagnóstico tem farol classificado por faixas declarativas e um resumo executivo determinístico. **Reaproveite a mecânica, não a matriz:** o DRM tem faixas e textos próprios, vindos da STORY-052.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: pacote de conteúdo da STORY-052 (**contrato desta estória**); `epics/EPIC-010-*/design/estrutura-drm.md`; o farol e o resumo executivo do Diagnóstico já em produção.

## O quê

Classificar as linhas da demonstração em faróis por setor e gerar uma leitura determinística em linguagem simples, exibidas na Demonstração do Mês.

## Por quê

Número sem interpretação não muda comportamento. É a interpretação que faz o usuário ajustar preço, cortar despesa ou renegociar — e voltar no mês seguinte para ver se funcionou.

## Critérios de aceite

- [ ] **CA-1 (Farol só onde há faixa calibrada):** as linhas cuja faixa está marcada como `calibrada` no pacote da STORY-052 recebem farol verde, amarelo ou vermelho, respeitando o setor da empresa. **Linha com faixa `nao_calibrada` não recebe farol** — comportamento correto, não lacuna.
- [ ] **CA-1b (Grau zero funcional):** com **todas** as faixas `nao_calibrada`, a tela renderiza corretamente, sem espaços vazios nem placeholders de erro, e a leitura em prosa se apoia apenas nos comparativos. Teste dedicado para este cenário — é o estado em que o módulo vai ao ar.
- [ ] **CA-1c (Calibrar não exige deploy de código):** popular uma faixa é edição do arquivo de configuração versionado + bump da versão do conteúdo. Teste prova que uma faixa recém-calibrada passa a produzir farol sem nenhuma mudança de lógica.
- [ ] **CA-2 (Faixas declarativas):** as faixas vivem em configuração versionada, não em código. Ajustá-las não exige alterar lógica.
- [ ] **CA-3 (Leitura determinística):** o parágrafo de leitura é gerado por algoritmo determinístico a partir dos faróis e dos comparativos — mesma entrada, mesma saída, sempre. Nenhuma geração livre de texto.
- [ ] **CA-4 (Leitura usa o histórico):** havendo meses anteriores, a leitura menciona o que mudou e por quê, com base nos comparativos da STORY-051. Sem histórico, a leitura é honesta sobre isso e não inventa tendência.
- [ ] **CA-5 (Prejuízo):** a leitura de um mês com prejuízo é direta, útil e não moralizante. Aponta a linha que mais pesou, sem julgar o usuário.
- [ ] **CA-6 (Sem faturamento):** com receita zero, não há farol de margem. A leitura explica o que se pode dizer — o custo de manter o negócio parado — e não força interpretação.
- [ ] **CA-7 (Lacuna editorial):** combinação de linha e farol sem texto no pacote produz mensagem neutra de reserva e registra o fato para o PO, no mesmo padrão adotado pelo Diagnóstico. **Nunca** deixa espaço vazio nem inventa texto.
- [ ] **CA-8 (A interpretação é derivada, não congelada):** **nada da camada de interpretação — faixa, farol ou texto de leitura — é gravado no snapshot do lançamento.** Farol e leitura são derivados na exibição, a partir do pacote de conteúdo vigente e dos comparativos. A tela exibe a versão do pacote em vigor junto à leitura e deixa explícito que **farol e leitura são interpretação, recalibrável**, enquanto **os valores nominais e percentuais do mês são imutáveis**. Teste dedicado: recalibrar uma faixa muda o farol de um mês antigo **e não altera nenhum valor do snapshot daquele mês**.

> ### ✅ `[CONFLITO-PO]` — **resolvido em 04/08/2026**, saída (a)
>
> O CA-8 original (*"a versão do pacote de conteúdo é gravada no snapshot do lançamento; meses antigos preservam a leitura com que foram gerados"*) foi escrito antes do `ADR-007` e pedia o oposto dele. Decisão delegada pelo PO ao Arquiteto em 04/08/2026 (*"tome estas decisões, precisamos encerrar isso"*) e tomada pela saída **(a) — alinhar ao `ADR-007`**. Três fundamentos, em ordem de peso:
>
> 1. **Congelar apagaria a história inteira do módulo.** Este é o argumento decisivo, e é específico do DRM. O módulo vai ao ar com **todas as faixas `nao_calibrada`** — o próprio CA-1b chama isso de *"o estado em que o módulo vai ao ar"*, e a `STORY-052` segue bloqueada esperando a EBC. Congelar a interpretação por mês significaria: todos os meses lançados antes da calibração ficariam **permanentemente sem farol**, para sempre, e só os meses posteriores ganhariam cor. O usuário que mais nos ajudou — o que lançou desde o primeiro dia — seria exatamente o mais punido, com uma série cinza atrás e colorida à frente. Derivar na leitura faz o contrário: no dia em que a EBC calibra, **a história inteira do usuário ganha interpretação de uma vez.**
> 2. **Farol é interpretação; o número é a medição.** O valor nominal e o percentual do mês nunca mudam — são o snapshot, e são o produto. O farol é uma leitura sobre um número já congelado. A assimetria com o Diagnóstico é deliberada e correta: lá, o texto da matriz **é** medição validada externamente (parecer da EBC, `RNF §9.3`), e por isso entra no snapshot; aqui não há parecer no núcleo, e o `PDR-003` explicitamente construiu o DRM para não depender dessa validação.
> 3. **Como estava, não era implementável.** O snapshot nasce no Checkpoint 1 (`STORY-047`/`048`); os faróis são do Checkpoint 3. Carimbar a versão do pacote "no snapshot do lançamento" exigiria escrever em coluna imutável de registros já existentes — proibido pelo `ADR-007` e pelo `IDR-010`, e exatamente o beco documentado no `IDR-013`.
>
> **O que o CA-8 original protegia continua protegido, por outro meio.** A preocupação legítima era não mudar o chão debaixo do usuário. A resposta não é congelar a interpretação — é **ancorar a confiança no que de fato não muda**: os valores. Daí a exigência, agora no critério, de a tela nomear a diferença entre o que é medição e o que é leitura.
>
> **Reversibilidade (princípio #7):** esta decisão é barata de desfazer. Se a EBC estabilizar as faixas e passarmos a querer congelá-las, acrescentar `conteudo_version` às versões criadas dali em diante é uma coluna e uma migration `expand` — sem tocar em nada já gravado. Gatilho de reabertura registrado no `ADR-007`, §Sinais de revisão 6.
>
> **Saídas descartadas:** (b) carimbar só a partir de agora — cria dois regimes de leitura convivendo na mesma série, a assimetria que o `IDR-013` documentou como dolorosa, e sem ganho enquanto as faixas não estiverem calibradas; (c) reabrir o `ADR-007` para persistir faixas — congelaria faixas **não validadas** na história do usuário, que é precisamente o que a ADR evitou.
- [ ] **CA-9 (Acessibilidade):** farol nunca comunicado só por cor — cor, ícone e texto.
- [ ] **CA-10 (Testes):** unitários cobrindo cada faixa em cada setor, cada combinação de farol na leitura, mês sem faturamento, mês com prejuízo, mês sem histórico e lacuna editorial; feature validando o render.
- [ ] **CA-11 (Cobertura):** ≥ 98% — é regra de negócio.

## Fora de escopo

- Recomendações de ação por indicador nos moldes da matriz do Diagnóstico. O DRM diz o que aconteceu; prescrever o que fazer é outra decisão de produto.
- Comparação com benchmark de mercado ou com outras empresas.
- Qualquer geração de texto por modelo de linguagem. Determinístico, ponto.

## Dependências

- **Bloqueada por:** STORY-051, STORY-052.
- **Bloqueia:** nada.

## Decisões já tomadas (não as reabra)

- Pacote de conteúdo da STORY-052 — estrutura das faixas e textos.
- Leitura determinística, nunca generativa.
- **Faixa não calibrada não produz farol.** Decisão do PO em 04/08: entregamos a estrutura paramétrica e a EBC popula depois, sem código.
- **`ADR-007`: faixas e faróis ficam fora do snapshot, derivados na leitura.** Ratificado pelo PO em 04/08. Consequência que **esta estória precisa tornar legível ao usuário**: recalibrar as faixas pode mudar a cor de um mês que ele já tinha visto, e os valores nominais e percentuais **não** mudam. Sem isso na tela, o usuário lê uma cor diferente e conclui que o número mudou. A assimetria com o Diagnóstico é deliberada: lá o texto da matriz é medição validada externamente e entra no snapshot; aqui o farol é leitura de um número já congelado.
- **CA-8 reescrito em 04/08/2026** pela saída (a) do `[CONFLITO-PO]` acima — decisão delegada pelo PO ao Arquiteto. O critério original (congelar a interpretação por mês) está descartado e não deve ser reintroduzido: congelá-la deixaria **permanentemente sem farol** todos os meses lançados antes de a EBC calibrar as faixas.

## Definição de Pronto

- [ ] CA-1 a CA-11 passam.
- [ ] Pipeline verde; deploy em homologação verificado.
- [ ] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas

- **Domínio `App\Domain\Drm\Leitura\LeituraDrm`** (puro; recebe `MesDaSerie` + `SerieDrm` + setor + posição, devolve `Leitura{farois, frases}`). O farol vem de `Faixas::farol()` (a regra "não calibrada não produz farol" continua só no motor). A prosa tem ordem fixa: abertura (lucro / zero a zero / prejuízo / sem faturamento), custo de manter o negócio e linha que mais pesou (prejuízo e mês parado), comparativos do resultado final com o mês anterior e a média + a causa que mais explica a diferença, e um item por farol aceso (vermelho, amarelo, verde).
- **Derivado na exibição (CA-8):** `Livewire\Drm\Demonstracao` chama o gerador a cada render; nada vai para o snapshot (teste confere que o JSON não contém farol/leitura/faixa e que recalibrar muda a cor sem mexer em `demonstracao` nem `payload_hash`). O card da leitura traz o `conteudo_version` e diz que farol e leitura são interpretação recalibrável, e que valores e percentuais não mudam.
- **Frases dos fatos em `config/drm/leitura_frases.php`** (arquivo novo, validado, com `version`). `leitura.php` (interpretação por linha e farol) continua vazio e sem provisório, como manda o PDR-009 §1. Separei os dois porque "o que o mês vendeu, o que mudou" não é interpretação de farol; sem isso CA-1b/4/5/6 ficariam sem prosa. Nenhuma frase julga número.
- **CA-7:** combinação sem texto em `leitura.php` vira "“Linha”: farol cor." e `ConteudoDrm::registrarLacunaDeLeitura()` grava a linha no inventário de lacunas (mesmo arquivo e padrão idempotente do provisório).
- **Farol na tela:** `<x-relatorio.farol>` (DDR-006) dentro da célula do rótulo (tabela e cards); ícone + texto + cor. Linha sem farol não renderiza nada.
- **Mês sem faturamento:** sem farol (percentual nulo), sem comparativos; só o custo de manter (L6+L8+L10) e a maior saída.
- Nada em `Faixas` mudou além do método `linhas()`.

### Descobertas

- As faixas são por código de linha e não por modo: no modo "dentro" `L6b` e `L7` têm outra definição (inclui pró-labore / EBITDA). Apliquei a faixa pelo código nos dois modos.
- O Dusk não consegue calibrar faixa em runtime (config do servidor), então o farol calibrado é provado no Pest e o Dusk cobre o grau zero.

### Dúvidas para o PO (não bloqueiam)

1. As frases de `leitura_frases.php` são microcopy de produto não validada pela EBC; confirmar que podem ir ao ar (não são interpretação de farol).
2. A mesma faixa vale para os dois modos do pró-labore (ver Descobertas)? Se a EBC calibrar L6b/L7, precisa dizer para qual modo.

### Bloqueios encontrados

Nenhum.

### IDRs criados

Nenhum.

### Cobertura final

Suíte All: 2563 passed (46.727 assertions), cobertura total 98,6%; gate `phpunit-domain.xml --min=98` verde (`Drm/Faixas` e `Drm/ConteudoDrm` 100%). Pint e PHPStan limpos. Dusk completo: 81 passed (654 assertions), incluindo `DrmFaroisLeituraBrowserTest` (grau zero em 360x800). Não verificado: farol calibrado em browser (config do servidor do Dusk não muda em runtime), axe e deploy em homologação.
