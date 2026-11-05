<?php

declare(strict_types=1);

namespace App\Support\Cobranca;

use App\Domain\Cobranca\MotivoRecusaCupom;
use App\Models\Cupom;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Services\Cobranca\CupomRecusado;
use App\Services\Cobranca\ItemDeCompra;

/** STORY-083 CA-3 — o motivo da recusa do cupom, no texto da spec STORY-070-cupom-no-checkout §4 (microcopy provisória). */
final class TextoCupom
{
    public static function recusa(CupomRecusado $e, ItemDeCompra $item): string
    {
        $cupom = $e->cupom;

        return match ($e->motivo) {
            MotivoRecusaCupom::Inexistente => 'Não encontramos esse cupom. Confira o código.',
            MotivoRecusaCupom::ForaDaVigencia => $cupom !== null && now()->toDateString() < $cupom->vigencia_inicio->toDateString()
                ? 'Este cupom ainda não está valendo. Ele começa em '.$cupom->vigencia_inicio->format('d/m/Y').'.'
                : 'Este cupom venceu em '.($cupom?->vigencia_fim->format('d/m/Y') ?? '').'.',
            MotivoRecusaCupom::Inativo => 'Este cupom não está mais ativo.',
            MotivoRecusaCupom::LimiteEsgotado => 'Este cupom atingiu o limite de usos.',
            MotivoRecusaCupom::JaUtilizado => 'Você já usou este cupom.',
            MotivoRecusaCupom::ItemNaoCoberto => 'Este cupom não vale para '.self::nomeDoItem($item).'.'.($cupom === null ? '' : ' Ele vale para '.self::itensDoCupom($cupom).'.'),
        };
    }

    private static function nomeDoItem(ItemDeCompra $item): string
    {
        if ($item->pacoteId !== null) {
            return 'o pacote '.(PacoteCredito::query()->whereKey($item->pacoteId)->value('nome') ?? 'escolhido');
        }

        $plano = $item->planoId === null ? null : Plano::query()->whereKey($item->planoId)->value('nome');

        return 'o plano '.($plano ?? 'escolhido').($item->ciclo === null ? '' : ' '.$item->ciclo);
    }

    private static function itensDoCupom(Cupom $cupom): string
    {
        $nomes = $cupom->itens()->get()->map(function ($i): string {
            if ($i->pacote_credito_id !== null) {
                return 'pacote '.(PacoteCredito::query()->whereKey($i->pacote_credito_id)->value('nome') ?? '');
            }

            return 'plano '.(Plano::query()->whereKey($i->plano_id)->value('nome') ?? '').' '.$i->ciclo;
        })->all();

        return $nomes === [] ? 'nenhum item' : implode(', ', $nomes);
    }
}
