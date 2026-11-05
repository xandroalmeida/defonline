<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\LancamentoCanonicalizer;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;
use App\Models\DrmLancamento;
use App\Models\EmpresaAnalisada;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Lançamento vigente (versão 1) válido e coerente com o próprio hash.
 *
 * @extends Factory<DrmLancamento>
 */
final class DrmLancamentoFactory extends Factory
{
    /** @var class-string<DrmLancamento> */
    protected $model = DrmLancamento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $empresa = EmpresaAnalisada::factory();

        return [
            'id' => (string) Str::uuid7(),
            'empresa_analisada_id' => $empresa,
            'usuario_id' => fn (array $atributos) => EmpresaAnalisada::withoutGlobalScopes()->findOrFail($atributos['empresa_analisada_id'])->usuario_id,
            'competencia' => '2026-09-01',
            'versao' => 1,
            'substituido_por_id' => null,
            'setor' => Setor::Comercio->value,
            'lancamento_payload' => fn (array $atributos) => $this->payloadPadrao(Setor::tryFrom($atributos['setor']) ?? Setor::Comercio),
            'payload_hash' => fn (array $atributos) => LancamentoCanonicalizer::hash($atributos['lancamento_payload']),
            'demonstracao' => [],
            'alertas_aceitos' => [],
            'motor_drm_version' => '0.0.0',
            'gerado_em' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadPadrao(Setor $setor): array
    {
        $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Agregado);

        $texto = ['D01' => '84.300,00', 'D02' => '5.058,00', 'D03' => '41.200,00', 'D08' => '9.000,00',
            'D15' => '20.702,00', 'D37' => '1.100,00', 'D40' => '4.000,00', 'D41' => '', 'D42' => ''];

        return Lancamento::deTexto($setor, $modos, $texto)->payload();
    }
}
