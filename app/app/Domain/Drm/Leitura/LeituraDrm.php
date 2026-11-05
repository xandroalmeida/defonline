<?php

declare(strict_types=1);

namespace App\Domain\Drm\Leitura;

use App\Domain\Drm\Competencia;
use App\Domain\Drm\ConteudoDrm;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Faixas;
use App\Domain\Drm\Percentual;
use App\Domain\Drm\Serie\Comparativo;
use App\Domain\Drm\Serie\MesDaSerie;
use App\Domain\Drm\Serie\SerieDrm;
use App\Domain\Drm\Setor;

/**
 * Faróis e leitura em linguagem simples de um mês do DRM (STORY-054).
 *
 * # Determinístico e sem texto livre (CA-3)
 *
 * Mesma entrada, mesma saída. Toda palavra que o usuário lê vem de `config/drm/leitura_frases.php`
 * (o esqueleto dos fatos) ou de `config/drm/leitura.php` (a interpretação de cada farol, da EBC).
 * Este código só escolhe qual frase, na ordem fixa abaixo, e preenche os números:
 *
 *   1. abertura do mês (lucro, zero a zero, prejuízo ou sem faturamento);
 *   2. prejuízo ou mês parado: o custo de manter o negócio e a linha que mais pesou;
 *   3. comparativos do resultado com o mês anterior e a média, e o que mais explica a diferença;
 *   4. um item por farol aceso, do vermelho ao verde.
 *
 * # Farol só onde há faixa calibrada (CA-1)
 *
 * A decisão é de {@see Faixas}, que devolve `null` para faixa `nao_calibrada`. Aqui não se repete
 * a regra: sem farol não entra na lista de faróis nem na leitura, e a prosa segue apoiada nos
 * comparativos (CA-1b). Calibrar uma faixa é editar config; este código não muda.
 *
 * # Derivada, não congelada (CA-8)
 *
 * Recebe o snapshot já lido (`MesDaSerie`) e devolve valores; não escreve em lugar nenhum além do
 * inventário de lacunas do PO (CA-7).
 */
final class LeituraDrm
{
    /**
     * Linhas de nível 1 que movem o resultado, com o sinal do efeito: receita e outras receitas
     * somam; deduções, custo, despesas e retirada subtraem. As linhas totalizadoras (L3, L5, L7, L9,
     * L11) ficam de fora de propósito: são consequência, não causa.
     */
    private const CAUSAS = ['L1' => 1, 'L2' => -1, 'L4' => -1, 'L6' => -1, 'L7b' => 1, 'L8' => -1, 'L10' => -1];

    /** Linhas cujo valor é saída de caixa; "a que mais pesou" sai daqui. L7b entra só se for despesa líquida. */
    private const SAIDAS = ['L2', 'L4', 'L6', 'L8', 'L10'];

    /** O que se paga mesmo sem vender: despesas de funcionamento, financeiras e a retirada do dono. */
    private const CUSTO_DE_MANTER = ['L6', 'L8', 'L10'];

    public function __construct(
        private readonly Faixas $faixas = new Faixas,
        private readonly ConteudoDrm $conteudo = new ConteudoDrm,
    ) {}

    /**
     * @param  MesDaSerie  $mes  o mês lido, no modo `$posicao`
     * @param  SerieDrm  $serie  a série das versões vigentes, de onde saem os comparativos
     * @param  string  $posicao  `fora` ou `dentro`
     * @return Leitura|null `null` só quando o snapshot não tem receita nem resultado final para ler
     */
    public function gerar(MesDaSerie $mes, SerieDrm $serie, Setor $setor, string $posicao): ?Leitura
    {
        $ancora = $mes->codigoAncora();
        $final = $mes->codigoFinal();
        $vendas = $ancora === null ? null : ($mes->nominais[$ancora] ?? null);
        $resultado = $final === null ? null : ($mes->nominais[$final] ?? null);
        if ($final === null || $vendas === null || $resultado === null) {
            return null;
        }

        $farois = $this->farois($mes, $setor);
        $frases = $vendas <= 0
            ? $this->mesSemFaturamento($mes, $setor, $posicao)
            : [...$this->mesComFaturamento($mes, $vendas, $resultado, $setor, $posicao), ...$this->comparativos($mes, $serie, $final, $setor, $posicao)];

        return new Leitura($farois, [...$frases, ...$this->frasesDosFarois($farois, $setor, $posicao)]);
    }

    // ------------------------------------------------------------------ faróis

    /**
     * @return array<string, string>
     */
    private function farois(MesDaSerie $mes, Setor $setor): array
    {
        $farois = [];
        foreach ($this->faixas->linhas($setor->value) as $linha) {
            $percentual = $mes->percentuais[$linha] ?? null;
            $cor = $percentual === null ? null : $this->faixas->farol($linha, (float) $percentual, $setor->value);
            if ($cor !== null) {
                $farois[$linha] = $cor;
            }
        }

        return $farois;
    }

    /**
     * Um item por farol aceso, do vermelho ao verde, e dentro da cor na ordem do arquivo de faixas.
     * Combinação sem texto no pacote vira a reserva neutra e é registrada para o PO (CA-7).
     *
     * @param  array<string, string>  $farois
     * @return list<string>
     */
    private function frasesDosFarois(array $farois, Setor $setor, string $posicao): array
    {
        $frases = [];
        foreach (array_reverse(Faixas::CORES) as $cor) {
            foreach ($farois as $linha => $corDaLinha) {
                if ($corDaLinha !== $cor) {
                    continue;
                }
                $texto = $this->conteudo->leitura($linha, $cor);
                if ($texto === null) {
                    $this->conteudo->registrarLacunaDeLeitura($linha, $cor);
                    $texto = strtr($this->conteudo->fraseDeLeitura('reserva'), [
                        ':linha' => $this->rotulo($linha, $posicao, $setor),
                        ':cor' => $this->conteudo->fraseDeLeitura("cores.{$cor}"),
                    ]);
                }
                $frases[] = $texto;
            }
        }

        return $frases;
    }

    // ------------------------------------------------------------------ o mês

    /**
     * @return list<string>
     */
    private function mesComFaturamento(MesDaSerie $mes, int $vendas, int $resultado, Setor $setor, string $posicao): array
    {
        $chave = $resultado > 0 ? 'lucro' : ($resultado === 0 ? 'zero' : 'prejuizo');
        $frases = [strtr($this->conteudo->fraseDeLeitura("abertura.{$chave}"), [
            ':mes' => $this->mesPorExtenso($mes->competencia),
            ':vendas' => Dinheiro::brl($vendas),
            ':resultado' => Dinheiro::brl(abs($resultado)),
            ':falta' => Dinheiro::brl(abs($resultado)),
        ])];

        if ($resultado < 0 && ($pesada = $this->linhaQueMaisPesou($mes)) !== null) {
            $percentual = $mes->percentuais[$pesada] ?? null;
            $frases[] = strtr($this->conteudo->fraseDeLeitura($percentual === null ? 'mais_pesou' : 'mais_pesou_percentual'), [
                ':linha' => $this->rotulo($pesada, $posicao, $setor),
                ':valor' => Dinheiro::brl((int) $mes->nominais[$pesada]),
                ':percentual' => $percentual === null ? '' : Percentual::formatar($percentual).'%',
            ]);
        }

        return $frases;
    }

    /**
     * Sem faturamento não se interpreta margem (CA-6): diz-se o que o mês mostra, que é o custo de
     * manter o negócio funcionando e onde ele mais pesou.
     *
     * @return list<string>
     */
    private function mesSemFaturamento(MesDaSerie $mes, Setor $setor, string $posicao): array
    {
        $frases = [strtr($this->conteudo->fraseDeLeitura('abertura.sem_faturamento'), [':mes' => $this->mesPorExtenso($mes->competencia)])];

        $custo = 0;
        foreach (self::CUSTO_DE_MANTER as $linha) {
            $custo += max(0, $mes->nominais[$linha] ?? 0);
        }
        if ($custo > 0) {
            $frases[] = strtr($this->conteudo->fraseDeLeitura('parado'), [':valor' => Dinheiro::brl($custo)]);
        }

        if (($pesada = $this->linhaQueMaisPesou($mes)) !== null) {
            $frases[] = strtr($this->conteudo->fraseDeLeitura('mais_pesou'), [
                ':linha' => $this->rotulo($pesada, $posicao, $setor),
                ':valor' => Dinheiro::brl((int) $mes->nominais[$pesada]),
            ]);
        }

        return $frases;
    }

    /**
     * A maior saída do mês. Empate fica com a primeira na ordem da conta. `L7b` só conta quando
     * as outras despesas superam as outras receitas (valor negativo).
     */
    private function linhaQueMaisPesou(MesDaSerie $mes): ?string
    {
        $maior = null;
        $valorMaior = 0;
        foreach (self::SAIDAS as $linha) {
            $valor = $mes->nominais[$linha] ?? null;
            if ($valor !== null && $valor > $valorMaior) {
                $maior = $linha;
                $valorMaior = $valor;
            }
        }

        return $maior;
    }

    // ------------------------------------------------------------------ histórico

    /**
     * O que mudou e por quê (CA-4). Sem histórico, a leitura diz que não há e não inventa tendência.
     *
     * @return list<string>
     */
    private function comparativos(MesDaSerie $mes, SerieDrm $serie, string $final, Setor $setor, string $posicao): array
    {
        $c = $serie->comparativos($mes->competencia, $final);
        $base = $this->nomeDoMes(Competencia::deData($mes->competencia->data->subMonthNoOverflow()));
        $frases = [];

        if ($c->vsAnterior->percentual !== null) {
            $frases[] = strtr($this->conteudo->fraseDeLeitura('anterior.'.$this->direcao($c->vsAnterior->percentual)), [
                ':variacao' => $this->modulo($c->vsAnterior->percentual),
                ':base' => $base,
            ]);
            if (($porQue = $this->porQue($mes, $serie, $setor, $posicao, $base)) !== null) {
                $frases[] = $porQue;
            }
        } else {
            $motivo = (string) $c->vsAnterior->motivo;
            $chave = match ($motivo) {
                Comparativo::PRIMEIRO_LANCAMENTO, Comparativo::SEM_LANCAMENTO, Comparativo::BASE_ZERO => $motivo,
                default => 'padrao',
            };
            $frases[] = strtr($this->conteudo->fraseDeLeitura("sem_comparacao.{$chave}"), [':base' => $base]);
        }

        if ($c->vsMedia->percentual !== null) {
            $frases[] = strtr($this->conteudo->fraseDeLeitura('media.'.$this->direcao($c->vsMedia->percentual)), [
                ':variacao' => $this->modulo($c->vsMedia->percentual),
            ]);
        }

        return $frases;
    }

    /**
     * A causa que mais moveu o resultado contra o mês anterior: maior efeito absoluto entre as linhas
     * de nível 1 que não são totais. `null` se o mês anterior não está na série ou nada se moveu.
     */
    private function porQue(MesDaSerie $mes, SerieDrm $serie, Setor $setor, string $posicao, string $base): ?string
    {
        $anterior = $serie->mes(Competencia::deData($mes->competencia->data->subMonthNoOverflow()));
        if ($anterior === null) {
            return null;
        }

        $causa = null;
        $efeito = 0;
        foreach (self::CAUSAS as $linha => $sinal) {
            $atual = $mes->nominais[$linha] ?? null;
            $antes = $anterior->nominais[$linha] ?? null;
            if ($atual !== null && $antes !== null && abs($sinal * ($atual - $antes)) > $efeito) {
                $causa = $linha;
                $efeito = abs($sinal * ($atual - $antes));
            }
        }
        if ($causa === null) {
            return null;
        }

        return strtr($this->conteudo->fraseDeLeitura('por_que'), [
            ':linha' => $this->rotulo($causa, $posicao, $setor),
            ':de' => $this->moeda((int) $anterior->nominais[$causa]),
            ':para' => $this->moeda((int) $mes->nominais[$causa]),
            ':base' => $base,
        ]);
    }

    // ------------------------------------------------------------------ texto

    /** `acima` | `abaixo` | `igual`, pelo que o usuário vê (1 casa): `-0,04%` é igual, não abaixo. */
    private function direcao(string $variacao): string
    {
        $formatado = Percentual::formatar($variacao);

        return match (true) {
            $formatado === '0,0' => 'igual',
            str_starts_with($formatado, '-') => 'abaixo',
            default => 'acima',
        };
    }

    private function modulo(string $variacao): string
    {
        return ltrim(Percentual::formatar($variacao), '-').'%';
    }

    private function moeda(int $centavos): string
    {
        return ($centavos < 0 ? '−' : '').Dinheiro::brl(abs($centavos));
    }

    private function rotulo(string $linha, string $posicao, Setor $setor): string
    {
        return $this->conteudo->rotuloLinha($linha, $posicao, $setor->value) ?? $linha;
    }

    private function mesPorExtenso(Competencia $competencia): string
    {
        return $competencia->mesPorExtenso().' de '.$competencia->data->year;
    }

    private function nomeDoMes(Competencia $competencia): string
    {
        return mb_strtolower($competencia->mesPorExtenso());
    }
}
