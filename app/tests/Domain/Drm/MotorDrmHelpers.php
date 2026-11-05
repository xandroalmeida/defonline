<?php

declare(strict_types=1);

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\MotorDrm;
use App\Domain\Drm\Setor;

/* Auxiliares compartilhados pelos testes do motor do DRM (STORY-048). Não é um teste. */

/**
 * Payload canônico: tudo nulo, com os campos dados e os modos dos grupos.
 *
 * @param  array<string, string>  $campos  campo => decimal de duas casas
 * @param  array<string, Modo>  $modos  grupo => modo (default: todos detalhados)
 * @return array<string, mixed>
 */
function motorDrm_payload(array $campos, array $modos = []): array
{
    $payload = array_fill_keys(Lancamento::todosOsCampos(), null);
    foreach ($campos as $campo => $valor) {
        $payload[$campo] = $valor;
    }
    $payload['modos'] = [];
    foreach (Grupo::cases() as $grupo) {
        $payload['modos'][$grupo->value] = ($modos[$grupo->value] ?? Modo::Detalhado)->value;
    }

    return $payload;
}

/** @param  array<string, mixed>  $payload */
function motorDrm_calcular(array $payload, Setor $setor = Setor::Comercio): array
{
    return (new MotorDrm)->calcular($payload, $setor, Competencia::deTexto('2026-09'), new DateTimeImmutable('2026-10-02T12:00:00+00:00'));
}

/** @return array<string, mixed> */
function motorDrm_linha(array $demonstracao, string $modo, string $codigo): array
{
    foreach ($demonstracao['linhas'][$modo] as $linha) {
        if ($linha['codigo'] === $codigo) {
            return $linha;
        }
    }

    throw new RuntimeException("Linha {$codigo} ausente no modo {$modo}.");
}

/** @return list<string> */
function motorDrm_codigos(array $demonstracao, string $modo): array
{
    return array_column($demonstracao['linhas'][$modo], 'codigo');
}

/**
 * Comércio, tudo detalhado. Contas à mão:
 *   L1 100000,00 · L2 6000,00 · L3 94000,00 · L4 30000+5000+1000 = 36000,00 · L5 58000,00
 *   L6a 2000+1500+500,50 = 4000,50 · L6b 10000+3000+5000+800,25 = 18800,25
 *   fora:   L6 22800,75 · L7 35199,25 · L7b 200−50 = 150,00 · L8 900+100 = 1000,00
 *           L9 35199,25+150−1000 = 34349,25 · L10 8000,00 · L11 26349,25
 *   dentro: L6b 26800,25 · L6 30800,75 · L7 (EBITDA) 27199,25 · L11 27199,25+150−1000 = 26349,25
 */
function motorDrm_comercioCompleto(): array
{
    return motorDrm_payload([
        'D01' => '100000.00', 'D02' => '6000.00',
        'D04' => '30000.00', 'D06' => '5000.00', 'D07' => '1000.00',
        'D09' => '2000.00', 'D10' => '1500.00', 'D13' => '500.50',
        'D16' => '10000.00', 'D17' => '3000.00', 'D23' => '5000.00', 'D28' => '800.25',
        'D38' => '900.00', 'D39' => '100.00',
        'D40' => '8000.00', 'D41' => '200.00', 'D42' => '50.00',
    ]);
}
