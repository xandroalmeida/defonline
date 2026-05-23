# DEFOnline — Arquitetura Técnica de Alto Nível (v3.0)

**Projeto:** Plataforma DEFOnline — Diagnóstico Econômico-Financeiro para Micro e Pequenas Empresas
**Versão do documento:** 3.0 (reset de stack — decisões de linguagem, framework e infra removidas)
**Data:** 19/05/2026
**Responsáveis:** EB Parcerias Ltda
**Escopo:** arquitetura **conceitual** de alto nível, requisitos não-funcionais, integrações, modelo de dados conceitual. **Este documento é deliberadamente neutro em relação a linguagem de programação, framework de aplicação, runtime, biblioteca e provedor de infraestrutura.** As únicas decisões técnicas fechadas neste documento são (a) **PostgreSQL** como banco relacional e (b) as exigências de **TDD** e **testes E2E** nos requisitos não-funcionais. Todo o restante (linguagem, framework backend e frontend, ORM, fila de jobs, geração de PDF, runtime, orquestração, provedor de nuvem, observabilidade, CI/CD) será redefinido em rodada dedicada de arquitetura técnica de construção.
**Documento companheiro:** `especificacao-funcional.md`.

---

## Sumário

1. [Visão Geral da Arquitetura](#1-visão-geral-da-arquitetura)
2. [Componentes de Alto Nível](#2-componentes-de-alto-nível)
3. [Integrações Externas](#3-integrações-externas)
4. [Modelo de Dados Conceitual](#4-modelo-de-dados-conceitual)
5. [Requisitos Não-Funcionais](#5-requisitos-não-funcionais)
6. [Segurança e LGPD](#6-segurança-e-lgpd)
7. [Hospedagem, CI/CD e Observabilidade (princípios)](#7-hospedagem-cicd-e-observabilidade-princípios)
8. [Plano de Contingência](#8-plano-de-contingência)
9. [Premissas e Decisões Abertas](#9-premissas-e-decisões-abertas)

---

## 1. Visão Geral da Arquitetura

A plataforma DEFOnline é uma aplicação SaaS web responsiva, com modelo relacional convencional.

O domínio é construído sobre duas entidades centrais — **Usuário** (pessoa física que loga, identificada por CPF + e-mail) e **Empresa Analisada** (entidade PJ/PF cujos dados financeiros são analisados) — com relação **1:N** (um Usuário tem N Empresas Analisadas; cada Empresa Analisada pertence a um único Usuário no MVP). Isolamento de dados entre Usuários é garantido pela autorização (toda consulta filtra por `usuario_id`); não há infraestrutura de multi-tenancy (sem schemas separados, sem bancos separados, sem `tenant_id` global).

A arquitetura segue o padrão de separação entre **frontend** (interface do Usuário, hotsite e área logada), **backend** (API HTTP que orquestra regras de negócio), **motor de diagnóstico** (componente especializado em cálculos e recomendações), **banco de dados relacional** (persistência de usuários, empresas analisadas, quizzes, diagnósticos, carteiras), **serviços auxiliares** (geração de PDF, envio de e-mails) e **integrações externas** (API da RFB, gateway de pagamento, provedor de e-mail).

A comunicação entre frontend e backend é por HTTPS com autenticação baseada em token. A comunicação com serviços externos acontece através de um nível de abstração interno (cada integração tem um *adapter*) para permitir troca de provedor sem impacto na lógica de negócio. O motor de diagnóstico é um componente lógico que pode rodar em processo no backend ou como serviço à parte, a depender da escala — decisão da spec de construção.

**Decisão técnica fechada:** o banco de dados é **PostgreSQL**. Justificativa: o domínio é claramente relacional, com relacionamentos fortes entre Usuário, Empresa Analisada, Quiz, Diagnostico, Carteira, Assinatura, Faturas; PostgreSQL atende com folga as exigências de integridade referencial, transações, consultas analíticas (comparativo temporal) e auditoria. Versões e operação do PostgreSQL (gerenciado vs. self-managed, HA, replicas) ficam para a spec de construção.

**Diagrama lógico (texto):**

```
┌─────────────┐    ┌──────────────────────────────────────┐
│  Hotsite    │    │        Frontend (App Logada)         │
│ (público)   │    │  Cadastro · Quiz · Relatório · FAQ   │
└──────┬──────┘    └──────────────────┬───────────────────┘
       │                              │ HTTPS / Token
       │                              ▼
       │                  ┌───────────────────────┐
       │                  │    Backend / API      │
       │                  │  Auth · Contas ·      │
       └────────────────▶ │  Carteira · Quiz ·    │
                          │  Relatório · Suporte  │
                          └──┬──────┬─────────┬───┘
                             │      │         │
                  ┌──────────┘      │         └────────────┐
                  ▼                 ▼                      ▼
          ┌────────────────┐ ┌──────────────┐ ┌──────────────────┐
          │ Motor de       │ │  Serviço de  │ │  Serviço de      │
          │ Diagnóstico    │ │  Geração PDF │ │  E-mail          │
          │ (cálculo +     │ │              │ │  Transacional    │
          │ recomendações) │ └──────────────┘ └──────────────────┘
          └────────┬───────┘
                   ▼
          ┌────────────────────┐
          │  PostgreSQL        │
          │  (banco relacional)│
          └────────────────────┘

Integrações externas:
   API RFB (CNPJ)      ◀──── Backend
   Gateway Pagamento   ◀──── Backend
   Storage de PDFs     ◀──── Serviço de Geração PDF
   Provedor de E-mail  ◀──── Serviço de E-mail
```

---

## 2. Componentes de Alto Nível

### 2.1 Frontend — Hotsite Público

Páginas estáticas (ou renderizadas no servidor) de marketing e captação: home, como funciona, planos, FAQ público, contato, blog, termos. Requisitos principais: SEO, performance (Core Web Vitals), responsividade, instrumentação com Google Analytics e Meta Pixel, banner LGPD. Pode ser entregue como site à parte (mais simples para SEO) ou integrado à aplicação — decisão da spec de construção.

### 2.2 Frontend — Aplicação Logada

Interface responsiva da área pós-login: painel do assinante, carteira, preenchimento do quiz (multi-step com rascunho), visualização e exportação do relatório, histórico com comparativo temporal, formulário de captação, FAQ. Princípios: navegação rápida, uso intuitivo, máscaras de entrada adequadas (R$, %, dias), salvamento automático, feedback visual imediato.

### 2.3 Backend — API

Camada de orquestração e regras de negócio. Responsabilidades:
- **Autenticação e autorização** (sessões, tokens, redefinição de senha, confirmação de e-mail).
- **Gestão de contas** (perfil, aceites LGPD/Termo, exclusão/anonimização).
- **Carteira** (estado da assinatura, cota mensal, saldo e FIFO de créditos, consumo por análise).
- **Assinaturas e pagamentos** (ciclo, renovação, falhas, cancelamento, trial).
- **Quiz** (rascunho, envio, clonagem do último).
- **Orquestração do Motor de Diagnóstico** (recebe quiz, dispara cálculo, persiste resultado).
- **Geração de relatório** (composição dos dados para a tela e para o PDF).
- **Histórico** (consulta, exclusão automática após 12 meses rolantes, comparativo).
- **Captação** (grava pedido, notifica equipe EB).
- **FAQ / Suporte** (consulta de itens, feedback).

### 2.4 Motor de Diagnóstico (Calculation & Recommendations Engine)

Componente lógico, idealmente isolado, responsável por:
- Receber os 23 inputs do quiz.
- Calcular balanço adaptado, DRE adaptada e os **14 indicadores** (anexos B–D do documento funcional).
- Classificar cada indicador nas faixas de semáforo (anexo E).
- Buscar na matriz de recomendações (versões curta e longa) os textos aplicáveis por indicador × setor × faixa.
- Retornar a estrutura completa do relatório para persistência.

**Requisitos técnicos** (alto nível):
- Determinismo total (mesma entrada → mesma saída).
- Versionamento: cada diagnóstico persiste a **versão do motor** e a **versão da matriz** usadas, viabilizando reprodutibilidade e evolução sem quebrar o histórico.
- Matriz de recomendações carregada de fonte versionada (em PostgreSQL, na tabela `Recommendation`).
- Proteção contra divisões por zero: indicadores com denominador zero retornam valor nulo e farol "Indisponível".

### 2.5 Serviço de Geração de PDF

Recebe os dados do relatório e produz um arquivo PDF A4 com layout definido na seção 4.7 do documento funcional. O PDF é gerado sob demanda (clique em Salvar PDF) e opcionalmente em background ao concluir o diagnóstico (para entrega imediata). Armazenado em storage de objetos com URL assinada e tempo de expiração curto, limitando a exposição. Implementação (biblioteca/abordagem) fica para a spec de construção.

### 2.6 Serviço de E-mail Transacional

Envio de e-mails listados na seção 4.12 do documento funcional. Templates versionados, com renderização HTML + texto plano. Rastreamento de entregas, bounces e aberturas para operação. Opt-out honrado para e-mails de marketing (trial, renovação em caráter comercial); e-mails transacionais (confirmação de pagamento, segurança) são enviados mesmo para usuários que optaram por não receber marketing. Provedor de envio fica para a spec de construção.

### 2.7 Banco de Dados — PostgreSQL `[DECIDIDO]`

Banco relacional **PostgreSQL** é o modelo natural para o domínio (usuários, quizzes, diagnósticos, carteiras, faturamento — tudo com relacionamentos claros). Modelo conceitual detalhado na seção 4. Versão mínima, parâmetros operacionais, replicação e estratégia de migração ficam para a spec de construção.

---

## 3. Integrações Externas

### 3.1 API da Receita Federal (RFB) para consulta de CNPJ

**Propósito:** enriquecer o cadastro de usuários PJ com Razão Social, Nome Fantasia, Data de Fundação, CNAE Principal, Município, UF e Situação Cadastral.

**Características:**
- Chamada síncrona no momento do cadastro; timeout curto (5s) para não travar a experiência.
- Cache local de respostas bem-sucedidas com TTL parametrizável (default 5min na implementação inicial; aceita-se valor maior até no máximo 1h por causa de mudanças de Situação Cadastral). Erros NÃO são cacheados.
- Fallback: se a API estiver indisponível ou retornar erro, o sistema permite preenchimento manual com aviso ao usuário.
- **Provedor: abstração `RfbCnpjClient` com dois provedores reais suportados** — `cnpja` (https://cnpja.com/) e `receitaws` (https://receitaws.com.br/) — selecionáveis via `config/services.php → rfb.provider` (decisão **IDR-004**). PO confirmou que produção usará os dois mesmos provedores; o primário em `production` será definido pelo Arquiteto em IDR separado quando a STORY-018 entregar.
- **Rate-limit por provedor** configurável independentemente (`rfb.providers.<provider>.rate_limit_per_minute`), implementado via `Illuminate\Support\Facades\RateLimiter` com chave `rfb:provider:{provider}`. Defaults baseados no plano gratuito público de cada um (3 RPM).

### 3.2 Gateway de Pagamento

**Propósito:** processar pagamentos via Pix (à vista) e cartão de crédito (recorrente).

**Características esperadas do gateway:**
- Tokenização do cartão (a plataforma **nunca** armazena número, CVV ou validade em plain text — apenas token opaco).
- API para criar cobrança única (Pix) e assinatura recorrente (cartão).
- Webhook de notificação para confirmação de pagamento, falha e cancelamento — a plataforma mantém endpoint dedicado para consumir esses eventos.
- Suporte a reembolsos (política de arrependimento de 7 dias).
- Emissão de nota fiscal é responsabilidade da EB Parcerias (backoffice futuro); a integração com API de NFSe/NFe fica no roadmap.

**Provedor específico:** `[A DEFINIR]` na spec de construção. Critérios de escolha: suporte a Pix recorrente futuro, preço por transação, qualidade da API, suporte no Brasil.

### 3.3 Provedor de E-mail Transacional `[A DEFINIR]`

**Critérios esperados:**
- Domínio principal verificado com **DKIM, SPF e DMARC**.
- Subdomínios separados para isolar reputação entre tráfego transacional e marketing.
- Templates versionados (HTML + texto plano).
- Tracking diferenciado entre trilha transacional e marketing.
- Custo previsível e baixo no volume do MVP.

### 3.4 Storage de PDFs e backup `[A DEFINIR]`

**PDFs gerados (produção):**
- Armazenamento em volume com URLs assinadas (validade curta — ex.: 15 min).
- Lifecycle: exclusão automática para PDFs com mais de 12 meses (alinhada à retenção da §4.8 da especificação funcional).
- Em caso de perda do storage, PDFs são regeneráveis a partir do quiz e diagnosis no banco.

**Backup off-site do PostgreSQL:**
- Backup diário em armazenamento off-site (provedor distinto do que hospeda o banco).
- Backup encriptado em repouso e em trânsito.
- Retenção: 30 dias rolantes + arquivos mensais por 12 meses.

### 3.5 Integrações Planejadas (Roadmap)

- Emissor de NFSe/NFe automático.
- API de WhatsApp Business (suporte e disparos de marketing).
- Serviço de analytics agregado (para benchmark setorial anônimo).
- Integrações com sistemas contábeis (Omie, Conta Azul, etc.) para importação de dados no quiz.

---

## 4. Modelo de Dados Conceitual

### 4.1 Entidades principais

**Usuario** *(pessoa física que loga; identificada por CPF + e-mail)*
- id
- cpf (único)
- nome
- email (único)
- senha_hash
- telefone (WhatsApp)
- status (Pendente | Ativa | Suspensa | Excluída)
- opt_in_marketing (bool)
- data_cadastro, data_confirmacao_email

**EmpresaAnalisada** *(entidade — PJ ou autônomo — cujos dados financeiros são analisados)*
- id
- usuario_id (FK — proprietário; 1:N com Usuario no MVP)
- tipo_documento (CNPJ | CPF)
- documento (CNPJ ou CPF — único por usuario_id)
- razao_social (PJ) / nome (autônomo)
- nome_fantasia (PJ — opcional)
- cnae_principal (PJ)
- municipio, uf
- situacao_cadastral (PJ)
- data_fundacao (PJ) / data_nascimento (autônomo)
- setor_atividade (1=Indústria | 2=Comércio | 3=Serviços) — usado para faixas
- origem_dados (RFB | Manual) — registra se houve enriquecimento
- status (Ativa | Arquivada)
- data_cadastro

> **Nota.** A tabela de associação `UsuarioEmpresa` (N:M com papéis Owner/Editor/Viewer) **não** existe no MVP. Quando o compartilhamento entre Usuários for implementado (roadmap §1.1), a FK `usuario_id` em `EmpresaAnalisada` se mantém como "Usuário criador/owner" e a nova tabela `UsuarioEmpresa` adiciona as relações extras — migração aditiva, sem reescrita.

**TermAcceptance** (histórico de aceites do Usuário)
- id, usuario_id
- termo_tipo (Termo de Adesão | LGPD | Opt-in Marketing | DPA-Pro)
- termo_versao
- empresa_id (nullable — preenchido quando o aceite é do DPA-Pro vinculado a uma Empresa Analisada específica)
- aceite_em (timestamp)
- ip, user_agent

**Plan** *(catálogo parametrizado de planos de assinatura)*
- id
- codigo (`basico` | `pro` | `trial`) — string única, usada pelo código
- nome_exibicao (`Básico` | `Pro` | `Trial`)
- cota_mensal (integer — análises incluídas no ciclo)
- preco_mensal_centavos (integer)
- preco_anual_centavos (integer)
- trial_dias (integer — para o plano "trial", default 7)
- trial_analises_incluidas (integer — para o plano "trial", default 1)
- ativo (bool)
- ordem_exibicao (integer)
- data_criacao, data_alteracao
- alterado_por_usuario_id (auditoria)

> Plan é a fonte de verdade dos preços e cotas. Subscription aponta para Plan via FK. Alterações de preço **não afetam** assinaturas vigentes (ver Subscription.preco_snapshot abaixo) — só valem para novas assinaturas e renovações.

**CreditPackage** *(catálogo parametrizado de pacotes de créditos avulsos)*
- id
- codigo (`mini` | `plus` | `max`) — string única
- nome_exibicao (`Mini` | `Plus` | `Max`)
- quantidade_creditos (integer)
- preco_centavos (integer)
- validade_meses (integer — default 12)
- ativo (bool)
- ordem_exibicao (integer)
- data_criacao, data_alteracao
- alterado_por_usuario_id (auditoria)

**Setting** *(configurações parametrizadas chave-valor)*
- key (string única — ex.: `pix_expiracao_minutos`, `analise_cota_acumula`, `rascunho_expira_dias`)
- value (text — interpretado conforme `tipo`)
- tipo (`number` | `string` | `boolean` | `json`)
- descricao (text — para back-office futuro)
- data_alteracao
- alterado_por_usuario_id

> Settings cobrem parâmetros operacionais que não pertencem a uma entidade própria: expiração do QR Pix (default 30), expiração de rascunho (default 90 dias — item 6.4), política de não-acúmulo da cota (default `false`), SLA da análise de captação (default 5 dias úteis), limite caractere recomendação (default 300), etc.

**Subscription** *(assinatura — vinculada ao Usuário)*
- id, usuario_id, plan_id (FK para Plan)
- ciclo (Mensal | Anual)
- status (Trial | Ativa | Em Atraso | Cancelada | Expirada)
- data_inicio, proximo_vencimento, data_cancelamento
- **preco_snapshot_centavos** (integer — preço do plano no momento da contratação ou renovação; protege a assinatura de mudanças de preço futuras)
- **cota_snapshot** (integer — cota do plano no momento da contratação ou renovação)
- cota_restante_ciclo
- meio_pagamento_id

**PaymentMethod** *(vinculado ao Usuário)*
- id, usuario_id
- tipo (Cartão | Pix)
- token (quando cartão — referência no gateway)
- ultimos_4_digitos (se cartão)
- bandeira (se cartão)
- titular (nome)
- status (Ativo | Expirado | Removido)

**Invoice / Transaction** *(vinculada ao Usuário pagante)*
- id, usuario_id, subscription_id (nullable — créditos avulsos não têm subscription)
- tipo (Assinatura | Crédito)
- valor, moeda
- status (Pendente | Paga | Falhou | Estornada)
- data_vencimento, data_pagamento
- meio_pagamento_id
- id_externo_gateway
- nota_fiscal_emitida (bool)

**CreditLot** (lotes FIFO — vinculados ao Usuário)
- id, usuario_id
- invoice_id
- quantidade_comprada
- quantidade_restante
- data_compra, data_expiracao (12 meses)
- status (Ativo | Esgotado | Expirado)

**Quiz** (rascunho ou enviado — vinculado a uma Empresa Analisada)
- id, usuario_id (quem está preenchendo), empresa_id (qual Empresa Analisada está sendo analisada)
- status (Rascunho | Enviado)
- data_criacao, data_envio
- setor (1|2|3) — copiado de EmpresaAnalisada.setor_atividade no momento de envio (snapshot)
- respostas (campos Q02–Q23, com possibilidade de campos nulos em rascunho)

**Diagnosis** (diagnóstico gerado)
- id, usuario_id, empresa_id, quiz_id
- data_geracao
- versao_motor, versao_matriz_recomendacoes
- consumo (Cota Mensal | Crédito | Trial)
- valores_calculados (balanço, DRE, 14 indicadores — snapshot)
- farol_agregado (Saudável | Atenção | Alerta)
- pdf_url, pdf_gerado_em
- data_expiracao (12 meses após geração)
- status (Ativa | Expirada | Excluída)

**Recommendation** (estrutura da matriz, consumida pelo motor)
- id
- indicador (chave)
- setor (1|2|3|0=todos)
- faixa_min, faixa_max
- texto_resumido (dez/2025)
- texto_detalhado (jul/2025)
- versao_matriz

**CaptureRequest** (solicitação de análise de captação)
- id, usuario_id, empresa_id, diagnosis_id
- data_solicitacao
- finalidade, prazo, garantias, observacoes
- contato_preferencial
- status (Enviada | Em análise | Respondida | Encerrada)

**FaqItem**
- id, tema
- pergunta, resposta
- ordem, publicado

**EventAudit** (auditoria de ações críticas)
- id, usuario_id, empresa_id (nullable), tipo_evento, dados, ip, timestamp

### 4.2 Relacionamentos principais

- Um **Usuario** tem N **EmpresaAnalisada** (1:N, sem limite quantitativo — controle de uso é pela cota mensal da Subscription).
- Um **Usuario** tem zero ou uma **Subscription** ativa por vez, com histórico completo de assinaturas.
- Um **Usuario** tem N **PaymentMethod**, N **Invoice**, N **CreditLot**, N **TermAcceptance**.
- Uma **EmpresaAnalisada** tem N **Quiz**, N **Diagnosis**, N **CaptureRequest**, todos também vinculados ao Usuario que os executou.
- Cada **Diagnosis** pertence a um **Quiz** (1:1), e ambos pertencem a uma **EmpresaAnalisada** e a um **Usuario**.
- Cada **CaptureRequest** aponta para o **Diagnosis** que a originou.
- **Autorização:** todas as consultas a EmpresaAnalisada, Quiz, Diagnosis, CaptureRequest filtram por `usuario_id` da sessão autenticada. Tentativa de acesso a recurso de outro Usuário retorna 404 (nunca 403, para não vazar existência).

### 4.3 Políticas de persistência

- **Soft-delete** para **Usuario** (`status = Excluída`) com anonimização dos campos identificáveis (LGPD — direito ao esquecimento); registros agregados para auditoria e obrigações fiscais ficam anonimizados. A exclusão do Usuario implica anonimização em cascata de todas as **EmpresaAnalisada**, **Quiz** e **Diagnosis** vinculadas.
- **Arquivamento** de **EmpresaAnalisada** (`status = Arquivada`) mantém a empresa no histórico do Usuario mas a oculta do painel principal; quizzes e diagnósticos seguem com a janela de retenção de 12 meses.
- **Exclusão física** automática de **Diagnosis** e **Quiz** com mais de 12 meses (política de retenção), precedida de aviso por e-mail D-7 ao Usuario.
- **Eventos de auditoria** mantidos por prazo maior (ex.: 5 anos) para conformidade, armazenados fora da base operacional.

---

## 5. Requisitos Não-Funcionais

### 5.1 Performance

- **Tempo de resposta** da interface: interações locais < 100ms, navegação entre telas < 500ms (P95).
- **API**: requisições síncronas < 500ms (P95); envio do quiz + cálculo < 2s (P95).
- **Geração de PDF**: < 5s (P95); se exceder, apresentar feedback de progresso e permitir download posterior.
- **Cadastro com enriquecimento RFB**: < 5s total; em caso de falha da API externa, seguir com fallback manual.

### 5.2 Escalabilidade

Projeção inicial (conservadora, v1): ~1.000 usuários ativos mensais, ~5.000 análises/mês, picos moderados em início de mês e meio de semana. Arquitetura deve permitir escalar horizontalmente a partir de ~10.000 usuários sem reprojeto: backend stateless, sessões em cache/token, banco escalável verticalmente até evolução para read replicas.

### 5.3 Disponibilidade

- **SLA alvo v1:** 99,5% (tolera ~3,6h/mês de indisponibilidade).
- **SLA alvo pós-v1:** 99,9% (~45min/mês).
- Janelas de manutenção programadas em horários de baixo uso, comunicadas previamente.

### 5.4 Responsividade e Acessibilidade

- Suporte total a desktop e mobile (web responsivo, sem app nativo na v1).
- Navegadores suportados: Chrome, Safari, Firefox, Edge — últimas 2 versões estáveis.
- **Acessibilidade**: aderência razoável ao WCAG 2.1 nível AA — contraste adequado, navegação por teclado, textos alternativos em ícones funcionais, foco visível.

### 5.5 Internacionalização

v1 opera exclusivamente em **português do Brasil** e em **moeda Real (R$)**. Estrutura do código preparada para internacionalização futura (textos em arquivos de tradução, formatação de datas/moedas por locale), sem necessidade de entregar múltiplos idiomas na v1.

### 5.6 Compatibilidade de Dados

Formato de exportação: **PDF** para relatórios. Não há exportação em Excel/CSV na v1 (roadmap). Importação de dados não prevista na v1.

### 5.7 Qualidade — TDD e Testes E2E `[DECIDIDO]`

**TDD (Test-Driven Development) é obrigatório** em toda a base de código da plataforma. Toda nova regra de negócio, fórmula de indicador, política de autorização, transição de estado de assinatura/carteira e regra de cálculo de cota/crédito deve ser exercitada por testes automatizados escritos **antes** da implementação. As fórmulas do **Motor de Diagnóstico** (14 indicadores, balanço adaptado e DRE adaptada) exigem cobertura mínima de **10 casos por fórmula**, contemplando: caso típico, fronteiras das faixas do farol, denominador zero, valores negativos legítimos, valores ausentes e casos por setor (1=Indústria, 2=Comércio, 3=Serviços).

**Testes E2E (end-to-end) são obrigatórios** para os fluxos críticos do produto, executados em ambiente de homologação com banco PostgreSQL real e mocks/sandbox dos provedores externos:
1. Cadastro completo do Usuário (com e sem enriquecimento RFB) → confirmação de e-mail → primeiro login.
2. Cadastro de Empresa Analisada e preenchimento completo do quiz (23 perguntas) com rascunho intermediário e retomada.
3. Envio do quiz → geração do diagnóstico → exibição do relatório → geração e download do PDF.
4. Contratação de plano (Pix e cartão), confirmação via webhook do gateway, ativação da assinatura, consumo da cota.
5. Compra de pacote de créditos avulsos, expiração de lote FIFO e consumo na ordem correta.
6. Solicitação de análise de captação a partir de um diagnóstico existente.
7. Comparativo de dois diagnósticos da mesma Empresa Analisada (histórico).
8. Exclusão de conta com anonimização em cascata (LGPD).

A execução completa da suíte E2E é critério de aceite para promoção de qualquer release a produção. Ferramentas, runners e estratégia de seed/teardown ficam para a spec de construção.

---

## 6. Segurança e LGPD

### 6.1 Princípios

- **Princípio do menor privilégio** em todos os acessos.
- **Defense in depth**: múltiplas camadas (rede, aplicação, banco, auditoria).
- **Privacy by design**: coleta mínima de dados necessários ao serviço; consentimento explícito e granular.

### 6.2 Proteção de credenciais e tokens

- Senhas armazenadas com algoritmo de hash moderno e parametrizado para resistência a brute force, nunca em plain text.
- Política de senha: mínimo 8 caracteres, força razoável, checagem contra lista de senhas vazadas (quando disponível).
- Tokens de sessão com expiração; rotação em redefinição de senha; revogação possível (logout em todos os dispositivos).
- Rate limiting em endpoints de autenticação (mitigação de brute force).
- Chaves de API de terceiros e segredos armazenados em serviço dedicado de segredos (secret manager), nunca no código.

### 6.3 Criptografia

- **Em trânsito:** HTTPS obrigatório em todas as páginas e endpoints; certificado TLS válido; HSTS habilitado.
- **Em repouso:** banco com criptografia de disco habilitada; storage de PDFs com criptografia nativa do provedor.
- Dados sensíveis de pagamento (número do cartão) **nunca** trafegam ou repousam na nossa infra — apenas o token do gateway é guardado.

### 6.4 LGPD

- **Base legal**: consentimento explícito para dados do assinante e para comunicação de marketing; execução de contrato para os dados estritamente necessários ao serviço; obrigação legal para dados fiscais.
- **Direitos do titular**: acesso, correção, portabilidade (exportação dos próprios dados), exclusão (anonimização), revogação de consentimento, informação sobre compartilhamento. Disponíveis no painel do assinante e por solicitação ao DPO.
- **DPO / Encarregado**: papel definido na EB Parcerias (contato e-mail `[A DEFINIR]`).
- **Registro de consentimentos** versionado com timestamp, IP, versão do termo.
- **Notificação de incidentes**: plano de resposta a incidentes com comunicação à ANPD e aos titulares dentro dos prazos legais.
- **Tratamento de menores**: o serviço é direcionado a empresários, não a menores de idade; o cadastro inclui declaração de maioridade.

### 6.5 Auditoria

- **Log de eventos críticos**: login, alteração de senha, alteração de dados cadastrais, compra, cancelamento, execução de análise, exclusão de conta, exportação de PDF, exclusão de diagnóstico, aceite de termo.
- Retenção dos logs de auditoria: 5 anos (prazo legal).

### 6.6 Proteções adicionais

- Validação server-side de todos os inputs (nunca confiar apenas em validação client-side).
- Proteção contra CSRF nas operações autenticadas.
- Proteção contra XSS (escaping sistemático de saídas, CSP adequada).
- Proteção contra SQL Injection (uso exclusivo de queries parametrizadas).
- Headers de segurança (CSP, X-Content-Type-Options, X-Frame-Options / frame-ancestors, Referrer-Policy).
- Revisão de dependências (SCA) e correção pontual de vulnerabilidades conhecidas.

---

## 7. Hospedagem, CI/CD e Observabilidade (princípios)

> **Nota.** Esta seção descreve apenas princípios e expectativas — escolhas concretas de provedor de nuvem, orquestração, pipeline e ferramentas de observabilidade ficam para a spec de construção.

### 7.1 Hospedagem

- Datacenter no Brasil (preferência por proximidade legal e de latência).
- Ambientes: desenvolvimento (local), homologação (espelha produção em escala reduzida), produção.
- TLS válido em todas as URLs públicas.
- Reverse proxy na borda, com rate limiting em endpoints sensíveis (`/auth/login`, `/auth/forgot`, `/cadastro`) e WAF básico.

### 7.2 CI/CD

- Pipeline automatizado por pull request: análise estática, typecheck (quando aplicável), execução completa da suíte de testes unitários e de integração, build.
- Em merge na branch principal: build de artefatos imutáveis e publicação em registry privado.
- Deploy em produção via promoção controlada (tag/release), com smoke test pós-deploy.
- Rollback automático em caso de falha do health check pós-deploy.
- Branches protegidas; merge mediante revisão.

### 7.3 Observabilidade

- **Logs** estruturados (JSON), com retenção mínima de 30 dias.
- **Erros** capturados em ferramenta centralizada (BE e FE), com alertas para 5xx persistentes, falhas de webhook de pagamento e falhas de envio de e-mail crítico.
- **Métricas operacionais** mínimas: latência por endpoint, taxa de erro, fila de jobs, uso de CPU/memória, conexões no PostgreSQL.
- **Métricas de produto:** eventos críticos (`cadastro_concluido`, `quiz_enviado`, `pagamento_confirmado`, `pdf_gerado`, etc.) gravados em `EventAudit`.
- **Uptime e certificado TLS** monitorados com alertas externos.

### 7.4 Backup e recuperação

- `pg_dump` (ou equivalente) **diário** em PostgreSQL, encriptado e enviado a storage off-site em provedor distinto do que hospeda o banco.
- Retenção: 30 dias rolantes + arquivos mensais por 12 meses.
- **RPO alvo:** 24h no MVP; evolução para 1h (PITR) quando a operação justificar.
- **RTO alvo:** 4h em caso de desastre total.
- **Teste de restore:** trimestral em ambiente de staging com um dump real anonimizado.

---

## 8. Plano de Contingência

### 8.1 Falha na API da RFB (enriquecimento de CNPJ)

- Fallback: permitir preenchimento manual dos campos com aviso ao usuário.
- Retry automático em segundo plano (background) para atualizar os dados assim que a API voltar.
- Alerta operacional **por provedor** (dimensão `provider` em `business_metrics`) se a taxa de falha exceder 5% em janela de 10min (NRF §3.1 — janela ajustada de 15min para 10min na NRF v2.0).
- **Failover entre provedores** (`cnpja` ↔ `receitaws`) é manual no MVP — troca via `RFB_PROVIDER` no `.env` + restart do `web`/`worker`. Failover automático exige PDR (impacto de custo e UX) — fora do escopo MVP.

### 8.2 Falha no gateway de pagamento

- Fila de retry para webhooks não entregues.
- Retry automático da cobrança recorrente até 3 vezes em 48h (seção 3.7 do funcional).
- Fallback para Pix caso falhe o cartão no trial/conversão.
- Alerta crítico se nenhum pagamento for confirmado em janela de 30min (possível panela no webhook).

### 8.3 Falha no provedor de e-mail

- Fila de retry interna com backoff exponencial.
- Fallback opcional: segundo provedor configurado como backup `[A DEFINIR]`.
- E-mails críticos (confirmação de cadastro, redefinição de senha) têm maior prioridade e alertas dedicados.

### 8.4 Indisponibilidade do banco de dados

- Read replica promovida automaticamente em ambientes com HA.
- Modo degradado: bloqueio de escrita, leitura permanece em replicas.
- Comunicação de status aos usuários via página pública de status.

### 8.5 Incidente de segurança

- Plano de resposta com papéis definidos (condutor, comunicação, técnico, jurídico).
- Templates de comunicação interna e externa prontos.
- Notificação à ANPD e titulares quando aplicável, conforme LGPD.
- Post-mortem sem culpa (blameless), com plano de ação corretivo.

### 8.6 Corrupção ou perda de dados

- Restore a partir do backup mais recente, aplicando logs de transação até o ponto antes do incidente.
- Teste trimestral de restore em ambiente isolado.

---

## 9. Premissas e Decisões Abertas

### 9.1 Premissas assumidas

- O domínio é relacional convencional, com **Usuario** (1) → **EmpresaAnalisada** (N) como espinha dorsal. Isolamento de dados entre Usuários é garantido por FK + regras de autorização (filtro por `usuario_id` em todas as consultas). **Não** se adota arquitetura multi-tenant infraestrutural (sem `tenant_id` global, sem schemas separados, sem bancos separados).
- A base principal é relacional (**PostgreSQL**). Caches, filas e storage de objetos são complementares.
- Operação principal em produção hospedada em nuvem pública (sem self-hosted em infra própria).
- Equipe de desenvolvimento seguirá práticas modernas: **TDD obrigatório**, **testes E2E nos fluxos críticos**, code review, deploys contínuos, monitoramento ativo.
- v1 **não** precisa de app móvel nativo.

### 9.2 Decisões fechadas neste documento

| Item | Decisão |
|---|---|
| Banco de dados | **PostgreSQL** |
| Qualidade — TDD | **Obrigatório** em todo o código (§5.7) |
| Qualidade — E2E | **Obrigatório** para 8 fluxos críticos (§5.7) |

### 9.3 Decisões abertas (`[A DEFINIR]` na spec de construção)

- Linguagem de programação (backend e frontend).
- Framework de aplicação backend.
- Framework de aplicação frontend (hotsite e área logada).
- Estratégia de monorepo ou multi-repo.
- ORM / camada de acesso a dados.
- Mecanismo de fila / jobs assíncronos.
- Estratégia de cache (in-process, distribuído).
- Biblioteca / estratégia de geração de PDF.
- Provedor de nuvem (incluindo formato: VPS, IaaS, PaaS, serverless).
- Orquestração de containers / runtime de execução.
- Reverse proxy / TLS.
- Pipeline de CI/CD (ferramenta).
- Ferramentas de observabilidade (logs, erros, métricas, uptime).
- Storage de PDFs (provedor).
- Storage de backup off-site (provedor).
- Provedor de e-mail transacional.
- ~~API CNPJ (provedor)~~ — decidido via **IDR-004**: abstração com `cnpja` + `receitaws` selecionáveis via config + rate-limit por provedor. Definição do primário em `production` fica com o Arquiteto (IDR separado, pós-STORY-018).
- Gateway de pagamento (provedor).

### 9.4 Estratégia de armazenamento da matriz de recomendações

- **Tabela `Recommendation` em PostgreSQL** (já presente no modelo §4.1) é a forma vigente em produção, consumida pelo motor.
- Os arquivos `.md` em `anexos/anexo-F-matriz-recomendacoes-dez2025.md` e `anexo-G-matriz-recomendacoes-jul2025.md` são a **representação legível para revisão humana**; servem como fonte de verdade até existir CMS de back-office (roadmap).
- **Versionamento:** campo `versao_matriz` na tabela; toda inserção/alteração cria uma nova versão; diagnósticos já gerados mantêm o ponteiro para a versão vigente na data da geração (campo `versao_matriz_recomendacoes` em `Diagnosis`).

### 9.5 Roadmap técnico pós-v1

- **Back-office administrativo** (CMS para recomendações e FAQ, dashboards financeiros, gestão de assinaturas, emissão automatizada de NFSe).
- **Portais B2B2C de parceiros patrocinadores** (geração de cupons, relatórios agregados, white-label parcial). Pode exigir, dependendo do volume de parceiros e do nível de isolamento contratual, evolução para schema separado por parceiro — decisão pós-MVP, fora do MVP funcional.
- **Programa de afiliados** (gestão de comissões, links de indicação, payout).
- **Aplicativos móveis nativos** (iOS e Android) com notificações push.
- **Benchmark setorial anônimo** (data lake com dados agregados, processo de anonimização, visualizações).
- **Integrações com contabilidade/ERP** (Omie, Conta Azul, Bling).
- **Integração WhatsApp Business** (suporte e campanhas).
- **Autenticação multifator (MFA)**.
- **Análise preditiva com IA** (detecção de tendência baseada em histórico, sugestões contextuais).
- **Internacionalização** (novos idiomas e moedas, se aplicável a futuros mercados).
- **Exportações avançadas** (Excel/CSV dos dados, API pública para parceiros).

---

**Fim do documento — Arquitetura Técnica de Alto Nível v3.0**
