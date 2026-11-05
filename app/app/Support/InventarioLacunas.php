<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Append idempotente no inventário de lacunas (PDR-009 §2 item 4; STORY-062).
 *
 * Um único caminho para os dois mecanismos (`MatrizRecomendacoes` e
 * `ConteudoDrm`). I/O lateral: nunca lança e nunca altera o que o chamador
 * devolve (determinismo do motor, IDR-010 §sub-decisão 3).
 *
 * - destino vazio desliga o I/O (é o que a suíte usa — CA-4);
 * - o diretório do destino é criado se faltar (o default vive em `storage/`);
 * - se não der para gravar, avisa com `Log::warning` e o path tentado (CA-3),
 *   uma vez por path e processo, para não inundar o log a cada cálculo.
 */
final class InventarioLacunas
{
    /** @var array<string,true> paths já avisados neste processo */
    private static array $avisados = [];

    /**
     * @param  string  $destino  path do arquivo; '' desliga
     * @param  string  $cabecalho  texto inicial, usado só quando o arquivo ainda não existe
     * @param  string  $linha  linha sem quebra final
     */
    public static function registrar(string $destino, string $cabecalho, string $linha): void
    {
        if ($destino === '') {
            return;
        }

        $dir = dirname($destino);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            self::avisar($destino, 'diretório inexistente e não foi possível criá-lo');

            return;
        }

        $existente = is_file($destino) ? (string) @file_get_contents($destino) : '';
        if (str_contains($existente, $linha."\n")) {
            return;
        }

        if ($existente === '') {
            $existente = $cabecalho;
        }

        if (@file_put_contents($destino, $existente.$linha."\n") === false) {
            self::avisar($destino, 'arquivo não gravável');
        }
    }

    private static function avisar(string $destino, string $motivo): void
    {
        if (isset(self::$avisados[$destino])) {
            return;
        }
        self::$avisados[$destino] = true;

        Log::warning('lacunas.inventario.nao_gravavel', ['path' => $destino, 'motivo' => $motivo]);
    }
}
