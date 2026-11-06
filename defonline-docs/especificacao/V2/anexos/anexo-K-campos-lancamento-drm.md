# Anexo K — Campos do Lançamento Mensal (DRM)

**Documento pai:** [`../especificacao-funcional.md`](../especificacao-funcional.md) — módulo DRM (a incorporar na próxima revisão da spec)
**Versão:** 1.1
**Data:** 04/08/2026
**Fonte original:** feedback do Key User José Marques (03/08/2026) e planilha `Demonstração de Resultado Mensal - DRM.xlsx`
**Decisões de produto:** [`PDR-003`](../../project-state/decisions/pdr/PDR-003-modulo-drm-raio-x-do-negocio.md) e [`PDR-004`](../../project-state/decisions/pdr/PDR-004-decisoes-estruturais-drm.md)
**Mantido por:** EB Parcerias Ltda (Produto)

---

## K.1 Finalidade

Este anexo é a referência canônica dos campos coletados no **lançamento mensal** do módulo DRM — Demonstração de Resultado Mensal. Equivale, para o DRM, ao que o Anexo A é para o Quiz de Diagnóstico.

É a fonte para: implementação do formulário, regras de validação e coerência, modelo de dados do lançamento, textos de ajuda e treinamento de suporte.

**O que o DRM não é.** O DRM não substitui nem alimenta o Diagnóstico. São módulos independentes ancorados na mesma Empresa Analisada (PDR-003). Enquanto o Diagnóstico olha estrutura patrimonial e ciclo financeiro em janela anual, o DRM olha **fluxo de um único mês**, em regime de caixa.

## K.2 Princípios de coleta

Cinco princípios governam todo o formulário e devem ser respeitados por qualquer campo futuro:

1. **Valores em reais, sempre.** Contrariando o enunciado original do Key User ("em mil reais"), a captura é em reais com máscara BRL — `R$ 1.234,56`. A apresentação em R$ mil é escolha de formatação da tela de resultado, nunca da entrada. Motivo em `revisao/analise-po-feedback-jose-marques-2026ago03.md` (F1).
2. **Nada que a plataforma já sabe é reperguntado.** CNPJ/CPF, razão social e setor vêm da Empresa Analisada.
3. **Regime de caixa.** Todo campo pergunta o que **entrou ou saiu no mês**, não o que foi competência do mês. É o que o microempresário consegue responder olhando o extrato.
4. **Só o que se aplica ao setor aparece.** Ver K.4.
5. **Detalhar é opcional.** Cada grupo de custo/despesa aceita valor único do grupo **ou** detalhamento por item. Ver K.5.

## K.3 Lista de campos

Prefixo `D` (de DRM), para não colidir com o `Q` do Anexo A.

### K.3.1 Identificação do lançamento

| Ref | Campo | Tipo | Formato | Obrigatório | Visível quando | Observação |
|---|---|---|---|---|---|---|
| D00 | Competência | Mês/Ano | `MM/AAAA` | Sim | sempre | Único por Empresa Analisada. Não aceita competência futura. Default sugerido: mês anterior ao corrente. |

> Setor e documento (CNPJ/CPF) **não são campos** — herdados da Empresa Analisada (PDR-003, decisão 3).

### K.3.2 Receita e deduções

| Ref | Campo | Tipo | Formato | Obrigatório | Visível quando | Observação |
|---|---|---|---|---|---|---|
| D01 | Quanto vendeu no mês | Numérico | R$ ≥ 0 | Sim | sempre | Receita bruta: mercadorias, produtos e serviços. Base (denominador) de toda a coluna de percentuais. |
| D02 | Impostos sobre vendas pagos no mês | Numérico | R$ ≥ 0 | Sim | sempre | Simples Nacional, ICMS, ISS, PIS/COFINS. Para o optante do Simples, é a guia única (DAS). |

### K.3.3 Custo do que foi vendido — grupo `custo`

Rótulo do grupo varia por setor (K.4). Aceita modo agregado ou detalhado (K.5).

| Ref | Campo | Tipo | Formato | Obrigatório | Visível quando | Observação |
|---|---|---|---|---|---|---|
| D03 | Total do custo do que foi vendido | Numérico | R$ ≥ 0 | Se grupo em modo agregado | modo agregado | Substitui D04–D07. |
| D04 | Compras de mercadorias | Numérico | R$ ≥ 0 | Não | modo detalhado **e** setor ∈ {Indústria, Comércio} | Mercadorias para revenda. |
| D05 | Compras de matéria-prima | Numérico | R$ ≥ 0 | Não | modo detalhado **e** setor = Indústria | — |
| D06 | Compras de insumos | Numérico | R$ ≥ 0 | Não | modo detalhado | Aplica-se aos três setores. |
| D07 | Fretes na aquisição | Numérico | R$ ≥ 0 | Não | modo detalhado | Frete pago **para receber** mercadoria, matéria-prima ou insumo. Não confundir com D09. |

### K.3.4 Despesas com vendas — grupo `vendas`

| Ref | Campo | Tipo | Formato | Obrigatório | Visível quando | Observação |
|---|---|---|---|---|---|---|
| D08 | Total das despesas com vendas | Numérico | R$ ≥ 0 | Se grupo em modo agregado | modo agregado | Substitui D09–D14. |
| D09 | Fretes na entrega ao cliente | Numérico | R$ ≥ 0 | Não | modo detalhado | Frete pago **para entregar** ao comprador. |
| D10 | Taxas de administração de cartões | Numérico | R$ ≥ 0 | Não | modo detalhado | Apenas a taxa da maquininha/adquirente. Juros de antecipação vão em D38. |
| D11 | Taxas de aplicativos de entrega | Numérico | R$ ≥ 0 | Não | modo detalhado | iFood, Rappi e similares. |
| D12 | Franquia (royalties) | Numérico | R$ ≥ 0 | Não | modo detalhado | Royalties e taxa de propaganda de rede franqueada. |
| D13 | Comissões | Numérico | R$ ≥ 0 | Não | modo detalhado | Comissões de venda pagas no mês. |
| D14 | Outras despesas que mudam conforme as vendas | Numérico | R$ ≥ 0 | Não | modo detalhado | — |

### K.3.5 Despesas administrativas e gerais — grupo `administrativas`

O grupo com maior número de itens (21) e, por isso, o principal alvo dos mecanismos anti-atrito de K.5.

| Ref | Campo | Tipo | Formato | Obrigatório | Visível quando | Observação |
|---|---|---|---|---|---|---|
| D15 | Total das despesas administrativas e gerais | Numérico | R$ ≥ 0 | Se grupo em modo agregado | modo agregado | Substitui D16–D36. |
| D16 | Salários | Numérico | R$ ≥ 0 | Não | modo detalhado | Folha líquida paga no mês. Não inclui a retirada do dono (ver D40). |
| D17 | Encargos trabalhistas (FGTS, INSS) | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D18 | Vale transporte | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D19 | Vale refeição / cesta alimentação | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D20 | Férias | Numérico | R$ ≥ 0 | Não | modo detalhado | Valor efetivamente pago no mês. Ver K.6 — rubrica sazonal. |
| D21 | Décimo-terceiro salário | Numérico | R$ ≥ 0 | Não | modo detalhado | Idem D20. |
| D22 | Uniforme, fardamento | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D23 | Aluguel | Numérico | R$ ≥ 0 | Não | modo detalhado | Inclui condomínio e IPTU quando cobrados junto. |
| D24 | Energia | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D25 | Água e esgoto | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D26 | Telefone | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D27 | Internet | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D28 | Contador | Numérico | R$ ≥ 0 | Não | modo detalhado | Honorários do escritório contábil. |
| D29 | Sistemas e aplicativos (tecnologia) | Numérico | R$ ≥ 0 | Não | modo detalhado | Assinaturas de software, ERP, PDV. |
| D30 | Marketing e publicidade | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D31 | Manutenção e reparos | Numérico | R$ ≥ 0 | Não | modo detalhado | Máquinas, equipamentos, móveis. |
| D32 | Material de limpeza | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D33 | Material de escritório | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D34 | Seguros | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D35 | Sindicatos e associações | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D36 | Outros custos que se repetem todo mês | Numérico | R$ ≥ 0 | Não | modo detalhado | — |

### K.3.6 Despesas financeiras e retirada

| Ref | Campo | Tipo | Formato | Obrigatório | Visível quando | Observação |
|---|---|---|---|---|---|---|
| D37 | Total das despesas financeiras | Numérico | R$ ≥ 0 | Se grupo em modo agregado | modo agregado | Substitui D38–D39. |
| D38 | Juros pagos das dívidas | Numérico | R$ ≥ 0 | Não | modo detalhado | Inclui juros de antecipação de recebíveis e desconto de boletos. |
| D39 | Tarifas bancárias | Numérico | R$ ≥ 0 | Não | modo detalhado | — |
| D40 | Pró-labore (retirada dos sócios/dono) | Numérico | R$ ≥ 0 | Sim | sempre | **Sempre campo próprio**, nunca dentro de D15/D16. Posição na demonstração parametrizável por Empresa Analisada (`[DECIDIR-B]`, resolvido no `PDR-004`; ver K.5-A), default `fora`. |

### K.3.7 Receitas e despesas não operacionais

Aprovadas pelo `PDR-004`, decisão E (resolve F7 do parecer). Ficam ocultas na demonstração quando ambas são zero.

| Ref | Campo | Tipo | Formato | Obrigatório | Visível quando | Observação |
|---|---|---|---|---|---|---|
| D41 | Outras receitas do mês | Numérico | R$ ≥ 0 | Não | sempre | Venda de equipamento usado, rendimento de aplicação, indenização recebida. Não é venda de mercadoria ou serviço — isso é D01. |
| D42 | Outras despesas do mês | Numérico | R$ ≥ 0 | Não | sempre | Multas, perdas, despesas não operacionais. |

**Totais.** **38 campos monetários** na configuração completa, sem contar os agregadores. Com os quatro grupos em modo agregado, caem para **9**: **7 obrigatórios** (D01, D02, D03, D08, D15, D37 e D40) e **2 opcionais** (D41 e D42), que ficam recolhidos na tela e tipicamente zerados. Em "7 campos" (checklist de validação) contam-se só os obrigatórios.

## K.4 Adaptação por setor

A **estrutura de linhas da demonstração é idêntica nos três setores**. O que varia é quais campos aparecem e como o grupo de custo é rotulado.

| Setor | Rótulo do grupo `custo` | Campos suprimidos |
|---|---|---|
| Indústria | Custo dos Produtos Vendidos (CPV) | nenhum |
| Comércio | Custo das Mercadorias Vendidas (CMV) | D05 (matéria-prima) |
| Serviços | Custo dos Serviços Prestados (CSP) | D04 (compras de mercadorias), D05 (matéria-prima) |

Campo suprimido não é coletado e é gravado como nulo — mesmo tratamento que o Anexo A dá a Q11 no setor Serviços.

## K.5 Modo agregado e modo detalhado

Cada um dos quatro grupos — `custo`, `vendas`, `administrativas`, `financeiras` — tem um modo próprio, escolhido pelo usuário e **alternável a qualquer momento, inclusive depois do lançamento salvo**.

- **Modo agregado:** exibe apenas o campo totalizador (D03, D08, D15, D37). O total do grupo é o valor digitado.
- **Modo detalhado:** exibe os itens do grupo. O total é a soma dos itens; o campo totalizador fica oculto e é derivado.

**Regras:**

- Ao alternar de detalhado para agregado, o totalizador é pré-preenchido com a soma dos itens; os itens são preservados no rascunho e restaurados se o usuário voltar atrás na mesma sessão.
- Ao alternar de agregado para detalhado, os itens vêm vazios e o totalizador digitado é descartado — com confirmação explícita, por ser destrutivo.
- O modo escolhido é **por grupo e por Empresa Analisada**, persistido como preferência e reaplicado no lançamento seguinte.
- O modo vigente de cada grupo é registrado no lançamento, porque determina a granularidade disponível na série histórica.

**Efeito na leitura.** Grupo em modo agregado produz demonstração correta, mas suprime as leituras finas daquele grupo ("sua conta de energia subiu 40%"). A tela deve deixar isso explícito no momento da escolha, sem culpabilizar quem escolhe agregado.

## K.5-A Parâmetros da Empresa Analisada

Além do modo de cada grupo (K.5), a Empresa Analisada tem parâmetros que afetam a **leitura** da demonstração, nunca o lançamento. Decididos no `PDR-004`.

| Parâmetro | Valores | Default | Efeito |
|---|---|---|---|
| Posição do pró-labore | `fora` \| `dentro` | `fora` | Alterna entre os dois modos de apresentação de `design/estrutura-drm.md` §1. Muda rótulos, número de linhas e ordem. **Não muda o Lucro Líquido do Mês.** |
| Tratamento de rubricas sazonais | `caixa` | `caixa` | Único valor disponível enquanto o spike da `STORY-058` não concluir. Ver K.8. |

**Regras:**

- Alternável a qualquer momento, sem confirmação destrutiva — nada se perde.
- Aplicado na leitura: **toda a série histórica passa a ser exibida no modo escolhido**, incluindo competências antigas. Não há migração e não há perda de comparabilidade.
- O modo vigente é exibido junto à demonstração, para que o usuário saiba qual leitura está vendo.
- O parâmetro **não** é gravado no lançamento. Não existe "este mês foi lançado no modo X".

## K.6 Regras de coerência

Aplicadas no cliente e no servidor, no mesmo padrão declarativo já usado pelo Diagnóstico (`config/quiz/validacoes-cruzadas.php`).

**Bloqueantes** — impedem o fechamento do lançamento:

- **B1 — Competência duplicada.** Já existe lançamento para a mesma Empresa Analisada e competência. Oferecer abrir o lançamento existente ou retificá-lo.
- **B2 — Competência futura.** Não se lança mês que ainda não terminou.
- **B3 — Valores negativos.** Nenhum campo aceita valor negativo. Devolução de venda reduz D01; devolução de compra reduz o campo de custo correspondente.
- **B4 — Impostos maiores que a receita.** `D02 > D01` é erro de digitação em qualquer regime tributário brasileiro.

**Alertas** — sinalizam, permitem prosseguir mediante aceite explícito (padrão `alertas_aceitos` já usado no Diagnóstico):

- **A1 — Mês sem faturamento.** `D01 = 0` com qualquer despesa lançada. Legítimo (sazonalidade, obra parada), mas confirma-se. Consequência: percentuais indisponíveis, ver K.7.
- **A2 — Custo maior que a receita.** `total custo > D01`. Possível em mês de reposição de estoque; vale confirmar.
- **A3 — Prejuízo no mês.** Resultado final negativo. Não é erro — é informação. O alerta existe para preparar a leitura, não para questionar o dado.
- **A4 — Rubrica atípica.** A partir do 4º lançamento, qualquer campo que exceda 3× sua própria média móvel dos meses anteriores. Cobre naturalmente férias e 13º (K.8) e erros de ordem de grandeza.
- **A5 — Pró-labore zerado.** `D40 = 0`. Comum e legítimo, mas frequentemente é esquecimento — e distorce a leitura de "quanto sobrou para mim".
- **A6 — Salto de competência.** Existe lacuna entre o último lançamento e o atual. Oferece lançar o mês faltante; a série histórica precisa saber distinguir "mês sem dados" de "mês sem movimento".

## K.7 Ausência de faturamento

Quando `D01 = 0`, a coluna de percentuais não pode ser calculada (denominador zero). O comportamento reaproveita o padrão consolidado do motor de Diagnóstico:

- Percentual gravado como `null`, com motivo `indisponivel:sem_faturamento`.
- Mensagem semântica na tela: *"Sem faturamento neste mês — os percentuais sobre a receita não se aplicam."*
- **Os valores nominais continuam sendo exibidos normalmente.** É a competência mais importante de todas para o usuário ver: quanto custou manter o negócio parado.

## K.8 Rubricas sazonais (férias e 13º)

O `PDR-004`, decisão D, fixou a **provisão contábil completa** como alvo — mas ela exige uma conta patrimonial de saldo acumulado, que o DRM não tem por desenho (regime de caixa puro, `PDR-003`). O caminho está sob spike de arquitetura (`STORY-058`), com saída legítima de virar épico próprio.

**Enquanto o spike não conclui, vale o tratamento abaixo**, que responde à dor concreta — dezembro não deve parecer um desastre só porque o 13º foi pago — sem nenhuma conta patrimonial:

- Campos D20 e D21 coletam o **valor efetivamente pago no mês** (regime de caixa, coerente com o princípio 3).
- Tooltip explica a alternativa de provisionar 1/12 por mês e alerta que o lançamento por caixa concentra a despesa.
- O alerta A4 detecta e sinaliza o mês afetado.
- Na série histórica, o mês sinalizado recebe marcação visual de atípico, para que a queda de resultado não seja lida como piora do negócio.

## K.9 Rascunho, retificação e pré-preenchimento

- **Rascunho automático**, no mesmo padrão de `quiz_rascunhos`: salvo a cada bloco concluído, retomável, com expiração. Ninguém preenche 36 campos numa sentada.
- **Pré-preenchimento a partir da competência anterior.** Ao abrir novo lançamento, os campos vêm com os valores do mês anterior, marcados visualmente como *repetidos* até confirmação. Aluguel, contador, internet e seguros raramente mudam — é onde mora a maior parte do atrito.
- **Retificação.** O usuário pode corrigir uma competência já fechada. A versão anterior é preservada e a tela sinaliza que aquele mês foi retificado. **A mecânica de versionamento é decisão do Arquiteto** (`STORY-045`), por interagir com o IDR-010.

## K.10 Textos de ajuda

Cada campo tem tooltip próprio, no mesmo padrão de `config/quiz/help-industria.php`. Prioridade de redação, por risco de confusão:

1. **D07 vs D09** — frete na aquisição vs frete na entrega. Confusão quase certa sem ajuda explícita.
2. **D10 vs D38** — taxa de cartão (despesa de venda) vs juros de antecipação (despesa financeira).
3. **D16 vs D40** — salários vs retirada do dono. Erro comum do microempresário que se paga junto com a folha.
4. **D02** — para o optante do Simples, é a guia única (DAS); explicitar para evitar dupla contagem.
5. **D20 e D21** — sazonalidade, conforme K.8.

Redação final e revisão editorial pertencem ao pacote de conteúdo da EBC (`STORY-052`).

## K.11 Rastreabilidade com a planilha original

| Campo deste anexo | Célula do **valor** em `Demonstração de Resultado Mensal - DRM.xlsx` (coluna D; a coluna A traz o rótulo) |
|---|---|
| D01 | D3 |
| D02 | D5 |
| D04–D07 | D8, D9, D10, D11 |
| D09–D14 | D15 a D20 |
| D16–D36 | D22 a D42 |
| D38–D39 | D45, D46 |
| D40 | D48 |
| D00 | — (só no título, A1: `"...DRM: AGO/2026"`) |
| D03, D08, D15, D37 | — (agregadores, não existem na planilha) |
| D41, D42 | — (`[DECIDIR-E]`) |

## K.12 Decisões que este anexo reflete

| Item | Decisão | Onde |
|---|---|---|
| `[DECIDIR-B]` | **Resolvido** (`PDR-004`): posição do pró-labore parametrizável, default `fora` | K.3.6, K.5-A, `design/estrutura-drm.md` §1 |
| `[DECIDIR-C]` | Percentual em todas as linhas; "antes do pró-labore" no lugar de "antes do IR/CSLL" | `design/estrutura-drm.md` §2 e §3.2 |
| `[DECIDIR-D]` | Provisão contábil como alvo; caixa + alerta A4 enquanto o spike não conclui | K.8 |
| `[DECIDIR-E]` | Outras receitas e despesas aprovadas | K.3.7 |
| `[DECIDIR-A]` | **Resolvido** (`PDR-009`, decisão 3): o módulo se chama "Raio X do Negócio" na interface | — |

## Histórico

- 05/11/2026 — correções da validação do EPIC-010 (STORY-095, D-6): K.11 aponta as células de valor (coluna D) e não as de rótulo; D16 remete a D40; K.3.6 e K.12 refletem `[DECIDIR-B]` e `[DECIDIR-A]` resolvidos; K.3 distingue 7 obrigatórios e 2 opcionais.
- 04/08/2026 — versão 1.1: incorpora o `PDR-004`. D41/D42 deixam de ser condicionais (38 campos monetários), nova seção K.5-A com os parâmetros da empresa, K.8 reescrita com o encaminhamento da provisão, nova K.12.
- 04/08/2026 — versão 1.0, derivada do feedback do Key User de 03/08/2026 e das decisões do PDR-003.
