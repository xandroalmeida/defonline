<?php

declare(strict_types=1);

use App\Domain\Drm\ConteudoDrm;
use App\Domain\Motor\Farol;
use App\Domain\Motor\MatrizRecomendacoes;
use App\Support\InventarioLacunas;
use Illuminate\Support\Facades\Log;

/*
| STORY-062 — o inventário de lacunas escreve onde o container consegue gravar,
| nos DOIS mecanismos, avisa quando não consegue e a suíte não toca o repositório.
*/

function _lacunasTmpDir(): string
{
    return sys_get_temp_dir().'/lacunas-'.uniqid();
}

function _lacunasRemove(string $dir): void
{
    foreach (glob($dir.'/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($dir);
}

it('CA-4 — a suíte roda com o inventário desligado (path vazio), nos dois mecanismos', function () {
    expect(config('motor.matriz_lacunas_path'))->toBe('')
        ->and(config('drm.lacunas_path'))->toBe('');
});

it('CA-1/CA-2 — o default dos dois mecanismos vive em storage/, gravável no container, e não em defonline-docs/', function () {
    // O .env.testing zera o path; o default é o que vale em dev e produção.
    foreach (['motor' => 'matriz_lacunas_path', 'drm' => 'lacunas_path'] as $arquivo => $chave) {
        $fonte = (string) file_get_contents(config_path($arquivo.'.php'));

        expect($fonte)->toMatch("/'{$chave}' => env\\('[A-Z_]+', storage_path\\(/")
            ->not->toContain('defonline-docs/project-state');
    }
});

it('CA-1/CA-6 — destino gravável (diretório ainda inexistente) recebe a linha, uma vez só', function () {
    $dir = _lacunasTmpDir();
    $arquivo = $dir.'/matriz-lacunas.md';

    try {
        InventarioLacunas::registrar($arquivo, "# Cabeçalho\n\n", '- `a` × `b`');
        InventarioLacunas::registrar($arquivo, "# Cabeçalho\n\n", '- `a` × `b`');

        $conteudo = (string) file_get_contents($arquivo);
        expect($conteudo)->toStartWith("# Cabeçalho\n\n")
            ->and(substr_count($conteudo, '- `a` × `b`'))->toBe(1);
    } finally {
        _lacunasRemove($dir);
    }
});

it('CA-1/CA-2 — Diagnóstico e DRM gravam no inventário quando o destino é gravável', function () {
    $dir = _lacunasTmpDir();
    config([
        'motor.matriz_lacunas_path' => $dir.'/matriz.md',
        'drm.lacunas_path' => $dir.'/drm.md',
    ]);

    try {
        (new MatrizRecomendacoes)->texto('codigo_lacunoso', Farol::VERDE);
        (new ConteudoDrm)->help('D07');

        expect((string) file_get_contents($dir.'/matriz.md'))->toContain('- `codigo_lacunoso` × `verde`')
            ->and((string) file_get_contents($dir.'/drm.md'))->toContain('`help` → `campos.D07`');
    } finally {
        _lacunasRemove($dir);
    }
});

it('CA-3 — destino não gravável avisa com o path tentado, sem lançar, e só uma vez por path', function () {
    Log::spy();
    $pai = tempnam(sys_get_temp_dir(), 'lacunas-pai-');
    $destino = $pai.'/x/inv.md';

    try {
        InventarioLacunas::registrar($destino, 'c', '- l1');
        InventarioLacunas::registrar($destino, 'c', '- l2');
    } finally {
        @unlink($pai);
    }

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $msg, array $ctx = []) => $msg === 'lacunas.inventario.nao_gravavel' && $ctx['path'] === $destino)
        ->once();
});

it('CA-4 — path vazio não grava nada nem avisa', function () {
    Log::spy();

    InventarioLacunas::registrar('', 'c', '- l');

    Log::shouldNotHaveReceived('warning');
});
