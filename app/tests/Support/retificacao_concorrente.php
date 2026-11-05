<?php

declare(strict_types=1);

/**
 * Processo filho do teste de concorrência da retificação (STORY-050): outro processo PHP, outra
 * conexão com o Postgres, retificando de verdade (`RetificarLancamento`).
 *
 * Uso: php retificacao_concorrente.php <empresa_id> <competencia AAAA-MM> <base_versao> <D02> <inicio_epoch_float>
 * Espera até `inicio` para largar junto com o irmão. Imprime uma linha JSON com o resultado.
 */

use App\Actions\Drm\RetificacaoConflitante;
use App\Actions\Drm\RetificacaoSemMudanca;
use App\Actions\Drm\RetificarLancamento;
use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Contracts\Console\Kernel;

[, $empresaId, $competencia, $base, $d02, $inicio] = $argv;

$raiz = dirname(__DIR__, 2);
require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$empresa = EmpresaAnalisada::withoutGlobalScopes()->findOrFail($empresaId);
// O Global Scope por usuário lê o usuário autenticado, como numa requisição web.
auth()->setUser(Usuario::query()->findOrFail($empresa->usuario_id));
$modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Agregado);
$lancamento = Lancamento::deTexto(Setor::Comercio, $modos, [
    'D01' => '84.300,00', 'D02' => $d02, 'D03' => '41.200,00', 'D08' => '9.000,00',
    'D15' => '20.702,00', 'D37' => '1.100,00', 'D40' => '4.000,00', 'D41' => '', 'D42' => '',
]);

while (microtime(true) < (float) $inicio) {
    usleep(500);
}

try {
    $nova = $app->make(RetificarLancamento::class)->execute($empresa, Competencia::deTexto($competencia), $lancamento, [], (int) $base);
    $saida = ['resultado' => 'ok', 'versao' => $nova->versao];
} catch (RetificacaoConflitante) {
    $saida = ['resultado' => 'conflito'];
} catch (RetificacaoSemMudanca) {
    $saida = ['resultado' => 'sem_mudanca'];
} catch (Throwable $e) {
    $saida = ['resultado' => 'erro', 'mensagem' => $e::class.': '.$e->getMessage()];
}

echo json_encode($saida), "\n";
