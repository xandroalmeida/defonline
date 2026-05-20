# DEFOnline — Roadmap Pós-v1 (Bloco 4)

**Projeto:** Plataforma DEFOnline
**Versão do documento:** 1.1 (alinhado com especificação v2.1)
**Data:** 17/05/2026
**Responsáveis:** EB Parcerias Ltda
**Documento pai:** [`especificacao-funcional.md`](especificacao-funcional.md) (Seção 7 e Anexo J)

**Histórico:**
- **v1.0** (24/04/2026) — versão inicial.
- **v1.1** (17/05/2026) — §1.1 alterado: "Portfólio multi-cliente do plano Pro" foi promovido ao MVP (parte do modelo de domínio base na spec funcional v2.1) e o item §1.1 passa a tratar exclusivamente do **compartilhamento N:M de Empresa Analisada entre Usuários**. Tabela consolidada atualizada.

**Finalidade.** Consolidar, priorizar e dar contexto às iniciativas **deliberadamente fora do escopo do MVP**, mas mapeadas durante as revisões de 20–22/04/2026. Cada item traz: descrição, motivação, estimativa de complexidade (Baixa/Média/Alta), dependências e horizonte sugerido (v1.1, v1.2, v2.0).

> Horizontes orientativos (a partir do go-live do MVP):
> - **v1.1** — 30 a 90 dias pós-MVP. Correções críticas e features de maior impacto em retenção/Pro.
> - **v1.2** — 3 a 6 meses pós-MVP. Expansão orgânica do produto.
> - **v2.0** — 6 a 12 meses pós-MVP. Evoluções estruturais e novos módulos.

---

## Sumário

1. [Prioridade 1 — Retenção e Plano Pro](#1-prioridade-1--retenção-e-plano-pro)
2. [Prioridade 2 — Expansão do Motor e Recomendações](#2-prioridade-2--expansão-do-motor-e-recomendações)
3. [Prioridade 3 — Canais e Distribuição](#3-prioridade-3--canais-e-distribuição)
4. [Prioridade 4 — Integração e API](#4-prioridade-4--integração-e-api)
5. [Prioridade 5 — Mobile e Internacionalização](#5-prioridade-5--mobile-e-internacionalização)
6. [Prioridade 6 — Back-office e Administração](#6-prioridade-6--back-office-e-administração)
7. [Prioridade 7 — Segurança e Conformidade Avançada](#7-prioridade-7--segurança-e-conformidade-avançada)
8. [Tabela Consolidada](#tabela-consolidada)

---

## 1. Prioridade 1 — Retenção e Plano Pro

### 1.1 Compartilhamento N:M de Empresa Analisada entre Usuários

- **Descrição.** Permitir que uma **mesma Empresa Analisada** seja vinculada a mais de um Usuário, com papéis (Owner / Editor / Viewer). Fluxo de convite: Owner envia convite por e-mail; convidado aceita e ganha acesso conforme papel atribuído. Caso de uso típico: a Joana (dona) convida o próprio contador Marcos para ter acesso de leitura aos diagnósticos da empresa dela, sem precisar duplicar o cadastro.
- **Motivação.** Reduz fricção de adoção em cenários colaborativos (dono + contador), evita duplicação de cadastro da mesma empresa em contas diferentes e fortalece o caso de uso B2B2C de aquisição cruzada (Joana descobre o produto via Marcos, ou vice-versa).
- **Complexidade:** Média (modelo de dados aditivo — nova tabela `UsuarioEmpresa` com `papel`; fluxo de convite por e-mail; ajustes de autorização no backend; nova tela de "Compartilhar empresa").
- **No MVP.** Cada Empresa Analisada pertence a **um único** Usuário (FK `EmpresaAnalisada.usuario_id`). A migração para N:M é puramente aditiva: a FK passa a representar "Usuário criador/Owner" e a nova tabela `UsuarioEmpresa` adiciona os relacionamentos extras.
- **Horizonte:** **v1.2**.

> **Nota histórica.** A v2.0 desta especificação tinha como item §1.1 do roadmap o "Portfólio multi-cliente do plano Pro" (Usuário → N Empresas Analisadas). Esse item foi **incorporado ao MVP** na v2.1 (17/05/2026) como parte do modelo de domínio base — ver §6.1 da especificação funcional, RESOLVIDO. O que permanece no roadmap é apenas a dimensão **colaborativa** (compartilhamento entre Usuários, N:M), descrita acima.

### 1.2 Funcionalidades de retenção e engajamento

- **Descrição.** Metas por indicador (usuário define objetivo numérico e a plataforma acompanha), alertas de deterioração (notifica por e-mail quando um indicador piora vs. último diagnóstico), lembretes mensais de novo diagnóstico, dashboard de evolução (gráficos de linha de 6 e 12 meses).
- **Motivação.** Mitigação do risco R7 do parecer CLAUDE — churn alto no segmento SaaS-MPE no Brasil (25 a 40% ao ano). Funcionalidades que criam hábito são determinantes para retenção.
- **Complexidade:** Média.
- **Horizonte:** **v1.1** (parcial, metas + alertas) / **v1.2** (dashboard completo).

### 1.3 Onboarding ativo (e-mails e webinar)

- **Descrição.** Sequência de e-mails D+2 e D+5 após o primeiro diagnóstico com interpretação guiada do relatório e case de uso. Webinar quinzenal "Como ler seu diagnóstico" para trialists e novos assinantes.
- **Motivação.** Mitigação do risco R2 — conversão trial → pago. O "aha moment" do DEF é longitudinal; onboarding ativo é necessário para encurtar o tempo até o valor percebido.
- **Complexidade:** Baixa (fluxo de e-mail marketing + operação de webinar).
- **Horizonte:** **v1.1** (e-mails automatizados pós-MVP).

### 1.4 Mecanismo de feedback por recomendação

- **Descrição.** Cada indicador do relatório exibe botão 👍 / 👎 ("Esta recomendação faz sentido para o seu caso?"). Dados alimentam ciclo de melhoria da matriz DEZ/2025.
- **Motivação.** Mitigação do risco R4 — matriz com faixas ou textos inadequados. Canal direto de feedback qualificado.
- **Complexidade:** Baixa.
- **Horizonte:** **v1.1**.

---

## 2. Prioridade 2 — Expansão do Motor e Recomendações

### 2.1 Medianas setoriais (benchmark anônimo)

- **Descrição.** A partir do banco de dados dos DEFs realizados, calcular medianas por **segmento × UF × porte** e exibir no relatório uma coluna extra: "Como você se compara à mediana do seu setor".
- **Motivação.** Ponderação 9 da EBC. Diferencial competitivo forte e fosso de defesa contra concorrentes que não acumulam dados.
- **Complexidade:** Média (dados existem; exige design estatístico, anonimização, UX de comparativo).
- **Dependência:** amostra mínima por célula de segmentação. Regra prudente: ≥ 30 diagnósticos no cruzamento antes de exibir a mediana.
- **Horizonte:** **v1.2** (após 6 meses de go-live, quando a amostra estiver madura).

### 2.2 Cálculo automático da análise de captação

- **Descrição.** Aplicar as fórmulas da aba DEF (linhas 109–114) para calcular automaticamente o valor viável de captação (menor entre: metade do endividamento total, 3× vendas com cartão, 1/3 do patrimônio livre, valor declarado pelo usuário). Mantém fluxo manual como fallback para casos complexos.
- **Motivação.** Parecer CLAUDE (pg. 4, §2.3) e mitigação do risco R9 — análise manual vira gargalo à medida que o volume cresce.
- **Complexidade:** Média (fórmulas prontas; falta UX e integração com o fluxo do pedido).
- **Horizonte:** **v1.1**.

### 2.3 Análise preditiva com IA

- **Descrição.** Modelo de ML que identifica tendências de deterioração ou melhoria com base no histórico do usuário, antecipando alertas ("seus indicadores sugerem aperto de caixa nos próximos 60 dias").
- **Motivação.** Diferencial premium, possibilita tier adicional de assinatura.
- **Complexidade:** Alta (requer volume de dados, engenharia de features, validação estatística e UI diferenciada).
- **Horizonte:** **v2.0**.

### 2.4 Novos indicadores e evolução da matriz

- **Descrição.** Ampliar além dos 14 indicadores atuais (ex.: EBITDA ajustado, indicadores de qualidade de crédito, produtividade por funcionário). Revisar e expandir a matriz de recomendações com base no feedback 👍 / 👎 coletado.
- **Complexidade:** Média.
- **Horizonte:** **v1.2** em diante, incremental.

---

## 3. Prioridade 3 — Canais e Distribuição

### 3.1 Portais de Parceiros Patrocinadores

- **Descrição.** Sebrae, Banco do Brasil, BNB, CDLs, Federações Comerciais podem comprar lotes de diagnósticos e distribuir via cupons/convites aos seus associados. Portal permite geração de cupons, relatórios agregados de uso (anonimizados), white-label parcial (logo do parceiro no relatório).
- **Motivação.** Canal B2B2C de alta escala, com aquisição pagável pelo parceiro em vez do usuário final.
- **Complexidade:** Alta.
- **Horizonte:** **v1.2** (pilotos com 1–2 parceiros) / **v2.0** (portal completo).

### 3.2 Programa de Afiliados / Parceiros Comerciais

- **Descrição.** Cadastro de afiliado, link de indicação com tracking, painel de comissões, regras de payout.
- **Motivação.** Baixo custo de aquisição para quem adere. Especialmente adequado a contadores e consultores que já promovem o DEFOnline.
- **Complexidade:** Média.
- **Horizonte:** **v1.2**.

### 3.3 B2B2C Patrocinado (gratuito para beneficiário final)

- **Descrição.** Parceiro patrocina o diagnóstico; beneficiário final acessa sem custo com código de cupom.
- **Motivação.** Desdobramento natural dos portais de parceiros.
- **Complexidade:** Média (requer lógica de carteira patrocinada, relatórios de consumo).
- **Horizonte:** **v1.2**.

### 3.4 Estratégia B2B dedicada para contadores (CRCs e associações)

- **Descrição.** Ação comercial estruturada junto aos Conselhos Regionais de Contabilidade, associações de escritórios contábeis (Sindicontas), plataformas de capacitação contábil. Apresentações, ofertas especiais, co-marketing.
- **Motivação.** Parecer CLAUDE (pg. 5) — canal de maior alavancagem do produto. Sem abordagem B2B ativa, o Pro fica subutilizado.
- **Complexidade:** Operacional (não é código, é go-to-market).
- **Horizonte:** **v1.1** (iniciar 30 dias pós-MVP, em paralelo ao desenvolvimento técnico).

---

## 4. Prioridade 4 — Integração e API

### 4.1 Persona 5 — Fornecedores de Crédito (módulo B2B)

- **Descrição.** API autenticada para que bancos, fintechs, cooperativas e factorings possam consumir diagnósticos de MPEs tomadoras de crédito, via integração com seus fluxos de onboarding. Requer: API REST com OAuth2/API keys, SLA contratual, DPA específico, base legal LGPD (legítimo interesse do fornecedor + consentimento do tomador), faturamento B2B por consumo.
- **Motivação.** Levantada pela revisão EBC (22/04/2026) como possível Persona 5. **Fora do MVP** porque exige produtos adicionais não compartilhados com a jornada de autosserviço e implica adequação jurídica adicional significativa.
- **Complexidade:** Alta.
- **Horizonte:** **v2.0**.
- **Dependências:** base de usuários consolidada (≥ 10.000 diagnósticos), maturidade operacional, jurídico.

### 4.2 Integrações com contabilidade e ERP

- **Descrição.** Importação automática de dados contábeis a partir de Omie, Conta Azul, TOTVS, Bling, outros, reduzindo atrito no preenchimento do quiz e melhorando qualidade da entrada.
- **Motivação.** Desafio mais difícil do MVP conforme parecer CLAUDE (pg. 6, §2.3) — qualidade dos dados. Integração com ERP é solução estrutural.
- **Complexidade:** Alta (cada integração é um projeto, com versões de API diferentes).
- **Horizonte:** **v1.2** em diante, **incremental** — começar por 1 ERP (Conta Azul ou Omie, conforme volume de uso observado).

### 4.3 API pública (developer portal)

- **Descrição.** Documentação OpenAPI, chaves de API, sandbox, quotas, portal de desenvolvedor.
- **Motivação.** Habilita ecossistema de terceiros (plugins, consultorias, ferramentas internas de contadores Pro).
- **Complexidade:** Alta.
- **Horizonte:** **v2.0**.

---

## 5. Prioridade 5 — Mobile e Internacionalização

### 5.1 Apps móveis nativos (iOS / Android)

- **Descrição.** Apps nativos com notificações push (para lembretes e alertas), preenchimento offline de rascunho, visualização de relatórios.
- **Motivação.** Observação EBC (pg. 5, §1.2): previsão futura. Experiência móvel responsiva do MVP é suficiente para cadastro e consumo de relatório, mas app permite notificações push (crítico para retenção).
- **Complexidade:** Alta.
- **Horizonte:** **v2.0**.

### 5.2 Internacionalização (i18n): francês, espanhol, inglês

- **Descrição.** Tradução do hotsite, da aplicação logada, do quiz e do relatório. Localização de moeda, formatação de números e datas. Ajuste de recomendações para contextos diferentes (fiscalidade, práticas de mercado).
- **Motivação.** Observação EBC (pg. 5, §1.1 e pg. 6, Anexo J). Expansão para outros mercados latinos e para nichos francófonos/anglófonos.
- **Complexidade:** Média (arquitetura prevê i18n desde o início — Seção 5.5 de `arquitetura-tecnica.md` — evita retrabalho); expansão real depende de validação mercadológica.
- **Horizonte:** **v2.0**, condicionado a validação comercial em mercado-piloto.

---

## 6. Prioridade 6 — Back-office e Administração

### 6.1 Back-office EB Parcerias

- **Descrição.** Console interno para gestão de assinaturas, faturamento, inadimplência, emissão de NFe, CMS para edição dos textos de recomendações/sugestões/FAQ, dashboards operacionais. Substitui scripts ad-hoc e planilhas.
- **Motivação.** À medida que a base cresce, operação manual vira gargalo.
- **Complexidade:** Alta.
- **Horizonte:** **v1.2**.

### 6.2 CMS leve do hotsite e FAQ

- **Descrição.** Interface para editar conteúdo do hotsite (landing, blog), textos da Central de Ajuda, e templates de e-mail sem dependência de deploy.
- **Complexidade:** Média.
- **Horizonte:** **v1.1**.

### 6.3 Gestão de conteúdo do blog (SEO)

- **Descrição.** Publicação de 15–20 artigos fundamentais antes do go-live (parecer CLAUDE §2.2), com continuidade de 2–4 artigos/mês. Integração com SEO (schema.org, sitemap, meta tags dinâmicas).
- **Motivação.** Tração orgânica — reduz CAC ao longo do tempo.
- **Complexidade:** Operacional (conteúdo) + Baixa (infraestrutura de blog).
- **Horizonte:** **pré-go-live** (publicação inicial) e **contínuo**.

---

## 7. Prioridade 7 — Segurança e Conformidade Avançada

### 7.1 Autenticação Multifator (MFA)

- **Descrição.** TOTP (Google Authenticator, Authy) opcional; SMS como fallback. Obrigatório para contas Pro quando o portfólio multi-cliente for lançado.
- **Motivação.** Proteção de contas com dados sensíveis de terceiros (Pro).
- **Complexidade:** Média.
- **Horizonte:** **v1.2**.

### 7.2 Chat/WhatsApp como canal de suporte

- **Descrição.** Atendimento via WhatsApp Business API e chat web in-app.
- **Motivação.** Observação EBC — canal preferencial do público-alvo (Joana, Roberto).
- **Complexidade:** Média.
- **Horizonte:** **v1.2**.

### 7.3 Gamificação e engajamento

- **Descrição.** Badges por consistência, metas alcançadas, convites a amigos. Complementa as funcionalidades de retenção (§1.2).
- **Complexidade:** Média.
- **Horizonte:** **v2.0**.

### 7.4 Estimativa e acompanhamento de custos fixos da plataforma

- **Descrição.** Ponderação 10 da EBC. Montar planilha viva de custos: registro de domínio, hospedagem, TLS, storage/CDN, redundância/espelho, gateway de pagamento, provedor de e-mail, APM/logs, APIs contratadas (RFB, antifraude). Revisão trimestral.
- **Motivação.** Previsibilidade financeira e base para decisões de precificação.
- **Complexidade:** Operacional.
- **Horizonte:** **pré-go-live** (primeira versão) e **revisão trimestral**.

---

## Tabela Consolidada

| # | Iniciativa | Horizonte | Complexidade | Origem |
|---|---|---|---|---|
| 1.1 | Compartilhamento N:M de Empresa Analisada entre Usuários | v1.2 | Média | Decisão v2.1 (17/05/2026) |
| 1.2 | Retenção e engajamento (metas, alertas) | v1.1 / v1.2 | Média | CLAUDE (R7) |
| 1.3 | Onboarding ativo (e-mails, webinar) | v1.1 | Baixa | CLAUDE (R2) |
| 1.4 | Feedback 👍 / 👎 por recomendação | v1.1 | Baixa | CLAUDE (R4) |
| 2.1 | Medianas setoriais | v1.2 | Média | EBC (pond. 9) |
| 2.2 | Cálculo automático da análise de captação | v1.1 | Média | CLAUDE (R9) + EBC |
| 2.3 | Análise preditiva com IA | v2.0 | Alta | EBC (roadmap) |
| 2.4 | Novos indicadores e evolução da matriz | v1.2+ | Média | Contínuo |
| 3.1 | Portais de Parceiros Patrocinadores | v1.2 / v2.0 | Alta | Spec v1 |
| 3.2 | Programa de Afiliados | v1.2 | Média | Spec v1 |
| 3.3 | B2B2C Patrocinado | v1.2 | Média | Spec v1 |
| 3.4 | Estratégia B2B para contadores (CRCs) | v1.1 | Operacional | CLAUDE (pg. 5) |
| 4.1 | Persona 5 — módulo B2B fornecedores de crédito | v2.0 | Alta | EBC (pg. 1) |
| 4.2 | Integrações com ERP (Omie, Conta Azul) | v1.2+ | Alta | CLAUDE (pg. 6) |
| 4.3 | API pública | v2.0 | Alta | Expansão |
| 5.1 | Apps móveis nativos iOS/Android | v2.0 | Alta | EBC (pg. 5) |
| 5.2 | i18n (fr, es, en) | v2.0 | Média | EBC (pg. 5 e 6) |
| 6.1 | Back-office EB Parcerias | v1.2 | Alta | Spec v1 |
| 6.2 | CMS leve (hotsite, FAQ, e-mails) | v1.1 | Média | Spec v1 |
| 6.3 | Blog SEO (15–20 artigos) | pré-go-live + contínuo | Operacional | CLAUDE (pg. 6) |
| 7.1 | MFA | v1.2 | Média | CLAUDE (roadmap) |
| 7.2 | Chat/WhatsApp suporte | v1.2 | Média | EBC |
| 7.3 | Gamificação | v2.0 | Média | Spec v1 |
| 7.4 | Estimativa de custos fixos | pré-go-live + trimestral | Operacional | EBC (pond. 10) |

---

**Fim do documento — Roadmap Pós-v1 v1.0 (draft)**

*Priorização orientativa; ajustes após o beta fechado e o primeiro trimestre pós-go-live, quando métricas reais (conversão trial → pago, churn, NPS, tempo de preenchimento) informarão o replanejamento.*
