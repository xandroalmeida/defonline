<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Cobranca\WebhookBruto;
use App\Domain\Cobranca\WebhookInvalido;
use App\Services\Cobranca\ReceberWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * STORY-076 CA-4 / ADR-010 §3 — endpoint de webhook do provedor de pagamento. Fora de sessão e de
 * CSRF; a autenticação é o token do provedor, conferido pelo adaptador. Sem token válido: 401 e
 * nada é gravado. Não depende do interruptor de cobrança: pagamento já feito precisa ser tratado.
 */
final class WebhookPagamentoController extends Controller
{
    public function __invoke(Request $request, ReceberWebhook $receber): JsonResponse
    {
        $cabecalhos = [];
        foreach ($request->headers->all() as $nome => $valores) {
            $cabecalhos[strtolower((string) $nome)] = (string) ($valores[0] ?? '');
        }

        try {
            $resultado = $receber->tratar(new WebhookBruto($cabecalhos, $request->getContent()));
        } catch (WebhookInvalido) {
            // STORY-086 CA-5: webhook rejeitado (sem autenticação válida ou ilegível) é token trocado errado (pagamentos param de ser
            // confirmados) ou alguém batendo na porta. Alerta P1, sem corpo, cabeçalho nem IP.
            Log::error('cobranca.webhook_rejeitado', ['module' => 'cobranca', 'action' => 'receber_webhook', 'prioridade' => 'P1']);

            return response()->json(['erro' => 'nao_autorizado'], 401);
        }

        return response()->json(['status' => $resultado]);
    }
}
