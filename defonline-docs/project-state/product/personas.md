# Personas do DEFOnline

**Versão:** 1.0 — 20/05/2026
**Mantido por:** Product Owner

Este documento fixa a persona alvo da onda atual e referencia, em segundo plano, os públicos secundários e futuros. Persona aqui não é "usuário genérico" — é alguém com **contexto, dor e gatilho de uso**, suficiente para guiar critérios de aceite, copy do produto e decisões de priorização.

Detalhamento canônico das personas formais (com cadastro, plano, modelo de dados) vive em `defonline-docs/especificacao/V2/especificacao-funcional.md` §1.3. Este arquivo **não duplica** aquele conteúdo — fixa a leitura de produto.

## Persona primária da onda 1 — Roberto

**Quem é.** Roberto, 47 anos, dono de uma marcenaria de móveis sob medida (EPP, indústria) no interior de São Paulo. Comanda 12 funcionários e um galpão próprio. Toca a operação fabril com domínio técnico — sabe medir madeira, calcular ferragem, negociar com fornecedor — mas nunca teve formação financeira. Delega a contabilidade ao escritório terceirizado, que entrega obrigação fiscal e pouco mais. O cônjuge cuida da parte administrativa em meio expediente.

**Como vive o dinheiro do negócio hoje.** Tem estoque elevado de matéria-prima (madeira nobre encomendada com 30 dias de antecedência). Vende a prazo para lojistas e empresas médias — recebe em 60 a 90 dias. Paga fornecedor à vista ou em 30 dias. Convive com aperto de caixa recorrente, principalmente em mês de baixa. Já recorreu a desconto de duplicata e a empréstimo pessoal para tampar buraco operacional.

**A dor que justifica usar o DEFOnline.** Três decisões reais batem na mesa todo trimestre:

- **Investir em máquina nova** (uma serra CNC custa o equivalente a três meses de faturamento). Vale o financiamento? Aguenta a parcela?
- **Captar capital de giro** num banco ou cooperativa. Quanto pode pedir sem comprometer o operacional?
- **Reajustar preço** dos produtos. A margem atual está coerente com o custo real ou está corroendo o lucro?

Hoje Roberto decide no instinto, ou pede opinião informal ao próprio contador (que dá uma resposta cautelosa e genérica). Contratar uma análise financeira profissional ad-hoc custa caro e demora — e ele já se queimou uma vez com consultor que entregou relatório que ele não conseguiu ler.

**Job-to-be-done.**
> "Quando preciso decidir se invisto, capto ou reajusto preço, contratar uma análise profissional não cabe no tempo nem no orçamento — quero saber, em minutos, se a saúde do meu negócio aguenta a decisão e quanto cabe nela, em linguagem que eu entenda sozinho."

**Gatilho de uso.** Não é "todo mês porque é bonito" — é **quando uma decisão financeira aparece**. Tipicamente a cada 2 a 4 meses. Por isso retenção do DEFOnline para Roberto **não é frequência alta** — é **continuidade ao longo do ano** com pelo menos 3 a 4 diagnósticos no período.

**O que faz Roberto dizer "isso resolve meu problema".**

- Em 15 minutos ele sai com um relatório que ele entende, sozinho, sem precisar pedir ajuda.
- O relatório responde à pergunta dele (posso captar? quanto? estou saudável?), não só joga indicadores.
- O semáforo torna óbvio onde está o problema, se houver.
- A recomendação é específica para indústria de pequeno porte — não genérica.

**O que faz Roberto desistir.**

- Quiz longo, com termos contábeis que ele não conhece (PL, CMV, NCG sem explicação).
- Resultado que ele não entende ou que parece desconectado da realidade dele.
- Necessidade de ter balanço formal montado para preencher.
- Lentidão no relatório (espera de minutos para gerar).

**Plano provável.** Básico (R$ 49,90/mês), com média de 3 a 4 análises por ano. Pode evoluir para compra avulsa de crédito Plus quando precisar de duas análises seguidas (cenário de comparação antes/depois de decisão).

## Personas secundárias (mesmo ano, fora da onda 1)

**Joana — dona de loja (MEI/ME, comércio).** Perfil mais comum em volume, dor mais difusa ("clareza" sem decisão financeira de alto valor), menor capacidade de pagar e maior risco de churn. Atendida pelo mesmo produto da onda 1, mas com **copy e onboarding adaptados** numa onda subsequente. O motor e o relatório precisam continuar fazendo sentido para ela — é tarefa da onda 1 não introduzir nada que **quebre** a leitura de Joana, mesmo sem ela ser a alvo primária.

**Marcos — contador (Pro).** Profissional intermediário, alavanca de distribuição e ARPU maior. Atendido por evolução do plano Pro (portfólio multi-cliente, workflow de relatório recorrente, eventualmente white-label parcial). Importante: o plano Pro já é nativo no modelo de domínio do MVP (cada Usuário pode ter N Empresas Analisadas), mas a UX e features Pro entram em onda posterior. Risco a evitar na onda 1: tomar decisão de produto que **trave** o caminho do Pro depois.

## Personas no horizonte (fora do MVP)

**Persona 4 — Consultor independente de MPE.** Cobertura funcional muito próxima da do contador (Marcos). Tratada como variação do Pro, não como persona separada, até evidência em contrário no pós-go-live. Ver `especificacao-funcional.md` §1.3.4.

**Persona 5 — Fornecedor de crédito (bancos, fintechs, cooperativas, factorings).** Uso B2B via API. Exige produto adicional (API autenticada, SLA contratual, DPA específico). Fica registrada no roadmap pós-v1; decisões de produto da onda 1 não podem inviabilizá-la, mas também não devem antecipar custo dela.

**Parceiros Patrocinadores** (Sebrae, BB, BNB, CDL, Federações). Canal B2B2C de aquisição em massa. Roadmap pós-v1. Sem impacto direto na onda 1.

## Por que essas personas, e não outras

A escolha de Roberto como alvo primário da onda 1 segue três critérios:

- **Dor financeira clara e custosa.** Decidir investir/captar/precificar é decisão de alto valor — justifica o ticket e a dedicação de 15 minutos a um quiz.
- **Adesão direta à hipótese central da visão.** Se Roberto consegue tomar decisão melhor com o DEFOnline, a hipótese "diagnóstico automatizado para MPE substitui a opacidade pela clareza" foi validada no caso mais exigente. Joana, com dor mais difusa, é teste mais fraco da hipótese.
- **Fundação reaproveitável.** O motor de 14 indicadores, recomendações por setor, relatório com semáforo e quiz que servem Roberto servem também Joana com pequenas adaptações de copy. Caminho inverso (começar por Joana e estender para Roberto) exigiria expandir o motor depois, custo maior.

A decisão é registrada no PDR de escopo da onda (`decisions/pdr/PDR-001-*.md`) com as opções consideradas.
