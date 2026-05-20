# Visão do DEFOnline

**Versão:** 1.0 — 20/05/2026
**Mantido por:** Product Owner

## Visão em uma frase

O DEFOnline democratiza o diagnóstico econômico-financeiro da micro e pequena empresa brasileira — para donos que querem clareza sobre o próprio negócio, para contadores que querem entregar valor recorrente aos clientes e, no horizonte, para parceiros financeiros que precisam avaliar tomadores com dados confiáveis.

## O problema

A maior parte das micro e pequenas empresas brasileiras opera sem visibilidade da própria saúde econômico-financeira. O balanço, quando existe, é um documento fiscal que o dono não lê. O contador entrega obrigação acessória, não diagnóstico. Decisões importantes — captar crédito, investir em máquina, contratar, segurar preço — são tomadas no instinto. O resultado é volatilidade, aperto de caixa recorrente, fechamento prematuro de negócios que poderiam dar certo.

O conhecimento técnico para diagnosticar a saúde de uma MPE existe. O que falta é traduzi-lo para a realidade de quem não tem formação financeira, em uma ferramenta acessível, rápida e confiável.

## O valor central que entregamos

Um diagnóstico econômico-financeiro automatizado, gerado em poucos minutos a partir de um quiz guiado, com:

- Relatório com semáforo visual e leitura imediata.
- Recomendações parametrizadas pelo setor de atividade e pela faixa de cada indicador.
- Linguagem amigável, sem exigir conhecimento contábil prévio.
- Histórico para acompanhar evolução ao longo dos meses.

O valor central não é "produzir indicadores" — é **substituir a opacidade pela clareza**, e fazer isso bem o suficiente para que decisões financeiras passem a ser informadas.

## Para quem

A visão cobre três públicos com graus distintos de maturidade no produto.

**No coração do MVP:** o **dono da MPE** — MEI, ME ou EPP — que opera o próprio negócio e quer entender se está ganhando dinheiro, se pode investir, se está em risco. É o público mais subatendido e o que justifica a missão de democratização.

**No mesmo ano:** o **contador / consultor** que atende dezenas de pequenas empresas e quer entregar diagnóstico recorrente com qualidade profissional e escala. Esse público é alavanca de distribuição e de retenção, e o produto já nasce com plano Pro pensado para ele.

**No horizonte:** **parceiros financeiros** (bancos, fintechs, cooperativas, factorings) que precisam avaliar a saúde de MPEs tomadoras de crédito. Esse uso é B2B, exige API dedicada e adequação jurídica adicional — não está na primeira onda, mas o desenho do produto não pode fechar portas para ele.

A primeira onda elege **uma** dessas frentes como prioritária — definido em `north-star.md` e no PDR de escopo da onda. Cobrir as três simultaneamente desde o dia 1 dispersaria o foco.

## O que o DEFOnline é, e o que não é

O DEFOnline **é** uma plataforma SaaS de autosserviço de diagnóstico, com motor determinístico de 14 indicadores e biblioteca de recomendações curadas. Roda em navegador (desktop e mobile responsivo). Trabalha em cima de dados declarados pelo usuário a partir do próprio controle financeiro.

O DEFOnline **não é**: substituto de contador, software de gestão (ERP), sistema de escrituração fiscal, plataforma de crédito (não empresta dinheiro nem aprova operação), nem ferramenta de análise contábil avançada para empresas de médio e grande porte. Cada vez que uma decisão de produto puxar para um desses territórios, ela precisa de PDR explícito justificando a expansão de escopo.

## Princípios de produto que herdam para todas as ondas

- **Clareza vence completude.** Indicador a mais que confunde é indicador a menos.
- **Entrega em produção desde o dia 1.** Cada onda deixa pegada visível para o usuário final.
- **Qualidade é requisito, não negociação.** Cobertura de testes, observabilidade, automação — sempre.
- **A MPE é o filtro.** Se uma decisão de produto melhora a vida do contador mas piora a do dono, repensar; o contador vive da satisfação do cliente.

## Referências canônicas

A regulação detalhada de regras de negócio, motor de cálculo, jornada, NFRs e jurídico vive em `defonline-docs/especificacao/V2/`. Esta visão **não duplica** esse conteúdo — só fixa o norte que guia priorização.
