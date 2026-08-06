---
story_id: STORY-053
slug: spike-exportacao
title: "Spike — motor de exportação: planilha e PDF"
epic_id: EPIC-010
type: spike
target_role: arquiteto
requires_design: false
status: done
owner_agent: arquiteto (claude-opus-5)
created_at: 2026-08-04
updated_at: 2026-08-06
closed_at: 2026-08-06
approved_by: Alexandro
estimated_session_size: M
checkpoint: CP3
---

# STORY-053 — Spike: motor de exportação

> **Para o agente que vai executar:** você é o **Arquiteto**. Esta decisão extrapola o EPIC-010 — o EPIC-007 (exportação do relatório de Diagnóstico em PDF) depende dela. Decida uma vez, para os dois.

## Contexto

O produto ainda não gera nenhum documento. Não há geração de PDF em lugar nenhum do código, embora a fila `pdf` já exista na configuração do worker, herdada do planejamento original. Esta é uma frente técnica nova, e é a razão de a exportação estar no Checkpoint 3 e não antes.

O DRM é o primeiro caso de uso real: o dono precisa levar o resultado do mês para o contador, para o sócio ou para o gerente do banco. E precisa da planilha — que é o formato em que ele consegue mexer.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `decisions/adr/ADR-002-topologia.md` (worker e filas); `decisions/adr/ADR-005-*` (infra e imagem de runtime); `roadmap/next-wave.md` (EPIC-007); `epics/EPIC-010-*/design/estrutura-drm.md`

## O quê

Produzir um **ADR** que fixe como o produto gera documentos — planilha e PDF — servindo tanto ao DRM quanto ao futuro EPIC-007.

## Perguntas que a decisão precisa responder

1. **Síncrono ou assíncrono?** A fila `pdf` já existe e nenhum job a consome. Vale a pena a assincronia para um documento de uma página, com o custo de UX que ela traz (notificação, polling, estado "gerando")?
2. **Impacto na imagem de runtime.** Geração de PDF costuma exigir dependências pesadas. Qual o efeito no tamanho da imagem, no tempo de build e na superfície de segurança? Há caminho que evite isso?
3. **Fidelidade visual.** O PDF deve reproduzir a tela ou ter layout próprio de documento A4? Como isso interage com o design system e com os tokens?
4. **Planilha.** Formato aberto e editável, com fórmulas preservadas ou valores estáticos? O usuário vai querer mexer — e a planilha original do Key User tinha fórmulas.
5. **Retenção.** O arquivo é gerado sob demanda e descartado, ou armazenado? Se armazenado, onde, por quanto tempo, e qual o impacto de LGPD e de custo?
6. **Escopo da exportação.** Uma competência, um intervalo de competências, ou a série inteira? Recomende com base no custo.
7. **Reaproveitamento pelo EPIC-007.** A decisão precisa servir ao relatório de Diagnóstico, que é bem mais denso — 14 indicadores, semáforo, glossário, recomendações. Valide contra esse caso, não só contra o DRM.

## Critérios de aceite

- [x] **CA-1:** ADR publicado em `decisions/adr/`, `status: accepted`, respondendo às 7 perguntas com opções, prós, contras e justificativa. → **`ADR-008` publicado em `proposed`**; vira `accepted` na aprovação do PO (a própria DoD desta estória exige revisão antes). As 7 perguntas têm resposta direta em tabela dedicada, além das seções de opções.
- [x] **CA-2:** A decisão é validada explicitamente contra os **dois** casos de uso — DRM e relatório de Diagnóstico. → §"Duas decisões, não uma" e resposta 7. Dito sem rodeio: **a decisão de planilha NÃO serve ao EPIC-007** (relatório é prosa e farol, não tabela); o que serve é o eixo do PDF, com direção e exclusões já registradas.
- [x] **CA-3:** Impacto declarado sobre imagem de runtime, tempo de build, pipeline de CI e infraestrutura de homologação. → resposta 2 + §"Estimativa de custo". Números **medidos**, não estimados.
- [x] **CA-4:** Impacto de LGPD declarado, caso a decisão implique armazenar documentos com dados do usuário. → resposta 5. A decisão **não armazena**: zero novo dado pessoal em repouso. O RNF §2.3/§4.8 não é revogado — passa a valer quando o EPIC-007 introduzir artefato armazenado.
- [x] **CA-5:** Recomendação explícita de escopo de exportação para o MVP do DRM, alimentando a STORY-055. → resposta 6: um arquivo, duas abas (competência aberta + série histórica), sem seletor de intervalo.
- [x] **CA-6:** Se houver protótipo, vive em branch `spike/STORY-053-*` e não é mergeado. → **não houve protótipo**, e a decisão não dependia de um. Ver "Descobertas" — os números que sustentam a decisão foram obtidos por medição da imagem existente e leitura do código, não por código novo.
- [x] **CA-7:** `index.json` atualizado. → `ADR-008` em `decisions.adr[]` como `proposed`; STORY-053 em `in_review`.
- [x] **CA-8:** Se a decisão se revelar cara demais para o MVP, **diga isso** e recomende entregar só a planilha, devolvendo o PDF ao EPIC-007. → **É exatamente esta a conclusão, e ela está dita com todas as letras.** Ver "Decisões tomadas" abaixo.

## Fora de escopo

- Implementar a exportação (STORY-055).
- Decidir o layout do documento — é design, e vem depois desta decisão.

## Dependências

- **Bloqueada por:** nada. Pode rodar em paralelo ao Checkpoint 2.
- **Bloqueia:** STORY-055.

## Decisões já tomadas (não as reabra)

- ADR-002 (topologia: monolito com worker e scheduler); ADR-005 (infra); ADR-006 (CI/CD).

## Definição de Pronto

- [x] CA-1 a CA-8 atendidos (CA-1 fecha quando o PO aprovar e o ADR virar `accepted`).
- [ ] ADR revisado com o PO antes de `accepted`. → **aguardando**.
- [x] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas

**A conclusão principal, com todas as letras: o PDF gerado pelo servidor é caro demais para o Checkpoint 3 do EPIC-010. Recomendo entregar apenas a exportação em planilha e devolver o PDF ao EPIC-007.** É a saída prevista no CA-8 desta estória e no risco "PDF abre frente técnica nova" do épico.

O que sustenta isso é uma separação que a pergunta original não fazia: **planilha e PDF parecem a mesma coisa ("exportar") e não são nem parentes.** A planilha é uma dependência de biblioteca e um loop sobre 17 linhas. O PDF é uma frente de infraestrutura inteira — renderizador, fila, artefato armazenado, política de retenção, superfície de segurança nova. O que decide não é a dificuldade, é **a quem pertence o custo**: o PDF é o objeto inteiro do EPIC-007, e pagá-lo dentro do CP3 do EPIC-010, por um documento de uma página que o usuário não pediu, é adiantar o custo de um épico e cobrar de outro.

O `ADR-008` registra quatro decisões:

1. **Planilha, agora:** XLSX via `phpoffice/phpspreadsheet`, **síncrona** (`streamDownload()`), **nunca armazenada**, fonte = snapshot do `ADR-007`, **com fórmulas nas linhas derivadas**, um arquivo com **duas abas** (competência aberta + série histórica), **sem seletor de intervalo**.
2. **PDF gerado no servidor: adiado ao EPIC-007.**
3. **Saída interina:** camada `@media print` sobre o **mesmo markup** da tela + botão "Imprimir / Salvar em PDF". Adiar o PDF não deixa o usuário sem papel — deixa sem *nosso* PDF.
4. **Direção registrada para o EPIC-007:** Chromium **isolado em container** (Gotenberg), a validar em spike próprio. **dompdf, mPDF e SaaS de HTML→PDF ficam desqualificados por antecipação**, com motivo — o EPIC-007 não começa do zero.

**Consequências diretas para a STORY-055:** `CA-3` (PDF) → **`n/a`**; `CA-4` (escopo) → competência aberta + aba de série; `CA-9` → síncrono, sem retenção, sem estado "gerando"; `CA-10` → o teste relê o XLSX e confere célula a célula, **inclusive avaliando as fórmulas**. Sugestão ao PO (não é alçada do Arquiteto alterar CA): acrescentar um CA para a camada de impressão na STORY-055, ou alocá-la na STORY-049.

### Descobertas

1. **Chromium na imagem custa 566 MB — medido, não estimado.** `docker history defonline/app:dev` mostra a camada `apk add chromium chromium-chromedriver` em **566 MB**. O target `dev` tem 1,81 GB; o `runtime` deployado tem ~1,25 GB. Embarcar Chromium é **+45% de imagem** em todo build, todo push para o GHCR e todo pull no deploy. A ADR-002 (histórico de 2026-05-24) já havia separado os targets exatamente para não pagar isso em homol/prod — reverter essa separação em nome de um PDF de conveniência seria desfazer uma decisão consciente de 2 meses atrás.

2. **A armadilha desta decisão é dompdf/mPDF, e ela é invisível se você olhar só o tamanho.** São ~10 MB de PHP puro, sem container — parecem a resposta do princípio #1. Mas nenhuma das duas resolve **CSS custom properties**, e o `app.css` é Tailwind v4 com `@theme` (IDR-008): a paleta inteira vive em `var(--*)`. O documento precisaria de um **segundo template com hex copiado à mão** — isto é, a **terceira exclusão** em `tests/Unit/Arch/DesignTokensTest.php`, ao lado do `legal.blade.php` e dos e-mails transacionais. O custo real não está na imagem, está no segundo layout que ninguém vai lembrar de atualizar — e ele apareceria justamente no relatório que o Roberto leva ao banco, onde o farol pode ficar de uma cor na tela e de outra no papel. **Novo sinal de revisão registrado no ADR: uma terceira exclusão nesse teste, proposta em qualquer PR, é gatilho de revisão imediata.**

3. **A especificação funcional já respondia à pergunta 3.** `especificacao-funcional.md` §4.7: *"Impressão é um atalho que abre a janela de impressão do navegador sobre a versão web."* A opção "usar o navegador como motor de PDF" não é uma invenção para cobrir um adiamento — é o que estava escrito desde sempre e ninguém tinha relido.

4. **A pergunta 1 vinha com um pressuposto herdado.** "Síncrono ou assíncrono?" pressupunha o RNF §1.2 (10 s com feedback visual) — que foi dimensionado **para o PDF do relatório**. Sem PDF, o pressuposto some junto. A fila `pdf` da ADR-002 continua declarada e vazia no `command` do worker (`docker-compose.yml:52` e o template Ansible de produção): fica registrada no ADR como **configuração intencionalmente morta**, para que ninguém a leia como sinal de que existe geração assíncrona.

5. **`phpoffice/phpspreadsheet` 5.9.0 exige `ext-gd`, que a imagem não tem.** As demais extensões (`zip`, `mbstring`, `dom`, `xml*`, `fileinfo`, `iconv`, `zlib`) já estão presentes, e `libpng-dev` já está no `apk add` — o custo é `docker-php-ext-install gd`, ~3 MB, em camada cacheada. A alternativa `openspout/openspout` 5.10.1 não precisa de extensão nenhuma nova, mas **não escreve fórmulas** — e fórmula é justamente o que faz a planilha ser planilha em vez de PDF com bordas.

6. **XLSX resolve um problema que o CSV não tem solução.** Excel em pt-BR espera `;` e decimal com vírgula; qualquer outro locale espera o contrário. Em CSV não existe escolha certa, só escolha de quem vai reclamar. XLSX guarda número como número — mesmo arquivo, leitura correta nos dois lados.

7. **Verificabilidade pende fortemente para a planilha.** O CA-10 da STORY-055 exige "verificação de conteúdo do arquivo gerado, não só do status da resposta". Reler um XLSX em Pest e conferir célula a célula é trivial — e dá para ir além, avaliando cada fórmula com `getCalculatedValue()` e comparando com o snapshot, o que impede a planilha de mentir. Fazer o equivalente com PDF exige extrair texto de binário: teste frágil, ou teste que só confirma que "veio um PDF". Princípios #9 e #10 apontam para o mesmo lado.

### Bloqueios encontrados

Nenhum bloqueio. Uma **pergunta aberta ao PO**, registrada no ADR e deliberadamente não decidida por mim porque é qualidade de produto, não arquitetura:

> A Decisão 4 assume que o EPIC-007 vai querer um **documento gerado pelo produto**, com nossa paginação e nosso rodapé de versão. Existe uma alternativa legítima que **elimina o EPIC-007 técnico inteiro**: aceitar que "Salvar PDF" seja, em definitivo, a impressão do navegador sobre uma folha de estilo bem-feita. Isso trocaria um épico por uma estória, ao custo de o documento que o Roberto leva ao banco variar conforme o navegador dele.

Se a resposta for "a impressão basta", o `ADR-008` é substituído por uma versão mais simples e o EPIC-007 encolhe drasticamente. Se for "quero documento nosso", a Decisão 4 vale como está e o EPIC-007 abre com um spike de motor.

### ADRs criados

- **`ADR-008` — Motor de exportação** (`decisions/adr/ADR-008-motor-exportacao.md`), `status: proposed`, aguardando aprovação do PO.


## Decisão do PO — 2026-08-06

Registrada em `decisions/pdr/PDR-007-aprovacao-specs-drm-e-motor-exportacao.md`. A estória fecha como `done`.

**`ADR-008` aceita como escrita, sem condicionantes (§3).** A separação entre planilha e PDF é a parte que importa: pagar o custo de abertura do EPIC-007 dentro do Checkpoint 3 do EPIC-010, por um documento de uma página que o usuário não pediu, seria dívida disfarçada de entrega. Vale para o DRM: XLSX síncrono, sem retenção, lendo o snapshot, com fórmulas nas linhas derivadas.

**Pergunta aberta respondida (§4): o PDF é documento da plataforma.** A alternativa de aceitar a impressão do navegador em definitivo foi **recusada** — o documento que o Roberto leva ao banco não pode variar conforme o navegador de quem imprime. Consequências: a **Decisão 4 do ADR vale como está** (Chromium isolado em container, direção Gotenberg, a validar em spike próprio); a camada `@media print` permanece **saída interina, não solução final**; e o **EPIC-007 abre com o spike de motor de PDF** como primeira estória.

**Segue aberto:** o ajuste dos CAs da `STORY-055` por consequência desta ADR (CA-3 → `n/a`, CA-4, CA-9, CA-10) e a alocação da camada de impressão entre a `STORY-055` e a `STORY-049`. Não bloqueia nada agora — a `STORY-055` é do Checkpoint 3.
