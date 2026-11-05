<?php

declare(strict_types=1);

/**
 * Processo filho dos testes de concorrência da carteira (STORY-073 CA-6): outro processo PHP,
 * outra conexão com o Postgres, concluindo uma análise de verdade (`CalcularDiagnostico`).
 *
 * Uso: php carteira_concorrente.php <usuario_id> <empresa_id> <quiz_id|-> <inicio_epoch_float>
 * Espera até `inicio` para largar junto com os irmãos. Imprime uma linha JSON com o resultado.
 */

use App\Actions\CalcularDiagnostico;
use App\Domain\Cobranca\AnaliseJaConcluida;
use App\Domain\Cobranca\SaldoInsuficiente;
use App\Models\EmpresaAnalisada;
use Illuminate\Contracts\Console\Kernel;

[, $usuarioId, $empresaId, $quizId, $inicio] = $argv;

$raiz = dirname(__DIR__, 2);
require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$payload = [
    'Q01' => 1, 'Q02' => '50000', 'Q03' => '80000', 'Q04' => '60000', 'Q05' => '300000', 'Q06' => '40000',
    'Q07' => '30000', 'Q08' => '50000', 'Q09' => '100000', 'Q10' => 45, 'Q11' => 30, 'Q12' => 30,
    'Q13' => '2', 'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
];

$empresa = EmpresaAnalisada::withoutGlobalScopes()->findOrFail($empresaId);
$acao = $app->make(CalcularDiagnostico::class);

while (microtime(true) < (float) $inicio) {
    usleep(500);
}

try {
    $diagnostico = $acao->execute($empresa, $payload, quizId: $quizId === '-' ? null : $quizId);
    $saida = ['resultado' => 'ok', 'diagnostico_id' => $diagnostico->id];
} catch (SaldoInsuficiente $e) {
    $saida = ['resultado' => 'sem_saldo', 'motivo' => $e->bloqueio->motivo->value];
} catch (AnaliseJaConcluida $e) {
    $saida = ['resultado' => 'ja_concluida', 'diagnostico_id' => $e->diagnosticoId];
} catch (Throwable $e) {
    $saida = ['resultado' => 'erro', 'mensagem' => $e::class.': '.$e->getMessage()];
}

echo json_encode($saida), "\n";
