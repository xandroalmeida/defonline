---
story_id: STORY-041
slug: plural-acoes-veredito
title: Plural "ações" no texto fixo do veredito em_alerta
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: bugfix
target_role: programador
requires_design: false
status: in_review
owner_agent: programador (claude-opus-5)
created_at: 2026-06-02
updated_at: 2026-08-04
estimated_session_size: XS
related_devolutiva: "Áudio Edvan 2026-05-27 — 'achei excelente essa abertura aí no resumo executivo: sua empresa apresenta indicadores em estado de alerta que demandam ações. Talvez no plural, talvez ficasse melhor.'"
---

# STORY-041 — Plural "ações" no veredito em_alerta

> Edvan aprovou o texto de abertura do veredito `em_alerta` mas sugeriu trocar **"demandam ação"** → **"demandam ações"** (plural). Diff de 1 palavra.

## Contexto

Devolutiva do Edvan (áudio 2026-05-27): "achei excelente essa abertura aí no resumo executivo: 'sua empresa apresenta indicadores em estado de alerta que demandam ações'. Talvez no plural, talvez ficasse melhor". O print do diagnóstico `cajuina cristal` confirma que hoje aparece **"demandam ação"** (singular). A spec §4.7.1 já tinha "ações" — implementação divergiu da spec.

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- Spec V2 §4.7.1 (texto canônico dos vereditos).
- IDR-010 (motor_version + golden hashes).

## O quê

Trocar "ação" por "ações" no texto fixo do veredito `em_alerta` na config do motor. Decidir junto com o Arquiteto se isso força bump `motor_version 1.2.0 → 1.2.1`.

## Por quê

Alinha implementação com spec §4.7.1 e absorve devolutiva sem custo.

## Critérios de aceite

- [x] **CA-1 (texto correto):** O veredito `em_alerta` na config retorna a string `"Sua empresa apresenta indicadores em estado de alerta que demandam ações."` (com plural, com ponto final).
- [x] **CA-2 (regressão):** Vereditos `saudavel`, `precisa_atencao` e `fallback` ficam intocados; teste de snapshot da config motor confirma.
- [x] **CA-3 (bump version):** Decidido com Arquiteto: **bump `1.2.0 → 1.2.1`** OU **patch silencioso** (texto é renderizado em runtime, não congelado no snapshot). Default sugerido pelo PO: **patch silencioso** se o texto NÃO entra no `indicadores_calculados` (snapshot da IDR-010). Confirmar com IDR rápida ou aditivo na IDR-010.
  - **Resolvido: bump.** A condição do default do PO foi verificada no código e **não se cumpre** — o texto entra no snapshot. Bump aplicado é **`1.3.0 → 1.3.1`** (não `1.2.0 → 1.2.1`: a STORY-032 já tinha subido a versão depois que esta estória foi escrita). Aditivo registrado na IDR-010.
- [x] **CA-4 (goldens):** Se bump for necessário (CA-3), 5 golden hashes re-emitidos. Se patch silencioso, goldens intocados.
- [x] **CA-5 (testes):** 1 teste assertivo no `ResumoExecutivoTest` (cenário `em_alerta`) verifica a string completa.

## Fora de escopo

- Outros textos de veredito.
- Reescrita do algoritmo §4.7.1.

## Padrões de qualidade exigidos

- Sem regressão em testes existentes (511+ Pest, 695+ asserções).
- Se houver bump de motor_version, IDR-010 aditivo + golden hashes re-emitidos.

## Dependências

- **Bloqueada por:** nada.
- **Bloqueia:** nada.

## Decisões já tomadas

- **Texto canônico:** `"Sua empresa apresenta indicadores em estado de alerta que demandam ações."` (plural, ponto final).
- **Bump version:** decisão a tomar com Arquiteto (CA-3) — default patch silencioso.

## Liberdade técnica do agente

- Se houver bump, registrar IDR ou aditivo na IDR-010.
- Se patch silencioso, documentar no commit + nota no `motor.php` explicando que textos de veredito são render-time e não impactam snapshot.

## Definição de Pronto

- [x] CA-1..CA-5 passam.
- [x] Suíte Pest verde — **828 testes / 2515 asserções** (a suíte cresceu desde os "511+" escritos na estória). Dusk E2E: 22/22. Pint limpo, PHPStan sem erros. Cobertura: 97,3% geral (mín. 80) e 99,6% no domínio (mín. 98), com `ResumoExecutivo` e `Motor` em 100%.
- [ ] CI verde. — **pendente de push** (ver nota abaixo).
- [ ] Deploy em homol verificado (re-abrir diagnóstico Vermelho e ver "ações"). — **pendente de deploy** (ver nota abaixo). Verificação equivalente feita **localmente**, ponta a ponta.
- [x] `index.json` atualizado (entrada da STORY-041; o arquivo segue não-commitado porque carrega trabalho em andamento do PO da WAVE-2026-02 — ver "Bloqueios / pendências").

## Notas do agente

### 2026-08-04 — Leitura inicial completa

- **Documentos lidos:** STORY-041 (inteira), `skills/programador/SKILL.md` + `reading-discipline.md` + `done-checklist.md`, `skills/po/references/agent-task-format.md`, IDR-010 (inteira, incluindo os 3 aditivos), spec V2 §4.7.1 (passos 1–6 + princípios operacionais), código de `app/app/Domain/Motor/ResumoExecutivo.php`, `app/config/motor.php`, `app/tests/Domain/Motor/ResumoExecutivoTest.php`, `app/tests/Domain/Motor/GoldenHashesTest.php`, `resources/views/components/relatorio/resumo-executivo.blade.php`.

- **Entendimento consolidado:** o texto fixo do veredito `em_alerta` está em singular ("demandam ação") na constante `ResumoExecutivo::VEREDITOS_TEXTO`. O PO já fixou o texto canônico em plural. A troca é de 1 palavra, mas `veredito_texto` **faz parte da saída do motor** — entra no `resumo_executivo` persistido (snapshot imutável, IDR-010 §sub-decisão 2) e no JSON que alimenta o golden hash. Logo não é texto render-time.

- **Duas divergências encontradas na leitura (registradas, não inventadas):**
  1. **A estória diz que "a spec §4.7.1 já tinha 'ações'" — não tem.** `especificacao-funcional.md:484` traz *"…que demandam ação."* (singular). A implementação **não** divergiu da spec: as duas estavam em singular. O que existe é uma devolutiva do Edvan pedindo o plural, e o PO fixou o texto canônico em "Decisões já tomadas". Consequência prática: a spec também precisa ser corrigida, senão o próximo agente vai "consertar" o código de volta para o singular. Trato isso como parte do bugfix (o texto canônico é decisão do PO já registrada na própria estória), não como mudança de escopo.
  2. **A estória fala em `motor_version 1.2.0 → 1.2.1`, mas a versão vigente é `1.3.0`** (`config/motor.php` — a STORY-032 bumpou 1.2.0 → 1.3.0 depois que a STORY-041 foi escrita). O bump correto é **1.3.0 → 1.3.1**.

- **Dúvidas:** nenhuma bloqueante. O CA-3 dá a regra de decisão de forma condicional ("patch silencioso **se** o texto NÃO entra no `indicadores_calculados`"), e a condição é verificável no código — ver "Decisões tomadas" abaixo.

- **Plano:**
  1. TDD: teste assertivo da string completa de `em_alerta` + teste de regressão dos outros 3 vereditos (`saudavel`, `precisa_atencao`, `fallback`) → vermelho.
  2. Trocar a string em `ResumoExecutivo::VEREDITOS_TEXTO` → verde.
  3. Bump `motor.version` 1.3.0 → 1.3.1 com comentário de linhagem no `config/motor.php`.
  4. Re-emitir os golden hashes que efetivamente mudarem (só as fixtures cujo veredito é `em_alerta`) e registrar o motivo no cabeçalho do `GoldenHashesTest`.
  5. Corrigir a spec §4.7.1 e registrar aditivo na IDR-010.
  6. Suíte completa (Pest) + Pint + PHPStan.

- **Testes que pretendo escrever:**
  - **CA-1 (caminho feliz):** cenário `em_alerta` → `veredito_texto` é exatamente `"Sua empresa apresenta indicadores em estado de alerta que demandam ações."` (asserção de igualdade estrita da string inteira, não `toContain`).
  - **CA-2 (regressão / snapshot da tabela de textos):** os 3 textos de veredito + a mensagem de fallback conferidos de uma vez, congelando a tabela inteira — pega tanto o alvo quanto qualquer dano colateral.
  - **CA-2 (borda oposta):** cenário `fallback` continua com `veredito_texto === null` (o fallback não tem texto de veredito — se alguém "consertar" a constante criando uma chave `fallback`, este teste pega).
  - **Bordas já cobertas pela suíte que preciso manter verdes:** `em_alerta` com todos vermelhos (Passo 6, sem destaque positivo) e o teste de limite de comprimento do bloco (`ResumoExecutivoTest:263` soma `mb_strlen($out['veredito_texto'])` — o plural adiciona 1 caractere ao bloco, então esse teste é sensível à mudança e precisa ser reavaliado, não só re-rodado).

### Decisões tomadas

- **CA-3 → bump, não patch silencioso.** O CA-3 deu a regra de forma condicional ("patch silencioso **se** o texto NÃO entra no snapshot"). Fui verificar a condição em vez de aplicar o default, e ela **não se cumpre**:
  - `ResumoExecutivo::gerar()` devolve `veredito_texto` dentro do `resumo_executivo` (`ResumoExecutivo.php:133`);
  - `Motor::calcular()` devolve esse `resumo_executivo` inteiro, e `CalcularDiagnostico.php:75` grava-o na coluna JSONB imutável `diagnosticos.resumo_executivo` (IDR-010 §sub-decisão 2);
  - `resources/views/components/relatorio/resumo-executivo.blade.php:61` renderiza `$resumo['veredito_texto']` **vindo do snapshot**, não de config.

  Ou seja, o texto **é** congelado no snapshot — é justamente por isso que diagnóstico antigo continua mostrando o texto da versão que o gerou, como a spec (Anexo F) exige. Logo o bump é obrigatório. Não inventei decisão de arquitetura: a regra estava escrita no CA-3, só faltava checar o fato.

- **Grau do bump: PATCH → `1.3.0 → 1.3.1`.** Encaixa na definição de PATCH da IDR-010 §sub-decisão 1 ("corrige bug de implementação **que altera output**") — não muda fórmula (não é MAJOR) nem adiciona indicador/setor (não é MINOR). **Correção à estória:** ela falava em `1.2.0 → 1.2.1`, mas a versão vigente era `1.3.0` — a STORY-032 bumpou 1.2.0 → 1.3.0 depois que esta estória foi escrita.

- **Spec §4.7.1 corrigida junto.** A estória afirmava que "a spec §4.7.1 já tinha 'ações' — implementação divergiu da spec". **Não era o caso:** `especificacao-funcional.md:484` também estava em singular. Spec e código estavam de acordo; o que mudou foi a devolutiva do Edvan + o texto canônico fixado pelo PO em "Decisões já tomadas". Corrigi a spec no mesmo commit porque, sem isso, o próximo agente reverteria o código para o singular alegando aderência à spec. Não é ampliação de escopo: o texto canônico é decisão do PO já registrada nesta estória.

- **Aditivo na IDR-010** (2026-08-04) registrando, de forma durável, que **textos de veredito são output do motor, não texto de render** — e que qualquer mudança em `VEREDITOS_TEXTO`, `LINHA_FIXA`, `MSG_FALLBACK` ou `MSG_TUDO_VERDE` exige bump. Comentário equivalente colocado na própria constante, para quem chegar pelo código e não pela IDR.

- **Bug trivial consertado junto (fora do escopo estrito, mas safe):** `Motor.php` lançava `"Setor 'X' não suportado pelo motor (1.2.0)"` com a versão **hardcoded** — já estava mentindo com o motor em 1.3.0. Passou a ler `config('motor.version')`. Mudança só em string de mensagem, coberta por teste existente (o log da suíte já mostra a mensagem correta com `1.3.1`), nenhum teste assertava o texto antigo.

### Descobertas relevantes

- **Os 5 golden hashes mudaram, mas por dois motivos diferentes.** O CA-4 previa "5 hashes re-emitidos" e foi o que aconteceu — mas re-emitir 5 hashes quando só 1 cenário mudou de texto é exatamente o tipo de coisa atrás da qual uma regressão se esconde. Isolei antes de aceitar: fixando `motor.version` de volta em `1.3.0` **com a correção de texto aplicada**, só o fixture `alerta` muda de hash; os outros 4 reproduzem o hash anterior **bit-exato**. Conclusão: `alerta` mudou pelo texto; os outros 4 mudaram apenas porque `motor_version` faz parte da saída de `Motor::calcular()` e portanto do payload hasheado. Sem regressão de cálculo. Registrei esse raciocínio no cabeçalho do `GoldenHashesTest` para o próximo re-emit não precisar redescobri-lo.

- **Impacto no gate de validação externa (STORY-036 / NRF §9.3):** o parecer do validador referencia uma `motor_version` exata, e ela mudou. A mudança é **puramente textual** — nenhum valor calculado, farol, ordenação de destaques ou classificação de veredito foi afetado (provado pelos 4 hashes que voltam bit-exatos). Se a reaprovação formal é necessária mesmo assim é decisão do PO, não minha; deixo o fato registrado aqui e na IDR-010.

- **Diagnósticos já emitidos continuam com "ação" no singular.** É o comportamento correto por design (snapshot imutável), não um bug. A verificação em homologação precisa ser feita em um diagnóstico **novo**, gerado após o deploy — reabrir um diagnóstico Vermelho antigo vai mostrar o singular e parecer que o deploy falhou.

### Cobertura final

- **Pest — suíte completa:** 828 passed / 2515 asserções (0 falhas).
- **Dusk E2E (browser real, Chromium headless):** 22 passed / 119 asserções.
- **Cobertura geral:** 97,3% (mín. exigido 80%).
- **Cobertura de domínio** (`phpunit-domain.xml`): 99,6% (mín. exigido 98%). `Motor/ResumoExecutivo` e `Motor/Motor` em **100%**.
- **Pint:** 232 arquivos, PASS. **PHPStan:** No errors.
- **Testes adicionados** (`app/tests/Domain/Motor/ResumoExecutivoTest.php`):
  - `CA-1/STORY-041 — veredito em_alerta usa "demandam ações" (plural)` — igualdade estrita da string inteira (CA-1/CA-5).
  - `CA-2/STORY-041 — textos dos 3 vereditos + fallback ficam congelados` — congela a tabela inteira exercitando a API pública, um cenário por saída do classificador, incluindo a borda `fallback → veredito_texto === null` (CA-2).
  - Ambos falharam pelo motivo certo antes da correção (TDD) e passaram depois.

### Bloqueios / pendências (para o PO)

- **Push, CI e deploy em homologação não foram feitos** — o workflow do projeto é commit direto em `main` local, sem push/PR/tag sem pedido explícito do PO. Commit local: `a7be1d1`. Quando o PO autorizar o push, faltam: CI verde e a verificação em homologação (**gerar um diagnóstico novo** em cenário Vermelho e conferir "ações" — diagnóstico antigo mostra o singular por design, ver "Descobertas").
- **`index.json` foi atualizado no disco mas não commitado**: o arquivo carrega trabalho em andamento do PO (fechamento da WAVE-2026-01, WAVE-2026-02, EPIC-010, PDR-003/004/005), e commitá-lo varreria essa WIP para dentro do commit desta estória. A entrada da STORY-041 está correta no arquivo; deixo para o PO commitar junto com o trabalho de onda.

### Links de evidência

- **Commit:** `a7be1d1` — `fix(motor): veredito em_alerta usa "demandam ações" (plural) — STORY-041`.
- **Aditivo IDR-010:** `decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md` § Histórico, entrada 2026-08-04.
- **Spec corrigida:** `especificacao/V2/especificacao-funcional.md` §4.7.1, Passo 4 (linha 484).
- **Verificação end-to-end local** (fixture `alerta` → Motor real → snapshot → Blade real renderizado):
  ```
  motor_version  : 1.3.1
  veredito       : em_alerta
  veredito_texto : Sua empresa apresenta indicadores em estado de alerta que demandam ações.
  renderizado    : Sua empresa apresenta indicadores em estado de alerta que demandam ações.
  contém plural  : SIM
  contém singular: NAO
  ```
