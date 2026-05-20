# DEFOnline — Especificação Funcional (v2.5)

**Projeto:** Plataforma DEFOnline — Diagnóstico Econômico-Financeiro para Micro e Pequenas Empresas
**Versão do documento:** 2.5 (preços parametrizados — kickoff destravado)
**Data:** 17/05/2026
**Responsáveis:** EB Parcerias Ltda
**Público-alvo do documento:** Product Owners, UX/UI, Arquitetos, Desenvolvedores, QA, Stakeholders de Negócio

**Versão vigente:** 2.5 (17/05/2026), consolidada com todas as correções da EBC (22/04/2026), parecer técnico CLAUDE (20/04/2026), refatoração do modelo de domínio (Usuário ↔ Empresa Analisada), algoritmo do Resumo Executivo, NCG absoluto como indicador informativo, dimensionamento do plano Pro e parametrização de preços. As regras de negócio aqui consolidadas seguem intactas após o reset técnico de 19/05/2026 — apenas as decisões de stack foram removidas dos documentos companheiros (arquitetura técnica e RNF).

Documentos complementares: `requisitos-nao-funcionais-e-juridicos.md` (Bloco 3) e `roadmap-pos-v1.md` (Bloco 4). Mapa de aceite/recusa de cada crítica em `resumo-recomendacoes-e-respostas.md`.

---

## Sumário

1. [Contexto e Visão](#1-contexto-e-visão)
2. [Modelo de Negócio](#2-modelo-de-negócio)
3. [Jornada do Usuário](#3-jornada-do-usuário)
4. [Especificação Funcional Detalhada](#4-especificação-funcional-detalhada)
5. [Anexos](#5-anexos)
6. [Decisões em Aberto (a resolver antes do kickoff técnico)](#6-decisões-em-aberto-a-resolver-antes-do-kickoff-técnico)

---

## 1. Contexto e Visão

### 1.1 Sumário Executivo

DEFOnline é uma plataforma SaaS de autosserviço, acessível via web (desktop e mobile), que produz um **Diagnóstico Econômico-Financeiro (DEF)** automatizado para micro e pequenas empresas a partir de um questionário guiado. O produto entrega um relatório com indicadores financeiros, recomendações técnicas parametrizadas pelo setor de atividade e sugestões genéricas de melhoria, traduzindo conceitos contábeis em linguagem amigável para empreendedores sem formação financeira.

O mercado endereçável é expressivo: a Receita Federal contabiliza mais de 15 milhões de MEIs no país, o IBGE aponta 13 milhões de MEIs (2021) e o SEBRAE identifica 6 milhões de microempresas — um público com baixa maturidade em gestão financeira e alta necessidade de ferramentas simples e acessíveis.

A v1 foca na jornada do assinante final: cadastro, pagamento, preenchimento do quiz, visualização e exportação do relatório, histórico de diagnósticos e solicitação de análise primária de captação de recursos. Back-office administrativo, portais de parceiros patrocinadores, programa de afiliados e módulos B2B2C ficam planejados para evoluções pós-v1.

### 1.2 Proposta de Valor e Diferenciais

**Proposta de valor.** "Avaliar a situação econômico-financeira de empresas, oferecendo um autosserviço interativo automatizado e relatório com o Diagnóstico Econômico-Financeiro, a fim de contribuir para melhorar decisões e otimizar resultados."

**Diferenciais competitivos.**
- Atendimento específico à realidade de pequenos empresários, sem exigir conhecimento contábil.
- Uso intuitivo, navegação rápida, linguagem amigável e objetiva.
- Fácil operacionalização: o diagnóstico completo é gerado em poucos segundos ou minutos, a partir de um quiz enxuto.
- Segurança da informação, conformidade com a LGPD e responsividade em desktop e mobile.
- Relatório com semáforo visual, permitindo leitura imediata da saúde do negócio.
- Recomendações parametrizadas por setor (Indústria, Comércio, Serviços) e por faixa de cada indicador.
- Histórico com comparativo temporal para acompanhar a evolução ao longo dos meses.

### 1.3 Público-Alvo e Personas

#### 1.3.1 Perfis atendidos na v1

Os perfis abaixo descrevem o **Usuário** (pessoa física que loga) e a **natureza da Empresa Analisada** que esse Usuário tipicamente cadastra na plataforma. Os dois conceitos são distintos — ver §1.5.2.

- **Dono(a) de MEI.** Pessoa que formalizou um MEI. Cadastra-se como Usuário (CPF + e-mail + senha) e adiciona uma única Empresa Analisada (o próprio MEI, identificado por CNPJ). Baixa maturidade em gestão financeira, busca clareza sobre a saúde do próprio negócio.
- **Dono(a) de Microempresa (ME) ou Empresa de Pequeno Porte (EPP).** Pessoa que se cadastra como Usuário e adiciona a empresa (CNPJ) como Empresa Analisada. Pode ter contador externo, mas atua diretamente na plataforma.
- **Autônomo/Profissional Liberal.** Quando não é formalizado, cadastra-se como Usuário e adiciona Empresa Analisada como CPF (autônomo).
- **Contador/Consultor.** Profissional que se cadastra como Usuário e adiciona **várias** Empresas Analisadas (as empresas que atende) à conta dele. Consome o plano Pro para produzir diagnósticos recorrentes.

#### 1.3.2 Personas-chave

**Persona 1 — Joana, dona de loja de roupas (Usuário Básico).**
Cadastra-se como Usuário com CPF + e-mail. Adiciona uma única Empresa Analisada: a loja dela (ME, comércio), com CNPJ. Tem ponto de venda com 3 funcionárias. Controla as finanças numa planilha e no extrato do banco. Nunca viu um balanço formal. Quer saber se está "ganhando dinheiro de verdade" ou se está perdendo. Motivação: clareza e paz de espírito.

**Persona 2 — Roberto, dono de pequena indústria de móveis sob medida (Usuário Básico).**
Cadastra-se como Usuário com CPF + e-mail. Adiciona uma única Empresa Analisada: a marcenaria (EPP, indústria), com CNPJ. Comanda 12 funcionários e um galpão próprio. Domina a operação fabril, mas nunca teve formação financeira e delega a contabilidade ao escritório terceirizado. Tem estoque elevado de matéria-prima, vendas a prazo para lojistas e médias empresas, e convive com aperto de caixa recorrente. Quer saber se pode investir numa nova máquina, quanto pode captar sem comprometer o giro, e se o preço que pratica está adequado à margem que o negócio precisa. Motivação: tomar decisão de investimento e de endividamento com segurança.

**Persona 3 — Marcos, contador que atende 60 pequenas empresas (Usuário Pro).**
Cadastra-se como Usuário com CPF + e-mail. **Não vincula a própria PJ do escritório como Empresa Analisada** — adiciona, ao longo do tempo, as empresas que atende (cada uma com CNPJ próprio) como Empresas Analisadas separadas dentro da conta dele. Quer agregar valor consultivo ao serviço mensal. Usa o DEFOnline para entregar **relatórios semestrais** a cada empresa atendida (cadência típica fixada em 17/05/2026 pelo item 6.5 da Seção 6 — checkup mid-year + análise anual, com revisões extras quando há sinais de alerta). A cota de **10 análises/mês = 120 análises/ano** do plano Pro sustenta de 30 a 60 empresas ativas conforme a frequência real escolhida pelo contador para cada cliente. Motivação: retenção de carteira e upsell.

#### 1.3.3 Perfis fora da v1 (roadmap)

- **Parceiro Patrocinador** (Sebrae, BB, BNB, CDL, Federações): compra lotes e distribui via cupons.
- **Parceiro Comercial / Afiliado**: vende assinaturas com comissão.
- **Administrador da plataforma** (EB Parcerias): gestão operacional, financeira e de conteúdo.
- **Cliente e parceiros EB / Xandrix**: stakeholders internos do projeto (time de produto, desenvolvimento e go-to-market).
- **CRC e associações de classe de contadores**: canal de aquisição estruturada para o plano Pro — detalhado no documento de roadmap pós-v1.

#### 1.3.4 Personas previstas para futuro (fora do MVP — justificativa)

Levantadas durante a revisão de 22/04/2026 e mapeadas para evolução pós-v1:

- **Persona 4 — Consultor independente de MPEs.** Perfil de uso e necessidades muito próximo do contador (Persona 3): atende carteira de clientes pequenos, precisa de portfólio multi-cliente e histórico agregado. **Decisão:** não cria-se Persona 4 autônoma no MVP porque a cobertura funcional já é endereçada pelo plano Pro + funcionalidade de portfólio multi-cliente (ver Seção 6, item 6.1). Tratar consultores como Pro evita duplicação de especificação e preserva o foco do MVP em uma proposta comercial. Caso, após o go-live, a pesquisa com usuários Pro evidencie necessidade específica (ex.: white-label, templates de apresentação ao cliente final), a Persona 4 é destacada no roadmap.
- **Persona 5 — Fornecedor de crédito (bancos, fintechs, cooperativas, factorings).** Perfil B2B, uso em massa, com probabilidade alta de consumo via API e fluxos de onboarding de MPE tomadora. **Decisão:** fora do MVP por três razões: (1) exige produtos adicionais não previstos na v1 — API B2B autenticada, SLA contratual reforçado, termos de uso de dados de terceiros; (2) implica adequação LGPD mais complexa, com base legal de legítimo interesse e possível DPA específico; (3) não compartilha a jornada de autosserviço do MVP. Fica registrada no `roadmap-pos-v1.md` como parte de um módulo B2B distinto, com porta-aberta para integração via API após estabilização da v1.

### 1.4 Escopo da v1 e Fora de Escopo

#### 1.4.1 Dentro do escopo da v1

Hotsite público com páginas de vendas, FAQ, blog inicial, contato. Cadastro de **Usuário** (CPF + e-mail + senha + telefone WhatsApp) com fluxo de cadastro da primeira **Empresa Analisada** (CNPJ enriquecido via API da RFB ou CPF manual) na sequência. Aceite de Termo de Adesão e consentimentos LGPD — **ambos serão revisados e ajustados após a conclusão das definições de "Regras do Negócio" e da "Especificação Funcional"**. Módulo de assinatura com planos recorrentes (Básico e Pro) e compra de créditos avulsos. **Vinculação de N Empresas Analisadas a uma mesma conta de Usuário** — relação 1:N nativa no MVP, sem limite no quantitativo (o controle de uso é exercido pela cota mensal de análises do plano). Pagamento via Pix e cartão de crédito recorrente. Trial de 7 dias com 1 análise (ver Seção 2.3 — a oferta gratuita está marcada `[DECIDIR]` na Seção 6, podendo ser ativada ou mantida desligada no MVP). Questionário (quiz) com os campos da planilha de Estrutura, vinculado a uma Empresa Analisada específica. Motor de cálculo (balanço e DRE adaptados) e de **14 indicadores**. Motor de recomendações por setor e faixa, com duas versões de texto (resumida e detalhada). Relatório com semáforo, sugestões genéricas, glossário. Histórico de diagnósticos (12 meses rolantes) com comparativo temporal, agrupado por Empresa Analisada. Solicitação internalizada de análise primária de viabilidade de captação por Empresa Analisada. Central de Ajuda (FAQ) e suporte por e-mail. Notificações transacionais por e-mail.

#### 1.4.2 Fora do escopo da v1 (roadmap)

Back-office administrativo completo para EB Parcerias (gestão financeira, operacional, emissão de NFe, gestão de conteúdo CMS). Portais de parceiros patrocinadores com geração de cupons e relatórios agregados. Programa de afiliados com controle de comissão. Módulo B2B2C para distribuição gratuita de análises via patrocínio. Cálculo automático do valor de captação na solicitação (v1 apenas coleta o pedido). Integrações com contabilidade, ERPs ou bancos para importação automática de dados. Aplicativos móveis nativos (iOS/Android) — v1 entrega experiência responsiva via navegador. Chat em tempo real como canal de suporte. Gamificação, benchmarks agregados anonimizados entre empresas, análise preditiva com IA. **Medianas setoriais** (por segmento, UF, porte etc.) construídas a partir do banco de dados de DEFs realizados na plataforma — viabilidade condicionada a volume mínimo de amostra após o go-live. **Versões em francês, espanhol e inglês** (i18n) do hotsite, quiz e relatório. **Compartilhamento de uma mesma Empresa Analisada entre vários Usuários** (relação N:M com papéis: Owner / Editor / Viewer) — útil para o caso em que o dono da empresa e o contador querem ter acesso conjunto à mesma Empresa Analisada; no MVP, cada Empresa Analisada pertence a um único Usuário.

A lista consolidada e priorizada das iniciativas pós-v1 está no documento companheiro [`roadmap-pos-v1.md`](roadmap-pos-v1.md) (Bloco 4).

### 1.5 Convenções e Glossário do Documento

#### 1.5.1 Marcadores de status

**`[A DEFINIR]`** indica ponto em aberto herdado da v1, que precisa ser confirmado antes ou durante a implementação.
**`[A VALIDAR]`** indica valor proposto pelo Product Owner que aguarda validação comercial/jurídica.
**`[DECIDIR]`** indica decisão de produto aberta identificada nas revisões e consolidada na Seção 6 deste documento — resolução requerida antes do kickoff técnico.
**`[VALIDADO]`** indica item anteriormente marcado como `[A VALIDAR]` cuja posição foi confirmada nas revisões.
**`[RESOLVIDO]`** indica item anteriormente marcado `[DECIDIR]` cuja decisão foi tomada e está agora incorporada à especificação.

#### 1.5.2 Entidades do domínio

**Usuário.** Pessoa física que possui credenciais de acesso à plataforma. **Sempre uma pessoa**, identificada por **CPF + e-mail + senha**. Ao se cadastrar, aceita o Termo de Adesão e os consentimentos LGPD, assina um plano (Básico ou Pro) e passa a deter uma **Carteira** (cota mensal + créditos avulsos). Um Usuário pode estar associado a uma ou mais Empresas Analisadas. Quem dispara uma análise consome a própria cota.

**Empresa Analisada.** Entidade — pessoa jurídica (CNPJ) ou pessoa física com atividade econômica (CPF) — cujos dados financeiros alimentam o quiz e cujo diagnóstico é gerado. Atributos: documento (CNPJ ou CPF), razão social/nome, nome fantasia, setor, CNAE, município, UF, situação cadastral. **Não tem login próprio** — é objeto de análise, não ator do sistema. No MVP, cada Empresa Analisada está associada a **exatamente um Usuário** (relação 1:N). Compartilhamento entre Usuários (N:M) está previsto no roadmap.

**Cliente.** Termo de uso **estritamente contábil**, referindo-se ao **comprador/consumidor da Empresa Analisada**. Aparece apenas no contexto de campos como Q03 (Contas a receber de clientes), Q12 (PMR — prazo a clientes) e Q13 (Inadimplência de clientes). **Não é uma entidade da plataforma** — não tem cadastro, CRUD ou identificador único. É um conceito presente nos dados financeiros declarados pelo Usuário.

> **Nota terminológica.** Para a UX, a interface do plano Pro chama o conjunto de Empresas Analisadas vinculadas a um Usuário simplesmente de **"Empresas"** (ex.: "Minhas Empresas", "Adicionar Empresa"). O termo "Empresa Analisada" é reservado para esta documentação técnica e para os documentos jurídicos.

#### 1.5.3 Termos operacionais

**DEF** é o Diagnóstico Econômico-Financeiro (produto e relatório).
**Quiz** é o questionário que o Usuário preenche para gerar um diagnóstico de uma Empresa Analisada específica.
**Análise** é uma execução completa do motor a partir de um quiz — consome 1 cota mensal ou 1 crédito da Carteira do Usuário.
**Carteira** é a estrutura associada ao Usuário que reúne sua assinatura (cota mensal) e seus créditos avulsos.
**Cota mensal** é a quantidade de análises inclusas no plano de assinatura por ciclo.
**Crédito** é uma unidade avulsa de análise, comprada em pacote.

---

## 2. Modelo de Negócio

### 2.1 Planos de Assinatura

A plataforma oferece dois planos recorrentes contratados pelo **Usuário**, cobrados em ciclo mensal ou anual (pagamento anual com desconto).

> **Parametrização.** Todos os valores monetários (preços e desconto anual), cotas mensais e demais parâmetros configuráveis ficam armazenados em uma **tabela `Plan`** do banco de dados (ver `arquitetura-tecnica.md` §4.1), permitindo ajuste sem deploy. Alterações são versionadas para auditoria. Os valores abaixo são as **referências iniciais** de seed (configuração default ao subir a plataforma).

| Plano | Cota mensal | Empresas Analisadas | Mensal (seed) | Anual (seed) | Público-alvo |
|---|---|---|---|---|---|
| **Básico** | 1 análise | **sem limite** | R$ 49,90 | R$ 399,00 | Dono(a) de MEI/ME/EPP que acompanha o próprio negócio. |
| **Pro** | **10 análises** | **sem limite** | **R$ 199,00** | **R$ 1.599,00** | Contadores e consultores que atendem várias empresas — cadência típica semestral (item 6.5 RESOLVIDO). |

**Regras do plano recorrente.**
- O Usuário pode cadastrar **quantas Empresas Analisadas quiser** em sua conta, em qualquer plano. O controle de uso é exercido exclusivamente pela **cota mensal de análises** — não há cobrança por empresa adicional cadastrada. Empresas sem análises ativas apenas ocupam um cadastro leve no histórico.
- A cota mensal é renovada no dia de aniversário da assinatura. Cada análise consome 1 unidade da cota (ou 1 crédito avulso, conforme §2.2), independentemente de em qual Empresa Analisada ela foi rodada.
- Análises não utilizadas no mês **não acumulam** para o mês seguinte. Política parametrizável via `Setting` no banco (campo `analise_cota_acumula`, default `false`).
- Quando o Usuário esgota a cota, pode comprar pacotes de créditos avulsos sem trocar de plano.
- O upgrade de Básico para Pro é imediato e proporcional (cobra a diferença no ciclo vigente); o downgrade só tem efeito no próximo ciclo.
- Ao final do ciclo, a cobrança é automática no cartão cadastrado ou em Pix (se anual).

### 2.2 Créditos Avulsos

Pacotes de créditos são comprados à vista (Pix ou cartão) e consumidos à medida que o usuário executa análises.

> **Parametrização.** Tamanho do pacote, preço e validade ficam armazenados em uma **tabela `CreditPackage`** do banco de dados (ver `arquitetura-tecnica.md` §4.1). Os valores abaixo são as **referências iniciais** de seed.

| Pacote | Qtd. créditos | Preço (seed) | Preço unitário equivalente | Validade (seed) |
|---|---|---|---|---|
| Mini | 1 | R$ 19,90 | R$ 19,90 | 12 meses |
| Plus | 5 | R$ 79,90 | R$ 15,98 | 12 meses |
| Max | 10 | R$ 129,90 | R$ 12,99 | 12 meses |

**Regras dos créditos.**
- O **preço unitário do crédito avulso** deve ser **superior** ao preço unitário equivalente da análise inclusa no plano Pro (R$ 19,90 com cota 10), preservando o incentivo à assinatura. Regra validada pelo administrador antes de aceitar nova configuração de preço.
- Créditos comprados têm validade de **12 meses** a partir da data da compra (parametrizável por pacote).
- A cota mensal da assinatura é consumida **antes** dos créditos; créditos só são debitados quando a cota se esgota no mês.
- Créditos não são reembolsáveis nem transferíveis entre contas.
- Um não-assinante pode comprar pacotes de créditos sem aderir a um plano recorrente, mas perde benefícios como histórico estendido (se aplicável no roadmap).

### 2.3 Trial Gratuito

`[DECIDIR]` — **A oferta de trial gratuito é uma decisão aberta para o MVP.** A versão v1 desta especificação previa o trial; a revisão de 22/04/2026 levantou a dúvida "acesso gratuito desvaloriza o produto?". A decisão final será tomada imediatamente antes do go-live do MVP, com três alternativas em aberto: **(a) ativar** o trial conforme especificado abaixo; **(b) desativar** a oferta gratuita (somente assinatura paga ou compra de créditos); ou **(c) ativar em formato reduzido** (por exemplo, hotsite com simulação guiada sem gerar o PDF completo). A especificação da Seção 2.3 permanece **pronta para ativação** — se a decisão for (b), o fluxo simplesmente não é exposto na UX, preservando o código. Ver Seção 6, item 6.10.

**Regras do trial (se ativado).**
- Novo usuário que se cadastra tem direito a **7 dias de trial** com **1 análise inclusa**.
- Não exige cadastro de meio de pagamento.
- Ao final do 7º dia, o acesso às funções pagas é bloqueado até o usuário assinar ou comprar créditos. O acesso ao histórico do diagnóstico realizado continua disponível durante a janela de retenção (ver seção 4.8).
- O trial pode ser ativado uma única vez por CPF/CNPJ.
- Abuso (múltiplos cadastros com variações de e-mail) será mitigado por verificação do documento (CPF/CNPJ já utilizado → bloqueia novo trial) e por verificação por IP (mitigação do risco R8 identificado no parecer CLAUDE).

### 2.4 Políticas Comerciais

**Cancelamento.** O assinante pode cancelar a renovação automática a qualquer momento no painel. O acesso permanece ativo até o fim do ciclo pago. Não há reembolso proporcional do valor já pago.

**Arrependimento.** Dentro dos **7 dias corridos** após a contratação, o assinante pode solicitar reembolso integral, conforme o CDC, desde que **não tenha executado nenhuma análise**.

**Retenção.** Após cancelamento, a conta continua existindo e o histórico de diagnósticos permanece dentro da janela de 12 meses rolantes (seção 4.8). Reativação é possível a qualquer momento.

**Reajuste.** Reajustes de preço são comunicados com antecedência mínima de **30 dias** por e-mail e aplicam-se apenas a novos ciclos após a data de vigência.

**Nota fiscal.** Na aquisição de qualquer plano ou pacote, é emitida nota fiscal pela EB Parcerias Ltda ao assinante. Detalhe do fluxo de emissão é item de back-office (fora da v1 funcional).

---

## 3. Jornada do Usuário

### 3.1 Visão Macro

O fluxo da ponta à ponta, do primeiro contato à primeira análise:

```
Hotsite (descoberta) → Cadastro do Usuário (CPF + e-mail + senha + telefone) →
Aceite LGPD e Termo de Adesão → Cadastro da primeira Empresa Analisada
(CNPJ via RFB ou CPF) → Trial (7 dias, 1 análise) OU Assinatura imediata
(Pix/Cartão) → Seleção da Empresa Analisada e Preenchimento do Quiz →
Cálculo e geração do Relatório → Visualização com semáforo e recomendações →
Exportação PDF / Impressão → (opcional) Solicitar análise de captação para a
Empresa Analisada → Histórico acessível para novos diagnósticos, agrupado por
Empresa Analisada
```

No plano Pro, após o cadastro da primeira Empresa Analisada, o Usuário pode adicionar quantas empresas precisar ao longo do tempo, sem trocar de plano.

### 3.2 Aquisição (Hotsite)

O hotsite serve de porta de entrada, explicando o produto, planos e benefícios. Páginas essenciais da v1: **Home** (proposta de valor, prova social futura, call-to-action para trial), **Como funciona** (passo a passo ilustrado), **Planos e preços** (tabela comparativa Básico × Pro + pacotes de créditos), **Para quem é** (MEI, ME, contadores), **FAQ**, **Contato**, **Blog** (estrutura mínima para conteúdo SEO futuro). Requisitos transversais: SEO otimizado (title, meta, schema.org BusinessService), responsivo, rastreamento via Google Analytics / Meta Pixel, LGPD (banner de cookies com consentimento granular).

### 3.3 Cadastro e Onboarding

O cadastro tem **dois momentos** distintos: o cadastro do **Usuário** (pessoa que loga) e o cadastro da primeira **Empresa Analisada** (entidade cujos dados serão analisados).

**Passo 1 — Cadastro do Usuário.** O Usuário clica em "Começar grátis" ou "Assinar" e é direcionado ao formulário de cadastro pessoal. Solicita: **CPF**, nome completo, e-mail, senha (com confirmação e regra de força), telefone WhatsApp, aceites (Termo de Adesão obrigatório, LGPD obrigatório, opt-in de marketing opcional). O e-mail recebe link de confirmação — **obrigatório para ativar a conta**.

**Passo 2 — Cadastro da primeira Empresa Analisada.** Após confirmar o e-mail, o Usuário cai numa tela de boas-vindas que oferece adicionar a primeira Empresa Analisada. Solicita: tipo do documento (**CNPJ** ou **CPF da Empresa Analisada**, no caso de autônomo não formalizado).

- Para CNPJ, o sistema consulta a API da RFB e pré-preenche Razão Social, Nome Fantasia, Data de Fundação, CNAE Principal, Município, UF e Situação Cadastral; o Usuário apenas confirma. Falha na API não impede o cadastro (preenchimento manual com aviso).
- Para CPF de autônomo, o Usuário informa nome, atividade econômica e demais campos manualmente.
- **Observação importante:** o CPF cadastrado nesta etapa é o da **Empresa Analisada (autônomo)** e **não precisa coincidir** com o CPF do Usuário do Passo 1. Joana, por exemplo, pode logar com o próprio CPF e cadastrar a empresa dela como CNPJ.
- Para Usuários do plano Pro (contadores e consultores), o passo de cadastro da primeira Empresa Analisada pode ser pulado e retomado depois — o Pro cadastra empresas conforme adquire clientes para atender.

Após esses dois passos, o Usuário vê o painel principal: lista de Empresas Analisadas (no Básico, geralmente uma; no Pro, várias) com botão "Adicionar empresa" e, em cada empresa, ação "Iniciar diagnóstico". Um tutorial opcional (modal de 3 slides) apresenta o conceito dos indicadores.

### 3.4 Assinatura e Pagamento

O usuário escolhe um caminho: **ativar trial de 7 dias** (sem pagamento) ou **assinar um plano** (Básico/Pro, mensal/anual) ou **comprar pacote de créditos**. Para assinaturas recorrentes, o cartão de crédito é obrigatório e é tokenizado pelo gateway. Para Pix (anual ou créditos), o sistema gera QR code com expiração de **30 minutos** (parametrizável via `Setting.pix_expiracao_minutos`); após confirmação do pagamento pelo gateway, a carteira é creditada automaticamente.

A confirmação do pagamento libera imediatamente o acesso. Se for Pix e o usuário fechar a tela, recebe e-mail com o QR code para concluir.

### 3.5 Preenchimento do Quiz

Antes de iniciar o quiz, o Usuário **seleciona qual Empresa Analisada** vai diagnosticar (no caso Pro com várias empresas) ou inicia diretamente (no caso Básico com uma única empresa).

A tela inicial do quiz exibe, em destaque, a mensagem: "A responsabilidade pela qualidade das informações alimentadas na Entrada de Dados / Questionário é do **Usuário assinante**, esclarecido que a assertividade e análise técnica depende da coerência dos dados digitados." — conforme a planilha de Estrutura. O Usuário confirma a ciência antes de iniciar. No caso do Usuário Pro operando dados de empresas de terceiros, o aceite específico do DPA é exibido no primeiro quiz de cada nova Empresa Analisada (ver §4.2 e `requisitos-nao-funcionais-e-juridicos.md` §7.2).

O quiz é dividido em 4 passos para reduzir a carga cognitiva:
1. **Identificação do negócio** (setor de atividade, dados complementares).
2. **Fotografia patrimonial** (caixa, clientes, estoques, patrimônio, dívidas, fornecedores).
3. **Operação** (compras, vendas, prazos médios, inadimplência).
4. **Resultado e capacidade** (custos fixos, variáveis, despesas financeiras, dados de captação, sócios).

Cada campo tem ícone de ajuda (?) com a explicação textual correspondente (conforme planilha de QUIZ). Valores são sempre numéricos; a UI garante máscaras de moeda (R$) e percentual conforme o campo. Após cada passo, o usuário pode voltar e ajustar. O envio final (**Enviar**) dispara o motor de cálculo e consome 1 unidade (cota mensal ou crédito).

Antes do envio, se o usuário abandonar a tela, o quiz é salvo em rascunho e pode ser retomado.

### 3.6 Visualização do Relatório

Após o envio, o motor calcula balanço adaptado, DRE adaptada e **14 indicadores**, e produz o **Relatório de Indicadores** para a Empresa Analisada selecionada. A tela apresenta:

- Cabeçalho: logo **DEF on line**, título "Relatório de Indicadores", dados da Empresa Analisada (Razão Social, CNPJ ou CPF, Setor, Município/UF), Quiz ID, Data de Criação, Gerado em, e identificação do Usuário que executou.
- Tabela principal com colunas **Indicador**, **Valor**, **Farol** (semáforo verde/amarelo/vermelho), **Mensagem curta** (versão dez/2025).
- Em cada linha, um botão "Ver recomendação detalhada" abre expansão com a versão longa (jul/2025) correspondente ao setor e à faixa.
- Seção **Sugestões Gerais**: bloco com a lista de sugestões genéricas comuns a todos os assinantes (anexo H).
- Seção **Glossário**: bloco com as definições dos termos financeiros (anexo I).
- Ações no topo: **Salvar PDF**, **Imprimir**, **Solicitar análise de captação**, **Nova análise**, **Voltar ao histórico**.

O PDF exportado mantém a mesma estrutura, otimizado para impressão e leitura offline.

### 3.7 Renovação / Retenção

Sete dias antes do fim do ciclo (mensal ou anual), o sistema envia um e-mail lembrando o assinante. Se a renovação automática falhar (cartão negado, saldo), o sistema notifica o assinante e tenta até 3 cobranças em intervalos de 48h. Após 3 falhas, o ciclo é encerrado; o acesso às funções pagas é bloqueado, mas o histórico permanece disponível dentro da janela de retenção.

---

## 4. Especificação Funcional Detalhada

### 4.1 Cadastro e Autenticação

O cadastro contempla **duas entidades distintas**: o **Usuário** (pessoa física que loga) e a **Empresa Analisada** (entidade cujos dados serão analisados). Conforme §3.3, o cadastro ocorre em dois passos.

#### 4.1.1 Cadastro do Usuário

**Campos.**
- **CPF** — único na base; validação de formato e dígito verificador. É o identificador da pessoa física.
- Nome completo.
- E-mail — único na base; validação de formato; confirmação obrigatória por link.
- Senha — mínimo 8 caracteres, pelo menos 1 maiúscula, 1 minúscula, 1 dígito; armazenamento em hash.
- **Telefone WhatsApp** — utilizado para comunicação transacional e como canal alternativo de suporte previsto no roadmap.
- Aceites: Termo de Adesão (obrigatório), Consentimento LGPD (obrigatório), Opt-in de marketing (opcional). O **Termo de Adesão e a Política de Privacidade serão revisados e ajustados após a conclusão das definições de "Regras do Negócio" e da "Especificação Funcional"** — texto final a ser produzido por advogado especializado em LGPD e responsabilidade de plataformas de informação financeira (ver `requisitos-nao-funcionais-e-juridicos.md`).

**Regras.**
- Se o CPF já está cadastrado, o sistema orienta "Já tem conta? Fazer login" ou "Recuperar senha".
- Se o e-mail já está cadastrado com outro CPF, o sistema rejeita e orienta **contato com suporte por e-mail**.
- A conta do Usuário é criada em estado **Pendente** até a confirmação do e-mail; pendências expiram em 72h.
- O Termo de Adesão e o aviso LGPD são versionados; alterações futuras exigem novo aceite no próximo login.

#### 4.1.2 Cadastro de Empresa Analisada

**Campos.**
- **Documento da Empresa Analisada** — CNPJ (PJ) ou CPF (autônomo não formalizado). Único por Usuário (o mesmo Usuário não pode ter duas Empresas Analisadas com o mesmo documento).
- Razão Social / Nome.
- Nome Fantasia (PJ).
- CNAE Principal (PJ — enriquecido pela API da RFB quando CNPJ).
- Município / UF.
- Situação Cadastral (PJ).
- Data de Fundação (PJ) ou Data de Nascimento (autônomo PF).

**Regras.**
- Para CNPJ, o sistema consulta a API da RFB e pré-preenche os campos derivados; o Usuário confirma. Falha na API não impede o cadastro: preenchimento manual com aviso "Não foi possível consultar a Receita. Verifique os dados antes de prosseguir."
- Para CPF de autônomo, preenchimento manual de todos os campos.
- **Sem limite de quantidade** de Empresas Analisadas por Usuário, em qualquer plano (§2.1).
- Cada Empresa Analisada pertence a exatamente **um** Usuário no MVP. Convite e compartilhamento entre Usuários estão no roadmap.
- O Usuário pode editar a Empresa Analisada (razão social, fantasia, atividade) a qualquer momento; o documento (CNPJ/CPF) só pode ser corrigido em caso de erro de digitação no cadastro, mediante confirmação.
- Exclusão de uma Empresa Analisada: o Usuário pode excluir; histórico de diagnósticos da empresa é anonimizado conforme política LGPD (Empresa removida do painel, dados agregados retidos por base legal).

#### 4.1.3 Login

- Identificação por **CPF ou e-mail + senha**.
- "Esqueci minha senha" envia link de redefinição válido por 30 minutos.
- Sessão com expiração configurável `[A DEFINIR]` (sugestão: 30 dias com "manter conectado", ou 2h sem); logout automático após inatividade.
- Bloqueio temporário após 5 tentativas falhas em 10 minutos (proteção contra brute force).
- Autenticação de dois fatores — **roadmap**.

### 4.2 Aceite de Termos e LGPD

O Termo de Adesão e a Política de Privacidade (LGPD) estão disponíveis em URLs públicas e versionados. **Ambos os documentos serão revisados e ajustados após a conclusão das definições de "Regras do Negócio" e da "Especificação Funcional"**, com revisão jurídica especializada conforme detalhado em `requisitos-nao-funcionais-e-juridicos.md`.

No cadastro, os aceites são registrados com timestamp, IP, versão do termo e consentimento explícito do **Usuário** (checkbox não pré-marcado). A ausência do aceite bloqueia a criação da conta.

O Usuário pode, a qualquer momento, no painel: baixar os termos aceitos; exercer direitos LGPD (acesso, correção, portabilidade, exclusão). A exclusão dispara fluxo assíncrono de anonimização (dados identificáveis são removidos; registros agregados são mantidos apenas para auditoria, conforme base legal). A exclusão do Usuário implica anonimização também das Empresas Analisadas vinculadas a ele.

**Base legal para Usuários Pro com Empresas Analisadas de terceiros.** Quando um Usuário do plano Pro (tipicamente contador ou consultor) cadastra como Empresa Analisada uma entidade que **não é a própria** (ex.: Marcos cadastra a loja da Joana), a plataforma trata o Usuário como **controlador** dos dados dessa Empresa Analisada e a EB Parcerias como **operadora**, nos termos da LGPD. O fluxo exige **aceite específico do Usuário declarando possuir autorização da Empresa Analisada** para uso dos dados financeiros — esse aceite é solicitado no primeiro quiz de cada nova Empresa Analisada vinculada à conta Pro. O subcontrato de processamento de dados (DPA simplificado) é detalhado em `requisitos-nao-funcionais-e-juridicos.md` §7.2.

### 4.3 Carteira: Assinatura e Créditos

A **Carteira** está associada ao **Usuário** (não às Empresas Analisadas — independentemente de quantas empresas o Usuário tenha, ele tem uma única Carteira). Possui duas dimensões:

- **Assinatura ativa** (opcional): plano + ciclo + próxima renovação + cota restante no ciclo.
- **Saldo de créditos** (opcional): quantidade + data de expiração de cada lote comprado (FIFO no consumo — primeiro lote a expirar é consumido antes).

O painel do Usuário mostra: plano atual, cota restante no ciclo, saldo de créditos (com alerta se algum lote está perto de expirar), histórico de faturamento, meios de pagamento cadastrados.

**Consumo.** Ao executar uma análise para uma Empresa Analisada, o sistema: (1) registra na execução qual Empresa Analisada foi diagnosticada (para histórico e auditoria); (2) se há cota mensal restante, debita 1 da cota; (3) senão, se há créditos, debita 1 crédito do lote com expiração mais próxima; (4) senão, bloqueia a execução e orienta o Usuário a comprar créditos ou aguardar a renovação. A cota é consumida pelo Usuário independentemente de em qual Empresa Analisada a análise foi rodada.

**Compra de créditos.** Disponível no painel; processo de checkout idêntico ao da assinatura, mas não cria recorrência.

### 4.4 Questionário (Quiz)

Cada **Quiz** é uma instância vinculada a **uma Empresa Analisada específica** e ao Usuário que o executa. Para iniciar um quiz, o Usuário seleciona qual de suas Empresas Analisadas vai diagnosticar — no caso Básico com empresa única, esta seleção é implícita.

**Campos do quiz** (baseado nas abas **Questionário** e **QUIZ** da planilha de Estrutura):

| Ref | Campo | Tipo | Formato | Obrigatório | Observação |
|---|---|---|---|---|---|
| Q01 | Setor de atividade | Enum | 1=Indústria · 2=Comércio · 3=Serviços | Sim | Dropdown |
| Q02 | Recursos disponíveis | Numérico | R$ ≥ 0 | Sim | Caixa + bancos + aplicações |
| Q03 | Contas a receber | Numérico | R$ ≥ 0 | Sim | Vendas a prazo, cheques, boleto, cartão, fiado |
| Q04 | Estoque | Numérico | R$ ≥ 0 | Sim | Produtos, mercadorias, matéria-prima, insumos |
| Q05 | Patrimônio | Numérico | R$ ≥ 0 | Sim | Imóveis, equipamentos, veículos (valor venal de venda rápida) |
| Q06 | Dívidas financeiras | Numérico | R$ ≥ 0 | Sim | Empréstimos, financiamentos, dívidas não operacionais |
| Q07 | Fornecedores a pagar | Numérico | R$ ≥ 0 | Sim | Obrigações comerciais |
| Q08 | Compras (média mensal 12 meses) | Numérico | R$ ≥ 0 | Sim | Mercadorias, matéria-prima, insumos |
| Q09 | Vendas (média mensal 12 meses) | Numérico | R$ ≥ 0 | Sim | Receita operacional bruta |
| Q10 | PMC — prazo de fornecedores | Numérico | dias ≥ 0 | Sim | Prazo médio de pagamento de compras |
| Q11 | PME — giro do estoque | Numérico | dias ≥ 0 | **Se Q01 ≠ 3** (não obrigatório para Serviços) | Prazo médio de permanência no estoque |
| Q12 | PMR — prazo a clientes | Numérico | dias ≥ 0 | Sim | Prazo médio de recebimento das vendas |
| Q13 | Inadimplência | Numérico | % ≥ 0 | Sim | % médio de atraso de clientes |
| Q14 | Custos e despesas fixas | Numérico | R$ ≥ 0 | Sim | Valor médio mensal de saídas do caixa para custos fixos: folha de pagamento, contador, aluguel, condomínio, energia, água, telefone, internet, franquia, tarifas, retirada de sócios e despesas fixas |
| Q15 | Custos e despesas variáveis | Numérico | R$ ≥ 0 | Sim | Valor médio mensal de saídas do caixa para custos variáveis: fretes, comissões de venda, tributos (impostos, taxas, contribuições) e despesas variáveis |
| Q16 | Despesas financeiras | Numérico | R$ ≥ 0 | Sim | Juros pagos a bancos, antecipações **de cartões e desconto de boletos** |
| Q17 | Necessita captar recursos | Enum | 1=Sim · 2=Não | Sim | Se 1, exibe Q18 a Q23 |
| Q18 | Valor que precisa captar | Numérico | R$ ≥ 0 | Se Q17=1 | Montante desejado |
| Q19 | Endividamento total no mercado | Numérico | R$ ≥ 0 | Se Q17=1 | Bancos + particulares |
| Q20 | Venda mensal com cartão/duplicatas | Numérico | R$ ≥ 0 **e < Q09** | Se Q17=1 | Para cálculo de garantia |
| Q21 | CPF do sócio 1 | Texto | CPF | **Se Q17=1** | Validação de dígitos |
| Q22 | CPF do sócio 2 | Texto | CPF | **Se Q17=1** | — |
| Q23 | CPF do sócio 3 | Texto | CPF | **Se Q17=1** | — |

**Validações de coerência.**
- PMC, PME, PMR: valores acima de 365 dias exigem confirmação do usuário ("Tem certeza? Valor atípico").
- Vendas e compras: se compras > vendas, o sistema alerta ("Suas compras são maiores que suas vendas — é temporário ou permanente?") sem impedir o envio.
- Q20 < Q09: o sistema valida que Q20 (venda mensal com cartão/duplicatas) não supera Q09 (venda mensal total).
- Inadimplência > 100% não é aceita.
- Todos os valores monetários têm máscara em reais (R$ 1.234,56).
- **Validações cruzadas DRE × Balanço** `[DECIDIR]` — regras adicionais em discussão (ver Seção 6, item 6.6), por exemplo Q16 (despesas financeiras anualizadas) × Q06 (dívidas financeiras): Q16 ≫ Q06 é improvável em contexto legal.

**Tooltip / box explicativo por campo.** Cada campo do quiz deve exibir um **ícone de ajuda (?) com box explicativo do conceito do indicador associado** (conceito resumido, exibido sem sair da página — inline). O conteúdo dos boxes é derivado da aba `QUIZ` da planilha de Estrutura e será consolidado no Anexo A. Finalidade: reduzir abandono e melhorar qualidade da entrada.

**Rascunho.** O quiz é salvo automaticamente a cada passo completo. O usuário pode retomar de onde parou no próximo login. O rascunho não consome cota nem crédito; o consumo só ocorre no "Enviar" final. **Expiração do rascunho** `[DECIDIR]` — proposta: 90 dias, com alerta ao retomar ("Seus dados podem estar desatualizados — revise antes de enviar"). Ver Seção 6, item 6.4.

**Clonagem.** Na criação de um novo quiz **para a mesma Empresa Analisada**, o Usuário escolhe entre **Começar do zero** ou **Usar último como base** (pré-preenchendo todos os campos com o último diagnóstico daquela empresa, permitindo ajustes pontuais). Quizzes de Empresas Analisadas diferentes não são clonáveis entre si.

### 4.5 Motor de Cálculo

O motor transforma **16 das 23 respostas** do quiz (as 7 restantes — Q17 flag, Q18–Q20 de captação e Q21–Q23 de CPFs dos sócios — alimentam a seção de captação e não entram no cálculo dos indicadores) em um balanço adaptado, uma DRE adaptada e **14 indicadores**. Todas as fórmulas estão descritas em detalhe nos Anexos B, C e D.

**Premissas de cálculo.**
- **Anualização.** `[VALIDADO]` As respostas Q08 (compras médias mensais), Q09 (vendas médias mensais), Q14 (custos e despesas fixas), Q15 (custos e despesas variáveis) e Q16 (despesas financeiras) são coletadas como **média mensal dos últimos 12 meses** e **multiplicadas por 12** para obtenção dos valores anualizados usados na DRE adaptada e nos indicadores de margem, giro e prazos médios. A regra é explícita, fixa e visível ao usuário no cabeçalho do relatório (rodapé metodológico: "Vendas e compras foram anualizadas a partir da média mensal declarada").
- **Tratamento de divisão por zero.** `[VALIDADO]` Qualquer indicador cujo denominador resulte em zero ou em valor inválido é apresentado com o rótulo textual **"Indisponível"** no lugar do valor numérico, **sem farol** (nem verde, nem amarelo, nem vermelho). Nenhum texto de recomendação é exibido para o indicador nessa condição; em vez disso, é mostrada uma nota curta: *"Não foi possível calcular este indicador com as informações fornecidas."*
- **Patrimônio Líquido (PL).** `[VALIDADO]` Na ausência de separação entre Passivo Circulante e Passivo Não Circulante no quiz, o PL é calculado como `Ativo Total − Passivo Circulante`. A EBC **decidiu manter esta simplificação** em 22/04/2026 em face do perfil do público-alvo e da ergonomia do quiz. A crítica técnica do parecer CLAUDE (pg. 3) — distorção em empresas com dívida de longo prazo relevante — é reconhecida, e a mitigação prevista é um **aviso no rodapé do relatório quando Q06 (dívidas financeiras) superar limiar a ser fixado** (ver Seção 6, item 6.7), informando que indicadores derivados do PL (Dívida Líquida/EBITDA, Fontes de Recursos) podem estar subestimados pela metodologia.
- **NCG absoluto — sem farol, indicador informativo.** `[VALIDADO]` (fechado em 17/05/2026 pelo item 6.3 da Seção 6.) O NCG absoluto (indicador #9, em R$) é **sempre exibido sem farol visual** — o produto mostra o valor numérico e uma mensagem curta da matriz DEZ/2025 (página 33) correspondente à faixa em que o NCG cai. As **três faixas semânticas** são:
  - **NCG ≤ 0** (negativo): texto sobre folga operacional / capital de giro positivo.
  - **NCG positivo moderado:** `0 < NCG ≤ 10% das Vendas anualizadas` (Q09 × 12) — texto sobre patamar gerenciável e atenção a prazos médios.
  - **NCG positivo alto crescente:** `NCG > 10% das Vendas anualizadas` — texto sobre pressão sobre o caixa e necessidade de revisão de PMC/PME/PMR ou captação estruturada.
  - O critério "% de Vendas anualizadas" escala automaticamente com o porte da empresa e mantém coerência matemática com o indicador irmão **NCG/Vendas** (#10), que **sim** recebe farol completo (verde / amarelo / vermelho).
  - Os 3 textos correspondentes à matriz DEZ/2025 página 33 ficam pendentes apenas de extração operacional pela EBC para a planilha RECOMENDAÇÕES — **não bloqueia o kickoff de código** (motor pode ser implementado com placeholders, textos finais entram via seed da tabela `Recommendation`).

**Princípios operacionais.**
- Totalmente determinístico: mesma entrada produz sempre a mesma saída.
- Valores extremos (ex.: margens > 100% por coerência inconsistente) são apresentados e destacados com aviso; não são "normalizados" silenciosamente, para preservar transparência.
- Parâmetros de referência (faixas) são versionados; o diagnóstico guardado registra a versão do motor e da matriz de recomendações usadas, para reprodutibilidade histórica.

### 4.6 Motor de Recomendações

Para cada indicador, o motor classifica o valor calculado em uma das faixas (verde/amarelo/vermelho), combina com o setor da empresa e busca na matriz de recomendações o texto correspondente. O relatório v1 apresenta a **versão resumida (dez/2025)** por padrão e permite ao usuário expandir para a **versão detalhada (jul/2025)** sob demanda.

Além das recomendações específicas por indicador, o relatório inclui uma seção fixa com as **sugestões genéricas** (anexo H) e o **glossário** (anexo I), idênticos para todos os assinantes.

**Evolução e versionamento.** A matriz de recomendações é um componente versionado. Atualizações futuras (novos textos, novas faixas, correções) geram uma nova versão, e os diagnósticos já gerados mantêm o texto da versão vigente na data da geração.

### 4.7 Relatório de Diagnóstico

**Componentes do relatório (tela + PDF).**

1. **Cabeçalho**: logo **DEF on line**, título "Relatório de Diagnóstico Econômico-Financeiro".
2. **Metadados**: identificam **a Empresa Analisada** (Razão Social ou Nome, Nome Fantasia, CNPJ/CPF, Setor, UF/Município) e **o contexto da execução** (Data do Diagnóstico — envio do quiz, Data de Geração — do PDF, ID do Quiz, identificação do Usuário que executou).
3. **Resumo executivo**: bloco de 4 a 5 linhas em linguagem amigável e construtiva, com **algoritmo determinístico** descrito em §4.7.1. Sinaliza o estado geral do negócio com um veredito ("saudável", "precisa de atenção" ou "em alerta") e destaca os indicadores mais relevantes do diagnóstico.
4. **Tabela de indicadores**: colunas Indicador, Valor, Farol, Mensagem curta (texto resumido da matriz dez/2025). Cada linha é expansível para "Ver recomendação detalhada" (texto completo da matriz jul/2025).
5. **Informações de captação** (se o usuário indicou Q17=1): apresenta os 4 parâmetros calculados (metade do endividamento, 3× vendas com cartão, 1/3 do patrimônio livre, valor declarado) e o menor deles como referência, com o botão "Solicitar análise primária" (seção 4.9).
6. **Sugestões gerais**: lista das 50+ sugestões genéricas (anexo H).
7. **Glossário**: definições dos termos utilizados no relatório (anexo I).
8. **Rodapé**: aviso legal curto ("Este diagnóstico é gerado automaticamente com base nas informações declaradas pelo usuário..."), versão da matriz de recomendações, link para dúvidas (`eb@ebparcerias.com`). **Aviso condicional de PL simplificado** é exibido quando Q06 exceder o limiar definido na Seção 6, item 6.7.

**Exportação.** O PDF é gerado sob demanda (clique em "Salvar PDF") ou imediatamente na geração do diagnóstico (armazenamento assíncrono para download). Formato A4, fonte legível, semáforo em cor mantido no PDF. Impressão é um atalho que abre a janela de impressão do navegador sobre a versão web.

**Regras do semáforo (por indicador).** Ver anexo E — cada indicador tem 3 faixas por setor, e a cor é atribuída automaticamente conforme o valor calculado caia em cada faixa.

#### 4.7.1 Algoritmo de geração do Resumo Executivo

Algoritmo determinístico — mesma entrada produz sempre a mesma saída. Fechado em 17/05/2026 (ver §6.2 RESOLVIDO).

**Entrada.** Lista dos 14 indicadores calculados pelo motor (Anexo D), cada um com: valor (ou `"Indisponível"`), farol (`verde` / `amarelo` / `vermelho` / `nenhum` quando indisponível **ou quando o indicador é informativo sem farol**), faixa do setor (Anexo E) e mensagem curta da matriz DEZ/2025 (Anexo F).

> **Observação.** O **indicador #9 (NCG absoluto)** é classificado como informativo (sem farol — ver §4.5 e §6.3 RESOLVIDO). Para este algoritmo, ele é tratado como `farol = nenhum`, ficando **sempre fora** da contagem do veredito e da seleção de destaques. A interpretação semântica do capital de giro no Resumo Executivo se dá via o indicador irmão **#10 NCG/Vendas**, que tem farol completo.

**Saída.** Bloco de **4 a 5 linhas** (até ~400 caracteres no total) composto por: 1 linha de **veredito**, até 3 **destaques** e 1 linha **de fechamento**.

**Passo 1 — Classificação por contagem proporcional (apenas entre indicadores válidos).**

Sejam `V` = quantidade de vermelhos, `A` = quantidade de amarelos, `Vd` = quantidade de verdes, `I` = quantidade de "Indisponível", `N = V + A + Vd` = total de indicadores válidos (válidos = não-indisponíveis).

| Condição | Veredito |
|---|---|
| `I / 14 ≥ 0,70` (caso extremo de dados insuficientes) | **Mensagem fixa** (passo 5 abaixo) |
| `N > 0` e `V / N ≥ 0,30` | **Em alerta** |
| `N > 0` e (`V ≥ 1` ou `A / N ≥ 0,50`) | **Precisa de atenção** |
| `N > 0` e `V = 0` e `A / N < 0,50` | **Saudável** |

**Passo 2 — Seleção de destaques (até 3 itens).**

- **Até 2 destaques negativos**, na ordem:
  1. Indicadores em vermelho, ordenados por **severidade decrescente**. *Severidade* = `|valor − fronteira_amarela| / amplitude_faixa_vermelha`. Em caso de empate: ordem do Anexo D (1 a 14).
  2. Se não houver vermelhos suficientes para completar 2 slots, complementar com indicadores em amarelo, mesma regra de severidade.
- **Até 1 destaque positivo**: o indicador em verde com **maior distância (sentido bom)** à fronteira amarela. Omitir se não houver verde.
- Indicadores com farol `nenhum` (Indisponível) são ignorados e nunca aparecem como destaque.

**Passo 3 — Construção do texto de cada destaque.**

Para cada destaque selecionado, usar a **mensagem curta da matriz DEZ/2025** (Anexo F) **truncada para ~80 caracteres**, mantendo a primeira frase semântica. Prefixar com o nome do indicador e dois-pontos.

Para o destaque positivo, prefixar com *"Por outro lado, "* — sinaliza contraste de tom.

**Passo 4 — Composição do bloco final.**

```
Linha 1: [Veredito]
Linha 2: [Destaque negativo 1, se existir]
Linha 3: [Destaque negativo 2, se existir]
Linha 4: [Destaque positivo, se existir]
Linha 5: Veja a tabela abaixo para análise detalhada e recomendações específicas.
```

Textos do veredito (linha 1):
- **Saudável:** *"Sua empresa apresenta indicadores saudáveis no período avaliado."*
- **Precisa de atenção:** *"Sua empresa apresenta pontos de atenção que merecem acompanhamento."*
- **Em alerta:** *"Sua empresa apresenta indicadores em estado de alerta que demandam ação."*

Linha 5 é fixa: *"Veja a tabela abaixo para análise detalhada e recomendações específicas."*

**Passo 5 — Caso extremo de dados insuficientes.**

Se `I / 14 ≥ 0,70` (70% ou mais dos indicadores estão "Indisponíveis"), o algoritmo **não** gera o resumo padrão e exibe uma **mensagem fixa única**:

> *"Não foi possível calcular indicadores suficientes para um resumo executivo. Revise os dados informados ou consulte a tabela abaixo."*

**Passo 6 — Casos extremos opostos.**

- **Todos os 14 indicadores em verde** (`V = 0`, `A = 0`, `Vd = 14`, `I = 0`): veredito "Saudável" + linha única "Todos os indicadores avaliados estão em patamar saudável. Continue acompanhando." + linha 5 fixa.
- **Todos os 14 indicadores em vermelho** (`V = 14`, `A = 0`, `Vd = 0`, `I = 0`): veredito "Em alerta" + 2 destaques mais severos (sem destaque positivo) + linha 5 fixa.

**Princípios operacionais.**

- **Sem pesos por indicador no MVP** — cada indicador vale 1 voto na contagem. Pesos podem entrar em evolução futura junto com medianas setoriais (`roadmap-pos-v1.md` §2.1).
- **Tom único** para todas as personas (Joana, Roberto, Marcos).
- **Linguagem em 3ª pessoa**, construtiva, sem jargão financeiro pesado.
- **Determinismo** garantido pela ordem fixa de desempate (Anexo D) — mesmo input gera mesmo output, essencial para reprodutibilidade histórica e testes automatizados.
- **Testes:** no mínimo 10 cenários cobrindo (saudável total, alerta total, precisa atenção típico, indisponível ≥70%, empate de severidade, sem verdes, etc.) — alinha-se à exigência geral de 10 casos por fórmula do motor (`requisitos-nao-funcionais-e-juridicos.md` §9.2).

### 4.8 Histórico e Comparativo Temporal

O painel do Usuário lista suas **Empresas Analisadas**. Ao selecionar uma empresa, o Usuário vê o histórico de diagnósticos daquela empresa nos últimos 12 meses rolantes, em ordem cronológica reversa. Cada registro mostra: data do diagnóstico, Quiz ID, resumo dos 3 indicadores principais (Margem EBITDA, Dívida Líquida/EBITDA, Ciclo Financeiro), farol agregado, link para o relatório completo.

Para Usuários Pro com várias Empresas Analisadas, o painel inicial mostra todas as empresas com o **farol agregado do último diagnóstico de cada uma**, permitindo identificar visualmente quais carteiras precisam de atenção. Filtros: setor, status do último farol, data do último diagnóstico.

**Comparativo temporal.** Ao abrir um relatório, o Usuário pode escolher "Comparar com..." e selecionar um diagnóstico anterior **da mesma Empresa Analisada**. O sistema exibe cada indicador lado a lado, com a variação percentual e uma seta de tendência (melhorou / piorou / estável). Comparação entre diagnósticos de Empresas Analisadas diferentes não faz sentido econômico e não é permitida.

**Retenção.** Diagnósticos com mais de 12 meses são automaticamente excluídos. Sete dias antes da exclusão, o Usuário recebe aviso por e-mail com a oportunidade de exportar o PDF. Essa política é comunicada na Política de Privacidade e no Termo de Adesão.

**Medianas setoriais (futuro).** A partir do banco de dados de diagnósticos realizados, está previsto para o pós-v1 um módulo de **medianas setoriais** (por segmento, UF, porte etc.) que permitirá ao assinante comparar seus indicadores à mediana anonimizada de pares do mesmo perfil. Detalhe em `roadmap-pos-v1.md`.

### 4.9 Solicitação de Análise de Captação

Disponível apenas quando o Usuário marcou Q17=1 no quiz de uma Empresa Analisada. Ao clicar em "Solicitar análise primária de viabilidade", abre-se um formulário com os campos pré-preenchidos com os dados da Empresa Analisada e do quiz mais: finalidade do recurso (campo texto livre), prazo desejado (meses), garantias disponíveis (checkboxes: imóvel, veículo, recebíveis, aval, sem garantia), observações livres, contato preferencial (e-mail ou telefone do Usuário, com DDD). Ao enviar, o sistema grava o pedido vinculado à Empresa Analisada e ao Usuário, confirma ("Recebemos seu pedido. A EB Parcerias retornará em até **5 dias úteis**") e notifica a EB Parcerias por e-mail.

A v1 **não calcula automaticamente** a viabilidade — a análise é feita manualmente pela equipe EB Parcerias conforme o **Fluxo Operacional para Captação de Recursos EB** (documento interno EB Parcerias, referência externa). O cálculo automático fica no roadmap (usando as fórmulas da aba DEF, linhas 109–114). Ver `roadmap-pos-v1.md` para detalhes.

### 4.10 Hotsite e Páginas Públicas

Páginas essenciais da v1:
- **Home** — headline, subheadline, CTA para trial, prova social (placeholders para depoimentos), 3 pilares de valor, footer.
- **Como funciona** — passo a passo ilustrado (4 passos do quiz + relatório), vídeo curto `[A DEFINIR]`.
- **Planos e preços** — tabela comparativa Básico × Pro (mensal e anual) + pacotes de créditos + destaque do trial.
- **Para quem é** — persona-alvo explicada.
- **FAQ público** — perguntas sobre produto, cobrança, segurança, LGPD.
- **Contato** — formulário + e-mail `eb@ebparcerias.com`.
- **Blog** — estrutura inicial vazia, pronta para conteúdo SEO futuro.
- **Termos** — Termo de Adesão, Política de Privacidade, Política de Cookies.

Integrações essenciais do hotsite: Google Analytics, Meta Pixel (para remarketing), banner LGPD com consentimento granular (funcional, analítico, marketing).

### 4.11 Central de Ajuda (FAQ)

Área pós-login (e também acessível pelo hotsite, em versão pública reduzida) com perguntas organizadas por temas:
- Produto: "O que é o Diagnóstico?", "Quanto tempo leva?", "Preciso ter contador?", "Meus dados ficam seguros?".
- Assinatura e pagamento: "Como cancelar?", "Como trocar de plano?", "Como comprar créditos?", "Meus créditos expiram?".
- Quiz: "O que significa cada campo?", "Posso salvar e retomar depois?".
- Relatório: "Como interpretar o farol?", "Posso refazer se errei um campo?".

Cada item é editável pelo administrador (CMS leve). Inclui campo de "Esta resposta ajudou? Sim/Não" para métrica.

### 4.12 Notificações e E-mails

E-mails transacionais obrigatórios da v1:

| Gatilho | Destinatário | Conteúdo |
|---|---|---|
| Cadastro | Novo usuário | Link de confirmação de e-mail |
| Confirmação de cadastro | Novo usuário | Boas-vindas + link para iniciar quiz |
| Redefinição de senha | Usuário | Link válido 30 min |
| Confirmação de pagamento | Assinante | Recibo + nota fiscal (quando aplicável) |
| Falha na cobrança recorrente | Assinante | Aviso + link para atualizar meio de pagamento |
| Próxima renovação (D-7) | Assinante | Lembrete do valor e data |
| Fim do trial (D-2 e D-0) | Trial | Convite para assinar |
| Análise concluída | Assinante | Link para o relatório |
| Solicitação de captação recebida | EB Parcerias + assinante | Confirmação com dados do pedido |
| Expiração próxima de diagnóstico (D-7) | Assinante | Aviso para exportar PDF |
| Aviso LGPD — nova versão de termos | Todos | Aviso de nova versão + pedido de novo aceite |

Os templates são versionados, enviam em texto e HTML, e incluem links de rastreamento (UTM) quando aplicável para marketing. Opção de opt-out em cada e-mail de marketing (não transacional).

---

## 5. Anexos

Os anexos são de dois tipos: **anexos inline** (B, C, D, E, H, J), cujo conteúdo está abaixo porque é curto ou indissociável da leitura do documento principal; e **anexos externos** (A, F, G, I), arquivos próprios em `anexos/` com ciclo de versionamento independente.

| Anexo | Conteúdo | Tipo | Link |
|---|---|---|---|
| A | Lista de campos do Quiz (23 campos) | Externo | [`anexos/anexo-A-campos-quiz.md`](anexos/anexo-A-campos-quiz.md) |
| B | Fórmulas do Balanço Adaptado | Inline | §5 abaixo |
| C | Fórmulas da DRE Adaptada | Inline | §5 abaixo |
| D | Fórmulas dos **14 Indicadores** | Inline | §5 abaixo |
| E | Faixas de Classificação e Semáforo | Inline | §5 abaixo |
| F | Matriz de Recomendações (dez/2025 — vigente) | Externo | [`anexos/anexo-F-matriz-recomendacoes-dez2025.md`](anexos/anexo-F-matriz-recomendacoes-dez2025.md) |
| G | Matriz de Recomendações (jul/2025 — detalhada) | Externo | [`anexos/anexo-G-matriz-recomendacoes-jul2025.md`](anexos/anexo-G-matriz-recomendacoes-jul2025.md) |
| H | Sugestões Genéricas (comum a todos os relatórios) | Inline | §5 abaixo |
| I | Glossário Financeiro | Externo | [`anexos/anexo-I-glossario.md`](anexos/anexo-I-glossario.md) |
| J | Roadmap Pós-v1 (referência) | Externo | [`roadmap-pos-v1.md`](roadmap-pos-v1.md) |

### Anexo A — Lista completa dos campos do Quiz

**Arquivo separado:** [`anexos/anexo-A-campos-quiz.md`](anexos/anexo-A-campos-quiz.md)
**Resumo:** referência canônica dos 23 campos do questionário com tipo, formato, obrigatoriedade, regras de visibilidade condicional, regras de coerência e premissas de anualização (× 12) para os campos mensais.
**Referência cruzada:** seção 4.4 desta especificação traz a mesma tabela em formato resumido para leitura linear do documento.

### Anexo B — Fórmulas do Balanço Adaptado

Baseado na aba **DEF** (linhas 14–30) da planilha de Estrutura.

| Conta | Símbolo | Fórmula |
|---|---|---|
| Disponibilidades | — | Q02 (Recursos disponíveis) |
| Ativo Circulante Financeiro | ACF | Disponibilidades |
| Clientes | — | Q03 (Contas a receber) |
| Estoques | — | Q04 |
| Ativo Circulante Cíclico | ACC | Clientes + Estoques |
| Ativo Circulante | AC | ACF + ACC |
| Imobilizado | — | Q05 (Patrimônio) |
| Ativo Não Circulante | ANC | Imobilizado |
| **Ativo Total** | — | AC + ANC |
| Empréstimos / Financiamentos | — | Q06 (Dívidas financeiras) |
| Passivo Circulante Financeiro | PCF | Empréstimos |
| Fornecedores | — | Q07 |
| Passivo Circulante Cíclico | PCC | Fornecedores |
| Passivo Circulante | PC | PCF + PCC |
| **Patrimônio Líquido** | PL | Ativo Total − PC |
| **Passivo Total** | — | PC + PL |

*Nota:* este é um balanço **adaptado e simplificado**, suficiente para os indicadores da v1. Não corresponde a um balanço contábil formal.

### Anexo C — Fórmulas da DRE Adaptada

Baseado na aba **DEF** (linhas 31–38).

| Conta | Símbolo | Fórmula |
|---|---|---|
| Vendas (ROB) | — | Q09 × 12 |
| Compras (CPV / CMV / CSV) | — | Q08 × 12 |
| Lucro Bruto (MB) | LB | Vendas − Compras |
| Despesas fixas (ano) | — | Q14 × 12 |
| Despesas variáveis (ano) | — | Q15 × 12 |
| **EBITDA** (Lucro Operacional) | — | LB − Despesas fixas − Despesas variáveis |
| Despesas financeiras (ano) | — | Q16 × 12 |
| **Lucro Operacional Líquido (MOL)** | LOL | EBITDA − Despesas financeiras |

### Anexo D — Fórmulas dos 14 Indicadores

Baseado na aba **DEF** (linhas 41–50) e consolidação com a matriz de recomendações DEZ/2025 (Anexo F).

| # | Indicador | Fórmula | Unidade | "Quanto..." |
|---|---|---|---|---|
| 1 | Margem Bruta | Lucro Bruto / Vendas × 100 | % | Maior = melhor |
| 2 | Margem EBITDA | EBITDA / Vendas × 100 | % | Maior = melhor |
| 3 | Margem Líquida | LOL / Vendas × 100 | % | Maior = melhor |
| 4 | Dívida Líquida / EBITDA | (PCF − ACF) / EBITDA | × | Menor = melhor |
| 5 | Desp. Financeiras / EBITDA | Despesas financeiras / EBITDA × 100 | % | Menor = melhor |
| 6 | Fontes de Recursos | PC / PL | — | Menor = melhor |
| 7 | Giro do Ativo | Vendas / Ativo Total | × | Maior = melhor |
| 8 | Ciclo Financeiro | PME + PMR − PMC | dias | Menor = melhor |
| 9 | Necessidade de Capital de Giro (NCG) — absoluto | Estoques + Clientes − Fornecedores | R$ | Indicador **informativo** (sem farol — ver §4.5). Interpretação por farol via #10 NCG/Vendas. |
| 10 | NCG / Vendas | (ACC − PCC) / Vendas | — | Menor = melhor |
| 11 | PMC — prazo de fornecedores | Declarado no quiz (Q10) | dias | Contextual — maior = melhor (dentro de limites) |
| 12 | PME — prazo de estoque | Declarado no quiz (Q11) | dias | Menor = melhor — *não se aplica a Serviços (Q01=3)* |
| 13 | PMR — prazo a clientes | Declarado no quiz (Q12) | dias | Menor = melhor |
| 14 | Inadimplência | Declarada no quiz (Q13) | % | Menor = melhor |

*Nota:* conforme a premissa de cálculo definida na seção 4.5, qualquer indicador com denominador igual a zero (ou inválido) é apresentado como **"Indisponível"**, sem farol e sem texto de recomendação associado. Os valores mensais declarados no quiz (Q08, Q09, Q14, Q15, Q16) são **anualizados por multiplicação × 12** antes de alimentar as fórmulas acima. O indicador **#12 PME** tem coleta obrigatória dispensada quando Q01=3 (Serviços) e não exibe farol nem recomendação para esse setor.

### Anexo E — Faixas de Classificação e Semáforo (por setor)

Sintetizado da matriz de recomendações dez/2025. Cada indicador tem 3 faixas por setor. Cor do farol:
- **Verde** = faixa desejável (superior, para indicadores "maior=melhor"; inferior, para "menor=melhor").
- **Amarelo** = faixa intermediária (atenção).
- **Vermelho** = faixa crítica.

| Indicador | Setor | Verde | Amarelo | Vermelho |
|---|---|---|---|---|
| Margem Bruta | Indústria | > 25% | 20,01–25% | ≤ 20% |
| Margem Bruta | Comércio | > 25% | 18,01–25% | ≤ 18% |
| Margem Bruta | Serviços | > 30% | 25,01–30% | ≤ 25% |
| Margem EBITDA | Indústria | > 20% | 15,01–20% | ≤ 15% |
| Margem EBITDA | Comércio | > 15% | 10,01–15% | ≤ 10% |
| Margem EBITDA | Serviços | > 30% | 20,01–30% | ≤ 20% |
| Margem Líquida | Indústria | > 15% | 8,01–15% | ≤ 8% |
| Margem Líquida | Comércio | > 12% | 6,01–12% | ≤ 6% |
| Margem Líquida | Serviços | > 20% | 10,01–20% | ≤ 10% |
| Dívida Líquida / EBITDA | Todos | ≤ 2 | 2,01–3 | > 3 |
| Desp. Financeiras / EBITDA | Indústria | ≤ 35% | 35,01–50% | > 50% |
| Desp. Financeiras / EBITDA | Comércio | ≤ 20% | 20,01–40% | > 40% |
| Desp. Financeiras / EBITDA | Serviços | ≤ 30% | 30,01–50% | > 50% |
| Fontes de Recursos | Todos | ≤ 0,5 | 0,501–1 | > 1 |
| Giro do Ativo | Indústria | > 2 | 1,01–2 | ≤ 1 |
| Giro do Ativo | Comércio | > 3 | 2,01–3 | ≤ 2 |
| Giro do Ativo | Serviços | > 3 | 2,01–3 | ≤ 2 |
| PMC | Todos | > 60 dias | 30,01–60 | ≤ 30 |
| PME | Indústria | ≤ 30 dias | 30,01–60 | > 60 |
| PME | Comércio | ≤ 30 dias | 30,01–60 | > 60 |
| PME | Serviços | — (não se aplica; Q11 não coletado quando Q01=3) | — | — |
| PMR | Todos | ≤ 30 dias | 30,01–60 | > 60 |
| Inadimplência | Todos | ≤ 3% | 3,01–5% | > 5% |
| Ciclo Financeiro | Todos | ≤ 30 dias | 30,01–60 | > 60 |
| NCG (absoluto) | Todos | **Sempre sem farol — indicador informativo.** 3 faixas semânticas com mensagem curta: (i) NCG ≤ 0 (negativo) → folga operacional; (ii) 0 < NCG ≤ 10% das Vendas anualizadas → positivo moderado; (iii) NCG > 10% das Vendas anualizadas → positivo alto crescente. Detalhe em §4.5. | — | — |
| NCG / Vendas | Todos | ≤ 0 | 0,01–10% | > 10% |

### Anexo F — Matriz de Recomendações (versão resumida dez/2025)

**Arquivo separado:** [`anexos/anexo-F-matriz-recomendacoes-dez2025.md`](anexos/anexo-F-matriz-recomendacoes-dez2025.md)
**Versão:** `dez/2025` — **vigente** para classificação do farol e exibição primária do relatório. **As faixas numéricas e os textos de recomendações da versão DEZ/2025 foram validados pela EB Parcerias** em 22/04/2026 (ver planilha RECOMENDAÇÕES). Os textos foram reduzidos em relação à versão JUL/2025 em face do **limite de 300 caracteres** por campo de recomendação no relatório.
**Cobertura:** 14 indicadores × 3 setores × 3 faixas (~126 textos únicos).
**Resumo:** textos curtos exibidos diretamente na "Tabela de indicadores" do relatório, organizados por indicador (F.1 a F.14). As faixas numéricas ali registradas também servem de referência ao Anexo E (semáforo).
**Origem:** `DEFweb.net - RECOMENDAÇÕES - ebc.2025dez10.xlsx`, aba Planilha1, linhas 3–56.

A tabela completa é **versionada no banco de dados** da plataforma — o arquivo `.md` é a representação legível para revisão humana. Atualizações geram uma nova versão; diagnósticos já emitidos mantêm o texto da versão vigente na data de geração.

### Anexo G — Matriz de Recomendações (versão detalhada jul/2025)

**Arquivo separado:** [`anexos/anexo-G-matriz-recomendacoes-jul2025.md`](anexos/anexo-G-matriz-recomendacoes-jul2025.md)
**Versão:** `jul/2025` — referência aprofundada, **não vigente** para farol (ver Anexo F).
**Cobertura:** 14 indicadores × 3 setores × 3 faixas, textos longos prescritivos.
**Resumo:** exibida sob demanda no relatório (botão "Ver recomendação detalhada") e opcionalmente incluída no PDF detalhado. Mapeada para a cor de farol definida pela matriz dez/2025 — as faixas numéricas jul/2025 divergem em vários indicadores e ficam como informação secundária.
**Origem:** `DEFweb.net - ESTRUTURA - ebc.2025jul30.xlsx`, aba DEF, linhas 54–107.

Serve como base histórica para eventuais destilações futuras e como fonte de consulta aprofundada pelo assinante.

### Anexo H — Sugestões Genéricas

Lista comum a todos os assinantes, exibida ao final de todo relatório. Derivada da aba **DEF** (linhas 117–170):

Identificar lacunas no mercado. Focar no cliente e na solução de problemas. Separar finanças da empresa das pessoais. Conscientizar-se de que problemas devem ser identificados e solucionados. Evitar concentração de fornecedores e clientes. Não manter estoque demasiado. Ter eficiência ao comprar. Reavaliar e atualizar sempre a formação de preço. Manter diferenciais competitivos. Manter controle das entradas e saídas de dinheiro. Não atirar para todos os lados. Lembrar sempre de que "custo é como unha, tem que cortar todo dia". Medir tudo ("o que não pode ser medido não pode ser gerenciado"). Concentrar-se no que sabe fazer melhor. Vender com os menores prazos para recebimento. Evitar concentração das compras em um ou poucos fornecedores. Evitar concentração das vendas em um ou poucos clientes. Utilizar técnicas adequadas à formação do preço de venda dos produtos/serviços. Fugir de empréstimos de curto prazo e juros elevados. Imobilizar somente o indispensável. Para investimentos indispensáveis, procurar obter recursos de longo prazo a juros subsidiados, mediante realização prévia de Estudo de Viabilidade Econômico-Financeira. Evitar antecipação de faturas de cartão de crédito, desconto de cheques e duplicatas. Nunca recorrer a agiotas. Buscar aperfeiçoamento de forma continuada. Aprender com a concorrência (benchmark), mas não copiar. Focar em diferenciais mercadológicos. Definir corretamente o público-alvo. Conhecer os clientes, suas necessidades e definir como atendê-los. Manter atualizada as equipes de vendas, atendimento e pós-venda. Reavaliar com frequência a qualidade dos produtos/serviços, atendimento e pós-venda. Identificar os pontos fortes do seu negócio; explorar o potencial e as oportunidades. Mapear os pontos fracos para implementar melhorias. Adaptar-se às necessidades do mercado. Conhecer o público-alvo do seu produto/serviço. Cuidar para que a localização dos seus pontos de venda esteja adequada. Manter equipe de vendas bem preparada. Cuidar do pós-venda. Cuidar da performance da equipe de colaboradores. Manter e melhorar, sempre, a qualidade dos produtos/serviços. Atuar com profissionalismo. Acompanhar o resultado do negócio. Não confundir faturamento com lucro. Não misturar "caixa do negócio" com "bolso do dono". Utilizar ferramenta informatizada que mantenha atualizada toda a movimentação financeira e a respectiva projeção de entradas e saídas de recursos. Ampliar a carteira de clientes. Aumentar a participação no mercado. Enxugar estruturas. Reduzir custos (principalmente fixos). Gerenciar estoques para manter somente o mínimo necessário (just in time). Estabelecer módica retirada para os sócios (pró-labore). Utilizar capitais de terceiros à medida em que o lucro gerado seja superior ao custo da dívida. Atentar para o fato de "quanto maiores sejam os estoques, mais recursos a empresa compromete".

### Anexo I — Glossário Financeiro

**Arquivo separado:** [`anexos/anexo-I-glossario.md`](anexos/anexo-I-glossario.md)
**Versão:** 1.0.
**Resumo:** definições curtas, em linguagem acessível, dos termos técnicos exibidos no relatório (EBITDA, NCG, Ciclo Financeiro, Giro do Ativo, IOG, etc.) acrescidas de quatro termos complementares introduzidos pela especificação v1 (Balanço Adaptado, DRE Adaptada, Farol/Semáforo, Indisponível).
**Origem:** `DEFweb.net - ESTRUTURA - ebc.2025jul30.xlsx`, aba DEF, linhas 177–191.
**Reuso:** o glossário é exibido ao final de todo relatório, disponível em consulta autônoma no painel, e pode ser reaproveitado pelo hotsite, FAQ público e comunicações por e-mail.

### Anexo J — Roadmap Pós-v1 (referência)

O roadmap foi destacado em documento próprio para facilitar manutenção, priorização e comunicação com stakeholders: consulte **[`roadmap-pos-v1.md`](roadmap-pos-v1.md)**.

Síntese dos itens cobertos (lista não exaustiva, priorização no documento referenciado):

1. Back-office EB Parcerias (gestão financeira, CMS, dashboards operacionais).
2. Portais de Parceiros Patrocinadores (Sebrae, BB, CDL, BNB, Federações) com geração de cupons e relatórios agregados.
3. Programa de Afiliados / Parceiros Comerciais.
4. B2B2C Patrocinado.
5. Captação com cálculo automático (fórmulas aba DEF linhas 109–114).
6. **Compartilhamento N:M de Empresa Analisada entre Usuários** (convite, papéis Owner/Editor/Viewer, acesso conjunto) — caso de uso típico: dono da empresa convida o próprio contador. No MVP cada Empresa Analisada pertence a um único Usuário. O modelo de "Portfólio multi-empresa" da v1 (Usuário → N Empresas) já está no MVP — ver §6.1 RESOLVIDO.
7. **Medianas setoriais** (benchmark anônimo a partir do banco de dados).
8. **Internacionalização (i18n)**: versões em francês, espanhol e inglês.
9. **Aplicativos móveis nativos** (iOS/Android).
10. Integrações com contabilidade/ERP (Omie, Conta Azul etc.).
11. Chat/WhatsApp como canal de suporte.
12. Autenticação multifator (MFA).
13. Gamificação, metas e lembretes (ganchos de retenção).
14. Análise preditiva com IA.
15. **Módulo B2B para fornecedores de crédito** (Persona 5 — API autenticada, DPA, SLA contratual).

---

## 6. Decisões em Aberto (a resolver antes do kickoff técnico)

Esta seção consolida **todas as decisões de produto identificadas pelas revisões de 22/04/2026** (parecer CLAUDE e observações EBC) cuja resolução é pré-requisito para o início do desenvolvimento ou para o go-live do MVP. Cada item traz: descrição do problema, impacto, proposta do Product Owner e estado `[DECIDIR]`. Uma vez resolvido, o item migra para a seção funcional correspondente e é marcado `[VALIDADO]`.

### 6.1 `[RESOLVIDO]` Portfólio multi-empresa (relação Usuário → N Empresas Analisadas)

**Histórico.** A especificação v1 pressupunha que cada conta possuía um único documento (CPF/CNPJ), fundindo a noção de Usuário com a de empresa analisada. O parecer CLAUDE apontou como **omissão crítica mais grave do documento** o fato de o plano Pro (Persona 3 — Marcos) não conseguir gerir múltiplas empresas; a EBC reconheceu ("também percebi"). A v2.0 abriu o item como `[DECIDIR]`: implementar no MVP ou adiar.

**Decisão tomada (v2.1, 17/05/2026).** O Product Owner e a EBC alinharam-se na refatoração do modelo de domínio: **Usuário** e **Empresa Analisada** passam a ser entidades distintas, com relação 1:N nativa em todos os planos. Não há "portfólio multi-empresa" como funcionalidade adicional — é o modelo base.

**Onde está documentado.**
- §1.5.2 introduz formalmente as entidades Usuário e Empresa Analisada.
- §1.3.2 reescreve Persona 3 (Marcos) explicitando o caso N empresas.
- §2.1 confirma "sem limite de Empresas Analisadas por plano".
- §3.3 separa o cadastro em dois passos: Usuário primeiro, Empresa Analisada depois.
- §4.1 detalha cadastro de cada entidade.
- §4.3 esclarece que a Carteira pertence ao Usuário; consumo de cota registra a Empresa Analisada da execução.
- §4.4 vincula cada Quiz a uma Empresa Analisada específica.
- §4.7 metadados do relatório identificam Empresa Analisada e Usuário.
- §4.8 histórico é por Empresa Analisada; painel Pro lista todas as empresas.
- `arquitetura-tecnica.md` §4.1 reflete o modelo de dados separado.

**Compartilhamento N:M (vários Usuários numa mesma Empresa Analisada)** continua **fora do MVP** — registrado em §1.4.2 e `roadmap-pos-v1.md` §1.1.

### 6.2 `[RESOLVIDO]` Algoritmo de geração do Resumo Executivo

**Histórico.** A v1 e a v2.0 descreviam o Resumo Executivo na §4.7 apenas como "3–5 linhas baseadas na média dos semáforos dos indicadores" — formulação ambígua que produziria implementações divergentes. O parecer CLAUDE (pg. 3) listou como omissão importante.

**Decisão tomada (v2.4, 17/05/2026).** Algoritmo determinístico com 8 regras fixas, especificado integralmente em **§4.7.1** da especificação funcional. Resumo das decisões:

1. **Veredito por contagem proporcional**: ≥30% vermelhos → "em alerta"; ≥1 vermelho ou ≥50% amarelos → "precisa de atenção"; senão → "saudável".
2. **Até 3 destaques** no corpo (até 2 negativos + até 1 positivo).
3. **Severidade** = distância relativa à fronteira amarela.
4. **Empate** desempatado pela ordem do Anexo D.
5. **"Indisponível"** sai do denominador; ≥70% indisponíveis → mensagem fixa.
6. **Texto-fonte** reutiliza a mensagem curta da matriz DEZ/2025 truncada a ~80 caracteres.
7. **Vereditos** com 3 frases padronizadas + linha de fechamento fixa.
8. **Sem pesos por indicador** no MVP (todos valem 1 voto).

**Onde está documentado.** §4.7.1 da especificação funcional contém o algoritmo passo-a-passo, exemplos de saída e diretrizes de teste. `requisitos-nao-funcionais-e-juridicos.md` §9.2 reforça a exigência de mínimo 10 casos de teste para o algoritmo.

### 6.3 `[RESOLVIDO]` Tratamento do NCG absoluto — limiar e exibição

**Histórico.** A v1 deixou a faixa "positivo alto crescente" do NCG absoluto como `[A DEFINIR]`. A EBC remeteu à planilha DEZ/2025 (página 33), mas o limiar numérico e o modelo de exibição não foram fixados. Levantou-se ainda a duplicidade visual potencial com o indicador irmão **NCG/Vendas** (#10).

**Decisão tomada (v2.3, 17/05/2026).** Cinco regras consolidadas em §4.5 e nos Anexos D e E:

1. **Limiar:** `NCG > 10% das Vendas anualizadas (Q09 × 12)` classifica como "positivo alto crescente"; `0 < NCG ≤ 10%` é "positivo moderado"; `NCG ≤ 0` é "negativo". O critério "% de Vendas" escala com o porte da empresa e mantém coerência com o NCG/Vendas.
2. **NCG absoluto sem farol visual no MVP.** Apresenta apenas valor em R$ + mensagem curta da matriz DEZ/2025 página 33, conforme a faixa (3 textos: negativo, moderado, alto crescente).
3. **Coerência com NCG/Vendas.** O #10 mantém farol completo (verde ≤ 0; amarelo 0,01–10%; vermelho > 10%). O farol agregado do relatório e o Resumo Executivo usam o #10, não o #9.
4. **Textos das 3 mensagens curtas.** A serem extraídos da planilha RECOMENDAÇÕES (página 33) pela EBC — tarefa operacional que **não bloqueia o kickoff**.
5. **Resumo Executivo.** O algoritmo da §4.7.1 ignora o #9 (tratado como `farol = nenhum`); a interpretação semântica do capital de giro se dá via #10.

**Onde está documentado.** §4.5 (motor), §4.7.1 (algoritmo do Resumo), Anexo D linha #9 e Anexo E linha "NCG (absoluto)". A pendência operacional dos 3 textos da matriz fica registrada no item §6.9 (critério de revisão das faixas).

### 6.4 `[DECIDIR]` Expiração de rascunhos do quiz

**Problema.** Sem prazo definido, um rascunho de 18 meses com dados desatualizados poderia ser retomado inadvertidamente.

**Proposta preliminar.** Expiração de **90 dias** da última edição, com alerta ao retomar ("Seus dados podem estar desatualizados — revise antes de enviar"). Rascunhos expirados são arquivados (recuperáveis via suporte por 30 dias) e depois excluídos.

**Decisão pendente.** Confirmar o prazo de 90 dias e o fluxo de arquivamento.

### 6.5 `[RESOLVIDO]` Cadência da Persona 3 e dimensionamento do plano Pro

**Histórico.** A v1 e a v2.0 não fixaram a cadência típica de uso pelo contador (Persona 3 — Marcos), o que deixava em aberto a cota mensal e o preço do plano Pro. O parecer CLAUDE assumiu trimestral; a EBC corrigiu para "periódico" sem fixar. Sem definição, o Pro ficava sem ancoragem comercial.

**Decisão tomada (v2.3, 17/05/2026).** Cadência **semestral** como padrão de produto e comunicação, com dimensionamento associado:

| Variável | Valor fixado |
|---|---|
| Cadência típica | **Semestral** — checkup mid-year + análise anual |
| Cota mensal do Pro | **10 análises/mês** (= 120 análises/ano) |
| Preço mensal do Pro | **R$ 199,00** |
| Preço anual do Pro | **R$ 1.599,00** |
| Custo por análise (Pro mensal) | R$ 19,90 |
| Desconto vs. Básico | 60% (Básico unitário = R$ 49,90) |
| Posicionamento comercial | "Check-up semestral consultivo" |
| Carteira de empresas suportada | 30 a 60 empresas ativas (a cota total de 120/ano cobre 2 análises/empresa para 60 ou 4 análises/empresa para 30) |

**Racional.** Semestral é a cadência mais natural para checkup financeiro de PMEs no mercado contábil brasileiro — alinha com fechamento contábil de 6 meses e análise anual. A cota de 10/mês cabe na maioria dos perfis de carteira (30-60 ativos) com flexibilidade — alguns clientes rodam 2x/ano, outros 1x, alguns 3x se houver urgência. O preço R$ 199 equivale a ~5% do honorário médio que o contador cobra por cliente — barato para virar parte do serviço dele, gerando margem se repassar custo aos clientes.

**Onde está documentado.** §1.3.2 (Persona 3 com texto atualizado), §2.1 (tabela de planos com cota e preço novos). Subscription.cota_mensal em `arquitetura-tecnica.md` §4.1 atualizada para 10. Pro+ (carteira maior, ex.: cota 20/mês) entra como evolução pós-MVP se feedback de mercado demandar.

### 6.6 `[DECIDIR]` Validações cruzadas DRE × Balanço

**Problema.** As validações atuais do quiz são campo a campo; não há verificação de coerência entre grupos (DRE × balanço). Isso permite entrada de combinações financeiramente impossíveis (ex.: Q16 ≫ Q06 em base anual).

**Proposta preliminar.** Acrescentar ao Anexo A, seção A.3, pelo menos:
- Alerta não-bloqueante quando `Q16 × 12 > Q06 × 2` (despesas financeiras anualizadas são mais que o dobro da dívida declarada).
- Alerta não-bloqueante quando `Q14 × 12 + Q15 × 12 > Q09 × 12` (custos totais anuais ultrapassam a receita anual).
- Alerta não-bloqueante quando `Q02 + Q03 + Q04 + Q05 < Q06 + Q07` (passivo maior que ativo — PL negativo).

**Decisão pendente.** Validar o conjunto de regras e seus limiares; definir se algum deve ser bloqueante.

### 6.7 `[DECIDIR]` Aviso condicional de PL simplificado no relatório

**Problema.** A metodologia de PL simplificado (mantida em 22/04/2026) distorce indicadores em empresas com dívida de longo prazo relevante — especialmente o perfil Roberto (EPP/indústria). Para manter transparência e mitigar o risco R10 do parecer CLAUDE, o relatório deve exibir aviso quando aplicável.

**Proposta preliminar.** Exibir aviso no rodapé do relatório **quando Q06 > 30% do Ativo Total** (Q02+Q03+Q04+Q05), com texto: *"A metodologia simplificada de cálculo do Patrimônio Líquido pode subestimar indicadores de alavancagem quando há dívida financeira relevante. Para análise de captação ou decisões de investimento, consulte um contador."*

**Decisão pendente.** Validar o limiar de 30% e o texto do aviso com o time jurídico (alinhado à revisão do Termo de Adesão — ver `requisitos-nao-funcionais-e-juridicos.md`).

### 6.8 `[DECIDIR]` Box de explicação por indicador no quiz

**Problema.** Na planilha QUIZ há textos explicativos curtos por campo que não foram migrados para a especificação funcional. A ponderação 8 da EBC pede box inline ("sem sair da página").

**Proposta preliminar.** Implementar como tooltip acionado por clique em ícone (?) ao lado do label de cada campo. Conteúdo: 2–4 linhas com conceito, exemplo numérico e dica de como obter o dado. Consolidar na próxima iteração do Anexo A (seção A.6 já reservada).

**Decisão pendente.** Aprovar a abordagem (tooltip inline) e os textos finais — priorizar os campos de maior taxa de abandono (PMC, PME, PMR, Inadimplência).

### 6.9 `[DECIDIR]` Documentação do critério de revisão de faixas JUL → DEZ 2025

**Problema.** Entre a matriz JUL/2025 e a DEZ/2025 houve alterações nas faixas numéricas de alguns indicadores (ex.: Margem Bruta Indústria passou de ≤ 10% para ≤ 20% na faixa vermelha) sem critério escrito. A EBC declarou "validamos as de DEZ/2025" em 22/04/2026 mas sem registrar o racional da mudança.

**Proposta preliminar.** Anexar ao Anexo E (ou em campo próprio da planilha RECOMENDAÇÕES) uma **coluna "Critério de Revisão"** por faixa, com 1–3 linhas explicando a base da definição (referência setorial, benchmark externo, ajuste empírico, sugestão de especialista). Para as faixas onde a JUL/2025 e a DEZ/2025 diferem, registrar justificativa retroativa.

**Decisão pendente.** EB Parcerias formalizar o critério e repassar ao Product Owner para incorporação ao Anexo E.

### 6.10 `[DECIDIR]` Ativação do trial gratuito no MVP

**Problema.** A v1 previa trial de 7 dias com 1 análise; a revisão EBC de 22/04/2026 levantou a dúvida "acesso gratuito desvaloriza o produto?".

**Proposta preliminar.** Manter o trial **implementado e testável**, e deixar a ativação da oferta como flag de configuração. Decisão final tomada pelo Product Owner imediatamente antes do go-live, com base em pesquisa rápida de precificação e eventuais insights do beta fechado.

**Alternativas em aberto:** (a) ativar o trial de 7 dias com 1 análise (recomendação padrão SaaS B2C); (b) desativar — somente assinatura paga ou créditos; (c) ativar em formato reduzido — simulador público no hotsite com preview de relatório sem PDF.

**Decisão pendente.** EBC confirma se deseja que a equipe **implemente** a funcionalidade (mantendo opção de desligar) ou se remove completamente do escopo de código do MVP.

### 6.11 Síntese — Itens pré-kickoff x itens pré-go-live

| Item | Status | Momento de resolução | Bloqueador |
|---|---|---|---|
| 6.1 Portfólio multi-empresa (Usuário → N Empresas Analisadas) | ✅ `[RESOLVIDO]` em 17/05/2026 | — | — |
| 6.2 Algoritmo Resumo Executivo | ✅ `[RESOLVIDO]` em 17/05/2026 | — | Especificado em §4.7.1 |
| 6.3 NCG absoluto — limiar e exibição | ✅ `[RESOLVIDO]` em 17/05/2026 | — | Especificado em §4.5, §4.7.1 e Anexos D/E; textos finais da matriz são tarefa operacional EBC |
| 6.4 Expiração de rascunhos | 🟡 `[DECIDIR]` | Pré-go-live | Validação do prazo |
| 6.5 Cadência Persona 3 + cota e preço do Pro | ✅ `[RESOLVIDO]` em 17/05/2026 | — | Semestral, cota 10/mês, R$ 199/R$ 1.599 |
| 6.6 Validações cruzadas | 🟡 `[DECIDIR]` | Pré-go-live | Validação das regras |
| 6.7 Aviso PL simplificado | 🟡 `[DECIDIR]` | Pré-go-live | Texto jurídico |
| 6.8 Box explicação quiz | 🟡 `[DECIDIR]` | Pré-go-live | Conteúdos finais |
| 6.9 Critério de revisão de faixas | 🟡 `[DECIDIR]` | Pré-go-live | EB Parcerias documentar |
| 6.10 Ativação do trial | 🟡 `[DECIDIR]` | Pré-go-live | Flag de produto |

---

## 7. Documentos Complementares

Esta especificação referencia dois documentos companheiros que consolidam temas extra-funcionais:

- **[`requisitos-nao-funcionais-e-juridicos.md`](requisitos-nao-funcionais-e-juridicos.md)** (Bloco 3) — SLA de disponibilidade e performance, infraestrutura, backup, criptografia em repouso, decisões de gateway de pagamento e e-mail transacional, base legal LGPD para plano Pro operando CNPJ de terceiros, premissas para a revisão jurídica do Termo de Adesão.
- **[`roadmap-pos-v1.md`](roadmap-pos-v1.md)** (Bloco 4) — priorização das iniciativas fora do MVP: portfólio multi-cliente (dependendo da decisão 6.1), medianas setoriais, internacionalização, apps móveis, persona "fornecedores de crédito" (B2B), integrações com ERP, funcionalidades de retenção (metas, lembretes, alertas).

E o documento de controle:

- **[`resumo-recomendacoes-e-respostas.md`](resumo-recomendacoes-e-respostas.md)** — mapa exaustivo de cada crítica, sugestão e ponderação dos três documentos de revisão de 20–22/04/2026, com status (aceito/recusado/adiado/em definição) e justificativa.

---

**Fim do documento — Especificação Funcional v2 (draft revisado)**

*Próximos passos sugeridos: fechamento dos itens `[DECIDIR]` da Seção 6, revisão conjunta do documento `requisitos-nao-funcionais-e-juridicos.md` com time jurídico e de infraestrutura, e transição para a especificação técnica de construção (baseada no documento irmão `arquitetura-tecnica.md`). O mapa de cobertura das críticas e sugestões recebidas está em `resumo-recomendacoes-e-respostas.md`.*
