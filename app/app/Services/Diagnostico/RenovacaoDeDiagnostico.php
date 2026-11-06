<?php

declare(strict_types=1);

namespace App\Services\Diagnostico;

use App\Models\AvisoRenovacaoDiagnostico;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Support\DataExibicao;
use Carbon\CarbonImmutable;

/** STORY-099 — quando o diagnóstico de uma empresa pede renovação, e o que o usuário muda nisso. */
final class RenovacaoDeDiagnostico
{
    /** @var list<int> */
    public const PRAZOS = [3, 6, 9, 12];

    public static function venceEm(Diagnostico $diagnostico, int $meses): CarbonImmutable
    {
        return DataExibicao::somarMeses($diagnostico->gerado_em, $meses);
    }

    /** O último diagnóstico, quando o alerta dele deve aparecer. */
    public function diagnosticoComAlerta(EmpresaAnalisada $empresa): ?Diagnostico
    {
        $ultimo = $this->ultimo($empresa);

        if ($ultimo === null || self::venceEm($ultimo, $empresa->renovacao_diagnostico_meses)->isFuture()) {
            return null;
        }

        $dispensado = AvisoRenovacaoDiagnostico::query()
            ->where('diagnostico_id', $ultimo->id)
            ->whereNotNull('alerta_dispensado_em')
            ->exists();

        return $dispensado ? null : $ultimo;
    }

    public function dispensarAlerta(EmpresaAnalisada $empresa): void
    {
        $ultimo = $this->ultimo($empresa);

        if ($ultimo !== null) {
            AvisoRenovacaoDiagnostico::query()->updateOrCreate(
                ['diagnostico_id' => $ultimo->id],
                ['alerta_dispensado_em' => now()],
            );
        }
    }

    /** O prazo novo vale como um ciclo novo: alerta e e-mails do diagnóstico atual recomeçam. */
    public function mudarPrazo(EmpresaAnalisada $empresa, int $meses): void
    {
        if ($empresa->renovacao_diagnostico_meses === $meses) {
            return;
        }

        $empresa->update(['renovacao_diagnostico_meses' => $meses]);

        $ultimo = $this->ultimo($empresa);

        if ($ultimo !== null) {
            AvisoRenovacaoDiagnostico::query()->where('diagnostico_id', $ultimo->id)->delete();
        }
    }

    private function ultimo(EmpresaAnalisada $empresa): ?Diagnostico
    {
        return $empresa->diagnosticos()->latest('gerado_em')->first();
    }
}
