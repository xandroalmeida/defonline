---
story_id: STORY-055
slug: exportacao-planilha-pdf
title: "Exportação da Demonstração — planilha XLSX e camada de impressão"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: programador (claude-sonnet-5-5)
created_at: 2026-08-04
updated_at: 2026-11-05
decisoes: [ADR-008, PDR-007, PDR-008]
estimated_session_size: M
checkpoint: CP3
---

# STORY-055 — Exportação em planilha e PDF

## Contexto

O resultado do mês precisa sair do produto. O dono leva ao contador, ao sócio, ao gerente do banco. E quer a planilha — porque é onde ele consegue mexer, e porque foi de uma planilha que a proposta do Key User nasceu.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: ADR da STORY-053 (**contrato desta estória**, inclusive quanto ao escopo recomendado); `epics/EPIC-010-*/design/estrutura-drm.md`; `Demonstração de Resultado Mensal - DRM.xlsx` — a planilha original é a referência de layout que o Key User já validou.

## O quê

Exportar a Demonstração de Resultado Mensal **em planilha XLSX**, e entregar a **camada de impressão** que permite ao usuário salvar um PDF pelo navegador enquanto o PDF da plataforma não existe.

> **O escopo desta estória mudou em 2026-08-06.** O `ADR-008` (aceito no `PDR-007`) separou os dois eixos: a planilha é desta estória; o **PDF gerado no servidor foi devolvido ao EPIC-007**, que abrirá com spike de motor. O `slug` do arquivo (`exportacao-planilha-pdf`) é histórico e não foi renomeado para não quebrar referências.

## Critérios de aceite

- [ ] **CA-1 (Planilha XLSX):** exportação em **XLSX** (via `phpoffice/phpspreadsheet`) reproduzindo a estrutura de `design/estrutura-drm.md` §1, com valores nominais e percentuais, cabeçalho identificando empresa e competência. **CSV não atende** — o `ADR-008` registra por quê: em CSV não existe separador certo, só escolha de quem vai reclamar.
- [ ] **CA-2 (Fidelidade ao original):** a planilha exportada é reconhecível por quem conhece o modelo do Key User — mesma ordem de linhas, mesma hierarquia, mesma coluna de percentual.
- [x] **CA-3 (PDF): `n/a` — resolvido em 2026-08-06 pelo `ADR-008`/`PDR-007`.** O PDF gerado no servidor voltou ao EPIC-007. **Não implemente PDF nesta estória** e não instale motor de renderização: `dompdf`, `mPDF` e SaaS de HTML→PDF estão desqualificados por antecipação, e a direção registrada (Chromium isolado em container) será validada em spike próprio do EPIC-007. A fila `pdf` do worker segue declarada e vazia — configuração intencionalmente morta.
- [ ] **CA-4 (Escopo do arquivo):** **um arquivo, duas abas** — a competência aberta e a série histórica. **Sem seletor de intervalo** (`ADR-008`). Fórmulas nas linhas derivadas: é isso que faz a planilha ser planilha em vez de PDF com bordas, e é o que o Key User pediu ao querer mexer nos números.
- [ ] **CA-5 (Fonte é o snapshot):** a exportação usa o resultado persistido, não recálculo. Exportar um mês retificado produz os valores retificados, com a retificação sinalizada no documento.
- [ ] **CA-6 (Escopo de acesso):** só o dono exporta. Cross-tenant retorna 404 silente.
- [ ] **CA-7 (Sem faturamento e prejuízo):** ambos os estados são exportados corretamente, com a mesma semântica da tela — percentuais indisponíveis explicados, prejuízo sinalizado sem dramatizar.
- [ ] **CA-8 (Nomenclatura do arquivo):** nome previsível e ordenável, identificando empresa e competência.
- [ ] **CA-9 (Síncrono e sem retenção):** geração **síncrona** por `streamDownload()`. O arquivo **nunca é armazenado** — sem storage, sem URL assinada, sem expurgo, sem estado "gerando" na UI e sem job em fila. É o que mantém a exportação fora do escopo de dado pessoal em repouso (`ADR-008`, força F5).
- [ ] **CA-10 (Testes):** feature cobrindo exportação nos três setores, mês sem faturamento, mês retificado e cross-tenant. **O teste relê o XLSX gerado e confere célula a célula, inclusive avaliando as fórmulas** com `getCalculatedValue()` contra o snapshot — verificar só o status da resposta não prova nada, e fórmula errada é planilha que mente. E2E percorrendo demonstração → exportar → arquivo recebido.
- [ ] **CA-11 (Cobertura):** ≥ 80%.
- [ ] **CA-12 (Camada de impressão):** folha de estilo `@media print` sobre o **mesmo markup** da tela da Demonstração do Mês — sem segundo template, sem hex literal fora do `tokens.css` (`IDR-008`) — mais o botão `Imprimir / Salvar em PDF`. Sai em A4 retrato, sem cabeçalho de navegação, sem drawer e sem botões de ação, com o rodapé de versão do motor, versão do conteúdo e data de geração. A especificação funcional §4.7 já previa isso: *"impressão é um atalho que abre a janela de impressão do navegador sobre a versão web"*.
- [ ] **CA-13 (Impressão é interina, e o produto não promete o contrário):** o botão de impressão **não** se chama "Exportar PDF" nem sugere que o arquivo é gerado por nós. Quando o EPIC-007 entregar o PDF da plataforma, esta camada é substituída, não acumulada.

## Fora de escopo

- **PDF gerado no servidor** — devolvido ao EPIC-007 pelo `ADR-008`. Não instale motor de renderização nem reative a fila `pdf`.
- Envio por e-mail, compartilhamento por link público, integração com armazenamento em nuvem.
- Personalização de layout ou marca do usuário.
- Exportação do relatório de Diagnóstico — é o EPIC-007, ainda que reuse a mesma base decidida na STORY-053.

## Dependências

- **Bloqueada por:** STORY-049, STORY-051. A STORY-053 fechou em 06/08/2026 com o `ADR-008` aceito — não é mais bloqueio. STORY-054 é desejável, não bloqueante: sem ela, o documento sai sem farol e sem leitura.
- **Bloqueia:** nada.

## Decisões já tomadas (não as reabra)

- **`ADR-008`** (STORY-053, aceito no `PDR-007`) — mecânica, escopo e retenção. XLSX síncrono sem retenção; PDF no EPIC-007; `@media print` como saída interina.
- **`PDR-008` §2** — a camada de impressão mora **aqui**, não na `STORY-049`, para não engordar o caminho crítico do Checkpoint 1.
- Fonte é o snapshot, nunca recálculo.
- `design/estrutura-drm.md` — estrutura e ordem das linhas.

## Definição de Pronto

- [ ] CA-1 a CA-13 passam. O **CA-3 já está resolvido como `n/a`** — não é decisão do agente.
- [ ] **Checkpoint 3 demonstrado em homologação:** demonstração com farol e leitura, exportada em XLSX e impressa pelo navegador.
- [ ] Pipeline verde; `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Plano (2026-11-05, antes de codar)

Lido: estória, ADR-008 (decisões 1-4, escopo recomendado, CA-3 `n/a`), `estrutura-drm` §1-§6, notas das STORY-047 a 051 e 054, `AcessoDrm` (STORY-084), `FecharLancamento`/`RetificarLancamento`, `DemonstracaoVisao`, o PDF do Diagnóstico (só para NÃO reaproveitar o dompdf).

Entendimento: um controller síncrono lê a versão vigente (snapshot) e a série das vigentes, monta o XLSX em memória e o transmite por `streamDownload`; nada é gravado. A camada de impressão é CSS sobre o mesmo Blade. PDF no servidor está fora (CA-3 `n/a`).

Plano: (1) `phpoffice/phpspreadsheet` + `ext-gd` no Dockerfile (ADR-008); (2) `PlanoDeFormulas` (puro) e `PlanilhaDrm`; (3) rota `drm.exportar` + controller; (4) botões e `@media print`; (5) Pest (células + fórmulas avaliadas) e Dusk (arquivo recebido, impressão).

### Decisões tomadas

- **Dependência:** `phpoffice/phpspreadsheet ^5.10` (ADR-008, decisão 1). No Dockerfile, `gd` entra no `docker-php-ext-install` do runtime e o stage `composer-deps` (imagem `composer:2`, sem gd) instala com `--ignore-platform-req=ext-gd`. `gd` foi compilado nos contêineres da pista com a mesma linha, o que valida o `docker-php-ext-install gd` (a imagem em si não foi reconstruída).
- **Fórmulas só quando provadas.** `PlanoDeFormulas` lê apenas `nivel` e `tipo` do snapshot (total = linha-base menos as deduções que vêm depois; dedução com linhas de nível 2 = soma delas; a única lista de códigos é `linhas_aditivas = [L7b]` em `config/drm/exportacao.php`). `PlanilhaDrm` escreve a fórmula de uma célula só quando a soma em centavos dos operandos reproduz o valor do snapshot e nenhum operando é nulo; senão grava o valor do snapshot. Teste adultera um snapshot e vê o valor, não a fórmula. Percentual: `=B{n}/B$âncora`, mesma regra (tolerância de 2e-4 pontos para o truncamento do snapshot a 4 casas).
- **Posição do pró-labore:** a da preferência da empresa (a mesma que a tela abre). A tela troca a preferência ao alternar, então o arquivo sai como a pessoa está vendo.
- **Aba "Série histórica":** uma coluna por mês, do primeiro ao último lançamento; mês sem lançamento = coluna em branco com "Não lançado" (nenhum zero); retificado = "Retificado (vN)". Duas seções (valores e % da receita), ambas com fórmulas pelo mesmo plano, mês a mês. As linhas são a união das linhas dos meses (ordem do primeiro, o resto entra depois do antecessor). O mês aberto não é destacado na aba da série: a aba "Mês" já o é.
- **Linhas omitidas na tela (L8a/L8b agregadas) também são omitidas no arquivo:** o arquivo usa `DemonstracaoVisao`, a mesma leitura da tela, para ser reconhecível (CA-2) e não duplicar regra. `LinhaVisao` ganhou `centavos` e `percentualBruto` (opcionais, no fim do construtor).
- **Rótulos são sempre texto** (`setCellValueExplicit`): "= Lucro Bruto" não pode virar fórmula (teste).
- **Prejuízo:** número negativo normal e observação "Prejuízo no mês" na linha final; fonte preta, sem cor (CA-7). Sem faturamento: valores saem, % em branco com o motivo na coluna Observação.
- **Acesso:** rota `drm.exportar` fora do middleware `drm.acesso` (é leitura do histórico; STORY-084 CA-3). Empresa alheia e mês sem lançamento = 404 (Global Scope). Nome: `drm_<empresa-slug>_<AAAA-MM>.xlsx`.
- **Impressão (CA-12/13):** `@page { size: A4 portrait }` em `app.css`, `print:hidden` no que é navegação (breadcrumb, rodapé do app, botões de ação, troca de modo, comparativos) e tabela forçada em `print:block`/cards `print:hidden` (a 794 px o `lg` não vale). Rodapé só de impressão: motor, conteúdo (`drm.conteudo_version`) e geração. Botão "Imprimir / Salvar em PDF" (`window.print()` via Alpine); nenhuma palavra "Exportar PDF".
- **Evento de exportação (STORY-056 CA-6)** fica para a 056: aqui o controller não emite nada.

### Descobertas

- Chrome normaliza `A4 portrait` para `a4` no CSSOM (retrato é o padrão); o Dusk compara `a4` e o Pest confere o texto do CSS.
- O pico de memória de uma geração é ~26 MB (medido), longe do limite de 128 MB; o estouro visto ao rodar o Pest com `memory_limit` padrão é do processo de testes, não da request.
- Dompdf continua no projeto (PDF do Diagnóstico, estória anterior); não foi usado aqui.

### Bloqueios encontrados

Nenhum.

### IDRs criados

Nenhum.

### Cobertura final

- Suíte `All`: 2590 passed (47.277 assertions) com `php -d memory_limit=-1`; Dusk completo: 83 passed (680 assertions), incluindo `DrmExportacaoBrowserTest` (arquivo recebido, impressão em A4 com mídia `print` emulada). Pint e PHPStan limpos. Cobertura: ver relatório da sessão.
- **Não verificado:** abrir o XLSX no Excel/LibreOffice (só releitura por PhpSpreadsheet); a janela de impressão real do navegador (só a mídia emulada); a imagem Docker reconstruída; Checkpoint 3 em homologação.
