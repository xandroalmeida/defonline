<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Models\Cupom;
use App\Models\CupomConcessao;
use App\Models\CupomUso;
use App\Models\Usuario;
use App\Support\DataExibicao;
use App\Support\Telefone;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Clientes que vieram por um cupom — para acompanhar uma parceria (ex.: um parceiro que distribui o
 * cupom aos clientes dele). Junta quem usou o cupom no checkout e quem o recebeu por concessão, e
 * mostra o que cada um fez desde então: plano atual, empresas e diagnósticos.
 */
final class RelatorioClientesDoCupom
{
    /**
     * @return list<array{usuario_id: string, nome: string, email: string, telefone: ?string, situacao: string, desde: ?CarbonInterface, desconto_centavos: int, plano: string, plano_ativo: bool, empresas: int, diagnosticos: int, ultimo_diagnostico: ?string}>
     */
    public function linhas(Cupom $cupom): array
    {
        $usos = CupomUso::query()->where('cupom_id', $cupom->id)->orderBy('usado_em')->get()->groupBy('usuario_id');
        $concessoes = CupomConcessao::query()->where('cupom_id', $cupom->id)->orderBy('concedida_em')->get()->keyBy('usuario_id');

        $ids = $usos->keys()->merge($concessoes->keys())->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $usuarios = Usuario::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');

        $empresas = DB::table('empresas_analisadas')->whereIn('usuario_id', $ids)->whereNull('deleted_at')
            ->groupBy('usuario_id')->selectRaw('usuario_id, count(*) as total')->pluck('total', 'usuario_id');
        $diagnosticos = DB::table('diagnosticos')->whereIn('usuario_id', $ids)->whereNull('deleted_at')
            ->groupBy('usuario_id')->selectRaw('usuario_id, count(*) as total, max(gerado_em) as ultimo')->get()->keyBy('usuario_id');

        // A assinatura mais recente de cada um, com o plano.
        $assinaturas = Assinatura::query()->with('plano')->whereIn('usuario_id', $ids)
            ->orderBy('data_inicio')->get()->keyBy('usuario_id');

        $linhas = [];
        foreach ($ids as $id) {
            $usuario = $usuarios->get($id);
            if ($usuario === null) {
                continue;
            }

            $usosDoUsuario = $usos->get($id);
            $primeiroUso = $usosDoUsuario?->first();
            $concessao = $concessoes->get($id);
            $assinatura = $assinaturas->get($id);
            $diag = $diagnosticos->get($id);

            $linhas[] = [
                'usuario_id' => $id,
                'nome' => $usuario->anonimizado() ? 'Anonimizado' : $usuario->nome,
                'email' => $usuario->anonimizado() ? '' : (string) $usuario->email,
                'telefone' => $usuario->anonimizado() ? null : Telefone::formatar($usuario->telefone),
                'situacao' => $primeiroUso !== null ? 'Usou' : 'Recebeu, não usou',
                'desde' => $primeiroUso !== null ? $primeiroUso->usado_em : $concessao?->concedida_em,
                'desconto_centavos' => (int) ($usosDoUsuario?->sum('desconto_centavos') ?? 0),
                'plano' => self::plano($assinatura),
                'plano_ativo' => $assinatura !== null && ! $assinatura->trial
                    && in_array($assinatura->status, [StatusAssinatura::Ativa, StatusAssinatura::EmAtraso], true),
                'empresas' => (int) ($empresas[$id] ?? 0),
                'diagnosticos' => (int) ($diag->total ?? 0),
                'ultimo_diagnostico' => isset($diag->ultimo) ? DataExibicao::data((string) $diag->ultimo) : null,
            ];
        }

        usort($linhas, fn (array $a, array $b): int => ($b['desde']?->getTimestamp() ?? 0) <=> ($a['desde']?->getTimestamp() ?? 0));

        return $linhas;
    }

    /**
     * @param  list<array{situacao: string, plano_ativo: bool, diagnosticos: int, desconto_centavos: int}>  $linhas
     * @return array{clientes: int, usaram: int, com_plano_ativo: int, com_diagnostico: int, diagnosticos: int, desconto_centavos: int}
     */
    public static function totais(array $linhas): array
    {
        return [
            'clientes' => count($linhas),
            'usaram' => count(array_filter($linhas, fn ($l) => $l['situacao'] === 'Usou')),
            'com_plano_ativo' => count(array_filter($linhas, fn ($l) => $l['plano_ativo'])),
            'com_diagnostico' => count(array_filter($linhas, fn ($l) => $l['diagnosticos'] > 0)),
            'diagnosticos' => array_sum(array_column($linhas, 'diagnosticos')),
            'desconto_centavos' => array_sum(array_column($linhas, 'desconto_centavos')),
        ];
    }

    private static function plano(?Assinatura $assinatura): string
    {
        if ($assinatura === null) {
            return 'Sem plano';
        }

        $nome = $assinatura->trial ? 'Teste grátis' : ($assinatura->plano !== null ? $assinatura->plano->nome : 'Plano');
        $status = match ($assinatura->status) {
            StatusAssinatura::Ativa => 'ativa',
            StatusAssinatura::EmAtraso => 'em atraso',
            StatusAssinatura::Cancelada => 'cancelada',
            StatusAssinatura::Expirada => 'encerrada',
        };

        return "{$nome} ({$status})";
    }
}
