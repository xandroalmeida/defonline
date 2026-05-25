# Anexo A — Campos do Questionário (Quiz)

**Documento pai:** [`../especificacao-funcional.md`](../especificacao-funcional.md), seção 4.4
**Versão:** 2.1 (alinhado ao modelo de domínio refatorado)
**Data:** 17/05/2026
**Fonte original:** planilha `DEFweb.net - ESTRUTURA - ebc.2025jul30.xlsx`, abas **Questionário** e **QUIZ**
**Mantido por:** EB Parcerias Ltda (Produto)

**Versão vigente:** 2.1 (17/05/2026), com correções da EBC (22/04/2026) — obrigatoriedade condicional de Q11 (Serviços), Q21–Q23 obrigatórios quando Q17=1, textos expandidos de Q14/Q15/Q16, validação Q20 < Q09, tooltip/box explicativo por campo — e desambiguação do termo "cliente" (compradores da Empresa Analisada, no sentido contábil).

---

## A.1 Finalidade

Este anexo consolida, em forma navegável e consultável de forma independente, a lista completa dos 23 campos coletados no questionário de diagnóstico de uma **Empresa Analisada**. É a referência canônica para:

- Implementação do formulário pelo time de desenvolvimento.
- Validação de coerência (regras cruzadas entre campos).
- Modelo de dados do Quiz no banco (cada Quiz tem FK para a Empresa Analisada e para o Usuário que o preenche — ver `../arquitetura-tecnica.md` §4.1).
- Treinamento de equipe de suporte.

> **Nota terminológica.** Neste anexo, o termo "cliente" é usado **apenas no sentido contábil** — designa o **comprador/consumidor da Empresa Analisada** (quem compra produtos ou serviços dela). Não confundir com "Cliente da plataforma" ou similar: a plataforma DEFOnline tem **Usuários** (pessoas que logam) e **Empresas Analisadas** (entidades cujos dados são analisados) — ver `../especificacao-funcional.md` §1.5.2.

A tabela equivalente aparece resumida na seção 4.4 da especificação funcional. Este anexo é a versão longa com notas de implementação.

## A.2 Lista de campos

| Ref | Campo | Tipo | Formato | Obrigatório | Visível quando | Observação |
|---|---|---|---|---|---|---|
| Q01 | Setor de atividade | Enum | 1=Indústria · 2=Comércio · 3=Serviços | Sim | sempre | Dropdown, define matriz de faixas e textos de recomendação. |
| Q02 | Recursos disponíveis | Numérico | R$ ≥ 0 | Sim | sempre | Caixa + bancos + aplicações de liquidez imediata. |
| Q03 | Contas a receber | Numérico | R$ ≥ 0 | Sim | sempre | Vendas a prazo a **clientes** (compradores da Empresa Analisada) — cheques, boletos, cartão, fiado. |
| Q04 | Estoque | Numérico | R$ ≥ 0 | Sim | sempre | Produtos, mercadorias, matéria-prima, insumos. |
| Q05 | Patrimônio (imobilizado) | Numérico | R$ ≥ 0 | Sim | sempre | Imóveis, equipamentos, veículos — valor venal de venda rápida. |
| Q06 | Dívidas financeiras | Numérico | R$ ≥ 0 | Sim | sempre | Empréstimos, financiamentos, dívidas não operacionais. |
| Q07 | Fornecedores a pagar | Numérico | R$ ≥ 0 | Sim | sempre | Obrigações comerciais em aberto. |
| Q08 | Compras (média mensal 12 meses) | Numérico | R$ ≥ 0 | Sim | sempre | Mercadorias, matéria-prima, insumos. **Anualizado × 12** no motor de cálculo. |
| Q09 | Vendas (média mensal 12 meses) | Numérico | R$ ≥ 0 | Sim | sempre | Receita operacional bruta. **Anualizado × 12**. |
| Q10 | PMC — prazo de fornecedores | Numérico | dias ≥ 0 | Sim | sempre | Prazo médio de pagamento de compras. |
| Q11 | PME — giro do estoque | Numérico | dias ≥ 0 | **Se Q01 ≠ 3** (não obrigatório para Serviços) | **Q01 ≠ 3** (suprimido da UI para Serviços) | Prazo médio de permanência em estoque. Para setor Serviços (Q01=3) o campo não é coletado e o indicador correspondente (Anexo D, #12) é omitido do relatório. |
| Q12 | PMR — prazo a clientes | Numérico | dias ≥ 0 | Sim | sempre | Prazo médio de recebimento das vendas feitas pela Empresa Analisada aos seus **clientes** (compradores). |
| Q13 | Inadimplência | Numérico | % ≥ 0 | Sim | sempre | % médio de atraso dos **clientes** (compradores) da Empresa Analisada. Valor > 100% não é aceito. |
| Q14 | Custos e despesas fixas | Numérico | R$ ≥ 0 | Sim | sempre | Valor médio mensal de saídas do caixa para custos fixos: folha de pagamento, contador, aluguel, condomínio, energia, água, telefone, internet, franquia, tarifas, retirada de sócios e despesas fixas. **Anualizado × 12** no motor. |
| Q15 | Custos e despesas variáveis | Numérico | R$ ≥ 0 | Sim | sempre | Valor médio mensal de saídas do caixa para custos variáveis: fretes, comissões de venda, tributos (impostos, taxas, contribuições) e despesas variáveis. **Anualizado × 12**. |
| Q16 | Despesas financeiras | Numérico | R$ ≥ 0 | Sim | sempre | Juros pagos a bancos, antecipações **de cartões e desconto de boletos**. **Anualizado × 12**. |
| Q17 | Necessita captar recursos | Enum | 1=Sim · 2=Não | Sim | sempre | Se 1, habilita Q18, Q19, Q20, Q21, Q22, Q23 e a seção de captação no relatório. |
| Q18 | Valor que precisa captar | Numérico | R$ ≥ 0 | Se Q17=1 | Q17=1 | Montante desejado. |
| Q19 | Endividamento total no mercado | Numérico | R$ ≥ 0 | Se Q17=1 | Q17=1 | Bancos + particulares. |
| Q20 | Venda mensal com cartão/duplicatas | Numérico | R$ ≥ 0 **e < Q09** | Se Q17=1 | Q17=1 | Base para cálculo de garantia / capacidade (3× esse valor). O valor declarado deve ser inferior à venda mensal total (Q09). |
| Q21 | CPF do sócio 1 | Texto | CPF | **Se Q17=1** | Q17=1 | Validação de dígitos verificadores. |
| Q22 | CPF do sócio 2 | Texto | CPF | **Se Q17=1** | Q17=1 | — |
| Q23 | CPF do sócio 3 | Texto | CPF | **Se Q17=1** | Q17=1 | — |

## A.3 Regras de coerência

Aplicadas no cliente (antes do envio) e no servidor (no recebimento):

- **Prazos (Q10, Q11, Q12) acima de 365 dias:** exibir confirmação explícita *("Tem certeza? Valor atípico")*; não bloqueia envio.
- **Compras > Vendas (Q08 > Q09):** exibir alerta *("Suas compras são maiores que suas vendas — é temporário ou permanente?")*; não bloqueia envio.
- **Q20 ≥ Q09:** bloqueio rígido — a venda mensal com cartão/duplicatas não pode ser maior ou igual à venda mensal total.
- **Inadimplência > 100% (Q13):** bloqueio rígido; não permite envio.
- **Q11 quando Q01=3 (Serviços):** suprimir o campo da UI; não coletar e gravar como nulo no banco.
- **Captação (Q17 = 1):** **Q18 a Q23** passam a ser obrigatórios; caso contrário são ignorados (gravados como nulos).
- **CPFs (Q21, Q22, Q23):** validação de dígitos verificadores quando preenchidos; obrigatórios se Q17=1.
- **Validações cruzadas DRE × Balanço:** `[DECIDIR]` (ver especificação principal, Seção 6, item 6.8). Proposta mínima: Q16 ≫ Q06 gera alerta ("Despesas financeiras anuais parecem altas em relação à dívida declarada").
- **Máscara monetária:** todos os valores em R$ apresentados como `R$ 1.234,56`.

## A.4 Rascunho e clonagem

- **Rascunho automático:** o quiz é salvo a cada passo completo. Não consome cota nem crédito; o consumo só ocorre no envio final.
- **Expiração do rascunho:** `[DECIDIR]` (ver Seção 6, item 6.4). Proposta: 90 dias a contar da última edição; ao retomar após esse período, exibir alerta "Seus dados podem estar desatualizados — revise antes de enviar".
- **Clonagem:** ao criar um novo quiz, o usuário escolhe entre **Começar do zero** ou **Usar último como base** (pré-preenchendo todos os campos com o diagnóstico anterior).

## A.5 Campos derivados (não coletados, calculados)

Para contexto: o motor de cálculo deriva, **a partir de 16 dos 23 campos** acima (os 7 restantes — Q17 flag, Q18–Q20 de captação e Q21–Q23 de CPFs — alimentam exclusivamente a seção de captação), o Balanço Adaptado, a DRE Adaptada e os **14 indicadores**. As fórmulas completas estão nos Anexos B, C e D da especificação funcional principal.

## A.6 Tooltip / box explicativo por campo

Cada campo do quiz exibe um ícone de ajuda (?) ao lado do label. Ao clicar no ícone, abre-se um **box inline** (popover em desktop ≥ 1024px, bottom-sheet em mobile) com texto curto em 2–4 linhas: **conceito do campo + exemplo numérico concreto + dica de como obter o dado**. Finalidade: reduzir abandono de usuários sem formação financeira (Persona 1 — Joana) e melhorar a qualidade da entrada de dados.

**Fonte primária:** coluna `DESCRIÇÕES EM ÍCONES (?)` da planilha `DEFweb.net - QUIZ.xlsx` (autoria EB Parcerias / EBC) — campos Q02 a Q16. Textos foram expandidos pela equipe de produto seguindo o padrão §6.8 da especificação funcional. Campos Q01, Q17 a Q23 não constavam na planilha original (são posteriores ao recorte EBC); foram redigidos pela equipe de produto e ficam marcados como **rascunho — confirmar com EBC** na próxima reunião de revisão.

**Implementação técnica:** arquivo `app/config/quiz/help-industria.php` (config PHP versionado em git). Edição do texto = edição do config + bump de versão deste anexo. STORY-033 (sprint W25) implementa o componente `<x-help>` consumindo este config.

### Tabela A.6 — textos dos tooltips (Indústria, versão 1.0)

| Campo | Texto do tooltip | Origem |
|---|---|---|
| Q01 — Setor de atividade | Define o setor da Empresa Analisada — escolha o que mais se aproxima da sua atividade principal. Cada setor tem faixas de farol e textos de recomendação próprios (matriz dez/2025). Exemplo: marcenaria, metalúrgica, padaria com produção própria = Indústria. Loja de roupas, mercadinho = Comércio. Escritório de contabilidade, oficina mecânica, salão = Serviços. Dica: se a empresa **transforma matéria-prima** em produto vendido, é Indústria. Se **revende** sem transformar, é Comércio. Se **executa** trabalho/atendimento sem produto físico, é Serviços. | rascunho — confirmar EBC |
| Q02 — Recursos disponíveis | Valor total disponível em caixa mais saldos em bancos e aplicações de liquidez imediata. Exemplo: R$ 3.000 no caixa + R$ 25.000 na conta corrente + R$ 12.000 em CDB resgatável no dia = R$ 40.000. Dica: olhe o extrato bancário de hoje e some o dinheiro físico. Não inclua investimentos com prazo de carência (CDB longo, fundos de prazo). | QUIZ B4 (expandido) |
| Q03 — Contas a receber | Valor total a receber de **clientes** (os compradores da empresa) por vendas a prazo: duplicatas, cheques pós-datados, cartão de crédito, boletos, caderneta, fiado. Exemplo: R$ 18.000 em duplicatas + R$ 4.500 em cheques + R$ 11.000 em cartão a receber = R$ 33.500. Dica: somar tudo que ainda não entrou no caixa mas vai entrar. Não confundir com vendas já recebidas no mês. | QUIZ B5 (expandido) |
| Q04 — Estoque | Valor total dos estoques: produtos prontos, mercadorias, matéria-prima e insumos. Exemplo: marcenaria com R$ 8.000 em madeira + R$ 5.000 em ferragens + R$ 12.000 em móveis prontos = R$ 25.000. Dica: avaliação pelo custo de aquisição (quanto você pagou). Inventário recente ajuda; se não tem, faça estimativa rápida por categoria. | QUIZ B6 (expandido) |
| Q05 — Patrimônio (imobilizado) | Valor venal de mercado dos imóveis, equipamentos e veículos da empresa — estimativa em hipótese de venda rápida. Exemplo: galpão R$ 220.000 + máquinas R$ 80.000 + caminhão R$ 35.000 = R$ 335.000. Dica: pense "quanto eu conseguiria por isso se precisasse vender rápido?" — não o valor de balanço contábil nem o de seguro. | QUIZ B7 (expandido) |
| Q06 — Dívidas financeiras | Soma de todas as dívidas com instituições financeiras: empréstimos, financiamentos, cheque especial, antecipações. **Não** inclua fornecedores. Exemplo: R$ 45.000 em empréstimo bancário + R$ 18.000 de financiamento de máquina + R$ 3.500 de cheque especial = R$ 66.500. Dica: somar saldo devedor atual de todos os contratos com banco/financeira. Olhar o último extrato de cada um. | QUIZ B8 (expandido) |
| Q07 — Fornecedores a pagar | Soma de todas as obrigações comerciais em aberto com fornecedores (matéria-prima, mercadorias, insumos). Exemplo: R$ 12.000 em boletos de fornecedores + R$ 4.500 em duplicatas a pagar = R$ 16.500. Dica: contas a pagar pendentes hoje. Não somar contratos futuros ainda não faturados. | QUIZ B9 (expandido) |
| Q08 — Compras (média mensal) | Valor médio mensal das compras realizadas nos últimos 12 meses: mercadorias, matéria-prima e insumos. Exemplo: se em 12 meses comprou R$ 240.000 em insumos, a média mensal é R$ 20.000. Dica: somar valor das notas fiscais de compra do ano e dividir por 12. Não incluir compras de imobilizado (máquinas, veículos). | QUIZ B10 (expandido) |
| Q09 — Vendas (média mensal) | Valor médio mensal das vendas realizadas nos últimos 12 meses — receita operacional bruta. Exemplo: faturou R$ 480.000 no ano = média mensal R$ 40.000. Dica: somar valor das notas fiscais de venda emitidas no ano e dividir por 12. Considerar o bruto (antes de impostos). | QUIZ B11 (expandido) |
| Q10 — PMC — prazo de fornecedores | Prazo médio de pagamento das compras a fornecedores, em **dias**. Exemplo: se você compra para pagar em 30/60/90, a média é 60 dias. Dica: se varia muito entre fornecedores, calcule a média ponderada pelo valor das compras. Em dúvida, use o prazo mais comum. | QUIZ B12 (expandido) |
| Q11 — PME — giro do estoque | Prazo médio em **dias** que o estoque demora a girar (entrar e sair). Considere produtos com permanência maior que 60 dias. Exemplo: se compra mensalmente e o estoque dura ~75 dias na prateleira/depósito, o PME é 75 dias. Dica: PME = (estoque médio ÷ custo das vendas) × 360. Empresas de serviços puros costumam ter PME = 0. | QUIZ B13 (expandido) |
| Q12 — PMR — prazo a clientes | Prazo médio de recebimento das vendas a prazo, em **dias** — quanto tempo entre vender e receber. Exemplo: se você vende no cartão em 4 parcelas e recebe a média em 60 dias, o PMR é 60. Dica: pondere prazos diferentes pelo volume. Venda à vista entra como 0 dias. | QUIZ B14 (expandido) |
| Q13 — Inadimplência | Percentual médio de atraso dos clientes nas vendas a prazo (%). Valores acima de 100% não são aceitos. Exemplo: se de cada R$ 100 vendidos a prazo, R$ 5 atrasam mais de 30 dias, inadimplência = 5%. Dica: olhe o relatório de duplicatas vencidas ou contas a receber do seu sistema. Se não tem controle preciso, estime conservadoramente. | QUIZ B15 (expandido) |
| Q14 — Custos e despesas fixas | Valor médio mensal de saídas do caixa para custos fixos: folha de pagamento (com encargos), contador, aluguel, condomínio, energia, água, telefone/internet, franquia, tarifas, retirada de sócios, demais despesas fixas. Exemplo: folha R$ 18.000 + aluguel R$ 5.000 + serviços/tarifas R$ 4.000 + retiradas R$ 8.000 = R$ 35.000. Dica: somar o que sai todo mês independentemente do volume de vendas. Não incluir tributos sobre venda (vão em variáveis). | QUIZ B16 (expandido) |
| Q15 — Custos e despesas variáveis | Valor médio mensal de saídas para custos variáveis: fretes, comissões de venda, tributos sobre vendas (impostos, taxas, contribuições) e demais despesas variáveis. Exemplo: tributos sobre vendas R$ 6.000 + comissões R$ 2.500 + frete R$ 1.500 = R$ 10.000. Dica: o que aumenta proporcionalmente quando você vende mais. Se Simples Nacional, o DAS entra aqui. | QUIZ B17 (expandido) |
| Q16 — Despesas financeiras | Valor médio mensal pago em **juros** por empréstimos, financiamentos, antecipação de recebíveis, cheque especial, desconto de duplicatas. Exemplo: parcelas de empréstimo (juros R$ 1.800) + taxa de cartão (R$ 600) + cheque especial (R$ 250) = R$ 2.650. Dica: somar **só os juros**, não as amortizações (parcelas de devolução do principal). Olhar a linha "juros" no extrato bancário. | QUIZ B18 (expandido) |
| Q17 — Necessita captar recursos | Indica se você precisa captar dinheiro novo (empréstimo, financiamento, sócio investidor). Habilita perguntas extras sobre quanto e como. Exemplo: Sim → você quer crédito para capital de giro, comprar máquina, expandir. Não → empresa não está buscando recursos novos no momento. Dica: responder "Sim" ativa o bloco final do quiz (Q18 a Q23) com perguntas sobre o valor desejado e dados de garantia. Se "Não", o diagnóstico foca só na saúde financeira atual. | rascunho — confirmar EBC |
| Q18 — Valor que precisa captar | Quanto você precisa captar de recursos financeiros (R$). É o montante total que pretende buscar no mercado. Exemplo: comprar máquina nova de R$ 60.000 + capital de giro de R$ 40.000 = R$ 100.000. Dica: pense no valor total da necessidade, não na parcela mensal. O diagnóstico avalia se sua empresa tem capacidade para essa captação. | rascunho — confirmar EBC |
| Q19 — Endividamento total no mercado | Total do endividamento atual da empresa **somado às dívidas pessoais dos sócios** ligadas ao negócio (bancos, financeiras, particulares). Exemplo: dívida da empresa R$ 80.000 + empréstimo pessoal do sócio para a empresa R$ 25.000 = R$ 105.000. Dica: inclua tudo que afetaria a capacidade da empresa de obter novo crédito — saldo devedor de contratos, agiotas, dívidas com fornecedores em atraso. | rascunho — confirmar EBC |
| Q20 — Venda mensal com cartão/duplicatas | Valor médio mensal das vendas que entram via cartão de crédito ou duplicatas (não inclui venda à vista em dinheiro/Pix). Deve ser **menor que Q09** (venda total mensal). Exemplo: vende R$ 40.000/mês no total e 65% é cartão/duplicata = R$ 26.000. Dica: base para calcular sua capacidade de garantia (limite aproximado de 3× esse valor). É o que os bancos olham para crédito com recebíveis. | rascunho — confirmar EBC |
| Q21 — CPF do sócio 1 | CPF do primeiro sócio responsável pela empresa. Usado para análise de capacidade dos sócios na captação. Dica: formato 000.000.000-00. Dígitos verificadores são checados automaticamente. | rascunho — confirmar EBC |
| Q22 — CPF do sócio 2 | CPF do segundo sócio, se houver. Opcional caso a empresa tenha apenas 1 sócio. Dica: deixe em branco se não aplicável. Quando preenchido, formato 000.000.000-00. | rascunho — confirmar EBC |
| Q23 — CPF do sócio 3 | CPF do terceiro sócio, se houver. Opcional. Dica: deixe em branco se não aplicável. Empresas com 4+ sócios serão tratadas em ondas futuras. | rascunho — confirmar EBC |

**Itens marcados "rascunho — confirmar EBC"** (Q01, Q17–Q23): não constavam da planilha QUIZ original (módulo de captação e seleção de setor são posteriores ao recorte EBC). Texto provisório redigido pela equipe de produto em 2026-05-25; agendar revisão com EB Parcerias na próxima reunião de validação.

---

*Este anexo é versionado de forma independente. Ao incluir/remover/alterar um campo, incrementar a versão no cabeçalho e atualizar a referência na seção 4.4 da especificação principal.*
