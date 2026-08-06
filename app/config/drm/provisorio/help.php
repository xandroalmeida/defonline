<?php

declare(strict_types=1);

/**
 * Tooltips do lançamento do DRM — **PROVISÓRIOS**, redigidos pela equipe de
 * produto (`PDR-009` §1, CA-4 da `STORY-052`). Nenhum passou pela EBC.
 *
 * Formato herdado de `config/quiz/help-industria.php`, que está em produção:
 * conceito + exemplo numérico + dica de onde achar o número, em 2–4 linhas.
 * `**negrito**` é suportado pelo `<x-help>`; sem outro markdown.
 *
 * **Campo sem tooltip não renderiza o `<x-help>`** — nem ícone, nem balão
 * vazio (`PDR-009` §1). Um ícone de ajuda que abre "informe o valor do campo"
 * é pior que ícone nenhum: gasta o toque do usuário, ensina que a ajuda deste
 * produto não ajuda, e some da atenção dele para sempre.
 *
 * # Ordem de redação
 *
 * O Anexo K §K.10 lista cinco pares de confusão previsível, e a redação
 * começou por eles porque confusão aqui não gera dúvida — gera **dado errado**,
 * que contamina a série histórica inteira sem ninguém perceber:
 *
 *   1. `D07` × `D09` — frete na aquisição × frete na entrega.
 *   2. `D10` × `D38` — taxa de cartão × juros de antecipação.
 *   3. `D16` × `D40` — salários × retirada do dono.
 *   4. `D02` — para o optante do Simples, é a guia única (DAS).
 *   5. `D20` e `D21` — férias e 13º, sazonalidade.
 *
 * Nesses cinco, o tooltip **nomeia o par** e diz onde vai o outro lado. Um
 * texto que só explica o próprio campo não desfaz confusão entre dois campos.
 *
 * # Promoção
 *
 * Mover a linha para `../help.php`, subir os dois `version` e o
 * `conteudo_version`. Ver `README.md` deste diretório.
 */
return [
    'version' => '1.0.0', // 2026-08-06 — STORY-052: 38 campos + competência.

    'campos' => [
        'D00' => 'Mês e ano a que se referem os valores deste lançamento. Lance o mês **já terminado** — não dá para fechar um mês em andamento. Exemplo: em 05/09 você lança agosto. Dica: cada mês é lançado uma única vez; se precisar corrigir depois, use a retificação, que preserva o que foi lançado antes.',

        // ── Par de confusão 4 (K.10): guia única do Simples ──────────────────
        'D01' => 'Tudo o que a empresa vendeu no mês, sem descontar nada: mercadorias, produtos e serviços, à vista e a prazo. Exemplo: R$ 42.000 no cartão + R$ 18.000 em dinheiro e Pix + R$ 12.000 faturado a prazo = R$ 72.000. Dica: é o valor **cheio** da venda. Impostos, taxa de cartão e comissão têm campo próprio e saem depois — não desconte aqui, senão eles sairiam duas vezes.',

        'D02' => 'Impostos que incidem sobre a venda e foram **pagos** neste mês: ICMS, ISS, PIS, COFINS. Se a empresa é optante do **Simples Nacional, é a guia única (DAS)** — lance o valor do DAS aqui e não repita nada em outro campo. Exemplo: DAS de R$ 4.320 pago em 20/08 = R$ 4.320. Dica: não inclua IPTU, IPVA nem taxas de licença; esses vão em despesas administrativas.',

        'D03' => 'Soma de tudo o que você gastou para ter o que foi vendido: mercadorias, matéria-prima, insumos e o frete para recebê-los. Exemplo: R$ 15.000 em matéria-prima + R$ 6.000 em insumos + R$ 900 de frete = R$ 21.900. Dica: este campo aparece porque o grupo está em **modo agregado**. Se quiser ver depois onde o custo está concentrado, mude para o modo detalhado.',

        'D04' => 'Compras de mercadorias prontas para revenda, sem transformação. Exemplo: um mercadinho que comprou R$ 28.000 em produtos para as prateleiras. Dica: se a empresa transforma o que compra antes de vender, o que ela compra é matéria-prima ou insumo, não mercadoria.',

        'D05' => 'Compras de matéria-prima — o que entra no produto e é transformado. Exemplo: marcenaria que comprou R$ 12.000 em madeira e R$ 3.000 em ferragens = R$ 15.000. Dica: matéria-prima vira parte física do produto vendido. O que se gasta e não vira produto (lixa, cola, energia) é insumo ou despesa.',

        'D06' => 'Compras de insumos: o que se consome na produção ou na prestação do serviço sem virar parte do produto. Exemplo: R$ 800 em lixas, discos de corte e EPI. Dica: pense "isso acaba enquanto eu produzo, mas o cliente não leva junto".',

        // ── Par de confusão 1 (K.10): D07 × D09 ──────────────────────────────
        'D07' => 'Frete que **você paga para receber** mercadoria, matéria-prima ou insumo do fornecedor — o frete que traz a carga para dentro. Exemplo: R$ 650 pagos à transportadora que entregou a madeira na sua marcenaria. Dica: não confunda com o frete de **entregar ao cliente**, que vai em "Fretes na entrega ao cliente". Um encarece o que você comprou; o outro é despesa de vender.',

        'D08' => 'Soma das despesas que sobem e descem junto com as vendas: fretes de entrega, taxa de cartão, aplicativos, comissões. Exemplo: R$ 2.100 de taxa de cartão + R$ 1.400 de entregas + R$ 900 de comissões = R$ 4.400. Dica: este campo aparece porque o grupo está em **modo agregado**; no detalhado você vê cada item separado.',

        'D09' => 'Frete que **você paga para entregar ao cliente** — o frete que leva a carga para fora. Exemplo: R$ 1.400 gastos com motoboy e transportadora nas entregas do mês. Dica: é despesa de vender, não custo do produto. O frete que traz a mercadoria do fornecedor vai em "Fretes na aquisição".',

        // ── Par de confusão 2 (K.10): D10 × D38 ──────────────────────────────
        'D10' => 'Apenas a **taxa da maquininha** que a operadora desconta em cada venda no cartão. Exemplo: R$ 60.000 vendidos no cartão com taxa média de 3,2% = R$ 1.920. Dica: se você **antecipou** o recebimento para receber antes do prazo, o custo dessa antecipação é **juro**, e vai em "Juros pagos das dívidas" — não aqui. Um é o preço de aceitar cartão; o outro é o preço de receber mais cedo.',

        'D11' => 'Comissões e taxas cobradas por aplicativos de venda e entrega — iFood, Rappi e similares. Exemplo: R$ 18.000 vendidos no iFood com 23% de comissão = R$ 4.140. Dica: use o extrato de repasse do aplicativo; ele separa a comissão do valor repassado.',

        'D12' => 'Royalties e taxa de propaganda pagos à franqueadora. Exemplo: 5% de royalty + 2% de fundo de propaganda sobre R$ 72.000 = R$ 5.040. Dica: só para empresa franqueada. Se não é franquia, deixe zerado.',

        'D13' => 'Comissões de venda pagas no mês — a vendedores, representantes ou parceiros. Exemplo: R$ 900 pagos ao representante sobre os pedidos fechados. Dica: comissão de funcionário registrado que sai na folha entra aqui, não em salários: ela varia com a venda, e é isso que este grupo mede.',

        'D14' => 'Outras despesas que sobem quando você vende mais e caem quando vende menos, e que não couberam nos campos acima. Exemplo: R$ 300 em embalagens e sacolas do mês. Dica: se a despesa é a mesma vendendo muito ou pouco, ela é administrativa, não de vendas.',

        'D15' => 'Soma das despesas que se repetem todo mês independentemente das vendas: folha, aluguel, energia, contador, sistemas. Exemplo: R$ 14.000 de folha + R$ 3.500 de aluguel + R$ 1.200 de contador + R$ 2.300 de outras = R$ 21.000. Dica: este campo aparece porque o grupo está em **modo agregado**. É o grupo com mais itens no modo detalhado — e é nele que costuma estar a maior surpresa quando se olha item a item.',

        // ── Par de confusão 3 (K.10): D16 × D40 ──────────────────────────────
        'D16' => 'Folha líquida paga aos **funcionários** no mês. Exemplo: 4 funcionários somando R$ 11.800 líquidos. Dica: **a sua retirada não entra aqui** — ela tem campo próprio, "Pró-labore". Misturar as duas coisas é o erro mais comum de quem se paga junto com a folha, e ele esconde exatamente a resposta que este relatório existe para dar: quanto sobrou para você.',

        'D17' => 'Encargos sobre a folha pagos no mês: FGTS, INSS patronal, contribuições. Exemplo: R$ 944 de FGTS + R$ 2.360 de INSS = R$ 3.304. Dica: use as guias efetivamente pagas no mês, não a provisão do contador.',

        'D18' => 'Vale transporte pago ou creditado no mês. Exemplo: 4 funcionários × R$ 220 = R$ 880. Dica: lance o valor que saiu do caixa, já descontada a parte que o funcionário paga.',

        'D19' => 'Vale refeição, vale alimentação ou cesta básica fornecidos no mês. Exemplo: 4 × R$ 400 = R$ 1.600. Dica: se a empresa serve refeição própria, o gasto com os insumos dessa refeição também entra aqui.',

        // ── Par de confusão 5 (K.10): sazonalidade ───────────────────────────
        'D20' => 'Férias **efetivamente pagas** neste mês, com o terço constitucional. Exemplo: um funcionário saiu de férias em janeiro e recebeu R$ 2.400 + R$ 800 = R$ 3.200. Dica: lance no mês em que o dinheiro saiu, não no mês em que as férias foram gozadas. Como o valor concentra num mês só, ele deixa aquele mês mais pesado — o relatório sinaliza isso, para o mês não parecer um desastre sem ser.',

        'D21' => 'Décimo-terceiro **efetivamente pago** neste mês, incluindo a parcela adiantada. Exemplo: R$ 5.900 pagos na segunda parcela em dezembro. Dica: como nas férias, vale o mês do pagamento. É por isso que dezembro costuma parecer o pior mês do ano sem ser: o 13º cai inteiro nele.',

        'D22' => 'Uniformes, fardamento e vestuário de trabalho comprados no mês. Exemplo: R$ 480 em camisetas com a marca. Dica: EPI de produção é insumo, não uniforme.',

        'D23' => 'Aluguel do imóvel pago no mês. Exemplo: R$ 3.500 de aluguel + R$ 420 de condomínio + R$ 180 de IPTU cobrados juntos = R$ 4.100. Dica: **inclua condomínio e IPTU quando vierem no mesmo boleto** — separá-los daria trabalho e não muda a leitura.',

        'D24' => 'Conta de energia elétrica paga no mês. Exemplo: R$ 1.850. Dica: se o imóvel é compartilhado com a residência, lance só a parte da empresa — estimativa razoável serve.',

        'D25' => 'Conta de água e esgoto paga no mês. Exemplo: R$ 240.',

        'D26' => 'Telefonia fixa e móvel da empresa no mês. Exemplo: R$ 180 do fixo + R$ 260 dos celulares = R$ 440. Dica: internet tem campo próprio, mesmo quando vem no combo — separe pelo valor do plano na fatura.',

        'D27' => 'Internet e links de dados da empresa. Exemplo: R$ 320 da fibra do estabelecimento.',

        'D28' => 'Honorários do escritório de contabilidade pagos no mês. Exemplo: R$ 1.200. Dica: serviços avulsos do contador (abertura de filial, defesa fiscal) entram aqui também, no mês em que foram pagos.',

        'D29' => 'Assinaturas de sistemas e aplicativos usados no negócio: ERP, PDV, emissor de nota, armazenamento. Exemplo: R$ 189 do sistema de vendas + R$ 60 do emissor = R$ 249. Dica: as taxas de aplicativos de **entrega** não entram aqui — elas variam com a venda e vão em "Taxas de aplicativos de entrega".',

        'D30' => 'Marketing e publicidade do mês: anúncios, impulsionamento, material gráfico, agência. Exemplo: R$ 600 em anúncios + R$ 250 em panfletos = R$ 850. Dica: taxa de propaganda paga à franqueadora vai em "Franquia (royalties)".',

        'D31' => 'Manutenção e reparos de máquinas, equipamentos, móveis e do imóvel. Exemplo: R$ 780 na revisão da máquina de corte. Dica: compra de máquina nova não é manutenção — é investimento, e não entra neste relatório.',

        'D32' => 'Material de limpeza e higiene consumido no mês. Exemplo: R$ 190.',

        'D33' => 'Material de escritório e papelaria. Exemplo: R$ 140 em papel, canetas e bobinas de impressora fiscal.',

        'D34' => 'Seguros pagos no mês: predial, de equipamentos, de frota, de responsabilidade. Exemplo: R$ 310 da parcela do seguro do galpão. Dica: se o seguro é anual e você pagou tudo de uma vez, lance no mês do pagamento — este relatório é em regime de caixa.',

        'D35' => 'Contribuições a sindicatos, associações comerciais e conselhos de classe. Exemplo: R$ 120 da mensalidade da associação.',

        'D36' => 'Outras despesas que se repetem todo mês e não couberam nos campos acima. Exemplo: R$ 200 de estacionamento mensal. Dica: se você usa muito este campo, vale olhar o que está caindo aqui — despesa sem nome é despesa que ninguém revisa.',

        'D37' => 'Soma do que você pagou ao banco e a credores no mês: juros e tarifas. Exemplo: R$ 1.500 de juros + R$ 180 de tarifas = R$ 1.680. Dica: este campo aparece porque o grupo está em **modo agregado**. **Só juros e tarifas** — a parcela que amortiza a dívida não é despesa, é pagamento do que você já devia.',

        'D38' => 'Juros pagos no mês sobre dívidas: empréstimos, cheque especial, **antecipação de recebíveis** e desconto de boletos. Exemplo: R$ 1.200 de juros do empréstimo + R$ 340 da antecipação do cartão = R$ 1.540. Dica: na parcela do empréstimo, só a parte de juros entra aqui — a amortização não é despesa. E a taxa da maquininha, que é o preço de aceitar cartão, vai em "Taxas de administração de cartões".',

        'D39' => 'Tarifas bancárias do mês: manutenção de conta, TED, boletos emitidos, pacote de serviços. Exemplo: R$ 89 do pacote + R$ 91 em boletos = R$ 180.',

        'D40' => 'O quanto **você e os sócios** retiraram do negócio no mês para viver. Exemplo: você retirou R$ 5.000 e seu sócio R$ 5.000 = R$ 10.000. Dica: **sempre lance, mesmo que a retirada saia junto com a folha** — este campo é o que permite responder "quanto sobrou depois de eu me pagar", que é a pergunta central deste relatório. Retirada lançada como salário faz o negócio parecer mais rentável do que é e você, mais barato do que custa.',

        'D41' => 'Dinheiro que entrou no mês e **não veio de vender** seu produto ou serviço. Exemplo: R$ 3.000 pela venda de uma máquina usada + R$ 210 de rendimento de aplicação = R$ 3.210. Dica: se entrou de venda normal, é "Quanto vendeu no mês". Este campo existe para o resultado do mês não ficar inflado por algo que não se repete.',

        'D42' => 'Dinheiro que saiu no mês e não tem a ver com a operação: multas, perdas, indenizações pagas. Exemplo: R$ 450 de multa de trânsito da frota. Dica: se a despesa faz parte de tocar o negócio, ela pertence a um dos grupos anteriores — este é para o que foge do normal.',
    ],
];
