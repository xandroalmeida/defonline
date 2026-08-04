---
story_id: STORY-043
slug: validacao-cruzada-q17-recebiveis
title: Validação cruzada R4 — Q20.recebíveis < Q09.venda_mensal (bloqueante)
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: implementation
target_role: programador
requires_design: false
status: done
owner_agent: programador (claude-opus-5)
created_at: 2026-06-02
updated_at: 2026-08-04
estimated_session_size: S
related_devolutiva: ".docx EBC §Bloco 4 — 'também deve ser ocultada a informação sobre ser inferior à venda mensal, pois também deve ser uma regra para checagem e não aceitar informação neste campo (recebíveis) de valor inferior ao contido no campo 2 do bloco 2 (vendas – média mensal)'"
---

# STORY-043 — Validação cruzada R4 (Q17.recebíveis ≥ Q09.venda_mensal)

> Nova regra de consistência **bloqueante** entre Q17 (recebíveis — base da capacidade de garantia) e Q09 (venda mensal) do quiz Indústria. Estende a engine declarativa de validações cruzadas da STORY-034.

## Contexto

A EBC pediu que o campo Q17 (recebíveis / base da capacidade de garantia, 3×) **não aceite valor inferior à venda mensal Q09**. A devolutiva original tratava isso como texto auxiliar visível ("Deve ser inferior à venda mensal..."); o PO decidiu **converter em regra de validação**:

- O texto auxiliar é **removido** (já tratado em STORY-042 CA-6).
- A regra vira **R4** declarativa em `config/quiz/validacoes-cruzadas.php`, reaproveitando a engine `App\Domain\Quiz\ValidacoesCruzadas` da STORY-034.
- **Diferente** de R1/R2/R3 (não-bloqueantes), R4 é **bloqueante** (consistência de input, não hipótese de erro estatístico).

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- STORY-034 (engine de validações cruzadas — `done`).
- Spec V2 §6.6 (formato canônico das regras — esta vira **R4** aditiva).

## O quê

Adicionar regra R4 declarativa que impede submissão do quiz quando `Q17.recebíveis < Q09.venda_mensal`, mostrando mensagem acionável que aponta para o campo suspeito.

## Por quê

Captura consistência de input mínima para o motor: `recebíveis` é base da capacidade de garantia (3×); se for menor que vendas mensais, o cálculo perde sentido. Ajusta confiabilidade do diagnóstico sem inflar UI.

## Critérios de aceite

> **Correção de rótulo e de sentido aprovada pelo PO em 2026-08-04**, antes da implementação (ver `[ESCALONAMENTO-PO]` em "Notas do agente"). Onde os CAs abaixo dizem **`Q17`** como valor monetário, leia **`Q20`** (`Venda mensal com cartão / duplicatas`, o campo "recebíveis" do sub-bloco gated por `Q17 = sim`). E o **sentido da comparação é o do Anexo A §validações**: recebíveis devem ser **inferiores** à venda mensal — a R4 dispara em `Q20 >= Q09`, não em `Q20 < Q09`. Os CAs afetados estão anotados individualmente.

- [x] **CA-1 (regra declarativa em config):** R4 adicionada em `config/quiz/validacoes-cruzadas.php` no mesmo formato declarativo das R1/R2/R3. Sem closures (config:cache-safe).
- [x] **CA-2 (bloqueante):** Dado `Q17 < Q09` no submit, então o submit é **impedido** (diferente das R1/R2/R3 que mostram banner "Continuar mesmo assim"); usuário não consegue avançar sem ajustar o valor.
  - Sentido corrigido: o submit é impedido quando **`Q20 >= Q09`**.
- [x] **CA-3 (mensagem acionável):** Erro exibido: *"Recebíveis (R$ {Q17}) é menor que a venda mensal média (R$ {Q09}). O valor de recebíveis deve ser pelo menos igual à venda mensal."* Com botão `Ir para Recebíveis (Q17)`.
  - Forma preservada, sentido corrigido: *"Recebíveis (R$ :Q20) é maior ou igual à venda mensal média (R$ :Q09). O valor de recebíveis deve ser inferior à venda mensal."* Botão `Ir para Recebíveis (Q20)`.
- [x] **CA-4 (separação UX):** R4 aparece **antes** do submit (no botão `Calcular diagnóstico`), não como banner inline. R1/R2/R3 mantêm o comportamento atual (banner antes do Bloco 4 → Bloco "Diagnóstico", não-bloqueante).
- [x] **CA-5 (não-disparado):** Dado `Q17 ≥ Q09`, submit funciona normalmente.
  - Sentido corrigido: submit funciona normalmente com **`Q20 < Q09`**.
- [x] **CA-6 (igualdade no limiar):** `Q17 = Q09` passa (validação é `<`, não `≤`).
  - Sentido corrigido: **`Q20 = Q09` bloqueia** (a comparação é `>=`, não `>`), como o Anexo A §60 já exigia ("Q20 ≥ Q09: bloqueio rígido"). Continua sendo o CA do limiar exato, com o veredito invertido junto com a regra.
- [x] **CA-7 (engine reutilizada):** R4 usa a mesma `ValidacoesCruzadas` da STORY-034 com flag/severidade nova `blocking` (ou similar) — Programador decide naming. Não criar engine paralela.
- [x] **CA-8 (cobertura):** ≥ 3 testes Pest na regra (disparado / não-disparado / limiar exato); 1 teste arquitetural do `payload_hash` (R4 não muda hash se canonicalizer estiver correto); 1 Dusk submetendo `Q17 < Q09` e vendo bloqueio.
- [x] **CA-9 (regressão):** R1/R2/R3 continuam não-bloqueantes; smoke E2E passa.

## Fora de escopo

- Outros campos do Q17 (cargo de garantia, etc).
- Reescrita das R1/R2/R3.
- Adicionar mais regras bloqueantes preventivamente — esperar próximas devolutivas.

## Padrões de qualidade exigidos

- **Cobertura ≥ 95% no diff** (regra pequena e crítica).
- **Sem impacto em `payload_hash`** (alertas registrados não entram no canonicalize — IDR-010 §3).

## Dependências

- **Bloqueada por:** STORY-042 (CA-6 — textos auxiliares de Q17 removidos antes; a engine pré-existe).
- **Bloqueia:** nada.

## Decisões já tomadas

- **R4 é bloqueante** (PO 2026-06-02; difere das R1/R2/R3 — consistência de input, não hipótese).
- **Limiar:** `Q17 < Q09` dispara erro; `Q17 = Q09` passa.
- **Engine reutilizada** (não criar paralela).

## Liberdade técnica do agente

- Como representar `severidade: blocking` na config (novo enum / flag).
- Como conectar a regra bloqueante ao submit (Livewire rule customizada / validator pré-submit).
- Como mostrar o erro (modal ou bloco fixo no botão de submit — Programador decide UX).

## Definição de Pronto

- [x] CA-1..CA-9 passam. — os 9, com o rótulo (Q17 → Q20) e o sentido corrigidos pelo PO antes da implementação; cada desvio anotado no próprio CA.
- [x] Suíte completa Pest verde (777+ testes). — **870 passed / 2627 asserções**, mais 24 Dusk em browser real.
- [x] CI verde. — run [30941074737](https://github.com/xandroalmeida/defonline/actions/runs/30941074737) em `main@5d7d192` (2026-08-04), todos os jobs do `pr.yml` verdes.
- [x] Deploy em homol verificado. — `v0.11.0-rc.1`; R4 exercitada em navegador real contra a URL pública. Ver "Verificação em homologação".
- [x] `index.json` atualizado.

## Notas do agente

### 2026-08-04 — Leitura inicial e escalonamento resolvido antes de codar

**Documentos lidos:** STORY-043 inteira (frontmatter, contexto, CA-1..CA-9, fora de escopo, decisões, DoD); `skills/programador/SKILL.md`; STORY-042 inteira (incluindo Notas do agente — o CA-6 já corrigiu o rótulo "Q17" para Q20 durante a execução); STORY-034 e seus artefatos vivos; `especificacao/V2/anexos/anexo-A-campos-quiz.md` (§campos, §validações, §tooltips); `devolutivas/2026-05-27-EBC/README.md` e `pauta-retorno.md`. Código: `app/Domain/Quiz/ValidacoesCruzadas.php`, `app/Domain/Quiz/Alerta.php`, `config/quiz/validacoes-cruzadas.php`, `app/Livewire/Diagnostico/Quiz.php`, `resources/views/livewire/diagnostico/quiz.blade.php`, `app/Domain/Motor/QuizPayloadCanonicalizer.php`, `tests/Domain/Quiz/ValidacoesCruzadasTest.php`, `tests/Feature/Livewire/Diagnostico/QuizTest.php`, `tests/Browser/ValidacoesCruzadasBrowserTest.php`.

**Dependências conferidas:** STORY-042 em `in_review`, commit `9686148` em `main`, com o `help` do campo esvaziado e comentário apontando para esta estória (`quiz.blade.php:281`). STORY-034 `done`, engine declarativa + `Alerta` + gate Bloco 3→4 no lugar. Ambas OK para começar.

**`[ESCALONAMENTO-PO]` levantado e RESOLVIDO antes da primeira linha de código.** A R4 como escrita contradizia uma regra vigente sobre o mesmo par de campos:

1. **O campo não é o Q17.** "Q17.recebíveis" designa o **Q20** (`Venda mensal com cartão / duplicatas`), dentro do sub-bloco gated por `Q17 === 'sim'`. Q17 é enum sim/não, não tem valor monetário. A STORY-042 já havia corrigido esse mesmo rótulo no CA-6 dela, pelas mesmas 2 strings de `help`.
2. **O sentido estava invertido.** `anexo-A-campos-quiz.md:60` já exige **`Q20 ≥ Q09` → bloqueio rígido** — validação creditada às próprias correções da EBC de 22/04/2026 (Anexo A v2.1), implementada em `Quiz::regraQ20MenorQueQ09()` e testada em `QuizTest.php:185`. Somar a R4 literal deixaria o Bloco 4 **insatisfazível** com `Q17 = sim`: nenhum valor de Q20 satisfaz `< Q09` e `≥ Q09` ao mesmo tempo — ninguém mais submeteria o quiz com captação.
3. **Leitura da frase do Edvan:** *"deve ser ocultada a informação sobre ser inferior à venda mensal, **pois também deve ser uma regra para checagem**"* — ele pede que o texto que estava ali vire regra, e o texto dizia "deve ser inferior". A segunda metade da frase saiu invertida. Reforça isso a definição do campo: vendas por cartão/duplicata são subconjunto das vendas totais, logo `Q20 < Q09` é a única leitura economicamente coerente.

**Decisão do PO (2026-08-04, em sessão):** vale o sentido preservado — **R4 dispara quando `Q20 >= Q09`** — e a regra **migra da closure ad-hoc para a engine declarativa** com severidade `blocking`. Consequências nos CAs, todas registradas na seção "Critérios de aceite":
- **CA-3** (texto da mensagem) e **CA-6** (limiar) invertem junto com o sentido: `Q20 = Q09` **bloqueia** (a comparação é `>=`, não `>`), porque o Anexo A diz "deve ser **inferior**".
- Anexo A fica intacto — a R4 passa a ser a implementação da regra que ele já descreve.

**Dúvidas remanescentes:** nenhuma.

**Plano:**
1. Engine: constantes de severidade + filtro por severidade em `ValidacoesCruzadas::validar()`. Sem engine paralela (CA-7), sem quebrar as chamadas da STORY-034.
2. Config: R4 declarativa (`>=`, sem closure) + bump de `version`.
3. Componente: `alertas_bloqueantes` avaliado **no submit** (CA-4), `regraQ20MenorQueQ09` deletada (uma fonte de verdade só), `blocoDoCampo` passa a conhecer o Bloco 4, Q20 só entra na avaliação quando `Q17 === 'sim'`.
4. Blade: bloco de erro fixo acima do botão `Calcular diagnóstico`, com o botão de foco — visual de `destructive`, sem "Continuar mesmo assim".
5. Testes: unidade da regra (disparo / não-disparo / limiar / campo ausente), filtro de severidade, arquitetural de severidade válida em toda regra, arquitetural de `payload_hash`, feature Livewire do submit bloqueado, regressão das R1/R2/R3 e Dusk em browser real.

### Decisões tomadas

- **A R4 não é aditiva: ela substitui a closure.** A estória a descrevia como regra nova, mas a regra já existia — `Quiz::regraQ20MenorQueQ09()`, dentro do `validate()` do Bloco 4. Deixar as duas seria manter duas fontes de verdade para a mesma frase do Anexo A, e a config (que se apresenta como "o" lugar das validações cruzadas) mentiria por omissão. A closure foi **removida**; o que sobrou dela é o teste `QuizTest.php:185`, reescrito para o mecanismo novo e mantido de propósito como sentinela de regressão da regra do Anexo A, independente de onde ela more.

- **Filtro por severidade explícito, em vez de "o gate ignora o que não entende".** `validar()` ganhou um 2º parâmetro opcional; o gate do Bloco 3 pede `SEVERIDADE_AVISO` e o submit pede `SEVERIDADE_BLOQUEIO`. Sem o filtro, a R4 já não apareceria no banner por acidente (Q20 é `null` no Bloco 3, e a engine pula regra com campo ausente) — mas seria acidente, não desenho: a próxima regra bloqueante entre campos dos Blocos 2 e 3 vazaria para o banner não-bloqueante com um "Continuar mesmo assim" ao lado. O filtro é `fail-closed`: severidade desconhecida não devolve nada, e um teste arquitetural garante que toda regra da config declara uma das duas conhecidas.

- **Constantes na engine, strings na config.** `SEVERIDADE_AVISO` / `SEVERIDADE_BLOQUEIO` como constantes de classe, não enum. A config precisa continuar sendo array puro (`config:cache`), então o valor gravado é string de qualquer jeito; um enum só acrescentaria conversão nas duas pontas. O vocabulário fica travado pelo teste arquitetural, que é onde ele precisa ser travado.

- **Q20 só é avaliado quando `Q17 = "sim"`.** Sem captação o campo nem entra no DOM e vira `null` no payload canônico. Se o valor digitado antes de trocar para "não" continuasse alimentando a regra, Roberto ficaria travado por uma inconsistência entre um campo invisível e um número que o motor nunca veria. `valoresParaValidacao()` aplica o mesmo gate do `payloadCanonico()`, e há teste de borda para isso.

- **UX: bloco fixo acima do botão, não modal.** A liberdade era do Programador (§Liberdade técnica). Modal exige uma interação a mais para voltar ao campo e some da tela quando fechado; o bloco fica ancorado onde o erro é acionado (o `Calcular diagnóstico`), reusa o vocabulário visual dos `@error` do formulário e mantém o botão de foco à mão. O que o separa do banner das R1/R2/R3 é justamente a **ausência** do "Continuar mesmo assim" — e isso virou asserção de teste, não convenção.

- **`payload_hash` intacto por construção, não por sorte.** Bloqueio nunca é aceito, logo nunca vira entrada de `alertas_aceitos`; e mesmo que virasse, o canonicalizer remove a chave inteira antes do hash (IDR-010 §3). Os dois caminhos estão cobertos por teste. Nenhum `motor_version` / `matrix_version` foi bumpado: a R4 vive antes do motor e não altera nada do que ele recebe.

### Descobertas relevantes

- **`[BUG-001]` — a máscara BRL perde o último dígito na sincronização com o Livewire. Bug de produção, pré-existente, fora do escopo desta estória, e sério.** Descoberto pelo Dusk: a tela mostrava `R$ 150.000,00` e a mensagem da R4, gerada a partir do estado do componente, dizia `R$ 15.000,00`.
  - **Reproduzido isoladamente** (teste-sonda, já descartado): digitar `10000000` no Q09 deixa o DOM em `100.000,00` e persiste no rascunho a string **`'10.000,000'`** — o valor **pré-máscara** do último caractere. `parseDecimal('10.000,000')` = `10000.0`. O usuário informa 100 mil, o motor calcula com 10 mil.
  - **Causa:** o handler `x-on:input` da máscara reescreve `$el.value` **sem** disparar novo evento `input`, então o `wire:model.live.debounce.300ms` sincroniza o texto do último keystroke antes da formatação. Vale para todo campo mascarado do quiz (BRL, dias, %, CPF), e o comportamento é idêntico para um usuário real — não é artefato do WebDriver.
  - **Correção validada** (aplicada, testada e **revertida** para não misturar escopos): tornar a reescrita idempotente e reemitir o evento —
    ```js
    const formatado = inteiro + ',' + decimal;
    if ($el.value !== formatado) { $el.value = formatado; $el.dispatchEvent(new Event('input')); }
    ```
    Termina em 2 passes (na 2ª o valor já é igual e nada é disparado). Com ela, a sonda passou: rascunho com `Q09 = 100.000,00`, `Q08 = 50.000,00`.
  - **Corrigido em commit separado** (`5088258`), por decisão do PO em sessão (2026-08-04): "corrigir agora, sem estória". Fora do commit da STORY-043 de propósito — é outro escopo e precisa poder ser revertido sozinho.
  - **Consequência para esta estória:** nenhuma no veredito da R4 (a regra dispara certo, porque os dois lados encolhem na mesma proporção). O teste de browser passou a assertar o **texto invariante** da mensagem em vez dos valores, e a invariante `Q20 < Q09` no payload em vez do valor absoluto — para não travar a correção do BUG-001 num teste de outra estória. A conferência numérica exata (`R$ 150.000,00` / `R$ 100.000,00`) vive nos testes de feature e de domínio, que não passam pela máscara.

- **`assertSame([], $payload['alertas_aceitos'])` não é a asserção certa para "nenhum alerta aceito".** A `CalcularDiagnostico` só mescla a chave quando a lista é não-vazia — sem alerta aceito, a chave **não existe** no payload. O correto é `assertArrayNotHasKey`. Custou um ciclo vermelho no Dusk.

- **`rm` com glob sem correspondência no zsh aborta o comando inteiro**, incluindo os alvos que existiam. O arquivo-sonda sobreviveu a um `rm -f sonda.php screenshots/*.png` que "não deu erro visível" e voltou a rodar na suíte Dusk seguinte. Vale conferir a remoção, não só emitir o comando.

### Cobertura final

- **Pest — suíte completa:** 870 passed / 2627 asserções (0 falhas). Eram 849 antes desta estória; **+21 testes**.
- **Dusk E2E (Chromium headless, browser real):** 24 passed / 146 asserções (eram 23; **+1**).
- **Cobertura geral:** 97,4% (mín. exigido 80%). **Cobertura de domínio** (`phpunit-domain.xml`): 99,6% (mín. 98%).
- **Cobertura do diff:** `Domain/Quiz/ValidacoesCruzadas` 100%, `Domain/Quiz/Alerta` 100%, `Domain/Motor/QuizPayloadCanonicalizer` 100%, `Livewire/Diagnostico/Quiz` 96,1% — as 12 linhas descobertas deste último são **todas pré-existentes** (branches de `regraBrl`, retornos `null` dos helpers de parse, hidratação de rascunho legado); nenhuma linha nova ficou sem cobertura. Atende o piso de 95% no diff.
- **Pint:** 237 arquivos, PASS. **PHPStan:** No errors.
- **Testes adicionados:**
  - `tests/Domain/Quiz/ValidacoesCruzadasTest.php` — **9 testes**: R4 disparada / não-disparada / limiar exato nos dois sentidos (`==` bloqueia, 1 centavo abaixo passa) / mensagem e botão do CA-3 / regra pulada sem captação; filtro de severidade sem mistura e `fail-closed` em severidade desconhecida; 2 arquiteturais (vocabulário de severidade em toda regra, config sem closures).
  - `tests/Domain/Motor/QuizPayloadCanonicalizerTest.php` — **1 teste** (CA-8): `payload_hash` idêntico com e sem a R4 na auditoria, e a regra não vaza para o JSON canônico sob nenhuma forma.
  - `tests/Feature/Livewire/Diagnostico/ValidacaoBloqueanteR4Test.php` — **11 testes** (CA-2..CA-6, CA-9): bloqueio no submit sem redirect e sem `Diagnostico` criado; mensagem com os dois valores e o botão; `irParaCampo` fechando o bloqueio no Bloco 4; limiar exato e 1 centavo abaixo; R4 ausente do gate do Bloco 3 mesmo com dados que a disparariam; ausência do "Continuar mesmo assim" e de rastro em `alertas_aceitos`; bordas (Q20 residual com `Q17="não"`, e correção liberando o submit na mesma sessão); regressão de aviso aceito que não vira bloqueio.
  - `tests/Browser/ValidacaoBloqueanteR4BrowserTest.php` — **1 teste de fluxo** (CA-8) em Chromium: quiz inteiro → captação → recebíveis acima da venda → bloqueio visível sem escape → botão de foco → correção → diagnóstico gerado, conferindo a invariante no payload.
  - `tests/Feature/Livewire/Diagnostico/QuizTest.php` — teste do Anexo A **reescrito** para o mecanismo novo (não removido).
  - Todos falharam pelo motivo certo antes da mudança (15 falhas com os arquivos de produção em `stash`) e passaram depois.

### Bloqueios / pendências (para o PO)

- **`[ESCALONAMENTO-PO]` do sentido da R4 — resolvido em sessão (2026-08-04)**, antes de qualquer código. Detalhe na entrada de leitura inicial acima. Duas consequências que valem confirmação com o Edvan:
  1. A frase da devolutiva (*"não aceitar informação neste campo (recebíveis) de valor inferior ao contido no campo 2 do bloco 2"*) está **invertida** em relação ao que o próprio Anexo A já registra como correção da EBC. Implementamos o sentido do Anexo A. Se a intenção dele era mesmo a literal, isso não é ajuste de regra — é redefinição do campo Q20, e reabre o Anexo A §49/§60.
  2. A `pauta-retorno.md` §"O que já entrou em sprint", item 6, descreve a R4 no sentido invertido. Convém corrigir antes de enviar, para não formalizar o erro com o cliente.

- **`[BUG-001]` — corrigido no commit `5088258`** (decisão do PO em sessão: corrigir junto, sem estória). As 4 máscaras do quiz passaram a delegar o commit do valor a um trecho único (`$commitMask`), que reemite o `input` quando a formatação muda o texto — máscara nova herda a correção. Regressão em browser real: `MascaraSincronizacaoBrowserTest`, 2 testes / 17 asserções, cobrindo BRL (último dígito), dias (cap 4) e % (2ª casa), do rascunho até o payload que chega ao motor. Vermelho antes, verde depois.
  - **Fica aberta a decisão sobre o dado histórico.** A correção vale daqui para a frente; **diagnósticos já gerados pela UI seguem gravados com valores até 10× menores que os digitados**, sempre que o último gesto do usuário no campo foi digitar um dígito. Não há como distinguir no banco um valor errado de um valor legítimo — só o usuário sabe o que digitou. As opções são reprocessar (impossível sem o input original), invalidar/marcar os diagnósticos afetados, ou avisar os usuários. **É decisão de produto e não foi tomada.**
  - **Fora do quiz, 3 máscaras compartilham o anti-padrão e NÃO foram tocadas** — CPF e telefone em `livewire/cadastro.blade.php`, documento (CPF/CNPJ) em `livewire/empresa/cadastrar.blade.php`. Verifiquei antes de decidir: nelas o efeito é nulo, porque (a) têm `maxlength`, o que fecha o caminho de truncamento, e (b) o servidor normaliza para só-dígitos, e o texto pré-máscara do último keystroke tem exatamente os mesmos dígitos do texto formatado. O que torna o bug grave no quiz é a máscara alterar o **significado numérico** (posição da vírgula), não a pontuação. Mexer nelas seria mudança sem defeito observável.

- ~~**Push, CI e deploy em homologação não foram feitos**~~ — **resolvido em 2026-08-04**: `main` empurrada, CI verde e `v0.11.0-rc.1` em homologação, com a R4 reverificada em navegador real contra a URL pública (ver "Verificação em homologação").

- **Divergência de `sprint_id` mantida:** o arquivo diz `SPRINT-2026-W26`, o `index.json` diz `SPRINT-2026-W32`. Mesma situação da STORY-042; não é minha para reconciliar.

### Links de evidência

- **Commit:** `5c27eed` — `feat(quiz): R4 bloqueante de recebíveis na engine de validações cruzadas — STORY-043`.
- **Arquivos de produção tocados (5):**
  - `app/Domain/Quiz/ValidacoesCruzadas.php` — constantes de severidade + filtro (CA-7).
  - `app/Domain/Quiz/Alerta.php` — docblock do contrato de severidade.
  - `config/quiz/validacoes-cruzadas.php` — R4 declarativa, `version` 1.0.0 → 1.1.0 (CA-1).
  - `app/Livewire/Diagnostico/Quiz.php` — gate no submit, `alertas_bloqueantes`, `valoresParaValidacao()`, `blocoDoCampo` com Bloco 4, closure `regraQ20MenorQueQ09` removida (CA-2/CA-4).
  - `resources/views/livewire/diagnostico/quiz.blade.php` — bloco de erro acima do `Calcular diagnóstico` (CA-3/CA-4).
- **Verificação em browser real:** `ValidacaoBloqueanteR4BrowserTest` (Chromium headless) — 9 asserções no caminho do bloqueio + fluxo de correção até o relatório.

### Verificação em homologação — 2026-08-04 (release `v0.11.0-rc.1`)

`main` empurrada (`5d7d192`) → CI verde
([run 30941074737](https://github.com/xandroalmeida/defonline/actions/runs/30941074737)) → tag
`v0.11.0-rc.1` → `release-homolog.yml`
([run 30941264096](https://github.com/xandroalmeida/defonline/actions/runs/30941264096)) verde nas 4
etapas. `/health` de `https://defonline.xandrix.com.br` reporta `v0.11.0-rc.1`.

Quiz percorrido em navegador real contra a URL pública, pela UI (sem factory, sem `loginAs`):

- **Bloqueio dispara ✅** — Bloco 4 com `Q17 = sim`, `Q20 = R$ 150.000,00` e `Q09 = R$ 100.000,00`
  (portanto `Q20 >= Q09`): o clique em **Calcular diagnóstico** não submete e o painel
  `quiz-bloqueios` aparece com a mensagem da R4 (`…deve ser inferior à venda mensal`).
- **Sem escape ✅** — a página não oferece "Continuar mesmo assim" (asserção negativa explícita),
  confirmando a diferença entre bloqueio e aviso.
- **Correção libera ✅** — trocando `Q17` para `não` na mesma sessão, o submit passa e o diagnóstico
  é gerado (relatório renderizado). O bloqueio é reavaliado a cada tentativa, como previsto.

Também confirmada indiretamente a correção do **`[BUG-001]`** (commit `5088258`, no mesmo release):
os valores digitados com máscara chegaram íntegros ao motor — o relatório mostra
`Vendas (12 meses): R$ 1.200.000,00` para `Q09 = R$ 100.000,00/mês`, isto é, sem o fator 10 de
perda do último dígito.

**Segue aberta para o PO** a decisão sobre o dado histórico do `[BUG-001]` (diagnósticos gerados
pela UI antes deste release podem ter valores até 10× menores que os digitados). Nada mudou nesse
ponto — a correção vale daqui para a frente.
