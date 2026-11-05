<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\CupomRegras;
use App\Domain\Cobranca\MotivoRecusaCupom;
use App\Domain\Cobranca\PrecoComCupom;
use App\Models\Cupom;
use App\Models\CupomConcessao;
use App\Models\Invoice;
use App\Models\Usuario;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * STORY-083 — escolhe o cupom de uma compra, calcula o preço e grava o uso.
 *
 * - **Concedido** (beta, CA-2): aplicado sozinho, e o de maior desconto vence se houver mais de um. Quem
 *   tem concedido aplicável não digita outro: cupons não acumulam (CA-5, ajuste do ADR-010).
 * - **Digitado** (CA-1/CA-3): recusado com o motivo específico.
 * - **Limite (CA-4):** a Invoice pendente com cupom é a reserva da vaga ({@see Cupom::reservasDeOutros()}).
 *   {@see reavaliarTravado()} trava a linha do cupom e conta usos + reservas, então dois checkouts não
 *   levam a mesma última vaga.
 * - **Uso (CA-3):** só nasce quando a contratação conclui ({@see registrarUso()}), na mesma transação do pagamento.
 */
final class CupomDoCheckout
{
    public static function minimoCentavos(): int
    {
        return (int) config('services.payments.valor_minimo_centavos', 500);
    }

    /** O cupom concedido à conta que vale para este item, ou nulo. */
    public function concedido(Usuario $usuario, ItemDeCompra $item, ?CarbonInterface $agora = null): ?CupomAplicado
    {
        $agora ??= now();
        $melhor = null;

        $concessoes = CupomConcessao::query()->where('usuario_id', $usuario->id)->orderBy('concedida_em')->get();

        foreach ($concessoes as $c) {
            $cupom = Cupom::query()->find($c->cupom_id);

            if ($cupom === null || $this->recusa($cupom, $usuario, $item, $agora) !== null) {
                continue;
            }

            $aplicado = $this->aplicar($cupom, true, $c->ciclos_restantes, $item);

            if ($melhor === null || $aplicado->preco->desconto > $melhor->preco->desconto) {
                $melhor = $aplicado;
            }
        }

        return $melhor;
    }

    /** @throws CupomRecusado */
    public function digitado(Usuario $usuario, string $codigo, ItemDeCompra $item, ?CarbonInterface $agora = null): CupomAplicado
    {
        $agora ??= now();
        $cupom = Cupom::query()->where('codigo', CupomRegras::normalizarCodigo($codigo))->first();

        if ($cupom === null) {
            throw new CupomRecusado(MotivoRecusaCupom::Inexistente);
        }

        $motivo = $this->recusa($cupom, $usuario, $item, $agora);

        if ($motivo !== null) {
            throw new CupomRecusado($motivo, $cupom);
        }

        return $this->aplicar($cupom, false, $cupom->ciclos_duracao, $item);
    }

    /**
     * O cupom da compra: o concedido tem prioridade e dispensa o código; senão o digitado, se houver.
     *
     * @throws CupomRecusado
     */
    public function para(Usuario $usuario, ?string $codigo, ItemDeCompra $item): ?CupomAplicado
    {
        $concedido = $this->concedido($usuario, $item);

        if ($concedido !== null) {
            return $concedido;
        }

        return $codigo === null || trim($codigo) === '' ? null : $this->digitado($usuario, $codigo, $item);
    }

    /**
     * Dentro da transação que cria a Invoice: trava o cupom e confere de novo, com usos e reservas.
     *
     * @throws CupomRecusado
     */
    public function reavaliarTravado(Usuario $usuario, CupomAplicado $aplicado, ItemDeCompra $item): CupomAplicado
    {
        $cupom = Cupom::query()->whereKey($aplicado->cupom->id)->lockForUpdate()->firstOrFail();
        $motivo = $this->recusa($cupom, $usuario, $item, now());

        if ($motivo !== null) {
            throw new CupomRecusado($motivo, $cupom);
        }

        return $this->aplicar($cupom, $aplicado->concedido, $aplicado->ciclos, $item);
    }

    /**
     * Campos da Invoice que guardam o retrato do desconto.
     *
     * @return array<string, mixed>
     */
    public function camposDaInvoice(?CupomAplicado $aplicado, int $precoCheio): array
    {
        return [
            'cupom_id' => $aplicado?->cupom->id,
            'cupom_ciclos' => $aplicado?->ciclos,
            'preco_cheio_centavos' => $precoCheio,
            'desconto_centavos' => $aplicado?->preco->desconto ?? 0,
        ];
    }

    /**
     * Grava o uso do cupom da Invoice paga. Chamar dentro da mesma transação que marca a Invoice como paga:
     * se o uso não gravar, o pagamento não é dado como concluído (nunca desconto sem registro).
     */
    public function registrarUso(Invoice $invoice): void
    {
        if ($invoice->cupom_id === null) {
            return;
        }

        $gravou = DB::table('cupom_usos')->insertOrIgnore([
            'id' => (string) Str::uuid7(),
            'cupom_id' => $invoice->cupom_id,
            'usuario_id' => $invoice->usuario_id,
            'desconto_centavos' => $invoice->desconto_centavos,
            'ciclos_restantes' => $invoice->cupom_ciclos,
            'usado_em' => now(),
            'invoice_id' => $invoice->id,
        ]);

        if ($gravou === 0) {
            // A mesma conta pagou duas compras com o mesmo cupom (duas abas): o desconto já foi cobrado, fica o alerta.
            Log::warning('cobranca.cupom_ja_usado', ['module' => 'cobranca', 'invoice_id' => $invoice->id, 'cupom_id' => $invoice->cupom_id]);

            return;
        }

        $cupom = Cupom::query()->find($invoice->cupom_id);

        if ($cupom?->limite_usos !== null && $cupom->usos()->count() > $cupom->limite_usos) {
            // Só acontece se a reserva venceu antes do pagamento (Pix pago depois do prazo) e a vaga foi tomada.
            Log::warning('cobranca.cupom_acima_do_limite', ['module' => 'cobranca', 'invoice_id' => $invoice->id, 'cupom_id' => $cupom->id]);
        }
    }

    private function recusa(Cupom $cupom, Usuario $usuario, ItemDeCompra $item, CarbonInterface $agora): ?MotivoRecusaCupom
    {
        return $cupom->recusaPara($usuario->id, $item->planoId, $item->ciclo, $item->pacoteId, $agora, $cupom->reservasDeOutros($usuario->id));
    }

    private function aplicar(Cupom $cupom, bool $concedido, ?int $ciclos, ItemDeCompra $item): CupomAplicado
    {
        return new CupomAplicado(
            $cupom,
            $concedido,
            $ciclos,
            PrecoComCupom::calcular($item->precoCentavos, $cupom->tipo, $cupom->valor, self::minimoCentavos()),
        );
    }
}
