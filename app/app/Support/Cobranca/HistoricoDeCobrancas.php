<?php

declare(strict_types=1);

namespace App\Support\Cobranca;

use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\MeioPagamento;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * STORY-081 CA-2 — as cobranças do Usuário para a tela Meu plano: data, descrição, valor, desconto,
 * meio e situação. Não mostra o que nunca virou cobrança (checkout abandonado, vencido ou cancelado,
 * falha nossa com o provedor). O desconto de cupom (STORY-083) vem da própria Invoice.
 */
class HistoricoDeCobrancas
{
    /** @return list<array{data: Carbon, descricao: string, valor_centavos: int, desconto_centavos: int, meio: string, status: string}> */
    public function do(string $usuarioId): array
    {
        return Invoice::query()
            ->where('usuario_id', $usuarioId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Invoice $i): ?array => $this->linha($i))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array{data: Carbon, descricao: string, valor_centavos: int, desconto_centavos: int, meio: string, status: string}|null */
    private function linha(Invoice $i): ?array
    {
        $status = match (true) {
            $i->status === EstadoCobranca::Paga => 'Pago',
            $i->status === EstadoCobranca::Estornada, $i->status === EstadoCobranca::EstornadaParcial => 'Reembolsado',
            $i->status === EstadoCobranca::Contestada => 'Contestado',
            $i->status === EstadoCobranca::Falhou && $i->motivo_encerramento !== 'provedor' => 'Recusado',
            $i->status === EstadoCobranca::Pendente && ! $i->expirada() => 'Pendente',
            default => null,
        };

        if ($status === null) {
            return null;
        }

        return [
            'data' => $i->pago_em ?? $i->created_at ?? now(),
            'descricao' => $i->descricao,
            'valor_centavos' => $i->valor_centavos,
            'desconto_centavos' => $i->desconto_centavos,
            'meio' => match ($i->meio) {
                MeioPagamento::Pix->value => 'Pix',
                'nenhum' => 'Sem cobrança',
                default => 'Cartão',
            },
            'status' => $status,
        ];
    }
}
