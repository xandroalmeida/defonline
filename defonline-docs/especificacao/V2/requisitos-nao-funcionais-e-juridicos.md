# DEFOnline — Requisitos Não-Funcionais e Jurídicos (Bloco 3)

**Projeto:** Plataforma DEFOnline
**Versão do documento:** 2.0 (reset de stack — decisões de infra e ferramentas removidas)
**Data:** 19/05/2026
**Responsáveis:** EB Parcerias Ltda
**Documento pai:** [`especificacao-funcional.md`](especificacao-funcional.md) (Seção 7)
**Escopo:** consolidar os requisitos **não-funcionais** (disponibilidade, performance, segurança de dados, qualidade, observabilidade, contingência) e os pontos **jurídicos** (Termo de Adesão, base legal LGPD, responsabilidade técnica do diagnóstico) identificados pelas revisões de 20–22/04/2026.

> Este documento é **neutro em relação a linguagem, framework, provedor de nuvem e ferramentas operacionais**. As únicas decisões técnicas firmes mantidas aqui são (a) **PostgreSQL** como banco relacional e (b) **TDD obrigatório** com **testes E2E** nos fluxos críticos. Todas as demais escolhas (provedor de VPS/cloud, container runtime, observabilidade, CI/CD, ORM, framework de aplicação) serão redefinidas na nova rodada de arquitetura técnica de construção.

---

## Sumário

1. [Requisitos de Disponibilidade e Performance (SLA)](#1-requisitos-de-disponibilidade-e-performance-sla)
2. [Infraestrutura de Hospedagem e Operação](#2-infraestrutura-de-hospedagem-e-operação)
3. [Integrações Externas Críticas](#3-integrações-externas-críticas)
4. [Segurança de Dados e Criptografia](#4-segurança-de-dados-e-criptografia)
5. [Backup, Retenção e Plano de Contingência](#5-backup-retenção-e-plano-de-contingência)
6. [Observabilidade, Logs e Alertas](#6-observabilidade-logs-e-alertas)
7. [LGPD — Base Legal Estendida](#7-lgpd--base-legal-estendida)
8. [Termo de Adesão — Premissas para Revisão Jurídica](#8-termo-de-adesão--premissas-para-revisão-jurídica)
9. [Gestão de Risco Técnico do Diagnóstico (TDD e E2E)](#9-gestão-de-risco-técnico-do-diagnóstico-tdd-e-e2e)
10. [Critérios de Aceite para Kickoff](#10-critérios-de-aceite-para-kickoff)

---

## 1. Requisitos de Disponibilidade e Performance (SLA)

### 1.1 Disponibilidade (uptime)

- **Uptime alvo (produção):** 99,5% mensal, excluída janela de manutenção programada anunciada com no mínimo 48h de antecedência. `[DECIDIR]` — validar se o contrato com hospedagem e o custo operacional comportam 99,9%.
- **Janela de manutenção programada:** até 2h por mês, preferencialmente em madrugada de domingo (02:00–04:00 BRT).
- **Definição de indisponibilidade:** incapacidade de login, de preencher o quiz, de gerar relatório ou de visualizar histórico por mais de 5 minutos consecutivos, para a maioria dos usuários.

### 1.2 Performance

| Operação | Meta de tempo de resposta (p95) |
|---|---|
| Página carregada (hotsite, Home) | < 2,0 s em conexão 4G típica |
| Login | < 1,5 s |
| Gravação de passo do quiz (rascunho) | < 800 ms |
| Envio final do quiz + execução do motor + persistência | < 3,0 s |
| Renderização da tela de relatório | < 2,0 s |
| Geração do PDF do relatório | < 10,0 s (assíncrono com feedback visual) |
| Consulta de histórico (12 meses) | < 1,5 s |

### 1.3 Capacidade concorrente

- **Usuários concorrentes suportados:** 300 no MVP (dimensionamento inicial para o SOM de 5.000–20.000 assinantes dos primeiros 24 meses). Escalabilidade horizontal projetada para 1.500 concorrentes sem rearquitetura.
- **Execuções concorrentes do motor de diagnóstico:** 50 sem degradação de tempo de resposta.

### 1.4 Compatibilidade e responsividade

- Navegadores: Chrome, Edge, Firefox, Safari — versões atuais e N-1.
- Responsivo desktop (≥ 1280px), tablet (768–1279px), mobile (360–767px).
- Acessibilidade: WCAG 2.1 nível AA como meta; conformidade nível A obrigatória para v1.

---

## 2. Infraestrutura de Hospedagem e Operação

> Esta seção descreve **expectativas e princípios** de infraestrutura. As escolhas concretas (provedor, runtime, orquestração, reverse proxy, ferramentas de filas, cache) ficam para a nova rodada de arquitetura técnica de construção.

### 2.1 Hospedagem `[A DEFINIR]`

- **Residência de dados:** **Brasil** — atende LGPD sem necessidade de TIA (Transfer Impact Assessment). Provedor cujo datacenter seja exclusivamente fora do Brasil exige TIA documentado na Política de Privacidade — evitar no MVP.
- **Ambientes:** produção, homologação (staging que espelha produção em escala reduzida) e desenvolvimento local de cada dev.
- **TLS** válido em todas as URLs públicas; HSTS habilitado.
- **Reverse proxy** na borda com regras de rate limiting nos endpoints sensíveis (`/auth/login`, `/auth/forgot`, `/cadastro`).
- **Patches de SO** automatizados para correções críticas.

### 2.2 Banco de Dados `[DECIDIDO]`

- **Engine:** **PostgreSQL** (versão a ser definida na spec de construção, com piso na versão estável majoritária no momento do kickoff).
- **Estrutura:** relacional convencional, com isolamento por FK + autorização — sem `tenant_id` global. Espinha dorsal **Usuario** (1) → **EmpresaAnalisada** (N); ver `arquitetura-tecnica.md` §4 para detalhe do modelo.
- **Filas e jobs assíncronos:** estratégia a definir na spec de construção. Cobertura mínima exigida: geração assíncrona de PDF, envio de e-mail, expiração de rascunho (90 dias parametrizável), retenção de diagnósticos (12 meses com aviso D-7), anonimização LGPD, retry de cobrança.
- **Backup off-site:** dump diário (cron 03:00 BRT) → encriptação → upload para storage off-site em provedor distinto do que hospeda o banco. Retenção: 30 dias rolantes + mensal por 12 meses. Detalhes em §5.1.
- **Restore testado:** trimestralmente em staging, com dump real anonimizado.

### 2.3 Armazenamento de objetos `[A DEFINIR]`

- **PDFs (produção):** armazenados em storage com URLs de download assinadas pela API com validade curta (ex.: 15 min).
- **PDFs (resiliência):** o conteúdo dos PDFs é regenerável a partir do quiz + diagnosis persistidos no banco — perda do storage é apenas perda de cache.
- **Backup do PostgreSQL:** storage off-site separado do storage de PDFs e do banco principal (boa prática).
- **Retenção dos PDFs:** alinhada à janela de 12 meses rolantes da especificação funcional (§4.8). Job diário exclui PDFs vencidos.

### 2.4 CDN e proteção de borda `[A DEFINIR]`

- **CDN** para assets estáticos com cache na borda e revalidação adequada.
- **WAF básico** e **mitigação de DDoS** na borda.
- Respostas dinâmicas da API com `Cache-Control: no-store`.

### 2.5 Cache de aplicação `[A DEFINIR]`

- Cobertura mínima: dados raramente alterados (matriz de recomendações, faixas Anexo E, FAQ).
- Decisão entre cache in-process e cache distribuído fica para a spec de construção (depende da estratégia de horizontal scaling).

---

## 3. Integrações Externas Críticas

### 3.1 API da Receita Federal (consulta CNPJ)

- **Objetivo:** enriquecer cadastro com Razão Social, Nome Fantasia, CNAE, Município, UF, Data de Fundação, Situação Cadastral.
- **Fallback robusto** (conforme mitigação R5 do parecer CLAUDE): em caso de indisponibilidade da API, permitir preenchimento manual com aviso explícito.
- **Provedor da API:** **abstração** `RfbCnpjClient` (PHP interface) com **dois provedores reais suportados**: `cnpja` (`https://cnpja.com/`) e `receitaws` (`https://receitaws.com.br/`). Seleção via configuração (`config/services.php` → bloco `rfb`, chave `provider` — valores aceitos: `mock | cnpja | receitaws`). Mock determinístico permanece como default em `local`/`testing`. Decisão registrada na **IDR-004**; ativação dos provedores reais entregue pela **STORY-018**. Critérios mantidos: custo, confiabilidade, limites de chamadas — agora avaliáveis empiricamente via métricas com dimensão `provider`.
- **Rate-limit por provedor:** cada provedor tem RPM (requests por minuto) **configurável independentemente** (`config/services.php` → `rfb.providers.<provider>.rate_limit_per_minute`). Defaults conservadores baseados no plano gratuito público de cada um (3 RPM); valores reais são sobrescritos via `.env` conforme plano contratado. Implementação via `Illuminate\Support\Facades\RateLimiter` com chave `rfb:provider:{provider}`.
- **Monitoramento:** alarme se taxa de erro > 5% em janela de 10 min, **por provedor** (dimensão `provider` em `business_metrics`).

### 3.2 Gateway de Pagamento

- **Requisitos funcionais:** cartão de crédito com recorrência tokenizada; geração de QR Code Pix com expiração de 30 minutos; webhook de confirmação; retentativas automáticas em falha de cobrança recorrente (até 3 em intervalos de 48h); emissão de recibo/nota fiscal (pela EB Parcerias — parte de back-office, fora do código do MVP).
- **Provedor:** `[A DEFINIR]`. Critérios: custo por transação, suporte a recorrência + Pix, webhooks confiáveis, conformidade PCI. Não bloqueia o kickoff — desenvolvimento pode iniciar com sandbox.
- **PCI-DSS:** plataforma DEFOnline **não armazena dados de cartão** — todos ficam no vault do gateway (tokenização).

### 3.3 Provedor de E-mail Transacional `[A DEFINIR]`

- **Requisitos:** DKIM/SPF/DMARC configurados em subdomínio dedicado (ex.: `mail.defonline.com.br`); reputação separada para tráfego transacional e tráfego de marketing; taxa de entrega > 98%.
- **Separação de trilhas:** e-mails transacionais e de marketing enviados em subdomínios diferentes para isolar reputação.

### 3.4 Observabilidade / APM `[A DEFINIR]`

- **Erros (BE e FE):** ferramenta centralizada de captura de exceções com projetos separados por aplicação (api, área logada, hotsite). Alertas para erros 5xx persistentes, falhas de webhook de pagamento, falhas de envio de e-mail crítico.
- **Métricas e logs:** logs estruturados (JSON) com retenção mínima de 30 dias online e exportação para storage frio por 12 meses.
- **APM e traces distribuídos:** instrumentação padronizada (preferência por padrão aberto e portátil entre fornecedores) — escolha da ferramenta de visualização fica para a spec de construção.

---

## 4. Segurança de Dados e Criptografia

### 4.1 Criptografia em trânsito

- **TLS 1.2 mínimo, 1.3 preferencial** em todas as conexões públicas.
- HSTS habilitado com preload para o domínio da aplicação.
- Certificados gerenciados (ACME ou gerenciado pela CDN/provedor).

### 4.2 Criptografia em repouso

- **Banco de dados:** criptografia nativa de volume desejável; quando não disponível na hospedagem escolhida, dados sensíveis críticos (senhas em hash resistente, tokens armazenados pelo gateway de pagamento, chaves de API em segredo de ambiente) são protegidos por design e a análise de risco fica registrada como decisão consciente.
- **Backups:** dump **encriptado** antes do upload para storage off-site; chave privada custodiada fora da máquina de produção.
- **PDFs:** não contêm CPF nem dado financeiro sensível além do que o usuário escolheu compartilhar; perda do storage é tratável (regeneração).
- **Storage off-site:** criptografia em repouso nativa do provedor (server-side encryption).

### 4.3 Autenticação e autorização

- Senhas armazenadas em hash com algoritmo moderno resistente a brute force, parametrizado para custo computacional adequado, nunca em plain text.
- Sessões com tokens assinados (com expiração curta + mecanismo de renovação) ou cookies de sessão seguros (HttpOnly, Secure, SameSite=Lax).
- **Isolamento rígido por `usuario_id`** em todas as queries autenticadas — testes automatizados de autorização cruzada (usuário A não acessa dado de usuário B → 404) obrigatórios em **cada endpoint** que retorne recurso pertencente a usuário (ver §9).

### 4.4 Proteções comuns

- Rate limiting em endpoints sensíveis (login, redefinição de senha, cadastro).
- CAPTCHA no cadastro (anti-fraude — mitigação complementar ao bloqueio por CPF/CNPJ duplicado, risco R8).
- Bloqueio temporário após 5 tentativas falhas de login em 10 min.
- Headers de segurança: CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy.

### 4.5 Segurança no ciclo de desenvolvimento

- Análise estática de dependências (SCA) no pipeline (detecção de CVEs).
- Análise de código (SAST) em PRs.
- Secrets fora do repositório (em secret manager / equivalente).
- Code review obrigatório com 1 aprovador mínimo para a branch principal.

---

## 5. Backup, Retenção e Plano de Contingência

### 5.1 Política de backup (detalhe)

- **Dump diário** automatizado via cron, executado às **03:00 BRT**, encriptado e enviado para storage off-site (em provedor distinto do que hospeda o banco).
- **Retenção:** 30 dias rolantes no storage off-site; cópia mensal arquivada por mais 12 meses.
- **Snapshot do volume da máquina de banco:** semanal, quando disponível na hospedagem escolhida.
- **Restore testado:** trimestral em staging com dump real anonimizado.
- **PITR (Point-in-Time Recovery):** desejável; entra como evolução quando a infraestrutura suportar WAL streaming.

### 5.2 RPO / RTO alvo

- **RPO (Recovery Point Objective):** **24 horas no MVP** (perda máxima de dados em caso de desastre total — backup diário). Meta de evolução: 1h, quando a infraestrutura permitir PITR.
- **RTO (Recovery Time Objective):** **~4 horas em caso de desastre total** (provisionar novo ambiente, restaurar dump mais recente, re-deploy via pipeline). **~30 min para falhas parciais** (restart de container, rollback de release).
- **Aceitação do risco:** o RPO de 24h é uma limitação aceita no MVP. Se houver perda de dados entre o último backup e o desastre, comunicação transparente aos Usuários afetados é obrigatória.

### 5.3 Cenários de contingência

| Cenário | Impacto | Plano |
|---|---|---|
| Queda da API da RFB | Cadastro sem preenchimento automático | Banner no cadastro + fluxo manual com aviso (conforme Seção 4.1 da especificação funcional) |
| Falha do gateway de pagamento | Assinaturas novas e renovações bloqueadas | Exibir mensagem ao usuário; retry automático em 5/15/30 min; avisar equipe por alerta |
| Falha do provedor de e-mail | Usuários não recebem confirmação/notificações | Fallback configurado para provedor secundário (futuro pós-MVP); no MVP, retry + alerta |
| Falha de banco de dados | Plataforma indisponível | Restore a partir do último backup ou PITR; comunicação aos usuários |
| Ataque/vazamento | Incidente LGPD | Playbook de resposta a incidente: contenção em ≤ 2h, comunicação à ANPD em 72h se aplicável |
| Corrupção de dados por bug | Diagnóstico inconsistente | Script de reprocessamento a partir do quiz preservado; comunicação aos afetados |

### 5.4 Ponto cego levantado na revisão

A **ponderação 3 da EBC** pede "identificação de pontos cegos do produto". O plano de contingência deve ser revisitado trimestralmente em sessão estruturada com o time técnico — item recorrente do calendário operacional.

---

## 6. Observabilidade, Logs e Alertas

### 6.1 Logs

- **Nível mínimo:** INFO em produção, DEBUG em staging.
- **Formato:** estruturado (JSON), com correlação por `usuario_id`.
- **Retenção:** 90 dias online; exportação para cold storage por 12 meses.
- **PII em logs:** proibido (CPF, CNPJ integrais, e-mail completo, senha, token cru ou hash de token). Uso obrigatório de máscaras ou identificadores internos.

### 6.2 Métricas operacionais

- Request rate, latência (p50/p95/p99), taxa de erro por endpoint.
- Tempo de execução do motor de diagnóstico.
- Tempo de geração de PDF.
- Taxa de sucesso do gateway de pagamento.
- Taxa de entrega do provedor de e-mail.
- Fila/backlog de jobs assíncronos.
- Conexões ativas no PostgreSQL.

### 6.3 Métricas de produto (instrumentação analítica)

- Taxa de completude do quiz por passo (detecção de abandono).
- Tempo médio de preenchimento do quiz (meta: < 15 min para Persona 1 — Joana).
- Conversão trial → assinatura (alinhado ao risco R2 do parecer CLAUDE).
- Churn mensal e anual (alinhado ao R7).

### 6.4 Alertas

- Uptime < 99,5% em janela de 30 min → alerta P1 (on-call).
- Taxa de erro > 5% em endpoint crítico → alerta P1.
- Fila de geração de PDF com backlog > 50 → alerta P2.
- Falha de pagamento > 10% em 1h → alerta P2.
- Ataque detectado (rate limit estourado por mais de 5 IPs simultâneos no login) → alerta P1.

---

## 7. LGPD — Base Legal Estendida

A especificação funcional cobre os aceites e os direitos do titular. Este documento consolida os pontos adicionais identificados pela revisão.

### 7.1 Base legal por finalidade

| Finalidade | Base legal |
|---|---|
| Execução do contrato (quiz, diagnóstico, cobrança) | Execução de contrato — Art. 7º, V |
| Comunicações de marketing | Consentimento — Art. 7º, I (opt-in desacoplado) |
| Comunicações transacionais | Execução de contrato |
| Dados de navegação (analytics) | Legítimo interesse — Art. 7º, IX, com opt-out via banner de cookies |
| Anonimização pós-exclusão | Obrigação legal / legítimo interesse (auditoria) |

### 7.2 Usuário Pro com Empresa Analisada de terceiros

Quando um Usuário (tipicamente contador ou consultor — Persona 3, Marcos) cadastra uma **Empresa Analisada que não é a própria** (ex.: Marcos cadastra a loja da Joana), a plataforma precisa de **base legal específica** para o tratamento dos dados financeiros dessa Empresa Analisada. Modelo adotado:

- O **Usuário é o controlador** dos dados da Empresa Analisada vinculada à sua conta nessas condições.
- A **EB Parcerias é operadora** — trata os dados exclusivamente conforme instrução do controlador (Usuário).
- Um **DPA simplificado (Data Processing Agreement)** é incorporado ao Termo de Adesão do plano Pro, com cláusulas sobre: finalidade, retenção, medidas de segurança, sub-operadores (provedor de nuvem etc.), transferência internacional (se aplicável) e notificação de incidentes.
- O Usuário declara, no **primeiro quiz de cada nova Empresa Analisada** vinculada à conta Pro, que **possui autorização expressa do titular** (sócio responsável da Empresa Analisada) para usar a plataforma em nome dele. Esse aceite é registrado em `TermAcceptance` com `termo_tipo = DPA-Pro` e `empresa_id` preenchido (ver `arquitetura-tecnica.md` §4.1).
- No plano Básico, ou no Pro quando a Empresa Analisada coincide com atividade do próprio Usuário, a base legal é simples execução de contrato — sem necessidade de DPA específico.

### 7.3 Retenção e anonimização

- Conta excluída → dados identificáveis anonimizados em D+30 corridos.
- Registros agregados (ex.: logs de auditoria LGPD, transações financeiras) retidos por período mínimo legal (5 a 10 anos dependendo do tipo).
- Diagnósticos anonimizados podem ser mantidos para composição da futura **base de medianas setoriais** (pós-v1) — base legal: legítimo interesse, com destaque na Política de Privacidade.

### 7.4 DPO e canal LGPD

- **DPO (Encarregado):** `[DECIDIR]` — indicar pessoa da EB Parcerias ou contratar DPO-as-a-service.
- Canal de contato: `dpo@ebparcerias.com` + formulário próprio no rodapé da plataforma.
- Prazos de resposta aos direitos do titular: 15 dias para solicitações simples; 30 dias prorrogáveis para complexas, conforme Art. 19 da LGPD.

---

## 8. Termo de Adesão — Premissas para Revisão Jurídica

O Termo de Adesão e a Política de Privacidade serão redigidos em versão definitiva por **advogado especializado em LGPD e em responsabilidade de plataformas de informação financeira** após a conclusão das regras de negócio. Este documento fixa as premissas que o jurídico deverá materializar.

### 8.1 Cláusulas obrigatórias

1. **Natureza do diagnóstico:** ferramenta **indicativa e automatizada**, não substitutiva de análise contábil profissional nem de parecer de consultor financeiro. O DEF não é decisão de crédito, concessão, recomendação de investimento ou laudo pericial.
2. **Responsabilidade pelos dados de entrada:** declaração expressa de que o **Usuário** é o **único responsável pela veracidade e qualidade das informações** declaradas no quiz para cada Empresa Analisada, e que a assertividade do diagnóstico depende dessa qualidade (reproduz o aviso já presente na tela inicial do quiz — Seção 3.5 da especificação funcional).
3. **Exoneração de responsabilidade por decisões de terceiros:** cláusula explícita de que decisões de investimento, captação, endividamento ou crédito tomadas com base no diagnóstico são de **exclusiva responsabilidade do usuário** e seus eventuais credores.
4. **Limitação de responsabilidade financeira:** teto indenizatório limitado ao **valor pago pelo usuário nos últimos 12 meses**, salvo nos casos de dolo ou culpa grave da EB Parcerias.
5. **Propriedade intelectual:** fórmulas, matrizes de recomendação, textos do relatório e marca DEF são propriedade da EB Parcerias Ltda. O usuário detém a propriedade dos **dados de entrada** e do **PDF do relatório final**.
6. **Uso dos dados anonimizados:** autorização para uso agregado e anonimizado em estudos, medianas setoriais e evoluções do produto.
7. **Arrependimento:** reiterar o direito de arrependimento de 7 dias (Art. 49 do CDC) para contratações à distância, condicionado à não-execução de análise.
8. **Foro:** definir foro competente e, para conflitos de menor complexidade, prever mediação/arbitragem online.

### 8.2 Cláusulas específicas do plano Pro (Usuário com Empresas Analisadas de terceiros)

- Aceite do DPA simplificado (ver §7.2).
- Declaração do Usuário de possuir autorização do titular da Empresa Analisada para uso dos dados financeiros na plataforma.
- Responsabilidade do Usuário por eventuais violações de privacidade da Empresa Analisada que ele cadastrou.
- Obrigação do Usuário de excluir a Empresa Analisada da plataforma quando deixar de prestar serviço ao titular.

### 8.3 Revisão contínua

- Termo de Adesão e Política de Privacidade **versionados**; alterações substanciais exigem novo aceite no próximo login.
- Revisão jurídica anual obrigatória, ou imediata em caso de mudança regulatória relevante (novas resoluções ANPD, alteração do CDC, etc.).

### 8.4 Documento editável

O jurídico receberá: (i) este documento de premissas; (ii) a especificação funcional v2 integral; (iii) as planilhas QUIZ, ESTRUTURA e RECOMENDAÇÕES; (iv) o parecer técnico CLAUDE.

---

## 9. Gestão de Risco Técnico do Diagnóstico (TDD e E2E)

### 9.1 Versionamento do motor (já previsto)

- Cada diagnóstico persistido guarda referência à **versão do motor de cálculo** e à **versão da matriz de recomendações** vigentes na data de geração — garante reprodutibilidade histórica e defesa técnica.

### 9.2 TDD obrigatório `[DECIDIDO]`

**TDD (Test-Driven Development) é regra de projeto inegociável.** Toda nova regra de negócio, fórmula de indicador, política de autorização, transição de estado de assinatura/carteira e regra de cálculo de cota/crédito é exercitada por testes automatizados escritos **antes** da implementação.

- Cada fórmula do motor (14 indicadores, balanço adaptado, DRE adaptada, classificação por faixa, algoritmo do Resumo Executivo) recebe no **mínimo 10 casos de teste** com valores conhecidos: caso típico, fronteiras das faixas do farol, denominador zero (`"Indisponível"`), valores negativos legítimos, valores ausentes, casos por setor (1=Indústria, 2=Comércio, 3=Serviços).
- Cada endpoint autenticado recebe um teste de **isolamento por `usuario_id`** ("usuário B não enxerga dado de usuário A → 404").
- A suíte de testes roda em cada PR; regressão bloqueia o merge.

### 9.3 Testes E2E obrigatórios `[DECIDIDO]`

Testes E2E rodam contra um ambiente que reproduza produção (build de produção da área logada e do hotsite, banco PostgreSQL real, mocks/sandbox dos provedores externos). A execução completa da suíte é critério de aceite para promoção de qualquer release a produção. Fluxos críticos cobertos obrigatoriamente:

1. Cadastro completo do Usuário (com e sem enriquecimento RFB) → confirmação de e-mail → primeiro login.
2. Cadastro de Empresa Analisada e preenchimento completo do quiz (23 perguntas) com rascunho intermediário e retomada.
3. Envio do quiz → geração do diagnóstico → exibição do relatório → geração e download do PDF.
4. Contratação de plano (Pix e cartão), confirmação via webhook do gateway, ativação da assinatura, consumo da cota.
5. Compra de pacote de créditos avulsos, expiração de lote FIFO e consumo na ordem correta.
6. Solicitação de análise de captação a partir de um diagnóstico existente.
7. Comparativo de dois diagnósticos da mesma Empresa Analisada (histórico).
8. Exclusão de conta com anonimização em cascata (LGPD).

Ferramenta de E2E, runner, estratégia de seed/teardown e ambiente de execução ficam para a spec de construção.

### 9.4 Validação de faixas por especialista

- As faixas da matriz DEZ/2025 já foram validadas pela EBC em 22/04/2026. Fica pendente **sessão adicional com especialista externo em finanças para MPEs** para revisão em segunda opinião — marcada como desejável no §2.2 do parecer CLAUDE.

### 9.5 Mecanismo de feedback no relatório

- Cada indicador do relatório deve ter um discreto botão "Esta recomendação faz sentido? 👍 / 👎". Dados coletados alimentam o ciclo de melhoria contínua da matriz de recomendações.

### 9.6 Beta estruturado

- Beta fechado com **20 a 30 usuários** representando as 3 personas (Joana, Roberto, Marcos), medindo: taxa de abandono por passo do quiz, tempo médio de preenchimento, dúvidas recorrentes, percepção de valor do relatório. Critério de go-live: tempo médio de preenchimento ≤ 15 min para Persona 1.

---

## 10. Critérios de Aceite para Kickoff

### 10.1 Critérios fechados (✅)

- [x] **Banco de dados** definido: **PostgreSQL** (§2.2).
- [x] **TDD obrigatório** com 10 casos mínimos por fórmula do motor + teste de isolamento por `usuario_id` em cada endpoint (§9.2).
- [x] **Testes E2E obrigatórios** para os 8 fluxos críticos definidos em §9.3.
- [x] **Item 6.1** da especificação funcional resolvido (modelo Usuário 1:N Empresa Analisada).
- [x] **Item 6.2** (algoritmo Resumo Executivo) — RESOLVIDO em §4.7.1 da especificação.
- [x] **Item 6.3** (NCG absoluto — limiar e exibição) — RESOLVIDO em §4.5 e Anexos D/E.
- [x] **Item 6.5** (cadência Persona 3 + cota e preço do Pro) — RESOLVIDO em §1.3.2 e §2.1.
- [x] **Preços** — viraram parametrização (entidades Plan, CreditPackage, Setting em `arquitetura-tecnica.md` §4.1). Valores citados na especificação funcional são seed inicial; alteração é decisão comercial pós-go-live, sem mexer em código.

### 10.2 Critérios pendentes para o novo kickoff de construção

A spec técnica de construção precisa fechar, **antes do início de implementação**:

- [ ] Linguagem de programação (backend e frontend).
- [ ] Framework de aplicação backend.
- [ ] Framework de aplicação frontend (área logada e hotsite).
- [ ] Estratégia de monorepo ou multi-repo.
- [ ] ORM / camada de acesso a dados.
- [ ] Mecanismo de fila / jobs assíncronos.
- [ ] Estratégia de cache.
- [ ] Biblioteca / estratégia de geração de PDF.
- [ ] Provedor de nuvem (incluindo formato: VPS, IaaS, PaaS, serverless).
- [ ] Orquestração / runtime de execução.
- [ ] Reverse proxy / TLS.
- [ ] Ferramenta de CI/CD.
- [ ] Ferramenta de captura de erros, ferramenta de métricas, monitor de uptime.
- [ ] Storage de PDFs (provedor).
- [ ] Storage de backup off-site (provedor).
- [ ] Provedor de e-mail transacional.
- [x] ~~Provedor da API CNPJ~~ — decidido via **IDR-004**: abstração `RfbCnpjClient` com dois provedores reais (`cnpja`, `receitaws`) selecionáveis via config + rate-limit por provedor. Ativação real entregue pela STORY-018.
- [ ] Gateway de pagamento.
- [ ] Ferramentas de teste E2E e de teste unitário/integração compatíveis com a stack escolhida.

### 10.3 Critérios pendentes (pré-go-live — podem rodar em paralelo)

- [ ] **Domínio `.com.br`** registrado no Registro.br.
- [ ] **DPO** formalmente nomeado (§7.4).
- [ ] **Termo de Adesão** e **Política de Privacidade** revisados por advogado especializado (§8). Dev pode rodar com placeholder.
- [ ] **Itens `[DECIDIR]` não-críticos** da Seção 6 da especificação funcional: 6.4, 6.6, 6.7, 6.8, 6.9, 6.10.
- [ ] **Sessão de validação de faixas** por especialista externo agendada (§9.4).
- [ ] **Playbook de resposta a incidente LGPD** redigido (§5.3 e §7).
- [ ] **Beta estruturado** organizado com 20 a 30 usuários das 3 personas (§9.6).
- [ ] **15 a 20 artigos de blog** publicados no hotsite (`roadmap-pos-v1.md` §6.3).

---

**Fim do documento — Requisitos Não-Funcionais e Jurídicos v2.0**

*Próximos passos: nova rodada de arquitetura técnica de construção para fechar a lista da §10.2; em paralelo, a EBC mantém os trabalhos jurídicos, de DPO e de validação externa das faixas (§10.3).*
