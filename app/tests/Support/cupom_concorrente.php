<?php

declare(strict_types=1);

/**
 * Processo filho dos testes de concorrência do cupom (STORY-083 CA-4): outro processo PHP, outra conexão com o
 * Postgres, contratando de verdade com o cupom.
 *
 * Uso: php cupom_concorrente.php <usuario_id> <creditos|assinatura> <codigo_do_cupom> <inicio_epoch_float>
 * Espera até `inicio` para largar junto com os irmãos. Imprime uma linha JSON com o resultado.
 */

use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\Usuario;
use App\Services\Cobranca\CupomRecusado;
use App\Services\Cobranca\IniciarAssinatura;
use App\Services\Cobranca\IniciarCompraDeCreditos;
use Illuminate\Contracts\Console\Kernel;

[, $usuarioId, $tipo, $codigo, $inicio] = $argv;

$raiz = dirname(__DIR__, 2);
require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$usuario = Usuario::query()->findOrFail($usuarioId);

while (microtime(true) < (float) $inicio) {
    usleep(500);
}

try {
    $invoice = $tipo === 'creditos'
        ? $app->make(IniciarCompraDeCreditos::class)->executar($usuario, PacoteCredito::query()->where('codigo', 'plus')->firstOrFail(), '203.0.113.7', 'Concorrencia', MeioPagamento::Pix, $codigo)
        : $app->make(IniciarAssinatura::class)->executar($usuario, Plano::query()->where('nome', 'Básico')->firstOrFail(), CicloCobranca::Mensal, '203.0.113.7', 'Concorrencia', MeioPagamento::Cartao, $codigo);
    $saida = ['resultado' => 'ok', 'invoice_id' => $invoice->id, 'status' => $invoice->status->value, 'cupom_id' => $invoice->cupom_id];
} catch (CupomRecusado $e) {
    $saida = ['resultado' => 'recusado', 'motivo' => $e->motivo->value];
} catch (Throwable $e) {
    $saida = ['resultado' => 'erro', 'mensagem' => $e::class.': '.$e->getMessage()];
}

echo json_encode($saida), "\n";
