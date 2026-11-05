<?php

declare(strict_types=1);

namespace App\Support\Drm;

use App\Domain\Drm\ConteudoDrm;
use App\Domain\Drm\Ocorrencia;
use Illuminate\Support\HtmlString;

/**
 * Texto de bloqueios e alertas do DRM para a tela: pega o pacote de conteúdo
 * (`ConteudoDrm`, PDR-009) e preenche os `:placeholders` com o contexto da ocorrência.
 */
final class MensagensDrm
{
    public function __construct(private readonly ConteudoDrm $conteudo) {}

    /**
     * @param  array<string, string>  $extras  placeholders que dependem da tela (empresa, competência)
     * @return array{titulo: string, texto: string, consequencia: ?string}
     */
    public function de(Ocorrencia $ocorrencia, array $extras = []): array
    {
        $bloco = str_starts_with($ocorrencia->codigo, 'B')
            ? $this->conteudo->bloqueio($ocorrencia->codigo)
            : $this->conteudo->alerta($ocorrencia->codigo);

        $troca = [];
        foreach ([...$extras, ...$ocorrencia->contexto] as $chave => $valor) {
            $troca[':'.$chave] = $valor;
        }
        if ($ocorrencia->campo !== null) {
            $troca[':campo'] = (string) $this->conteudo->rotuloCampo($ocorrencia->campo);
        }

        $preencher = static fn (?string $texto): ?string => $texto === null ? null : strtr($texto, $troca);

        return [
            'titulo' => (string) $preencher($bloco['titulo'] ?? $ocorrencia->codigo),
            'texto' => (string) $preencher($bloco['texto'] ?? ''),
            'consequencia' => $preencher($bloco['consequencia'] ?? null),
        ];
    }

    /** Escapa e promove só `**negrito**`, como o `<x-help>`. */
    public static function html(string $texto): HtmlString
    {
        return new HtmlString((string) preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e($texto)));
    }
}
