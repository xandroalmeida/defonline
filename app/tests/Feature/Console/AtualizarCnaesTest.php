<?php

declare(strict_types=1);

use App\Console\Commands\AtualizarCnaes;
use App\Domain\Cnae;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->original = file_get_contents(Cnae::arquivo());
});

afterEach(function () {
    file_put_contents(Cnae::arquivo(), $this->original);
});

it('baixa do IBGE, normaliza e grava a lista ordenada', function () {
    $itens = [];
    for ($i = 1100; $i >= 1; $i--) {
        $itens[] = ['id' => sprintf('%07d', $i), 'descricao' => "ATIVIDADE  NÚMERO {$i}"];
    }
    $itens[] = ['id' => '12', 'descricao' => 'CÓDIGO CURTO DESCARTADO'];
    Http::fake([AtualizarCnaes::URL => Http::response($itens)]);

    $this->artisan('cnae:atualizar')->assertSuccessful();

    $gravado = json_decode((string) file_get_contents(Cnae::arquivo()), true);
    expect($gravado)->toHaveCount(1100)
        ->and($gravado[0])->toBe(['codigo' => '0000001', 'descricao' => 'Atividade número 1']);
});

it('não altera o arquivo quando o IBGE falha ou devolve lista incompleta', function ($resposta) {
    Http::fake([AtualizarCnaes::URL => $resposta]);

    $this->artisan('cnae:atualizar')->assertFailed();

    expect(file_get_contents(Cnae::arquivo()))->toBe($this->original);
})->with([
    'HTTP 500' => fn () => Http::response('erro', 500),
    'lista curta' => fn () => Http::response([['id' => '1622699', 'descricao' => 'X']]),
]);
