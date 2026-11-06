<?php

declare(strict_types=1);

namespace App\Services\Diagnostico;

use App\Mail\RenovacaoDiagnosticoMessage;
use App\Models\AvisoRenovacaoDiagnostico;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Scopes\BelongsToUsuarioScope;
use App\Models\Usuario;
use App\Support\DataExibicao;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * STORY-099 — envia o aviso de prazo vencido e o lembrete 30 dias depois, um e-mail por empresa.
 *
 * A vaga do envio é reservada com UPDATE condicional antes de enviar, então rodar de novo ou ao mesmo
 * tempo não repete o e-mail. Se o envio falhar, a reserva é devolvida e a próxima rodada tenta de novo.
 */
final class AvisarRenovacaoDeDiagnostico
{
    public const LEMBRETE_DIAS = 30;

    /**
     * @param  (callable(int, int, int): void)|null  $progresso  recebe (feitas, total, enviados)
     * @return array{enviados: int, falhas: int}
     */
    public function executar(?callable $progresso = null): array
    {
        $empresas = $this->empresasElegiveis();
        $total = $empresas->count();
        $enviados = 0;
        $falhas = 0;

        foreach ($empresas->values() as $i => $empresa) {
            $ultimo = $this->ultimo($empresa);

            if ($ultimo !== null && ! RenovacaoDeDiagnostico::venceEm($ultimo, $empresa->renovacao_diagnostico_meses)->isFuture()) {
                $resultado = $this->avisar($empresa, $ultimo);
                $enviados += $resultado === true ? 1 : 0;
                $falhas += $resultado === false ? 1 : 0;
            }

            if ($progresso !== null) {
                $progresso($i + 1, $total, $enviados);
            }
        }

        return ['enviados' => $enviados, 'falhas' => $falhas];
    }

    /** @return Collection<int, EmpresaAnalisada> */
    private function empresasElegiveis(): Collection
    {
        $usuarios = Usuario::query()
            ->whereNotNull('email_confirmed_at')
            ->whereNull('anonimizado_at')
            ->whereNull('aviso_renovacao_descadastrado_em')
            ->select('id');

        return EmpresaAnalisada::query()
            ->withoutGlobalScope(BelongsToUsuarioScope::class)
            ->whereIn('usuario_id', $usuarios)
            ->whereHas('diagnosticos', fn ($q) => $q->withoutGlobalScope(BelongsToUsuarioScope::class))
            ->orderBy('created_at')
            ->get();
    }

    private function ultimo(EmpresaAnalisada $empresa): ?Diagnostico
    {
        return Diagnostico::query()
            ->withoutGlobalScope(BelongsToUsuarioScope::class)
            ->where('empresa_analisada_id', $empresa->id)
            ->latest('gerado_em')
            ->first();
    }

    /** @return bool|null `true` enviou, `false` falhou, `null` não havia nada a enviar */
    private function avisar(EmpresaAnalisada $empresa, Diagnostico $diagnostico): ?bool
    {
        AvisoRenovacaoDiagnostico::query()->firstOrCreate(['diagnostico_id' => $diagnostico->id]);
        $aviso = AvisoRenovacaoDiagnostico::query()->where('diagnostico_id', $diagnostico->id)->firstOrFail();

        if ($aviso->aviso_em === null) {
            return $this->enviarReservando($aviso, 'aviso_em', $empresa, $diagnostico, false);
        }

        if (! $aviso->sem_lembrete && $aviso->lembrete_em === null && $aviso->aviso_em->lessThanOrEqualTo(now()->subDays(self::LEMBRETE_DIAS))) {
            return $this->enviarReservando($aviso, 'lembrete_em', $empresa, $diagnostico, true);
        }

        return null;
    }

    private function enviarReservando(AvisoRenovacaoDiagnostico $aviso, string $coluna, EmpresaAnalisada $empresa, Diagnostico $diagnostico, bool $lembrete): ?bool
    {
        $reservou = AvisoRenovacaoDiagnostico::query()->whereKey($aviso->id)->whereNull($coluna)->update([$coluna => now()]);

        if ($reservou === 0) {
            return null;
        }

        if ($this->enviar($empresa, $diagnostico->gerado_em, $lembrete)) {
            return true;
        }

        AvisoRenovacaoDiagnostico::query()->whereKey($aviso->id)->update([$coluna => null]);

        return false;
    }

    private function enviar(EmpresaAnalisada $empresa, Carbon $geradoEm, bool $lembrete): bool
    {
        try {
            $usuario = Usuario::query()->findOrFail($empresa->usuario_id);

            Mail::to($usuario->email)->send(new RenovacaoDiagnosticoMessage(
                $usuario->primeiroNome(),
                $empresa->nomeExibicao(),
                DataExibicao::data($geradoEm),
                route('diagnosticos.novo', $empresa->id),
                URL::signedRoute('avisos.renovacao.descadastro', ['usuario' => $usuario->id]),
                $lembrete,
            ));

            return true;
        } catch (Throwable $e) {
            Log::error('diagnostico.aviso_renovacao_nao_enviado', [
                'module' => 'diagnostico',
                'action' => 'avisar_renovacao',
                'empresa_id' => $empresa->id,
                'lembrete' => $lembrete,
                'exception' => $e::class,
            ]);

            return false;
        }
    }
}
