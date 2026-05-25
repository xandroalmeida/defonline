<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Domain\Motor\QuizPayloadCanonicalizer;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;

/*
| ----------------------------------------------------------------------
| CalcularDiagnostico — persistência + idempotência ponta-a-ponta (espec §4.5
| + IDR-010 §sub-decisão 2 + STORY-028 CA-6).
| ----------------------------------------------------------------------
*/

function payloadIndustriaCanonico(): array
{
    return [
        'Q01' => 1,
        'Q02' => '50000',
        'Q03' => '80000',
        'Q04' => '60000',
        'Q05' => '300000',
        'Q06' => '40000',
        'Q07' => '30000',
        'Q08' => '50000',
        'Q09' => '100000',
        'Q10' => 45,
        'Q11' => 30,
        'Q12' => 30,
        'Q13' => '2',
        'Q14' => '20000',
        'Q15' => '8000',
        'Q16' => '2000',
    ];
}

it('persiste um Diagnostico com snapshot completo', function () {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    $action = app(CalcularDiagnostico::class);
    $diag = $action->execute($empresa, payloadIndustriaCanonico());

    expect($diag)->toBeInstanceOf(Diagnostico::class);
    expect($diag->motor_version)->toBe('1.0.0');
    expect($diag->matrix_version)->toBe('dez-2025');
    expect($diag->setor)->toBe('industria');
    expect($diag->usuario_id)->toBe($u->id);
    expect($diag->empresa_analisada_id)->toBe($empresa->id);
    expect($diag->payload_hash)->toMatch('/^[0-9a-f]{64}$/');
    expect(count($diag->indicadores_calculados))->toBe(8);
});

it('calcula payload_hash como SHA-256 do canonical JSON', function () {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    $payload = payloadIndustriaCanonico();
    $hashEsperado = hash('sha256', QuizPayloadCanonicalizer::toJson(QuizPayloadCanonicalizer::canonicalize($payload)));

    $diag = app(CalcularDiagnostico::class)->execute($empresa, $payload);

    expect($diag->payload_hash)->toBe($hashEsperado);
});

it('é idempotente de OUTPUT — dois execute() com mesmo input geram mesmo payload_hash e mesmos indicadores', function () {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    $payload = payloadIndustriaCanonico();
    $action = app(CalcularDiagnostico::class);

    $d1 = $action->execute($empresa, $payload);
    $d2 = $action->execute($empresa, $payload);

    // IDR-010 §sub-decisão 2: NÃO deduplica em banco — são 2 registros conscientes.
    expect($d1->id)->not->toBe($d2->id);
    // Mesmo payload_hash + mesmas versões → indicadores bit-exatos.
    expect($d1->payload_hash)->toBe($d2->payload_hash);
    expect($d1->hasSameInputsAs($d2))->toBeTrue();

    $i1 = iterator_to_array($d1->indicadores_calculados);
    $i2 = iterator_to_array($d2->indicadores_calculados);
    expect($i1)->toBe($i2);
});

it('canonicalização funciona — payload reordenado produz mesmo hash', function () {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    $a = payloadIndustriaCanonico();
    $b = array_reverse($a, preserve_keys: true);

    $action = app(CalcularDiagnostico::class);
    $dA = $action->execute($empresa, $a);
    $dB = $action->execute($empresa, $b);

    expect($dA->payload_hash)->toBe($dB->payload_hash);
});

it('quiz_payload persistido é o canonical (chaves ordenadas)', function () {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    // Entrada com chaves fora de ordem.
    $payload = array_reverse(payloadIndustriaCanonico(), preserve_keys: true);

    $diag = app(CalcularDiagnostico::class)->execute($empresa, $payload);

    // Releitura do banco — Postgres jsonb pode reordenar, então comparamos o conteúdo
    // re-canonicalizado e o hash, não a ordem do array após SELECT.
    expect($diag->payload_hash)->toBe(
        hash('sha256', QuizPayloadCanonicalizer::toJson(QuizPayloadCanonicalizer::canonicalize($payload))),
    );
});

it('gerado_em é setado pelo Action (não pelo motor — IDR-010 §sub-decisão 4)', function () {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    $antes = now();
    $diag = app(CalcularDiagnostico::class)->execute($empresa, payloadIndustriaCanonico());
    $depois = now();

    expect($diag->gerado_em->between($antes->subSecond(), $depois->addSecond()))->toBeTrue();
});

it('rollback de transaction se algo falhar — exception inesperada não persiste registro parcial', function () {
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    // Força exception pedindo setor inválido.
    expect(fn () => app(CalcularDiagnostico::class)->execute($empresa, payloadIndustriaCanonico(), 'agropecuaria'))
        ->toThrow(InvalidArgumentException::class);

    expect(Diagnostico::withoutGlobalScopes()->count())->toBe(0);
});

it('valor de indicador é numérico quando calculável (margem_bruta verde 50%)', function () {
    // **Nota:** Postgres `jsonb` normaliza floats sem fração para int ao armazenar
    // (50.0 → 50). O cast `AsArrayObject` devolve o tipo que veio do banco. O
    // contrato de idempotência usa o `payload_hash` (input) e os golden hashes
    // (output em memória, antes do banco) — não o tipo do valor relido. Por isso
    // usamos `toEqual` (igualdade matemática) aqui.
    $u = Usuario::factory()->create();
    Auth::login($u);
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $u->id]);

    $diag = app(CalcularDiagnostico::class)->execute($empresa, payloadIndustriaCanonico());
    $mb = $diag->indicadores_calculados['margem_bruta'];

    expect($mb['valor'])->toEqual(50.0)
        ->and($mb['farol'])->toBe('verde')
        ->and($mb['motivo'])->toBeNull();
});
