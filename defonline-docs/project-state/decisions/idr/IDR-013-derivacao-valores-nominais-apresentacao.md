---
idr_id: IDR-013
slug: derivacao-valores-nominais-apresentacao
title: Agregados monetários de exibição derivados do quiz_payload na camada de apresentação (sem tocar o snapshot do motor)
status: proposed
decided_by: programador
owner_agent: programador (claude-opus-5)
related_story: STORY-044
related_stories: ["STORY-029", "STORY-030", "STORY-044"]
related_adrs: []
related_idrs: ["IDR-010"]
supersedes: null
superseded_by: null
decided_at: 2026-08-04
created_at: 2026-08-04
updated_at: 2026-08-04
---

# IDR-013 — Agregados monetários de exibição derivados do `quiz_payload` na apresentação

## Contexto

A STORY-044 (feedback do Key User José Marques, item A) pede que o relatório de Diagnóstico exiba, junto a cada indicador, os valores nominais em reais que o produziram — "margem bruta de 22%" sem dizer "de quanto" parece caixa-preta.

Ao abrir o código, o snapshot persistido do diagnóstico se revelou **duas coisas distintas**, e a distinção é o centro desta decisão:

- **`indicadores_calculados`** — a saída do motor. Guarda, por indicador, apenas `{valor, farol, motivo, mensagem}` (`App\Domain\Motor\IndicadorResultado::toArray()`). **Os agregados intermediários — Lucro Bruto, EBITDA, LOL, Ativo Total, PL, NCG — nunca foram carimbados aqui.** Eles existem apenas em memória, dentro da `DreAdaptada`, durante a execução do motor, e são descartados.
- **`quiz_payload`** — a entrada. Persistida canonicalizada no mesmo `INSERT` (`CalcularDiagnostico::execute()`), imutável, coberta pelo `payload_hash`.

Logo, o pedido não é atendível lendo `indicadores_calculados`. As duas saídas possíveis eram: (a) carimbar os agregados na saída do motor, ou (b) derivá-los do `quiz_payload` na apresentação. A opção (a) muda a saída de `Motor::calcular()` e portanto exige bump de `motor_version` e atualização dos golden hashes — exatamente o que a STORY-044 proíbe em CA-2 e em "Fora de escopo" ("Alterar a versão do motor. Esta estória é de apresentação, não de cálculo"). Restou (b), e ela precisa de contrato explícito porque vai se repetir: o EPIC-003 (histórico), o EPIC-007 (PDF) e o EPIC-010 (DRM) vão querer os mesmos números.

## Decisão

> **Decidi derivar os agregados monetários de exibição a partir do `quiz_payload` persistido, numa classe da camada de apresentação (`App\Support\Relatorio\ValoresNominais`), reusando a `DreAdaptada` em modo leitura — sem instanciar o `Motor`, sem escrever nada, sem alterar `payload_hash` nem `motor_version`.**

Concretamente, o contrato é:

1. **A fonte é o registro persistido, não o config nem o request.** `ValoresNominais::doSnapshot((array) $diagnostico->quiz_payload)`. O payload já está canonicalizado no banco — não é recanonicalizado.
2. **Reuso, não reimplementação.** A aritmética dos agregados (anualização ×12, LB = Vendas − Compras, EBITDA = LB − DF − DV, LOL = EBITDA − DespFin, AT, PC, PL) vive só na `DreAdaptada`. A camada de apresentação **não** reescreve fórmula alguma. Os dois agregados que a `DreAdaptada` não expõe (dívida líquida = Q06 − Q02; NCG = Q03 + Q04 − Q07) são composições diretas dos accessores dela, não fórmulas novas.
3. **O `Motor` nunca é instanciado na leitura.** Continua valendo IDR-010 §sub-decisão 4: reabrir um diagnóstico é ler do banco e renderizar.
4. **Nada é gravado.** A renderização do relatório não escreve coluna nenhuma — travado por teste que compara a linha crua da tabela antes e depois do `GET`.
5. **Ausência é silêncio, não placeholder.** Termo faltante zera o indicador inteiro (não se exibe "R$ 600.000,00 sobre —"); indicador sem origem monetária não exibe nada. Snapshot antigo com payload incompleto perde só os indicadores afetados.

## Por quê

**Porque é leitura de snapshot, não recálculo.** O que IDR-010 congela é a **saída do motor** — os 14 indicadores e o resumo executivo, que o validador externo aprova (NRF §9.3) e que o usuário viu no dia. Os agregados de exibição não são saída do motor: nunca estiveram no snapshot, nunca foram auditados, nunca entraram no hash. Derivá-los da entrada persistida é a mesma classe de operação que a tela já faz ao formatar `0.0916` como `9,2%` — transformação de apresentação sobre dado imutável.

**Porque a alternativa está proibida pela estória, e por bom motivo.** Carimbar os agregados exigiria bump de `motor_version`, novos golden hashes, e — o custo real — deixaria os diagnósticos **já emitidos** sem os nominais para sempre, já que IDR-010 proíbe recálculo retroativo. Uma melhoria de leitura que só vale para diagnósticos futuros entrega metade do valor pedido.

**Porque uma segunda implementação das fórmulas seria pior que o problema.** Reescrever "EBITDA = LB − DF − DV" na apresentação criaria duas fontes de verdade para o mesmo número dentro do mesmo produto — que é literalmente o defeito de confiança que o Key User apontou (`analise-po-feedback-jose-marques-2026ago03.md` §F2 descreve o estrago de "duas telas do mesmo produto discordando sobre o mesmo número"). A `DreAdaptada` é pura (sem `now()`, sem banco, sem Auth) e já trata faltante com `null`; consumi-la em leitura é o caminho que mantém uma fórmula só.

## Alternativas consideradas

- **Carimbar os agregados em `indicadores_calculados` (bump de motor).** Descartada: proibida pelo CA-2 e pelo "Fora de escopo" da STORY-044, e deixaria todo o histórico já emitido sem o dado — o oposto do valor pedido. Se algum dia o resumo executivo ou o PDF precisarem dos agregados **auditáveis** (não só exibíveis), aí sim é bump legítimo e esta IDR deve ser reaberta.
- **Reimplementar a aritmética na camada de apresentação, sem depender de `app/Domain`.** Descartada: pureza arquitetural comprada com duplicação de regra financeira. A `DreAdaptada` não tem estado nem I/O — depender dela em leitura não acopla ciclo de vida nenhum. Ver "Trade-offs aceitos" para o risco que isso deixa em aberto.
- **Coluna nova `agregados_exibicao` no `diagnosticos`, preenchida por backfill.** Descartada: migração + backfill sobre snapshot declarado imutável, para guardar dado 100% derivável da coluna ao lado. Custo alto, ganho zero.

## Consequências

### Para outros agentes

- **Agregado monetário de exibição se deriva do `quiz_payload`, não se carimba no snapshot.** Quem precisar de Lucro Bruto / EBITDA / NCG para uma tela nova (histórico, PDF, comparativo) usa `App\Support\Relatorio\ValoresNominais` ou estende-a — não mexe no motor.
- **Fórmula financeira mora em `app/Domain/Motor/Calculos/DreAdaptada`, e só lá.** Se um agregado novo for preciso, o lugar é a `DreAdaptada` (accessor puro, sem alterar a saída de `Motor::calcular()` — o que **não** bumpa `motor_version`), não uma cópia na camada de apresentação.
- **`ValoresNominais::CODIGOS_COM_NOMINAL` é lista explícita e travada por teste.** Indicador novo no motor não ganha nominal sozinho: entra na lista por decisão consciente, ou o teste de contrato falha.

### Para o projeto

- Zero dependência nova, zero migração, zero coluna nova.
- Um acoplamento novo de leitura: `app/Support/Relatorio` → `app/Domain/Motor/Calculos`. Direção correta (apresentação depende de domínio), sem ciclo.

### Trade-offs aceitos

- **Risco de drift em bump MAJOR do motor.** Se um dia uma fórmula da `DreAdaptada` mudar (ex.: EBITDA passar a descontar pró-labore — discussão real, aberta em `[DECIDIR-B]` do parecer do PO), um diagnóstico **antigo** passará a exibir o agregado calculado pela fórmula **nova**, ao lado de um indicador congelado pela fórmula **velha**. O indicador não muda (vem do snapshot); o número de apoio sim. É inconsistência visível, e é o preço de não ter o agregado carimbado.
  - **Magnitude hoje:** nula. A `DreAdaptada` está estável desde a STORY-028 e nenhuma estória aberta muda fórmula dela.
  - **Mitigação quando acontecer:** o bump MAJOR já obriga a atualizar golden hashes; a regra é que o PR que muda fórmula da `DreAdaptada` decide, na mesma revisão, se congela a fórmula antiga para leitura de snapshots antigos ou se aceita o drift. Está escrito em "Como verificar".
- **A camada de apresentação passa a conhecer a semântica dos indicadores** (quais termos compõem cada um). É acoplamento de conteúdo, não de código: se o Anexo D mudar a fórmula de um indicador, o mapa de rótulos aqui precisa acompanhar. Travado parcialmente pelo teste de contrato do catálogo.

## Como verificar

- **Que a decisão está sendo respeitada:** `tests/Feature/Http/DiagnosticoValoresNominaisTest.php` compara a linha crua de `diagnosticos` antes e depois do `GET` do relatório — qualquer escrita durante a renderização falha o teste, nomeando a coluna. Os golden hashes (`tests/Domain/Motor/GoldenHashesTest.php`) continuam provando que a saída do motor não mudou.
- **Que ela não virou obsoleta — gatilho de reabertura:** qualquer PR que altere um método da `App\Domain\Motor\Calculos\DreAdaptada` **e** bumpe `motor_version` deve reabrir esta IDR e decidir explicitamente sobre o drift descrito acima. O mesmo vale se o resumo executivo, o PDF (EPIC-007) ou uma validação externa passarem a precisar dos agregados como dado **auditável** — aí o carimbo no snapshot volta à mesa como bump legítimo.

## Tipo

- [ ] **Padrão transversal**: lib/abordagem que vira default no projeto.
- [ ] **Workaround**: contornar bug/limitação documentado.
- [x] **Convenção interna**: padrão de código local que precisa ser seguido (de onde vem agregado monetário de exibição, e onde ele **não** pode ser escrito).
- [ ] **Otimização**: mudança feita por motivo de performance, com medição.
- [ ] **Refatoração estrutural**: mudança que afeta vários módulos por motivo de qualidade.

---

## Histórico

- 2026-08-04 — criada como `proposed` por programador (claude-opus-5) durante STORY-044
