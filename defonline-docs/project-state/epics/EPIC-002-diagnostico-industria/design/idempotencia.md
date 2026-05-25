---
artifact: design-note
title: Protocolo de idempotência do motor — canonicalização, hash, golden files
related_idr: IDR-010
related_story: STORY-026
related_epic: EPIC-002
status: draft
created_at: 2026-05-25
updated_at: 2026-05-25
---

# Protocolo de idempotência do motor

> **Contrato (IDR-010 sub-decisão 3).** Dado um `quiz_payload` canonicalizado X + `motor_version` = `M` + `matrix_version` = `Mat` + `setor` = `S`, o motor produz **saída bit-exata**. Mudança do hash de saída em CI = bump obrigatório de `motor_version` (ou correção de bug, justificada no PR).

## 1. Canonicalização do `quiz_payload`

O `quiz_payload` entra no motor como um array PHP vindo do Livewire da STORY-027. Antes de calcular qualquer coisa, o motor o **canonicaliza** e mantém essa forma como única fonte de verdade para hash e persistência.

Regras de canonicalização (aplicadas em ordem, idempotentes):

1. **Chaves ordenadas lexicograficamente** (recursivo em arrays associativos).
2. **Decimais como string** com casas fixas conforme o campo (Anexo A):
   - Valores monetários: 2 casas (`"123456.78"`).
   - Percentuais: 2 casas (`"4.50"`).
   - Prazos em dias: inteiros sem casa (`"45"`).
   - Quantidades sem decimal: inteiro literal JSON.
3. **String vazia ⟶ `null`** (Q* opcionais ficam `null`, nunca `""`).
4. **Booleanos**: literais `true`/`false` JSON (sem `"1"`/`"0"` ou `"sim"`/`"nao"`).
5. **Encoding**: UTF-8 NFC. Acentos normalizados (`"José"` ≠ `"José"`).
6. **Nenhum metadado** entra no payload: `Q01..Q23` são o universo. Sem `created_at`, sem `usuario_id`, sem `csrf`.

A implementação fica em `App\Domain\Motor\QuizPayloadCanonicalizer` (STORY-028). Exposta como método estático `canonicalize(array $raw): array` + `toJson(array $canonical): string` que serializa com `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION`.

## 2. Função de hash

```php
$payloadHash = hash('sha256', QuizPayloadCanonicalizer::toJson($canonical));
```

- **Algoritmo:** SHA-256 (hex lowercase, 64 chars).
- **Onde fica:** coluna `diagnosticos.payload_hash`. Validada por CHECK constraint (`^[0-9a-f]{64}$`) na migration.
- **O hash inclui apenas `quiz_payload` canonicalizado.** Não inclui `motor_version`/`matrix_version`/`setor` — esses são chaves correlatas, capturadas em colunas próprias e usadas em `Diagnostico::hasSameInputsAs()`.

## 3. Golden hashes da saída do motor

Para garantir que a **saída** é bit-exata (não só o input), o motor é testado contra um conjunto de **golden hashes** congelados.

### Layout

```
app/
  tests/
    Domain/
      Motor/
        Fixtures/
          quiz_industria_saudavel.json
          quiz_industria_atencao.json
          quiz_industria_alerta.json
          quiz_industria_ncg_negativo.json
          quiz_industria_70pct_indisponivel.json
        GoldenHashesTest.php
```

### Estrutura do fixture

Cada `*.json` em `Fixtures/` é um `quiz_payload` **já canonicalizado** (chaves ordenadas, decimais como string, etc.). Comentário inicial no JSON é proibido — JSON puro. Documentação do cenário fica no nome do arquivo + bloco docstring no teste.

### Estrutura do teste

```php
// app/tests/Domain/Motor/GoldenHashesTest.php
use App\Domain\Motor\Motor;

dataset('fixtures', [
    'saudavel'              => ['quiz_industria_saudavel.json',              '<sha256_saida_esperado>'],
    'atencao'               => ['quiz_industria_atencao.json',               '<sha256_saida_esperado>'],
    'alerta'                => ['quiz_industria_alerta.json',                '<sha256_saida_esperado>'],
    'ncg_negativo'          => ['quiz_industria_ncg_negativo.json',          '<sha256_saida_esperado>'],
    '70pct_indisponivel'    => ['quiz_industria_70pct_indisponivel.json',    '<sha256_saida_esperado>'],
]);

test('motor produz saida bit-exata para fixture canonical', function (string $file, string $expectedHash) {
    $payload = json_decode(file_get_contents(__DIR__."/Fixtures/{$file}"), true);

    $saida = app(Motor::class)->calcular($payload, setor: 'industria');

    $hash = hash('sha256', json_encode($saida, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));

    expect($hash)->toBe($expectedHash);
})->with('fixtures');
```

A `$saida` da função `Motor::calcular()` é um array com a estrutura:

```
[
  "motor_version" => "1.0.0",
  "matrix_version" => "dez-2025",
  "setor" => "industria",
  "indicadores_calculados" => [...14 indicadores...],
  "resumo_executivo" => [...estrutura §4.7.1...],
]
```

Esse array é **a unidade de bit-exatidão**: serializado com a mesma flags de `JSON_*` que a canonicalização, e hasheado.

### Política de bump

| Cenário | Ação |
|---|---|
| Hash mudou e era refactor cosmético | **PR rejeitado** — refactor não deve mudar output. Investigar deriva. |
| Hash mudou por bug fix que **corrige** output (ex.: divisão por zero retornava 0 antes; agora retorna null) | Bump `motor_version` PATCH (`1.0.0 → 1.0.1`); atualiza hashes esperados no PR; reviewer justifica no description. |
| Hash mudou porque adicionou novo indicador (saída cresceu de 7 para 14) | Bump `motor_version` MINOR (`1.0.x → 1.1.0`); atualiza hashes esperados. Hashes V1 (com fixtures velhas) **devem** ainda casar — golden test mantém ambas as suítes. Se quebrarem, regressão. |
| Hash mudou porque mudou fórmula (ex.: nova definição de EBITDA) | Bump `motor_version` MAJOR (`1.x.y → 2.0.0`); atualiza hashes. Diagnósticos antigos no banco **não** são recalculados (snapshot — IDR-010). |
| Hash NÃO mudou em PR que pretendia mudar comportamento | **Alerta** — provavelmente o caminho do código não está coberto pelos fixtures atuais. Adicionar fixture nova que exercita o cenário antes de mergear. |

### Como gerar o hash esperado pela primeira vez

```bash
# Quando criar fixture novo:
php artisan tinker --execute='
  $payload = json_decode(file_get_contents("tests/Domain/Motor/Fixtures/quiz_industria_xxx.json"), true);
  $saida = app(App\Domain\Motor\Motor::class)->calcular($payload, setor: "industria");
  echo hash("sha256", json_encode($saida, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)).PHP_EOL;
'
```

O hash impresso vai na `dataset()` do teste.

## 4. Determinismo dentro do motor

Para que a propriedade acima se sustente, o motor **proíbe**:

- `now()`, `Date::now()`, `Carbon::now()` no caminho de cálculo (metadados como `gerado_em` ficam **no controller**, não no motor).
- `Str::random()`, `Str::uuid7()` ou qualquer fonte de aleatoriedade.
- `array_unique`, `array_diff` sem `array_values` reindex — ordenam diferente em PHP 8 vs PHP 9 em chaves não-int.
- `usort` com comparador instável — usar sempre critério primário + secundário (ex.: severidade DESC + ordem do Anexo D ASC).
- Float arithmetic em valores monetários — usar `bcmath` (`bcdiv`, `bcmul`, `bcsub`, `bcadd`) com escala 4 no cálculo + casts para string com `number_format` na serialização. Apenas para o cast final visual (relatório).

## 5. Fixtures recomendados para STORY-028 (mínimo)

| Fixture | Cenário | Cobertura |
|---|---|---|
| `quiz_industria_saudavel.json` | Marcenaria com margens boas, ciclo saudável, sem dívida líquida | Caminho "todos verdes" |
| `quiz_industria_atencao.json` | Margens limítrofes, NCG positivo moderado | Caminho "mistura de farol" |
| `quiz_industria_alerta.json` | EBITDA negativo, dívida alta, ciclo extenso | Caminho "vermelhos" + casos extremos do catálogo |
| `quiz_industria_ncg_negativo.json` | Empresa com folga operacional, NCG ≤ 0 | NCG informativo sem farol |
| `quiz_industria_70pct_indisponivel.json` | Muitos campos faltantes (vendas, despesas) | Fallback fixo do Resumo Executivo §4.7.1 |

STORY-028 pode adicionar mais. STORY-030 (motor V2) adiciona pelo menos 1 fixture por novo indicador (mas os 5 acima **devem** continuar com hash casando).

## 6. Pegadinhas conhecidas

- **Ordem de chaves em `json_encode`:** `json_encode` do PHP preserva a ordem de inserção do array. Se a canonicalização ordenou as chaves antes de codificar, o JSON sai ordenado — **mas** se algum agregado dentro do motor faz `array_merge` no meio do caminho e isso vai para o output, a ordem muda. Cuidado em código que monta `indicadores_calculados`.
- **`AsArrayObject` cast no Eloquent:** o cast (`Diagnostico::$casts` usa `AsArrayObject::class`) lê do banco como `ArrayObject`. Para hashear de novo (em testes), converter para array com `iterator_to_array()` antes de `json_encode`.
- **Postgres `jsonb` reordena chaves automaticamente** ao armazenar. Por isso o hash **não** é calculado depois de ler do banco — é calculado **antes** de inserir, e persistido em `payload_hash`. A leitura do `quiz_payload` para exibição (debug, suporte) não precisa de ordem; a comparação de idempotência usa `payload_hash`.
- **Locale do `bcmath`:** `bcmath` é locale-independent (sempre `.` como separador decimal) — seguro. **Não** usar `number_format($value, 2, ',', '.')` no caminho de cálculo (formato BR muda o output).

## 7. Resumo dos artefatos a serem criados pela STORY-028

- [ ] `app/app/Domain/Motor/QuizPayloadCanonicalizer.php`
- [ ] `app/app/Domain/Motor/Motor.php`
- [ ] `app/tests/Domain/Motor/Fixtures/*.json` (5 fixtures listados acima)
- [ ] `app/tests/Domain/Motor/GoldenHashesTest.php`
- [ ] `app/config/motor.php` com `'version' => '1.0.0'`, `'matrix_version' => 'dez-2025'`
- [ ] CI step: garantir que `phpunit-domain.xml` cobre `app/Domain/Motor` com `--min=98` (já existe; só registrar o novo path).
