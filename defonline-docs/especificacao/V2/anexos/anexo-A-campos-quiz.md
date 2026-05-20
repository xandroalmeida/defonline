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

Cada campo do quiz exibirá um ícone de ajuda (?) com **box inline explicativo do conceito do indicador associado** (sem sair da página). Os conteúdos dos boxes são derivados da aba `QUIZ` da planilha de Estrutura e serão consolidados em tabela dedicada deste anexo na próxima iteração. Finalidade: reduzir abandono de usuários sem formação financeira (Persona 1 — Joana) e melhorar a qualidade da entrada de dados.

---

*Este anexo é versionado de forma independente. Ao incluir/remover/alterar um campo, incrementar a versão no cabeçalho e atualizar a referência na seção 4.4 da especificação principal.*
