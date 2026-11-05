---
pdr_id: PDR-003
slug: modulo-drm-raio-x-do-negocio
title: Criação do módulo DRM — Demonstração de Resultado Mensal para MEI e Simples Nacional
status: accepted
decided_at: 2026-08-04
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-010, EPIC-002, EPIC-005, EPIC-006]
related_adrs: [ADR-003]
related_idrs: [IDR-010]
origem: "revisao/analise-po-feedback-jose-marques-2026ago03.md"
---

# PDR-003 — Módulo DRM (Demonstração de Resultado Mensal)

> **Alterada pela PDR-020 (2026-11-05):** a medição de adesão continua, mas deixa de condicionar a construção da cobrança (EPIC-005).

## Contexto

Em 03/08/2026 o Key User José Marques enviou feedback sobre o MVP do DEF Online com três pedidos: (A) registrar o valor nominal junto a cada indicador do relatório, (B) nomear o produto "Raio X do Negócio" e (C) criar um módulo mensal que responda à pergunta *"meu negócio dá lucro?"*, acompanhado de uma planilha modelo com a estrutura completa da demonstração. A EBC analisou e emitiu parecer favorável à criação do módulo.

O pedido C expõe um descasamento real entre o produto e a persona declarada. O Diagnóstico entregue no EPIC-002 pede 23 campos, seis deles de Balanço Patrimonial (estoques, recebíveis, imobilizado, dívidas). Quem dispõe desses números tem escrita contábil — e quem tem escrita contábil não é MEI nem, na maioria dos casos, optante do Simples. O Key User afirma que apenas 5 das perguntas são "palatáveis e assimiláveis" pelo público-alvo; a EBC concorda que o formato atual serve melhor a empresas de maior porte.

Há ainda uma consequência de recorrência. Nosso north star é "MPEs ativas com ≥1 diagnóstico concluído nos últimos 90 dias". O Diagnóstico é episódico por natureza — o usuário o refaz quando enfrenta uma decisão de investimento, captação ou preço, o que ocorre uma ou duas vezes por ano. Um módulo de lançamento mensal muda a frequência de uso de semestral para mensal, e é essa frequência que sustenta o modelo de assinatura previsto no EPIC-005.

A decisão a tomar não é *se* construímos o DRM — o mérito é consensual entre Key User, EBC e PO. É **em que forma, com que escopo e em que ordem**.

## Opções consideradas

### Opção 1 — DRM como módulo independente, três setores, sem cobrança

- **Descrição:** módulo novo, com modelo de dados, motor de cálculo e telas próprias, ancorado na mesma `Empresa Analisada` do Diagnóstico, mas sem acoplamento entre os motores. Nasce cobrindo Indústria, Comércio e Serviços. Entregue sem paywall, instrumentado com eventos de produto para medir adesão.
- **Prós:** entrega os três setores sem depender da validação externa de faixas que bloqueia o EPIC-006; preserva o IDR-010 (imutabilidade do snapshot de Diagnóstico) porque não mexe naquele motor; permite medir disposição de uso antes de investir em gateway de pagamento; o esforço de cálculo é baixo — a demonstração é aritmética encadeada, não modelagem estatística.
- **Contras:** cria um segundo motor e um segundo conjunto de telas para manter; abre a possibilidade de a mesma empresa ver números divergentes entre os dois módulos se as definições não forem alinhadas (ver F2 do parecer); adia receita.

### Opção 2 — DRM junto com a cobrança sugerida pelo Key User

- **Descrição:** especificar e entregar o módulo já com a assinatura anual de R$ 600,00 (ou 12× R$ 50,00), Pix e cartão recorrente.
- **Prós:** valida a hipótese comercial completa de uma vez; responde diretamente à sugestão do Key User, que veio com preço embutido.
- **Contras:** escopo muito maior; exige ADR de gateway de pagamento, ainda inexistente; o Termo de Adesão revisado por advogado é gatilho **bloqueante** já registrado nos riscos da WAVE-2026-02 — sem ele não se pode cobrar; e precificar antes de observar uso é decidir no escuro. Não sabemos se o microempresário completa doze lançamentos por ano.

### Opção 3 — DRM como simplificação do Diagnóstico existente

- **Descrição:** em vez de módulo novo, criar um "modo simplificado" do quiz atual, com menos campos, reaproveitando motor, snapshot e relatório.
- **Prós:** menos código novo; uma só tela de relatório para manter.
- **Contras:** os dois produtos respondem a perguntas diferentes com dados de naturezas diferentes — o Diagnóstico olha estrutura patrimonial e ciclo, o DRM olha fluxo de um mês. Forçá-los no mesmo motor obrigaria a tratar a ausência de Balanço como "indisponibilidade" em massa, produzindo relatórios majoritariamente vazios. Pior: contaminaria o `payload_hash` e o versionamento do motor de Diagnóstico, violando o espírito do IDR-010. Alto risco de degradar os dois produtos ao mesmo tempo.

### Opção 4 — Status quo (não construir o DRM)

- **Consequência:** mantemos um produto que a EBC reconhece servir melhor a empresas com escrita contábil, enquanto declaramos servir MEI e Simples. O north star continua dependente de um comportamento episódico. A sugestão mais bem fundamentada que recebemos de um Key User fica sem resposta.

## Decisão

> **Optamos pela Opção 1.**

Criamos o **módulo DRM — Demonstração de Resultado Mensal** como módulo independente do Diagnóstico, ancorado na mesma Empresa Analisada, cobrindo Indústria, Comércio e Serviços desde o MVP, entregue sem cobrança e instrumentado para medir adesão. O módulo é formalizado no `EPIC-010`, épico âncora da WAVE-2026-02.

## Decisões de escopo que acompanham

Derivadas da análise dos anexos (detalhamento e numeração dos achados em `revisao/analise-po-feedback-jose-marques-2026ago03.md`):

1. **Entrada em reais, não em milhares** (F1). O questionário do Key User pede todos os valores "em mil reais"; para quem faturou R$ 45.000 isso significa digitar `45`, e um zero a mais escala a demonstração inteira sem produzir sinal visível de erro. Capturamos em reais com máscara BRL. Apresentação em R$ mil é decisão de formatação de tela.
2. **Competência é campo de primeira classe** (F8), obrigatória, única por Empresa Analisada, sem lançamento futuro.
3. **CNPJ/CPF e setor não são reperguntados** (F9) — herdados da Empresa Analisada.
4. **Formulário adaptativo por setor** (F6). A estrutura de linhas do DRM é idêntica nos três setores; variam os campos de entrada exibidos e os rótulos de custo (CPV / CMV / Custo dos Serviços Prestados).
5. **Sem faturamento no mês não é erro** (F4). Percentuais ficam indisponíveis com motivo semântico, reaproveitando o padrão do motor de Diagnóstico; valores nominais continuam sendo exibidos.
6. **Três mecanismos anti-atrito obrigatórios no MVP** (F11), porque 36 lançamentos mensais são quase o dobro do Diagnóstico completo e o principal risco de retenção do módulo: entrada em dois níveis (valor do grupo ou detalhamento por item, escolha reversível por grupo), pré-preenchimento com a competência anterior sinalizado visualmente, e rascunho retomável.
7. **Retificação de competência é requisito de produto** (F12). O usuário precisa corrigir um mês já lançado, o sistema precisa preservar a versão anterior e a tela precisa sinalizar a retificação. **Como** isso se materializa é decisão do Arquiteto — escalado como spike na `STORY-045`, por colidir com o IDR-010.
8. **Sem paywall neste épico.** A assinatura sugerida (R$ 600/ano ou 12× R$ 50) vira insumo do EPIC-005.

## Justificativa

Três razões, em ordem de peso.

**Alcance sem bloqueio.** O EPIC-006 — estender o Diagnóstico a Comércio e Serviços — está travado por validação externa das faixas da matriz de recomendações (NRF §9.3), um gargalo que não controlamos. O DRM não depende dessa validação, porque a demonstração é aritmética: 11 linhas de totalização sobre até 36 entradas, sem faixas nem semáforo obrigatório no núcleo. É o caminho mais curto até a Joana e até o público que dissemos servir desde o início.

**Recorrência.** O north star mede atividade em janela de 90 dias. Um produto de uso semestral trabalha contra a própria métrica. O lançamento mensal cria o hábito que o modelo de assinatura pressupõe — o EPIC-005 não faz sentido comercial sobre um produto que o usuário abre duas vezes por ano.

**Risco contido.** Adiar a cobrança custa receita que ainda não temos e compra informação que não podemos obter de outro jeito: quantos usuários completam o segundo, o terceiro e o sexto lançamento. Se a taxa de segundo lançamento for baixa, o problema é de produto, não de preço — e descobrir isso depois de construir um gateway seria caro. A Opção 2 inverte essa ordem.

A Opção 3 foi descartada por razão de integridade: acoplar o DRM ao motor de Diagnóstico contaminaria o versionamento e o `payload_hash` de um módulo que a EBC já validou externamente, colocando em risco algo que funciona para acelerar algo que ainda não existe.

## Consequências

### Positivas

- O produto passa a servir de fato MEI e optantes do Simples, nos três setores, sem esperar a validação de faixas.
- Cria a base de recorrência que o EPIC-005 pressupõe, e dados reais de uso para calibrar o preço.
- A série histórica mensal se torna, com o tempo, o ativo mais defensável do produto — dado que o concorrente não tem e que o usuário não quer recomeçar em outro lugar.
- Doze meses de DRM são, potencialmente, insumo de pré-preenchimento do Diagnóstico anual. **Não implementamos esse vínculo agora** (decisão explícita de manter os módulos independentes), mas o modelo de dados não deve impedi-lo.

### Negativas / trade-offs aceitos

- Segundo motor e segundo conjunto de telas a manter. Aceito: o custo de cálculo é baixo e a alternativa acoplada é pior.
- Risco de divergência conceitual entre módulos — especialmente na Margem EBITDA, dada a posição atípica do pró-labore na estrutura do Key User (F2). Mitigado por `[DECIDIR-B]`, que precisa ser resolvido antes da construção do motor.
- Receita adiada para o EPIC-005.
- O posicionamento relativo dos dois produtos fica em aberto. Se o DRM se mostrar a porta de entrada natural do microempresário, o Diagnóstico de 23 campos vira produto de upgrade — inversão de posicionamento que exigirá novo PDR após o beta.

### Para o time técnico

- **ADR/IDR demandados:** decisão de versionamento e retificação de competência (spike `STORY-045`, provável IDR); decisão sobre motor de exportação em planilha e PDF (spike `STORY-053`, compartilhado com o EPIC-007).
- **Restrições herdadas que continuam valendo:** ADR-003 (multi-tenancy por FK + Global Scope, soft delete, CHECK constraints); IDR-009 (404 silente cross-tenant); IDR-008 (tokens de design em `tokens.css`, sem hex literal); IDR-010 continua vigente **para o Diagnóstico** e não deve ser revogado para acomodar o DRM.
- **Impacto em épicos:** EPIC-010 criado; EPIC-005 ganha insumo de precificação e passa a depender do DRM para fazer sentido comercial; EPIC-006 é despriorizado dentro da onda 2; EPIC-007 (PDF) tem sua decisão de engine antecipada pela `STORY-053`.

## Decisões abertas que este PDR não fecha

| ID | Questão | Dono | Prazo |
|---|---|---|---|
| ~~`[DECIDIR-A]`~~ | ~~"Raio X do Negócio" nomeia o módulo DRM, o relatório de Diagnóstico ou a plataforma?~~ **RESOLVIDO em 2026-08-06 (`PDR-009` §3): nomeia o módulo DRM.** O Diagnóstico segue "Diagnóstico" e a plataforma segue "DEFOnline". É nome de **interface**; "DRM" continua sendo o nome técnico em tabelas, config, rotas e documentação. | PO | ✅ 2026-08-06 |
| `[DECIDIR-B]` | Pró-labore dentro ou fora do EBITDA, e rótulo da linha de Resultado Operacional | EBC | **Antes da `STORY-048`** |
| `[DECIDIR-C]` | Percentual nas linhas de Receita Bruta, Deduções e Receita Líquida; rótulo "antes do IR/CSLL" | EBC | Antes da `STORY-052` |
| `[DECIDIR-D]` | Tratamento de férias e 13º: caixa puro, provisão sugerida ou detecção de mês atípico | EBC | Antes da `STORY-051` |
| `[DECIDIR-E]` | Incluir linhas "Outras receitas" e "Outras despesas" | EBC | **Antes da `STORY-048`** |

`[DECIDIR-B]` e `[DECIDIR-E]` travam a construção do motor. Recomendo consulta única à EBC cobrindo B, C, D e E antes da abertura do sprint que iniciar o épico.

## Sinais de revisão

Reavaliar esta decisão se, no beta do módulo:

- **Menos de 40% dos usuários que concluem o primeiro lançamento concluírem o segundo.** Indicaria que o atrito de 36 campos não foi resolvido pelos mecanismos de F11, e que o problema é de desenho de entrada, não de valor percebido — possivelmente exigindo integração com extrato bancário ou emissor de nota, o que é outra ordem de esforço.
- **Mais de 30% dos lançamentos precisarem de retificação.** Indicaria que a captura manual não é confiável o bastante para sustentar série histórica.
- **A adesão se concentrar em empresas com escrita contábil** em vez de MEI e Simples. Indicaria que a hipótese de persona deste PDR está errada e que o problema real é outro.
- **A EBC decidir, em `[DECIDIR-B]`, manter o pró-labore fora do EBITDA sem renomear a linha.** Nesse caso, a divergência entre os dois módulos vira dívida conceitual permanente e precisa de PDR próprio para decidir qual dos dois números o produto assume publicamente.
