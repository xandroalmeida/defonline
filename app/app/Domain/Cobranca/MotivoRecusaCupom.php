<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** STORY-072 — por que um cupom não pode ser usado agora. */
enum MotivoRecusaCupom: string
{
    case Inativo = 'inativo';
    case ForaDaVigencia = 'fora_da_vigencia';
    case LimiteEsgotado = 'limite_esgotado';
    case ItemNaoCoberto = 'item_nao_coberto';
    case JaUtilizado = 'ja_utilizado';
    /** STORY-083: o código digitado não é de nenhum cupom. */
    case Inexistente = 'inexistente';

    public function mensagem(): string
    {
        return match ($this) {
            self::Inativo => 'Este cupom foi desativado.',
            self::ForaDaVigencia => 'Este cupom está fora do período de validade.',
            self::LimiteEsgotado => 'Este cupom atingiu o limite de usos.',
            self::ItemNaoCoberto => 'Este cupom não vale para o item escolhido.',
            self::JaUtilizado => 'Você já usou este cupom.',
            self::Inexistente => 'Não encontramos esse cupom. Confira o código.',
        };
    }
}
