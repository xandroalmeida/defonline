<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonInterface;

/**
 * STORY-072 / PDR-020 / spec §2.5 — regras de um cupom, sem banco nem tela. O backoffice usa
 * a validação da definição; o checkout (STORY-083) usa `avaliarUso`.
 */
final class CupomRegras
{
    /** Maiúsculas e sem espaços nas pontas: "beta25" e "BETA25" são o mesmo código. */
    public static function normalizarCodigo(string $codigo): string
    {
        return mb_strtoupper(trim($codigo));
    }

    /**
     * Código aleatório fácil de ler e ditar: sem caracteres confundíveis (0/O, 1/I/L). Com 31 símbolos
     * e 8 posições há cerca de 850 bilhões de combinações. A unicidade é conferida por quem grava.
     */
    public static function gerarCodigo(): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $codigo = '';

        for ($i = 0; $i < 8; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        return $codigo;
    }

    /**
     * Erros da definição por campo (`valor`, `vigencia_fim`). Vazio = definição válida.
     * `valor` é o percentual em décimos ou os centavos, conforme o tipo.
     *
     * @return array<string, string>
     */
    public static function errosDaDefinicao(TipoDesconto $tipo, int $valor, CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $erros = [];

        if ($tipo === TipoDesconto::Percentual && ($valor < 1 || $valor > 1000)) {
            $erros['valor'] = 'O percentual deve ficar entre 0,1 e 100.';
        }

        if ($tipo === TipoDesconto::ValorFixo && $valor <= 0) {
            $erros['valor'] = 'O valor fixo deve ser maior que zero.';
        }

        if ($fim->toDateString() < $inicio->toDateString()) {
            $erros['vigencia_fim'] = 'O fim da vigência não pode ser anterior ao início.';
        }

        return $erros;
    }

    /**
     * Pode usar o cupom agora? Devolve o motivo da recusa ou null se pode. A vigência vale por
     * dia inteiro, nas duas pontas. Cupons não são cumulativos: quem chama aplica um por compra.
     */
    public static function avaliarUso(
        bool $ativo,
        CarbonInterface $vigenciaInicio,
        CarbonInterface $vigenciaFim,
        ?int $limiteUsos,
        int $usosFeitos,
        bool $itemCoberto,
        bool $usuarioJaUsou,
        CarbonInterface $agora,
    ): ?MotivoRecusaCupom {
        $hoje = $agora->toDateString();

        return match (true) {
            ! $ativo => MotivoRecusaCupom::Inativo,
            $hoje < $vigenciaInicio->toDateString(), $hoje > $vigenciaFim->toDateString() => MotivoRecusaCupom::ForaDaVigencia,
            $limiteUsos !== null && $usosFeitos >= $limiteUsos => MotivoRecusaCupom::LimiteEsgotado,
            ! $itemCoberto => MotivoRecusaCupom::ItemNaoCoberto,
            $usuarioJaUsou => MotivoRecusaCupom::JaUtilizado,
            default => null,
        };
    }
}
