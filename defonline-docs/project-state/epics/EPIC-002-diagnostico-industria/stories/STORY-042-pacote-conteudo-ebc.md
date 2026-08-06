---
story_id: STORY-042
slug: pacote-conteudo-ebc
title: Pacote de conteúdo EBC — títulos, rótulos, textos auxiliares, glossário, NCG positivo
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: implementation
target_role: programador
requires_design: false
status: done
owner_agent: programador (claude-opus-5)
created_at: 2026-06-02
updated_at: 2026-08-04
closed_at: 2026-08-04
approved_by: Alexandro
estimated_session_size: M
related_devolutiva: ".docx 'DEF on line - MVP 270526 - observações ebc' — blocos 2, 3, 4 + diagnóstico + glossário"
---

# STORY-042 — Pacote de conteúdo EBC

> Coleção coordenada de mudanças textuais sugeridas pelo Edvan (EB Parcerias) no `.docx` de devolutiva — títulos de blocos, rótulos de itens, textos auxiliares, recomendação de NCG positivo e ordem do glossário. 1 commit coeso, sem mudança de regra de negócio do motor.

## Contexto

A EBC (Edvan) revisou o MVP entregue na SPRINT-2026-W25 e devolveu uma lista de ajustes editoriais no `.docx` "DEF on line - MVP 270526 - observações ebc". Ajustes são pontuais (não reescrevem fluxo) e melhoram a clareza para o público-alvo. Sem impacto em regras do motor — apenas em conteúdo renderizado e em texto da matriz de recomendações.

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- Devolutiva: `epics/EPIC-002-diagnostico-industria/devolutivas/2026-05-27-EBC/`
- Spec V2: Anexo A (quiz), Anexo F (matriz dez/2025), glossário.

## O quê

Aplicar a lista abaixo de mudanças em uma única estória coesa (1 PR, 1 commit ou poucos commits temáticos).

### Lista de mudanças

**Bloco 2 (DRE):**
- **Remover** do front o texto auxiliar "o motor anualiza (×12) no cálculo" — a anualização continua silenciosa no motor; o usuário não vê.
- **Subtítulo do item 3 — Custos e Despesas Fixas:** acrescentar exemplos: *"Folha de Pagamento, Encargos sociais, contador, aluguel, condomínio, energia, água, telefone, internet, franquia, retirada de sócios, etc."*
- **Subtítulo do item 4 — Custos e Despesas Variáveis:** acrescentar exemplos: *"Fretes, comissões, tributos, despesas de vendas, outras despesas variáveis, etc."*
- **Título do Bloco 2:** [PENDENTE confirmar com Edvan — `.docx` não especificou; manter texto atual se Edvan não responder até 2026-06-04].

**Bloco 3 (Balanço):**
- **Título do Bloco 3:** trocar para *"Saldos Atuais – uma foto da situação econômica do negócio hoje"*.
- **Patrimônio (Imobilizado), texto auxiliar:** trocar para *"Imóveis, equipamentos e veículos livres de ônus – valor estimado para venda rápida"*.

**Bloco 4 (Contexto):**
- **Q17 (Necessita de recursos?):** **ocultar** o texto auxiliar "Base de cálculo da capacidade de garantia (3× esse valor)" — vira regra interna, não texto na UI.
- **Q17 (Necessita de recursos?):** **ocultar** o texto auxiliar "Deve ser inferior à venda mensal..." — a regra de consistência vira validação backend, tratada na **STORY-043** (esta estória só remove o texto).

**Matriz dez-2025 (Anexo F):**
- **Recomendação NCG positivo:** trocar texto atual para *"A elevação deve ser acompanhada, na mesma proporção, pelo aumento das fontes de financiamento de longo prazo, preferencialmente de fornecedores"*.
- Atenção: a entrada `ncg_vendas × farol_positivo` em `config/recomendacoes/dez-2025.php` (ou equivalente). Snapshot de diagnósticos legados não é alterado (IDR-010 §retro).

**Glossário (na página do diagnóstico):**
- Reordenar: **"Ebitda (Margem Operacional)" como 2º item**.
- **Remover** o item que está antes do "Farol/Semáforo" — [PENDENTE: qual item exatamente? `.docx` ambíguo. Confirmar com Edvan na pauta 2026-06-02; se não responder até 2026-06-04, deixar como está e remover a tarefa do escopo].

## Por quê

Devolutiva da EBC aumenta a clareza para o Roberto e absorve o feedback do analista financeiro de referência. Tornar o quiz/relatório mais legível elevará taxa de submissão completa (D2 — Ativação).

## Critérios de aceite

- [x] **CA-1 (Bloco 2 — texto anualização):** Texto "o motor anualiza (×12)" não aparece em nenhum lugar da UI do quiz; teste E2E percorre Bloco 2 e busca a string — não acha.
- [x] **CA-2 (Bloco 2 — item 3):** Subtítulo do item Custos e Despesas Fixas exibe a lista de exemplos exata acima.
- [x] **CA-3 (Bloco 2 — item 4):** Subtítulo do item Custos e Despesas Variáveis exibe a lista de exemplos exata acima.
- [x] **CA-4 (Bloco 3 — título):** Cabeçalho do Bloco 3 do quiz exibe "Saldos Atuais – uma foto da situação econômica do negócio hoje".
- [x] **CA-5 (Bloco 3 — imobilizado):** Subtítulo de Patrimônio (Imobilizado) exibe o texto novo.
- [x] **CA-6 (Bloco 4 — Q17 ocultar):** Os 2 textos auxiliares de Q17 (base de cálculo 3× / inferior à venda mensal) não aparecem.
  - Rótulo do CA corrigido na execução: as 2 strings vivem no campo **Q20**, dentro do sub-bloco gated por `Q17 === 'sim'`. Verificado com o sub-bloco **aberto** — com `Q17 = não` o campo nem entra no DOM e a asserção passaria vazia.
- [ ] **CA-7 (Matriz NCG positivo):** Diagnóstico novo (após deploy) com NCG positivo exibe a recomendação revisada. Diagnóstico legado abre com texto antigo (snapshot preservado).
  - **Metade feita, metade escalada.** O texto revisado está na matriz (`ncg_absoluto.positiva`) e diagnóstico legado segue intocado — as duas coisas cobertas por teste. Mas **nenhum diagnóstico, novo ou velho, renderiza essa entrada**: `NcgAbsoluto` não consome a matriz, por decisão da STORY-032. Fazer o CA passar por inteiro exige desfazer aquela decisão — é produto, não implementação. Ver `[ESCALONAMENTO-PO]` em "Bloqueios / pendências".
- [x] **CA-8 (Glossário ordem):** "Ebitda (Margem Operacional)" aparece como 2º item da lista do glossário.
  - Rótulo vigente é `EBITDA / Margem Operacional` (Anexo I §I.2). Reordenado, **não** renomeado — ver "Decisões tomadas".
- [x] **CA-9 (Pendências fechadas):** Título do Bloco 2 e item a remover do glossário — fechados com Edvan ou registrado no PR como aberto/postergado.
- [x] **CA-10 (cobertura):** suíte Pest verde; 1 Dusk percorrendo o quiz inteiro e verificando os textos novos; testes de snapshot da matriz/glossário re-emitidos onde necessário.

## Fora de escopo

- Validação cruzada de Q17 ≥ Q09 — vai para **STORY-043** (separada para isolar mudança de regra).
- Mudança de algoritmo de seleção de destaques no Resumo Executivo — aguarda Edvan (§4.7.1).
- Tradução para outros idiomas.

## Padrões de qualidade exigidos

- **Cobertura ≥ 80% nos arquivos tocados** (alguns só têm strings; cobre via E2E).
- **Snapshot da matriz dez-2025 atualizado** se houver teste de hash do conteúdo da matriz.

## Dependências

- **Bloqueada por:** respostas do Edvan (2 itens — título Bloco 2 + item glossário) — não-críticas (default documentado).
- **Bloqueia:** STORY-043 (R4 depende do Q17 já com textos ajustados — embora seja paralelizável se Programador for cuidadoso).

## Decisões já tomadas

- **Anualização (×12) — texto auxiliar removido** da UI, lógica do motor inalterada.
- **Q17 — textos auxiliares ocultos** (a regra de "inferior à venda mensal" vira validação backend na STORY-043).
- **Matriz dez-2025** — diagnósticos legados preservados (IDR-010 §retro).
- **Glossário item a remover** — se Edvan não responder até 2026-06-04, **manter** e fechar pendência como "diferido".

## Liberdade técnica do agente

- Como organizar o commit (1 commit gigante OK; ou 5 commits temáticos com squash no merge — Programador decide).
- Como executar mudanças no glossário (componente único ou múltiplos templates).
- Onde mora o texto da matriz NCG positivo (config PHP versionado — já existe).

## Definição de Pronto

- [ ] CA-1..CA-10 passam. — **9 de 10.** CA-7 tem metade escalada ao PO (ver acima); os outros 9 passam com teste.
- [x] Snapshot tests onde aplicável. — matriz (`MatrizNcgPositivoTest`, igualdade estrita) e glossário (ordem do DOM, `DiagnosticoShowTest`). **Os 5 golden hashes do motor não mudaram**, e isso é resultado, não sorte: prova de que a edição da matriz não vaza para diagnóstico nenhum.
- [x] Dusk percorrendo quiz com textos novos. — `PacoteConteudoEbcBrowserTest`, Bloco 1 → 4 + relatório, 17 asserções.
- [x] CI verde. — run [30941074737](https://github.com/xandroalmeida/defonline/actions/runs/30941074737) em `main@5d7d192` (2026-08-04), todos os jobs do `pr.yml` verdes.
- [x] Deploy em homol verificado (sweep visual + smoke). — `v0.11.0-rc.1`; sweep feito em navegador real contra a URL pública. Ver "Verificação em homologação". **Não fecha a estória:** o CA-7 segue escalado.
- [x] `index.json` atualizado.

## Notas do agente

### 2026-08-04 — Leitura inicial completa

- **Documentos lidos:** STORY-042 (inteira, incluindo frontmatter, fora de escopo, DoD), `briefings/STORY-042-abertura.md`, `skills/programador/SKILL.md`, `skills/po/references/agent-task-format.md`, `devolutivas/2026-05-27-EBC/README.md` + `pauta-retorno.md` (as 4 perguntas ao Edvan e as políticas de fallback), `especificacao/V2/anexos/anexo-F-matriz-recomendacoes-dez2025.md` (§F.13 e §F.14), `especificacao/V2/anexos/anexo-I-glossario.md` (inteiro). Código: `resources/views/livewire/diagnostico/quiz.blade.php`, `partials/campo-brl.blade.php`, `components/help.blade.php`, `resources/views/diagnosticos/show.blade.php`, `config/quiz/help-industria.php`, `config/motor/matriz-dez-2025-industria.php`, `app/Domain/Motor/MatrizRecomendacoes.php`, `app/Domain/Motor/Indicadores/NcgAbsoluto.php`, `tests/Domain/Motor/MatrizArchTest.php`, `tests/Domain/Motor/GoldenHashesTest.php`, `tests/Feature/Http/DiagnosticoShowTest.php`, `tests/Feature/Livewire/Diagnostico/QuizTest.php`, `tests/Browser/*`.

- **Entendimento consolidado:** é um pacote editorial. Nenhuma fórmula, faixa de farol ou classificação muda; nenhum bump de `motor_version` é devido, porque nada do que eu toco entra na saída de `Motor::calcular()` (verificado, não presumido — ver "Descobertas"). O trabalho é: 4 strings de texto auxiliar no Blade do quiz (2 trocadas, 2 removidas), 1 título de bloco, 1 reordenação de array no Blade do relatório e 1 string no config da matriz.

- **Estado das 2 pendências ao Edvan (verificado, não presumido):** não existe nenhum `resposta-edvan-*.md` em `devolutivas/2026-05-27-EBC/` (a pasta tem só `README.md` e `pauta-retorno.md`), e a data de hoje é **2026-08-04** — o prazo de **2026-06-04** venceu há 2 meses. Aplico os defaults documentados: **título do Bloco 2 mantido** e **item do glossário não removido** (pendência fechada como *diferida*). Detalhe em "Pendências fechadas com Edvan".

- **Dúvidas:** 1 bloqueante parcial (CA-7 — ver `[ESCALONAMENTO-PO]` em "Bloqueios / pendências"); as demais divergências abaixo são resolvíveis por leitura do código e ficam registradas, não inventadas.

- **Divergências entre o texto da estória e o código (registradas na leitura, antes de codar):**
  1. **Os 2 textos auxiliares "de Q17" são do Q20.** A estória e o CA-6 dizem "Q17 (Necessita de recursos?)", mas as strings exatas ("Base de cálculo da capacidade de garantia (3× esse valor)" / "Deve ser inferior à venda mensal total (Q09)") vivem no `help` do campo **Q20** (`quiz.blade.php:277`), que é renderizado dentro do sub-bloco `@if ($Q17 === 'sim')`. Ou seja: "Q17" na estória designa o **sub-bloco de captação gated pelo Q17**, não o campo Q17. A intenção é inequívoca e as strings são únicas no repositório — trato como erro de rótulo, não como ambiguidade de escopo.
  2. **`ncg_vendas × farol_positivo` em `config/recomendacoes/dez-2025.php` não existe.** O arquivo é `config/motor/matriz-dez-2025-industria.php` (a estória já previa isso com "ou equivalente") e não há farol `positivo` — `ncg_vendas` tem `verde`/`amarelo`/`vermelho`. A única entrada com cenário positivo é `ncg_absoluto` → chaves `positiva`/`negativa` (§F.13 do Anexo F). Prova de que o alvo é essa: o texto novo pedido pela estória é **literalmente a segunda frase do texto atual de `ncg_absoluto.positiva`** — Edvan riscou a primeira frase do próprio texto que ele havia escrito no Anexo F. Nenhuma leitura de `ncg_vendas` produz esse recorte.
  3. **Rótulo do item do glossário.** O CA-8 chama o item de "Ebitda (Margem Operacional)"; o rótulo vigente no relatório é `EBITDA / Margem Operacional` (`show.blade.php:49`) e o Anexo I §I.2 usa "**EBITDA** (ou **Margem Operacional**)". Entendo o CA-8 como *identificação* do item a mover — o verbo da estória é "Reordenar", não "renomear". Ver "Decisões tomadas".
  4. **`sprint_id` divergente:** o arquivo da estória diz `SPRINT-2026-W26`; o `index.json` já a move para `SPRINT-2026-W32`. Não toco em nenhum dos dois — é reprogramação do PO.

- **Plano:**
  1. TDD — Pest primeiro, um teste por CA, todos vermelhos antes de qualquer edição de Blade/config.
  2. Bloco 2 e Bloco 3 e sub-bloco de captação em `quiz.blade.php` (1 arquivo, 6 edições pontuais).
  3. Glossário em `diagnosticos/show.blade.php` (mover 1 linha do array).
  4. `ncg_absoluto.positiva` em `config/motor/matriz-dez-2025-industria.php` + comentário registrando a divergência deliberada em relação ao Anexo F.
  5. Dusk percorrendo os 4 blocos do quiz em browser real (CA-10) + Dusk conferindo a 2ª posição do glossário no relatório renderizado.
  6. Suíte completa (Pest + Dusk) + Pint + PHPStan + cobertura.

- **Testes que pretendo escrever** (além do caminho feliz, cada CA ganha a borda que realmente pode quebrar):
  - **CA-1:** a página do Bloco 2 **não** contém "anualiza" (asserção negativa); e **continua** contendo "Médias mensais dos últimos 12 meses" (borda: garante que a remoção foi cirúrgica e não levou a frase inteira junto).
  - **CA-2 / CA-3:** igualdade estrita das duas listas de exemplos (string inteira, não `toContain` — `toContain` passaria com o texto antigo se o novo fosse prefixo).
  - **CA-4:** cabeçalho do Bloco 3 com o texto novo **e** ausência de "Bloco 3 de 4 — Balanço" (borda: pega o caso de alguém adicionar o texto novo em outro lugar e esquecer o título).
  - **CA-5:** igualdade estrita do subtítulo do Q05.
  - **CA-6:** as 2 strings ausentes **com o sub-bloco de captação aberto** (`Q17 = sim`) — se o teste rodasse com `Q17 = não` passaria trivialmente sem provar nada, porque o campo nem é renderizado. Inclui a borda inversa: o *tooltip* do Q20 (superfície diferente, fora de escopo) continua presente.
  - **CA-7:** valor exato de `ncg_absoluto.positiva`; teste de **não-regressão de snapshot** provando que a mudança não vaza para diagnóstico nenhum (os 5 golden hashes continuam idênticos — asserção positiva de que a entrada não é consumida).
  - **CA-8:** índice 1 (2º item) do glossário renderizado é o item do EBITDA, asserção feita **sobre a ordem do DOM** e não sobre `assertSee` (que passa em qualquer posição); + borda de que a lista continua com 12 itens (pega remoção acidental, que é justamente o que o default manda **não** fazer).
  - **Suíte inteira** como rede para o resto.

### Decisões tomadas

- **Alvo do CA-7: `ncg_absoluto.positiva`, não `ncg_vendas`.** A estória apontou "`ncg_vendas` × `farol_positivo` em `config/recomendacoes/dez-2025.php`". Esse par não existe: `ncg_vendas` tem `verde`/`amarelo`/`vermelho` (§F.14) e não há farol "positivo". O alvo real é `ncg_absoluto` → `positiva` (§F.13), e a prova não é de gosto: **o texto novo pedido é exatamente a segunda frase do texto atual dessa entrada**. Edvan riscou a primeira frase do texto que ele próprio escreveu no Anexo F. Nenhuma leitura de `ncg_vendas` produz esse recorte. Um teste de borda trava o erro inverso (`ncg_vendas` intocado), porque escrever no indicador errado era o modo de falha real desta tarefa.

- **O parágrafo "Saldos atuais — uma foto do balanço de hoje." foi removido junto com a troca do título do Bloco 3.** Ele era a versão curta da mesma frase; o texto do Edvan foi promovido a título do bloco. Manter os dois deixaria "Bloco 3 de 4 — Saldos Atuais – uma foto da situação econômica do negócio hoje" com "Saldos atuais — uma foto do balanço de hoje." logo abaixo, na mesma dobra. É a única remoção que fiz além das que a estória pediu; se o PO discordar, é uma linha para voltar.

- **Glossário: reordenado, não renomeado.** O CA-8 chama o item de "Ebitda (Margem Operacional)"; o rótulo vigente é `EBITDA / Margem Operacional`. O verbo da estória é "Reordenar" e o Anexo I §I.2 (documento editorial da EBC) grafa o acrônimo em caixa alta — renomear seria mudança editorial não pedida, em um termo que aparece em todo relatório. Entendi o CA-8 como *identificação* do item a mover. Se o PO quiser a grafia do `.docx`, é 1 linha em `show.blade.php:50`.

- **Textos transcritos literalmente, incluindo a pontuação.** Seguindo o briefing ("valem como string literal — transcrever, não reescrever"), mantive o **travessão curto** (`–`) do `.docx` no título do Bloco 3 e no subtítulo do imobilizado, embora o resto do arquivo use travessão longo (`—`), e mantive o imobilizado **sem ponto final**, como no `.docx`. Não é descuido; é transcrição.

- **Q20 com `help` vazio em vez de `@include` sem a chave.** O parcial `campo-brl` já trata `! empty($help)`, então passar `''` desliga o parágrafo sem tocar no parcial (que é compartilhado por 14 campos). KISS: nenhuma mudança de contrato para remover um texto.

- **Nada de bump de `motor_version`.** Verifiquei a condição em vez de presumir: nada do que toquei entra na saída de `Motor::calcular()`. Os 5 golden hashes rodam idênticos depois da mudança — o que é, ao mesmo tempo, a confirmação de que não há bump devido **e** a evidência do problema do CA-7 (a entrada editada não chega a diagnóstico nenhum).

- **Escopo recusado no sweep sugerido pelo briefing** ("mover strings hard-coded próximas para config", máx +30min): olhei e **não movi nada**. Os `help` do quiz estão hard-coded no Blade enquanto os tooltips vivem em `config/quiz/help-industria.php` — a assimetria é real e incomoda. Mas mover os 14 subtítulos para config no mesmo commit misturaria refatoração estrutural com um pacote editorial que o PO vai querer revisar linha a linha, e mexeria em campos que esta devolutiva não tocou. Fica registrado como candidato a estória própria.

### Descobertas relevantes

- **Gotcha de Blade que custou um ciclo vermelho:** escrever `<x-help>` dentro de um comentário `//` de PHP, em arquivo `.blade.php`, **quebra o arquivo**. O compilador de tags de componente do Blade varre o template como texto antes de qualquer coisa e não sabe o que é comentário — ele tenta compilar a tag, o `@include` que continha o comentário deixa de ser reconhecido como diretiva e vai **inteiro para o HTML como texto literal**, com todo o conteúdo do comentário junto. O sintoma foi cruel de ler: o teste reclamava que um texto que eu tinha acabado de apagar continuava na página (era o texto **do meu comentário**) e que o tooltip do campo tinha sumido (o `@include` nunca rodou). Regra prática para o próximo: em `.blade.php`, nunca cite tags `<x-*>` literalmente em comentário — nem em `{{-- --}}`, nem em `//`.

- **`assertSee` do Dusk compara texto renderizado, e `.label` do design system é `text-transform: uppercase`.** Asserção por rótulo de campo falha por CSS, não por conteúdo. Nos testes de browser, campo se confere por seletor `@dusk`; texto corrido (parágrafos de ajuda, títulos) é que se confere por string. Custou um segundo ciclo vermelho.

- **O item anterior a "Farol / Semáforo" mudou de identidade por efeito colateral da reordenação.** Antes desta estória, o item logo acima de "Farol / Semáforo" era justamente o do EBITDA — o mesmo que o CA-8 manda subir para 2º. Depois da reordenação, o vizinho de cima do Farol passa a ser "Necessidade de Capital de Giro (NCG)". Isso importa porque a pendência não respondida do Edvan é *"excluir o item que está antes do Farol/Semáforo"*: **se ele responder agora, "o item antes do Farol" já não é o mesmo item de quando ele escreveu o `.docx`**. Deixei um teste travando o vizinho atual, para que a ambiguidade não se resolva sozinha em silêncio, e sinalizo ao PO na seção de bloqueios.

- **A matriz agora diverge do Anexo F §F.13.** O `config/motor/matriz-dez-2025-industria.php` se declara "transcrito literalmente do Anexo F". Depois desta edição, isso deixou de ser verdade para uma célula. Registrei a divergência em comentário no próprio config (com o porquê e a data), mas **não editei o Anexo F**: é documento editorial de propriedade da EB Parcerias e a estória não pediu. Fica para o PO.

### Pendências fechadas com Edvan

Verifiquei o estado real antes de aplicar default: a pasta `devolutivas/2026-05-27-EBC/` contém apenas `README.md` e `pauta-retorno.md` — **não há nenhum `resposta-edvan-*.md`**. O prazo era **2026-06-04** e hoje é **2026-08-04**: venceu há 2 meses. Ambos os defaults aplicados:

1. **Título do Bloco 2 (pauta-retorno §4) — fechada como "mantida".** Segue `DRE e Operação`. Travado por teste (`CA-4 (borda) — o default vencido do título do Bloco 2 foi aplicado`), porque "decidi manter" e "esqueci de mexer" são indistinguíveis no diff, e só o teste registra que foi decisão.

2. **Item do glossário a remover (pauta-retorno §3) — fechada como "diferida".** Nenhum item removido; a lista segue com 12 termos. Travado por dois testes: contagem/unicidade dos 12 termos e identidade do vizinho de cima do "Farol / Semáforo". Ressalva importante para reabrir a conversa com o Edvan: ver "Descobertas" — o vizinho mudou por efeito da própria reordenação do CA-8.

### Cobertura final

- **Pest — suíte completa:** 849 passed / 2557 asserções (0 falhas). Eram 828 antes desta estória; **+21 testes**.
- **Dusk E2E (Chromium headless, browser real):** 23 passed / 136 asserções (eram 22; **+1**).
- **Cobertura geral:** 97,3% (mín. exigido 80%).
- **Cobertura de domínio** (`phpunit-domain.xml`): 99,6% (mín. exigido 98%).
- **Pint:** 235 arquivos, PASS. **PHPStan:** No errors.
- **Golden hashes:** os 5 inalterados — nenhuma re-emissão devida (ver "Decisões tomadas").
- **Testes adicionados:**
  - `tests/Feature/Livewire/Diagnostico/PacoteConteudoEbcTest.php` — 14 testes (CA-1 a CA-6). Cada CA com o caminho feliz **e** a borda que quebraria de verdade: remoção cirúrgica vs. parágrafo inteiro apagado, texto novo coexistindo com o antigo, título novo colocado fora do cabeçalho, sub-bloco de captação fechado (asserção vazia), tooltip apagado junto com o texto auxiliar.
  - `tests/Domain/Motor/MatrizNcgPositivoTest.php` — 4 testes (CA-7): igualdade estrita do texto novo; cenário `negativa` intocado; `ncg_vendas` intocado (o alvo errado que a estória sugeria); e não-regressão de snapshot, rodando o motor real sobre um payload de NCG positivo.
  - `tests/Feature/Http/DiagnosticoShowTest.php` — 3 testes (CA-8/CA-9): posição no DOM (não `assertSee`, que é cego a ordem), 12 termos únicos com o 1º intacto, e o vizinho do "Farol / Semáforo".
  - `tests/Browser/PacoteConteudoEbcBrowserTest.php` — 1 teste de fluxo (CA-10) percorrendo Bloco 1 → 4 e terminando na ordem do glossário do relatório gerado.
  - Todos falharam pelo motivo certo antes da mudança (TDD) e passaram depois.

### Bloqueios / pendências (para o PO)

- **`[ESCALONAMENTO-PO]` — CA-7 não fecha sem uma decisão de produto.** A entrada que a estória manda editar (`ncg_absoluto` da matriz dez-2025) **não é consumida pelo motor**. `NcgAbsoluto.php` usa 3 mensagens próprias (FOLGA / MODERADO / ALTO) — decisão explícita da STORY-032, que preferiu a granularidade de 3 faixas entregue pela STORY-028 aos 2 cenários do Anexo F, e deixou a entrada da matriz "disponível para auditoria editorial". Consequência: **editei o texto e nenhum usuário vê diferença** — nem em diagnóstico novo, nem em legado.
  Para o CA-7 passar como escrito, `NcgAbsoluto` teria que passar a ler a matriz, o que **troca as 3 faixas por 2 cenários** e apaga a granularidade que a STORY-028 entregou. Isso é escopo de produto/editorial e reverte uma decisão de outra estória — não é minha para tomar sozinho no meio de um pacote de conteúdo. As opções, para o PO escolher:
  1. **Aceitar como está** — a edição vale como correção editorial da matriz (auditoria), e o CA-7 se fecha reconhecendo que não há efeito visível. Custo zero.
  2. **Levar o texto do Edvan para as mensagens do usuário** — mantendo as 3 faixas e reescrevendo MODERADO/ALTO com o texto revisado. Preserva a granularidade; exige redação do Edvan para as duas faixas e **bump de `motor_version`** (o texto entra no snapshot).
  3. **Migrar `NcgAbsoluto` para a matriz** — cumpre o CA-7 ao pé da letra, desfaz a decisão da STORY-032, perde as 3 faixas e exige bump.
  Minha leitura: a (2) é a que atende o que o Edvan quis dizer sem jogar fora trabalho anterior — mas a escolha é do PO. **Vale confirmar com o Edvan se ele sabia que esse texto não aparece para o usuário hoje.**

- **Anexo F §F.13 (coluna Indústria, linha "NCG positiva") ficou desatualizado** em relação ao config. Não editei — é doc editorial da EBC. Sugiro corrigir junto com a decisão do item acima, para não editar o anexo duas vezes.

- **Pendência do glossário deve ser reformulada antes de reperguntar ao Edvan** — "o item antes do Farol/Semáforo" mudou de identidade com a reordenação desta estória (ver "Descobertas"). Reperguntar com a frase original produziria a remoção do item errado.

- **Push, CI e deploy em homologação não foram feitos** — o workflow do projeto é commit direto em `main` local, sem push/PR/tag sem pedido explícito do PO. Quando autorizado, faltam: CI verde e o sweep visual em homologação (o quiz inteiro; nenhum diagnóstico precisa ser regerado, já que nada do relatório mudou além da ordem do glossário).

- **Divergência de `sprint_id` deixada como está:** o arquivo da estória diz `SPRINT-2026-W26`, o `index.json` diz `SPRINT-2026-W32`. Não é minha para reconciliar.

### Links de evidência

- **Commit:** `9686148` — `feat(conteudo): pacote editorial da devolutiva EBC — STORY-042`.
- **Arquivos de produção tocados (3):**
  - `app/resources/views/livewire/diagnostico/quiz.blade.php` — CA-1 a CA-6.
  - `app/resources/views/diagnosticos/show.blade.php` — CA-8.
  - `app/config/motor/matriz-dez-2025-industria.php` — CA-7 (metade viável).
- **Verificação em browser real** (`PacoteConteudoEbcBrowserTest`, Chromium headless): percorre Bloco 1 → 2 → 3 → 4 com `Q17 = sim`, submete e checa a ordem do glossário no relatório gerado — 17 asserções, todas verdes.

### Verificação em homologação — 2026-08-04 (release `v0.11.0-rc.1`)

`main` empurrada (`5d7d192`) → CI verde
([run 30941074737](https://github.com/xandroalmeida/defonline/actions/runs/30941074737)) → tag
`v0.11.0-rc.1` → `release-homolog.yml`
([run 30941264096](https://github.com/xandroalmeida/defonline/actions/runs/30941264096)) verde nas 4
etapas, incluindo o smoke read-only pós-deploy. `/health` de `https://defonline.xandrix.com.br`
reporta `v0.11.0-rc.1`.

Sweep visual feito em navegador real contra a URL pública, percorrendo o quiz pela UI:

- **CA-1 ✅** — o Bloco 2 renderizado em homologação **não contém a palavra "anualiza"** (asserção
  negativa sobre a página inteira).
- **CA-4 ✅** — o Bloco 3 exibe o título revisado
  `Saldos Atuais – uma foto da situação econômica do negócio hoje`, e a string antiga
  `Bloco 3 de 4 — Balanço` **não** aparece.
- **CA-6 ✅** — verificado por consequência: o sub-bloco de captação (`Q17 = sim`) abriu com o Q20
  sem os 2 textos auxiliares, e a regra que os substituiu (R4, STORY-043) disparou corretamente.
- **CA-8 ✅** — no relatório renderizado, o **2º item do glossário** é o do EBITDA (leitura da ordem
  real do DOM, não `assertSee`).

**O CA-7 não foi verificado em homologação porque não é verificável ali** — e essa é exatamente a
questão escalada: a entrada `ncg_absoluto.positiva` não é consumida por nenhum caminho de render, em
diagnóstico novo ou legado. O deploy não muda esse fato.

### `[ESCALONAMENTO-PO]` reiterado em 2026-08-04 — a estória segue `in_review`

Com CI verde e deploy verificado, **o único item que impede o `done` é o CA-7**, e ele é decisão de
produto, não de implementação: fazer o texto revisado da matriz chegar ao usuário exige reabrir a
decisão da STORY-032 (o `NcgAbsoluto` não consome a matriz). As 3 opções registradas acima seguem
sobre a mesa, sem resposta. Enquanto isso, o **EPIC-002 não pode ser promovido a `done`**, porque o
`promote_to_done_when` exige STORY-039..044 todas em `done`.


## Decisão do PO — 2026-08-04

`[ESCALONAMENTO-PO]` **resolvido: CA-7 marcado `n/a`.** Registrado em `decisions/pdr/PDR-006-escalacoes-sprint-w32.md` §2.

A estória fecha como `done` com **9 critérios verdes e 1 não aplicável**.

O CA-7 foi escrito sobre uma premissa errada — a de que o `NcgAbsoluto` lê a matriz. Ele não lê, por decisão deliberada e documentada da `STORY-032`: o Anexo F oferece 2 cenários e o motor entrega 3 faixas semânticas, e a granularidade maior é mais útil ao usuário. Fazer o CA passar exigiria desfazer aquela decisão e trocar três mensagens por duas, **piorando o produto para satisfazer um critério de aceite mal formulado**. O erro é meu, na redação do CA; a resposta certa é corrigir o critério, não o produto.

O agente acertou em não decidir sozinho, e acertou em entregar a metade que era dele — o texto está na matriz e o legado está intocado, ambos com teste.

**O resíduo legítimo virou a `STORY-060`:** a intenção editorial da EBC de fato não chegou ao usuário, e a entrada da matriz virou configuração inerte que vai confundir o próximo agente. Aquela estória leva a intenção para as três mensagens de faixa que o produto realmente exibe — com bump de versão do motor e legado preservado — e resolve a entrada inerte.
