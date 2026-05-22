# Padrões de qualidade exigidos pelo PO

Este documento é referenciado por toda estória. Ele descreve o que o PO **exige** do time de desenvolvimento, sem prescrever como atingir (a tecnologia é decisão do Arquiteto/Programador).

## Por que estes padrões

DEFOnline lida com diagnóstico financeiro de empresas reais. Um erro de cálculo, vazamento de dado ou indisponibilidade prolongada custa a confiança de uma micro/pequena empresa que tem pouco a perder. Por isso a régua é alta — e é o PO que paga o preço de a baixar, então não baixe.

## 1. Testes automatizados

### 1.1 Cobertura unitária

- **Mínimo geral:** 80% de cobertura de linhas e branches no código novo de cada estória.
- **Núcleo e regras de negócio:** 98% de cobertura — isso inclui cálculos de diagnóstico, validações de regras, lógica de scoring, transformação de dados financeiros.
- Cobertura é medida no PR. Se cair abaixo da meta, o PR não merge.

### 1.2 Testes E2E

- Todo fluxo de usuário visível ao usuário final tem **pelo menos um cenário E2E** automatizado.
- **Frontend web:** os E2E rodam em browser real via automação (não simulação por unit). Não importa qual ferramenta — importa que o teste exerça o DOM real.
- **APIs sem frontend:** E2E é via cliente HTTP real chamando o serviço deployado em ambiente equivalente ao produtivo.
- Cenários cobrem caminho feliz + ao menos um caminho de erro relevante.

### 1.3 Disciplina de TDD

- Estórias de funcionalidade nova: o agente escreve teste primeiro, vê falhar, implementa, vê passar. Este é um requisito herdado e ratificado (ver `requisitos-nao-funcionais-e-juridicos.md`).
- Não é fetichismo: o sinal de TDD bem feito é que o histórico de commits mostra testes acompanhando ou precedendo o código.

### 1.4 Sem código não testado em produção

- Funções com `// no coverage` ou equivalente precisam de justificativa explícita no PR.
- Ramos catch genéricos não contam como código não testado se o teste cobre o gatilho.

## 2. Automação de tudo

O princípio: **se um humano precisa lembrar de fazer algo manualmente, o processo está quebrado**.

### 2.1 Ambiente de desenvolvimento local

- Um comando único leva alguém de "acabei de clonar o repo" até "API e FE rodando localmente com dados de seed".
- Esse comando é testado em CI periodicamente para não apodrecer.

### 2.2 CI/CD

> Política detalhada em [ADR-006](../../../project-state/decisions/adr/ADR-006-cicd.md) (`accepted` 2026-05-21).

- **Antes do push (laptop do dev/agente):** git pre-push hook versionado (instalado por `composer install`) roda testes unitários + testes Feature com Postgres real + testes E2E em browser real (Dusk) + análise de cobertura (gate 80% geral / 98% núcleo `app/Domain/**`). Hook falha = `git push` abortado.
- **Todo push para branch de feature dispara (CI no GitHub Actions, leve):** lint (pint, larastan, ansible-lint, commitlint), análise de dependências (`composer audit`), análise de imagem (`trivy fs`), detecção de segredos (`gitleaks`), build da imagem Docker. **Não** sobe Postgres nem Chromium no runner — testes pesados já foram cobrados localmente pelo hook.
- **Promoção é tag-based explícita**: criação da tag `vX.Y.Z-rc.N` dispara deploy automático em homologação (sem gate); criação da tag `vX.Y.Z` (sem `-rc`) dispara deploy em produção com gate humano de 1 clique via GitHub Environment.
- Deploy é sempre automatizado — execução nunca é manual. Gate humano em produção é o único ato humano no fluxo; tudo o que ele aciona é Ansible playbook versionado.

### 2.3 Infraestrutura

- Os ambientes de homologação e produção são criados/atualizados via Infra-as-Code. Nada de "ah, eu cliquei na UI do provedor pra criar isso".
- Recriar o ambiente do zero a partir do código é um caminho viável (e idealmente exercitado).

### 2.4 Banco de dados

- Migrações são automatizadas, versionadas e idempotentes.
- Backup e restore têm runbook automatizado.

## 3. Observabilidade mínima

Cada serviço entregue precisa, no mínimo:

- Endpoint de saúde (health check).
- Logs estruturados.
- Métricas básicas: requisições por segundo, latência p50/p95/p99, taxa de erro.
- Alerta configurado para indisponibilidade.

Isso é exigência por estória que entrega serviço novo, não opcional.

## 4. Segurança e LGPD

- Análise de dependências vulneráveis no pipeline.
- Segredos nunca no código — sempre em cofre/variável injetada.
- Dados pessoais tratados conforme `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md`.
- PRs que adicionam coleta de novo dado pessoal são bloqueados até o PO confirmar que está coberto pelo aviso de privacidade.

## 5. Acessibilidade (frontend)

- Componentes interativos novos têm rótulos acessíveis e funcionam por teclado.
- Contraste mínimo respeitado conforme `defonline-docs/especificacao/V2/design-system.md`.

## 6. O que NÃO é exigência transversal (e portanto é decisão técnica do time)

Para deixar claro o que NÃO entra aqui:

- Qual framework de teste usar.
- Qual ferramenta de E2E (Playwright, Cypress, Puppeteer, etc).
- Qual provedor de cloud.
- Qual ferramenta de IaC (Terraform, Pulumi, etc).
- Qual padrão arquitetural (monolito, microsserviços, etc).

Tudo isso é o Arquiteto/Programador que decide. O PO só fala em **resultado** ("o pipeline tem que ser verde", "o ambiente tem que recriar do zero") — não em ferramenta.

## 7. Como o PO escreve isso em estórias

Não copie este documento. Em cada estória, escreva:

> Esta estória segue os padrões em `defonline-docs/skills/po/references/quality-standards.md`. Em particular: [destacar o que for específico desta estória, ex: cobertura 98% no módulo de scoring, E2E cobrindo o fluxo de cadastro].

Se algum padrão NÃO se aplica (ex: estória de spike arquitetural não precisa de E2E), declare a exceção explicitamente na estória.
