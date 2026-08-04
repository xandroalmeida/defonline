---
tipo: parecer-po
origem: "DEF on line - Feed-back JOSÉ MARQUES - ebc.2026ago03.docx"
anexos:
  - "Demonstração de Resultado Mensal - DRM.xlsx"
autor: PO (Alexandro / Claude)
data: 2026-08-04
status: concluido
decisoes_geradas: [PDR-003]
artefatos_gerados: [EPIC-010, STORY-044, STORY-045..STORY-056, anexo-K]
---

# Parecer do PO — Feedback de José Marques (03/08/2026)

## 1. Sumário executivo

O feedback contém **três pedidos distintos**, frequentemente lidos como um só. Separá-los é a primeira decisão de produto, porque cada um tem custo, risco e dono diferentes:

| # | Pedido | Natureza | Encaminhamento |
|---|---|---|---|
| A | Registrar o **valor nominal** junto a cada indicador do relatório | Melhoria pequena no relatório de Diagnóstico já entregue | `STORY-044`, hotfix pós-devolutiva do EPIC-002 |
| B | Nomear o produto **"Raio X do Negócio"** | Decisão de marca | `[DECIDIR-A]` — registrado no PDR-003, precisa da EBC |
| C | Criar o **módulo DRM** — Demonstração de Resultado Mensal | Módulo novo, com modelo de dados, motor e telas próprias | `EPIC-010`, épico âncora da WAVE-2026-02 |

O item C é o de longe mais importante, e concordo com o parecer favorável da EBC — mas por um motivo que vale explicitar, porque muda a prioridade do roadmap inteiro.

## 2. Por que o DRM é a sugestão mais valiosa que recebemos até aqui

O Key User está apontando um **descasamento entre o produto que construímos e a persona que dissemos servir**.

O DEF Online nasceu declarando o microempresário (MEI e Simples Nacional) como público-alvo, mas o Diagnóstico atual pede 23 campos — seis deles de **Balanço Patrimonial** (Q02–Q07: estoques, recebíveis, imobilizado, dívidas). Quem tem esses números na ponta da língua tem escrita contábil. Quem tem escrita contábil não é MEI. O Key User diz isso com todas as letras ao afirmar que só 5 das perguntas são "palatáveis e assimiláveis pelo público-alvo", e a análise da EBC confirma: *"o formato atual possui um universo de informações que podem ser mais necessárias a empresas de maior porte, com escrita contábil"*.

Três consequências que sustentam a priorização:

**2.1. O DRM só precisa de dados que o microempresário já tem.** Toda a estrutura da planilha se alimenta de fluxo (vendas, compras, despesas pagas no mês). Nada de patrimônio, nada de ciclo financeiro, nada de prazo médio. É o que cabe num extrato bancário e num caderno.

**2.2. O DRM é aritmética, não modelagem.** A demonstração inteira são somas e subtrações encadeadas — 11 linhas de totalização sobre 36 entradas. Não depende da matriz de recomendações por setor, que hoje é o gargalo real da expansão: o `EPIC-006` (cobertura de Comércio e Serviços) está bloqueado por validação externa de faixas (NRF §9.3). **O DRM entrega Comércio e Serviços sem esperar essa validação.** É o caminho mais curto até a Joana.

**2.3. O DRM é mensal — o Diagnóstico é episódico.** Nosso north star é "MPEs ativas com ≥1 diagnóstico concluído nos últimos 90 dias". Um produto que o usuário abre uma vez por semestre luta contra a própria métrica. Um lançamento mensal cria hábito, e hábito é o que sustenta assinatura anual. **O DRM não é um módulo a mais: é o motor de recorrência que o modelo de assinatura do EPIC-005 pressupõe e que hoje não existe.**

O corolário incômodo: se o DRM é o que prende o usuário, o Diagnóstico de 23 campos passa a ser o produto de upgrade, não a porta de entrada. Não estou propondo essa inversão agora — mas ela vai voltar à mesa depois do beta do DRM, e é melhor que volte como decisão consciente.

## 3. Achados na análise dos anexos

Analisei a planilha `Demonstração de Resultado Mensal - DRM.xlsx` célula a célula e cruzei com o questionário do `.docx`. Doze achados. Nenhum invalida a proposta; todos precisam de resposta antes de o programador encostar no teclado.

### F1 — "Em mil reais" é um convite ao erro de ordem de grandeza — **RESOLVIDO**

Todas as 36 perguntas do questionário pedem o valor "em mil reais". Para o microempresário que faturou R$ 45.000 no mês, isso significa digitar `45`. Um zero a mais ou a menos, e a demonstração inteira mente — sem nenhum sinal visível de erro, porque todas as linhas escalam juntas e os percentuais continuam corretos.

**Decisão:** capturar sempre em **reais**, com máscara BRL, reaproveitando o componente `campo-brl` já existente no quiz. A apresentação em R$ mil (ou milhão) é decisão de formatação da tela, não de entrada. Registrado no PDR-003.

### F2 — O pró-labore está fora do EBITDA, e isso conflita com o Diagnóstico — **`[DECIDIR-B]`**

Na planilha, o pró-labore (linha 48) é subtraído **depois** do "Resultado Antes do IR/CSLL" (linha 47). Consequência aritmética: o pró-labore não entra nas Despesas Administrativas, logo **não reduz o Resultado Operacional rotulado como "Ebitda"** (linha 43).

Isso é atípico contabilmente — a retirada dos sócios é despesa administrativa e compõe o EBITDA — e cria um problema concreto de produto: a mesma empresa veria **duas Margens EBITDA diferentes**, uma no relatório de Diagnóstico (que segue a definição padrão via `DreAdaptada`) e outra no DRM. Duas telas do mesmo produto discordando sobre o mesmo número destrói a confiança mais rápido do que qualquer bug.

Também noto que o próprio Key User é ambivalente: na versão de 5 perguntas do QUIZ, ele inclui *"retirada do dono/sócios"* dentro das despesas fixas; na versão DRM, separa. A separação parece deliberada e didática — o microempresário quer ver "quanto sobrou **depois** de eu me pagar" —, e essa intenção é boa.

**Recomendação do PO (a validar com a EBC):** manter a posição didática do Key User, **mas renomear a linha 43** de "Resultado Operacional (M.Operacional / Ebitda)" para **"Resultado Operacional (antes do pró-labore)"**, e calcular internamente também o EBITDA padrão (com pró-labore dentro das despesas), exibido apenas quando o módulo precisar conversar com o Diagnóstico. Assim preservamos a leitura do dono sem publicar dois EBITDAs conflitantes.

### F3 — A coluna de % está ausente em três linhas da planilha — **`[DECIDIR-C]`**

As fórmulas de percentual existem em `E7, E12, E13, E14, E21, E43, E44, E47, E48, E49`, mas **não** em `E2` (Receita Bruta), `E4` (Deduções) e `E6` (Receita Operacional Líquida). Como o denominador de todas é a Receita Bruta (`D$2`), a linha 2 seria trivialmente 100% — mas as linhas 4 e 6 são informativas e úteis (carga tributária sobre a venda é justamente um dos números que o microempresário mais quer ver).

**Recomendação:** exibir % em todas as linhas, com a Receita Bruta fixada em 100% como âncora de leitura. Precisa do aval da EBC por ser conteúdo editorial.

### F4 — Mês sem faturamento quebra todos os percentuais — **RESOLVIDO**

Toda a coluna % divide por `D$2`. Se a empresa não vendeu no mês — sazonalidade, férias coletivas, obra parada —, a planilha retorna `#DIV/0!` em dez linhas.

**Decisão:** reaproveitar o padrão já consolidado do motor de Diagnóstico: valor `null`, farol `nenhum`, motivo `indisponivel:<chave>` e mensagem semântica ("sem faturamento no mês — percentuais não se aplicam"). Os **valores nominais continuam sendo exibidos normalmente** — que é justamente o pedido A do Key User, e mostra que os dois pedidos se reforçam.

### F5 — Férias e 13º salário distorcem o mês em que são pagos — **`[DECIDIR-D]`**

A planilha lista "Férias" (C26) e "Décimo-terceiro salário" (C27) como despesas administrativas mensais. Em regime de caixa, esses valores aparecem concentrados em novembro/dezembro e no mês de férias de cada funcionário, produzindo uma queda abrupta de resultado que **não reflete piora do negócio**. O usuário vai abrir o DRM de dezembro, ver o lucro despencar e concluir a coisa errada.

**Recomendação:** manter os campos (fidelidade ao caixa, que é o que o microempresário entende), mas (i) tooltip explicando a opção de provisionar 1/12 por mês e (ii) na série histórica, sinalizar o mês como atípico quando uma rubrica exceder em 3× a sua própria média móvel. A detecção de atípico é barata e é exatamente o tipo de leitura que justifica assinatura.

### F6 — Um terço dos campos não se aplica a Serviços — **RESOLVIDO**

O CPV da planilha é `compras de mercadorias + matéria-prima + insumos + fretes na aquisição`. Para **Serviços**, "compras de mercadorias" e "matéria-prima" tipicamente não existem. Para **Comércio**, "matéria-prima" e "insumos" não existem. Apresentar 36 campos vazios e irrelevantes é o caminho mais curto para o abandono do formulário.

**Decisão:** formulário **adaptativo por setor**, herdando o setor da `EmpresaAnalisada` (sem repergunta). A estrutura de linhas do DRM permanece **idêntica nos três setores** — o que varia é quais campos de entrada aparecem e como o CPV é rotulado (Indústria: "Custo dos Produtos Vendidos"; Comércio: "Custo das Mercadorias Vendidas"; Serviços: "Custo dos Serviços Prestados"). Detalhado no Anexo K.

### F7 — Não há linha para receitas e despesas não operacionais — **`[DECIDIR-E]`**

A estrutura não acomoda venda de um equipamento usado, rendimento de aplicação, recebimento de indenização, nem multa contratual paga. Esses eventos existem na vida real da MPE e, quando ocorrem, o usuário vai forçá-los em alguma linha errada — contaminando a série histórica que é o coração do módulo.

**Recomendação:** acrescentar duas linhas opcionais — "Outras receitas" e "Outras despesas" — posicionadas **após** o Resultado Operacional, de modo a não contaminar as margens operacionais. Ficam ocultas quando zeradas.

### F8 — Falta o campo de competência — **RESOLVIDO**

A planilha carrega o mês apenas no título (`A1 = "Demonstração de Resultado Mensal - DRM: AGO/2026"`), como texto livre. Um módulo mensal precisa da competência como dado de primeira classe: chave de unicidade por empresa, eixo da série histórica, base do comparativo.

**Decisão:** campo obrigatório `competencia` (mês/ano), único por Empresa Analisada, sem lançamento em competência futura. Detalhado no Anexo K.

### F9 — CNPJ/CPF e setor não devem ser reperguntados — **RESOLVIDO**

As duas primeiras perguntas do questionário do Key User ("CNPJ ou/CPF" e "INDÚSTRIA: 1, COMÉRCIO: 2, SERVIÇOS: 3") já são atributos da `EmpresaAnalisada`, capturados no cadastro e enriquecidos via Receita Federal. Repetir a pergunta a cada mês é atrito puro e abre espaço para divergência de dados.

**Decisão:** o lançamento mensal é sempre feito **no contexto de uma Empresa Analisada já cadastrada**. Os dois campos somem do formulário.

### F10 — O rótulo "Resultado Antes do IR/CSLL" confunde justamente o público-alvo — **`[DECIDIR-C]`**

A empresa do Simples Nacional não recolhe IR e CSLL separadamente: ambos já estão embutidos na guia única, que o usuário lançou lá em cima, em "Impostos sobre vendas". O MEI, menos ainda. Manter um rótulo que anuncia um imposto que o usuário nunca vai pagar separadamente sugere que falta uma etapa — e a estrutura, corretamente, não tem linha de IR/CSLL depois dela.

**Recomendação:** renomear para **"Resultado antes do pró-labore"** — que é literalmente o que a linha 47 é, dado que a única subtração seguinte é o pró-labore. Agrupar com F3 na mesma consulta editorial à EBC.

### F11 — 36 campos por mês é atrito alto demais para um produto de recorrência — **RESOLVIDO (com desenho)**

Este é o maior risco de produto do módulo, e é de retenção, não de construção.

Contei os campos monetários da estrutura proposta: 1 receita + 1 dedução + 4 de CPV + 6 de despesas com vendas + **21 de despesas administrativas** + 2 financeiras + 1 pró-labore = **36 lançamentos por mês**. O Diagnóstico completo, que já consideramos denso, tem 20. Estamos propondo que o microempresário faça **quase o dobro do esforço, doze vezes por ano**.

O detalhamento em si é bom — é ele que permite dizer "sua conta de energia subiu 40%". O problema é exigi-lo integralmente todo mês, desde o primeiro.

**Decisão de desenho (três mecanismos, todos no MVP):**

1. **Entrada em dois níveis.** Cada grupo (CPV, Despesas com Vendas, Despesas Administrativas) aceita ou um **valor único do grupo** ou o **detalhamento por item**. Quem detalha ganha as leituras finas; quem não detalha ainda tem uma demonstração correta. A escolha é por grupo e reversível a qualquer momento.
2. **Repetição do mês anterior.** Ao abrir um novo lançamento, os campos vêm pré-preenchidos com os valores da competência anterior, marcados visualmente como "repetido" até que o usuário confirme. Aluguel, contador, internet e seguros praticamente não mudam — é aí que mora a maior parte dos 21 campos.
3. **Rascunho retomável**, no mesmo padrão do `quiz_rascunhos` já existente. Ninguém preenche 36 campos numa sentada.

Com os três, o custo marginal do segundo mês em diante cai para os poucos campos que efetivamente mudaram. Isso é a diferença entre um produto que o usuário usa em janeiro e abandona em março, e um que ele mantém.

### F12 — O DRM precisa ser retificável, e isso colide com o IDR-010 — **ESCALADO AO ARQUITETO**

O `IDR-010` estabeleceu que o Diagnóstico é **snapshot imutável**: persiste a saída, as versões que a produziram, e nunca recalcula o passado. É uma boa regra e não estou propondo revogá-la.

Mas o DRM é outro animal. O usuário **vai** errar um lançamento e **vai** precisar corrigi-lo no mês seguinte, quando conciliar o extrato. Um DRM imutável seria inutilizável; um DRM livremente editável perde a trilha de auditoria e permite que a série histórica mude debaixo dos pés do usuário sem explicação.

**Não é decisão minha.** Escalado como spike de arquitetura (`STORY-045`), com o requisito de produto declarado: o usuário precisa poder **retificar uma competência já fechada**, o sistema precisa **preservar a versão anterior**, e a tela precisa **sinalizar que aquele mês foi retificado**. Como isso se materializa em tabelas e versionamento é decisão do Arquiteto.

## 4. Decisões que tomei

Registradas formalmente no `PDR-003`:

1. **O DRM é um módulo novo, não uma variação do Diagnóstico.** Ambos ancorados em `EmpresaAnalisada`, sem acoplamento entre motores. Preserva o `IDR-010` e evita que a versão do DRM contamine o `payload_hash` do Diagnóstico.
2. **Nasce cobrindo Indústria, Comércio e Serviços.** A estrutura de linhas é única; o que varia é o conjunto de campos de entrada e os rótulos de custo.
3. **Sem paywall neste épico.** Entregamos o módulo funcional e instrumentado com eventos de produto. A assinatura sugerida pelo Key User (R$ 600/ano ou 12× R$ 50) vira insumo do `EPIC-005`, que permanece bloqueado pelo Termo de Adesão revisado por advogado. Medir adesão antes de construir gateway.
4. **Entrada sempre em reais** (F1), competência como campo de primeira classe (F8), sem repergunta de CNPJ e setor (F9), formulário adaptativo por setor (F6), percentuais indisponíveis em vez de erro quando não há faturamento (F4), e os três mecanismos anti-atrito de F11.

## 5. Decisões que não são minhas

| ID | Questão | Dono | Bloqueia |
|---|---|---|---|
| `[DECIDIR-A]` | Adotar "Raio X do Negócio" como nome — do módulo DRM, do relatório de Diagnóstico ou da plataforma inteira? | EBC / negócio | Nada. Nomenclatura pode entrar até o fim do épico |
| `[DECIDIR-B]` | Posição do pró-labore e rótulo da linha de Resultado Operacional (F2) | EBC | `STORY-048` (motor) — **crítico** |
| `[DECIDIR-C]` | Percentual nas linhas 2/4/6 (F3) e rótulo "antes do IR/CSLL" (F10) | EBC | `STORY-052` (pacote de conteúdo) |
| `[DECIDIR-D]` | Tratamento de férias e 13º: caixa puro, provisão sugerida ou detecção de atípico (F5) | EBC | `STORY-051` (série histórica) |
| `[DECIDIR-E]` | Incluir "Outras receitas" e "Outras despesas" (F7) | EBC | `STORY-048` (motor) |
| Retificação | Como versionar competências retificadas sem violar o IDR-010 (F12) | Arquiteto | Todo o épico — é a `STORY-045` |
| Preço | R$ 600/ano é o preço certo? | Negócio | `EPIC-005`, fora deste épico |

`[DECIDIR-B]` e `[DECIDIR-E]` são os únicos que travam a construção do motor. Recomendo consulta única à EBC cobrindo B, C, D e E antes da abertura do sprint.

## 6. Impacto no roadmap

O `EPIC-010` entra na **WAVE-2026-02 como épico âncora**, antes do `EPIC-006` (cobertura de Comércio e Serviços no Diagnóstico). Justificativa: o DRM entrega os três setores sem depender da validação externa de faixas que trava o EPIC-006, e entrega recorrência — que o `EPIC-005` (cobrança) pressupõe para fazer sentido comercial.

Sequência revisada da onda 2: **EPIC-010 (DRM) → EPIC-005 (cobrança) → EPIC-007 (PDF) → EPIC-006 (setores no Diagnóstico) → EPIC-008 → EPIC-009**.

O `STORY-044` (valor nominal, item A) não espera a onda 2 — é hotfix pequeno do EPIC-002, entra no próximo sprint junto com as demais estórias pós-devolutiva.

## 7. Artefatos produzidos

- `project-state/decisions/pdr/PDR-003-modulo-drm-raio-x-do-negocio.md`
- `project-state/epics/EPIC-010-drm-demonstracao-resultado-mensal/` — épico, 12 estórias, estrutura canônica, checklist de validação, handoff
- `project-state/epics/EPIC-002-diagnostico-industria/stories/STORY-044-valor-nominal-indicadores.md`
- `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md`
- `roadmap/next-wave.md` e `index.json` atualizados

## Histórico

- 2026-08-04 — parecer emitido pelo PO a partir do feedback de 03/08/2026.
