<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\TermoTipo;
use App\Domain\TermoVigente;
use Illuminate\Support\Facades\View;

/**
 * Registro central de qual view/versão é o "termo vigente" hoje (STORY-012
 * CA-3, CA-4). O `conteudo_hash` é calculado renderizando a view sem layout,
 * com SHA-256 sobre o HTML — assim qualquer mudança no texto invalida o hash
 * registrado nos aceites antigos, permitindo identificar quem precisa re-aceitar
 * quando o jurídico voltar com a redação definitiva.
 *
 * Marketing não tem texto próprio: o aceite é só o opt-in. Mantemos uma "versão"
 * estável para que `term_acceptances.versao` siga consistente.
 */
final class TermosVigentes
{
    /** @var array<string, array{versao: string, view: string|null, rota: string}> */
    private const REGISTRO = [
        'termo_adesao' => [
            'versao' => 'v1-placeholder',
            'view' => 'legal.termo-adesao-v1-placeholder',
            'rota' => '/termos/termo-adesao',
        ],
        'lgpd' => [
            'versao' => 'v1-placeholder',
            'view' => 'legal.politica-privacidade-v1-placeholder',
            'rota' => '/termos/politica-privacidade',
        ],
        'marketing' => [
            'versao' => 'v1-placeholder',
            'view' => null,
            'rota' => '/termos/termo-adesao',
        ],
    ];

    public static function para(TermoTipo $tipo): TermoVigente
    {
        $registro = self::REGISTRO[$tipo->value];

        return new TermoVigente(
            tipo: $tipo,
            versao: $registro['versao'],
            conteudoHash: self::hashDe($registro['view'], $tipo->value, $registro['versao']),
            rota: $registro['rota'],
        );
    }

    private static function hashDe(?string $view, string $tipo, string $versao): string
    {
        if ($view === null) {
            return hash('sha256', "{$tipo}:{$versao}");
        }

        return hash('sha256', View::make($view)->render());
    }
}
