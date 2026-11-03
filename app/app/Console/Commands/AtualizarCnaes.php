<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Cnae;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Baixa a tabela de subclasses CNAE da API do IBGE e regrava
 * `resources/data/cnae-subclasses.json` (versionado). Rodar quando o
 * CONCLA publicar nova versão da CNAE e commitar o arquivo.
 */
final class AtualizarCnaes extends Command
{
    public const URL = 'https://servicodados.ibge.gov.br/api/v2/cnae/subclasses';

    protected $signature = 'cnae:atualizar';

    protected $description = 'Baixa a lista de subclasses CNAE do IBGE para resources/data/cnae-subclasses.json';

    public function handle(): int
    {
        $this->info('Baixando subclasses CNAE do IBGE…');

        $resposta = Http::timeout(60)->retry(2, 2000, throw: false)->get(self::URL);

        if (! $resposta->successful() || ! is_array($resposta->json())) {
            $this->error("Falha ao baixar a lista do IBGE (HTTP {$resposta->status()}). Arquivo não alterado.");

            return self::FAILURE;
        }

        $linhas = [];
        foreach ($resposta->json() as $item) {
            $codigo = Cnae::normalizar((string) ($item['id'] ?? ''));
            if (strlen($codigo) !== 7 || ! isset($item['descricao'])) {
                continue;
            }
            $linhas[$codigo] = ['codigo' => $codigo, 'descricao' => Cnae::descricaoLegivel((string) $item['descricao'])];
        }

        if (count($linhas) < 1000) {
            $this->error('Lista do IBGE veio incompleta ('.count($linhas).' subclasses). Arquivo não alterado.');

            return self::FAILURE;
        }

        ksort($linhas, SORT_STRING);

        // Um item por linha: compacto para servir ao navegador e diff legível no git.
        $json = array_map(
            static fn (array $l): string => json_encode($l, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            array_values($linhas),
        );
        file_put_contents(Cnae::arquivo(), "[\n".implode(",\n", $json)."\n]\n");

        $this->info(count($linhas).' subclasses gravadas em resources/data/cnae-subclasses.json.');

        return self::SUCCESS;
    }
}
